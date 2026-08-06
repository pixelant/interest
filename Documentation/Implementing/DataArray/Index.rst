.. include:: ../../Includes.txt

.. _implementing-data:

=========================
Anatomy of the Data Array
=========================

The data array is the representation of a single record in the database.

..  contents::
    :local:
    :depth: 2

.. _implementing-data-record:

The record data
===============

.. _implementing-data-record-simple:

Simple fields
-------------

Each record's data is represented as an associative array, where the key is a
database field name and the value is the value of the field.

This example has data for two fields, `title` and `subtitle`:

.. code-block:: json

   {
      "title": "1998 - the First Public Appearance",
      "subtitle": "TYPO3 was first presented at IFRA in Lyon, France"
   }

.. _implementing-data-record-relation:

Relation fields
---------------

Fields can also represent relations to one or more other records.

These records are represented by the record's :ref:`remote ID <what-it-does-remote-ids>`,
and never by their UID in the database. If a record wasn't created by this
extension, you will have to create the remote ID manually.

The extension will automatically detect if a field is a relation field and treat
the value as a remote ID.

..  note::
    **No error will be issued if a remote ID does not exist.** Instead, the
    relation will be :ref:`deferred <what-it-does-track-relations-and-defer>`.
    It will be inserted only when the the record on the other side of the
    relation — and its remote ID — is created.

..  note::
    **Missing required relations may lead to :ref:`deferred <what-it-does-track-relations-and-defer>`
    record insert.** If a remote ID is required and doesn't exist, a record that
    depends on it may not be inserted until the required relation is created.
    For example, a subpage will only be created when its parent page has been
    created.

.. _implementing-data-record-single-relation:

Single relation
~~~~~~~~~~~~~~~

In this example the `pid` is set to the remote ID `siteRootPage`:

.. code-block:: json

   {
      "title": "Test Name",
      "pid": "siteRootPage"
   }

.. _implementing-data-record-multi-relation:

Multiple relations
~~~~~~~~~~~~~~~~~~

Multiple relations are defined as an array of remote IDs. The array can only be
numeric and must not be associative.

This example defines relations to three image files:

.. code-block:: json

   {
      "images": [
         "FileReference-1",
         "FileReference-2",
         "FileReference-3"
      ]
   }

..  tip::
    Relations to image files are usually created through relations to the
    `sys_file_reference` table that again reference files in the `sys_file`
    table. You must therefore create both of these records too. See
    :ref:`File Handling <implementing-files>`

..  note::
    The extension tracks the intended order of relations. If `FileReference-2`
    is created before `FileReference-1`, it will maintain the order of the
    relations so `FileReference-1` is listed before `FileReference-2`.

.. _implementing-data-single:

Single-record requests
======================

The simplest requests contain data for just a single record:

.. code-block:: json

   {
      "title": "Test Name",
      "pid": "siteRootPage"
   }

.. _implementing-data-single-rest-reaction:

Example for REST or Reaction requests
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

For a :ref:`REST <implementing-rest>` or :ref:`Reaction <reaction>` request,
this is assigned to the `data` property:

.. code-block:: json

   {
      "data": {
         "title": "Test Name",
         "pid": "siteRootPage"
      }
   }

.. _implementing-data-single-cli:

Example for a CLI command
~~~~~~~~~~~~~~~~~~~~~~~~~

For a :ref:`CLI <implementing-cli>` command, it is what you set using the
`--data` (`-d`) option or pipe:

.. code-block:: bash

   # These commands have identical results.

   typo3 interest:create ... --data='{"title":"Test Name","pid":"siteRootPage"}'

   echo -n '{"title":"Test Name","pid":"siteRootPage"}' | typo3 interest:create ...

.. _implementing-rest-batch:
.. _implementing-data-batch:

Batch requests
==============

`[table]` and `[remoteId]` must be supplied, but can be a part of the `[data]`
array. This makes it possible to supply batch data affecting multiple records
and tables.

Given a request with only `[table]` supplied in the URL:

.. code-block:: text

   http://www.example.org/[endpoint]/[table]

.. _implementing-rest-batch-same-table:
.. _implementing-data-batch-same-table:

