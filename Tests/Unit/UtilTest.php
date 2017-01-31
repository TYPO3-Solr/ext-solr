<?php


use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Util;

class UtilTest extends UnitTest
{

    /**
     * @test
     */
    public function getConfigurationFromPageIdReturnsEmptyConfigurationForPageIdZero()
    {
        $configuration = Util::getConfigurationFromPageId(0, 'plugin.tx_solr', false, 0, false);
        $this->assertInstanceOf('ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration', $configuration);
    }
}
