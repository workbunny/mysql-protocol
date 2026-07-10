<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

/**
 * COM_STMT_PREPARE_OK 响应包
 *
 * 服务器对 COM_STMT_PREPARE 的响应。
 *
 * 结构：
 *   [1字节 0x00 标志]
 *   [4字节 stmt_id]
 *   [2字节 num_columns]
 *   [2字节 num_params]
 *   [1字节 reserved = 0x00]
 *   [2字节 warning_count]
 *
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_stmt_prepare.html#sect_protocol_com_stmt_prepare_response_packet
 */
class StmtPrepareOk implements PacketInterface
{
    public const PACKET_FLAG = 0x00;

    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            $flag = $binary->readByte();
            if ($flag !== self::PACKET_FLAG) {
                throw new PacketException("Invalid packet flag '$flag', expected 0x00", ExceptionCode::ERROR_VALUE);
            }
            $stmtId       = $binary->readUB(Binary::UB4);
            $numColumns   = $binary->readUB(Binary::UB2);
            $numParams    = $binary->readUB(Binary::UB2);
            $reserved     = $binary->readByte();
            $warningCount = $binary->readUB(Binary::UB2);
            return [
                'flag'          => $flag,
                'stmt_id'       => $stmtId,
                'num_columns'   => $numColumns,
                'num_params'    => $numParams,
                'reserved'      => $reserved,
                'warning_count' => $warningCount,
            ];
        }, $binary);
    }

    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $binary->writeByte(self::PACKET_FLAG);
            $binary->writeUB((int)($data['stmt_id'] ?? 0), Binary::UB4);
            $binary->writeUB((int)($data['num_columns'] ?? 0), Binary::UB2);
            $binary->writeUB((int)($data['num_params'] ?? 0), Binary::UB2);
            $binary->writeByte(0x00); // reserved
            $binary->writeUB((int)($data['warning_count'] ?? 0), Binary::UB2);
        }, (int)($data['packet_id'] ?? 0));
    }
}
