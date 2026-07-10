<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Workbunny\MysqlProtocol\Packets\BinlogEvent;
use Workbunny\MysqlProtocol\Utils\Binary;

class BinlogEventTest extends TestCase
{
    public function testPackAndUnpackBasicEvent(): void
    {
        $data = [
            'timestamp'  => 1609459200,
            'event_type' => BinlogEvent::QUERY_EVENT,
            'server_id'  => 1,
            'log_pos'    => 100,
            'flags'      => 0,
            'body'       => [0x41, 0x42, 0x43],
        ];
        $binary = BinlogEvent::pack($data);
        $result = BinlogEvent::unpack($binary);
        $this->assertSame($data['timestamp'], $result['timestamp']);
        $this->assertSame($data['event_type'], $result['event_type']);
        $this->assertSame('QUERY_EVENT', $result['event_type_name']);
        $this->assertSame($data['server_id'], $result['server_id']);
        $this->assertSame($data['log_pos'], $result['log_pos']);
        $this->assertSame($data['flags'], $result['flags']);
        $this->assertSame($data['body'], $result['body']);
    }

    public function testEventTypeName(): void
    {
        $this->assertSame('FORMAT_DESCRIPTION_EVENT', BinlogEvent::getEventTypeName(BinlogEvent::FORMAT_DESCRIPTION_EVENT));
        $this->assertSame('QUERY_EVENT', BinlogEvent::getEventTypeName(BinlogEvent::QUERY_EVENT));
        $this->assertSame('ROTATE_EVENT', BinlogEvent::getEventTypeName(BinlogEvent::ROTATE_EVENT));
        $this->assertSame('XID_EVENT', BinlogEvent::getEventTypeName(BinlogEvent::XID_EVENT));
        $this->assertSame('TABLE_MAP_EVENT', BinlogEvent::getEventTypeName(BinlogEvent::TABLE_MAP_EVENT));
        $this->assertSame('GTID_EVENT', BinlogEvent::getEventTypeName(BinlogEvent::GTID_EVENT));
        $this->assertSame('HEARTBEAT_EVENT', BinlogEvent::getEventTypeName(BinlogEvent::HEARTBEAT_EVENT));
        $this->assertSame('STOP_EVENT', BinlogEvent::getEventTypeName(BinlogEvent::STOP_EVENT));
        $this->assertSame('WRITE_ROWS_EVENT_V2', BinlogEvent::getEventTypeName(BinlogEvent::WRITE_ROWS_EVENT_V2));
        $this->assertSame('UPDATE_ROWS_EVENT_V2', BinlogEvent::getEventTypeName(BinlogEvent::UPDATE_ROWS_EVENT_V2));
        $this->assertSame('DELETE_ROWS_EVENT_V2', BinlogEvent::getEventTypeName(BinlogEvent::DELETE_ROWS_EVENT_V2));
        $this->assertSame('UNKNOWN', BinlogEvent::getEventTypeName(0xFF));
    }

    public function testEventTooShort(): void
    {
        $binary = new Binary([1, 2, 3]);
        $this->expectException(\Workbunny\MysqlProtocol\Exceptions\PacketException::class);
        BinlogEvent::unpack($binary);
    }

    public function testFormatDescriptionEventParse(): void
    {
        $body = new Binary();
        $body->writeUB(4, Binary::UB2);
        $versionBytes = Binary::StringToBytes(str_pad('8.0.35-test', 50, "\x00"));
        $body->writeBytes($versionBytes);
        $body->writeUB(1609459200, Binary::UB4);
        $body->writeByte(19);
        $data = [
            'timestamp'  => 1609459200,
            'event_type' => BinlogEvent::FORMAT_DESCRIPTION_EVENT,
            'server_id'  => 1,
            'log_pos'    => 0,
            'flags'      => 0,
            'body'       => $body->unpack(),
        ];
        $binary = BinlogEvent::pack($data);
        $result = BinlogEvent::unpack($binary);
        $this->assertSame(BinlogEvent::FORMAT_DESCRIPTION_EVENT, $result['event_type']);
        $this->assertNotNull($result['event_data']);
        $this->assertSame(4, $result['event_data']['binlog_version']);
        $this->assertSame('8.0.35-test', $result['event_data']['server_version']);
        $this->assertSame(19, $result['event_data']['common_header_length']);
    }

    public function testXidEventParse(): void
    {
        $body = new Binary();
        $body->writeUB(42, Binary::UB8);
        $data = [
            'timestamp'  => 1609459200,
            'event_type' => BinlogEvent::XID_EVENT,
            'server_id'  => 1,
            'log_pos'    => 200,
            'flags'      => 0,
            'body'       => $body->unpack(),
        ];
        $binary = BinlogEvent::pack($data);
        $result = BinlogEvent::unpack($binary);
        $this->assertSame(BinlogEvent::XID_EVENT, $result['event_type']);
        $this->assertNotNull($result['event_data']);
        $this->assertSame(42, $result['event_data']['xid']);
    }

    public function testRotateEventParse(): void
    {
        $body = new Binary();
        $body->writeUB(4, Binary::UB8);
        $body->writeBytes(Binary::StringToBytes('mysql-bin.000002'));
        $data = [
            'timestamp'  => 1609459200,
            'event_type' => BinlogEvent::ROTATE_EVENT,
            'server_id'  => 1,
            'log_pos'    => 200,
            'flags'      => 0,
            'body'       => $body->unpack(),
        ];
        $binary = BinlogEvent::pack($data);
        $result = BinlogEvent::unpack($binary);
        $this->assertSame(BinlogEvent::ROTATE_EVENT, $result['event_type']);
        $this->assertNotNull($result['event_data']);
        $this->assertSame(4, $result['event_data']['position']);
        $this->assertSame('mysql-bin.000002', $result['event_data']['filename']);
    }

    public function testHeartbeatEventParse(): void
    {
        $data = [
            'timestamp'  => 1609459200,
            'event_type' => BinlogEvent::HEARTBEAT_EVENT,
            'server_id'  => 1,
            'log_pos'    => 0,
            'flags'      => 0,
            'body'       => [],
        ];
        $binary = BinlogEvent::pack($data);
        $result = BinlogEvent::unpack($binary);
        $this->assertSame(BinlogEvent::HEARTBEAT_EVENT, $result['event_type']);
        $this->assertSame([], $result['event_data']);
    }

    public function testGtidEventParse(): void
    {
        $sidHex = '12345678123412341234123456789abc';
        $sidBytes = [];
        for ($i = 0; $i < 32; $i += 2) {
            $sidBytes[] = hexdec(substr($sidHex, $i, 2));
        }
        $body = new Binary();
        $body->writeByte(0); // flags
        $body->writeBytes($sidBytes); // SID (16 bytes)
        $body->writeUB(1, Binary::UB8); // GNO
        $data = [
            'timestamp'  => 1609459200,
            'event_type' => BinlogEvent::GTID_EVENT,
            'server_id'  => 1,
            'log_pos'    => 100,
            'flags'      => 0,
            'body'       => $body->unpack(),
        ];
        $binary = BinlogEvent::pack($data);
        $result = BinlogEvent::unpack($binary);
        $this->assertSame(BinlogEvent::GTID_EVENT, $result['event_type']);
        $this->assertNotNull($result['event_data']);
        $this->assertSame('12345678-1234-1234-1234-123456789abc', $result['event_data']['sid']);
        $this->assertSame(1, $result['event_data']['gno']);
    }
}
