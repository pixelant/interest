<?php

declare(strict_types=1);

namespace Pixelant\Interest;

use Pixelant\Interest\Http\InterestRequestInterface;
use Psr\Http\Message\ServerRequestInterface;

interface RequestFactoryInterface
{
    /**
     * Return request.
     *
     * @return InterestRequestInterface
     */
    public function getRequest(): InterestRequestInterface;

    /**
     * Resets current request.
     *
     * @return $this
     */
    public function resetRequest(): self;

    /**
     * Register/overwrite current request.
     *
     * @param ServerRequestInterface $request
     * @return $this
     */
    public function registerCurrentRequest(ServerRequestInterface $request): self;
}
