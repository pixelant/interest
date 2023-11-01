.. include:: ../Includes.txt

.. _reaction:

==================
Webhook (Reaction)
==================

TYPO3 v12 adds the possibility to receive webhooks and react to them with
*reactions* using the `typo3/cms-reactions` core extension. When it is
installed, the Interest extension can use a webhook as a wrapper for a REST
request.

.. _reaction-differences:

Differences between a webhook and REST request
==============================================

The implementation is similar to a normal REST request, except:

* The entry point is always the reaction extension's entry point: `/typo3/reaction/{reactionId}`
* Backend user authentication is handled by the reactions extension.
* The HTTP method of a reaction request must always be POST, so you must instead provide the action in the JSON array key `method` (POST, PUT, PATCH, or DELETE).
* `table`, `remoteId`, `language`, and `workspace` can only be supplied in the JSON array.

.. _reaction-create:

Create an Interest reaction
===========================

To create a new reaction navigate to the :guilabel:`System > Reactions` backend
module. Then click on the button :guilabel:`Create new reaction` to add a new
reaction.

In the form, select the reaction type "Interest operation (create, update, or
delete)" and fill in the other general configuration fields.

Remember to hold on to the content of the :guilabel:`Secret` field. The secret
is necessary to authorize the reaction from the outside. It can be recreated
anytime, but will be visible only once (until the record is saved). Click on the
"dices" button next to the form field to create a random secret. Store the
secret somewhere safe.

When you save and close the form, the reaction will be visible in the list of
reactions.

.. _reaction-request-url:

Request URL
===========

By clicking on the :guilabel:`Example` button in the list of reactions, you'll
see skeleton of a cURL request for use on the command. You can adjust and run it
in the console, using our placeholders as payload:

..  code-block:: bash

    curl -X 'POST' \
        'https://example.com/typo3/reaction/a5cffc58-b69a-42f6-9866-f93ec1ad9dc5' \
          -d '{"method":"PATCH","table":"pages","remoteId":"testPage","data":{"title":"Test Name","pid":"siteRootPage"}}' \
          -H 'accept: application/json' \
          -H 'x-api-key: d9b230d615ac4ab4f6e0841bd4383fa15f222b6b'

.. _reaction-request-data:

Request data
============

Your payload data should always contain at least contain the key `method`.

.. _reaction-request-method:

Specifying the HTTP request header
----------------------------------

Because webhook-based requests always use the `POST` method, it must be
overridden in your data. This means

`method` refers to the HTTP request method that would otherwise have been used
by the REST request. The available values are `POST`, `PUT`, `PATCH`, and
`DELETE`. More information in the :ref:`implementing-rest` section on
:ref:`implementing-rest-methods`.

Example request data
--------------------

The request data can be formatted for both single and batch operations. A
single-record request could look like this:

.. code-block:: json

   {
      "method": "PATCH",
      "table": "pages",
      "remoteId": "testPage",
      "data": {
         "title": "Test Name",
         "pid":"siteRootPage"
      }
   }

A batch request could look like this:

.. code-block:: json

   {
      "method": "POST",
      "data": {
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
      }
   }

You can read more about creating batch requests in the :ref:`implementing-rest`
section on :ref:`implementing-rest-batch`.
