<?php

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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Extbase;

use ApacheSolrForTypo3\FakeExtension\Domain\Model\Foo;
use ApacheSolrForTypo3\FakeExtension\Domain\Repository\FooRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class PersistenceEventListenerTest extends IntegrationTestBase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/solr',
        '../vendor/apache-solr-for-typo3/solr/Tests/Integration/Fixtures/Extensions/fake_extension',
    ];

    protected ?Queue $indexQueue = null;
    protected ?FooRepository $repository = null;
    protected ?PersistenceManager $persistenceManager = null;
    protected ServerRequest $serverRequest;

    /**
     * @throws SiteNotFoundException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_solr.index.queue.foo = 1
            plugin.tx_solr.index.queue.foo.type = tx_fakeextension_domain_model_foo
        ');
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
        $this->repository = GeneralUtility::makeInstance(FooRepository::class);
        $this->persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);

        $frontendTypoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTypoScript->setSetupArray([]);
        $GLOBALS['TYPO3_REQUEST'] = $this->serverRequest = (new ServerRequest('http://testone.site/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('frontend.typoscript', $frontendTypoScript)
            ->withAttribute(
                'site',
                $this->get(SiteFinder::class)->getSiteByIdentifier('integration_tree_one'),
            );
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws DBALException
     */
    #[Test]
    public function newEntityIsAddedToIndexQueue(): void
    {
        $object = new Foo();
        $object->setTitle('Added');
        $object->setPid(1);
        $repository = GeneralUtility::makeInstance(FooRepository::class);
        $repository->add($object);
        $this->persistenceManager->persistAll();
        self::assertTrue($this->indexQueue->containsItem('tx_fakeextension_domain_model_foo', 1));
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws DBALException
     */
    #[Test]
    public function newHiddenEntityIsNotAddedToIndexQueue(): void
    {
        $object = new Foo();
        $object->setTitle('Added');
        $object->setHidden(true);
        $object->setPid(1);
        $repository = GeneralUtility::makeInstance(FooRepository::class);
        $repository->add($object);
        $this->persistenceManager->persistAll();
        self::assertFalse($this->indexQueue->containsItem('tx_fakeextension_domain_model_foo', 1));
    }

    /**
     * @throws AspectNotFoundException
     * @throws UnknownObjectException
     * @throws IllegalObjectTypeException
     * @throws DBALException
     */
    #[Test]
    public function updatedEntityIsUpdatedInIndexQueue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/update_items.csv');
        /** @var Foo $object */
        $object = $this->repository->findByUid(2);
        $object->setTitle('Updated');
        $this->repository->update($object);
        $this->persistenceManager->persistAll();

        /** @var Context $context */
        $context = GeneralUtility::makeInstance(Context::class);
        $currentTimestamp = $context->getPropertyFromAspect('date', 'timestamp');

        self::assertTrue($this->indexQueue->containsItem('tx_fakeextension_domain_model_foo', 2));

        $item = $this->indexQueue->getItem(1);
        self::assertSame(2, $item->getRecordUid());
        self::assertSame('foo', $item->getIndexingConfigurationName());
        self::assertSame($currentTimestamp, $item->getChanged());
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws DBALException
     */
    #[Test]
    public function softDeletedEntityIsRemovedFromIndexQueue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/delete_items.csv');
        /** @var Foo $object */
        $object = $this->repository->findByUid(3);
        $this->repository->remove($object);
        $this->persistenceManager->persistAll();

        self::assertFalse($this->indexQueue->containsItem('tx_fakeextension_domain_model_foo', 3));
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws DBALException
     */
    #[Test]
    public function deletedEntityIsRemovedFromIndexQueue(): void
    {
        unset($GLOBALS['TCA']['tx_fakeextension_domain_model_foo']['ctrl']['delete']);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/delete_items.csv');
        /** @var Foo $object */
        $object = $this->repository->findByUid(3);
        $this->repository->remove($object);
        $this->persistenceManager->persistAll();

        self::assertFalse($this->indexQueue->containsItem('tx_fakeextension_domain_model_foo', 3));
    }

    /**
     * @throws UnknownObjectException
     * @throws IllegalObjectTypeException
     * @throws DBALException
     */
    #[Test]
    public function updatedEntityTurnedHiddenIsRemovedFromIndexQueue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/hidden_items.csv');
        /** @var Foo $object */
        $object = $this->repository->findByUid(4);
        $object->setHidden(true);
        $this->repository->update($object);
        $this->persistenceManager->persistAll();

        self::assertFalse($this->indexQueue->containsItem('tx_fakeextension_domain_model_foo', 4));
    }
}
