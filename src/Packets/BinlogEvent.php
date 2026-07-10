<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;

/**
 * Binlog 事件包
 *
 * 解析主库发送的 binlog 事件流。每个事件由通用事件头 + 事件体组成。
 *
 * 通用事件头 (19 字节, v4):
 *   [4字节 timestamp]
 *   [1字节 event type]
 *   [4字节 server_id]
 *   [4字节 event size]
 *   [4字节 log pos]
 *   [2字节 flags]
 *
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/binary_log.html
 */
class BinlogEvent implements PacketInterface
{
    public const UNKNOWN_EVENT            = 0x00;
    public const START_EVENT_V3           = 0x01;
    public const QUERY_EVENT              = 0x02;
    public const STOP_EVENT               = 0x03;
    public const ROTATE_EVENT             = 0x04;
    public const INTVAR_EVENT             = 0x05;
    public const LOAD_EVENT               = 0x06;
    public const SLAVE_EVENT              = 0x07;
    public const CREATE_FILE_EVENT        = 0x08;
    public const APPEND_BLOCK_EVENT       = 0x09;
    public const EXEC_LOAD_EVENT          = 0x0A;
    public const DELETE_FILE_EVENT        = 0x0B;
    public const NEW_LOAD_EVENT           = 0x0C;
    public const RAND_EVENT               = 0x0D;
    public const USER_VAR_EVENT           = 0x0E;
    public const FORMAT_DESCRIPTION_EVENT = 0x0F;
    public const XID_EVENT                = 0x10;
    public const BEGIN_LOAD_QUERY_EVENT   = 0x11;
    public const EXECUTE_LOAD_QUERY_EVENT = 0x12;
    public const TABLE_MAP_EVENT          = 0x13;
    public const PRE_GA_WRITE_ROWS_EVENT  = 0x14;
    public const PRE_GA_UPDATE_ROWS_EVENT = 0x15;
    public const PRE_GA_DELETE_ROWS_EVENT = 0x16;
    public const WRITE_ROWS_EVENT_V1      = 0x17;
    public const UPDATE_ROWS_EVENT_V1     = 0x18;
    public const DELETE_ROWS_EVENT_V1     = 0x19;
    public const INCIDENT_EVENT           = 0x1A;
    public const HEARTBEAT_EVENT          = 0x1B;
    public const IGNORABLE_EVENT          = 0x1C;
    public const ROWS_QUERY_EVENT         = 0x1D;
    public const WRITE_ROWS_EVENT_V2      = 0x1E;
    public const UPDATE_ROWS_EVENT_V2     = 0x1F;
    public const DELETE_ROWS_EVENT_V2     = 0x20;
    public const GTID_EVENT               = 0x21;
    public const ANONYMOUS_GTID_EVENT     = 0x22;
    public const PREVIOUS_GTIDS_EVENT     = 0x23;
    public const TRANSACTION_CONTEXT_EVENT = 0x24;
    public const VIEW_CHANGE_EVENT        = 0x25;
    public const XA_PREPARE_EVENT         = 0x26;
    public const PARTIAL_UPDATE_ROWS_EVENT = 0x27;
    public const TRANSACTION_PAYLOAD_EVENT = 0x28;
    public const HEARTBEAT_LOG_EVENT_V2   = 0x29;

    public const EVENT_HEADER_SIZE = 19;

    public static function unpack(Binary $binary): array
    {
        if ($binary->length() < self::EVENT_HEADER_SIZE) {
            throw new PacketException(
                "Binlog event too short, need at least " . self::EVENT_HEADER_SIZE . " bytes",
                ExceptionCode::ERROR_VALUE
            );
        }
        $timestamp  = $binary->readUB(Binary::UB4);
        $eventType  = $binary->readByte();
        $serverId   = $binary->readUB(Binary::UB4);
        $eventSize  = $binary->readUB(Binary::UB4);
        $logPos     = $binary->readUB(Binary::UB4);
        $flags      = $binary->readUB(Binary::UB2);

        $result = [
            'timestamp'       => $timestamp,
            'event_type'      => $eventType,
            'event_type_name' => self::getEventTypeName($eventType),
            'server_id'       => $serverId,
            'event_size'      => $eventSize,
            'log_pos'         => $logPos,
            'flags'           => $flags,
        ];

        $bodySize = $eventSize - self::EVENT_HEADER_SIZE;
        if ($bodySize > 0) {
            $bodyBytes = $binary->readBytes($bodySize);
            $result['body'] = $bodyBytes;
            $result['event_data'] = self::parseEventBody($eventType, $bodyBytes);
        } else {
            $result['body'] = [];
            $result['event_data'] = self::parseEventBody($eventType, []);
        }

        return $result;
    }

