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
        1   => 'big5',
        2   => 'latin2',
        3   => 'dec8',
        4   => 'cp850',
        5   => 'latin1',
        6   => 'hp8',
        7   => 'koi8r',
        8   => 'latin1',
        9   => 'latin2',
        10  => 'swe7',
        11  => 'ascii',
        12  => 'ujis',
        13  => 'sjis',
        14  => 'cp1251',
        15  => 'macroman',
        16  => 'cp1250',
        17  => 'cp852',
        18  => 'latin2',
        19  => 'swe7',
        20  => 'cp1250',
        21  => 'cp1257',
        22  => 'cp1251',
        23  => 'cp1251',
        24  => 'cp1251',
        25  => 'cp1256',
        26  => 'cp1257',
        27  => 'latin5',
        28  => 'cp1250',
        29  => 'gbk',
        30  => 'cp1250',
        31  => 'cp1251',
        32  => 'cp1251',
        33  => 'utf8mb3',
        34  => 'cp1250',
        35  => 'utf8mb3',
        36  => 'cp1250',
        37  => 'utf8mb3',
        38  => 'cp1250',
        39  => 'utf8mb3',
        40  => 'cp1250',
        41  => 'utf8mb3',
        42  => 'cp1250',
        43  => 'cp1250',
        44  => 'cp1257',
        45  => 'utf8mb4',
        46  => 'utf8mb3',
        47  => 'latin1',
        48  => 'cp1251',
        49  => 'cp1251',
        50  => 'cp1251',
        51  => 'cp1251',
        52  => 'cp1251',
        53  => 'cp1251',
        54  => 'utf8mb3',
        55  => 'utf8mb3',
        56  => 'utf8mb3',
        57  => 'cp1252',
        58  => 'latin7',
        59  => 'utf8mb3',
        60  => 'utf8mb3',
        61  => 'utf8mb3',
        62  => 'ascii',
        63  => 'binary',
        64  => 'cp1250',
        65  => 'cp1251',
        66  => 'utf8mb3',
        67  => 'ucs2',
        68  => 'cp866',
        69  => 'cp1251',
        70  => 'cp1251',
        71  => 'cp1251',
        72  => 'macce',
        73  => 'cp1250',
        74  => 'utf8mb3',
        75  => 'utf8mb3',
        76  => 'utf8mb3',
        77  => 'utf8mb3',
        78  => 'utf8mb3',
        79  => 'utf8mb3',
        80  => 'cp1250',
        81  => 'cp1251',
        82  => 'cp1251',
        83  => 'cp1251',
        84  => 'cp1256',
        85  => 'cp1257',
        86  => 'cp1257',
        87  => 'cp1257',
        88  => 'cp1257',
        89  => 'cp1257',
        90  => 'cp1257',
        91  => 'cp1257',
        92  => 'cp1257',
        93  => 'cp1257',
        94  => 'utf8mb3',
        95  => 'utf8mb3',
        96  => 'utf8mb3',
        97  => 'eucjpms',
        98  => 'eucjpms',
        99  => 'cp1250',
        100 => 'utf8mb3',
        101 => 'utf8mb3',
        102 => 'euckr',
        103 => 'cp1250',
        104 => 'cp1250',
        105 => 'utf8mb3',
        106 => 'utf8mb3',
        107 => 'cp1250',
        108 => 'cp1251',
        109 => 'cp1251',
        110 => 'cp1251',
        111 => 'cp1256',
        112 => 'cp1257',
        113 => 'cp1257',
        114 => 'cp1257',
        115 => 'cp1257',
        116 => 'cp1257',
        117 => 'cp1257',
        118 => 'cp1257',
        119 => 'cp1257',
        120 => 'utf8mb3',
        121 => 'utf8mb3',
        122 => 'utf8mb3',
        123 => 'utf8mb3',
        124 => 'utf8mb3',
        125 => 'utf8mb3',
        126 => 'utf8mb3',
        127 => 'utf8mb3',
        128 => 'ucs2',
        129 => 'utf8mb3',
        130 => 'utf8mb3',
        131 => 'utf8mb3',
        132 => 'utf8mb3',
        133 => 'cp1250',
        134 => 'utf8mb3',
        135 => 'utf8mb3',
        136 => 'utf8mb3',
        137 => 'utf8mb3',
        138 => 'utf8mb3',
        139 => 'cp1250',
        140 => 'utf8mb3',
        141 => 'utf8mb3',
        142 => 'utf8mb3',
        143 => 'utf8mb3',
        144 => 'utf8mb3',
        145 => 'utf8mb3',
        146 => 'utf8mb3',
        147 => 'utf8mb3',
        148 => 'utf8mb3',
        149 => 'utf8mb3',
        150 => 'utf8mb3',
        151 => 'utf8mb3',
        152 => 'utf8mb3',
        153 => 'utf8mb3',
        154 => 'utf8mb3',
        155 => 'utf8mb3',
        156 => 'utf8mb3',
        157 => 'utf8mb3',
        158 => 'utf8mb3',
        159 => 'utf8mb3',
        160 => 'utf8mb3',
        161 => 'utf8mb3',
        162 => 'utf8mb3',
        163 => 'utf8mb3',
        164 => 'utf8mb3',
        165 => 'utf8mb3',
        166 => 'utf8mb3',
        167 => 'utf8mb3',
        168 => 'utf8mb3',
        169 => 'utf8mb3',
        170 => 'utf8mb3',
        171 => 'utf8mb3',
        172 => 'utf8mb3',
        173 => 'utf8mb3',
        174 => 'utf8mb3',
        175 => 'utf8mb3',
        176 => 'utf8mb3',
        177 => 'utf8mb3',
        178 => 'utf8mb3',
        179 => 'utf8mb3',
        180 => 'utf8mb3',
        181 => 'utf8mb3',
        182 => 'utf8mb3',
        183 => 'utf8mb3',
        184 => 'utf8mb3',
        185 => 'utf8mb3',
        186 => 'utf8mb3',
        187 => 'utf8mb3',
        188 => 'utf8mb3',
        189 => 'utf8mb3',
        190 => 'utf8mb3',
        191 => 'utf8mb3',
        192 => 'utf8mb3',
        193 => 'utf8mb3',
        194 => 'utf8mb3',
        195 => 'utf8mb3',
        196 => 'utf8mb3',
        197 => 'utf8mb3',
        198 => 'utf8mb3',
        199 => 'utf8mb3',
        200 => 'utf8mb3',
        201 => 'utf8mb3',
        202 => 'utf8mb3',
        203 => 'utf8mb3',
        204 => 'utf8mb3',
        205 => 'utf8mb3',
        206 => 'utf8mb3',
        207 => 'utf8mb3',
        208 => 'utf8mb3',
        209 => 'utf8mb3',
        210 => 'utf8mb3',
        211 => 'utf8mb3',
        212 => 'utf8mb3',
        213 => 'utf8mb3',
        214 => 'utf8mb3',
        215 => 'utf8mb3',
        216 => 'utf8mb3',
        217 => 'utf8mb3',
        218 => 'utf8mb3',
        219 => 'utf8mb3',
        220 => 'utf8mb3',
        221 => 'utf8mb3',
        222 => 'utf8mb3',
        223 => 'utf8mb4',
        224 => 'utf8mb4',
        225 => 'utf8mb4',
        226 => 'utf8mb4',
        227 => 'utf8mb4',
        228 => 'utf8mb4',
        229 => 'utf8mb4',
        230 => 'utf8mb4',
        231 => 'utf8mb4',
        232 => 'utf8mb4',
        233 => 'utf8mb4',
        234 => 'utf8mb4',
        235 => 'utf8mb4',
        236 => 'utf8mb4',
        237 => 'utf8mb4',
        238 => 'utf8mb4',
        239 => 'utf8mb4',
        240 => 'utf8mb4',
        241 => 'utf8mb4',
        242 => 'utf8mb4',
        243 => 'utf8mb4',
        244 => 'utf16',
        245 => 'utf16le',
        246 => 'utf16le',
        247 => 'utf8mb4',
        248 => 'utf8mb4',
        249 => 'utf8mb4',
        250 => 'utf8mb4',
        251 => 'utf8mb4',
        252 => 'utf8mb4',
        253 => 'utf8mb4',
        254 => 'utf8mb4',
        255 => 'utf8mb4',
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
