=========
Customize
=========


Use custom Fluid Templates
==========================

After these steps solrfluid is usable and using the default Templates, Layouts and Partials. If you want to overwrite them, you can change the TypoScript configuration:

.. code-block:: typoscript

    plugin.tx_solr {
        view {
            layoutRootPaths.10 = EXT:yourpath/Layouts/Frontend/
            partialRootPaths.10 = EXT:yourpath/Partials/Frontend/
            templateRootPaths.10 = EXT:yourpath/Templates/Frontend/
        }
    }

Now you can copy the default partials from the extension to you project path and adapt them to your needs.