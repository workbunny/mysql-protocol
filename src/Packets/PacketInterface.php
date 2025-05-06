<?php

declare(strict_types=1);

namespace nWorkbunny\MysqlProtocol\Packets;

use nWorkbunny\MysqlProtocol\Utils\Binary;

interface PacketInterface
{
    /**
     * 从 Binary 对象中解包为 PHP 数组表示的数据包内容。
     *
     * @param Binary $binary
     * @return array
     */
    public static function unpack(Binary $binary): array;

    /**
     * 将 PHP 数组表示的数据包内容封装为 Binary 对象。
     *
     * @param array $data
     * @return Binary
     */
    public static function pack(array $data): Binary;
}