    public static function pack(array $data): Binary
    {
        $binary = new Binary();
        $binary->writeUB((int)($data['timestamp'] ?? time()), Binary::UB4);
        $binary->writeByte((int)($data['event_type'] ?? self::UNKNOWN_EVENT));
        $binary->writeUB((int)($data['server_id'] ?? 0), Binary::UB4);
        $eventSizePos = $binary->getWriteCursor();
        $binary->writeUB(0, Binary::UB4);
        $binary->writeUB((int)($data['log_pos'] ?? 0), Binary::UB4);
        $binary->writeUB((int)($data['flags'] ?? 0), Binary::UB2);
        if (isset($data['body']) && is_array($data['body'])) {
            $binary->writeBytes($data['body']);
        }
        $eventSize = $binary->getWriteCursor();
        $binary->setWriteCursor($eventSizePos);
        $binary->writeUB($eventSize, Binary::UB4);
        $binary->setWriteCursor($eventSize);
        return $binary;
    }

    public static function getEventTypeName(int $type): string
    {
        return match ($type) {
            self::UNKNOWN_EVENT             => 'UNKNOWN_EVENT',
            self::START_EVENT_V3            => 'START_EVENT_V3',
            self::QUERY_EVENT               => 'QUERY_EVENT',
            self::STOP_EVENT                => 'STOP_EVENT',
            self::ROTATE_EVENT              => 'ROTATE_EVENT',
            self::INTVAR_EVENT              => 'INTVAR_EVENT',
            self::LOAD_EVENT                => 'LOAD_EVENT',
            self::SLAVE_EVENT               => 'SLAVE_EVENT',
            self::CREATE_FILE_EVENT         => 'CREATE_FILE_EVENT',
            self::APPEND_BLOCK_EVENT        => 'APPEND_BLOCK_EVENT',
            self::EXEC_LOAD_EVENT           => 'EXEC_LOAD_EVENT',
            self::DELETE_FILE_EVENT         => 'DELETE_FILE_EVENT',
            self::NEW_LOAD_EVENT            => 'NEW_LOAD_EVENT',
            self::RAND_EVENT                => 'RAND_EVENT',
            self::USER_VAR_EVENT            => 'USER_VAR_EVENT',
            self::FORMAT_DESCRIPTION_EVENT  => 'FORMAT_DESCRIPTION_EVENT',
            self::XID_EVENT                 => 'XID_EVENT',
            self::BEGIN_LOAD_QUERY_EVENT    => 'BEGIN_LOAD_QUERY_EVENT',
            self::EXECUTE_LOAD_QUERY_EVENT  => 'EXECUTE_LOAD_QUERY_EVENT',
            self::TABLE_MAP_EVENT           => 'TABLE_MAP_EVENT',
            self::WRITE_ROWS_EVENT_V1       => 'WRITE_ROWS_EVENT_V1',
            self::UPDATE_ROWS_EVENT_V1      => 'UPDATE_ROWS_EVENT_V1',
            self::DELETE_ROWS_EVENT_V1      => 'DELETE_ROWS_EVENT_V1',
            self::INCIDENT_EVENT            => 'INCIDENT_EVENT',
            self::HEARTBEAT_EVENT           => 'HEARTBEAT_EVENT',
            self::IGNORABLE_EVENT           => 'IGNORABLE_EVENT',
            self::ROWS_QUERY_EVENT          => 'ROWS_QUERY_EVENT',
            self::WRITE_ROWS_EVENT_V2       => 'WRITE_ROWS_EVENT_V2',
            self::UPDATE_ROWS_EVENT_V2      => 'UPDATE_ROWS_EVENT_V2',
            self::DELETE_ROWS_EVENT_V2      => 'DELETE_ROWS_EVENT_V2',
            self::GTID_EVENT                => 'GTID_EVENT',
            self::ANONYMOUS_GTID_EVENT      => 'ANONYMOUS_GTID_EVENT',
            self::PREVIOUS_GTIDS_EVENT      => 'PREVIOUS_GTIDS_EVENT',
            self::TRANSACTION_CONTEXT_EVENT => 'TRANSACTION_CONTEXT_EVENT',
            self::VIEW_CHANGE_EVENT         => 'VIEW_CHANGE_EVENT',
            self::XA_PREPARE_EVENT          => 'XA_PREPARE_EVENT',
            self::PARTIAL_UPDATE_ROWS_EVENT => 'PARTIAL_UPDATE_ROWS_EVENT',
            self::TRANSACTION_PAYLOAD_EVENT => 'TRANSACTION_PAYLOAD_EVENT',
            self::HEARTBEAT_LOG_EVENT_V2    => 'HEARTBEAT_LOG_EVENT_V2',
            default                         => 'UNKNOWN',
        };
    }

