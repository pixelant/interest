.. include:: ../../Includes.txt

.. _extending:

======================
Changing and Extending
======================

If you need additional functionality or the extisting functionality of the extension isn't quite what you need, this section tells you how to change the behavior of the Interest extesnsion. It also tells you how to extend the functionality, as well as a bit about the extension's inner workings.

.. _extending-events:

PSR-14 Events
=============

The events are listed in order of execution.

.. _extending-beforerecordoperationevent:

`BeforeRecordOperationEvent`
----------------------------

..  php:namespace::  Pixelant\Interest\DataHandling\Operation\Event

..  php:class:: BeforeRecordOperationEvent

    Datetime class

EventHandler Interface:
   :php:`\Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEventHandlerInterface`

Executed when:
   When a :php:`*RecordOperation` object has been initialized, but before data validations. (Actually inside the :php:`AbstractRecordOperation::__construct()`.)

.. _extending-how-works:

How it works
============


