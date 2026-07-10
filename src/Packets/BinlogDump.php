<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

/**
 * COM_BINLOG_DUMP 数据包
 *
 * 从库请求主库开始发送 binlog 事件流（非 GTID 模式）。
 *
 * 结构：
 *   [1字节命令码 0x12]
 *   [4字节 binlog pos]
 *   [2字节 flags]
 *   [4字节 server_id]
 *   [剩余字节 binlog filename]
 *
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_replication_binlog_dump.html
 */
class BinlogDump implements PacketInterface
{
    public const COMMAND = 0x12;

    public const BINLOG_DUMP_NON_BLOCK = 0x01;
    public const BINLOG_THROUGH_GTID = 0x02;
    public const BINLOG_DUMP_NONBLOCK_CONTINUOUS = 0x04;

    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            $command = $binary->readByte();
            if ($command !== self::COMMAND) {
                throw new PacketException("Invalid command '$command', expected 0x12", ExceptionCode::ERROR_VALUE);
            }
            $binlogPos = $binary->readUB(Binary::UB4);
            $flags = $binary->readUB(Binary::UB2);
            $serverId = $binary->readUB(Binary::UB4);
            $remaining = $binary->length() - $binary->getReadCursor();
            $binlogFilename = '';
            if ($remaining > 0) {
                $binlogFilename = Binary::BytesToString($binary->readBytes($remaining));
            }
            return [
                'command'         => $command,
                'binlog_pos'      => $binlogPos,
                'flags'           => $flags,
                'server_id'       => $serverId,
                'binlog_filename' => $binlogFilename,
            ];
        }, $binary);
    }

    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $binlogPos      = (int)($data['binlog_pos'] ?? 4);
            $flags          = (int)($data['flags'] ?? 0);
            $serverId       = (int)($data['server_id'] ?? 0);
            $binlogFilename = (string)($data['binlog_filename'] ?? '');
            $binary->writeByte(self::COMMAND);
            $binary->writeUB($binlogPos, Binary::UB4);
            $binary->writeUB($flags, Binary::UB2);
            $binary->writeUB($serverId, Binary::UB4);
            if ($binlogFilename !== '') {
                $binary->writeBytes(Binary::StringToBytes($binlogFilename));
            }
        }, (int)($data['packet_id'] ?? 0));
    }
}
