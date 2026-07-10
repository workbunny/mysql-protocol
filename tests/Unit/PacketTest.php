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
}
