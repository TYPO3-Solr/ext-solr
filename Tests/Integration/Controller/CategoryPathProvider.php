<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;


/**
 * Class CategoryPathProvider
 *
 * Test class that returns a fixture path list just for testing.
 *
 * @package ApacheSolrForTypo3\Solr\Tests\Integration\Controller
 */
class CategoryPathProvider {

    /**
     * Returns a list of paths concatenated with , only for testing
     * @return string
     */
    public function getPaths() {

        if ($GLOBALS['TSFE']->id === 2) {
            return 'Men/Shoes \/ Socks,Accessoires/Socks';
        }

        return '';
    }
}