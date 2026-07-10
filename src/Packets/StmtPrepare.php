<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

/**
 * COM_STMT_PREPARE 数据包
 *
 * 客户端请求服务器预处理 SQL 语句。
 *
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_stmt_prepare.html
 */
class StmtPrepare implements PacketInterface
{
    public const COMMAND = 0x16;

    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            $command = $binary->readByte();
            $remaining = $binary->length() - $binary->getReadCursor();
            $sql = '';
            if ($remaining > 0) {
                $sql = Binary::BytesToString($binary->readBytes($remaining));
            }
            return [
                'command' => $command,
                'sql'     => $sql,
            ];
        }, $binary);
    }

    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $sql = (string)($data['sql'] ?? '');
            $binary->writeByte(self::COMMAND);
            if ($sql !== '') {
                $binary->writeBytes(Binary::StringToBytes($sql));
            }
        }, (int)($data['packet_id'] ?? 0));
    }
}
