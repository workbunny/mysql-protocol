<?php

declare(strict_types=1);

namespace nWorkbunny\MysqlProtocol\Packets;

use nWorkbunny\MysqlProtocol\Exceptions\PacketException;
use nWorkbunny\MysqlProtocol\Utils\Binary;
use nWorkbunny\MysqlProtocol\Utils\Packet;

class HandshakeResponse implements PacketInterface
{
    // 以下常量定义了常用的 capability flag 值
    public const CLIENT_PROTOCOL_41 = 0x0200;
    public const CLIENT_SECURE_CONNECTION = 0x8000;
    public const CLIENT_PLUGIN_AUTH = 0x00080000;
    public const CLIENT_CONNECT_WITH_DB = 0x00000008;
    public const CLIENT_CONNECT_ATTRS = 0x00100000;
    public const CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA = 0x00200000;

    /**
     * 从传入的 Binary 对象中解析握手响应包（payload，不包括 4 字节包头）。
     *
     * @param Binary $binary
     * @return array 解析后的数组，键值包括：
     *               - capability_flags (int)
     *               - max_packet_size (int)
     *               - character_set (int)
     *               - reserved (23 字节的字符串，通常全 0)
     *               - username (string)
     *               - auth_response (string)
     *               - database (string，可选)
     *               - auth_plugin (string，可选)
     *               - attributes (array，可选)
     */
    public static function unpack(Binary $binary): array
    {
        try {
            return Packet::parser(function (Binary $binary) {
                // 1. 能力标志：4 字节
                $capabilityFlags = $binary->readUB(Binary::UB4);
                // 2. 最大数据包大小：4 字节
                $maxPacketSize = $binary->readUB(Binary::UB4);
                // 3. 字符集：1 字节
                $characterSet = $binary->readByte();
                // 4. 保留 23 字节
                $reserved = $binary->readBytes(23);
                // 5. 用户名，以 NULL 终止
                $username = Binary::BytesToString($binary->readNullTerminated());
                // 6. 认证数据的读取
                if ($capabilityFlags & self::CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA) {
                    $authResponse = $binary->readLenEncString();
                } elseif ($capabilityFlags & self::CLIENT_SECURE_CONNECTION) {
                    $len = $binary->readByte();
                    $authResponse = Binary::BytesToString($binary->readBytes($len));
                } else {
                    $authResponse = Binary::BytesToString($binary->readNullTerminated());
                }
                $database = null;
                // 7. 如果设置了 CLIENT_CONNECT_WITH_DB，则读取数据库名称（NULL 终止）
                if ($capabilityFlags & self::CLIENT_CONNECT_WITH_DB) {
                    $database = Binary::BytesToString($binary->readNullTerminated());
                }
                $authPlugin = null;
                // 8. 如果设置了 CLIENT_PLUGIN_AUTH，则读取认证插件名称（NULL 终止）
                if ($capabilityFlags & self::CLIENT_PLUGIN_AUTH) {
                    $authPlugin = Binary::BytesToString($binary->readNullTerminated());
                }
                $attributes = [];
                // 9. 如果设置了 CLIENT_CONNECT_ATTRS，则读取连接属性
                if ($capabilityFlags & self::CLIENT_CONNECT_ATTRS) {
                    $attrTotalLength = $binary->readLenEncInt();
                    $read = 0;
                    // 循环读取所有的属性键值对
                    while ($read < $attrTotalLength) {
                        $key = $binary->readLenEncString();
                        $value = $binary->readLenEncString();
                        $attributes[$key] = $value;
                        $read += Binary::lenEncLength(strlen($key)) + strlen($key)
                            + Binary::lenEncLength(strlen($value)) + strlen($value);
                    }
                }

                return [
                    'capability_flags' => $capabilityFlags,
                    'max_packet_size' => $maxPacketSize,
                    'character_set' => $characterSet,
                    'username' => $username,
                    'database' => $database,
                    'auth_plugin' => $authPlugin,
                    'auth_response' => $authResponse,
                    'attributes' => $attributes,
                    'reserved' => $reserved,
                ];
            }, $binary);
        } catch (\InvalidArgumentException $e) {
            throw new PacketException("Error: Failed to unpack handshake response packet [{$e->getMessage()}]", $e->getCode(), $e);
        }
    }

