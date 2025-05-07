<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Utils;

use Closure;
use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\Exception;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Packets\AuthMoreDataRequest;
use Workbunny\MysqlProtocol\Packets\AuthSwitchRequest;
use Workbunny\MysqlProtocol\Packets\EOF;
use Workbunny\MysqlProtocol\Packets\Error;
use Workbunny\MysqlProtocol\Packets\OK;
use Workbunny\MysqlProtocol\Packets\PacketInterface;

class Packet
{

    /**
     * 新建符合Packet基础协议的binary对象
     *
     * @param Closure $closure = function(Binary $binary) {}
     * @param int $packetId
     * @return Binary
     */
    public static function binary(Closure $closure, int $packetId = 0): Binary
    {
        $binary = new Binary();
        $binary->setWriteCursor($pos = 3);
        $binary->writeByte($packetId);
        $closure($binary);
        $packetLength = $binary->getWriteCursor() - 1 - $pos;
        $binary->setWriteCursor(0);
        $binary->writeUB($packetLength, Binary::UB3);
        return $binary;
    }

    /**
     * 快速解析包头和包体
     *
     * @param Closure|null $closure
     * @param Binary $binary
     * @return array
     */
    public static function parser(?Closure $closure, Binary $binary): array
    {
        // 重置读指针
        $binary->setReadCursor(0);
        // 包头
        $packetLength = $binary->readUB(Binary::UB3);
        $packetId = $binary->readByte();
        $result = $closure ? $closure($binary) : [];
        if (!is_array($result)) {
            throw new PacketException('Packet parser must return array', ExceptionCode::ERROR);
        }
        return array_merge([
            'packet_length' => $packetLength,
            'packet_id' => $packetId,
        ], $result);
    }

    /**
     * 根据包头获取包类型
     *
     * @param Binary $binary
     * @param Closure|null $closure = function(Binary $binary): class-string<PacketInterface>|null {}
     * @return class-string<PacketInterface>|null
     */
    public static function getPacketClass(Binary $binary, ?Closure $closure = null): ?string
    {
        // 获取当前指针
        $readCursor = $binary->getReadCursor();
        // 重置读指针
        $binary->setReadCursor(0);
        // 包长
        $packetLength = $binary->readUB(Binary::UB3);
        // 都指针下移包身
        $binary->setReadCursor(4);
        // 读取flag
        $header = $binary->readByte();
        // 恢复指针
        $binary->setReadCursor($readCursor);
        return match (true) {
            $header === Ok::PACKET_FLAG => OK::class,
            $header === Error::PACKET_FLAG => Error::class,
            // EOF 包体长度小于9字节，且flag为0xFE
            ($packetLength < 9) and $header === EOF::PACKET_FLAG => EOF::class,
            // AuthSwitchRequest 包体长度通常大于9字节，且flag为0xFE
            $header === AuthSwitchRequest::PACKET_FLAG => AuthSwitchRequest::class,
            $header === AuthMoreDataRequest::PACKET_FLAG => AuthMoreDataRequest::class,
            // 如果传入自定义解析函数，则使用
            default => $closure ? $closure($binary) : null,
        };
    }

    /**
     * 生成认证数据
     *
     * @param int $bytesCount
     * @return array<int>
     */
    public static function authData(int $bytesCount = 8): array
    {
        try {
            $bytesCount = $bytesCount  > 21 ? 21 : max($bytesCount, 8);
            return array_values(unpack('C*', random_bytes($bytesCount)));
        } catch (\Throwable $throwable) {
            throw new Exception($throwable->getMessage(), ExceptionCode::ERROR, $throwable);
        }
    }
}