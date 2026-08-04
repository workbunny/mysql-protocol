<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Workbunny\MysqlProtocol\Packets\AuthMoreDataRequest;
use Workbunny\MysqlProtocol\Packets\AuthMoreDataResponse;
use Workbunny\MysqlProtocol\Packets\AuthSwitchRequest;
use Workbunny\MysqlProtocol\Packets\AuthSwitchResponse;
use Workbunny\MysqlProtocol\Packets\BinlogDump;
use Workbunny\MysqlProtocol\Packets\BinlogDumpGTID;
use Workbunny\MysqlProtocol\Packets\Command;
use Workbunny\MysqlProtocol\Packets\EOF;
use Workbunny\MysqlProtocol\Packets\Error;
use Workbunny\MysqlProtocol\Packets\Field;
use Workbunny\MysqlProtocol\Packets\HandshakeInitialization;
use Workbunny\MysqlProtocol\Packets\HandshakeResponse;
use Workbunny\MysqlProtocol\Packets\Ok;
use Workbunny\MysqlProtocol\Packets\RegisterSlave;
use Workbunny\MysqlProtocol\Packets\ResultSetHeader;
use Workbunny\MysqlProtocol\Packets\RowData;
use Workbunny\MysqlProtocol\Packets\StmtExecute;
use Workbunny\MysqlProtocol\Packets\StmtPrepare;
use Workbunny\MysqlProtocol\Packets\StmtPrepareOk;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