    /**
     * 将传入的数组数据组装为 HandshakeResponse 的 Binary 对象（payload部分）。
     *
     * 要求数组必须至少包含以下键：
     *   - capability_flags (int)
     *   - max_packet_size (int)
     *   - character_set (int)
     *   - username (string)
     *   - auth_response (string)
     *
     * 可选：
     *   - database (string) 如果设置了 CLIENT_CONNECT_WITH_DB
     *   - auth_plugin (string) 如果设置了 CLIENT_PLUGIN_AUTH
     *   - attributes (array) 如果设置了 CLIENT_CONNECT_ATTRS
     *
     * @param array $data
     * @return Binary
     */
    public static function pack(array $data): Binary
    {
        $packetId = $data['packet_id'] ?? 0;
        try {
            return Packet::binary(function (Binary $binary) use ($data) {
                $capabilityFlags       = $data['capability_flags'] ?? 0;
                $maxPacketSize         = $data['max_packet_size'] ?? 0;
                $characterSet          = $data['character_set'] ?? 0;
                $username              = $data['username'] ?? '';
                $database              = $data['database'] ?? '';
                $authPlugin            = $data['auth_plugin'] ?? '';
                $attributes            = $data['attributes'] ?? [];
                $authResponse          = $data['auth_response'] ?? '';
                // 1. 写入能力标志（4 字节）
                $binary->writeUB($capabilityFlags, Binary::UB4);
                // 2. 写入最大数据包大小（4 字节）
                $binary->writeUB($maxPacketSize, Binary::UB4);
                // 3. 写入字符集（1 字节）
                $binary->writeByte($characterSet);
                // 4. 写入 23 字节保留字段（全部 0）
                $binary->writeBytes(array_fill(0, 23, 0));
                // 5. 写入用户名，以 NULL 结尾
                $binary->writeNullTerminated(Binary::StringToBytes($username));
                // 6. 写入认证响应
                if ($capabilityFlags & self::CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA) {
                    $binary->writeLenEncString($username);
                } elseif ($capabilityFlags & self::CLIENT_SECURE_CONNECTION) {
                    $len = strlen($authResponse);
                    $binary->writeByte($len);
                    $binary->writeBytes(Binary::StringToBytes($authResponse));
                } else {
                    $binary->writeNullTerminated(Binary::StringToBytes($authResponse));
                }
                // 7. 如果设置了 CLIENT_CONNECT_WITH_DB，则写入数据库名（NULL 终止）
                if ($capabilityFlags & self::CLIENT_CONNECT_WITH_DB) {
                    $binary->writeNullTerminated(Binary::StringToBytes($database));
                }
                // 8. 如果设置了 CLIENT_PLUGIN_AUTH，则写入认证插件名称（NULL 终止）
                if ($capabilityFlags & self::CLIENT_PLUGIN_AUTH) {
                    $binary->writeNullTerminated(Binary::StringToBytes($authPlugin));
                }
                // 9. 如果设置了 CLIENT_CONNECT_ATTRS，则写入连接属性
                if ($capabilityFlags & self::CLIENT_CONNECT_ATTRS) {
                    $attrBlob = new Binary();
                    foreach ($attributes as $key => $value) {
                        $attrBlob->writeLenEncString($key);
                        $attrBlob->writeLenEncString($value);
                    }
                    $attrStr = $attrBlob->pack();
                    $binary->writeLenEncInt(strlen($attrStr));
                    $binary->writeBytes(Binary::StringToBytes($attrStr));
                }
            }, $packetId);
        } catch (\InvalidArgumentException $e) {
            throw new PacketException("Error: Failed to pack handshake response packet [{$e->getMessage()}]", $e->getCode(), $e);
        }
    }

}
