<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Packets;

use Workbunny\MysqlProtocol\Constants\ExceptionCode;
use Workbunny\MysqlProtocol\Exceptions\PacketException;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

class ChangeUser implements PacketInterface
{
    public const COMMAND = 0x11;

    public static function unpack(Binary $binary): array
    {
        return Packet::parser(function (Binary $binary) {
            $command = $binary->readByte();
            if ($command !== self::COMMAND) {
                throw new PacketException("Invalid command '$command', expected 0x11", ExceptionCode::ERROR_VALUE);
            }
            $user = Binary::BytesToString($binary->readNullTerminated());
            $authLen = $binary->readByte();
            $authResponse = Binary::BytesToString($binary->readBytes($authLen));
            $database = Binary::BytesToString($binary->readNullTerminated());
            $characterSet = 0;
            $remaining = $binary->length() - $binary->getReadCursor();
            if ($remaining >= 2) {
                $characterSet = $binary->readUB(Binary::UB2);
            }
            $authPlugin = null;
            $remaining = $binary->length() - $binary->getReadCursor();
            if ($remaining > 0) {
                $authPlugin = Binary::BytesToString($binary->readNullTerminated());
            }
            $attributes = [];
            $remaining = $binary->length() - $binary->getReadCursor();
            if ($remaining > 0) {
                $attrLen = $binary->readLenEncInt();
                $attrEnd = $binary->getReadCursor() + $attrLen;
                while ($binary->getReadCursor() < $attrEnd) {
                    $key   = $binary->readLenEncString();
                    $value = $binary->readLenEncString();
                    $attributes[$key] = $value;
                }
            }
            return [
                'command'       => $command,
                'user'          => $user,
                'auth_response' => $authResponse,
                'database'      => $database,
                'character_set' => $characterSet,
                'auth_plugin'   => $authPlugin,
                'attributes'    => $attributes,
            ];
        }, $binary);
    }

    public static function pack(array $data): Binary
    {
        return Packet::binary(function (Binary $binary) use ($data) {
            $user         = (string)($data['user'] ?? '');
            $authResponse = (string)($data['auth_response'] ?? '');
            $database     = $data['database'] ?? null;
            $characterSet = (int)($data['character_set'] ?? 33);
            $authPlugin   = $data['auth_plugin'] ?? null;
            $attributes   = (array)($data['attributes'] ?? []);

            $binary->writeByte(self::COMMAND);
            $binary->writeNullTerminated(Binary::StringToBytes($user));
            $authBytes = Binary::StringToBytes($authResponse);
            $binary->writeByte(count($authBytes));
            $binary->writeBytes($authBytes);
            // MySQL 协议要求 database 始终存在（空时为单字节 NULL 终止符）
            $binary->writeNullTerminated(Binary::StringToBytes((string)$database));
            $binary->writeUB($characterSet, Binary::UB2);
            if ($authPlugin !== null) {
                $binary->writeNullTerminated(Binary::StringToBytes((string)$authPlugin));
            }
            if (!empty($attributes)) {
                $attrBlob = new Binary();
                foreach ($attributes as $key => $value) {
                    $attrBlob->writeLenEncString((string)$key);
                    $attrBlob->writeLenEncString((string)$value);
                }
                $attrStr = $attrBlob->pack();
                $binary->writeLenEncInt(strlen($attrStr));
                $binary->writeBytes(Binary::StringToBytes($attrStr));
            }
        }, (int)($data['packet_id'] ?? 0));
    }
}
