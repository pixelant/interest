<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler;

use Pixelant\Interest\Context;
use Pixelant\Interest\Database\RelationHandlerWithoutReferenceIndex;
use Pixelant\Interest\DataHandling\Operation\Exception\AbstractException;
use Pixelant\Interest\RequestHandler\Exception\MissingArgumentException;
use Pixelant\Interest\RequestHandler\ExceptionConverter\OperationToRequestHandlerExceptionConverter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractRecordRequestHandler extends AbstractRequestHandler
{
    /**
     * @var array The data array to be processed.
     */
    protected array $data = [];

    /**
     * @var array Metadata for the processing.
     */
    protected array $metaData = [];

    /**
     * @param array $entryPointParts
     * @param ServerRequestInterface $request
     */
    public function __construct(array $entryPointParts, ServerRequestInterface $request)
    {
        parent::__construct($entryPointParts, $request);

        Context::setDisableReferenceIndex($request->getQueryParams()['disableReferenceIndex'] ?? false);

        if (Context::isDisableReferenceIndex()) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][RelationHandler::class] = [
                'className' => RelationHandlerWithoutReferenceIndex::class
            ];
        }

        $this->compileData();
    }

    /**
     * Correctly compiles the $data and $metaData.
     *
     * $data is compiled into a multidimensional array like this:
     *
     * $data = [
     *     tableName => [
     *         remoteId => [
     *             language => [
     *                 workspace => [
     *                      // Key-value pairs of record data.
     *                 ],
     *                 ...
     *             ],
     *             ...
     *         ],
     *         ...
     *     ],
     *     ...
     * ];
     *
     * @return void
     */
    protected function compileData(): void
    {
        $this->getRequest()->getBody()->rewind();

        $body = $this->getRequest()->getBody()->getContents();

        if (empty($body)) {
            return;
        }

        $decodedContent = json_decode($body) ?? [];

        $this->metaData = $this->convertObjectToArrayRecursive((array)($decodedContent->metaData ?? []));

        $data = $decodedContent->data ?? new \stdClass();

        $table = $this->getEntryPointParts()[0] ?? $decodedContent->table ?? null;

        $remoteId = $this->getEntryPointParts()[1] ?? $decodedContent->remoteId ?? null;

        $language = $this->getEntryPointParts()[2]
            ?? $decodedContent->language
            ?? $this->getRequest()->getQueryParams()['language']
            ?? null;

        $workspace = $this->getEntryPointParts()[3]
            ?? $decodedContent->workspace
            ?? $this->getRequest()->getQueryParams()['workspace']
            ?? null;

        $dataLayerCount = 0;
        $currentLayer = $data;
        do {
            if ($this->isRecordData($currentLayer)) {
                break;
            }

            $currentLayer = next($currentLayer);

            $dataLayerCount++;
        } while ($dataLayerCount < 5);

        if ($dataLayerCount < 4) {
            $data = [(string)$workspace => $data];
        }

        if ($dataLayerCount < 3) {
            $data = [(string)$language => $data];
        }

        if ($dataLayerCount < 2) {
            if ($remoteId === null) {
                throw new MissingArgumentException(
                    'Remote ID not specified.',
                    $this->getRequest()
                );
            }

            $data = [$remoteId => $data];
        }

        if ($dataLayerCount < 1) {
            if ($table === null) {
                throw new MissingArgumentException(
                    'Table not specified.',
                    $this->getRequest()
                );
            }

            $data = [$table => $data];
        }

        $this->data = $this->convertObjectToArrayRecursive($data);
    }

    /**
     * @inheritDoc
     */
    public function handle(): ResponseInterface
    {
        $exceptions = [];

        $operationCount = 0;

        $exception = null;

        foreach ($this->data as $table => $tableData) {
            foreach ($tableData as $remoteId => $remoteIdData) {
                foreach ($remoteIdData as $language => $languageData) {
                    foreach ($languageData as $workspace => $data) {
                        $operationCount++;

                        try {
                            $this->handleSingleOperation($table, $remoteId, $language, $workspace, $data);
                        } catch (\Throwable $exception) {
                            $exceptions[$table][$remoteId][$language][$workspace] = $exception;
                        }
                    }
                }
            }
        }

        if (count($exceptions) === 0) {
            return GeneralUtility::makeInstance(
                JsonResponse::class,
                [
                    'success' => true,
                    'message' => '1 operation completed successfully.',
                ],
                200
            );
        }

        if ($operationCount === 1 && count($exceptions)) {
            throw OperationToRequestHandlerExceptionConverter::convert($exception, $this->getRequest());
        }

        $statuses = [];

        $exceptionCount = 0;
        foreach ($this->data as $table => $tableData) {
            foreach ($tableData as $remoteId => $remoteIdData) {
                foreach ($remoteIdData as $language => $languageData) {
                    foreach ($languageData as $workspace => $data) {
                        if (isset($exceptions[$table][$remoteId][$language][$workspace])) {
                            $responseException = OperationToRequestHandlerExceptionConverter::convert(
                                $exceptions[$table][$remoteId][$language][$workspace],
                                $this->getRequest()
                            );

                            $status = [
                                'success' => false,
                                'code' => $responseException->getCode(),
                                'message' => $responseException->getMessage(),
                            ];

                            $exceptionCount++;
                        } else {
                            $status = [
                                'success' => true,
                            ];
                        }

                        $statuses[$table][$remoteId][$language][$workspace] = $status;
                    }
                }
            }
        }

        return GeneralUtility::makeInstance(
            JsonResponse::class,
            [
                'success' => $exceptionCount === 0,
                'message' => $exceptionCount . ' operations failed while ' . ($operationCount - $exceptionCount) . ' operations completed successfully.',
                'statuses' => $statuses,
                'total' => $operationCount,
                'successful' => $operationCount - $exceptionCount,
                'unsuccessful' => $exceptionCount
            ],
            207
        );
    }

    /**
     * Handle a single record operation.
     *
     * @param string $table
     * @param string $remoteId
     * @param string $language
     * @param string $workspace
     * @return void
     */
    abstract protected function handleSingleOperation(
        string $table,
        string $remoteId,
        string $language,
        string $workspace,
        array $data
    ): void;

    /**
     *
     *
     * @param object $object
     * @return bool True if the object contains any other value than objects.
     */
    private function isRecordData(object $object): bool
    {
       return !is_object(next($object));
    }

    /**
     * Recursively convert an array of objects into an array of arrays.
     *
     * @param array $values
     * @return array
     */
    private function convertObjectToArrayRecursive(array $values): array
    {
        foreach ($values as &$value) {
            if (is_object($value)) {
                $value = (array)$value;
            }

            if (is_array($value)) {
                $value = $this->convertObjectToArrayRecursive($value);
            }
        }

        return $values;
    }
}
