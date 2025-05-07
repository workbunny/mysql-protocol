<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

class ResultSetHeader implements PacketInterface
{
    /**
     * 从 Binary 对象中解析结果集头包（payload部分），返回字段数。
     *
     * @param Binary $binary
     * @return array ['field_count' => int]
     */
    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            return [
                'field_count' => $binary->readLenEncInt()
            ];
        }, $binary);
    }

    /**
     * 将结果集头数据组装为 Binary 对象（payload部分）。
     *
     * 数组中须包含：
     *  - field_count: int
     *
     * @param array $data
     * @return Binary
     */
    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $fieldCount = (int)($data['field_count'] ?? 0);
            $binary->writeLenEncInt($fieldCount);
        }, $data['packet_id'] ?? 0);
    }
}
