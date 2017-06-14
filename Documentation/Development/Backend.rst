
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

Renders menu in backends doc header with available Solr cores for selected Site and changes the solr core by clicking on option in drop down menu.

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

***
FAQ
***

**Why should I add some action name to my action controller?**

To allow calling this action within your controller, component can use own controller for changing state only if that is hardcoded with some module
and allowed by ACL for all(or almost all) be users/groups, but this is a bag approach. Therefore allow changing something, only if that is needed.

**What do I need to do for using Backend Components?**

By extending ApacheSolrForTypo3\Solr\Controller\Backend\Search\AbstractModuleController your module has the pagetree (to select the side) and the core selector, to
select the needed solr core.

