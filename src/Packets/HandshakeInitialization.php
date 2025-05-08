<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

class HandshakeInitialization implements PacketInterface
{
    public const AUTH_PLUGIN_mysql_NATIVE_PLUGIN = 'mysql_native_password';
    public const AUTH_PLUGIN_CACHING_SHA2_PASSWORD = 'caching_sha2_password';

    /**
     * 从 Binary 对象中解析 MySQL 握手包
     *
     * @param Binary $binary 包含原始握手二进制数据的 Binary 对象
     * @return array 解析后得到的关联数组，包含以下字段：
     *   - packet_length (int)
     *   - packet_id (int)
     *   - protocol_version (int)
     *   - server_version (string)
     *   - connection_id (int)
     *   - capability_flags (int)
     *   - character_set (int)
     *   - status_flags (int)
     *   - auth_plugin_data_length (int)
     *   - auth_plugin_data_part1 (int[])
     *   - auth_plugin_data_part2 (int[])
     *   - auth_plugin_name (string)
     *
     * @throws PacketException 如果读取数据时发生错误
     */
    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            // 1. 协议版本：1 字节
            $protocolVersion = $binary->readByte();
            // 2. 服务器版本：NULL 终止字符串
            $serverVersion = Binary::BytesToString($binary->readNullTerminated());
            // 3. 连接 ID：4 字节（小端序）
            $connectionId = $binary->readUB(Binary::UB4);
            // 4. Auth-plugin-data-part1：8 字节
            $authPluginDataPart1 = $binary->readBytes(8);
            // 5. Filler：1 字节（应为 0）
            $filler = $binary->readByte();
            if ($filler !== 0) {
                throw new PacketException("Filler byte must be 0, found '$filler'", ExceptionCode::ERROR_VALUE);
            }
            // 6. 能力标识低 2 字节;
            $capLow = $binary->readUB(Binary::UB2);
            // 7. 字符集：1 字节
            $characterSetIndex = $binary->readByte();
            // 8. 状态标识：2 字节
            $statusFlags = $binary->readUB(Binary::UB2);
            // 9. 能力标识高 2 字节
            $capHigh = $binary->readUB(Binary::UB2);
            $capabilityFlags = $capLow | ($capHigh << 16);
            // 10. Auth-plugin-data 长度：1 字节
            $authPluginDataLength = $binary->readByte();
            // 11. 保留字段：10 字节
            $reserved = $binary->readBytes(10);
            // 12. Auth-plugin-data-part2：若长度 > 8，则读取多余字节，否则为空
            $part2Len = ($authPluginDataLength > 8) ? ($authPluginDataLength - 8) : 0;
            $authPluginDataPart2 = $part2Len > 0 ? $binary->readBytes($part2Len) : [];
            // 13. Auth-plugin 名称：NULL 终止字符串
            $authPluginName = Binary::BytesToString($binary->readNullTerminated());

            return [
                'protocol_version'         => $protocolVersion,
                'server_version'           => $serverVersion,
                'connection_id'            => $connectionId,
                'capability_flags'         => $capabilityFlags,
                'character_set_index'      => $characterSetIndex,
                'status_flags'             => $statusFlags,
                'auth_plugin_data_length'  => $authPluginDataLength,
                'auth_plugin_data_part1'   => $authPluginDataPart1,
                'auth_plugin_data_part2'   => $authPluginDataPart2,
                'auth_plugin_name'         => $authPluginName,
                'reserved'                 => $reserved,
            ];
        }, $binary);
    }

    /**
     * 将握手包数据封装为 Binary 对象
     *
     * 要求 $data 数组中必须包含以下字段：
     *   - server_version (string)
     *   - connection_id (int)
     *   - capability_flags (int)
     *   - character_set (int)
     *   - status_flags (int)
     *   - auth_plugin_data (string) (长度至少 8 字节)
     *   - auth_plugin_name (string)
     *
     * 可选字段：
     *   - protocol_version (int)，默认为 10
     *
     * @param array $data
     * @return Binary
     * @throws PacketException 如果必填字段缺失或不合法
     */
    public static function pack(array $data): Binary
    {
        foreach (
            [
                'server_version', 'connection_id', 'capability_flags', 'character_set_index', 'status_flags',
                'auth_plugin_data', 'auth_plugin_name'
            ] as $field
        ) {
            if (!isset($data[$field])) {
                throw new PacketException("Missing required field '$field' for handshake packet.");
            }
        }
        return Packet::binary(function (Binary $binary) use ($data) {
            $protocolVersion        = $data['protocol_version'] ?? 10;
            $serverVersion          = (string)$data['server_version'];
            $connectionId           = (int)$data['connection_id'];
            $capabilityFlags        = (int)$data['capability_flags'];
            $characterSetIndex      = (int)$data['character_set_index'];
            $statusFlags            = (int)$data['status_flags'];
            $authPluginData         = (array)$data['auth_plugin_data'];
            $authPluginName         = (string)$data['auth_plugin_name'];

            // 认证数据长度
            if (($authPluginDataLength = count($authPluginData)) < 8) {
                throw new PacketException("auth_plugin_data must be at least 8 bytes.", ExceptionCode::ERROR_VALUE);
            }
            foreach ($authPluginData as $byte) {
                if (!is_int($byte) || $byte < 0 || $byte > 255) {
                    throw new PacketException("auth_plugin_data must be an array of bytes.", ExceptionCode::ERROR_VALUE);
                }
            }
            // 分割认证数据：前 8 字节为 part1，其余为 part2
            $authPluginPart1 = array_slice($authPluginData, 0, 8);
            $authPluginPart2 = $authPluginDataLength > 8 ? array_slice($authPluginData, 8) : [0];

            $capLow  = $capabilityFlags & 0xFFFF;
            $capHigh = ($capabilityFlags >> 16) & 0xFFFF;

            // 1. 写入协议版本（1 字节）
            $binary->writeByte($protocolVersion);
            // 2. 写入服务器版本（NULL 终止字符串）
            $binary->writeNullTerminated(Binary::StringToBytes($serverVersion));
            // 3. 写入连接 ID（4 字节，小端序）
            $binary->writeUB($connectionId, Binary::UB4);
            // 4. 写入 Auth-plugin-data-part-1（8 字节）
            $binary->writeBytes($authPluginPart1);
            // 5. 写入 Filler（1 字节，0）
            $binary->writeByte(0);
            // 6. 写入能力标识低 2 字节（小端序）
            $binary->writeUB($capLow, Binary::UB2);
            // 7. 写入字符集（1 字节）
            $binary->writeByte($characterSetIndex);
            // 8. 写入状态标识（2 字节，小端序）
            $binary->writeUB($statusFlags, Binary::UB2);
            // 9. 写入能力标识高 2 字节（小端序）
            $binary->writeUB($capHigh, Binary::UB2);
            // 10. 写入 Auth-plugin-data 长度（1 字节）
            $binary->writeByte($authPluginDataLength);
            // 11. 写入保留字段（10 字节，全部为 0）
            $binary->writeBytes(array_fill(0, 10, 0));
            // 12. 写入 Auth-plugin-data-part-2
            $binary->writeBytes($authPluginPart2);
            // 13. 写入 Auth-plugin 名称（NULL 终止字符串）
            $binary->writeNullTerminated(Binary::StringToBytes($authPluginName));
        }, (int)$data['packet_id'] ?? 0);
    }
}
