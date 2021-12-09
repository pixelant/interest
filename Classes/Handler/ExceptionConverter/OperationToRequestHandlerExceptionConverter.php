<?php

declare(strict_types=1);


namespace Pixelant\Interest\Handler\ExceptionConverter;


use Pixelant\Interest\DataHandling\Operation\Exception\AbstractException;
use Pixelant\Interest\DataHandling\Operation\Exception\ConflictException as OperationConflictException;
use Pixelant\Interest\DataHandling\Operation\Exception\DataHandlerErrorException as OperationDataHandlerErrorException;
use Pixelant\Interest\DataHandling\Operation\Exception\IdentityConflictException as OperationIdentityConflictException;
use Pixelant\Interest\DataHandling\Operation\Exception\InvalidArgumentException as OperationInvalidArgumentException;
use Pixelant\Interest\DataHandling\Operation\Exception\MissingArgumentException as OperationMissingArgumentException;
use Pixelant\Interest\DataHandling\Operation\Exception\NotFoundException as OperationNotFoundException;
use Pixelant\Interest\Handler\Exception\ConflictException;
use Pixelant\Interest\Handler\Exception\DataHandlerErrorException;
use Pixelant\Interest\Handler\Exception\InvalidArgumentException;
use Pixelant\Interest\Handler\Exception\MissingArgumentException;
use Pixelant\Interest\Handler\Exception\NotFoundException;
use Pixelant\Interest\Http\InterestRequestInterface;

final class OperationToRequestHandlerExceptionConverter
{
    private const EXCEPTION_MAP = [
        OperationConflictException::class => ConflictException::class,
        OperationDataHandlerErrorException::class => DataHandlerErrorException::class,
        OperationIdentityConflictException::class => ConflictException::class,
        OperationInvalidArgumentException::class => InvalidArgumentException::class,
        OperationNotFoundException::class => NotFoundException::class,
        OperationMissingArgumentException::class => MissingArgumentException::class,
    ];

    /**
     * @param AbstractException $exception The exception to convert.
     * @param InterestRequestInterface $request The request to attach.
     * @return \Throwable
     */
    public static function convert(
        AbstractException $exception,
        InterestRequestInterface $request
    ): \Throwable
    {
        if (array_key_exists(get_class($exception), self::EXCEPTION_MAP)) {
            $newExceptionFqcn = self::EXCEPTION_MAP[get_class($exception)];
            return new $newExceptionFqcn(
                sprintf('%s (%s)', $exception->getMessage(), $exception->getCode()),
                $request
            );
        }

        return $exception;
    }
}
