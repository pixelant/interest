<?php

declare(strict_types=1);

namespace Pixelant\Interest\Reaction;

use Pixelant\Interest\Context;
use Pixelant\Interest\Database\RelationHandlerWithoutReferenceIndex;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Reactions\Model\ReactionInstruction;
use TYPO3\CMS\Reactions\Reaction\ReactionInterface;

class CreateUpdateDeleteReaction implements ReactionInterface
{
    public static function getType(): string
    {
        return 'interest-create-update-delete';
    }

    public static function getDescription(): string
    {
        return 'Interest operation (create, update, or delete)';
    }

    public static function getIconIdentifier(): string
    {
        return 'ext-interest-cud-reaction';
    }

    public function react(
        ServerRequestInterface $request,
        array $payload,
        ReactionInstruction $reaction
    ): ResponseInterface {
        $this->initialize($request);

        $payload = $this->compilePayload($payload);
    }

    /**
     * @param ServerRequestInterface $request
     * @return void
     */
    protected function initialize(ServerRequestInterface $request): void
    {
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
    }

    protected function compilePayload(array $payload): array
    {

    }
}
