<?php

declare(strict_types=1);

use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;
use Workerman\Connection\TcpConnection;

require_once __DIR__ . '/../vendor/autoload.php';

$server = new \Workerman\Worker("MySQL://0.0.0.0:8844");
$server->name = 'workbunny-mysql-server';
$server->count = 2;
$server->onConnect = function (TcpConnection $source) {
    // 创建与MySQL-server的连接
    $target = new \Workerman\Connection\AsyncTcpConnection("MySQL://host.docker.internal:3306");
    // 管道传输
    $target->pipe($source);
    $target->onMessage = function (TcpConnection $target, Binary $data) use ($source) {
        // 客户端的来源信息
        dump('-------------------------------------------------------------------------------------------------------------------');
        dump('Server', $data->dump(), Packet::parser(null, $data));
        dump('Packet-Class: ' . Packet::getPacketClass($data));
        dump('-------------------------------------------------------------------------------------------------------------------');
        $source->send($data);
    };

    $source->pipe($target);
    $source->onMessage = function (TcpConnection $source, Binary $data) use ($target) {
        dump('-------------------------------------------------------------------------------------------------------------------');
        dump('Client', $data->dump(), Packet::parser(null, $data));
        dump('-------------------------------------------------------------------------------------------------------------------');
        $target->send($data);
    };

    $target->connect();
};

\Workerman\Worker::runAll();
