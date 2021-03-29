<?php

declare(strict_types=1);

namespace Pixelant\Interest\Handler\Exception;

use Psr\Http\Message\RequestInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Exception issued in cases where HTTP Authentication fails. Should not be used for TYPO3 backend user access
 * restriction errors.
 */
class DataHandlerErrorException extends AbstractRequestHandlerException
{
    protected const RESPONSE_CODE = 400;

    public function __construct(DataHandler $dataHandler, RequestInterface $request)
    {
        if (count($dataHandler->errorLog) === 0) {
            throw new \UnexpectedValueException(
                'No DataHandler errors. This exception should not have been thrown.',
                1616669972
            );
        }

        $message = 'Error occured during the data handling: ' . implode(', ', $dataHandler->errorLog) . ')';

        parent::__construct($message, $request, null);
    }
}
