<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use InvalidArgumentException;
use Workbunny\MysqlProtocol\Utils\Packet;

class RowData implements PacketInterface
{
    public const NULL_VALUE = 0xFB;

    /**
     * 解析一行数据包（payload部分）。
     *
     * 该包由多个列值组成，每个列值以长度编码字符串表示；当值为 NULL 时，表示为单字节 0xFB。
     *
     * @param Binary $binary
     * @return array 每个元素对应一列的值（NULL 或字符串）
     */
    public static function unpack(Binary $binary): array
    {
        try {
            return Packet::parser(function (Binary $binary) {
                $values = [];
                // 循环读取，直到达到数据包末尾
                while ($binary->getReadCursor() < $binary->length()) {
                    // 读取第一个字节判断是否为 NULL 指示符 0xFB
                    $nextByte = $binary->readByte();
                    if ($nextByte === self::NULL_VALUE) {
                        $values[] = null;
                    } else {
                        // 非 NULL 值：将刚刚读的字节退回（减 1 个指针位置），再完整读取长度编码字符串
                        $binary->setReadCursor($binary->getReadCursor() - 1);
                        $value = $binary->readLenEncString();
                        $values[] = $value;
                    }
                }
                return [
                    'values' => $values,
                ];
            }, $binary);
        } catch (InvalidArgumentException $e) {
            throw new PacketException("Error: Failed to unpack row data packet [{$e->getMessage()}]", $e->getCode(), $e);
        }

    }

    /**
     * 将一行数据封装为 RowDataPacket（payload部分）。
     *
     * 输入为数组，每个元素代表一列的值。NULL 值写为单字节 0xFB，
     * 非 NULL 值以长度编码字符串写入。
     *
     * @param array $data
     * @return Binary
     */
    public static function pack(array $data): Binary
    {
        try {
            return Packet::binary(function (Binary $binary) use ($data) {
                foreach (($data['values'] ?? []) as $value) {
                    if (is_null($value)) {
                        $binary->writeByte(self::NULL_VALUE);
                    } else {
                        $binary->writeLenEncString((string)$value);
                    }
                }
            }, $data['packet_id'] ?? 0);
        } catch (InvalidArgumentException $e) {
            throw new PacketException("Error: Failed to pack row data packet [{$e->getMessage()}]", $e->getCode(), $e);
        }
    }
}
