<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler;

use Pixelant\Interest\Context;
use Pixelant\Interest\Database\RelationHandlerWithoutReferenceIndex;
use Pixelant\Interest\DataHandling\Operation\Exception\AbstractException;
use Pixelant\Interest\RequestHandler\ExceptionConverter\OperationToRequestHandlerExceptionConverter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\ArrayUtility;
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

        $data = $this->formatDataArray($data, $table, $remoteId, $language, $workspace);

        $this->data = $data;
    }

    /**
     * @return ResponseInterface
     * phpcs:disable Squiz.Commenting.FunctionCommentThrowTag
     */
    public function handle(): ResponseInterface
    {
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

        if ($operationCount === 1 && count($exceptions)) {
            throw OperationToRequestHandlerExceptionConverter::convert(
                current(ArrayUtility::flattenPlain($exceptions)),
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
     * @param string $table
     * @param string $remoteId
     * @param string $language
     * @param string $workspace
     */
    abstract protected function handleSingleOperation(
        string $table,
        string $remoteId,
        string $language,
        string $workspace,
        array $data
    ): void;

    /**
     * @param object $object
     * @return bool True if the object contains any other value than objects.
     */
    private function isRecordData(object $object): bool
    {
        $array = (array)$object;

        return !is_object($array[array_key_first($array)]);
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
            if (!is_array($valueCopy[array_key_first($valueCopy)]) || is_object($value)) {
                $value = (array)$value;
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

        if (!$this->isRecordData($data)) {
            $currentLayer = $data;
            do {
                $layerCount++;

                $currentLayer = next($currentLayer);
            } while (!$this->isRecordData($currentLayer));

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
                            $this->handleSingleOperation($table, $remoteId, $language, $workspace, $data);
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
