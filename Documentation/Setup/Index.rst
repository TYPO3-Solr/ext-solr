.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _setup-solr:

Install EXT:solr and Apache Solr
================================

.. contents::
   :local:

Prerequisites
-------------

* TYPO3 version 6.2 or newer
* Java version 7 or newer
* wget


Install EXT:solr
----------------

Install from TER using the TYPO3 Extension Manager
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

You can simply install stable versions of EXT:solr using the Extension Manager
from the TYPO3 backend.

#. Go to the **Extension Manager**, select **Get Extensions** and search for
   "solr".
#. Install the Extension.
#. The Extension Manager will also install EXT:scheduler if not installed already
   for running the indexing tasks
#. While developing we recommend installing devlog for easier error detection, too.

Install from git
^^^^^^^^^^^^^^^^

Alternatively, you can also get the latest development version from GitHub:

.. code-block:: bash

    $ git clone git@github.com:TYPO3-Solr/ext-solr.git solr


Install Apache Tomcat and Apache Solr
-------------------------------------

Please make sure to use a current Java SDK (JDK). We recommend using Oracle JDK.

We have included an install script to automatically set up Tomcat and Solr. You
can find it in EXT:solr/Resources/Install/install-solr-tomcat.sh.

That shell script will do a full setup, downloading a recent version of Apache
Tomcat and Apache Solr in a version as required by EXT:solr. The script installs
Tomcat and Solr into ``/opt/solr-tomcat/`` and when done starts Tomcat.

Install Solr with an english core:

.. code-block:: bash

    $ sudo ./install-solr-tomcat.sh

Install Solr with additional languages - simply list them separated with space

.. code-block:: bash

    $ sudo ./install-solr-tomcat.sh english german french

This will download schema configuration files for english, german, and french.
You still need to add the cores in ``/opt/solr-tomcat/solr/solr.xml``. An
english core is already configured, you can simply copy the configuration and
adapt the paths for the ``schema`` and ``dataDir`` attributes.

.. figure:: ../Images/Setup/install-script.png

    Install script output (shortened).
