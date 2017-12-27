<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Records\SystemDomain;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-eb-support@dkd.de>
 *
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

use ApacheSolrForTypo3\Solr\System\Records\SystemDomain\SystemDomainRepository;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * Integration test for the SystemDomainRepository
 */
class SystemDomainRepositoryTest extends IntegrationTest
{
    /**
     * @var SystemDomainRepository
     */
    protected $systemDomainRepository;

    public function setUp()
    {
        parent::setUp();

        $this->systemDomainRepository = GeneralUtility::makeInstance(SystemDomainRepository::class);
    }

    /**
     * @test
     */
    public function canFindDomainRecordsByRootPagesIds()
    {
        $this->importDataSetFromFixture('sys_domain.xml');

        $fakeRootPageIds = [125, 12, 24];

        $domainRecords = $this->systemDomainRepository->findDomainRecordsByRootPagesIds($fakeRootPageIds);

        $expectedDomainRecords = [
            24 => [
                'uid' => 17,
                'pid' => 24
            ]];

        $this->assertEquals($expectedDomainRecords, $domainRecords);
    }
}