class PacketRoundTripTest extends TestCase
{
    public function testOkRoundTrip(): void
    {
        $data = [
            'packet_id'      => 2,
            'affected_rows'  => 10,
            'last_insert_id' => 5,
            'status_flags'   => 0x0002,
            'warnings'       => 0,
            'info'           => 'Test info',
        ];
        $binary = Ok::pack($data);
        $result = Ok::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['affected_rows'], $result['affected_rows']);
        $this->assertSame($data['last_insert_id'], $result['last_insert_id']);
        $this->assertSame($data['status_flags'], $result['status_flags']);
        $this->assertSame($data['warnings'], $result['warnings']);
        $this->assertSame($data['info'], $result['info']);
    }

    public function testOkWithEmptyInfo(): void
    {
        $data = [
            'packet_id'      => 1,
            'affected_rows'  => 0,
            'last_insert_id' => 0,
            'status_flags'   => 0,
            'warnings'       => 0,
        ];
        $binary = Ok::pack($data);
        $result = Ok::unpack($binary);
        $this->assertNull($result['info']);
    }

    public function testErrorRoundTrip(): void
    {
        $data = [
            'packet_id'     => 3,
            'error_code'    => 1064,
            'sql_state'     => '42000',
            'error_message' => 'You have an error in your SQL syntax',
        ];
        $binary = Error::pack($data);
        $result = Error::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['error_code'], $result['error_code']);
        $this->assertSame($data['sql_state'], $result['sql_state']);
        $this->assertSame($data['error_message'], $result['error_message']);
    }

    public function testEOFRoundTrip(): void
    {
        $data = [
            'packet_id'    => 5,
            'warnings'     => 3,
            'status_flags' => 0x0002,
        ];
        $binary = EOF::pack($data);
        $result = EOF::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['warnings'], $result['warnings']);
        $this->assertSame($data['status_flags'], $result['status_flags']);
    }

    public function testCommandRoundTrip(): void
    {
        $data = [
            'packet_id' => 1,
            'command'   => Command::COM_QUERY,
            'data'      => 'SELECT 1',
        ];
        $binary = Command::pack($data);
        $result = Command::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['command'], $result['command']);
        $this->assertSame($data['data'], $result['data']);
    }

    public function testCommandWithoutData(): void
    {
        $data = [
            'packet_id' => 1,
            'command'   => Command::COM_PING,
        ];
        $binary = Command::pack($data);
        $result = Command::unpack($binary);
        $this->assertSame($data['command'], $result['command']);
        $this->assertNull($result['data']);
    }

    public function testResultSetHeaderRoundTrip(): void
    {
        $data = [
            'packet_id'   => 1,
            'field_count' => 3,
        ];
        $binary = ResultSetHeader::pack($data);
        $result = ResultSetHeader::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['field_count'], $result['field_count']);
    }

    public function testFieldRoundTrip(): void
    {
        $data = [
            'packet_id'     => 2,
            'catalog'       => 'def',
            'schema'        => 'test_db',
            'table'         => 'users',
            'org_table'     => 'users',
            'name'          => 'id',
            'org_name'      => 'id',
            'character_set' => 33,
            'column_length' => 4,
            'type'          => 0x03,
            'flags'         => 0x5003,
            'decimals'      => 0,
        ];
        $binary = Field::pack($data);
        $result = Field::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['catalog'], $result['catalog']);
        $this->assertSame($data['schema'], $result['schema']);
        $this->assertSame($data['table'], $result['table']);
        $this->assertSame($data['org_table'], $result['org_table']);
        $this->assertSame($data['name'], $result['name']);
        $this->assertSame($data['org_name'], $result['org_name']);
        $this->assertSame($data['character_set'], $result['character_set']);
        $this->assertSame($data['column_length'], $result['column_length']);
        $this->assertSame($data['type'], $result['type']);
        $this->assertSame($data['flags'], $result['flags']);
        $this->assertSame($data['decimals'], $result['decimals']);
    }

    public function testRowDataRoundTrip(): void
    {
        $data = [
            'packet_id' => 4,
            'values'    => ['hello', null, 'world', '123'],
        ];
        $binary = RowData::pack($data);
        $result = RowData::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['values'], $result['values']);
    }

    public function testRowDataAllNull(): void
    {
        $data = [
            'packet_id' => 4,
            'values'    => [null, null, null],
        ];
        $binary = RowData::pack($data);
        $result = RowData::unpack($binary);
        $this->assertSame($data['values'], $result['values']);
    }

    public function testHandshakeInitializationRoundTrip(): void
    {
        $authData = Packet::authData(20);
        $data = [
            'packet_id'            => 0,
            'protocol_version'     => 10,
            'server_version'       => '8.0.35-test',
            'connection_id'        => 12345,
            'capability_flags'     => 0x807FFFFF,
            'character_set_index'  => 255,
            'status_flags'         => 0x0002,
            'auth_plugin_data'     => $authData,
            'auth_plugin_name'     => 'caching_sha2_password',
        ];
        $binary = HandshakeInitialization::pack($data);
        $result = HandshakeInitialization::unpack($binary);
        $this->assertSame($data['protocol_version'], $result['protocol_version']);
        $this->assertSame($data['server_version'], $result['server_version']);
        $this->assertSame($data['connection_id'], $result['connection_id']);
        $this->assertSame($data['capability_flags'], $result['capability_flags']);
        $this->assertSame($data['character_set_index'], $result['character_set_index']);
        $this->assertSame($data['status_flags'], $result['status_flags']);
        $this->assertSame($data['auth_plugin_name'], $result['auth_plugin_name']);
        $this->assertSame(array_slice($authData, 0, 8), $result['auth_plugin_data_part1']);
    }

    public function testHandshakeResponseRoundTrip(): void
    {
        $data = [
            'packet_id'        => 1,
            'capability_flags' => HandshakeResponse::CLIENT_PROTOCOL_41
                                | HandshakeResponse::CLIENT_SECURE_CONNECTION
                                | HandshakeResponse::CLIENT_PLUGIN_AUTH
                                | HandshakeResponse::CLIENT_CONNECT_WITH_DB,
            'max_packet_size'  => 16777216,
            'character_set'    => 33,
            'username'         => 'root',
            'auth_response'    => 'test_auth_response',
            'database'         => 'testdb',
            'auth_plugin'      => 'mysql_native_password',
        ];
        $binary = HandshakeResponse::pack($data);
        $result = HandshakeResponse::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['capability_flags'], $result['capability_flags']);
        $this->assertSame($data['max_packet_size'], $result['max_packet_size']);
        $this->assertSame($data['character_set'], $result['character_set']);
        $this->assertSame($data['username'], $result['username']);
        $this->assertSame($data['auth_response'], $result['auth_response']);
        $this->assertSame($data['database'], $result['database']);
        $this->assertSame($data['auth_plugin'], $result['auth_plugin']);
    }

    public function testHandshakeResponseWithLenEncAuthData(): void
    {
        $data = [
            'packet_id'        => 1,
            'capability_flags' => HandshakeResponse::CLIENT_PROTOCOL_41
                                | HandshakeResponse::CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA,
            'max_packet_size'  => 16777216,
            'character_set'    => 33,
            'username'         => 'root',
            'auth_response'    => str_repeat('x', 300),
        ];
        $binary = HandshakeResponse::pack($data);
        $result = HandshakeResponse::unpack($binary);
        $this->assertSame($data['auth_response'], $result['auth_response']);
    }

    public function testAuthSwitchRequestRoundTrip(): void
    {
        $data = [
            'packet_id'        => 2,
            'plugin_name'      => 'caching_sha2_password',
            'auth_plugin_data' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20],
        ];
        $binary = AuthSwitchRequest::pack($data);
        $result = AuthSwitchRequest::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['plugin_name'], $result['plugin_name']);
        $this->assertSame($data['auth_plugin_data'], $result['auth_plugin_data']);
    }

    public function testAuthSwitchResponseRoundTrip(): void
    {
        $data = [
            'packet_id'     => 3,
            'auth_response' => 'computed_auth_response_data',
        ];
        $binary = AuthSwitchResponse::pack($data);
        $result = AuthSwitchResponse::unpack($binary);
        $this->assertSame($data['auth_response'], $result['auth_response']);
    }

    public function testRegisterSlaveRoundTrip(): void
    {
        $data = [
            'packet_id'        => 0,
            'server_id'        => 100,
            'hostname'         => 'slave1.example.com',
            'user'             => 'repl_user',
            'password'         => 'repl_pass',
            'port'             => 3306,
            'replication_rank' => 0,
            'master_id'        => 0,
        ];
        $binary = RegisterSlave::pack($data);
        $result = RegisterSlave::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['server_id'], $result['server_id']);
        $this->assertSame($data['hostname'], $result['hostname']);
        $this->assertSame($data['user'], $result['user']);
        $this->assertSame($data['password'], $result['password']);
        $this->assertSame($data['port'], $result['port']);
        $this->assertSame($data['replication_rank'], $result['replication_rank']);
        $this->assertSame($data['master_id'], $result['master_id']);
    }

    public function testBinlogDumpRoundTrip(): void
    {
        $data = [
            'packet_id'       => 0,
            'binlog_pos'      => 4,
            'flags'           => 0,
            'server_id'       => 100,
            'binlog_filename' => 'mysql-bin.000001',
        ];
        $binary = BinlogDump::pack($data);
        $result = BinlogDump::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['binlog_pos'], $result['binlog_pos']);
        $this->assertSame($data['flags'], $result['flags']);
        $this->assertSame($data['server_id'], $result['server_id']);
        $this->assertSame($data['binlog_filename'], $result['binlog_filename']);
    }

    public function testBinlogDumpGTIDRoundTrip(): void
    {
        $data = [
            'packet_id'       => 0,
            'flags'           => 0,
            'server_id'       => 100,
            'binlog_filename' => 'mysql-bin.000001',
            'binlog_pos'      => 4,
            'gtid_sets'       => [
                [
                    'sid'       => '12345678-1234-1234-1234-123456789abc',
                    'intervals' => [
                        ['start' => 1, 'end' => 10],
                    ],
                ],
            ],
        ];
        $binary = BinlogDumpGTID::pack($data);
        $result = BinlogDumpGTID::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['flags'], $result['flags']);
        $this->assertSame($data['server_id'], $result['server_id']);
        $this->assertSame($data['binlog_filename'], $result['binlog_filename']);
        $this->assertSame($data['binlog_pos'], $result['binlog_pos']);
        $this->assertCount(1, $result['gtid_sets']);
        $this->assertSame($data['gtid_sets'][0]['sid'], $result['gtid_sets'][0]['sid']);
        $this->assertSame($data['gtid_sets'][0]['intervals'], $result['gtid_sets'][0]['intervals']);
    }

    public function testStmtPrepareRoundTrip(): void
    {
        $data = [
            'packet_id' => 0,
            'sql'       => 'SELECT * FROM users WHERE id = ?',
        ];
        $binary = StmtPrepare::pack($data);
        $result = StmtPrepare::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame(StmtPrepare::COMMAND, $result['command']);
        $this->assertSame($data['sql'], $result['sql']);
    }

    public function testStmtPrepareOkRoundTrip(): void
    {
        $data = [
            'packet_id'     => 1,
            'stmt_id'       => 42,
            'num_columns'   => 3,
            'num_params'    => 1,
            'warning_count' => 0,
        ];
        $binary = StmtPrepareOk::pack($data);
        $result = StmtPrepareOk::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['stmt_id'], $result['stmt_id']);
        $this->assertSame($data['num_columns'], $result['num_columns']);
        $this->assertSame($data['num_params'], $result['num_params']);
        $this->assertSame($data['warning_count'], $result['warning_count']);
    }

    public function testStmtExecuteRoundTrip(): void
    {
        $data = [
            'packet_id'       => 0,
            'stmt_id'         => 1,
            'flags'           => StmtExecute::CURSOR_TYPE_NO_CURSOR,
            'iteration_count' => 1,
        ];
        $binary = StmtExecute::pack($data);
        $result = StmtExecute::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['stmt_id'], $result['stmt_id']);
        $this->assertSame($data['flags'], $result['flags']);
        $this->assertSame($data['iteration_count'], $result['iteration_count']);
    }

    public function testStmtExecuteWithParams(): void
    {
        $data = [
            'packet_id'       => 0,
            'stmt_id'         => 1,
            'flags'           => StmtExecute::CURSOR_TYPE_NO_CURSOR,
            'iteration_count' => 1,
            'parameters'      => [
                ['type' => StmtExecute::MYSQL_TYPE_LONG, 'value' => 42],
                ['type' => StmtExecute::MYSQL_TYPE_STRING, 'value' => 'hello'],
            ],
        ];
        $binary = StmtExecute::pack($data);
        $result = StmtExecute::unpack($binary);
        $this->assertSame($data['stmt_id'], $result['stmt_id']);
    }

    // ── AuthMoreData ──────────────────────────────────────

    public function testAuthMoreDataRequestRoundTrip(): void
    {
        $data = [
            'packet_id'  => 4,
            'extra_data' => [0x01, 0x02, 0x03],
        ];
        $binary = AuthMoreDataRequest::pack($data);
        $result = AuthMoreDataRequest::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['extra_data'], $result['extra_data']);
    }

    public function testAuthMoreDataResponseRoundTrip(): void
    {
        $data = [
            'packet_id'     => 5,
            'auth_response' => str_repeat("\x01\x02\x03\x04", 50),
        ];
        $binary = AuthMoreDataResponse::pack($data);
        $result = AuthMoreDataResponse::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['auth_response'], $result['auth_response']);
    }

    // ── HandshakeResponse with CLIENT_CONNECT_ATTRS ───────

    public function testHandshakeResponseWithAttrs(): void
    {
        $data = [
            'packet_id'        => 1,
            'capability_flags' => HandshakeResponse::CLIENT_PROTOCOL_41
                                | HandshakeResponse::CLIENT_SECURE_CONNECTION
                                | HandshakeResponse::CLIENT_PLUGIN_AUTH
                                | HandshakeResponse::CLIENT_CONNECT_ATTRS,
            'max_packet_size'  => 16777216,
            'character_set'    => 33,
            'username'         => 'root',
            'auth_response'    => 'auth_data',
            'auth_plugin'      => 'mysql_native_password',
            'attributes'       => [
                '_client_name' => 'mysql-protocol',
                '_client_version' => '1.0.0',
            ],
        ];
        $binary = HandshakeResponse::pack($data);
        $result = HandshakeResponse::unpack($binary);
        $this->assertSame($data['capability_flags'], $result['capability_flags']);
        $this->assertSame($data['username'], $result['username']);
        $this->assertSame($data['attributes'], $result['attributes']);
    }

    // ── HandshakeResponse with non-ASCII auth_response ───

    public function testHandshakeResponseBinaryAuthResponse(): void
    {
        $authResponse = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13";
        $data = [
            'packet_id'        => 1,
            'capability_flags' => HandshakeResponse::CLIENT_PROTOCOL_41
                                | HandshakeResponse::CLIENT_SECURE_CONNECTION,
            'max_packet_size'  => 16777216,
            'character_set'    => 33,
            'username'         => 'root',
            'auth_response'    => $authResponse,
        ];
        $binary = HandshakeResponse::pack($data);
        $result = HandshakeResponse::unpack($binary);
        $this->assertSame($authResponse, $result['auth_response']);
    }

    // ── BinlogDumpGTID with multiple GTID sets ────────────

    public function testBinlogDumpGTIDMultipleSets(): void
    {
        $data = [
            'packet_id'       => 0,
            'flags'           => 0,
            'server_id'       => 100,
            'binlog_filename' => 'mysql-bin.000003',
            'binlog_pos'      => 120,
            'gtid_sets'       => [
                [
                    'sid'       => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                    'intervals' => [['start' => 1, 'end' => 50]],
                ],
                [
                    'sid'       => '11111111-2222-3333-4444-555555555555',
                    'intervals' => [
                        ['start' => 1, 'end' => 10],
                        ['start' => 20, 'end' => 30],
                    ],
                ],
            ],
        ];
        $binary = BinlogDumpGTID::pack($data);
        $result = BinlogDumpGTID::unpack($binary);
        $this->assertCount(2, $result['gtid_sets']);
        $this->assertSame($data['gtid_sets'][0]['sid'], $result['gtid_sets'][0]['sid']);
        $this->assertSame($data['gtid_sets'][1]['sid'], $result['gtid_sets'][1]['sid']);
        $this->assertCount(1, $result['gtid_sets'][0]['intervals']);
        $this->assertCount(2, $result['gtid_sets'][1]['intervals']);
    }

    // ── Error boundary tests ──────────────────────────────

    public function testErrorRoundTripEmptyMessage(): void
    {
        $data = [
            'packet_id'     => 1,
            'error_code'    => 1000,
            'error_message' => '',
        ];
        $binary = Error::pack($data);
        $result = Error::unpack($binary);
        $this->assertSame($data['error_code'], $result['error_code']);
        $this->assertSame($data['error_message'], $result['error_message']);
    }

    // ── Ok with large affected_rows ───────────────────────

    public function testOkLargeAffectedRows(): void
    {
        $data = [
            'packet_id'      => 1,
            'affected_rows'  => 999999,
            'last_insert_id' => 0,
            'status_flags'   => 2,
            'warnings'       => 0,
        ];
        $binary = Ok::pack($data);
        $result = Ok::unpack($binary);
        $this->assertSame($data['affected_rows'], $result['affected_rows']);
    }
}
