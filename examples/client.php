<?php

declare(strict_types=1);

use Workbunny\MysqlProtocol\Packets\HandshakeInitialization;
use Workbunny\MysqlProtocol\Packets\HandshakeResponse;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;
use Workbunny\MysqlProtocol\Packets\Error;
use Workbunny\MysqlProtocol\Packets\Command;
use Workbunny\MysqlProtocol\Packets\Ok;
use Workerman\Connection\TcpConnection;

require_once __DIR__ . '/../vendor/autoload.php';

$server = new \Workerman\Worker();
$server->name = 'workbunny-mysql-client';
$server->onWorkerStart = function () {
    global $clientMysqlHandshakeStatus;
    $clientMysqlHandshakeStatus = $clientMysqlHandshakeStatus ?: 0;
    $client = new \Workerman\Connection\AsyncTcpConnection("MySQL://host.docker.internal:3306");

    $client->onConnect = function (TcpConnection $connection) use (&$clientMysqlHandshakeStatus) {
        $connection->errorHandler = function (Throwable $exception) {
            dump($exception);
        };
        // 模拟心跳
        \Workerman\Timer::add(30, function () use ($connection, &$clientMysqlHandshakeStatus) {
            if ($clientMysqlHandshakeStatus > 1) {
                $connection->send(Command::pack([
                    'command'   => Command::COM_QUERY,
                    'data'      => 'SELECT 1'
                ]));
            }
        });
    };

    $client->onMessage = function (TcpConnection $connection, Binary $binary) use (&$clientMysqlHandshakeStatus) {
        dump($binary->dump());
        // 还未握手
        if ($clientMysqlHandshakeStatus < 1) {
            // 解析握手包
            $handshakeInitialization = HandshakeInitialization::unpack($binary);
            $clientMysqlHandshakeStatus = 1;
            $connection->send(HandshakeResponse::pack([
                'packet_id'         => $handshakeInitialization['packet_id'] + 1,
                "capability_flags"  => $handshakeInitialization['capability_flags'],
                "max_packet_size"   => 1073741824,
                "character_set"     => 33,
                "username"          => "root",
                "database"          => null,
                "auth_plugin"       => $handshakeInitialization['auth_plugin_name'],
                "auth_response"     => "",
            ]));
        }
        // 握手还未确认
        elseif ($clientMysqlHandshakeStatus < 2) {
            if (!$class = Packet::getPacketClass($binary)) {
                echo "Error!\n";
                return;
            }
            $result = $class::unpack($binary);
            dump($result);
            // todo判定是否握手成功

        }
        // 握手成功
        else {

            dump($binary->dump());
        }
    };

    $client->connect();
};

\Workerman\Worker::runAll();
