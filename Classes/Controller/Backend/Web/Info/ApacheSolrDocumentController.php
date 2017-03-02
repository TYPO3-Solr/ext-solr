<?php
namespace ApacheSolrForTypo3\Solr\Controller\Backend\Web\Info;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Rafael Kähm <rafael.kaehm@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/


use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Repository;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * Administration module controller
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class ApacheSolrDocumentController extends ActionController
{

    /**
     * Page ID in page context
     *
     * @var int
     */
    protected $pageId = 0;

    /**
     * Page ID in page context
     *
     * @var int
     */
    protected $languageId = 0;

    /**
     * @var \ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Repository
     * @inject
     */
    protected $apacheSolrDocumentRepository;

    /**
     * Initializes action
     *
     * @return void
     */
    protected function initializeAction()
    {
        parent::initializeAction();
        $pageId = (int)GeneralUtility::_GP('id');
        $languageId = (int)GeneralUtility::_GP('L');
        $this->initializePageIdAndLanguageId($pageId, $languageId);
    }

    /**
     * Initializes required for processing properties page and language Ids
     *
     * @param $pageId
     * @param $languageId
     */
    public function initializePageIdAndLanguageId($pageId, $languageId)
    {
        $this->pageId = $pageId;
        $this->languageId = $languageId;
    }

    /**
     * Lists all avalable apacha solr documents from page
     *
     * @return string|void
     */
    public function indexAction()
    {
        $documents = $this->apacheSolrDocumentRepository->findByPageIdAndByLanguageId($this->pageId, 0);
        $documentsByType = [];
        foreach ($documents as $document) {
            $documentsByType[$document->type][] = $document;
        }

        $this->view->assignMultiple([
            'pageId' => $this->pageId,
            'documentsByType' => $documentsByType
        ]);
    }
}
