.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. _conf-tx-solr-view:

tx_solr.view
============

All view related settings, these settings might also be relevant for Fluid.

pluginNamespace
---------------

:Type: String
:TS Path: plugin.tx_solr.view.pluginNamespace
:Since: 7.0
:Default: tx_solr

    Plugin namespace. Can be used to change the plugin namespace and can be changed by instance in the flexform.


templateFiles
-------------

By convention the templates is loaded from EXT:solr/Resources/Private/Templates/Frontend/Search/(ActionName).html.
If you want to define a different entry template, you can do this here to overwrite the conventional default template.
If you want to use FLUID fallbacks you can just configure the template name, otherwise you could also use a full reference EXT:/.../.

The templates that you configure in availableTemplate can be used in the flexform by the editor to select a template for the concrete plugin instance.

templateFiles.results
---------------------

:Type: String
:TS Path: plugin.tx_solr.view.templateFiles.results
:Since: 7.0 (Replaces previous setting plugin.tx_solr.templateFiles.result)
:Default: Results

    By convention the "Results" template from you configured FLUID template path will be used As alternative you can configure a different template name here (e.g. MyResults or a full path to an entry template here).

templateFiles.results.availableTemplates
----------------------------------------

:Type: Array
:TS Path: plugin.tx_solr.view.templateFiles.results.availableTemplates
:Since: 7.0
:Default: none

    Allows to configure templates that are available in the flexform to switch.

    Example:

.. code-block:: typoscript

    plugin.tx_solr.view.templateFiles.results.availableTemplates {
        default {
            label = Default Searchresults Template
            file = Results
        }
        finder {
            label = Productfinder Template
            file = ProductFinder
        }
    }


templateFiles.form
------------------

:Type: String
:TS Path: plugin.tx_solr.view.templateFiles.form
:Since: 7.0 (Replaces previous setting plugin.tx_solr.templateFiles.form)
:Default: Form

    By convention the "Form" template from you configured FLUID template path will be used . As alternative you can configure a different template name here (e.g. MyForm or a full path to an entry template here).

templateFiles.form.availableTemplates
-------------------------------------

:Type: Array
:TS Path: plugin.tx_solr.view.templateFiles.form.availableTemplates
:Since: 7.0
:Default: none

    Allows to configure templates that are available in the flexform to switch.

    Example:

.. code-block:: typoscript

    plugin.tx_solr.view.templateFiles.form.availableTemplates {
        default {
            label = Default Searchform Template
            file = Form
        }
        specialform {
            label = Extended Search Form
            file = BetterForm
        }
    }


templateFiles.frequentSearched
------------------------------

:Type: String
:TS Path: plugin.tx_solr.view.templateFiles.frequentSearched
:Since: 7.0 (Replaces previous setting plugin.tx_solr.templateFiles.frequentSearched)
:Default: FrequentlySearched

    By convention the "FrequentlySearched" template from you configured FLUID template path will be used . As alternative you can configure a different template name here (e.g. FrequentlySearched or a full path to an entry template here).
