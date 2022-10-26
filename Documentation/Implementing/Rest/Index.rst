.. include:: ../../Includes.txt

.. _implementing-rest:

========
REST API
========

.. _implementing-rest-basic:

Basic request URL
=================

Requests to the REST API usually follows a simple pattern, where all of the
parts, except from the endpoint, are optional or can be supplied elsewhere:

.. code-block:: text

   http://www.example.org/[endpoint]/[table]/[remoteId]/[language]/[workspace]

Example with values added:

.. code-block:: text

   http://www.example.org/rest/tt_content/Content-531/nb-NO/0

.. _implementing-rest-optional-parts:

Optional URL parts
==================

`[language]` and `[workspace]` are fully optional and can be left out entirely:

.. code-block:: text

   http://www.example.org/[endpoint]/[table]/[remoteId]

They can also be supplied in the query string:

.. code-block:: text

   http://www.example.org/[endpoint]/[table]/[remoteId]?language=[language]&workspace=[workspace]

.. _implementing-rest-authentication:

Authentication
==============

.. _implementing-rest-authentication-basic:

Scheme: basic
-------------

Initial authentication is done using the `basic` HTTP authentication schema,
where a Base64-encoded string is submitted to the server. The string is a TYPO3
backend username and the corresponding password, separated by a colon: `:`.

Given the username "testuser" and password "test1234", the concatenated string
will be "testuser:test1234" and the Base64-encoded version:
"dGVzdHVzZXI6dGVzdDEyMzQ=".

.. code-block:: bash

   curl -XPOST \
        -H 'Authorization: basic dGVzdHVzZXI6dGVzdDEyMzQ=' \
        -v 'https://example.org/rest/authenticate'

.. info::

   `authenticate` is a special endpoint used only for basic authentication
   requests.

This request will return a JSON response body including a token that can be
used on subsequent requests:

.. code-block:: json

   {"success":true,"token":"f3c0946fb05aae4ad50897e9060ab4e8"}

.. _implementing-rest-authentication-bearer:

Scheme: bearer (OAuth)
----------------------

For any other request, you must use `bearer` HTTP authentication scheme,
supplying an authentication token, such as the one supplied by the `basic`
authentication request mentioned above:

.. code-block:: bash

   curl -XPOST \
        -H 'Authorization: bearer f3c0946fb05aae4ad50897e9060ab4e8' \
        -v 'https://example.org/rest/pages/testPage' \
        -d '{"data":{"title":"Test Name","pid":"siteRootPage"}}'

.. _implementing-rest-batch:

Batch requests
==============

`[table]` and `[remoteId]` must be supplied, but can be a part of the `[data]`
array. This makes it possible to supply batch data affecting multiple records
and tables.

Given a request with only `[table]` supplied in the URL:

.. code-block:: text

   http://www.example.org/[endpoint]/[table]

.. _implementing-rest-batch-same-table:

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

.. _implementing-rest-batch-multitable:

Multiple records in multiple tables
-----------------------------------

You can also leave out the table and insert or update multiple records within
multiple tables:

.. code-block:: json

   "pages": {
      "Page-1": {
         "title": "My first page",
      },
      "Page-2": {
         "title": "My second page",
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

.. _implementing-rest-multilingual:

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

.. _implementing-rest-methods:

HTTP Methods
============

.. _implementing-rest-methods-post:

POST
----

Create a record.

.. _implementing-rest-methods-put:

PUT
---

Update a record.

.. _implementing-rest-methods-patch:

PATCH
-----

Update a record if it exists, otherwise create it.

.. _implementing-rest-methods-delete:

DELETE
------

Delete a record.

.. code-block:: bash

   curl -XDELETE \
        -H 'Authorization: bearer f3c0946fb05aae4ad50897e9060ab4e8' \
        -v 'https://example.org/rest/pages/testPage'


Optional HTTP Headers
=====================

Disable updating the reference index during the request. This has a positive
performance impact. You can (and should) reindex the reference index manually
afterwards.

.. confval:: Interest-Disable-Reference-Index

   :Required: false
   :Type: Boolean
