<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

/**
 * COM_STMT_EXECUTE 数据包
 *
 * 客户端执行已预处理的语句。
 *
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_stmt_execute.html
 */
class StmtExecute implements PacketInterface
{
    public const COMMAND = 0x17;

    public const CURSOR_TYPE_NO_CURSOR  = 0x00;
    public const CURSOR_TYPE_READ_ONLY  = 0x01;
    public const CURSOR_TYPE_FOR_UPDATE = 0x02;
    public const CURSOR_TYPE_SCROLLABLE = 0x04;

    // 参数类型常量
    public const MYSQL_TYPE_TINY      = 0x01;
    public const MYSQL_TYPE_SHORT     = 0x02;
    public const MYSQL_TYPE_LONG      = 0x03;
    public const MYSQL_TYPE_FLOAT     = 0x04;
    public const MYSQL_TYPE_DOUBLE    = 0x05;
    public const MYSQL_TYPE_NULL      = 0x06;
    public const MYSQL_TYPE_LONGLONG  = 0x08;
    public const MYSQL_TYPE_INT24     = 0x09;
    public const MYSQL_TYPE_VARCHAR   = 0x0F;
    public const MYSQL_TYPE_NEWDECIMAL = 0xF6;
    public const MYSQL_TYPE_BLOB      = 0xFC;
    public const MYSQL_TYPE_VAR_STRING = 0xFD;
    public const MYSQL_TYPE_STRING    = 0xFE;

    public const UNSIGNED_FLAG = 0x80;

    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            $command        = $binary->readByte();
            $stmtId         = $binary->readUB(Binary::UB4);
            $flags          = $binary->readByte();
            $iterationCount = $binary->readUB(Binary::UB4);
            $result = [
                'command'         => $command,
                'stmt_id'         => $stmtId,
                'flags'           => $flags,
                'iteration_count' => $iterationCount,
                'parameters'      => [],
            ];
            if ($flags & self::CURSOR_TYPE_READ_ONLY) {
                $binary->readBytes(4);
            }
            $remaining = $binary->length() - $binary->getReadCursor();
            if ($remaining > 0) {
                $result['raw_parameters'] = $binary->readBytes($remaining);
            }
            return $result;
        }, $binary);
    }

    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $stmtId         = (int)($data['stmt_id'] ?? 0);
            $flags          = (int)($data['flags'] ?? self::CURSOR_TYPE_NO_CURSOR);
            $iterationCount = (int)($data['iteration_count'] ?? 1);
            $parameters     = (array)($data['parameters'] ?? []);

            $binary->writeByte(self::COMMAND);
            $binary->writeUB($stmtId, Binary::UB4);
            $binary->writeByte($flags);
            $binary->writeUB($iterationCount, Binary::UB4);

            if ($flags & self::CURSOR_TYPE_READ_ONLY) {
                $binary->writeUB(0, Binary::UB4);
            }

            if (!empty($parameters)) {
                $numParams = count($parameters);
                $bitmapLen = ($numParams + 7) >> 3;
                $nullBitmap = array_fill(0, $bitmapLen, 0);
                foreach ($parameters as $i => $param) {
                    if (($param['value'] ?? null) === null) {
                        $nullBitmap[$i >> 3] |= (1 << ($i % 8));
                    }
                }
                $binary->writeBytes($nullBitmap);
                $binary->writeByte(1); // new_params_bound_flag
                // 参数类型 (每个参数 2 字节: type + flags)
                foreach ($parameters as $param) {
                    $type = (int)($param['type'] ?? self::MYSQL_TYPE_STRING);
                    $unsigned = !empty($param['unsigned']) ? self::UNSIGNED_FLAG : 0;
                    $binary->writeByte($type);
                    $binary->writeByte($unsigned);
                }
                // 参数值
                foreach ($parameters as $param) {
                    if (($param['value'] ?? null) !== null) {
                        self::writeParamValue($binary, $param);
                    }
                }
            }
        }, (int)($data['packet_id'] ?? 0));
    }

    /**
     * 写入参数值
     */
    private static function writeParamValue(Binary $binary, array $param): void
    {
        $type  = (int)($param['type'] ?? self::MYSQL_TYPE_STRING);
        $value = $param['value'];

        switch ($type) {
            case self::MYSQL_TYPE_TINY:
                $binary->writeByte((int)$value & 0xFF);
                break;
            case self::MYSQL_TYPE_SHORT:
                $binary->writeUB((int)$value, Binary::UB2);
                break;
            case self::MYSQL_TYPE_LONG:
            case self::MYSQL_TYPE_INT24:
                $binary->writeUB((int)$value, Binary::UB4);
                break;
            case self::MYSQL_TYPE_LONGLONG:
                $binary->writeUB((int)$value, Binary::UB8);
                break;
            case self::MYSQL_TYPE_FLOAT:
                $binary->writeBytes(unpack('C*', pack('f', (float)$value)));
                break;
            case self::MYSQL_TYPE_DOUBLE:
                $binary->writeBytes(unpack('C*', pack('d', (float)$value)));
                break;
            case self::MYSQL_TYPE_NULL:
                break;
            case self::MYSQL_TYPE_VARCHAR:
            case self::MYSQL_TYPE_VAR_STRING:
            case self::MYSQL_TYPE_STRING:
            case self::MYSQL_TYPE_BLOB:
            default:
                $binary->writeLenEncString((string)$value);
                break;
        }
    }
}
