.. include:: ../../Includes.txt

.. _implementing-files:

=============
File Handling
=============

.. note::

   File handling is managed in the EventHandler class :php:`\Pixelant\Interest\DataHandling\Operation\Event\Handler\PersistFileDataEventHandler`.

.. _implementing-files-data-source:

File Data Sources
=================

When writing to the `sys_file` table, the extension introduces two virtual fields:

* **fileData:** Base64-encoded file data.
* **url:** A publicly accessible URL. The file data will be downloaded from this location or handled by an `OnlineMediaHelper <https://docs.typo3.org/typo3cms/extensions/core/Changelog/7.5/Feature-61799-ImprovedHandlingOfOnlineMedia.html>`__ (such as for YouTube and Vimeo URLs, that are turned into `.youtube` and `.vimeo` files).

.. note::

   File uploads are also affected by the :ref:`Handle Existing Files <extension-conf-properties-behavior>` behavior setting in the Extension Configuration.

.. _implementing-files-data-source-filedata:

Base64-encoded file data
------------------------

With the extension's default configuration, this example will create the file at :file:`fileadmin/tx_interest/image.png`.

..  code-block:: json

   {
     "sys_file": {
       "ExampleRemoteId": {
         "fileData": "iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8...",
         "name": "image.png"
       }
     }
   }

The `name` field must be supplied and include a file extension.

.. _implementing-files-data-source-url:

Publicly accessible URL
-----------------------

.. note::

   When used repeatedly on the same remote ID, the extension will look for `If-Modified-Since` and `If-None-Match` (`ETag`) response headers to determine if a file has changed remotely or not. If it has not changed, the file will not be downloaded again. This means it is possible to always supply the URL, but e.g. only rename the file, if that is the only change in the data.

.. _implementing-files-data-source-url-normal:

Normal URL
^^^^^^^^^^

The default behavior is that the file specified in the `url` field is downloaded to the download location specified in the :ref:`persistence.fileUploadFolderPath` UserTS configuration parameter.

With the extension's default configuration, this example will download the file at `https://example.com/image.png` to :file:`fileadmin/tx_interest/image.png`.

..  code-block:: json

   {
     "sys_file": {
       "ExampleRemoteId": {
         "url": "https://example.com/image.png",
         "name": "image.png"
       }
     }
   }

The `name` field must be supplied and include a file extension.

.. _implementing-files-data-source-url-mediahelper:

URL with OnlineMediaHelper
^^^^^^^^^^^^^^^^^^^^^^^^^^

If an `OnlineMediaHelper <https://docs.typo3.org/typo3cms/extensions/core/Changelog/7.5/Feature-61799-ImprovedHandlingOfOnlineMedia.html>`__ is registered for the URL, it will take priority over any other file handling.

With the extension's default configuration, this example will create the file :file:`fileadmin/tx_interest/TYPO3_-_Channel_Intro.youtube`.

..  code-block:: json

   {
     "sys_file": {
       "ExampleRemoteId": {
         "url": "https://youtu.be/zpOVYePk6mM",
       }
     }
   }

If the `name` field is used, the file will use the name supplied there and the correct extension added if necessary (e.g. `.youtube`).

.. _implementing-files-data-source-naming:

File name sanitation
====================

File names are sanitized using TYPO3's standard sanitation methods, so expect spaces to be replaced with underscores, etc.
