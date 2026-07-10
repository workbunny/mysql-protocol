<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Workbunny\MysqlProtocol\Exceptions\InvalidArgumentException;
use Workbunny\MysqlProtocol\Utils\Binary;

class BinaryTest extends TestCase
{
    public function testConstructWithString(): void
    {
        $binary = new Binary('workbunny');
        $this->assertSame([119, 111, 114, 107, 98, 117, 110, 110, 121], $binary->unpack());
        $this->assertSame('workbunny', $binary->pack());
    }

    public function testConstructWithNull(): void
    {
        $binary = new Binary(null);
        $this->assertSame([], $binary->unpack());
        $this->assertSame('', $binary->pack());
        $this->assertSame(0, $binary->length());
        $this->assertSame(0, $binary->count());
    }

    public function testConstructWithByteArray(): void
    {
        $binary = new Binary([72, 101, 108, 108, 111]);
        $this->assertSame('Hello', $binary->pack());
    }

    public function testConstructWithInvalidByte(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Binary([0, 256]);
    }

    public function testStringToBytesAndBack(): void
    {
        $str = 'Hello World';
        $bytes = Binary::StringToBytes($str);
        $this->assertSame($str, Binary::BytesToString($bytes));
    }

    public function testLenEncLength(): void
    {
        $this->assertSame(1, Binary::LenEncLength(0));
        $this->assertSame(1, Binary::LenEncLength(250));
        $this->assertSame(3, Binary::LenEncLength(251));
        $this->assertSame(3, Binary::LenEncLength(65535));
        $this->assertSame(4, Binary::LenEncLength(65536));
        $this->assertSame(4, Binary::LenEncLength(16777215));
        $this->assertSame(9, Binary::LenEncLength(16777216));
    }

    public function testReadByte(): void
    {
        $binary = new Binary([0x01, 0x02, 0x03]);
        $this->assertSame(1, $binary->readByte());
        $this->assertSame(2, $binary->readByte());
        $this->assertSame(3, $binary->readByte());
    }

    public function testReadByteOutOfBounds(): void
    {
        $binary = new Binary([0x01]);
        $binary->readByte();
        $this->expectException(InvalidArgumentException::class);
        $binary->readByte();
    }

    public function testReadBytes(): void
    {
        $binary = new Binary([0x01, 0x02, 0x03, 0x04]);
        $this->assertSame([1, 2], $binary->readBytes(2));
        $this->assertSame([3, 4], $binary->readBytes(2));
    }

    public function testReadUB2(): void
    {
        $binary = new Binary([0x02, 0x01]);
        $this->assertSame(0x0102, $binary->readUB(Binary::UB2));
    }

    public function testReadUB4(): void
    {
        $binary = new Binary([0x04, 0x03, 0x02, 0x01]);
        $this->assertSame(0x01020304, $binary->readUB(Binary::UB4));
    }

    public function testReadUB8(): void
    {
        $binary = new Binary([0x08, 0x07, 0x06, 0x05, 0x04, 0x03, 0x02, 0x01]);
        $this->assertSame(0x0102030405060708, $binary->readUB(Binary::UB8));
    }

    public function testReadUB3(): void
    {
        $binary = new Binary([0x03, 0x02, 0x01]);
        $this->assertSame(0x010203, $binary->readUB(Binary::UB3));
    }

    public function testReadUBBigEndian(): void
    {
        $binary = new Binary([0x01, 0x02]);
        $this->assertSame(0x0102, $binary->readUB(Binary::UB2, false));
    }

    public function testReadNullTerminated(): void
    {
        $binary = new Binary([0x41, 0x42, 0x43, 0x00, 0x44]);
        $result = $binary->readNullTerminated();
        $this->assertSame([0x41, 0x42, 0x43], $result);
        $this->assertSame(4, $binary->getReadCursor());
    }

    public function testReadLenEncIntSmall(): void
    {
        $binary = new Binary([250]);
        $this->assertSame(250, $binary->readLenEncInt());
    }

    public function testReadLenEncInt0xFC(): void
    {
        $binary = new Binary([0xFC, 0xFF, 0x00]);
        $this->assertSame(255, $binary->readLenEncInt());
    }

    public function testReadLenEncInt0xFD(): void
    {
        $binary = new Binary([0xFD, 0x00, 0x00, 0x01]);
        $this->assertSame(65536, $binary->readLenEncInt());
    }

    public function testReadLenEncInt0xFE(): void
    {
        $binary = new Binary([0xFE, 0x00, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00]);
        $this->assertSame(4294967296, $binary->readLenEncInt());
    }

    public function testReadLenEncString(): void
    {
        $binary = new Binary([2, 72, 105]);
        $this->assertSame('Hi', $binary->readLenEncString());
    }

    public function testWriteByte(): void
    {
        $binary = new Binary();
        $binary->writeByte(0x41);
        $binary->writeByte(0x42);
        $this->assertSame('AB', $binary->pack());
    }

    public function testWriteByteInvalid(): void
    {
        $binary = new Binary();
        $this->expectException(InvalidArgumentException::class);
        $binary->writeByte(256);
    }

    public function testWriteBytes(): void
    {
        $binary = new Binary();
        $binary->writeBytes([0x48, 0x69]);
        $this->assertSame('Hi', $binary->pack());
    }

