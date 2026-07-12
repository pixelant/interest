.. include:: ../../Includes.txt

.. _implementing-rest:

========
REST API
========

..  contents::
    :local:
    :depth: 2

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

..  versionadded:: 10.2
    Since version 4.1, requests can be made with `basic` or `basic`
    authentication. Previously, `basic` authentication was only available when
    retrieving a `bearer` token through the `authenticate` endpoint.

.. _implementing-rest-authentication-security-performance:

Security and performance considerations
---------------------------------------

`basic` authentication is *not as secure* and less performant than bearer
authentication. Because it requires you to encode and transmit your backend
username and password with every request, it offers inferior security.

`bearer` authentication is a token-based system where you first retrieve a
temporary token and then use only this token for subsequent requests.

.. _implementing-rest-authentication-basic:

Scheme: `basic`
---------------

.. _implementing-rest-authentication-basic-generating:

Generating your authentication string
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The `basic` HTTP authentication schema, uses a Base64-encoded string consisting
of a backend username and the corresponding password, separated by a colon: `:`.

Given the username "testuser" and password "test1234", the concatenated string
will be "testuser:test1234" and the Base64-encoded version:
"dGVzdHVzZXI6dGVzdDEyMzQ=".

You can base64-encode a string by using built-in terminal commands:

..  code-block:: bash

    # Will output: dGVzdHVzZXI6dGVzdDEyMzQ=
    echo -n "testuser:test1234" | base64

..  warning::
    Using online tools for encoding your password is not advisable!

.. _implementing-rest-authentication-basic-using:

Using `basic` authentication in a request
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Authentication is done using the `Authorization` HTTP header.

.. code-block:: bash

   curl -XPOST \
        -H 'Authorization: basic dGVzdHVzZXI6dGVzdDEyMzQ=' \
        -v 'https://example.org/rest/pages/testPage' \
        -d '{"data":{"title":"Test Name","pid":"siteRootPage"}}'

The request will return a JSON response body:

.. code-block:: json

   {"success":true, ...}

.. _implementing-rest-authentication-basic-apache:

Fixing Apache and the authorization HTTP header
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

When using Apache there is a need to add the following to .htaccess

.. code-block:: bash

   RewriteEngine On
   RewriteCond %{HTTP:Authorization} ^(.*)
   RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]

.. _implementing-rest-authentication-bearer:

Scheme: bearer (OAuth)
----------------------

.. _implementing-rest-authentication-bearer-token:

Retrieving an authentication token
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

When you use the `bearer` HTTP authentication scheme, you must first retrieve
an authentication token through the `authenticate` endpoint
using :ref:`\`basic\` authentication <implementing-rest-authentication-basic>`.

.. code-block:: bash

   curl -XPOST \
        -H 'Authorization: basic dGVzdHVzZXI6dGVzdDEyMzQ=' \
        -v 'https://example.org/rest/authenticate'

.. note::
   `authenticate` is a special endpoint used when retrieving a token. For
   obvious reasons, it only supports `basic` authentication.

This request will return a JSON response body including a token that can be
used on subsequent requests:

.. code-block:: json

   {"success":true,"token":"f3c0946fb05aae4ad50897e9060ab4e8"}

.. _implementing-rest-authentication-bearer-authentication:

Authenticating a request with a bearer token
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

You supply the `bearer` token using the `Authorization` HTTP header in your
request:

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

.. confval:: Interest-Disable-Reference-Index

   :Required: false
   :Type: Boolean

   Disable updating the reference index during the request. This has a positive
   performance impact. You can (and should) reindex the reference index manually
   afterwards.
