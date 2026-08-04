<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

/**
 * COM_STMT_FETCH — 从游标抓取行数据
 */
class StmtFetch implements PacketInterface
{
    public const COMMAND = 0x1C;

    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            $command = $binary->readByte();
            if ($command !== self::COMMAND) {
                throw new PacketException("Invalid command '$command', expected 0x1C", ExceptionCode::ERROR_VALUE);
            }
            $stmtId  = $binary->readUB(Binary::UB4);
            $numRows = $binary->readUB(Binary::UB4);
            return [
                'command'  => $command,
                'stmt_id'  => $stmtId,
                'num_rows' => $numRows,
            ];
        }, $binary);
    }

    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $stmtId  = (int)($data['stmt_id'] ?? 0);
            $numRows = (int)($data['num_rows'] ?? 1);
            $binary->writeByte(self::COMMAND);
            $binary->writeUB($stmtId, Binary::UB4);
            $binary->writeUB($numRows, Binary::UB4);
        }, (int)($data['packet_id'] ?? 0));
    }
}
