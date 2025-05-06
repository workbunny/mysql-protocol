<?php

declare(strict_types=1);

namespace nWorkbunny\MysqlProtocol\Packets;

use nWorkbunny\MysqlProtocol\Exceptions\PacketException;
use nWorkbunny\MysqlProtocol\Utils\Binary;
use InvalidArgumentException;
use nWorkbunny\MysqlProtocol\Utils\Packet;

class Error implements PacketInterface
{
    public const PACKET_FLAG = 0xFF;

    /**
     * 解包 ERROR 包（payload部分，不包括包头）。
     *
     * @param Binary $binary
     * @return array
     *         - header: int 固定 0xFF
     *         - error_code: int
     *         - sql_state: string (5字节)
     *         - error_message: string
     */
    public static function unpack(Binary $binary): array
    {
        try {
            return Packet::parser(function (Binary $binary) {
                // 1. 读取包头，必须为 0xFF
                $flag = $binary->readByte();
                if ($flag !== self::PACKET_FLAG) {
                    throw new PacketException("Error: Invalid packet flag '$flag', expected 0xFF");
                }
                // 2. 读取 error_code：2字节 little-endian
                $errorCode = $binary->readUB(Binary::UB2);
                // 3. 读取 SQL State Marker，预期为 '#' (ASCII 35)
                $marker = $binary->readByte();
                if ($marker !== ord('#')) {
                    // 兼容旧协议：如果没有 '#'，认为没有 SQL state信息
                    $sqlState = '';
                    // 同时把 marker 字节作为错误消息的起始字节处理
                    $errorMsgBytes = array_merge([$marker], $binary->readBytes($binary->length() - $binary->getReadCursor()));
                    $errorMessage = Binary::BytesToString($errorMsgBytes);
                } else {
                    // 4. 读取接下来的 5 字节作为 SQL state
                    $sqlStateBytes = $binary->readBytes(5);
                    $sqlState = Binary::BytesToString($sqlStateBytes);
                    // 5. 剩余部分为错误消息
                    $remaining = $binary->length() - $binary->getReadCursor();
                    $errorMsg = '';
                    if ($remaining > 0) {
                        $errorMsgBytes = $binary->readBytes($remaining);
                        $errorMsg = Binary::BytesToString($errorMsgBytes);
                    }
                    $errorMessage = $errorMsg;
                }

                return [
                    'flag'              => $flag,
                    'error_code'        => $errorCode,
                    'sql_state'         => $sqlState,
                    'error_message'     => $errorMessage,
                ];
            }, $binary);
        } catch (InvalidArgumentException $e) {
            throw new PacketException("Error: Failed to unpack Error packet [{$e->getMessage()}]", $e->getCode(), $e);
        }
    }

    /**
     * 封装 ERROR 包为 Binary 对象（payload部分，不包含包头）。
     *
     * 要求 $data 至少包含以下键：
     *   - error_code (int)
     *   - error_message (string)
     * 可选：
     *   - sql_state (string), 默认使用 'HY000'
     *
     * @param array $data
     * @return Binary
     */
    public static function pack(array $data): Binary
    {
        $packetId              = $data['packet_id'] ?? 0;
        try {
            return Packet::binary(function (Binary $binary) use ($data) {
                $errorCode             = $data['error_code'] ?? 0;
                $sqlState              = $data['sql_state'] ?? 'HY000';
                $errorMessage          = $data['error_message'] ?? null;
                // 1. 写入 OK 包头 0x00
                $binary->writeByte(self::PACKET_FLAG);
                // 2. 写入 error code，2 字节 little-endian
                $binary->writeUB((int)$errorCode, Binary::UB2);
                // 3. 写入 SQL state marker '#' 和 5 字节 SQL state
                $binary->writeByte(ord('#'));
                // 不足 5 字节则用空格补齐，多余取前 5 字节
                $sqlState = str_pad($sqlState, 5, ' ');
                $binary->writeBytes(Binary::StringToBytes(substr($sqlState, 0, 5)));
                // 4. 写入错误消息（剩余部分）
                if ($errorMessage) {
                    $binary->writeBytes(Binary::StringToBytes($errorMessage));
                }
            }, $packetId);
        } catch (InvalidArgumentException $e) {
            throw new PacketException("Error: Failed to pack Error packet [{$e->getMessage()}]", $e->getCode(), $e);
        }
    }
}
