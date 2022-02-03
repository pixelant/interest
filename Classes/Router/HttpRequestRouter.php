<?php
declare(strict_types=1);

namespace Pixelant\Interest\Router;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Routes requests to the correct handler and converts exceptions to responses.
 */
class HttpRequestRouter
{
    /**
     * @var string[]
     */
    protected array $entryPointParts;

    /**
     * @param RequestInterface $request
     * @param ExtensionConfiguration $extensionConfiguration
     */
    public function __construct(RequestInterface $request, ExtensionConfiguration $extensionConfiguration = null)
    {
        $extensionConfiguration = $extensionConfiguration
            ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);

        $this->entryPointParts = explode(
            '/',
            substr(
                $request->getRequestTarget(),
                strpos(
                    $request->getRequestTarget(),
                    '/' . trim($extensionConfiguration->get('interest', 'entryPoint'), '/') . '/'
                ) + 1
            )
        );
    }

    /**
     * Route the request to correct handler.
     *
     * @return ResponseInterface
     */
    public static function route(): ResponseInterface
    {

    }

    /**
     * @return string[]
     */
    public function getEntryPointParts()
    {
        return $this->entryPointParts;
    }
}
