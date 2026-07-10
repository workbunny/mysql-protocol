<?php

declare(strict_types=1);

use Workbunny\MysqlProtocol\Packets\AuthSwitchResponse;
use Workbunny\MysqlProtocol\Packets\BinlogDump;
use Workbunny\MysqlProtocol\Packets\BinlogEvent;
use Workbunny\MysqlProtocol\Packets\Command;
use Workbunny\MysqlProtocol\Packets\HandshakeInitialization;
use Workbunny\MysqlProtocol\Packets\HandshakeResponse;
use Workbunny\MysqlProtocol\Packets\Ok;
use Workbunny\MysqlProtocol\Packets\RegisterSlave;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;
use Workerman\Connection\TcpConnection;

require_once __DIR__ . '/../vendor/autoload.php';

$MYSQL_HOST = '127.0.0.1';
$MYSQL_PORT = 3306;
$MYSQL_USER = 'repl';
$MYSQL_PASS = 'repl';
$SERVER_ID  = 100;

$worker = new \Workerman\Worker();
$worker->name = 'mysql-replication';
$worker->count = 1;

$worker->onWorkerStart = function () use ($MYSQL_HOST, $MYSQL_PORT, $MYSQL_USER, $MYSQL_PASS, $SERVER_ID) {
    $client = new \Workerman\Connection\AsyncTcpConnection("MySQL://{$MYSQL_HOST}:{$MYSQL_PORT}");
    $client->mysqlState = 0;

    $client->onMessage = function (TcpConnection $conn, Binary $binary) use ($MYSQL_USER, $MYSQL_PASS, $SERVER_ID) {
        switch ($conn->mysqlState) {
            // 阶段1: 握手
            case 0:
                $hs = HandshakeInitialization::unpack($binary);
                echo "[REPL] Server: {$hs['server_version']}\n";
                $authData = array_merge($hs['auth_plugin_data_part1'], $hs['auth_plugin_data_part2']);
                $authResp = '';
                if ($hs['auth_plugin_name'] === HandshakeInitialization::AUTH_PLUGIN_mysql_NATIVE_PLUGIN) {
                    $passBytes = Binary::StringToBytes($MYSQL_PASS);
                    $stage1 = sha1($MYSQL_PASS, true);
                    $stage2 = sha1($stage1, true);
                    $stage3 = sha1(Binary::BytesToString($authData) . $stage2, true);
                    $authResp = $stage1 ^ $stage3;
                }
                $conn->send(HandshakeResponse::pack([
                    'packet_id'        => ($hs['packet_id'] + 1) & 0xFF,
                    'capability_flags' => $hs['capability_flags'],
                    'max_packet_size'  => 1073741824,
                    'character_set'    => 33,
                    'username'         => $MYSQL_USER,
                    'auth_plugin'      => $hs['auth_plugin_name'],
                    'auth_response'    => $authResp,
                ]));
                $conn->mysqlState = 1;
                break;

            // 阶段2: 认证结果 → 注册从库 → 请求 binlog
            case 1:
                $ok = Ok::unpack($binary);
                echo "[REPL] Connected. Registering slave (server_id={$SERVER_ID})...\n";

                // 注册为从库
                $conn->send(RegisterSlave::pack([
                    'packet_id' => ($ok['packet_id'] + 1) & 0xFF,
                    'server_id' => $SERVER_ID,
                    'hostname'  => '',
                    'user'      => '',
                    'password'  => '',
                    'port'      => 0,
                ]));
                $conn->mysqlState = 2;
                break;

            // 阶段3: 注册响应 → 请求 binlog dump
            case 2:
                echo "[REPL] Slave registered. Requesting binlog dump...\n";

                // 请求 binlog 事件流（从位置 4 开始，即 binlog 开头）
                $conn->send(BinlogDump::pack([
                    'packet_id'       => 0,
                    'binlog_pos'      => 4,
                    'flags'           => 0,
                    'server_id'       => $SERVER_ID,
                    'binlog_filename' => '', // 空字符串表示从第一个 binlog 文件开始
                ]));
                $conn->mysqlState = 3;
                break;

            // 阶段4: 持续接收 binlog 事件
            case 3:
                $parsed = Packet::parser(null, $binary);
                $payload = $binary->unpack();
                // 跳过 4 字节 MySQL 包头，剩余为 binlog 事件
                $eventBytes = array_slice($payload, 4);
                if (empty($eventBytes)) {
                    break;
                }
                try {
                    $eventBinary = new Binary($eventBytes);
                    $event = BinlogEvent::unpack($eventBinary);

                    echo "\n[EVENT] {$event['event_type_name']} (type=0x" . dechex($event['event_type']) . ")\n";
                    echo "  timestamp: " . date('Y-m-d H:i:s', $event['timestamp']) . "\n";
                    echo "  server_id: {$event['server_id']}\n";
                    echo "  event_size: {$event['event_size']}\n";
                    echo "  log_pos: {$event['log_pos']}\n";

                    // 输出事件体详情
                    if ($event['event_data'] !== null) {
                        switch ($event['event_type']) {
                            case BinlogEvent::FORMAT_DESCRIPTION_EVENT:
                                $d = $event['event_data'];
                                echo "  binlog_version: {$d['binlog_version']}\n";
                                echo "  server_version: {$d['server_version']}\n";
                                break;
                            case BinlogEvent::QUERY_EVENT:
                                $d = $event['event_data'];
                                echo "  schema: {$d['schema']}\n";
                                echo "  sql: {$d['sql']}\n";
                                break;
                            case BinlogEvent::ROTATE_EVENT:
                                $d = $event['event_data'];
                                echo "  next_file: {$d['filename']} @ {$d['position']}\n";
                                break;
                            case BinlogEvent::XID_EVENT:
                                $d = $event['event_data'];
                                echo "  xid: {$d['xid']}\n";
                                break;
                            case BinlogEvent::GTID_EVENT:
                                $d = $event['event_data'];
                                echo "  gtid: {$d['sid']}:{$d['gno']}\n";
                                break;
                            case BinlogEvent::TABLE_MAP_EVENT:
                                $d = $event['event_data'];
                                echo "  table: {$d['schema']}.{$d['table']} (id={$d['table_id']})\n";
                                echo "  columns: {$d['column_count']}\n";
                                break;
                            case BinlogEvent::WRITE_ROWS_EVENT_V2:
                                echo "  >> INSERT event\n";
                                break;
                            case BinlogEvent::UPDATE_ROWS_EVENT_V2:
                                echo "  >> UPDATE event\n";
                                break;
                            case BinlogEvent::DELETE_ROWS_EVENT_V2:
                                echo "  >> DELETE event\n";
                                break;
                            case BinlogEvent::HEARTBEAT_EVENT:
                                echo "  (heartbeat)\n";
                                break;
                        }
                    }
                } catch (\Throwable $e) {
                    echo "[EVENT] Parse error: {$e->getMessage()}\n";
                }
                break;
        }
    };

    $client->onClose = function () {
        echo "[REPL] Connection closed\n";
    };

    $client->connect();
    echo "[REPL] Connecting to MySQL for replication...\n";
};

\Workerman\Worker::runAll();
