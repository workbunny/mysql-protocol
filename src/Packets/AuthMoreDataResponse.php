<?php

declare(strict_types=1);

namespace nWorkbunny\MysqlProtocol\Packets;

use nWorkbunny\MysqlProtocol\Exceptions\PacketException;
use nWorkbunny\MysqlProtocol\Utils\Binary;
use nWorkbunny\MysqlProtocol\Utils\Packet;

/**
 * AuthMoreDataResponse 用于客户端发送全认证响应数据。
 *
 * 该包的 payload 通常即为客户端完整认证数据，
 * 例如在非安全连接下客户端使用服务器提供的 RSA 公钥对密码加密后的结果。
 */
class AuthMoreDataResponse implements PacketInterface
{
    /**
     * 从 Binary 对象中解包 AuthMoreDataResponse 数据包（payload部分，不含包头）。
     *
     * @param Binary $binary
     * @return array 包含：
     *               - auth_response: string，全认证的响应数据
     */
    public static function unpack(Binary $binary): array
    {
        try {
            return Packet::parser(function (Binary $binary) {
                $remainingLength = $binary->length() - $binary->getReadCursor();
                $responseBytes = $binary->readBytes($remainingLength);
                return [
                    'auth_response' => Binary::BytesToString($responseBytes),
                ];
            }, $binary);
        } catch (\InvalidArgumentException $e) {
            throw new PacketException("Error: Failed to unpack auth more data response packet [{$e->getMessage()}]", $e->getCode(), $e);
        }
    }

    /**
     * 将 AuthMoreDataResponse 数据包打包为 Binary 对象（payload部分，不含包头）。
     *
     * 要求传入的数组包含键：
     *   - auth_response: string，客户端计算或加密后的全认证数据
     *
     * @param array $data
     * @return Binary
     */
    public static function pack(array $data): Binary
    {
        $packetId = $data['packet_id'] ?? 0;
        try {
            return Packet::binary(function (Binary $binary) use ($data) {
                $authResponse = $data['auth_response'] ?? '';
                $binary->writeBytes(Binary::StringToBytes($authResponse));
            }, $packetId);
        } catch (\InvalidArgumentException $e) {
            throw new PacketException("Error: Failed to pack auth more data response packet [{$e->getMessage()}]", $e->getCode(), $e);
        }
    }
}
