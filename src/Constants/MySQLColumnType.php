<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Constants;

/**
 * MySQL 列类型常量
 *
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/field__types_8h.html
 */
final class MySQLColumnType
{
    /** Decimal (fixed-point) */
    public const MYSQL_TYPE_DECIMAL = 0x00;
    /** Tiny integer (1 byte) */
    public const MYSQL_TYPE_TINY = 0x01;
    /** Short integer (2 bytes) */
    public const MYSQL_TYPE_SHORT = 0x02;
    /** Long integer (4 bytes) */
    public const MYSQL_TYPE_LONG = 0x03;
    /** Float (4 bytes) */
    public const MYSQL_TYPE_FLOAT = 0x04;
    /** Double (8 bytes) */
    public const MYSQL_TYPE_DOUBLE = 0x05;
    /** NULL value */
    public const MYSQL_TYPE_NULL = 0x06;
    /** Timestamp */
    public const MYSQL_TYPE_TIMESTAMP = 0x07;
    /** Big integer (8 bytes) */
    public const MYSQL_TYPE_LONGLONG = 0x08;
    /** Medium integer (3 bytes) */
    public const MYSQL_TYPE_INT24 = 0x09;
    /** Date */
    public const MYSQL_TYPE_DATE = 0x0A;
    /** Time */
    public const MYSQL_TYPE_TIME = 0x0B;
    /** Datetime */
    public const MYSQL_TYPE_DATETIME = 0x0C;
    /** Year (2 or 4 digit) */
    public const MYSQL_TYPE_YEAR = 0x0D;
    /** New date format (MySQL 5.0+) */
    public const MYSQL_TYPE_NEWDATE = 0x0E;
    /** Varchar */
    public const MYSQL_TYPE_VARCHAR = 0x0F;
    /** Bit field */
    public const MYSQL_TYPE_BIT = 0x10;
    /** Timestamp2 (MySQL 5.6+) */
    public const MYSQL_TYPE_TIMESTAMP2 = 0x11;
    /** Datetime2 (MySQL 5.6+) */
    public const MYSQL_TYPE_DATETIME2 = 0x12;
    /** Time2 (MySQL 5.6+) */
    public const MYSQL_TYPE_TIME2 = 0x13;
    /** Typed array (MySQL 8.0+) */
    public const MYSQL_TYPE_TYPED_ARRAY = 0x14;
    /** JSON (MySQL 5.7+) */
    public const MYSQL_TYPE_JSON = 0xF5;
    /** New decimal (MySQL 5.0+) */
    public const MYSQL_TYPE_NEWDECIMAL = 0xF6;
    /** Enum */
    public const MYSQL_TYPE_ENUM = 0xF7;
    /** Set */
    public const MYSQL_TYPE_SET = 0xF8;
    /** Tiny blob */
    public const MYSQL_TYPE_TINY_BLOB = 0xF9;
    /** Medium blob */
    public const MYSQL_TYPE_MEDIUM_BLOB = 0xFA;
    /** Long blob */
    public const MYSQL_TYPE_LONG_BLOB = 0xFB;
    /** Blob */
    public const MYSQL_TYPE_BLOB = 0xFC;
    /** Var string */
    public const MYSQL_TYPE_VAR_STRING = 0xFD;
    /** String */
    public const MYSQL_TYPE_STRING = 0xFE;
    /** Geometry */
    public const MYSQL_TYPE_GEOMETRY = 0xFF;

    /**
     * 获取类型名称
     *
     * @param int $type
     * @return string
     */
    public static function getName(int $type): string
    {
        return match ($type) {
            self::MYSQL_TYPE_DECIMAL     => 'DECIMAL',
            self::MYSQL_TYPE_TINY        => 'TINY',
            self::MYSQL_TYPE_SHORT       => 'SHORT',
            self::MYSQL_TYPE_LONG        => 'LONG',
            self::MYSQL_TYPE_FLOAT       => 'FLOAT',
            self::MYSQL_TYPE_DOUBLE      => 'DOUBLE',
            self::MYSQL_TYPE_NULL        => 'NULL',
            self::MYSQL_TYPE_TIMESTAMP   => 'TIMESTAMP',
            self::MYSQL_TYPE_LONGLONG    => 'LONGLONG',
            self::MYSQL_TYPE_INT24       => 'INT24',
            self::MYSQL_TYPE_DATE        => 'DATE',
            self::MYSQL_TYPE_TIME        => 'TIME',
            self::MYSQL_TYPE_DATETIME    => 'DATETIME',
            self::MYSQL_TYPE_YEAR        => 'YEAR',
            self::MYSQL_TYPE_NEWDATE     => 'NEWDATE',
            self::MYSQL_TYPE_VARCHAR     => 'VARCHAR',
            self::MYSQL_TYPE_BIT         => 'BIT',
            self::MYSQL_TYPE_TIMESTAMP2  => 'TIMESTAMP2',
            self::MYSQL_TYPE_DATETIME2   => 'DATETIME2',
            self::MYSQL_TYPE_TIME2       => 'TIME2',
            self::MYSQL_TYPE_TYPED_ARRAY => 'TYPED_ARRAY',
            self::MYSQL_TYPE_JSON        => 'JSON',
            self::MYSQL_TYPE_NEWDECIMAL  => 'NEWDECIMAL',
            self::MYSQL_TYPE_ENUM        => 'ENUM',
            self::MYSQL_TYPE_SET         => 'SET',
            self::MYSQL_TYPE_TINY_BLOB   => 'TINY_BLOB',
            self::MYSQL_TYPE_MEDIUM_BLOB => 'MEDIUM_BLOB',
            self::MYSQL_TYPE_LONG_BLOB   => 'LONG_BLOB',
            self::MYSQL_TYPE_BLOB        => 'BLOB',
            self::MYSQL_TYPE_VAR_STRING  => 'VAR_STRING',
            self::MYSQL_TYPE_STRING      => 'STRING',
            self::MYSQL_TYPE_GEOMETRY    => 'GEOMETRY',
            default                      => 'UNKNOWN',
        };
    }
}
