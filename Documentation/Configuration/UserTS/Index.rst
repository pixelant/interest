.. include:: ../../Includes.txt

.. _userts:

==========================
Backend User Configuration
==========================

.. _userts-access-mounts:

Access privileges and page mounts
=================================

.. _userts-access-mounts-rest:

REST API
--------

The REST API's write permissions are drawn from the authenticated backend user.
You can configure the user in the TYPO3 backend as any other user. We recommend
using a non-admin user with permissions limited to exactly the tables,
page mounts, and file storage that are required.

Authenticating admin users is also possible and slightly more performant.
However, it also comes with potential security risks.

.. _userts-access-mounts-cli:

CLI commands
------------

The write permissions of the CLI commands is drawn from the default `_cli_`
backend user.

.. _userts-conf:

UserTS Configuration
====================

Since calls to the Interest extension are not happening within a page scope,
extension-specific configurations are made using TypoScript in UserTS. Many
properties support :typoscript:`stdWrap`, so it is possible to configure
advanced data transformations.

.. _userts-accessing-metadata:

Accessing metadata
------------------

Both the CLI commands and REST endpoints support sending metadata alongside with
the record data. The metadata is common to the command or request, and it can
e.g. be used to set storage PID dependent on a criteria.

Here's an example using market metadata to configure the PID:

.. code-block:: typoscript

   tx_interest.persistence {
     storagePid {
       cObject = CASE
       cObject {
         key.data = field : metaData | market

         Denmark = TEXT
         Denmark.value =

         Norway = TEXT
         Norway.value = 1814

         Sweden = TEXT
         Sweden.value =
       }
     }

.. _userts-properties:

UserTS properties
-----------------

UserTS configuration properties are set within `tx_interest` in the UserTS field
of a backend user or backend user group, or within a .userts file.

.. confval:: persistence.hashedSubfolders

   :Required: false
   :Type: int
   :Default: 0

   The number of layers of subfolders to create within
   :typoscript:`fileUploadFolderPath`. Each folder is named using characters
   from a hash of the file name.

   If the file name is :file:`image.jpg` and :typoscript:`hashedSubfolders = 3`,
   the file will be saved in :file:`0/d/5/image.jpg` within
   :typoscript:`fileUploadFolderPath`.

.. confval:: persistence.fileUploadFolderPath

   :Required: true
   :Type: string (combined storage identifier)
   :Default: :typoscript:`1:tx_interest`

   The location where uploaded files will be stored.

.. confval:: persistence.storagePid

   :Required: false
   :Type: int | stdWrap
   :Default:

   Where new records will be stored. Can also be supplied in the record data
   by setting the `pid` field.

.. confval:: relationOverrides.[table].[field]

   :Required: false
   :Type: bool | stdWrap

   If a field *is* or *isn't* wrongly a relation, this is your friend. Override
   whatever is in the TCA and whatever the extension itself thinks. Play God.

.. confval:: relationTypeOverride.[table].[field]

   :Required: false
   :Type: string | stdWrap

   Override the TCA type of the field. E.g.: change "text" to "inline". The
   incoming value is always set to the field's current type.

.. confval:: isSingleRelationOverrides.[table].[field]

   :Required: false
   :Type: string | stdWrap

   Override whether a field supports 1:n or m:n relations. Should be set to
   true (1) if it is a 1:n relation and false (0) if it is a m:n relation.
