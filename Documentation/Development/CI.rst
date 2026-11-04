=================================
Testing and Continues Integration
=================================

The goal during the development of EXT:solr is, to test most of the components with unit and integration tests.

Unit Tests
==========

For the single classes we've added unit tests whenever we though it is usefull. The unit tests should run very quickly, to git a quick response.

Integration Tests
=================

As in EXT:solr the integration tests are more complex and test the integration of the different components. Since a database server and a Solr server is required this is needed to run the integration test suite. During the bootstrap of the test environment, we use the TYPO3 core functionality for database tests and we install a local Solr server with out install script.

To simplify the local usage of the unit and integration tests, we ship a few bash script that support you to get everything started.

Bootstrapping the Test Environment
==================================

When you want to start the testrunner in your shell you need to bootstrap it once:

.. code-block:: bash

    source ./Build/Test/bootstrap.sh --local


The bootstrapper will prompt for some values:

.. image:: /Images/Development/ci_bootstrap.png


When the boostrapper was finished successful the following was done:
    * Environment variables for the TYPO3 testing framework have been exported
    * Test database was created
    * Test Solr instance was created

Afterwards you can run the ci suite in your shell


Running the ci Suite
====================

When the test environment was boostrapped correctly you can run the same checks as the CI does:

.. code-block:: bash

    composer tests:lint-php
    composer t3:standards:fix -- --diff --dry-run
    composer tests:lint-xml
    composer tests:phpstan
    composer tests:known-tests
    composer tests:unit
    typo3DatabaseName=$TYPO3_DATABASE_NAME typo3DatabaseHost=$TYPO3_DATABASE_HOST \
      typo3DatabaseUsername=$TYPO3_DATABASE_USERNAME typo3DatabasePassword=$TYPO3_DATABASE_PASSWORD \
      composer tests:integration

The integration tests need the database settings in the lowerCamelCase names the TYPO3 testing framework reads.

