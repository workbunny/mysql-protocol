<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

/**
 * COM_STMT_SEND_LONG_DATA — 分块发送 BLOB 参数数据
 */
class StmtSendLongData implements PacketInterface
{
    public const COMMAND = 0x18;

    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            $command = $binary->readByte();
            if ($command !== self::COMMAND) {
                throw new PacketException("Invalid command '$command', expected 0x18", ExceptionCode::ERROR_VALUE);
            }
            $stmtId  = $binary->readUB(Binary::UB4);
            $paramId = $binary->readUB(Binary::UB2);
            $remaining = $binary->length() - $binary->getReadCursor();
            $data = $remaining > 0 ? $binary->readBytes($remaining) : [];
            return [
                'command'  => $command,
                'stmt_id'  => $stmtId,
                'param_id' => $paramId,
                'data'     => $data,
            ];
        }, $binary);
    }

    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $stmtId  = (int)($data['stmt_id'] ?? 0);
            $paramId = (int)($data['param_id'] ?? 0);
            $chunk   = (array)($data['data'] ?? []);
            $binary->writeByte(self::COMMAND);
            $binary->writeUB($stmtId, Binary::UB4);
            $binary->writeUB($paramId, Binary::UB2);
            if (!empty($chunk)) {
                $binary->writeBytes($chunk);
            }
        }, (int)($data['packet_id'] ?? 0));
    }
}
