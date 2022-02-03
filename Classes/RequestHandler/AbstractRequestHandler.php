<?php

declare(strict_types=1);

namespace Pixelant\Interest\RequestHandler;

use Pixelant\Interest\Router\HttpRequestRouter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract class for handling requests.
 */
abstract class AbstractRequestHandler
{
    /**
     * @var string[]
     */
    protected array $entryPointParts;

    protected RequestInterface $request;

    /**
     * @param array $entryPointParts The remaining parts of the URL, e.g. "/rest/foo/bar" makes `['foo', 'bar']`.
     * @param RequestInterface $request
     */
    public function __construct(
        array $entryPointParts,
        RequestInterface $request
    ) {
        $this->entryPointParts = $entryPointParts;
        $this->request = $request;
    }

    /**
     * Handle the request.
     *
     * @return ResponseInterface
     */
    abstract public function handle(): ResponseInterface;

    /**
     * @return string[]
     */
    public function getEntryPointParts(): array
    {
        return $this->entryPointParts;
    }

    /**
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
