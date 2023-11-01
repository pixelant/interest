.. include:: ../../Includes.txt

.. _implementing-cli:

================
Command Line API
================

.. _implementing-cli-authentication:

Authentication
--------------

Operations performed using the CLI endpoints are all authenticated using the
default `_cli_` backend user.

.. _implementing-cli-commonoptions:

Common options
--------------

Some commands share CLI options. These are explained here.

.. confval:: -b | --batch

   :Name: `--batch`
   :Shortcut: `-b`
   :Type: boolean

   Enables batch operations. The `REMOTE_ID` argument will be ignored, but has
   to be set to some string to avoid errors.

   With this option enabled, the data JSON string's first level is an array of
   record data with the key set to the record's remote ID.

   .. code-block:: bash

      typo3 interest:create pages ignoredRemoteId --batch --data='{"newPage1":{"title":"Test Name","pid":"siteRootPage"},"newPage2":{...}}'

.. confval:: -d | --data

   :Name: `--data`
   :Shortcut: `-d`
   :Type: JSON string

   Record data as a JSON string. This string can also be piped in.

   .. code-block:: bash

      # These commands have identical results.

      typo3 interest:create ... --data='{"title":"Test Name","pid":"siteRootPage"}'

      echo -n '{"title":"Test Name","pid":"siteRootPage"}' | typo3 interest:create ...

.. confval:: --disableReferenceIndex

   :Name: `--disableReferenceIndex`
   :Shortcut: *none*
   :Type: Boolean

   Disable updating the reference index during the request. This has a positive
   performance impact. You can (and should) reindex the reference index manually
   afterwards.

   .. code-block:: bash

      typo3 interest:create ... --disableReferenceIndex

.. confval:: -m | --metaData

   :Name: `--metaData`
   :Shortcut: `-m`
   :Type: JSON string

   Meta data for the operation.

   .. code-block:: bash

      # These commands have identical results.

      typo3 interest:create ... --metaData='{"context":"NewZealand"}'

.. _implementing-cli-commands:

Available commands/endpoints
----------------------------

All commands can be executed using TYPO3's default CLI endpoint,
:bash:`typo3 [command]`, e.g. :bash:`typo3 interest:create ...`.

.. _implementing-cli-clearhash:

interest:clearhash
~~~~~~~~~~~~~~~~~~

Interest stores a hash of the data in each operation together with the remote
ID. This command clears this hash. The hash is used to prevent an operation from
being executed repeatedly.

Sometimes, especially in connection with testing, clearing the hash and
re-running the operation makes sense.

.. code-block:: bash

   typo3 interest:clearhash REMOTE_ID [-c|--contains]

.. _implementing-cli-clearhash-options:

Options
^^^^^^^

.. confval:: -c | --contains

   :Name: `--contains`
   :Shortcut: `-c`
   :Type: boolean

   Interpret `REMOTE_ID` as a partial remote ID and match any remote ID
   containing this string.

.. _implementing-cli-create:

interest:create
~~~~~~~~~~~~~~~

Create a record.

.. code-block:: bash

   typo3 interest:create ENDPOINT REMOTE_ID [LANGUAGE [WORKSPACE]] [-u|--update] [-d|--data]  [-m|--metaData] [-b|--batch] [--disableReferenceIndex]

.. _implementing-cli-create-options:

Additional options
^^^^^^^^^^^^^^^^^^

.. confval:: -u | --update

   :Name: `--update`
   :Shortcut: `-u`
   :Type: boolean

   If the record already exists, update it instead.

.. _implementing-cli-delete:

interest:delete
~~~~~~~~~~~~~~~

Delete a record.

.. tip::

   The `REMOTE_ID` argument can be a comma-separated list of remote IDs.

.. code-block:: bash

   typo3 interest:delete REMOTE_ID [LANGUAGE [WORKSPACE]] [--disableReferenceIndex]

.. _implementing-cli-pendingrelations:

interest:pendingrelations
~~~~~~~~~~~~~~~~~~~~~~~~~

View statistics and optionally try to resolve pending relations.

.. code-block:: bash

   typo3 interest:pendingrelations [-r|--resolve]

.. _implementing-cli-pendingrelations-options:

Options
^^^^^^^

.. confval:: -r | --resolve

   :Name: `--resolve`
   :Shortcut: `-r`
   :Type: boolean

   Try to resolve any resolvable pending relations in addition to showing
   statistics.

.. _implementing-cli-update:

interest:update
~~~~~~~~~~~~~~~

Update a record.

.. code-block:: bash

   typo3 interest:update ENDPOINT REMOTE_ID [LANGUAGE [WORKSPACE]] [-c|--create] [-d|--data]  [-m|--metaData] [-b|--batch] [--disableReferenceIndex]

.. _implementing-cli-update-create:

Additional options
^^^^^^^^^^^^^^^^^^

.. confval:: -c | --create

   :Name: `--update`
   :Shortcut: `-u`
   :Type: boolean

   If the record doesn't exist, create it instead.

