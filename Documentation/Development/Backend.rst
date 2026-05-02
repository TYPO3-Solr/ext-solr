##########################
Developing Backend Modules
##########################

******************
Backend Components
******************

EXT:solr provides UI components for backend modules. Some components hold (GUI)state and some not, but all components calling actions(changing the extension and/or GUI state!),
and then redirecting to the actions within the component was used(referrer also) or to the defined by callers action uri, if that is required by UX.

Below are all available components listed and their responsibility.

CoreSelector
============

Renders menu in backends doc header with available Solr cores for selected Site and changes the Solr core by clicking on option in drop down menu.

* Provides following methods, which must be called inside the `initializeView(...)` method in your controller to render this component in Backend:

  * `generateCoreSelectorMenuUsingSiteSelector()`

    * Use this method together with SiteSelectorMenu component.

  * `generateCoreSelectorMenuUsingPageTree()`

    * Use this method if you are using original page tree from CMS.

* Provides following Actions for changing state, must be added to actions list of your controller:

  * `switchCore`

* Provides following fully initialized properties in utilizing action controller:

  * `$selectedSolrCoreConnection from type \ApacheSolrForTypo3\Solr\SolrService`

If you need the possibility to switch the core, you can extends the AbstractModuleController (in ApacheSolrForTypo3\Solr\Controller\Backend\Search).

****************************************
Server-rendered counts in action buttons
****************************************

Several backend actions accept a numeric bound (a batch size, a limit, an offset) and the surrounding UI needs to communicate that bound to the user. The Index Queue module's "Index N items" button is the reference implementation of this pattern.

The constraint is simple: **whatever the label promises, the action must perform — no more, no less, with no extra round-trip needed to discover the value.** A naive split where the label says "Index 50" but the action figures out the real count on submit creates two failure modes — the user sees a stale number, or the action over- or under-shoots what was advertised.

The pattern resolves this by computing the bound **once on the server** and threading it through three places that all read the same variable:

1. **Controller** computes the value before rendering and assigns it to the view:

   .. code-block:: php

       $manualIndexBatchSize = min(
           $statistics->getPendingCount(),
           self::MANUAL_INDEX_RUN_MAX_LIMIT
       );
       $this->moduleTemplate->assign('manualIndexBatchSize', $manualIndexBatchSize);

   ``min(pending, cap)`` collapses both rules — "stop at the actual count" and "never exceed the cap" — into a single integer.

2. **Fluid template** uses that integer twice in the same form: as the visible label and as the hidden form value. The :html:`<f:if>` guard hides the button when it would be useless (here, when 1 or fewer items are pending — the single-item button already covers that case):

   .. code-block:: html

       <f:if condition="{manualIndexBatchSize} > 1">
           <f:form action="doIndexingRun" method="POST">
               <f:form.hidden name="limit" value="{manualIndexBatchSize}"/>
               <f:form.submit value="{f:translate(
                   key:'action.indexBatch',
                   domain:'solr.modules.index_queue',
                   arguments:{0: manualIndexBatchSize}
               )}"/>
           </f:form>
       </f:if>

   Because both the label argument and the hidden field read ``{manualIndexBatchSize}``, **what the user sees is exactly what the controller will receive** — drift is structurally impossible.

3. **XLF translations** use positional :php:`%d` interpolation so each language keeps natural word order:

   .. code-block:: xml

       <!-- en -->
       <source>Index %d items</source>
       <!-- de -->
       <target>%d indexieren</target>

   ``LocalizationUtility::translate()`` runs the result through :php:`vsprintf()` with the ``arguments`` array, so ``{0: 31}`` becomes ``31``.

The controller action stays generic — it accepts the limit as an ordinary parameter and clamps it defensively, so it can be invoked by any caller, not just the batch button:

.. code-block:: php

    public function doIndexingRunAction(int $limit = 1): ResponseInterface
    {
        $limit = max(1, min($limit, self::MANUAL_INDEX_RUN_MAX_LIMIT));
        // ...
    }

When to reach for this pattern:

* The button label needs to communicate **how much** work the next click will do (batch size, page size, items-to-process, records-to-export).
* The number depends on **server state** that the client does not already have (queue depth, available licenses, remaining quota).
* You want the UI to stay correct without any JavaScript or AJAX polling.

When **not** to use it:

* The bound never changes (a static "Index 50" button is simpler — just hard-code the value).
* The bound changes faster than the page renders (a long-running queue that drains while the user looks at the page). In that case either reload the section after each click, or move to a real progress UI.

***
FAQ
***

**Why should I add some action name to my action controller?**

To allow calling this action within your controller, component can use own controller for changing state only if that is hardcoded with some module
and allowed by ACL for all(or almost all) be users/groups, but this is a bag approach. Therefore allow changing something, only if that is needed.

**What do I need to do for using Backend Components?**

By extending ApacheSolrForTypo3\Solr\Controller\Backend\Search\AbstractModuleController your module has the pagetree (to select the side) and the core selector, to
select the needed Solr core.