Multiple records in the same table
----------------------------------

You can insert or update multiple records within `[table]`. Your data array
could look something like this:

.. code-block:: json

   {
      "Record-1": {
         "title": "My first record",
         "page": ["Page-916"]
      },
      "Record-2": {
         "title": "My second record",
         "page": ["Page-376"]
      }
   }

.. _implementing-data-batch-same-table-rest-reaction:

Example for REST or Reaction requests
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

For a :ref:`REST <implementing-rest>` or :ref:`Reaction <reaction>` request,
this is assigned to the `data` property:

.. code-block:: json

   {
      "data": {
         "Record-1": { ... },
         "Record-2": { ... }
      }
   }

.. _implementing-data-batch-same-table-cli:

Example for a CLI command
~~~~~~~~~~~~~~~~~~~~~~~~~

For a :ref:`CLI <implementing-cli>` command, it is what you set using the
`--data` (`-d`) option or pipe:

.. code-block:: bash

   # These commands have identical results.

   typo3 interest:create ... --data='{"Record-1":{ ... },"Record-2":{ ... }}'

   echo -n '{"Record-1":{ ... },"Record-2":{ ... }}' | typo3 interest:create ...

.. _implementing-rest-batch-multitable:
.. _implementing-data-batch-multitable:

Multiple records in multiple tables
-----------------------------------

You can also leave out the table and insert or update multiple records within
multiple tables:

.. code-block:: json

   "pages": {
      "Page-1": {
         "title": "My first page"
      },
      "Page-2": {
         "title": "My second page"
      }
   },
   "tt_content": {
      "Content-1": {
         "heading": "Welcome to the first page",
         "pid": "Page-1"
      },
      "Content-2": {
         "heading": "Welcome to the second page",
         "pid": "Page-2"
      }
   }

.. _implementing-data-batch-multitable-rest-reaction:

Example for REST or Reaction requests
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

For a :ref:`REST <implementing-rest>` or :ref:`Reaction <reaction>` request,
this is assigned to the `data` property:

.. code-block:: json

   {
      "data": {
         "pages": { ... },
         "tt_content": { ... }
      }
   }

.. _implementing-data-batch-multitable-cli:

Example for a CLI command
~~~~~~~~~~~~~~~~~~~~~~~~~

For a :ref:`CLI <implementing-cli>` command, it is what you set using the
`--data` (`-d`) option or pipe:

.. code-block:: bash

   # These commands have identical results.

   typo3 interest:create ... --data='{"pages":{ ... },"tt_content":{ ... }}'

   echo -n '{"pages":{ ... },"tt_content":{ ... }}' | typo3 interest:create ...

.. _implementing-rest-multilingual:
.. _implementing-data-multilingual:

Multilingual records
--------------------

It is even possible to insert records in multiple languages by adding a language
layer to the data:

.. code-block:: json

   "pages": {
      "Page-1": {
         "en": {
            "title": "My first page"
         },
         "nb": {
            "title": "Min første side"
         }
      },
      "Page-2": {
         "en": {
            "title": "My second page"
         }
      },
   },
   "tt_content": {
      "Content-1": {
         "en": {
            "heading": "Welcome to the first page",
            "pid": "Page-1"
         },
         "nb" {
            "heading": "Velkommen til den første siden",
            "pid": "Page-1"
         }
      },
   }

.. _implementing-data-multilingual-rest-reaction:

Example for REST or Reaction requests
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

For a :ref:`REST <implementing-rest>` or :ref:`Reaction <reaction>` request,
this is assigned to the `data` property:

.. code-block:: json

   {
      "data": {
         "pages": { ... },
         "tt_content": { ... }
      }
   }

.. _implementing-data-multilingual-cli:

Example for a CLI command
~~~~~~~~~~~~~~~~~~~~~~~~~

For a :ref:`CLI <implementing-cli>` command, it is what you set using the
`--data` (`-d`) option or pipe:

.. code-block:: bash

   # These commands have identical results.

   typo3 interest:create ... --data='{"pages":{ ... },"tt_content":{ ... }}'

   echo -n '{"pages":{ ... },"tt_content":{ ... }}' | typo3 interest:create ...
