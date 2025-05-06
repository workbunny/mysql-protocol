<?php

declare(strict_types=1);

namespace Protocols;

use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;
use Workerman\Connection\ConnectionInterface;
use Workerman\Worker;

class MySQL
{

    /**
     * Check the integrity of the package.
     * Please return the length of package.
     * If length is unknown please return 0 that means waiting for more data.
     * If the package has something wrong please return -1 the connection will be closed.
     *
     * @param string $buffer
     * @param ConnectionInterface $connection
     * @return int
     */
    public static function input(string $buffer, ConnectionInterface $connection): int
    {
        try {
            $data = Packet::parser(function (Binary $binary) {
                return [];
            }, $binary = new Binary($buffer));
            return 4 + $data['packet_length'];
        } catch (\Throwable $throwable) {
            Worker::safeEcho("Error: {$throwable->getMessage()}\n");
            $connection->close();
            return 0;
        }
    }

    /**
     * Decode package and emit onMessage($message) callback, $message is the result that decode returned.
     *
     * @param string $buffer
     * @param ConnectionInterface $connection
     * @return Binary|null
     */
    public static function decode(string $buffer, ConnectionInterface $connection): ?Binary
    {
        try {
            return new Binary($buffer);
        } catch (\Throwable $throwable) {
            Worker::safeEcho("Error: {$throwable->getMessage()}\n");
            $connection->close();
            return null;
        }
    }

    /**
     * Encode package before sending to client.
     *
     * @param Binary $data
     * @param ConnectionInterface $connection
     * @return string
     */
    public static function encode(Binary $data, ConnectionInterface $connection): string
    {
        return $data->pack();
    }
}
