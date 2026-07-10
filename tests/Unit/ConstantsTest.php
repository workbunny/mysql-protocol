<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Workbunny\MysqlProtocol\Constants\Capabilities;
use Workbunny\MysqlProtocol\Constants\MySQLColumnType;
use Workbunny\MysqlProtocol\Constants\ServerStatus;
use Workbunny\MysqlProtocol\Utils\Charset;

class ConstantsTest extends TestCase
{
    public function testCapabilitiesHaveMySQL8Flags(): void
    {
        $this->assertSame(0x01000000, Capabilities::CLIENT_DEPRECATE_EOF->value);
        $this->assertSame(0x00400000, Capabilities::CLIENT_SESSION_TRACK->value);
    }

    public function testCapabilitiesGetName(): void
    {
        $name = Capabilities::getName(Capabilities::CLIENT_DEPRECATE_EOF->value);
        $this->assertNotNull($name);
    }

    public function testServerStatusFlags(): void
    {
        $this->assertSame(0x0001, ServerStatus::SERVER_STATUS_IN_TRANS);
        $this->assertSame(0x0002, ServerStatus::SERVER_STATUS_AUTOCOMMIT);
        $this->assertSame(0x4000, ServerStatus::SERVER_SESSION_STATE_CHANGED);
    }

    public function testColumnTypeNames(): void
    {
        $this->assertSame('TINY', MySQLColumnType::getName(MySQLColumnType::MYSQL_TYPE_TINY));
        $this->assertSame('LONG', MySQLColumnType::getName(MySQLColumnType::MYSQL_TYPE_LONG));
        $this->assertSame('VARCHAR', MySQLColumnType::getName(MySQLColumnType::MYSQL_TYPE_VARCHAR));
        $this->assertSame('JSON', MySQLColumnType::getName(MySQLColumnType::MYSQL_TYPE_JSON));
        $this->assertSame('BLOB', MySQLColumnType::getName(MySQLColumnType::MYSQL_TYPE_BLOB));
        $this->assertSame('UNKNOWN', MySQLColumnType::getName(0x99));
    }

    public function testCharsetGetByIndex(): void
    {
        $this->assertSame('utf8mb3', Charset::getCharsetNameByIndex(33));
        $this->assertSame('utf8mb4', Charset::getCharsetNameByIndex(45));
        $this->assertSame('binary', Charset::getCharsetNameByIndex(63));
    }

    public function testCharsetGetByName(): void
    {
        $this->assertSame(45, Charset::getCharsetIndexByName('utf8mb4'));
        $this->assertSame(63, Charset::getCharsetIndexByName('binary'));
        $this->assertSame(33, Charset::getCharsetIndexByName('utf8mb3'));
    }

    public function testCharsetCaseInsensitive(): void
    {
        $this->assertSame(45, Charset::getCharsetIndexByName('UTF8MB4'));
        $this->assertSame(45, Charset::getCharsetIndexByName('Utf8Mb4'));
    }

    public function testCharsetInvalidIndex(): void
    {
        $this->expectException(\Workbunny\MysqlProtocol\Exceptions\InvalidArgumentException::class);
        Charset::getCharsetNameByIndex(99999);
    }

    public function testCharsetInvalidName(): void
    {
        $this->expectException(\Workbunny\MysqlProtocol\Exceptions\InvalidArgumentException::class);
        Charset::getCharsetIndexByName('nonexistent_charset');
    }

    public function testGetAllCharsets(): void
    {
        $all = Charset::getAllCharsets();
        $this->assertIsArray($all);
        $this->assertArrayHasKey(45, $all);
        $this->assertSame('utf8mb4', $all[45]);
    }
}
