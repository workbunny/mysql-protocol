<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

/**
 * COM_STMT_CLOSE — 关闭预处理语句
 */
class StmtClose implements PacketInterface
{
    public const COMMAND = 0x19;

    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            $command = $binary->readByte();
            if ($command !== self::COMMAND) {
                throw new PacketException("Invalid command '$command', expected 0x19", ExceptionCode::ERROR_VALUE);
            }
            $stmtId = $binary->readUB(Binary::UB4);
            return ['command' => $command, 'stmt_id' => $stmtId];
        }, $binary);
    }

    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $binary->writeByte(self::COMMAND);
            $binary->writeUB((int)($data['stmt_id'] ?? 0), Binary::UB4);
        }, (int)($data['packet_id'] ?? 0));
    }
}
