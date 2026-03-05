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
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectFactory;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Tests setUp for EXT:solr content object classes
 */
abstract class SetUpContentObject extends SetUpUnitTestCase
{
    protected bool $resetSingletonInstances = true;

    protected MockObject|ContentObjectRenderer $contentObjectRenderer;
    protected AbstractContentObject $testableContentObject;

    protected function setUp(): void
    {
        parent::setUp();

        $request = new ServerRequest();
        $this->contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $this->contentObjectRenderer->method('getRequest')->willReturn($request);
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
        $container->set(EventDispatcherInterface::class, new NoopEventDispatcher());
        GeneralUtility::setContainer($container);

        // Track data set via start() for use in stdWrap
        $data = [];
        $this->contentObjectRenderer->method('start')->willReturnCallback(
            function (array $inputData) use (&$data) {
                $data = $inputData;
            },
        );

        // Configure stdWrap to return field values from data
        $this->contentObjectRenderer->method('stdWrap')->willReturnCallback(
            function (string $content, array $conf) use (&$data) {
                if (isset($conf['field']) && isset($data[$conf['field']])) {
                    return $data[$conf['field']];
                }
                return $content;
            },
        );

        // Configure the mock to call the actual content object's render method
        $testableContentObject = $this->testableContentObject;
        $this->contentObjectRenderer->method('cObjGetSingle')->willReturnCallback(
            function (string $name, array $conf) use ($testableContentObject) {
                $contentObjectName = ($testableContentObject::class)::CONTENT_OBJECT_NAME;
                if ($name === $contentObjectName) {
                    return $testableContentObject->render($conf);
                }
                return '';
            },
        );
    }

    abstract protected function getTestableContentObjectClassName(): string;
}
