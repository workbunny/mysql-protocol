<?php

declare(strict_types=1);

use Workbunny\MysqlProtocol\Constants\MySQLColumnType;
use Workbunny\MysqlProtocol\Constants\ServerStatus;
use Workbunny\MysqlProtocol\Packets\Command;
use Workbunny\MysqlProtocol\Packets\EOF;
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

$server = new \Workerman\Worker('MySQL://0.0.0.0:8844');
$server->name = 'mysql-mock-server';
$server->count = 2;

$server->onConnect = function (TcpConnection $connection) {
    echo "\n[CONNECT] {$connection->getRemoteAddress()}\n";
    $connection->send(HandshakeInitialization::pack([
        'protocol_version'    => 10,
        'server_version'      => '8.4.3-workbunny-mock',
        'connection_id'       => $connection->id,
        'capability_flags'    => 0x807FFFFF,
        'character_set_index' => 255,
        'status_flags'        => ServerStatus::SERVER_STATUS_AUTOCOMMIT,
        'auth_plugin_data'    => Packet::authData(21),
        'auth_plugin_name'    => HandshakeInitialization::AUTH_PLUGIN_mysql_NATIVE_PLUGIN,
    ]));
    $connection->mysqlState = 0;
};

$server->onMessage = function (TcpConnection $connection, Binary $binary) {
    if (!isset($connection->mysqlState)) {
        $connection->close();
        return;
    }
    if ($connection->mysqlState < 1) {
        $resp = HandshakeResponse::unpack($binary);
        echo "[HANDSHAKE] User: {$resp['username']}\n";
        $connection->send(Ok::pack([
            'packet_id'    => ($resp['packet_id'] + 1) & 0xFF,
            'status_flags' => ServerStatus::SERVER_STATUS_AUTOCOMMIT,
        ]));
        $connection->mysqlState = 1;
        echo "[HANDSHAKE] Authenticated\n";
        return;
    }
    $cmd     = Command::unpack($binary);
    $nextId  = ($cmd['packet_id'] + 1) & 0xFF;
    $okFlags = ['packet_id' => $nextId, 'status_flags' => ServerStatus::SERVER_STATUS_AUTOCOMMIT];
    switch ($cmd['command']) {
        case Command::COM_QUIT:
            echo "[CMD] COM_QUIT\n";
            $connection->close();
            break;
        case Command::COM_PING:
            echo "[CMD] COM_PING\n";
            $connection->send(Ok::pack($okFlags));
            break;
        case Command::COM_INIT_DB:
            echo "[CMD] COM_INIT_DB: {$cmd['data']}\n";
            $connection->send(Ok::pack($okFlags));
            break;
        case Command::COM_FIELD_LIST:
            $connection->send(EOF::pack($okFlags));
            break;
        case Command::COM_QUERY:
            $sql = trim($cmd['data'] ?? '');
            echo "[CMD] COM_QUERY: {$sql}\n";
            handleQuery($connection, $nextId, $sql);
            break;
        default:
            $connection->send(Ok::pack($okFlags));
            break;
    }
};

$server->onClose = function (TcpConnection $connection) {
    echo "[DISCONNECT] Client closed\n";
};

\Workerman\Worker::runAll();

function handleQuery(TcpConnection $conn, int $nextId, string $sql): void
{
    $sqlUpper = strtoupper(trim($sql));
    if (preg_match('/^SELECT\s+1\s+AS\s+(\w+)/i', $sql, $m)) {
        $colName = $m[1];
        $conn->send(ResultSetHeader::pack(['packet_id' => $nextId++, 'field_count' => 1]));
        $conn->send(Field::pack([
            'packet_id'     => $nextId++,
            'catalog'       => 'def',
            'name'          => $colName,
            'org_name'      => $colName,
            'character_set' => 63,
            'column_length' => 1,
            'type'          => MySQLColumnType::MYSQL_TYPE_LONGLONG,
        ]));
        $conn->send(EOF::pack(['packet_id' => $nextId++, 'status_flags' => ServerStatus::SERVER_STATUS_AUTOCOMMIT]));
        $conn->send(RowData::pack(['packet_id' => $nextId++, 'values' => ['1']]));
        $conn->send(EOF::pack(['packet_id' => $nextId++, 'status_flags' => ServerStatus::SERVER_STATUS_AUTOCOMMIT]));
        echo "[QUERY] 1 row in set\n";
        return;
    }
    if (str_starts_with($sqlUpper, 'SELECT') || str_starts_with($sqlUpper, 'SHOW')) {
        $conn->send(ResultSetHeader::pack(['packet_id' => $nextId++, 'field_count' => 0]));
        $conn->send(EOF::pack(['packet_id' => $nextId++, 'status_flags' => ServerStatus::SERVER_STATUS_AUTOCOMMIT]));
        echo "[QUERY] Empty set\n";
        return;
    }
    $conn->send(Ok::pack([
        'packet_id'      => $nextId,
        'affected_rows'  => 1,
        'status_flags'   => ServerStatus::SERVER_STATUS_AUTOCOMMIT,
    ]));
    echo "[QUERY] OK, 1 row affected\n";
}
