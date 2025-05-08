<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

class AuthSwitchRequest implements PacketInterface
{
    public const PACKET_FLAG = 0xFE;

    /**
     * 从 Binary 对象中解包 AuthSwitchRequest 数据包。
     * 结构：
     *   [1字节标志=0xFE] + [NULL 终止的认证插件名称] + [剩余的附加认证数据（可选）]
     *
     * @param Binary $binary
     * @return array 解析后的数据，包含:
     *               - plugin_name: string
     *               - auth_plugin_data: array（字节组）
     */
    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            // 读取 1 个字节并验证标志必须为 0xFE
            $flag = $binary->readByte();
            if ($flag !== self::PACKET_FLAG) {
                throw new PacketException("Invalid packet flag '$flag', expected 0xFE", ExceptionCode::ERROR_VALUE);
            }
            // 读取 NULL 终止的认证插件名称
            $pluginName = Binary::BytesToString($binary->readNullTerminated());
            // 剩下的所有数据为附加的认证数据（可以为空）
            $remainingLength = $binary->length() - $binary->getReadCursor();
            $authPluginData = $binary->readBytes($remainingLength);
            return [
                'flag'             => $flag,
                'plugin_name'      => $pluginName,
                'auth_plugin_data' => $authPluginData,
            ];
        }, $binary);
    }

    /**
     * 将 AuthSwitchRequest 数据包内容封装为 Binary 对象。
     *
     * @param array $data 数组包含：
     *                      - plugin_name: 认证插件名称（字符串，必填）
     *                      - auth_plugin_data: 附加认证数据（字节组，可选）
     * @return Binary
     */
    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $pluginName            = $data['plugin_name'] ?? '';
            $authPluginData        = $data['auth_plugin_data'] ?? [];
            // 写入标志字节 0xFE
            $binary->writeByte(self::PACKET_FLAG);
            // 写入认证插件名称（字符串转换成字节数组）及 NULL 终止符
            if (!is_string($pluginName)) {
                throw new PacketException('Invalid plugin_name type, expected string', ExceptionCode::ERROR_TYPE);
            }
            $binary->writeNullTerminated(Binary::StringToBytes($pluginName));
            // 如果附加认证数据存在，则写入
            if ($authPluginData) {
                if (!is_array($authPluginData)) {
                    throw new PacketException('Invalid auth_plugin_data type, expected bytes array', ExceptionCode::ERROR_TYPE);
                }
                $binary->writeBytes($authPluginData);
            }
        }, $data['packet_id'] ?? 0);
    }
}