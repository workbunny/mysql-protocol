<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Exceptions;

use Throwable;
use Workbunny\MysqlProtocol\Constants\ExceptionCode;

class Exception extends \RuntimeException
{
    /** @var ExceptionCode  */
    protected ExceptionCode $errorCode;

    /**
     * @param string $message
     * @param ExceptionCode $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message, ExceptionCode $code = ExceptionCode::ERROR, ?Throwable $previous = null)
    {
        $this->errorCode = $code;
        parent::__construct("[{$code->getName($code->value)}] $message", $code->value, $previous);
    }

    /**
     * @return ExceptionCode
     */
    public function getErrorCode(): ExceptionCode
    {
        return $this->errorCode;
    }
}
