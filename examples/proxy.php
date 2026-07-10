<?php

declare(strict_types=1);

/**
 * MySQL 代理服务器示例
 *
 * 监听客户端连接，将协议包转发给后端 MySQL 服务器，同时可拦截检查流量。
 *
 * 功能演示：
 *   - 双向转发客户端与后端之间的所有 MySQL 协议包
 *   - 自动识别包类型并输出日志
 *   - 拦截 COM_QUERY 并记录 SQL 语句
 *   - 拦截 Error 包并高亮显示
 *
 * 运行: php examples/proxy.php start
 *
 * 连接方式: mysql -h 127.0.0.1 -P 8844 -u root -p
 */

use Workbunny\MysqlProtocol\Packets\Command;
use Workbunny\MysqlProtocol\Packets\Error;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\TcpConnection;

require_once __DIR__ . '/../vendor/autoload.php';

// ─── 配置 ───────────────────────────────────────────────
$LISTEN_HOST  = '0.0.0.0';
$LISTEN_PORT  = 8844;
$BACKEND_HOST = '127.0.0.1';
$BACKEND_PORT = 3306;

// ─── Worker ─────────────────────────────────────────────
$proxy = new \Workerman\Worker("MySQL://{$LISTEN_HOST}:{$LISTEN_PORT}");
$proxy->name = 'mysql-proxy';
$proxy->count = 2;

$proxy->onConnect = function (TcpConnection $client) use ($BACKEND_HOST, $BACKEND_PORT) {
    echo "\n[CONNECT] Client {$client->getRemoteAddress()} connected\n";

    // 创建到后端 MySQL 的异步连接
    $backend = new AsyncTcpConnection("MySQL://{$BACKEND_HOST}:{$BACKEND_PORT}");

    // 保存互相引用，方便关闭时联动
    $client->backend = $backend;
    $backend->client = $client;

    // ─── 客户端 → 代理 → 后端 ────────────────────────────
    $client->onMessage = function (TcpConnection $client, Binary $data) use ($backend) {
        $parsed = Packet::parser(null, $data);
        $class  = Packet::getPacketClass($data);

        if ($class === Command::class) {
            $cmd = Command::unpack($data);
            $cmdName = match ($cmd['command']) {
                Command::COM_QUERY             => 'COM_QUERY',
                Command::COM_PING              => 'COM_PING',
                Command::COM_QUIT              => 'COM_QUIT',
                Command::COM_INIT_DB           => 'COM_INIT_DB',
                Command::COM_FIELD_LIST        => 'COM_FIELD_LIST',
                Command::COM_STMT_PREPARE      => 'COM_STMT_PREPARE',
                Command::COM_STMT_EXECUTE      => 'COM_STMT_EXECUTE',
                Command::COM_STMT_CLOSE        => 'COM_STMT_CLOSE',
                Command::COM_STMT_RESET        => 'COM_STMT_RESET',
                Command::COM_RESET_CONNECTION  => 'COM_RESET_CONNECTION',
                default                        => 'COM_0x' . dechex($cmd['command']),
            };
            if ($cmd['command'] === Command::COM_QUERY && $cmd['data']) {
                echo "[CLIENT → BACKEND] {$cmdName}: {$cmd['data']}\n";
            } else {
                echo "[CLIENT → BACKEND] {$cmdName}\n";
            }
        } else {
            $shortClass = $class ? substr(strrchr($class, '\\'), 1) : 'raw';
            echo "[CLIENT → BACKEND] packet_id={$parsed['packet_id']}, len={$parsed['packet_length']}, type={$shortClass}\n";
        }

        // 转发给后端
        $backend->send($data);
    };

    // ─── 后端 → 代理 → 客户端 ────────────────────────────
    $backend->onMessage = function (TcpConnection $backend, Binary $data) use ($client) {
        $parsed = Packet::parser(null, $data);
        $class  = Packet::getPacketClass($data);

        if ($class === Error::class) {
            $err = Error::unpack($data);
            echo "[BACKEND → CLIENT] ERROR [{$err['error_code']}] {$err['sql_state']}: {$err['error_message']}\n";
        } else {
            $shortClass = $class ? substr(strrchr($class, '\\'), 1) : 'raw';
            echo "[BACKEND → CLIENT] packet_id={$parsed['packet_id']}, len={$parsed['packet_length']}, type={$shortClass}\n";
        }

        // 转发给客户端
        $client->send($data);
    };

    // ─── 关闭联动 ────────────────────────────────────────
    $client->onClose = function (TcpConnection $client) {
        echo "[DISCONNECT] Client closed\n";
        if (isset($client->backend)) {
            $client->backend->close();
        }
    };

    $backend->onClose = function (TcpConnection $backend) {
        echo "[DISCONNECT] Backend closed\n";
        if (isset($backend->client)) {
            $backend->client->close();
        }
    };

    $backend->connect();
    echo "[PROXY] Backend connecting to {$BACKEND_HOST}:{$BACKEND_PORT}\n";
};

\Workerman\Worker::runAll();
