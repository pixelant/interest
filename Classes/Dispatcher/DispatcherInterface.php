<?php
declare(strict_types=1);

namespace Pixelant\Interest\Dispatcher;

use Pixelant\Interest\Http\InterestRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 *
 * Interface for the main dispatcher.
 */
interface DispatcherInterface
{
    /**
     * Process the raw request
     *
     * Entry point for the PSR 7 middleware
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function processRequest(ServerRequestInterface $request): ResponseInterface;

    /**
     * Dispatch the request
     *
     * @param InterestRequestInterface $request
     * @return ResponseInterface
     */
    public function dispatch(InterestRequestInterface $request): ResponseInterface;
}
