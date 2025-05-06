<?php

namespace nWorkbunny\MysqlProtocol\Utils;

use InvalidArgumentException;
use RuntimeException;
use stdClass;

class Binary
{
    public const UB2 = 2;
    public const UB3 = 3;
    public const UB4 = 4;
    public const UB8 = 8;

    /**
     * 输入数据
     *
     * @var mixed
     */
    protected mixed $payload;

    /**
     * 字节数组
     *
     * @var array<int>
     */
    protected array $bytes = [];

    /**
     * 二进制字符串
     *
     * @var string|null
     */
    protected null|string $string = null;

    /**
     * 数据长度
     *
     * @var int|null
     */
    protected ?int $length = null;

    /**
     * bytes数量
     *
     * @var int|null
     */
    protected ?int $count = null;

    /**
     * 读指针
     *
     * @var int
     */
    protected int $readCursor = 0;

    /**
     * 写指针
     *
     * @var int 写指针
     */
    protected int $writeCursor = 0;

    /**
     * 字符串转为字节组
     *
     * @param string $string
     * @return int[]
     */
    public static function StringToBytes(string $string): array
    {
        if (($bytes = unpack('C*', $string)) === false) {
            throw new RuntimeException("[Error Type] String '$string' is invalid");
        }
        return array_values($bytes);
    }

    /**
     * 字节组转为字符串
     *
     * @param array $bytes
     * @return string
     */
    public static function BytesToString(array $bytes): string
    {
        return pack('C*', ...$bytes);
    }

    /**
     * 辅助函数：根据字符串真实长度返回对应“长度编码整数”本身所占字节数。
     *
     * @param int $valueLength
     * @return int
     */
    public static function LenEncLength(int $valueLength): int
    {
        return match (true) {
            $valueLength < 251 => 1,
            $valueLength < (1 << 16) => 1 + 2,
            $valueLength < (1 << 24) => 1 + 3,
            default => 1 + 8,
        };
    }

    /**
     * 构造函数
     *
     * @param array|string|null $payload 字符串或者字节数组
     * @throws InvalidArgumentException
     */
    public function __construct(mixed $payload = null)
    {
        $this->payload = $payload;
        switch (true) {
            case is_null($payload):
                $this->bytes = [];
                break;
            case is_numeric($payload):
                $this->bytes = [(int)$payload];
                break;
            case is_string($payload):
                $this->bytes = static::StringToBytes($payload);
                break;
            case is_iterable($payload) or ($payload instanceof stdClass):
                $payload = ($payload instanceof stdClass) ? get_object_vars($payload) : $payload;
                $bytes = [];
                foreach ($payload as $index => $byte) {
                    if (!is_int($byte) || $byte < 0 || $byte > 255) {
                        throw new InvalidArgumentException("[Error Type] Bytes '$index'->'$byte' is invalid");
                    }
                    $bytes[] = $byte;
                }
                $this->bytes = $bytes;
                break;
            default:
                $type = gettype($payload);
                throw new InvalidArgumentException("[Error Type] Payload type '$type' is invalid");
        }
    }

    /**
     * @return mixed
     */
    public function getPayload(): mixed
    {
        return $this->payload;
    }

    /**
     * 获取读指针的位置
     *
     * @return int
     */
    public function getReadCursor(): int
    {
        return $this->readCursor;
    }

    /**
     * 获取写指针的位置
     *
     * @return int
     */
    public function getWriteCursor(): int
    {
        return $this->writeCursor;
    }

    /**
     * 设置内部指针的位置
     *
     * @param int $position
     * @throws InvalidArgumentException
     */
    public function setReadCursor(int $position): void
    {
        if ($position < 0 || $position > $this->count()) {
            throw new InvalidArgumentException("[Error Value] Invalid cursor '$position'");
        }
        $this->readCursor = $position;
    }

