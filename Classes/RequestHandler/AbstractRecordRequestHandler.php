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
     * @param array|null $parsedBody Supply parsed JSON body as associative array to avoid parsing JSON twice.
     */
    public function __construct(array $entryPointParts, ServerRequestInterface $request, ?array $parsedBody = null)
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

        $this->compileData($parsedBody);
    }

    /**
     * Correctly compiles the $data and $metaData.
     *
     * @param array|null $parsedBody Supply parsed JSON body as associative array to avoid parsing JSON twice.
     *
     * phpcs:disable Generic.Metrics.CyclomaticComplexity
     */
    protected function compileData(?array $parsedBody = null): void
    {
        if ($parsedBody === null) {
            $parsedBody = $this->parseRequestBody();
        }

        if (!static::EXPECT_EMPTY_REQUEST && $parsedBody === []) {
            return;
        }

        if (is_string($parsedBody['metaData'] ?? null)) {
            $parsedBody['metaData'] = json_decode($parsedBody['metaData'], true, 512, JSON_THROW_ON_ERROR);
        }

        if (is_array($parsedBody['metaData'] ?? null)) {
            $this->metaData = $parsedBody['metaData'];
        }

        if (is_string($parsedBody['data'] ?? null)) {
            $parsedBody['data'] = json_decode($parsedBody['data'], true, 512, JSON_THROW_ON_ERROR);
        }

        $data = $parsedBody['data'] ?? [];

        $table = $this->getEntryPointParts()[0] ?? $parsedBody['table'] ?? null;

        $remoteId = $this->getEntryPointParts()[1] ?? $parsedBody['remoteId'] ?? null;

        $language = $this->getEntryPointParts()[2]
            ?? $parsedBody['language']
            ?? $this->getRequest()->getQueryParams()['language']
            ?? null;

        $workspace = $this->getEntryPointParts()[3]
            ?? $parsedBody['workspace']
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

        if ($operationCount === 0 && count($exceptions) === 0) {
            return GeneralUtility::makeInstance(
                JsonResponse::class,
                [
                    'success' => true,
                    'message' => 'No operations supplied',
                ],
                422
            );
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
     * @param array<string|array> $array
     * @return bool True if the object contains any other value than objects.
     */
    private function isRecordData(array $array): bool
    {
        $item = array_pop($array) ?? '';

        return is_scalar($item) || array_is_list($item);
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
     * @param array $data
     * @param string|null $table
     * @param string|null $remoteId
     * @param string|null $language
     * @param int|null $workspace
     * @return array
     */
    protected function formatDataArray(
        array $data,
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

        if (!$this->isRecordData($data) && $data !== []) {
            $currentLayer = $data;
            do {
                $layerCount++;

                $currentLayer = current((array)$currentLayer);
            } while ($currentLayer !== false && !$this->isRecordData($currentLayer));

            $addDimensions = function (&$item) use (&$addDimensions, $layerCount, $workspace, $language) {
                if (!$this->isRecordData($item)) {
                    array_walk($item, $addDimensions);

                    return;
                }

                if ($layerCount < 4 || $workspace !== null) {
                    $item = [(string)$workspace => $item];
                }

                if ($layerCount < 3 || $language !== null) {
                    $item = [(string)$language => $item];
                }
            };

            $addDimensions($data);
        } else {
            $data = [
                (string)$language => [
                    (string)$workspace => $data,
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

    /**
     * @return array
     */
    protected function parseRequestBody(): array
    {
        $body = '';

        if ($this->getRequest()->getBody() instanceof StreamInterface) {
            $this->getRequest()->getBody()->rewind();

            $body = $this->getRequest()->getBody()->getContents();
        }

        if ($body === '') {
            $parsedBody = [];
        } else {
            $parsedBody = json_decode($body, true, 512, JSON_THROW_ON_ERROR) ?? [];
        }

        return $parsedBody;
    }
}
