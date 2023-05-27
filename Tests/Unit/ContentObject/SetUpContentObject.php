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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\ContentObject;

use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectFactory;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Tests setUp for EXT:solr content object classes
 */
abstract class SetUpContentObject extends SetUpUnitTestCase
{
    protected bool $resetSingletonInstances = true;

    protected ContentObjectRenderer $contentObjectRenderer;
    protected AbstractContentObject $testableContentObject;

    protected function setUp(): void
    {
        parent::setUp();

        $tsfe = $this->getMockBuilder(TypoScriptFrontendController::class)
            ->addMethods(['dummy'])
            ->disableOriginalConstructor()
            ->getMock();

        $request = new ServerRequest();
        $this->contentObjectRenderer = new ContentObjectRenderer($tsfe);
        $this->contentObjectRenderer->setRequest($request);
        $cObjectFactoryMock = $this->getMockBuilder(ContentObjectFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->testableContentObject = new ($this->getTestableContentObjectClassName())();
        $this->testableContentObject->setRequest($request);
        $this->testableContentObject->setContentObjectRenderer($this->contentObjectRenderer);

        $cObjectFactoryMock->method('getContentObject')->willReturnMap([
            [($this->getTestableContentObjectClassName())::CONTENT_OBJECT_NAME, $request, $this->contentObjectRenderer, $this->testableContentObject],
        ]);

        $container = new Container();
        $container->set(ContentObjectFactory::class, $cObjectFactoryMock);
        GeneralUtility::setContainer($container);
    }

    abstract protected function getTestableContentObjectClassName(): string;
}
