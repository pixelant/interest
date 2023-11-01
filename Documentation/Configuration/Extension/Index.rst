.. include:: ../../Includes.txt

.. _extension-conf:

=======================
Extension Configuration
=======================

The extension configuration is global and affects the Interest extension across
the entire TYPO3 instance.

You can edit these configurations by:

* Going to the *Settings* module in the TYPO3 Backend and clicking *Extension*
  *Configuration*. Scroll down until you find *interest* and click it to open
  the editing form.
* By setting environment variables.

.. _extension-conf-properties:

Properties
==========

.. _extension-conf-properties-rest:

REST
----

.. confval:: URL Entry Point

   :Required: true
   :Type: string
   :Default: rest
   :Key: `entryPoint`
   :Environment variable: `APP_INTEREST_ENTRY_POINT`

   If you would like to make REST calls to `https://example.org/entrypoint/...`,
   the value here should be set to "entrypoint".

.. confval:: Token lifetime

   :Required: false
   :Type: int
   :Default: 86400
   :Key: `tokenLifetime`
   :Environment variable: `APP_INTEREST_TOKEN_TTL`

   The authentication token's lifetime in seconds. Zero means no expiry.

.. _extension-conf-properties-behavior:

Behavior
--------

.. confval:: Handle Empty Files

   :Required: true
   :Type: int
   :Default: 0
   :Key: `handleEmptyFile`

   How to handle files that are empty. Available options are:

   **0**
      Treat as any other file. You can also use this option if you want to
      handle empty files with a custom
      :php:`Pixelant\Interest\DataHandling\Operation\Event\BeforeRecordOperationEvent`.
      Just make sure your EventHandler it is executed after
      :php:`\Pixelant\Interest\DataHandling\Operation\Event\Handler\PersistFileDataEventHandler`.
   **1**
      Stop processing the record. This might result in pending relation records
      that will never be resolved in the database, but if that's OK for you it
      won't cause any issues.
   **2**
      Fail. The operation will be treated as failed and returns an error.

.. confval:: Handle Existing Files

   :Required: true
   :Type: string from :php:`TYPO3\CMS\Core\Resource\DuplicationBehavior`
   :Default: cancel
   :Key: `handleExistingFile`

   How to handle files that already exist in the filesystem. Uses the same
   configuration options as :php:`TYPO3\CMS\Core\Resource\DuplicationBehavior`:

   **cancel**
      Fail with exception.
   **rename**
      Rename the new file.
   **replace**
      Replace the existing file.

.. _extension-conf-properties-log:

Log
---

Logging of REST calls, including request and response data and execution time.

.. confval:: Enable logging

   :Required: false
   :Type: int
   :Default: 0
   :Key: `log`
   :Environment variable: `APP_INTEREST_LOG`

   Enable logging and specify where to log. Available values:

   **0**
      Disabled. No logging.
   **1**
      Log in response headers
   **2**
      Log in database.
   **3**
      Log in both response headers and database.

.. confval:: Logging threshold

   :Required: false
   :Type: int
   :Default: 0
   :Key: `logMs`
   :Environment variable: `APP_INTEREST_LOGMS`

   The execution time in milliseconds above which logging is enabled.
