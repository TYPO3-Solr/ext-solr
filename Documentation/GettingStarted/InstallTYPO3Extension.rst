.. highlight:: bash


.. _started-install-extension:

Install EXT:solr
----------------

Install from TER using the TYPO3 Extension Manager
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

You can simply install stable versions of EXT:solr using the Extension Manager
from the TYPO3 backend.

#. Go to the **Extension Manager**, select **Get Extensions** and search for
   "solr".
#. Install the extension.
#. The Extension Manager will also install EXT:scheduler if not installed already
   for running the indexing tasks
#. While developing we recommend installing devlog for easier error detection, too.


Install from git
^^^^^^^^^^^^^^^^

Alternatively, you can also get the latest development version from GitHub:


.. code-block:: bash

    $ git clone git@github.com:TYPO3-Solr/ext-solr.git solr


Install with composer
^^^^^^^^^^^^^^^^^^^^^

Install this TYPO3 Extension ``solr`` via TYPO3 Extension Manager as usual, or via ``composer`` by
running:


.. code-block:: bash

    composer require apache-solr-for-typo3/solr


Head over to the Extension Manager module and activate the Extension.

.. image:: /Images/GettingStarted/typo3-extension-manager-filtered.png

That's all you have to do, now head over to :ref:`started-configure-extension`.
