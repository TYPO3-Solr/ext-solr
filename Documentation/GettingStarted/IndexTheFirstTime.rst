.. _started-index:

Index the first time
====================

After everything is setup, you need to index the contents of TYPO3 to enable searching in Solr.
To do so open the *Search* module and navigate to the *Index Queue*. Select the contents to index
and *Queue Selected Content for Indexing*.

.. image:: /Images/GettingStarted/typo3-add-index-queue.png

Switch to the *Scheduler* module. If the module is not available, make sure to enable the extension
first. It comes bundled with TYPO3 CMS but is not enabled by default.

Create a new scheduler task to run the indexing:

.. image:: /Images/GettingStarted/typo3-create-scheduler-task.png

After the task was created, run it manually. The page will indicate a reload but won't reload after
the task was run. Therefore you can reload the module to see the progress bar indicating the current
progress of indexing:

.. image:: /Images/GettingStarted/typo3-scheduler-task-progress.png

The duration depends on things like the number of records to index and the number of languages
configured in your system. Also whether caching is enabled and warmed up.

The extension will now index all records in the queue and send them to Solrs index.

Once you have some records inside the index, you can :ref:`started-display-results`.
