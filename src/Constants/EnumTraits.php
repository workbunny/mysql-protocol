<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Constants;

trait EnumTraits
{
    /**
     * 获取名称
     *
     * @param $value
     * @return string|null
     */
    public static function getName($value): ?string
    {
        if ($enum = self::tryFrom($value)) {
            return ucwords(strtolower(str_replace('_', ' ', $enum->name)));
        }
        return null;
    }
}
