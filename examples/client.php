<?php

declare(strict_types=1);

/**
 * MySQL 客户端示例
 *
 * 演示完整的客户端连接流程：
 *   1. 接收服务器握手包
 *   2. 发送握手响应（含认证）
 *   3. 处理认证结果 / 认证切换
 *   4. 执行 SQL 查询并解析结果集
 *   5. 心跳保活
 *
 * 运行: php examples/client.php start
 */

use Workbunny\MysqlProtocol\Packets\AuthSwitchRequest;
use Workbunny\MysqlProtocol\Packets\AuthSwitchResponse;
use Workbunny\MysqlProtocol\Packets\Command;
use Workbunny\MysqlProtocol\Packets\EOF;
use Workbunny\MysqlProtocol\Packets\Error;
use Workbunny\MysqlProtocol\Packets\Field;
use Workbunny\MysqlProtocol\Packets\HandshakeInitialization;
use Workbunny\MysqlProtocol\Packets\HandshakeResponse;
use Workbunny\MysqlProtocol\Packets\Ok;
use Workbunny\MysqlProtocol\Packets\ResultSetHeader;
use Workbunny\MysqlProtocol\Packets\RowData;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;
use Workerman\Connection\TcpConnection;

require_once __DIR__ . '/../vendor/autoload.php';

// ─── 配置 ───────────────────────────────────────────────
$MYSQL_HOST = '127.0.0.1';
$MYSQL_PORT = 3306;
$MYSQL_USER = 'root';
$MYSQL_PASS = 'root';       // 明文密码，仅用于演示
$MYSQL_DB   = '';           // 默认数据库，留空不选择
$QUERY_SQL  = 'SELECT 1 AS result, NOW() AS now';

// ─── Worker ─────────────────────────────────────────────
$worker = new \Workerman\Worker();
$worker->name = 'mysql-client';
$worker->count = 1;

$worker->onWorkerStart = function () use ($MYSQL_HOST, $MYSQL_PORT, $MYSQL_USER, $MYSQL_PASS, $MYSQL_DB, $QUERY_SQL) {
    // MySQL:// 协议前缀会自动使用 Protocols\MySQL 进行分包
    $client = new \Workerman\Connection\AsyncTcpConnection("MySQL://{$MYSQL_HOST}:{$MYSQL_PORT}");

    // 握手状态机: 0=等待握手包, 1=等待认证结果, 2=已连接
    $client->mysqlState    = 0;
    $client->mysqlPacketId = 0;
    $client->mysqlFields   = [];
    $client->mysqlRows     = [];

    // ─── 心跳定时器 ──────────────────────────────────────
    $client->onConnect = function (TcpConnection $conn) {
        \Workerman\Timer::add(30, function () use ($conn) {
            if ($conn->mysqlState >= 2) {
                $conn->send(Command::pack([
                    'packet_id' => ($conn->mysqlPacketId++) & 0xFF,
                    'command'   => Command::COM_PING,
                ]));
                echo "[HEARTBEAT] COM_PING sent\n";
            }
        });
    };

    // ─── 消息处理 ────────────────────────────────────────
    $client->onMessage = function (TcpConnection $conn, Binary $binary) use ($MYSQL_USER, $MYSQL_PASS, $MYSQL_DB, $QUERY_SQL) {
        switch ($conn->mysqlState) {
            // ═══ 阶段 1: 接收服务器握手包 ═════════════════
            case 0:
                $handshake = HandshakeInitialization::unpack($binary);
                $conn->mysqlPacketId = $handshake['packet_id'];

                echo "[HANDSHAKE] Server: {$handshake['server_version']}\n";
                echo "[HANDSHAKE] Protocol: v{$handshake['protocol_version']}\n";
                echo "[HANDSHAKE] Connection ID: {$handshake['connection_id']}\n";
                echo "[HANDSHAKE] Auth plugin: {$handshake['auth_plugin_name']}\n";
                echo "[HANDSHAKE] Capabilities: 0x" . dechex($handshake['capability_flags']) . "\n";

                // 计算 auth_response（mysql_native_password）
                $authData = array_merge(
                    $handshake['auth_plugin_data_part1'],
                    $handshake['auth_plugin_data_part2']
                );
                $authResponse = '';
                if ($handshake['auth_plugin_name'] === HandshakeInitialization::AUTH_PLUGIN_mysql_NATIVE_PLUGIN) {
                    $authResponse = mysqlNativePassword($MYSQL_PASS, $authData);
                } elseif ($handshake['auth_plugin_name'] === HandshakeInitialization::AUTH_PLUGIN_CACHING_SHA2_PASSWORD) {
                    // caching_sha2_password 在非 SSL 连接下需要完整认证流程
                    // 这里先发送空响应，等待服务器后续质询
                    $authResponse = '';
                }

                $capabilities = $handshake['capability_flags'];

                $conn->send(HandshakeResponse::pack([
                    'packet_id'        => ($conn->mysqlPacketId + 1) & 0xFF,
                    'capability_flags' => $capabilities,
                    'max_packet_size'  => 1073741824,
                    'character_set'    => 33, // utf8mb3
                    'username'         => $MYSQL_USER,
                    'database'         => $MYSQL_DB ?: null,
                    'auth_plugin'      => $handshake['auth_plugin_name'],
                    'auth_response'    => $authResponse,
                ]));
                $conn->mysqlPacketId += 2;
                $conn->mysqlState = 1;
                break;

            // ═══ 阶段 2: 处理认证结果 ═════════════════════
            case 1:
                $class = Packet::getPacketClass($binary);

                if ($class === Ok::class) {
                    // 认证成功
                    $ok = Ok::unpack($binary);
                    echo "[AUTH] Success! status=0x" . dechex($ok['status_flags']) . "\n";
                    $conn->mysqlState = 2;
                    sendQuery($conn, $QUERY_SQL);
                } elseif ($class === Error::class) {
                    // 认证失败
                    $err = Error::unpack($binary);
                    echo "[AUTH] Failed: [{$err['error_code']}] {$err['error_message']}\n";
                    $conn->close();
                } elseif ($class === AuthSwitchRequest::class) {
                    // 服务器要求切换认证方式
                    $switchReq = AuthSwitchRequest::unpack($binary);
                    echo "[AUTH] Switch to: {$switchReq['plugin_name']}\n";

                    // 根据新插件计算响应
                    $newAuthData = $switchReq['auth_plugin_data'];
                    $newResponse = '';
                    if ($switchReq['plugin_name'] === HandshakeInitialization::AUTH_PLUGIN_mysql_NATIVE_PLUGIN) {
                        $newResponse = mysqlNativePassword($MYSQL_PASS, $newAuthData);
                    }

                    $conn->send(AuthSwitchResponse::pack([
                        'packet_id'     => ($conn->mysqlPacketId++) & 0xFF,
                        'auth_response' => $newResponse,
                    ]));
                    // 继续等待认证结果（保持 state=1）
                } else {
                    // 可能是 AuthMoreDataRequest（caching_sha2_password 的完整认证流程）
                    echo "[AUTH] Unexpected packet class: $class\n";
                    echo $binary->dump();
                }
                break;

            // ═══ 阶段 3: 处理查询结果 ═════════════════════
            case 2:
                handleQueryResult($conn, $binary);
                break;
        }
    };

    $client->onError = function (TcpConnection $conn, $code, $msg) {
        echo "[ERROR] $code: $msg\n";
    };

    $client->onClose = function () {
        echo "[CLOSED] Connection closed\n";
    };

    $client->connect();
    echo "[CLIENT] Connecting to {$MYSQL_HOST}:{$MYSQL_PORT}...\n";
};

