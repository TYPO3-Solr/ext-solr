<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Logging;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Logging\DebugWriter;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DebugWriterTest extends UnitTest
{

    /**
     * @test
     */
    public function testDebugMessageIsWrittenForMessageFromSolr()
    {
        /** @var $logWriter DebugWriter */
        $logWriter = $this->getMockBuilder(DebugWriter::class)->setMethods(['getIsAllowedByDevIPMask', 'getIsdebugOutputEnabled', 'writeDebugMessage'])->getMock();
        $logWriter->expects($this->any())->method('getIsAllowedByDevIPMask')->will($this->returnValue(true));
        $logWriter->expects($this->any())->method('getIsdebugOutputEnabled')->will($this->returnValue(true));

            //we have a matching devIpMask and the debugOutput of log messages is enabled => debug should be written
        $logWriter->expects($this->once())->method('writeDebugMessage');
        $logWriter->write(SolrLogManager::INFO, 'test');
    }

    /**
     * @test
     */
    public function testDebugMessageIsNotWrittenWhenDevIpMaskIsNotMatching()
    {
        /** @var $logWriter DebugWriter */
        $logWriter = $this->getMockBuilder(DebugWriter::class)->setMethods(['getIsAllowedByDevIPMask', 'getIsdebugOutputEnabled', 'writeDebugMessage'])->getMock();
        $logWriter->expects($this->any())->method('getIsAllowedByDevIPMask')->will($this->returnValue(false));
        $logWriter->expects($this->any())->method('getIsdebugOutputEnabled')->will($this->returnValue(true));

        //we have a matching devIpMask and the debugOutput of log messages is enabled => debug should be written
        $logWriter->expects($this->never())->method('writeDebugMessage');
        $logWriter->write(SolrLogManager::INFO, 'test');
    }

    /**
     * @test
     */
    public function testDebugMessageIsNotWrittenWhenDebugOutputIsDisabled()
    {
        /** @var $logWriter DebugWriter */
        $logWriter = $this->getMockBuilder(DebugWriter::class)->setMethods(['getIsAllowedByDevIPMask', 'getIsdebugOutputEnabled', 'writeDebugMessage'])->getMock();
        $logWriter->expects($this->any())->method('getIsAllowedByDevIPMask')->will($this->returnValue(true));
        $logWriter->expects($this->any())->method('getIsdebugOutputEnabled')->will($this->returnValue(false));

        //we have a matching devIpMask and the debugOutput of log messages is enabled => debug should be written
        $logWriter->expects($this->never())->method('writeDebugMessage');
        $logWriter->write(SolrLogManager::INFO, 'test');
    }
}
