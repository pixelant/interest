<?php

declare(strict_types=1);

namespace Pixelant\Interest\Reaction;

use Pixelant\Interest\DataHandling\Operation\Exception\AbstractException;
use Pixelant\Interest\RequestHandler\CreateOrUpdateRequestHandler;
use Pixelant\Interest\RequestHandler\CreateRequestHandler;
use Pixelant\Interest\RequestHandler\DeleteRequestHandler;
use Pixelant\Interest\RequestHandler\ExceptionConverter\OperationToRequestHandlerExceptionConverter;
use Pixelant\Interest\RequestHandler\UpdateRequestHandler;
use Pixelant\Interest\Router\Event\HttpRequestRouterHandleByEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reactions\Model\ReactionInstruction;
use TYPO3\CMS\Reactions\Reaction\ReactionInterface;

// @phpstan-ignore interface.notFound
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

    // phpcs:disable Squiz.Commenting.FunctionCommentThrowTag
    // @phpstan-ignore class.notFound
    public function react(
        ServerRequestInterface $request,
        array $payload,
        ReactionInstruction $reaction
    ): ResponseInterface {
        if (($payload['method'] ?? '') === '') {
            return GeneralUtility::makeInstance(
                JsonResponse::class,
                [
                    'success' => false,
                    'message' => 'No method provided',
                ],
                405
            );
        }

        $event = GeneralUtility::makeInstance(EventDispatcher::class)->dispatch(
            new HttpRequestRouterHandleByEvent((clone $request)->withMethod(strtoupper($payload['method'])), [])
        );

        try {
            switch (strtoupper($event->getRequest()->getMethod())) {
                case 'POST':
                    return GeneralUtility::makeInstance(
                        CreateRequestHandler::class,
                        $event->getEntryPointParts(),
                        $event->getRequest(),
                        $payload
                    )->handle();
                case 'PUT':
                    return GeneralUtility::makeInstance(
                        UpdateRequestHandler::class,
                        $event->getEntryPointParts(),
                        $event->getRequest(),
                        $payload
                    )->handle();
                case 'PATCH':
                    return GeneralUtility::makeInstance(
                        CreateOrUpdateRequestHandler::class,
                        $event->getEntryPointParts(),
                        $event->getRequest(),
                        $payload
                    )->handle();
                case 'DELETE':
                    return GeneralUtility::makeInstance(
                        DeleteRequestHandler::class,
                        $event->getEntryPointParts(),
                        $event->getRequest(),
                        $payload
                    )->handle();
            }
        } catch (AbstractException $dataHandlingException) {
            throw OperationToRequestHandlerExceptionConverter::convert($dataHandlingException, $request);
        }

        return GeneralUtility::makeInstance(
            JsonResponse::class,
            [
                'success' => false,
                'message' => 'Method not allowed.',
            ],
            405
        );
    }
}
