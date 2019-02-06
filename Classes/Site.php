<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Domain\Site\Site as NewSite;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;

/**
 * A site is a branch in a TYPO3 installation. Each site's root page is marked
 * by the "Use as Root Page" flag.
 *
 * @deprecated The class was  moved to ApacheSolrForTypo3\Solr\Domain\Site\Site the old class will be removed in EXT:solr 10
 * @author Ingo Renner <ingo@typo3.org>
 */
class Site extends NewSite
{
    /**
     * Constructor.
     *
     * @param TypoScriptConfiguration $configuration
     * @param array $page Site root page ID (uid). The page must be marked as site root ("Use as Root Page" flag).
     * @param string $domain The domain record used by this Site
     * @param string $siteHash The site hash used by this site
     * @param PagesRepository $pagesRepository
     * @param int $defaultLanguageId
     * @deprecated Deprecated since 9.0.0 please use ApacheSolrForTypo3\Solr\Domain\Site\Site now. Will be removed in EXT:solr 10
     */
    public function __construct(TypoScriptConfiguration $configuration, array $page, $domain, $siteHash, PagesRepository $pagesRepository = null, $defaultLanguageId = 0)
    {
        trigger_error('The class Site was moved to ApacheSolrForTypo3\Solr\Domain\Site\Site please make sure that you this class now. Will be removed in EXT:solr 10', E_USER_DEPRECATED);
        parent::__construct($configuration, $page, $domain, $siteHash, $pagesRepository, $defaultLanguageId);
    }

    /**
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call($method, $args) {
        if(is_callable(array($this,$method))) {
            trigger_error('The class Site was moved to ApacheSolrForTypo3\Solr\Domain\Site\Site please make sure that you this class now. Will be removed in EXT:solr 10', E_USER_DEPRECATED);
            return call_user_func_array(array($this,$method), $args);
        } else {
            trigger_error("Call to undefined method '{$method}'");
        }
    }
}
