<?php
namespace ApacheSolrForTypo3\Solr\System\Configuration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2016 Timo Schmidt <timo.schmidt@dkd.de
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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

use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Records\SystemTemplate\SystemTemplateRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * This class is responsible to find the closest page id from the rootline where
 * a typoscript template is stored on.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ConfigurationPageResolver
{

    /**
     * @var SystemTemplateRepository
     */
    protected $systemTemplateRepository;

    /**
     * @var TwoLevelCache
     */
    protected $twoLevelCache;

    /**
     * @var TwoLevelCache
     */
    protected $runtimeCache;

    /**
     * ConfigurationPageResolver constructor.
     * @param PageRepository|null $pageRepository
     * @param TwoLevelCache|null $twoLevelCache
     * @param SystemTemplateRepository $systemTemplateRepository
     */
    public function __construct(PageRepository $pageRepository = null, TwoLevelCache $twoLevelCache = null, SystemTemplateRepository $systemTemplateRepository = null)
    {
        $this->runtimeCache = $twoLevelCache ?? GeneralUtility::makeInstance(TwoLevelCache::class, /** @scrutinizer ignore-type */ 'cache_runtime');
        $this->systemTemplateRepository = $systemTemplateRepository ?? GeneralUtility::makeInstance(SystemTemplateRepository::class);
    }

    /**
     * This method fetches the rootLine and calculates the id of the closest template in the rootLine.
     * The result is stored in the runtime cache.
     *
     * @param integer $startPageId
     * @return integer
     */
    public function getClosestPageIdWithActiveTemplate($startPageId)
    {
        if ($startPageId === 0) {
            return 0;
        }

        $cacheId = 'ConfigurationPageResolver' . '_' . 'getClosestPageIdWithActiveTemplate' . '_' . $startPageId;
        $methodResult = $this->runtimeCache->get($cacheId);
        if (!empty($methodResult)) {
            return $methodResult;
        }

        $methodResult = $this->calculateClosestPageIdWithActiveTemplate($startPageId);
        $this->runtimeCache->set($cacheId, $methodResult);

        return $methodResult;
    }

    /**
     * This method fetches the rootLine and calculates the id of the closest template in the rootLine.
     *
     * @param integer $startPageId
     * @return int
     */
    protected function calculateClosestPageIdWithActiveTemplate($startPageId)
    {

        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $startPageId);
        try {
            $rootline = $rootlineUtility->get();
        } catch (\RuntimeException $e) {
            return $startPageId;
        }

        $closestPageIdWithTemplate = $this->systemTemplateRepository->findOneClosestPageIdWithActiveTemplateByRootLine($rootline);
        if ($closestPageIdWithTemplate === 0) {
            return $startPageId;
        }

        return (int)$closestPageIdWithTemplate;
    }
}
