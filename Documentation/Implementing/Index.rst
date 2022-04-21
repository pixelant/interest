.. include:: ../Includes.txt

.. _implementing:

============
Implementing
============

.. toctree::
   :maxdepth: 1
   :titlesonly:
   :glob:

   Cli/Index
   Rest/Index

.. _choosing-rest-or-cli:

Choosing REST or CLI?
=====================

The extension supports both REST and CLI (Command Line Interface) endpoints.
Which one you use depends on your needs, but here's the way we usually keep
them apart:

* Use **REST** when the data source is external and supports REST export.
  `DataCater<https://datacater.io/?utm_source=ext_interest>`__ is an ETA
  (Extract Transform Load) service that integrates well with the Interest
  extension's REST API.
* Use **CLI** when the service sending data to the extension is in the same
  environment. This is good for situations where you need more advanced parsing
  and transformation of input data.

.. _common-properties:

Common Properties
=================

Which-ever implementation you choose, there are a number of common properties
that you will be using:

**[table]**
   The name of the table you are writing records to.

**[remoteId]**
   The record reference. See: :ref:`what-it-does-remote-ids`

**[language]**
   A language as an RFC 1766/3066 string, e.g. `nb` or `sv-SE`. Will be set to
   the default language if not supplied.

**[workspace]**
   *(Not yet implemented. Will be ignored.)*

**[data]**
   The data you would like to write to a record. Compatible with DataHandler,
   but using remote IDs for references.

   .. code-block:: json

      {
         "title": "What are your inteRESTed in?",
         "item_count": 100,
         "images": [
            "Reference-File-543",
            "Reference-File-876"
         ],
         "page": ["Page-916"]
      }

   .. tip::

      Please note that arrays of references can only be numeric arrays!

**[metaData]**
   Additional data, external to the record data, sent with a request. Can be
   used in conditions and transformations. See: :ref:`userts-accessing-metadata`
