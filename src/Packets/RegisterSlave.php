<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

/**
 * COM_REGISTER_SLAVE 数据包
 *
 * 客户端（从库）向服务器（主库）注册自己为从库，用于复制协议。
 *
 * 结构：
 *   [1字节命令码 0x15]
 *   [4字节 server_id]
 *   [1字节 hostname 长度] [hostname 字符串]
 *   [1字节 user 长度] [user 字符串]
 *   [1字节 password 长度] [password 字符串]
 *   [2字节 port]
 *   [4字节 replication rank]
 *   [4字节 master id]
 *
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_replication.html
 */
class RegisterSlave implements PacketInterface
{
    public const COMMAND = 0x15;

    /**
     * 解包 COM_REGISTER_SLAVE 数据包
     *
     * @param Binary $binary
     * @return array
     */
    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            // 1. 命令码
            $command = $binary->readByte();
            if ($command !== self::COMMAND) {
                throw new PacketException(
                    "Invalid command '$command', expected 0x15",
                    ExceptionCode::ERROR_VALUE
                );
            }
            // 2. server_id (4 字节)
            $serverId = $binary->readUB(Binary::UB4);
            // 3. hostname (1 字节长度 + 字符串)
            $hostnameLen = $binary->readByte();
            $hostname = Binary::BytesToString($binary->readBytes($hostnameLen));
            // 4. user (1 字节长度 + 字符串)
            $userLen = $binary->readByte();
            $user = Binary::BytesToString($binary->readBytes($userLen));
            // 5. password (1 字节长度 + 字符串)
            $passwordLen = $binary->readByte();
            $password = Binary::BytesToString($binary->readBytes($passwordLen));
            // 6. port (2 字节)
            $port = $binary->readUB(Binary::UB2);
            // 7. replication rank (4 字节)
            $replicationRank = $binary->readUB(Binary::UB4);
            // 8. master id (4 字节)
            $masterId = $binary->readUB(Binary::UB4);

            return [
                'command'          => $command,
                'server_id'        => $serverId,
                'hostname'         => $hostname,
                'user'             => $user,
                'password'         => $password,
                'port'             => $port,
                'replication_rank' => $replicationRank,
                'master_id'        => $masterId,
            ];
        }, $binary);
    }

    /**
     * 封装 COM_REGISTER_SLAVE 数据包
     *
     * @param array $data
     * @return Binary
     */
    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $serverId        = (int)($data['server_id'] ?? 0);
            $hostname        = (string)($data['hostname'] ?? '');
            $user            = (string)($data['user'] ?? '');
            $password        = (string)($data['password'] ?? '');
            $port            = (int)($data['port'] ?? 3306);
            $replicationRank = (int)($data['replication_rank'] ?? 0);
            $masterId        = (int)($data['master_id'] ?? 0);

            // 1. 命令码
            $binary->writeByte(self::COMMAND);
            // 2. server_id
            $binary->writeUB($serverId, Binary::UB4);
            // 3. hostname
            $hostnameBytes = Binary::StringToBytes($hostname);
            $binary->writeByte(count($hostnameBytes));
            $binary->writeBytes($hostnameBytes);
            // 4. user
            $userBytes = Binary::StringToBytes($user);
            $binary->writeByte(count($userBytes));
            $binary->writeBytes($userBytes);
            // 5. password
            $passwordBytes = Binary::StringToBytes($password);
            $binary->writeByte(count($passwordBytes));
            $binary->writeBytes($passwordBytes);
            // 6. port
            $binary->writeUB($port, Binary::UB2);
            // 7. replication rank
            $binary->writeUB($replicationRank, Binary::UB4);
            // 8. master id
            $binary->writeUB($masterId, Binary::UB4);
        }, (int)($data['packet_id'] ?? 0));
    }
}
