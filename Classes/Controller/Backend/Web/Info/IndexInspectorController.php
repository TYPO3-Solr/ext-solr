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
use TYPO3\CMS\Backend\Module\AbstractFunctionModule;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Administration module controller
 */
class IndexInspectorController extends AbstractFunctionModule
{

    /**
     * Page ID in page context
     *
     * @var int
     */
    protected $pageId = 0;

    /**
     * @var Repository
     */
    protected $apacheSolrDocumentRepository;

    /**
     * Initializes properties
     */
    public function __construct()
    {
        $this->apacheSolrDocumentRepository = GeneralUtility::makeInstance(Repository::class);
        $this->pageId = (int)GeneralUtility::_GP('id');
    }

    /**
     * Lists all avalable apacha solr documents from page
     *
     * @return string|void
     */
    public function main()
    {
        $documents = $this->apacheSolrDocumentRepository->findByPageIdAndByLanguageId($this->pageId, 0);
        $documentsByType = [];
        foreach ($documents as $document) {
            $documentsByType[$document->type][] = $document;
        }

        $path = GeneralUtility::getFileAbsFileName('EXT:solr/Resources/Private/Templates/Backend/Web/Info/IndexInspector.html');
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->getRequest()->setControllerExtensionName('solr');
        $view->setTemplatePathAndFilename($path);
        $view->assignMultiple([
            'pageId' => $this->pageId,
            'documentsByType' => $documentsByType
        ]);

        return $view->render();
    }
}
