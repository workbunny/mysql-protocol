<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Plugins;

use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

class MySQLNativePasswordAuthPlugin extends AbstractPlugin
{
    /** @inheritdoc  */
    public function authPluginName(): string
    {
        return 'mysql_native_password';
    }

    /** @inheritdoc  */
    public function authData(): array
    {
        return Packet::authData();
    }

    /** @inheritdoc  */
    public function server(string $payload): Binary
    {
        // TODO: Implement server() method.
    }

    /** @inheritdoc  */
    public function client(string $payload): Binary
    {
        // TODO: Implement client() method.
    }
}