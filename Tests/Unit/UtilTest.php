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

    /**
     * @test
     */
    public function getSiteHashForDomain()
    {
        $oldKey = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'testKey';

        $hash1 = Util::getSiteHashForDomain('www.test.de');
        $hash2 = Util::getSiteHashForDomain('www.test.de');

        $this->assertEquals('b17ca8164881e80e96a96529c16b19cc405c9bd0', $hash1);
        $this->assertEquals('b17ca8164881e80e96a96529c16b19cc405c9bd0', $hash2);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $oldKey;

    }
}
