.. include:: ../../Includes.txt

.. _extending:

======================
Changing and Extending
======================

If you need additional functionality or the extisting functionality of the extension isn't quite what you need, this section tells you how to change the behavior of the Interest extesnsion. It also tells you how to extend the functionality, as well as a bit about the extension's inner workings.

.. _extending-events:

PSR-14 Events
=============

.. _extending-events-list:

Events
------

The events are listed in order of execution.

.. php:namespace:: Pixelant\Interest\Router\Event

.. php:class:: HttpRequestRouterHandleByEvent

   Called in :php:`HttpRequestRouter::handleByMethod()`. Can be used to modify the request and entry point parts before they are passed on to a RequestHandler.

   EventHandlers for this event should implement :php:`Pixelant\Interest\Router\Event\HttpRequestRouterHandleByEventHandlerInterface`.

   .. php:method:: getEntryPointParts()

      Returns an array of the entry point parts, i.e. the parts of the URL used to detect the correct entry point. Given the URL `http://www.example.com/rest/tt_content/ContentRemoteId` and the default entry point `rest`, the entry point parts will be :php:`['tt_content', 'ContentRemoteId']`.

      :returntype: array

   .. php:method:: setEntryPointParts($entryPointParts)

      :param array $entryPointParts:

   .. php:method:: getRequest()

      :returntype: Psr\Http\Message\ServerRequestInterface

   .. php:method:: setRequest($request)

      :param Psr\Http\Message\ServerRequestInterface $request:

.. php:namespace::  Pixelant\Interest\DataHandling\Operation\Event

.. php:class:: BeforeRecordOperationEvent

   Called inside the :php:`AbstractRecordOperation::__construct()` when a :php:`*RecordOperation` object has been initialized, but before data validations.

   EventHandlers for this event should implement :php:`Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEventHandlerInterface`.

   EventHandlers for this event can throw these exceptions:

   :php:`Pixelant\Interest\DataHandling\Operation\Event\Exception\StopRecordOperationException`
      To quietly stop the record operation. This exception is only logged as informational and the operation will be treated as successful. E.g. used when deferring an operation.

   :php:`Pixelant\Interest\DataHandling\Operation\Event\Exception\BeforeRecordOperationEventException`
      Will stop the record operation and log as an error. The operation will be treated as unsuccessful.

   .. php:method:: getRecordOperation()

      :returntype: Pixelant\Interest\DataHandling\Operation\AbstractRecordOperation

.. php:namespace::  Pixelant\Interest\DataHandling\Operation\Event

.. php:class:: AfterRecordOperationEvent

   Called as the last thing inside the :php:`AbstractRecordOperation::__invoke()` method, after all data persistence and pending relations have been resolved.

   EventHandlers for this event should implement :php:`Pixelant\Interest\DataHandling\Operation\Event\AfterRecordOperationEventHandlerInterface`.

   .. php:method:: getRecordOperation()

      :returntype: Pixelant\Interest\DataHandling\Operation\AbstractRecordOperation

.. php:namespace::  Pixelant\Interest\Middleware\Event

.. php:class:: HttpResponseEvent

   Called in the middleware, just before control is handled back over to TYPO3 during an HTTP request. Allows modification of the response object.

   EventHandlers for this event should implement :php:`Pixelant\Interest\Middleware\Event\HttpResponseEventHandlerInterface`.

   .. php:method:: getResponse()

      :returntype: Psr\Http\Message\ResponseInterface

   .. php:method:: setResponse($response)

      :param Psr\Http\Message\ResponseInterface $response:

.. _extending-events-typo3v9

In TYPO3 version 9
------------------

TYPO3 version 9 doesn't support PSR-14 events, but it's using signals and slots instead. Luckily, PSR-14 Events and EventHandlers can be made to work with them as well. You can register an EventHandler as a SignalSlot using this convenience function:

.. code-block:: php

   \Pixelant\Interest\Utility\CompatibilityUtility::registerEventHandlerAsSignalSlot(
       string $eventClassName,
       string $eventHandlerClassName
   );

It will map the class and methods correctly. The only difference is that you can't change the order of execution, as the slots are called in the order they are registered.

Here's how the function is used by the Interest extension itself:

.. code-block:: php

   \Pixelant\Interest\Utility\CompatibilityUtility::registerEventHandlerAsSignalSlot(
       \Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent::class,
       \Pixelant\Interest\DataHandling\Operation\Event\Handler\StopIfRepeatingPreviousRecordOperation::class
   );

.. _extending-how-works:

PHP API
=======


