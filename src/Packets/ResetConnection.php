<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

/**
 * COM_RESET_CONNECTION — 重置连接状态
 */
class ResetConnection implements PacketInterface
{
    public const COMMAND = 0x1F;

    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            $command = $binary->readByte();
            if ($command !== self::COMMAND) {
                throw new PacketException("Invalid command '$command', expected 0x1F", ExceptionCode::ERROR_VALUE);
            }
            return ['command' => $command];
        }, $binary);
    }

    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) {
            $binary->writeByte(self::COMMAND);
        }, (int)($data['packet_id'] ?? 0));
    }
}
