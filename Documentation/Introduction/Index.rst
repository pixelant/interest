.. include:: ../Includes.txt

.. _introduction:

============
Introduction
============

.. _why-call-it-interest:

Why is it called Interest?
==========================

"Interest" is a portmanteau of the words "integration" and "REST [API]".

.. _what-it-does:

What does it do?
================

.. _what-it-does-cli-and-rest:

A CLI and REST frontend to TYPO3's DataHandler
----------------------------------------------

This is an import extension that provides CLI and REST endpoints for creating,
updating, and deleting records in TYPO3. It uses TYPO3's DataHandler API, so
data is inserted as if you submitted a form in the Backend. This means changes
are visible in the record history, and you can even revert to previous versions.

.. _what-it-does-permissions:

Backend user permissions
------------------------

REST calls authenticate as a backend user. That user's permissions are the
permissions of the Interest extension. This means the REST calls can only
create the records the backend user has permission to do. The extension uses
TYPO3's own backend permission checking functionality.

.. _what-it-does-remote-ids:

Remote ID mapping
-----------------

During an import, you don't want to have to keep track of what UID each new
record receives in the TYPO3 database, and it might be different between your
development, staging, and live environments anyway. Sometimes one record in the
import source can become multiple records in TYPO3.

The interest extension keeps track of the UIDs for you and maps them to whatever
name (aka. a "remote ID") your data source gives it. The remote ID can be any
string, not just a number. That makes debugging much easier!

Some remote ID examples:

* `News-123`
* `NewsLink-123`
* `myCoolImage.jpg`
* `Hash-ab3235fdab0a`

You can even manually create remote IDs for records in TYPO3, so the remote ID
`ContactPage` always represents the UID of the Contact Us page. Another example
is files that are uploaded to TYPO3 manually: You can use the file name and
parts of the path as a remote ID and common reference point.

.. _what-it-does-track-relations-and-defer:

Send data in any order: Relation tracking and insert deferral
-------------------------------------------------------------

Import data often out of order. You have to insert the parent page before you
insert the children.

The Interest extension makes it possible to insert data in any order. Yes, you
can send over the data of the child pages before the parent is present.

It does this with relation tracking and insert deferral:

* **Relation tracking:** Interest keeps track of a record's relations.
  Especially the records that have not yet been created. Once one of the missing
  related records are created, the extension will make sure that the relations
  are set up. It will even track the intended order of relations: If record 7 is
  created before record 4, it will insert the new record before the other.
* **Insert deferral:** Some records are not useful or create errors if they
  exist without one or more of their relations. Interest can store the data
  away and wait with inserting it until the relation is created. By default
  :sql:`sys_file_reference` records are deferred until the related
  :sql:`sys_file` is created. The extension provides an API for registering more
  insert deferrals.

.. _what-it-does-file-upload:

Flexible file uploading
-----------------------

Files can be created based on an URL, Base64-encoded data, or a URL to a
MediaHandler-compatible website, such as YouTube and Vimeo.

You can even make relations to files e.g. on an Amazon S3 or Microsoft Azure
mount within TYPO3: It is easy to give them remote IDs based on their ID in the
external storage system.

The extension also supports putting files in any number of subfolders based on
a hash of the filename. E.g.: :file:`interest_files/a/0/image.jpg`

.. _what-it-does-transformations:

Data transformations
--------------------

All record data values can be modified using PHP and `stdWrap`. Each call
to an endpoint can also include meta data that is not saved to the database,
but can be used to make conditional decisions, such as deciding which PID a
record should go into depending on some otherwise unused value in the source
data.

.. _what-it-does-optimizations:

Optimizations
-------------

Interest includes a number of optimizations that will make it easier for you
to maintain a data import with few changes:

* The same **operation will not be repeated twice** in a row. The extension hashes
  the data of the record operation and checks it.
* **Files are not downloaded again**, unless the file has changed. The extension
  tracks modification date and ETag headers.
* **Disable reference indexing.** Updating the reference index is time consuming
  when you are writing a lot of data. You can disable it during import and
  and update the index afterwards.
* For TYPO3 v9, the CLI module also **disables registering of Extbase commands**
  to greatly improve performance.

.. _what-it-does-not-do:

What doesn't it do?
===================

.. _what-it-does-not-do-performance:

High Performance
----------------

This extension has not been made to make imports faster, only better. Using
TYPO3's backend APIs will always be slower than a list of SQL commands. On the
other side, Interest tries to ensure that the date will always be as TYPO3
expects it, using core APIs whenever possible. And you can run it in multiple
threads.

.. _what-it-does-not-do-read:

Read operations
---------------

Read operations are currently not supported. It is a question how helpful it
would be. In our experience, an import scenario where you need to use the
Interest extension to read out data is not well designed. You should be able to
trust that the data you are sending to Interest is correct: Check the data
beforehand.

The only exception to this is the touch timestamps that are attached to each
remote ID mapping. They can be used to see when a remote ID was touched last
(i.e. when Interest was sent an operation directed towards it, even if no data
was written in the end). It can be used to track when your data source last
mentioned a particular remote ID to the Interest extension, for example to
know which records to delete because they are no longer mentioned in your data
source.

.. _what-it-does-not-do-frontend:

Frontend operations
-------------------

Just like the TYPO3 Backend, the Interest extension is not suited as your
website. It is an admin tool.
