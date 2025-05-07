<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

class EOF implements PacketInterface
{
    public const PACKET_FLAG = 0xFE;
    /**
     * 解析 EOF 包（payload部分）。
     *
     * 结构：
     *   - 1 字节：包头（应为 0xFE）
     *   - 2 字节：warnings 数
     *   - 2 字节：状态标志
     *
     * @param Binary $binary
     * @return array ['header'=>int, 'warnings'=>int, 'status_flags'=>int]
     */
    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            $result = [];
            $flag = $binary->readByte();
            if ($flag !== self::PACKET_FLAG) {
                throw new PacketException("Invalid packet flag '$flag', expected 0x00", ExceptionCode::ERROR_VALUE);
            }
            $result['flag']         = $flag;
            $result['warnings']     = $binary->readUB(Binary::UB2);
            $result['status_flags'] = $binary->readUB(Binary::UB2);
            return $result;
        }, $binary);
    }

    /**
     * 封装 EOF 包为 Binary 对象（payload部分）。
     *
     * 要求数组可包含：
     *   - warnings (int)
     *   - status_flags (int)
     *
     * @param array $data
     * @return Binary
     */
    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            // 写入 0xFE 作为包头标识
            $binary->writeByte(self::PACKET_FLAG);
            $binary->writeUB((int)($data['warnings'] ?? 0), Binary::UB2);
            $binary->writeUB((int)($data['status_flags'] ?? 0), Binary::UB2);
        }, $data['packet_id'] ?? 0);
    }
}