    private static function parseEventBody(int $eventType, array $bodyBytes): ?array
    {
        // 对于无事件体的事件类型，直接返回空数组
        if (empty($bodyBytes)) {
            return match ($eventType) {
                self::HEARTBEAT_EVENT => [],
                self::STOP_EVENT      => [],
                default               => null,
            };
        }
        try {
            $body = new Binary($bodyBytes);
            return match ($eventType) {
                self::FORMAT_DESCRIPTION_EVENT => self::parseFormatDescription($body),
                self::QUERY_EVENT              => self::parseQueryEvent($body),
                self::ROTATE_EVENT             => self::parseRotateEvent($body),
                self::XID_EVENT                => self::parseXidEvent($body),
                self::TABLE_MAP_EVENT          => self::parseTableMapEvent($body),
                self::GTID_EVENT               => self::parseGtidEvent($body),
                self::HEARTBEAT_EVENT          => [],
                self::STOP_EVENT               => [],
                default                        => null,
            };
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private static function parseFormatDescription(Binary $body): array
    {
        $binlogVersion   = $body->readUB(Binary::UB2);
        $serverVersion   = Binary::BytesToString($body->readBytes(50));
        $serverVersion   = rtrim($serverVersion, "\x00");
        $createTimestamp = $body->readUB(Binary::UB4);
        $commonHeaderLen = $body->readByte();
        $remaining       = $body->length() - $body->getReadCursor();
        $postHeaderLengths = $remaining > 0 ? $body->readBytes($remaining) : [];
        return [
            'binlog_version'       => $binlogVersion,
            'server_version'       => $serverVersion,
            'create_timestamp'     => $createTimestamp,
            'common_header_length' => $commonHeaderLen,
            'post_header_lengths'  => $postHeaderLengths,
        ];
    }

    private static function parseQueryEvent(Binary $body): array
    {
        $slaveProxyId     = $body->readUB(Binary::UB4);
        $executionTime    = $body->readUB(Binary::UB4);
        $schemaLength     = $body->readByte();
        $errorCode        = $body->readUB(Binary::UB2);
        $statusVarsLength = $body->readUB(Binary::UB2);
        $statusVars       = $body->readBytes($statusVarsLength);
        $schema           = Binary::BytesToString($body->readBytes($schemaLength));
        $body->readByte(); // 1-byte filler
        $remaining = $body->length() - $body->getReadCursor();
        $sql = $remaining > 0 ? Binary::BytesToString($body->readBytes($remaining)) : '';
        return [
            'slave_proxy_id'  => $slaveProxyId,
            'execution_time'  => $executionTime,
            'schema_length'   => $schemaLength,
            'error_code'      => $errorCode,
            'status_vars'     => $statusVars,
            'schema'          => $schema,
            'sql'             => $sql,
        ];
    }

    private static function parseRotateEvent(Binary $body): array
    {
        $position  = $body->readUB(Binary::UB8);
        $remaining = $body->length() - $body->getReadCursor();
        $filename  = $remaining > 0 ? Binary::BytesToString($body->readBytes($remaining)) : '';
        return ['position' => $position, 'filename' => $filename];
    }

    private static function parseXidEvent(Binary $body): array
    {
        return ['xid' => $body->readUB(Binary::UB8)];
    }

    private static function parseGtidEvent(Binary $body): array
    {
        $gtidFlags = $body->readByte();
        $sidBytes  = $body->readBytes(16);
        $sidHex    = '';
        foreach ($sidBytes as $b) {
            $sidHex .= sprintf('%02x', $b);
        }
        $sid = substr($sidHex, 0, 8) . '-' . substr($sidHex, 8, 4) . '-'
             . substr($sidHex, 12, 4) . '-' . substr($sidHex, 16, 4) . '-'
             . substr($sidHex, 20, 12);
        $gno = $body->readUB(Binary::UB8);
        $lcType = 0;
        $remaining = $body->length() - $body->getReadCursor();
        if ($remaining >= 1) {
            $lcType = $body->readByte();
        }
        return ['flags' => $gtidFlags, 'sid' => $sid, 'gno' => $gno, 'lc_type' => $lcType];
    }

    private static function parseTableMapEvent(Binary $body): array
    {
        $tableId  = $body->readUB(Binary::UB8);
        $flags    = $body->readUB(Binary::UB2);
        $schemaLen = $body->readLenEncInt();
        $schema   = Binary::BytesToString($body->readBytes($schemaLen));
        $body->readByte(); // filler [00]
        $tableLen = $body->readLenEncInt();
        $table    = Binary::BytesToString($body->readBytes($tableLen));
        $body->readByte(); // filler [00]
        $columnCount = $body->readLenEncInt();
        $columnTypes = $body->readBytes($columnCount);
        // column metadata
        $metadata = self::readColumnMetadata($body, $columnTypes);
        // null bitmap
        $nullBitmapLen = ($columnCount + 7) >> 3;
        $nullBitmap = $body->readBytes($nullBitmapLen);
        // optional field metadata (剩余部分)
        $remaining = $body->length() - $body->getReadCursor();
        $optionalMetadata = $remaining > 0 ? $body->readBytes($remaining) : [];
        return [
            'table_id'           => $tableId,
            'flags'              => $flags,
            'schema'             => $schema,
            'table'              => $table,
            'column_count'       => $columnCount,
            'column_types'       => $columnTypes,
            'column_metadata'    => $metadata,
            'null_bitmap'        => $nullBitmap,
            'optional_metadata'  => $optionalMetadata,
        ];
    }

    /**
     * 读取列元数据
     *
     * @param Binary $body
     * @param array $columnTypes
     * @return array
     */
    private static function readColumnMetadata(Binary $body, array $columnTypes): array
    {
        $metadata = [];
        foreach ($columnTypes as $type) {
            switch ($type) {
                case 0x01: // MYSQL_TYPE_TINY
                case 0x02: // MYSQL_TYPE_SHORT
                case 0x03: // MYSQL_TYPE_LONG
                case 0x08: // MYSQL_TYPE_LONGLONG
                case 0x09: // MYSQL_TYPE_INT24
                case 0x0D: // MYSQL_TYPE_YEAR
                    $metadata[] = null; // no metadata
                    break;
                case 0x04: // MYSQL_TYPE_FLOAT
                case 0x05: // MYSQL_TYPE_DOUBLE
                    $metadata[] = $body->readByte();
                    break;
                case 0x07: // MYSQL_TYPE_TIMESTAMP
                case 0x0A: // MYSQL_TYPE_DATE
                case 0x0B: // MYSQL_TYPE_TIME
                case 0x0C: // MYSQL_TYPE_DATETIME
                    $metadata[] = null;
                    break;
                case 0x0F: // MYSQL_TYPE_VARCHAR
                case 0xF5: // MYSQL_TYPE_JSON
                    $metadata[] = $body->readUB(Binary::UB2);
                    break;
                case 0xFC: // MYSQL_TYPE_BLOB
                case 0xFD: // MYSQL_TYPE_VAR_STRING
                case 0xFE: // MYSQL_TYPE_STRING
                    $metadata[] = $body->readByte();
                    break;
                case 0xF6: // MYSQL_TYPE_NEWDECIMAL
                    $metadata[] = $body->readUB(Binary::UB2);
                    break;
                case 0xF7: // MYSQL_TYPE_ENUM
                case 0xF8: // MYSQL_TYPE_SET
                    $metadata[] = $body->readByte();
                    break;
                case 0xF9: // MYSQL_TYPE_TINY_BLOB
                case 0xFA: // MYSQL_TYPE_MEDIUM_BLOB
                case 0xFB: // MYSQL_TYPE_LONG_BLOB
                    $metadata[] = $body->readByte();
                    break;
                case 0x10: // MYSQL_TYPE_BIT
                    $metadata[] = $body->readUB(Binary::UB2);
                    break;
                default:
                    $metadata[] = null;
                    break;
            }
        }
        return $metadata;
    }
}