    public function testWriteUB2(): void
    {
        $binary = new Binary();
        $binary->writeUB(0x0102, Binary::UB2);
        $this->assertSame([0x02, 0x01], $binary->unpack());
    }

    public function testWriteUB4(): void
    {
        $binary = new Binary();
        $binary->writeUB(0x01020304, Binary::UB4);
        $this->assertSame([0x04, 0x03, 0x02, 0x01], $binary->unpack());
    }

    public function testWriteUB8(): void
    {
        $binary = new Binary();
        $binary->writeUB(0x0102030405060708, Binary::UB8);
        $this->assertSame([0x08, 0x07, 0x06, 0x05, 0x04, 0x03, 0x02, 0x01], $binary->unpack());
    }

    public function testWriteUBBigEndian(): void
    {
        $binary = new Binary();
        $binary->writeUB(0x0102, Binary::UB2, false);
        $this->assertSame([0x01, 0x02], $binary->unpack());
    }

    public function testWriteNullTerminated(): void
    {
        $binary = new Binary();
        $binary->writeNullTerminated([0x41, 0x42]);
        $this->assertSame([0x41, 0x42, 0x00], $binary->unpack());
    }

    public function testWriteLenEncIntSmall(): void
    {
        $binary = new Binary();
        $binary->writeLenEncInt(250);
        $this->assertSame([250], $binary->unpack());
    }

    public function testWriteLenEncInt251(): void
    {
        $binary = new Binary();
        $binary->writeLenEncInt(251);
        $this->assertSame([0xFC, 251, 0], $binary->unpack());
    }

    public function testWriteLenEncInt65536(): void
    {
        $binary = new Binary();
        $binary->writeLenEncInt(65536);
        $this->assertSame([0xFD, 0, 0, 1], $binary->unpack());
    }

    public function testWriteLenEncString(): void
    {
        $binary = new Binary();
        $binary->writeLenEncString('Hi');
        $this->assertSame([2, 72, 105], $binary->unpack());
    }

    public function testReadAndWriteCursors(): void
    {
        $binary = new Binary();
        $binary->writeByte(0x01);
        $binary->writeByte(0x02);
        $this->assertSame(2, $binary->getWriteCursor());
        $binary->setReadCursor(1);
        $this->assertSame(1, $binary->getReadCursor());
        $this->assertSame(2, $binary->readByte());
    }

    public function testSetReadCursorInvalid(): void
    {
        $binary = new Binary([1, 2, 3]);
        $this->expectException(InvalidArgumentException::class);
        $binary->setReadCursor(4);
    }

    public function testLength(): void
    {
        $binary = new Binary('Hello');
        $this->assertSame(5, $binary->length());
    }

    public function testCount(): void
    {
        $binary = new Binary([1, 2, 3, 4, 5]);
        $this->assertSame(5, $binary->count());
    }

    public function testPackAlwaysReturnsString(): void
    {
        $binary = new Binary('test');
        $this->assertSame('test', $binary->pack());
        $this->assertSame('test', $binary->pack(true));
    }

    public function testPackCacheInvalidationAfterWriteByte(): void
    {
        $binary = new Binary();
        $binary->writeBytes([0x41, 0x42]);
        $cached = $binary->pack(true);
        $this->assertSame('AB', $cached);
        $binary->writeByte(0x43);
        $this->assertSame('ABC', $binary->pack(true));
    }

    public function testPackCacheInvalidationAfterWriteBytes(): void
    {
        $binary = new Binary();
        $binary->writeBytes([0x41]);
        $binary->pack(true);
        $binary->writeBytes([0x42, 0x43]);
        $this->assertSame('ABC', $binary->pack(true));
    }

    public function testSetWriteCursorPadsZeros(): void
    {
        $binary = new Binary();
        $binary->setWriteCursor(3);
        $binary->writeByte(0xFF);
        $this->assertSame([0, 0, 0, 0xFF], $binary->unpack());
    }

    public function testRoundTripLenEncString(): void
    {
        $testStrings = ['', 'Hi', 'Hello World', str_repeat('x', 300)];
        foreach ($testStrings as $str) {
            $binary = new Binary();
            $binary->writeLenEncString($str);
            $readBinary = new Binary($binary->pack());
            $this->assertSame($str, $readBinary->readLenEncString(), "Failed for string length: " . strlen($str));
        }
    }

    public function testRoundTripUB(): void
    {
        $values = [0, 1, 255, 256, 65535, 65536, 16777215, 16777216];
        foreach ($values as $value) {
            $binary = new Binary();
            $byteCount = match (true) {
                $value <= 0xFF => Binary::UB2,
                $value <= 0xFFFF => Binary::UB4,
                default => Binary::UB8,
            };
            $binary->writeUB($value, $byteCount);
            $readBinary = new Binary($binary->pack());
            $this->assertSame($value, $readBinary->readUB($byteCount), "Failed for value: $value");
        }
    }

    public function testDump(): void
    {
        $binary = new Binary('AB');
        $dump = $binary->dump();
        $this->assertStringContainsString('41', $dump);
        $this->assertStringContainsString('42', $dump);
        $this->assertStringContainsString('|AB|', $dump);
    }

    public function testPayload(): void
    {
        $binary = new Binary('test');
        $this->assertSame('test', $binary->payload());
    }
}
