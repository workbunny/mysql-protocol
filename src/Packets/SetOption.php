<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

/**
 * COM_SET_OPTION — 设置客户端选项 (multi-statements)
 */
class SetOption implements PacketInterface
{
    public const COMMAND = 0x1B;

    public const MYSQL_OPTION_MULTI_STATEMENTS_ON  = 0;
    public const MYSQL_OPTION_MULTI_STATEMENTS_OFF = 1;

    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            $command = $binary->readByte();
            if ($command !== self::COMMAND) {
                throw new PacketException("Invalid command '$command', expected 0x1B", ExceptionCode::ERROR_VALUE);
            }
            $option = $binary->readUB(Binary::UB2);
            return ['command' => $command, 'option' => $option];
        }, $binary);
    }

    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $option = (int)($data['option'] ?? self::MYSQL_OPTION_MULTI_STATEMENTS_ON);
            $binary->writeByte(self::COMMAND);
            $binary->writeUB($option, Binary::UB2);
        }, (int)($data['packet_id'] ?? 0));
    }
}
