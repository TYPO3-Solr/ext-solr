<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Widget\Controller;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesService;
use ApacheSolrForTypo3\Solr\Widget\AbstractWidgetController;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LastSearchesController
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class LastSearchesController extends AbstractWidgetController
{
    /**
     * Last searches
     */
    public function indexAction()
    {
        $typoScriptConfiguration = $this->controllerContext->getTypoScriptConfiguration();
        $lastSearchesService = GeneralUtility::makeInstance(
            LastSearchesService::class,
            /** @scrutinizer ignore-type */ $typoScriptConfiguration
        );
        $this->view->assign('contentArguments', ['lastSearches' => $lastSearchesService->getLastSearches()]);
    }
}
