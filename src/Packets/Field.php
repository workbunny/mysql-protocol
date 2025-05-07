<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

class Field implements PacketInterface
{
    public const PACKET_FIXED_LENGTH = 0x0c;

    /**
     * 解析单个字段定义包（协议 41 格式）。
     *
     * 依次解析以下字段：
     *   - catalog, schema, table, org_table, name, org_name（均为长度编码字符串）
     *   - 固定字段长度（1 字节，必须为 0x0c）
     *   - character_set (2 字节)
     *   - column_length (4 字节)
     *   - type (1 字节)
     *   - flags (2 字节)
     *   - decimals (1 字节)
     *   - filler (跳过 2 字节)
     *
     * @param Binary $binary
     * @return array
     */
    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            $result = [];
            $result['catalog']   = $binary->readLenEncString();
            $result['schema']    = $binary->readLenEncString();
            $result['table']     = $binary->readLenEncString();
            $result['org_table'] = $binary->readLenEncString();
            $result['name']      = $binary->readLenEncString();
            $result['org_name']  = $binary->readLenEncString();

            // 固定字段长度，应该为 0x0c
            $fixedLength = $binary->readByte();
            if ($fixedLength !== self::PACKET_FIXED_LENGTH) {
                throw new PacketException("Invalid packet fixed length '$fixedLength', expected 0x0c",  ExceptionCode::ERROR_VALUE);
            }
            $result['fixed_length']    = $fixedLength;
            $result['character_set']   = $binary->readUB(Binary::UB2);
            $result['column_length']   = $binary->readUB(Binary::UB4);
            $result['type']            = $binary->readByte();
            $result['flags']           = $binary->readUB(Binary::UB2);
            $result['decimals']        = $binary->readByte();
            // 跳过 2 字节 filler
            $binary->readBytes(2);

            return $result;
        }, $binary);
    }

    /**
     * 封装字段定义数据为 FieldPacket 的 Binary 对象（payload部分）。
     *
     * 所需数据键包括 catalog, schema, table, org_table, name, org_name, character_set,
     * column_length, type, flags, decimals。
     *
     * @param array $data
     * @return Binary
     */
    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $binary->writeLenEncString($data['catalog'] ?? 'def');
            $binary->writeLenEncString( $data['schema']    ?? '');
            $binary->writeLenEncString( $data['table']     ?? '');
            $binary->writeLenEncString( $data['org_table'] ?? '');
            $binary->writeLenEncString( $data['name']      ?? '');
            $binary->writeLenEncString( $data['org_name']  ?? '');
            // 固定长度字段：始终写入 0x0c
            $binary->writeByte(self::PACKET_FIXED_LENGTH);
            $binary->writeUB((int)($data['character_set'] ?? 33), Binary::UB2);
            $binary->writeUB((int)($data['column_length'] ?? 0), Binary::UB4);
            $binary->writeByte((int)($data['type'] ?? 0));
            $binary->writeUB((int)($data['flags'] ?? 0), Binary::UB2);
            $binary->writeByte((int)($data['decimals'] ?? 0));
            // 写入 2 字节 filler
            $binary->writeBytes([0x00, 0x00]);
        }, $data['packet_id'] ?? 0);
    }
}
