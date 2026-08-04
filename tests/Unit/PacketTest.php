<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

class PacketTest extends TestCase
{
    public function testBinaryCreatesPacketWithHeader(): void
    {
        $binary = Packet::binary(function (Binary $binary) {
            $binary->writeByte(0x41);
            $binary->writeByte(0x42);
        }, 1);

        $bytes = $binary->unpack();
        $this->assertCount(6, $bytes);
        $this->assertSame(2, $bytes[0]);
        $this->assertSame(0, $bytes[1]);
        $this->assertSame(0, $bytes[2]);
        $this->assertSame(1, $bytes[3]);
        $this->assertSame(0x41, $bytes[4]);
        $this->assertSame(0x42, $bytes[5]);
    }

    public function testParserReadsHeaderAndBody(): void
    {
        $binary = Packet::binary(function (Binary $binary) {
            $binary->writeByte(0x41);
        }, 5);

        $result = Packet::parser(function (Binary $binary) {
            return ['body_byte' => $binary->readByte()];
        }, $binary);

        $this->assertSame(1, $result['packet_length']);
        $this->assertSame(5, $result['packet_id']);
        $this->assertSame(0x41, $result['body_byte']);
    }

    public function testParserWithNullClosure(): void
    {
        $binary = Packet::binary(function (Binary $binary) {
            $binary->writeByte(0x00);
        }, 0);

        $result = Packet::parser(null, $binary);
        $this->assertSame(1, $result['packet_length']);
        $this->assertSame(0, $result['packet_id']);
    }

    public function testGetPacketClassOk(): void
    {
        $binary = Packet::binary(function (Binary $binary) {
            $binary->writeByte(0x00);
        }, 0);
        $class = Packet::getPacketClass($binary);
        $this->assertSame(\Workbunny\MysqlProtocol\Packets\OK::class, $class);
    }

    public function testGetPacketClassError(): void
    {
        $binary = Packet::binary(function (Binary $binary) {
            $binary->writeByte(0xFF);
        }, 0);
        $class = Packet::getPacketClass($binary);
        $this->assertSame(\Workbunny\MysqlProtocol\Packets\Error::class, $class);
    }

    public function testGetPacketClassEOF(): void
    {
        $binary = Packet::binary(function (Binary $binary) {
            $binary->writeByte(0xFE);
            $binary->writeUB(0, Binary::UB2);
            $binary->writeUB(0, Binary::UB2);
        }, 0);
        $class = Packet::getPacketClass($binary);
        $this->assertSame(\Workbunny\MysqlProtocol\Packets\EOF::class, $class);
    }

    public function testAuthDataReturnsByteArray(): void
    {
        $data = Packet::authData(8);
        $this->assertCount(8, $data);
        foreach ($data as $byte) {
            $this->assertGreaterThanOrEqual(0, $byte);
            $this->assertLessThanOrEqual(255, $byte);
        }
    }

    public function testAuthDataMinSize(): void
    {
        $data = Packet::authData(4);
        $this->assertCount(8, $data);
    }

    public function testAuthDataMaxSize(): void
    {
        $data = Packet::authData(100);
        $this->assertCount(21, $data);
    }

    // ── getPacketClass boundary tests ─────────────────────

    public function testGetPacketClassAuthSwitchVsEOF(): void
    {
        // EOF: packetLength < 9, flag = 0xFE
        $eof = Packet::binary(function (Binary $binary) {
            $binary->writeByte(0xFE);
            $binary->writeUB(0, Binary::UB2);
            $binary->writeUB(0, Binary::UB2);
        }, 0);
        $this->assertSame(\Workbunny\MysqlProtocol\Packets\EOF::class, Packet::getPacketClass($eof));

        // AuthSwitchRequest: packetLength >= 9, flag = 0xFE
        $authSwitch = Packet::binary(function (Binary $binary) {
            $binary->writeByte(0xFE);
            $binary->writeNullTerminated(Binary::StringToBytes('caching_sha2_password'));
            $binary->writeBytes([1, 2, 3, 4, 5, 6, 7, 8, 9]);
        }, 0);
        $this->assertSame(\Workbunny\MysqlProtocol\Packets\AuthSwitchRequest::class, Packet::getPacketClass($authSwitch));
    }

    public function testGetPacketClassResultSetHeader(): void
    {
        $rs = Packet::binary(function (Binary $binary) {
            $binary->writeLenEncInt(5);
        }, 1);
        $this->assertSame(\Workbunny\MysqlProtocol\Packets\ResultSetHeader::class, Packet::getPacketClass($rs));
    }

    public function testGetPacketClassUnknown(): void
    {
        $unknown = Packet::binary(function (Binary $binary) {
            $binary->writeByte(0xFC);
        }, 0);
        $this->assertNull(Packet::getPacketClass($unknown));
    }

    public function testParserRecover(): void
    {
        $binary = Packet::binary(function (Binary $binary) {
            $binary->writeByte(0x41);
            $binary->writeByte(0x42);
        }, 3);
        // packet structure: [len:3][id:1][0x41][0x42] = 6 bytes
        // parser internally resets cursor to 0, reads header (4 bytes), then reads body
        // After body read, cursor = 6
        // Set cursor to 4 (start of body) to verify recover
        $binary->setReadCursor(4);
        $result = Packet::parser(function (Binary $binary) {
            return ['byte' => $binary->readByte()];
        }, $binary, true);
        $this->assertSame(0x41, $result['byte']);
        // recover 恢复的是包头读取前的指针位置，闭包内 readByte 会正常推进指针
        $this->assertSame(5, $binary->getReadCursor());
    }
}
