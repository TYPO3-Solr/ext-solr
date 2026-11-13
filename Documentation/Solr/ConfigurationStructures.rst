=======================
Configuration Structure
=======================

The configuration can be found in the folder "Resources/Private/Solr".

This folder contains:

* The folder "configsets": This folder a set of configuration files that can be deployed into a Solr server, as a template.

It contains the "solrconfig.xml" file, the "schema.xml" files for all languages and the accessfilter libary that belongs to
this version as a jar file. This configSet needs to be in place on a Solr server to create cores that are compatible to the EXT:solr
extension.

* The folder "cores": This folder ships an example "core.properties" file for all languages that are compatible with EXT:solr.

A "core.properties" file references a "configSet" that should be used. The path to the schema that is bound to a core is configured as "schema" relative to the root folder of the "configSet".

By example a "core.properties" file looks like this:

.. code-block:: bash

    configSet=ext_solr_6_0_0
    schema=german/schema.xml
    name=core_de
    dataDir=../../data/german

* The solr.xml file: This file configures Solr as required for the used Apache Solr version.


The EXT:solr configSet
======================

As mentioned before the configSet is one single package, that contains all to customize a plain Apache Solr Server, to an Apache Solr Server, ready
for EXT:solr & TYPO3.

The configset of the current release is located in "Resources/Private/Solr/configsets/ext_solr_<release>".

Inside the configSet you find the following folders:

* conf: This folder contains the Solr configuration and all schemata files. There is one directory for each language which ships the schema.xml file for this language.

The schema.xml file contains the language specific adaptions and includes all general schema fields and types with an XInclude statement.

.. code-block:: xml

    <!-- xinclude fields -->
    <xi:include href="../general_schema_types.xml" xmlns:xi="http://www.w3.org/2001/XInclude"/>

    <!--  xinclude fields-->
    <xi:include href="../general_schema_fields.xml" xmlns:xi="http://www.w3.org/2001/XInclude"/>

* typo3lib: This folder ships the compiled access filter jar file, that should be used with this EXT:solr version.

The solrconfig (conf/solrconfig.xml) is configured to load all jar files from typo3lib.

===========
Setup steps
===========

With the extension we ship an installer for development and a Docker images that can be used to install Solr.

When you want to install Solr on your system in another way the following steps are required.

* Install the Solr server
* Copy the configsets into the configset folder (by default $SOLR_HOME/server/solr/configsets)
* Make sure that the solr.xml file ($SOLR_HOME/server/solr/solr.xml) is in place and fits to your Solr version

* Create an init script that starts Solr on boottime.
* Secure your Solr port from outside.
* Make sure that Solr is running with an own user.
* Backup your data folders

*Hint:* Apache Solr ships an install script in newer version that might cover your requirements for production
($SOLR_HOME/bin/install_solr_service.sh). We don't use it in EXT:solr because there are currently problems when using it with Ubuntu Xenial (16.04)

=========
SolrCloud
=========

EXT:solr also works with Solr running in SolrCloud mode, which is the default since Apache Solr 10. Instead of cores,
create one collection per language, named like the core it replaces (e.g. ``core_en``), from the EXT:solr configSet.
Pass the language schema as core property when creating the collection, e.g.
``property.schema=english/schema.xml``.

Adapt the configSet once before uploading it to ZooKeeper. The stop word and synonym lists are identified by the
core name, set by ``managed="${solr.core.name}"`` in the schemata. In SolrCloud, every replica is a separate core
with its own name, e.g. ``core_en_shard1_replica_n1``. Without the change:

* every replica gets its own lists
* changes made in the TYPO3 backend only reach one replica
* the shipped default stop words are not loaded

The following commands identify the lists by the collection name instead:

.. code-block:: bash

    cp -r Resources/Private/Solr/configsets/ext_solr_<release> /tmp/ext_solr_<release>
    find /tmp/ext_solr_<release>/conf -name schema.xml \
      -exec sed -i 's/managed="${solr.core.name}"/managed="${solr.core.collection}"/g' {} +
    bin/solr zk upconfig --conf-name ext_solr_<release> --conf-dir /tmp/ext_solr_<release> --zk-host <zookeeper>

Do not use the adapted configSet in user-managed (standalone) mode, because ``solr.core.collection`` is not defined there.

The configSet in ZooKeeper is shared by all Solr nodes, but two parts of the EXT:solr setup are read from the local
disk of each node. Copy both from ``Resources/Private/Solr/`` to the Solr home directory of **every** Solr node. The
Solr home is ``/var/solr/data`` for the official Docker image and the Solr service installer; it is set by
``SOLR_HOME``.

* ``typo3lib/``: contains the access filter plugin (jar file). The configSet registers it as query parser
  ``typo3access`` in ``solrconfig.xml``. Without the jar, the collections cannot be loaded on that node.
* ``solr.xml``: loads the jar files from ``typo3lib/`` (``sharedLib``), enables the Solr modules used by the
  configSet and contains the ``<solrcloud>`` section, which Solr requires in SolrCloud mode. Since Apache Solr 9,
  ``solr.xml`` is not read from ZooKeeper anymore.

Restart the Solr nodes after copying the files. Repeat these steps on every new node before adding it to the cluster,
and on all nodes when updating EXT:solr to a release with a new configSet.
