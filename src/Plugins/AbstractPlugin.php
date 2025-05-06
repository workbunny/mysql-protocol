<?php

declare(strict_types=1);

namespace Workbunny\src\Plugins;

use;
use Exception;
use nWorkbunny\MysqlProtocol\Utils\Binary;

abstract class AbstractPlugin
{

    /**
     * 默认插件
     *
     * @var class-string[]
     */
    protected static array $plugins = [
        'mysql_native_password' => MySQLNativePasswordAuthPlugin::class,
        'caching_sha2_password' => CachingSha2PasswordAuthPlugin::class,
    ];

    /** @var AbstractPlugin[] */
    protected static array $pluginInstances = [];

    /**
     * 注册插件
     *
     * @param string $authPluginName
     * @param class-string $pluginClass
     * @return void
     */
    public static function register(string $authPluginName, string $pluginClass): void
    {
        if (isset(static::$plugins[$authPluginName]) and is_a($pluginClass, AbstractPlugin::class, true)) {
            static::$plugins[$authPluginName] = $pluginClass;
        }
    }

    /**
     * 工厂
     *
     * @param string $authPluginName
     * @return AbstractPlugin
     * @throws Exception
     */
    public static function factory(string $authPluginName): AbstractPlugin
    {
        $pluginClass = static::$plugins[$authPluginName] ?? null;
        if (!$pluginClass) {
            throw new Exception("Auth plugin [$authPluginName] not found.");
        }
        if (!isset(static::$pluginInstances[$authPluginName])) {
            static::$pluginInstances[$authPluginName] = new $pluginClass;
        }
        return static::$pluginInstances[$authPluginName];
    }

    /**
     * 盐值 [part1, part2]
     *
     * @return array<int[], int[]>
     */
    abstract public function authData(): array;

    /**
     * 认证插件名称
     *
     * @return string
     */
    abstract public function authPluginName(): string;

    /**
     * 服务端响应逻辑
     *
     * @param string $payload
     * @return Binary
     */
    abstract public function server(string $payload): Binary;

    /**
     * 客户端相应逻辑
     *
     * @param string $payload
     * @return Binary
     */
    abstract public function client(string $payload): Binary;

}
