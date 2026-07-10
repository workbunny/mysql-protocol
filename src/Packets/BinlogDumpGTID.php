<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

/**
 * COM_BINLOG_DUMP_GTID 数据包
 *
 * 从库请求主库开始发送 binlog 事件流（GTID 模式）。
 *
 * 结构：
 *   [1字节命令码 0x1E]
 *   [2字节 flags]
 *   [4字节 server_id]
 *   [4字节 binlog filename 长度] [binlog filename 字符串]
 *   [8字节 binlog pos]
 *   [4字节 gtid data 长度] [gtid data]
 *
 * gtid_data 格式：
 *   [8字节 number of sids]
 *   重复以下结构 number_of_sids 次:
 *     [16字节 SID (UUID)]
 *     [8字节 number of intervals]
 *     重复以下结构 number_of_intervals 次:
 *       [8字节 start]
 *       [8字节 end (exclusive)]
 *
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_replication_binlog_dump_gtid.html
 */
class BinlogDumpGTID implements PacketInterface
{
    public const COMMAND = 0x1E;

    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            $command = $binary->readByte();
            if ($command !== self::COMMAND) {
                throw new PacketException("Invalid command '$command', expected 0x1E", ExceptionCode::ERROR_VALUE);
            }
            // flags (2 字节)
            $flags = $binary->readUB(Binary::UB2);
            // server_id (4 字节)
            $serverId = $binary->readUB(Binary::UB4);
            // binlog filename (4 字节长度 + 字符串)
            $filenameLen = $binary->readUB(Binary::UB4);
            $binlogFilename = Binary::BytesToString($binary->readBytes($filenameLen));
            // binlog pos (8 字节)
            $binlogPos = $binary->readUB(Binary::UB8);
            // gtid data (4 字节长度 + 数据)
            $gtidDataLen = $binary->readUB(Binary::UB4);
            $gtidData = $binary->readBytes($gtidDataLen);
            // 解析 gtid sets
            $gtidSets = self::parseGtidSets($gtidData);

            return [
                'command'         => $command,
                'flags'           => $flags,
                'server_id'       => $serverId,
                'binlog_filename' => $binlogFilename,
                'binlog_pos'      => $binlogPos,
                'gtid_data'       => $gtidData,
                'gtid_sets'       => $gtidSets,
            ];
        }, $binary);
    }

    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $flags          = (int)($data['flags'] ?? 0);
            $serverId       = (int)($data['server_id'] ?? 0);
            $binlogFilename = (string)($data['binlog_filename'] ?? '');
            $binlogPos      = (int)($data['binlog_pos'] ?? 4);

            $binary->writeByte(self::COMMAND);
            $binary->writeUB($flags, Binary::UB2);
            $binary->writeUB($serverId, Binary::UB4);

            // binlog filename (4 字节长度 + 字符串)
            $filenameBytes = Binary::StringToBytes($binlogFilename);
            $binary->writeUB(count($filenameBytes), Binary::UB4);
            $binary->writeBytes($filenameBytes);

            // binlog pos (8 字节)
            $binary->writeUB($binlogPos, Binary::UB8);

            // gtid data
            if (isset($data['gtid_sets'])) {
                $gtidData = self::buildGtidSets($data['gtid_sets']);
            } else {
                $gtidData = (array)($data['gtid_data'] ?? []);
            }
            $binary->writeUB(count($gtidData), Binary::UB4);
            $binary->writeBytes($gtidData);
        }, (int)($data['packet_id'] ?? 0));
    }

    /**
     * 解析 GTID sets 字节数据
     *
     * @param array $bytes
     * @return array
     */
    private static function parseGtidSets(array $bytes): array
    {
        if (empty($bytes)) {
            return [];
        }
        $sub = new Binary($bytes);
        $result = [];
        $numSids = $sub->readUB(Binary::UB8);
        for ($i = 0; $i < $numSids; $i++) {
            $sidBytes = $sub->readBytes(16);
            $sid = implode('-', array_map(
                fn($chunk) => bin2hex(implode('', array_map('chr', $chunk))),
                [array_slice($sidBytes, 0, 4), array_slice($sidBytes, 4, 2), array_slice($sidBytes, 6, 2), array_slice($sidBytes, 8, 2), array_slice($sidBytes, 10, 6)]
            ));
            $numIntervals = $sub->readUB(Binary::UB8);
            $intervals = [];
            for ($j = 0; $j < $numIntervals; $j++) {
                $start = $sub->readUB(Binary::UB8);
                $end = $sub->readUB(Binary::UB8);
                $intervals[] = ['start' => $start, 'end' => $end];
            }
            $result[] = ['sid' => $sid, 'intervals' => $intervals];
        }
        return $result;
    }

    /**
     * 构建 GTID sets 字节数据
     *
     * @param array $gtidSets
     * @return array
     */
    private static function buildGtidSets(array $gtidSets): array
    {
        $sub = new Binary();
        $sub->writeUB(count($gtidSets), Binary::UB8);
        foreach ($gtidSets as $entry) {
            $sid = $entry['sid'] ?? '';
            $sidHex = str_replace('-', '', $sid);
            $sidBytes = [];
            for ($i = 0; $i < 32; $i += 2) {
                $sidBytes[] = hexdec(substr($sidHex, $i, 2));
            }
            $sub->writeBytes($sidBytes);
            $intervals = $entry['intervals'] ?? [];
            $sub->writeUB(count($intervals), Binary::UB8);
            foreach ($intervals as $interval) {
                $sub->writeUB((int)$interval['start'], Binary::UB8);
                $sub->writeUB((int)$interval['end'], Binary::UB8);
            }
        }
        return $sub->unpack();
    }
}
