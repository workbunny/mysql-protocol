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

$server = new \Workerman\Worker("MySQL://0.0.0.0:8844");
$server->name = 'workbunny-mysql-server';
$server->count = 2;
$server->onConnect = function (TcpConnection $connection) {
    // 构造握手包所需数据（这些数据可根据实际服务器配置、能力及认证数据来定制）
    $handshakeData = [
        // 通常，协议版本固定为 10
        'protocol_version'   => 10,
        // 服务器版本
        'server_version'     => '8.4.3-workbunny',
        // 连接 ID（示例值，可以自定义）
        'connection_id'      => $connection->id,
        // 能力标识
        'capability_flags'   => 3758096383,
        // 字符集索引
        'character_set_index'=> 255,
        // 状态标识
        'status_flags'       => 2,
        // 认证数据，必须至少 8 字节（示例数据）
        'auth_plugin_data'   => Packet::authData(21),
        // 认证插件名称（MySQL 8 默认认证插件通常是 caching_sha2_password）
        'auth_plugin_name'   => 'caching_sha2_password'
    ];
    // 生成握手包的 Binary 对象
    $binary = HandshakeInitialization::pack($handshakeData);
    // 握手状态机
    $connection->mysql_handshake_status = 0;
    $connection->send($binary);
};

$server->onMessage = function (TcpConnection $connection, Binary $binary) {
    // 友好打印
    dump($binary->dump());
    // 判断状态机
    if (!isset($connection->mysql_handshake_status)) {
        $connection->close(Error::pack([
            'error_code' => 0,
            'sql_state'  => 'HY000',
            'message'    => 'Invalid connection.',
        ]));
        return;
    }
    // 状态机：0 握手阶段，1 已经握手
    if ($connection->mysql_handshake_status < 1) {
        // 握手响应信息获取
        $handshakeResponse = HandshakeResponse::unpack($binary);
        dump($handshakeResponse);
        // todo 可以对 数据信息进行验证，这里暂时不验证用户信息等

        // 状态机：1 已经握手
        $connection->mysql_handshake_status = 1;
        $connection->send(Ok::pack([
            'packet_id' => $handshakeResponse['packet_id'] + 1
        ]));
    } else { // command包
        $command = Command::unpack($binary);
        $connection->send(Ok::pack([
            'packet_id' => $command['packet_id'] + 1,
        ]));
    }
};

\Workerman\Worker::runAll();
