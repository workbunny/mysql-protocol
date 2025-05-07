<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

/**
 * AuthMoreDataRequest 用于服务器向客户端请求全认证数据。
 *
 * 此数据包一般结构为：
 *   [1 字节标志] + [可选：附加数据]
 *
 * 标志通常为：
 *   0x01 表示「请求全认证」（fast authentication 失败，需要客户端发送完整认证数据）
 *   （其他的值也可能代表其它状态，可根据实际需要调整）
 */
class AuthMoreDataRequest implements PacketInterface
{
    public const PACKET_FLAG = 0x01;

    /**
     * 从 Binary 对象中解包 AuthMoreDataRequest 数据包（payload，包括包头已剥离）。
     *
     * @param Binary $binary
     * @return array 包含：
     *               - flag: int，标志字节
     *               - extra_data: string，附加数据（可能为空）
     */
    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            // 读取第 1 个字节作为标志
            $flag = $binary->readByte();
            if ($flag !== self::PACKET_FLAG) {
                throw new PacketException("Invalid packet flag '$flag', expected 0x01", ExceptionCode::ERROR_VALUE);
            }
            // 如果后续有附加数据，则读取之
            $remainingLength = $binary->length() - $binary->getReadCursor();
            $extraData = '';
            if ($remainingLength > 0) {
                $extraData = Binary::BytesToString($binary->readBytes($remainingLength));
            }

            return [
                'flag'          => $flag,
                'extra_data'    => $extraData,
            ];
        }, $binary);
    }

    /**
     * 将 AuthMoreDataRequest 数据包打包为 Binary 对象（payload部分，不包含 4 字节包头）。
     *
     * 需要传入的数据数组至少包含键：
     *   - flag: int，标志字节（默认 0x01 表示请求全认证）
     *
     * 可选键：
     *   - extra_data: string，附加数据
     *
     * @param array $data
     * @return Binary
     */
    public static function pack(array $data): Binary
    {
        $packetId = $data['packet_id'] ?? 0;
        return Packet::binary(function (Binary $binary) use ($data) {
            $extraData = $data['extra_data'] ?? null;
            $binary->writeByte(self::PACKET_FLAG);
            if ($extraData) {
                $binary->writeBytes(Binary::StringToBytes($extraData));
            }
        }, $packetId);
    }
}
