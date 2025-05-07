<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Exceptions\InvalidArgumentException;
use Workbunny\MysqlProtocol\Utils\Packet;

class Command implements PacketInterface
{
    // 常见命令码定义
    public const COM_QUIT = 0x01;
    public const COM_INIT_DB = 0x02;
    public const COM_QUERY = 0x03;
    public const COM_FIELD_LIST = 0x04;
    public const COM_CREATE_DB = 0x05;
    public const COM_DROP_DB = 0x06;
    public const COM_REFRESH = 0x07;
    public const COM_SHUTDOWN = 0x08;
    public const COM_STATISTICS = 0x09;
    public const COM_PROCESS_INFO = 0x0A;
    public const COM_CONNECT = 0x0B;
    public const COM_PROCESS_KILL = 0x0C;
    public const COM_DEBUG = 0x0D;
    public const COM_PING = 0x0E;

    /**
     * 从 Binary 对象中解析 Command Packet 数据包（payload部分，不包括 4 字节包头）。
     *
     * 返回数组格式如下：
     *   - command: int    命令码
     *   - data: string    剩余数据（例如 SQL 语句或其它命令参数）
     *
     * @param Binary $binary
     * @return array
     */
    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {

            $command = $binary->readByte();
            // 如果还包含其它数据，则读取剩余部分，并将其视为字符串
            $remaining = $binary->length() - $binary->getReadCursor();
            $data = null;
            if ($remaining > 0) {
                $data = Binary::BytesToString($binary->readBytes($remaining));
            }
            return [
                'command' => $command,
                'data' => $data,
            ];
        }, $binary);
    }

    /**
     * 将 Command Packet 数据组装为 Binary 对象（payload部分，不包含包头）。
     *
     * 数组中应至少包含以下键：
     *   - command: int    命令码
     *
     * 可选：
     *   - data: string    具体命令数据（如 SQL 查询语句、数据库名称等）
     *
     * @param array $data
     * @return Binary
     * @throws InvalidArgumentException 如果缺少必要的 'command' 字段
     */
    public static function pack(array $data): Binary
    {
        $packetId = $data['packet_id'] ?? 0;
        return Packet::binary(function (Binary $binary) use ($data) {
            $command = $data['command'];
            $data = $data['data'] ?? null;
            // 写入命令码（1 字节）
            $binary->writeByte($command);

            // 如果存在额外数据，则写入（例如对于 COM_QUERY，把 SQL 语句写进来）
            if ($data) {
                $binary->writeBytes(Binary::StringToBytes($data));
            }
        }, $packetId);
    }
}
