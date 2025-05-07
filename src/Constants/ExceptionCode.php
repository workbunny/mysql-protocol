<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Constants;

enum ExceptionCode : int
{
    use EnumTraits;

    case ERROR = 0;
    case ERROR_VALUE = 1;
    case ERROR_TYPE = 2;

    case ERROR_CURSOR = 3;

    case ERROR_SUPPORT = 4;
}
