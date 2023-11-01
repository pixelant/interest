<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler;

use Pixelant\Interest\Context;
use Pixelant\Interest\Database\RelationHandlerWithoutReferenceIndex;
use Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException;
use Pixelant\Interest\DataHandling\Operation\Exception\AbstractException;
use Pixelant\Interest\Domain\Model\Dto\RecordInstanceIdentifier;
use Pixelant\Interest\Domain\Model\Dto\RecordRepresentation;
use Pixelant\Interest\RequestHandler\ExceptionConverter\OperationToRequestHandlerExceptionConverter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractRecordRequestHandler extends AbstractRequestHandler
{
    /**
     * @const bool If true, we don't expect any more data than what can be passed in the URL. E.g. DELETE operations.
     */
    protected const EXPECT_EMPTY_REQUEST = false;

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

        Context::setDisableReferenceIndex(
            filter_var(
                $request->getHeader('Interest-Disable-Reference-Index')[0] ?? 'false',
                FILTER_VALIDATE_BOOLEAN
            )
        );

        if (Context::isDisableReferenceIndex()) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][RelationHandler::class] = [
                'className' => RelationHandlerWithoutReferenceIndex::class,
            ];
        }

        $this->compileData();
    }

    /**
     * Correctly compiles the $data and $metaData.
     *
     * phpcs:disable Generic.Metrics.CyclomaticComplexity
     */
    protected function compileData(): void
    {
        $body = '';

        if ($this->getRequest()->getBody() instanceof StreamInterface) {
            $this->getRequest()->getBody()->rewind();

            $body = $this->getRequest()->getBody()->getContents();
        }

        if (!static::EXPECT_EMPTY_REQUEST && $body === '') {
            return;
        }

        if ($body === '') {
            $decodedContent = [];
        } else {
            $decodedContent = json_decode($body) ?? [];
        }

        if (is_string($decodedContent->metaData ?? null)) {
            $decodedContent->metaData = json_decode($decodedContent->metaData) ?? [];
        }

        $this->metaData = $this->convertObjectToArrayRecursive((array)($decodedContent->metaData ?? []));

        if (is_string($decodedContent->data ?? null)) {
            $decodedContent->data = json_decode($decodedContent->data) ?? new \stdClass();
        }

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

        $data = $this->formatDataArray($data, $table, $remoteId, $language, $workspace);

        $this->data = $data;
    }

    /**
     * @return ResponseInterface
     * phpcs:disable Squiz.Commenting.FunctionCommentThrowTag
     */
    public function handle(): ResponseInterface
    {
        if ($this->data === []) {
            return GeneralUtility::makeInstance(
                JsonResponse::class,
                [
                    'success' => false,
                    'message' => 'The request contained insufficient or no data',
                ],
                400
            );
        }

        $operationCount = 0;

        $exceptions = $this->handleOperations($operationCount);

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

        if ($operationCount === 1 && count($exceptions) > 0) {
            throw OperationToRequestHandlerExceptionConverter::convert(
                current(ArrayUtility::flatten($exceptions)),
                $this->getRequest()
            );
        }

        $exceptionCount = 0;

        $statuses = $this->convertExceptionsToResponseStatuses($exceptions, $exceptionCount);

        return GeneralUtility::makeInstance(
            JsonResponse::class,
            [
                'success' => $exceptionCount === 0,
                'message' => $exceptionCount . ' operations failed while ' . ($operationCount - $exceptionCount)
                    . ' operations completed successfully.',
                'statuses' => $statuses,
                'total' => $operationCount,
                'successful' => $operationCount - $exceptionCount,
                'unsuccessful' => $exceptionCount,
            ],
            207
        );
    }

    /**
     * Handle a single record operation.
     *
     * @param RecordRepresentation $recordRepresentation
     */
    abstract protected function handleSingleOperation(
        RecordRepresentation $recordRepresentation
    ): void;

    /**
     * @param object $object
     * @return bool True if the object contains any other value than objects.
     */
    private function isRecordData(object $object): bool
    {
        $array = (array)$object;

        return !is_object($array[array_key_first($array)] ?? null);
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
            $valueCopy = (array)$value;

            if (!is_object($valueCopy[array_key_first($valueCopy)])) {
                continue;
            }

            if (!is_array($valueCopy[array_key_first($valueCopy)]) || is_object($value)) {
                $value = $this->convertObjectToArrayRecursive((array)$value);
            } elseif (is_array($value)) {
                $value = $this->convertObjectToArrayRecursive($value);
            }
        }

        return $values;
    }

    /**
     * Compile $data into a multidimensional array like this:
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
     * @param \stdClass $data
     * @param string|null $table
     * @param string|null $remoteId
     * @param string|null $language
     * @param int|null $workspace
     * @return array
     */
    protected function formatDataArray(
        \stdClass $data,
        ?string $table,
        ?string $remoteId,
        ?string $language,
        ?int $workspace
    ): array {
        $layerCount = 0;

        if ($table !== null) {
            $layerCount++;
        }

        if ($remoteId !== null) {
            $layerCount++;
        }

        if (!$this->isRecordData($data) && (array)$data !== []) {
            $currentLayer = $data;
            do {
                $layerCount++;

                $currentLayer = current((array)$currentLayer);
            } while ($currentLayer !== false && !$this->isRecordData($currentLayer));

            $data = $this->convertObjectToArrayRecursive((array)$data);

            array_walk_recursive(
                $data,
                function (&$item) use ($layerCount, $workspace, $language) {
                    $item = (array)$item;

                    if ($layerCount < 4 || $workspace !== null) {
                        $item = [(string)$workspace => $item];
                    }

                    if ($layerCount < 3 || $language !== null) {
                        $item = [(string)$language => $item];
                    }
                }
            );
        } else {
            $data = [
                (string)$language => [
                    (string)$workspace => (array)$data,
                ],
            ];
        }

        if ($remoteId !== null) {
            $data = [$remoteId => $data];
        }

        if ($table !== null) {
            $data = [$table => $data];
        }

        return $data;
    }

    /**
     * @param array $exceptions
     * @param int $exceptionCount Will be populated with the number of exceptions found.
     * @return array
     */
    protected function convertExceptionsToResponseStatuses(array $exceptions, int &$exceptionCount = 0): array
    {
        $statuses = [];
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

        return $statuses;
    }

    /**
     * @param int $operationCount Will be populated with the number of operations completed.
     * @return array
     */
    protected function handleOperations(&$operationCount = 0): array
    {
        $exceptions = [];

        foreach ($this->data as $table => $tableData) {
            foreach ($tableData as $remoteId => $remoteIdData) {
                foreach ($remoteIdData as $language => $languageData) {
                    foreach ($languageData as $workspace => $data) {
                        $operationCount++;

                        try {
                            $this->handleSingleOperation(
                                new RecordRepresentation(
                                    $data,
                                    new RecordInstanceIdentifier(
                                        $table,
                                        $remoteId,
                                        (string)$language,
                                        (string)$workspace
                                    )
                                )
                            );
                        } catch (StopRecordOperationException $exception) {
                            continue;
                        } catch (AbstractException $exception) {
                            $exceptions[$table][$remoteId][$language][$workspace] = $exception;
                        }
                    }
                }
            }
        }

        return $exceptions;
    }
}