    /**
     * @param int $position
     * @return void
     */
    public function setWriteCursor(int $position): void
    {
        if ($this->count() < $position) {
            for ($i = $this->count(); $i < $position; $i++) {
                $this->bytes[] = 0;
            }
        }
        $this->writeCursor = $position;
    }

    /**
     * 将payload转换为字节数组
     *
     * @return array
     */
    public function unpack(): array
    {
        return $this->bytes;
    }

    /**
     * 将bytes转换为字符串形式
     *
     * @return string
     */
    public function pack(): string
    {
        if ($this->string === null) {
            $this->string = pack('C*', ...$this->bytes);
        }
        return $this->string;
    }

    /**
     * 获取二进制字符串长度
     *
     * @param bool $cache
     * @return int
     */
    public function length(bool $cache = false): int
    {
        if (!$cache) {
            $this->length = null;
        }
        if ($this->length === null) {
            $this->length = strlen($this->pack());
        }
        return $this->length;
    }

    /**
     * 获取字节数组数量
     *
     * @param bool $cache
     * @return int
     */
    public function count(bool $cache = false): int
    {
        if (!$cache) {
            $this->count = null;
        }
        if ($this->count === null) {
            $this->count = count($this->unpack());
        }
        return $this->count;
    }

    /**
     * 生成友好打印的二进制 map
     *
     * @return string
     */
    public function dump(): string
    {
        $total = $this->count();
        $output = '';
        $bytesPerLine = 16;

        for ($i = 0; $i < $total; $i += $bytesPerLine) {
            $line = sprintf('%08X  ', $i);
            $chunk = array_slice($this->bytes, $i, $bytesPerLine);
            $hexPart = '';
            $asciiPart = '';

            foreach ($chunk as $index => $byte) {
                $hexPart .= sprintf('%02X ', $byte);
                if ($index === 7) {
                    $hexPart .= ' ';
                }
                $asciiPart .= ($byte >= 32 && $byte <= 126) ? chr($byte) : '.';
            }
            $expectedHexLen = ($bytesPerLine * 3) + 1;
            $hexPart = str_pad($hexPart, $expectedHexLen);
            $line .=  "$hexPart |$asciiPart|\n";
            $output .= $line;
        }

        return $output;
    }

    /**
     * 读取当前指针位置的一个字节（返回 0～255 的整数），随后指针前移 1
     *
     * @return int
     */
    public function readByte(): int
    {
        if ($this->readCursor >= $this->length()) {
            throw new InvalidArgumentException("[Error Cursor] Read Cursor '$this->readCursor' > length of data");
        }
        return $this->bytes[$this->readCursor++];
    }

    /**
     * 从当前指针位置读取指定长度的字节串，随后指针前移相应长度
     *
     * @param int $length
     * @return int[]
     */
    public function readBytes(int $length): array
    {
        if ($this->readCursor + $length > $this->length()) {
            throw new InvalidArgumentException("[Error Cursor] Read Cursor '$this->readCursor' + length '$length' > length of data");
        }
        $readOffset = $this->readCursor;
        $this->readCursor += $length;
        return array_slice($this->bytes, $readOffset, $length);
    }

    /**
     * 从当前指针位置读取直到第一个 NULL 字节（0x00）
     *
     * @return int[]
     */
    public function readNullTerminated(): array
    {
        $bytes = [];
        while (true) {
            $byte = $this->readByte();
            if ($byte === 0) {
                break;
            }
            $bytes[] = $byte;
        }
        return $bytes;
    }

    /**
     * 当前指针位置读取指定字节数组成的无符号整数
     *
     * @param int $byteCount 字节数
     * @param bool $littleEndian 小端序
     * @return int
     */
    public function readUB(int $byteCount, bool $littleEndian = true): int
    {
        $bytes = $this->readBytes($byteCount);
        if (!$littleEndian) {
            $bytes = array_reverse($bytes);
        }
        $func = static function ($bytes, $byteCount) {
            $value = 0;
            for ($i = 0; $i < $byteCount; $i++) {
                $byte = $bytes[$i];
                $value |= $byte << (8 * $i);
            }
            return $value;
        };
        return match ($byteCount) {
            self::UB2   => unpack('v', self::BytesToString($bytes))[1],
            self::UB4   => unpack('V',self::BytesToString($bytes))[1],
            self::UB8   => unpack('P', self::BytesToString($bytes))[1],
            default     => $func($bytes, $byteCount)
        };
    }

