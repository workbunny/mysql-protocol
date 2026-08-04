<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Workbunny\MysqlProtocol\Packets\ChangeUser;
use Workbunny\MysqlProtocol\Packets\ResetConnection;
use Workbunny\MysqlProtocol\Packets\SetOption;
use Workbunny\MysqlProtocol\Packets\StmtClose;
use Workbunny\MysqlProtocol\Packets\StmtFetch;
use Workbunny\MysqlProtocol\Packets\StmtReset;
use Workbunny\MysqlProtocol\Packets\StmtSendLongData;
use Workbunny\MysqlProtocol\Packets\BinlogEvent;
use Workbunny\MysqlProtocol\Packets\RowData;
use Workbunny\MysqlProtocol\Utils\Binary;

class PacketEdgeCaseTest extends TestCase
{
    // ── ChangeUser ────────────────────────────────────────

    public function testChangeUserRoundTrip(): void
    {
        $data = [
            'packet_id'     => 0,
            'user'          => 'newuser',
            'auth_response' => 'new_auth_data',
            'database'      => 'newdb',
            'character_set' => 45,
            'auth_plugin'   => 'mysql_native_password',
            'attributes'    => ['_client_name' => 'test'],
        ];
        $binary = ChangeUser::pack($data);
        $result = ChangeUser::unpack($binary);
        $this->assertSame($data['user'], $result['user']);
        $this->assertSame($data['auth_response'], $result['auth_response']);
        $this->assertSame($data['database'], $result['database']);
        $this->assertSame($data['character_set'], $result['character_set']);
        $this->assertSame($data['auth_plugin'], $result['auth_plugin']);
        $this->assertSame($data['attributes'], $result['attributes']);
    }

    public function testChangeUserWithoutOptionalFields(): void
    {
        $data = [
            'packet_id'     => 0,
            'user'          => 'root',
            'auth_response' => 'resp',
        ];
        $binary = ChangeUser::pack($data);
        $result = ChangeUser::unpack($binary);
        $this->assertSame($data['user'], $result['user']);
        $this->assertSame($data['auth_response'], $result['auth_response']);
        $this->assertSame('', $result['database']);
        $this->assertNull($result['auth_plugin']);
    }

    // ── StmtClose ─────────────────────────────────────────

    public function testStmtCloseRoundTrip(): void
    {
        $data = ['packet_id' => 0, 'stmt_id' => 42];
        $binary = StmtClose::pack($data);
        $result = StmtClose::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame($data['stmt_id'], $result['stmt_id']);
    }

    // ── StmtReset ─────────────────────────────────────────

    public function testStmtResetRoundTrip(): void
    {
        $data = ['packet_id' => 0, 'stmt_id' => 7];
        $binary = StmtReset::pack($data);
        $result = StmtReset::unpack($binary);
        $this->assertSame($data['stmt_id'], $result['stmt_id']);
    }

    // ── StmtSendLongData ──────────────────────────────────

    public function testStmtSendLongDataRoundTrip(): void
    {
        $data = [
            'packet_id' => 0,
            'stmt_id'   => 1,
            'param_id'  => 0,
            'data'      => [0x41, 0x42, 0x43, 0x44],
        ];
        $binary = StmtSendLongData::pack($data);
        $result = StmtSendLongData::unpack($binary);
        $this->assertSame($data['stmt_id'], $result['stmt_id']);
        $this->assertSame($data['param_id'], $result['param_id']);
        $this->assertSame($data['data'], $result['data']);
    }

    public function testStmtSendLongDataEmpty(): void
    {
        $data = ['packet_id' => 0, 'stmt_id' => 1, 'param_id' => 0];
        $binary = StmtSendLongData::pack($data);
        $result = StmtSendLongData::unpack($binary);
        $this->assertSame([], $result['data']);
    }

    // ── StmtFetch ─────────────────────────────────────────

    public function testStmtFetchRoundTrip(): void
    {
        $data = ['packet_id' => 0, 'stmt_id' => 1, 'num_rows' => 100];
        $binary = StmtFetch::pack($data);
        $result = StmtFetch::unpack($binary);
        $this->assertSame($data['stmt_id'], $result['stmt_id']);
        $this->assertSame($data['num_rows'], $result['num_rows']);
    }

    // ── SetOption ─────────────────────────────────────────

    public function testSetOptionRoundTrip(): void
    {
        $data = ['packet_id' => 0, 'option' => SetOption::MYSQL_OPTION_MULTI_STATEMENTS_ON];
        $binary = SetOption::pack($data);
        $result = SetOption::unpack($binary);
        $this->assertSame($data['option'], $result['option']);
    }

    public function testSetOptionOff(): void
    {
        $data = ['packet_id' => 0, 'option' => SetOption::MYSQL_OPTION_MULTI_STATEMENTS_OFF];
        $binary = SetOption::pack($data);
        $result = SetOption::unpack($binary);
        $this->assertSame($data['option'], $result['option']);
    }

    // ── ResetConnection ───────────────────────────────────

    public function testResetConnectionRoundTrip(): void
    {
        $data = ['packet_id' => 0];
        $binary = ResetConnection::pack($data);
        $result = ResetConnection::unpack($binary);
        $this->assertSame($data['packet_id'], $result['packet_id']);
        $this->assertSame(ResetConnection::COMMAND, $result['command']);
    }

    // ── BinlogEvent: TABLE_MAP_EVENT ──────────────────────

    public function testTableMapEventParse(): void
    {
        $body = new Binary();
        $body->writeUB(108, Binary::UB8);
        $body->writeUB(0, Binary::UB2);
        $body->writeLenEncString('test_db');
        $body->writeByte(0x00);
        $body->writeLenEncString('users');
        $body->writeByte(0x00);
        $body->writeLenEncInt(3);
        $body->writeBytes([0x03, 0x03, 0x0F]);
        $body->writeBytes([0x64, 0x00]);
        $body->writeByte(0x00);
        $data = [
            'timestamp'  => time(),
            'event_type' => BinlogEvent::TABLE_MAP_EVENT,
            'server_id'  => 1,
            'log_pos'    => 500,
            'flags'      => 0,
            'body'       => $body->unpack(),
        ];
        $binary = BinlogEvent::pack($data);
        $result = BinlogEvent::unpack($binary);
        $this->assertSame(BinlogEvent::TABLE_MAP_EVENT, $result['event_type']);
        $this->assertNotNull($result['event_data']);
        $this->assertSame(108, $result['event_data']['table_id']);
        $this->assertSame('test_db', $result['event_data']['schema']);
        $this->assertSame('users', $result['event_data']['table']);
        $this->assertSame(3, $result['event_data']['column_count']);
        $this->assertSame([0x03, 0x03, 0x0F], $result['event_data']['column_types']);
    }

    // ── RowData: empty values ─────────────────────────────

    public function testRowDataEmptyValues(): void
    {
        $data = ['packet_id' => 1, 'values' => []];
        $binary = RowData::pack($data);
        $result = RowData::unpack($binary);
        $this->assertSame([], $result['values']);
    }

    // ── RowData: large strings ────────────────────────────

    public function testRowDataLargeString(): void
    {
        $largeStr = str_repeat('x', 10000);
        $data = ['packet_id' => 1, 'values' => [$largeStr, null, 'short']];
        $binary = RowData::pack($data);
        $result = RowData::unpack($binary);
        $this->assertSame($data['values'], $result['values']);
    }
}