\Workerman\Worker::runAll();

// ─── 辅助函数 ────────────────────────────────────────────

/**
 * 发送查询
 */
function sendQuery(TcpConnection $conn, string $sql): void
{
    $conn->mysqlFields = [];
    $conn->mysqlRows   = [];
    echo "\n[QUERY] {$sql}\n";
    $conn->send(Command::pack([
        'packet_id' => ($conn->mysqlPacketId++) & 0xFF,
        'command'   => Command::COM_QUERY,
        'data'      => $sql,
    ]));
}

/**
 * 处理查询结果
 */
function handleQueryResult(TcpConnection $conn, Binary $binary): void
{
    $class = Packet::getPacketClass($binary);

    switch ($class) {
        case ResultSetHeader::class:
            $header = ResultSetHeader::unpack($binary);
            echo "[RESULT] {$header['field_count']} columns\n";
            break;

        case Field::class:
            $field = Field::unpack($binary);
            $conn->mysqlFields[] = $field;
            echo "  [FIELD] {$field['name']} (type={$field['type']}, len={$field['column_length']})\n";
            break;

        case RowData::class:
            $row = RowData::unpack($binary);
            $conn->mysqlRows[] = $row['values'];

            // 打印行数据
            $values = array_map(fn($v) => $v ?? 'NULL', $row['values']);
            echo "  [ROW]   " . implode(' | ', $values) . "\n";
            break;

        case EOF::class:
            // 结果集结束
            $fieldCount = count($conn->mysqlFields);
            $rowCount   = count($conn->mysqlRows);
            echo "[DONE] {$rowCount} row(s) in set ({$fieldCount} columns)\n\n";

            // 示例：查询完成后可以继续发送下一条命令
            // sendQuery($conn, 'SELECT 2');
            break;

        case Ok::class:
            $ok = Ok::unpack($binary);
            echo "[OK] affected={$ok['affected_rows']}, last_insert_id={$ok['last_insert_id']}\n\n";
            break;

        case Error::class:
            $err = Error::unpack($binary);
            echo "[ERROR] [{$err['error_code']}] {$err['error_message']}\n\n";
            break;

        default:
            echo "[UNKNOWN] Packet class: " . ($class ?? 'null') . "\n";
            echo $binary->dump();
            break;
    }
}

/**
 * mysql_native_password 认证算法
 *
 * SHA1(password) XOR SHA1(salt + SHA1(SHA1(password)))
 *
 * @param string $password 明文密码
 * @param array  $salt     服务器提供的 auth_plugin_data（字节数组）
 * @return string 20 字节的认证响应
 */
function mysqlNativePassword(string $password, array $salt): string
{
    if ($password === '') {
        return '';
    }
    $saltStr = Binary::BytesToString($salt);
    $stage1 = sha1($password, true);
    $stage2 = sha1($stage1, true);
    $stage3 = sha1($saltStr . $stage2, true);
    return $stage1 ^ $stage3;
}
