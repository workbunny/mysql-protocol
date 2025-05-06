<?php

declare(strict_types=1);

namespace nWorkbunny\MysqlProtocol\Packets;

use nWorkbunny\MysqlProtocol\Exceptions\PacketException;
use nWorkbunny\MysqlProtocol\Utils\Binary;
use InvalidArgumentException;
use nWorkbunny\MysqlProtocol\Utils\Packet;

class Ok implements PacketInterface
{
    public const PACKET_FLAG = 0x00;

    /**
     * 解包 OK 包（payload部分，不包括 MySQL 包头）。
     *
     * @param Binary $binary
     * @return array
     *         - header: int 固定值 0x00
     *         - affected_rows: int
     *         - last_insert_id: int
     *         - status_flags: int (2字节)
     *         - warnings: int (2字节)
     *         - info: string
     */
    public static function unpack(Binary $binary): array
    {
        try {
            return Packet::parser(function (Binary $binary) {
                // 1. 读取tag，必须为 0x00
                $flag = $binary->readByte();
                if ($flag !== self::PACKET_FLAG) {
                    throw new PacketException("Error: Invalid packet flag '$flag', expected 0x00");
                }
                // 2. 读取 length-encoded affected rows
                $affectedRows = $binary->readLenEncInt();
                // 3. 读取 length-encoded last insert id
                $lastInsertId = $binary->readLenEncInt();
                // 4. 读取2字节 status flags
                $statusFlags = $binary->readUB(Binary::UB2);
                // 5. 读取2字节 warnings count
                $warnings = $binary->readUB(Binary::UB2);
                // 6. 剩下的为 info 字符串
                $info = null;
                $remaining = $binary->length() - $binary->getReadCursor();
                if ($remaining > 0) {
                    $info = Binary::BytesToString($binary->readBytes($remaining));
                }

                return [
                    'flag'              => $flag,
                    'affected_rows'     => $affectedRows,
                    'last_insert_id'    => $lastInsertId,
                    'status_flags'      => $statusFlags,
                    'warnings'          => $warnings,
                    'info'              => $info,
                ];
            }, $binary);
        } catch (InvalidArgumentException $e) {
            throw new PacketException("Error: Failed to unpack OK packet [{$e->getMessage()}]", $e->getCode(), $e);
        }
    }

    /**
     * 封装 OK 包为 Binary 对象（payload部分，不包含 4 字节包头）。
     *
     * 要求 $data 至少包含以下键：
     *   - affected_rows (int)
     *   - last_insert_id (int)
     *   - status_flags (int)
     *   - warnings (int)
     *   - info (string，可选)
     *
     * @param array $data
     * @return Binary
     */
    public static function pack(array $data): Binary
    {
        try {
            return Packet::binary(function (Binary $binary) use ($data) {
                $affectedRows          = $data['affected_rows'] ?? 0;
                $lastInsertId          = $data['last_insert_id'] ?? 0;
                $statusFlags           = $data['status_flags'] ?? 0;
                $warnings              = $data['warnings'] ?? 0;
                $info                  = $data['info'] ?? null;
                // 1. 写入 OK 包头 0x00
                $binary->writeByte(self::PACKET_FLAG);
                // 2. 写入 affected rows 以长度编码整数格式写入
                $binary->writeLenEncInt((int)$affectedRows);
                // 3. 写入 last insert id 以长度编码整数格式写入
                $binary->writeLenEncInt((int)$lastInsertId);
                // 4. 写入 status flags (2 字节)
                $binary->writeUB((int)$statusFlags, Binary::UB2);
                // 5. 写入 warnings (2 字节)
                $binary->writeUB((int)$warnings, Binary::UB2);
                // 6. 写入 info 字符串（如果存在）
                if ($info) {
                    $binary->writeBytes(Binary::StringToBytes($info));
                }
            }, $data['packet_id'] ?? 0);
        } catch (InvalidArgumentException $e) {
            throw new PacketException("Error: Failed to pack OK packet [{$e->getMessage()}]", $e->getCode(), $e);
        }
    }
}
