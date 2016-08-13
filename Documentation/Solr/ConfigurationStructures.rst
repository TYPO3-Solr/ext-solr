=======================
Configuration Structure
=======================

The configuration can be found in the folder "Resources/Private/Solr".

This folder contains:

* The folder "configsets": This folder a set of configuration files that can be deployed into a solr server, as a template.
It contains the "solrconfig.xml" file, the "schema.xml" files for all languages and the accessfilter libary that belongs to
this version as a jar file. This configSet needs to be in place on a solr server to create cores that are compatible to the EXT:solr
extension.

* The folder "cores": This folder ships an example "core.properties" file for all languages that are compatible with EXT:solr.

A "core.properties" file references a "configSet" that should be used. The path to the schema that is bound to a core is configured as "schema" relative to the root folder of the "configSet".

By example a "core.properties" file looks like this:

|

.. code-block::

configSet=ext_solr_6_0_0
schema=german/schema.xml
name=core_de
dataDir=../../data/german

|

* The solr.xml file: This file configures solr as required for the used Apache Solr version.

===========
Setup steps
===========

With the extension we ship an installer for development and a docker images that can be used to install solr.

When you want to install solr on your system in another way the following steps are required.

* Install the solr server
* Copy the configsets into the configset folder (by default $SOLR_HOME/server/solr/configsets)
* Make sure that the solr.xml file ($SOLR_HOME/server/solr/solr.xml) is in place an fits to your solr version

* Create an init script that start solr on boottime.
* Secure your solr port from outside.
* Make sure that solr is running with an own user.
* Backup your data folders

*Hint:* Apache Solr ships an install script in newer version that might cover your requirements for production
($SOLR_HOME/bin/install_solr_service.sh). We don't use it in EXT:solr because there are currently problems when using it with ubuntu xenial (16.04)