    /**
     * 写入一个字节到当前的字符串数据末尾，并刷新缓存
     *
     * @param int $byte 取值 0～255
     */
    public function writeByte(int $byte): void
    {
        if ($byte < 0 || $byte > 255) {
            throw new InvalidArgumentException("[Error Type] Byte '$byte' is invalid");
        }
        $this->bytes[$this->writeCursor ++] = $byte;
    }

    /**
     * 写入一组字节到当前的字符串数据末尾
     *
     * @param int[] $bytes
     */
    public function writeBytes(array $bytes): void
    {
        $bs = [];
        foreach ($bytes as $index => $byte) {
            if ($byte < 0 || $byte > 255) {
                throw new InvalidArgumentException("[Error Type] Bytes '$index'->'$byte' is invalid");
            }
            $bs[] = $byte;
        }
        foreach ($bs as $byte) {
            $this->bytes[$this->writeCursor ++] = $byte;
        }
    }

    /**
     * 写入一个无符号整数，写入字节数由 $byteCount 决定
     *
     * @param int $int
     * @param int $byteCount
     * @param bool $littleEndian
     */
    public function writeUB(int $int, int $byteCount, bool $littleEndian = true): void
    {
        $fuc = static function ($int, $byteCount) {
            $bytes = [];
            for ($i = 0; $i < $byteCount; $i++) {
                $bytes[] = ($int >> (8 * $i)) & 255;
            }
            return $bytes;
        };
        $bytes = match ($byteCount) {
            self::UB2   => unpack('C*', pack('v', $int)),
            self::UB4   => unpack('C*', pack('V', $int)),
            self::UB8   => unpack('C*', pack('P', $int)),
            default     => $fuc($int, $byteCount)
        };
        $this->writeBytes(!$littleEndian ? array_reverse($bytes) : $bytes);
    }

    /**
     * 追加写入NULL终止符
     *
     * @param array $bytes
     */
    public function writeNullTerminated(array $bytes): void
    {
        $bytes[] = 0;
        $this->writeBytes($bytes);
    }

    /**
     * 读取“长度编码整数”。
     *
     * @return int
     */
    public function readLenEncInt(): int
    {
        $first = $this->readByte();
        return match (true) {
            $first < 251 => $first,
            $first === 0xfc => $this->readUB(static::UB2),
            $first === 0xfd => $this->readUB(static::UB3),
            $first === 0xfe => $this->readUB(static::UB8),
            default => throw new InvalidArgumentException("[Error Byte] Integer first tag '$first' is invalid"),
        };
    }

    /**
     * 读取“长度编码字符串”。
     *
     * @return string
     */
    public function readLenEncString(): string
    {
        return static::BytesToString($this->readBytes($this->readLenEncInt()));
    }

    /**
     * 写入“长度编码整数”。
     *
     * @param int $value
     */
    public function writeLenEncInt(int $value): void
    {
        switch (true) {
            case $value < 251:
                $this->writeByte($value);
                break;
                case $value < (1 << 16):
                $this->writeByte(0xfc);
                $this->writeUB($value, static::UB2);
                break;
            case $value < (1 << 24):
                $this->writeByte(0xfd);
                $this->writeUB($value, static::UB3);
                break;
            default:
                $this->writeByte(0xfe);
                $this->writeUB($value, static::UB8);
                break;
        }
    }

    /**
     * 写入“长度编码字符串”。
     *
     * @param string $str
     */
    public function writeLenEncString(string $str): void
    {
        $bytes = static::StringToBytes($str);
        $this->writeLenEncInt(strlen($str));
        $this->writeBytes($bytes);
    }
}