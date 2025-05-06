<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

class AuthSwitchResponse implements PacketInterface
{
    /**
     * 从 Binary 对象中解包 AuthSwitchResponse 数据包。
     * 结构：整个包体为客户端计算得到的认证响应数据
     *
     * @param Binary $binary
     * @return array 包含：
     *               - auth_response: string
     */
    public static function unpack(Binary $binary): array
    {
        try {
            return Packet::parser(function (Binary $binary) {
                // 读取剩余的所有字节作为认证响应
                $remainingLength = $binary->length() - $binary->getReadCursor();
                $authResponse = Binary::BytesToString($binary->readBytes($remainingLength));

                return [
                    'auth_response' => $authResponse,
                ];
            }, $binary);
        } catch (\InvalidArgumentException $e) {
            throw new PacketException("Error: Failed to unpack auth switch response packet [{$e->getMessage()}]", $e->getCode(), $e);
        }

    }

    /**
     * 将 AuthSwitchResponse 数据包内容封装为 Binary 对象。
     *
     * @param array $data 数组包含：
     *                      - auth_response: 客户端针对新的认证挑战计算后的响应（字符串）
     * @return Binary
     */
    public static function pack(array $data): Binary
    {
        $packetId     = $data['packet_id'] ?? 0;
        try {
            return Packet::binary(function (Binary $binary) use ($data) {
                $authResponse = $data['auth_response'] ?? '';
                $binary->writeBytes(Binary::StringToBytes($authResponse));
            }, $packetId);
        } catch (\InvalidArgumentException $e) {
            throw new PacketException("Error: Failed to pack auth switch response packet [{$e->getMessage()}]", $e->getCode(), $e);
        }

    }
}

