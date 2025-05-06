<?php

declare(strict_types=1);

namespace Workbunny\src\Plugins;

use nWorkbunny\MysqlProtocol\Utils\Binary;

class CachingSha2PasswordAuthPlugin extends AbstractPlugin
{
    /** @inheritdoc  */
    public function authPluginName(): string
    {
        return 'caching_sha2_password';
    }

    /** @inheritdoc  */
    public function authData(): array
    {
        // TODO: Implement authData() method.
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