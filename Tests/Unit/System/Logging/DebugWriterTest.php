<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Logging;

use ApacheSolrForTypo3\Solr\System\Logging\DebugWriter;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LogLevel;

class DebugWriterTest extends SetUpUnitTestCase
{
    #[Test]
    public function testDebugMessageIsWrittenForMessageFromSolr(): void
    {
        $logWriter = $this->getMockBuilder(DebugWriter::class)->onlyMethods(['getIsAllowedByDevIPMask', 'getIsdebugOutputEnabled', 'writeDebugMessage'])->getMock();
        $logWriter->expects(self::any())->method('getIsAllowedByDevIPMask')->willReturn(true);
        $logWriter->expects(self::any())->method('getIsdebugOutputEnabled')->willReturn(true);

        //we have a matching devIpMask and the debugOutput of log messages is enabled => debug should be written
        $logWriter->expects(self::once())->method('writeDebugMessage');
        $logWriter->write(LogLevel::INFO, 'test');
    }

    #[Test]
    public function testDebugMessageIsNotWrittenWhenDevIpMaskIsNotMatching(): void
    {
        $logWriter = $this->getMockBuilder(DebugWriter::class)->onlyMethods(['getIsAllowedByDevIPMask', 'getIsdebugOutputEnabled', 'writeDebugMessage'])->getMock();
        $logWriter->expects(self::any())->method('getIsAllowedByDevIPMask')->willReturn(false);
        $logWriter->expects(self::any())->method('getIsdebugOutputEnabled')->willReturn(true);

        //we have a matching devIpMask and the debugOutput of log messages is enabled => debug should be written
        $logWriter->expects(self::never())->method('writeDebugMessage');
        $logWriter->write(LogLevel::INFO, 'test');
    }

    #[Test]
    public function testDebugMessageIsNotWrittenWhenDebugOutputIsDisabled(): void
    {
        $logWriter = $this->getMockBuilder(DebugWriter::class)->onlyMethods(['getIsAllowedByDevIPMask', 'getIsdebugOutputEnabled', 'writeDebugMessage'])->getMock();
        $logWriter->expects(self::any())->method('getIsAllowedByDevIPMask')->willReturn(true);
        $logWriter->expects(self::any())->method('getIsdebugOutputEnabled')->willReturn(false);

        //we have a matching devIpMask and the debugOutput of log messages is enabled => debug should be written
        $logWriter->expects(self::never())->method('writeDebugMessage');
        $logWriter->write(LogLevel::INFO, 'test');
    }
}
