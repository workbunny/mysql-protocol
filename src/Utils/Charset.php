<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Utils;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\InvalidArgumentException;

class Charset
{
    /**
     * MySQL 8 中字符集编号与字符集名称的映射表
     *
     * @var array<int, string>
     */
    private const CHARSET_MAP = [
        1  => 'big5',
        3  => 'dec8',
        4  => 'cp850',
        6  => 'hp8',
        8  => 'latin1',
        12 => 'macce',
        15 => 'macroman',
        16 => 'dos',
        17 => 'cp852',
        18 => 'latin2',
        19 => 'swe7',
        20 => 'ibm850',
        21 => 'ibm866',
        22 => 'cp865',
        23 => 'cp1252',
        24 => 'cp1251',
        25 => 'cp1256',
        26 => 'cp1257',
        33 => 'utf8',
        45 => 'utf8mb4',
        63 => 'binary'
    ];

    /**
     * 根据字符集编号获取对应的字符集名称
     *
     * @param int $index
     * @return string
     * @throws InvalidArgumentException
     */
    public static function getCharsetNameByIndex(int $index): string
    {
        if (!isset(self::CHARSET_MAP[$index])) {
            throw new InvalidArgumentException("Charset index '$index' is not supported.", ExceptionCode::ERROR_SUPPORT);
        }
        return self::CHARSET_MAP[$index];
    }

    /**
     * 根据字符集名称查询对应的字符集编号
     *
     * @param string $name 字符集名称（例如 "utf8mb4"）
     * @return int
     * @throws InvalidArgumentException
     */
    public static function getCharsetIndexByName(string $name): int
    {
        $normalized = strtolower($name);
        // 使用 array_map 将所有名称转换为小写，与 normalized 进行比较
        $lookup = array_map('strtolower', self::CHARSET_MAP);
        $index = array_search($normalized, $lookup, true);
        if ($index === false) {
            throw new InvalidArgumentException("Charset '$name' is not supported.", ExceptionCode::ERROR_SUPPORT);
        }
        return (int)$index;
    }

    /**
     * 返回所有支持的字符集映射。
     *
     * @return array<int, string> 数组形式的字符集编号 => 字符集名称映射
     */
    public static function getAllCharsets(): array
    {
        return self::CHARSET_MAP;
    }
}
