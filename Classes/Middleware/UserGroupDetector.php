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

namespace ApacheSolrForTypo3\Solr\Middleware;

use ApacheSolrForTypo3\Solr\IndexQueue\IndexingResultCollector;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Domain\Access\RecordAccessGrantedEvent;
use TYPO3\CMS\Core\Domain\Event\BeforePageIsRetrievedEvent;
use TYPO3\CMS\Core\Domain\Event\BeforeRecordLanguageOverlayEvent;
use TYPO3\CMS\Core\Domain\Event\ModifyDefaultConstraintsForDatabaseQueryEvent;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\Exception\UndefinedSchemaException;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Frontend\ContentObject\Event\AfterContentObjectRendererInitializedEvent;

/**
 * Event listeners for the findUserGroups sub-request phase.
 *
 * Active only when the request attribute `solr.userGroupDetection` is true
 * (set by UserGroupDetectionMiddleware). Performs two tasks:
 *
 * 1. Removes fe_group access restrictions so ALL content elements are rendered
 * 2. Collects fe_group values from rendered content elements into the ResultCollector
 *
 * Not a Singleton — fresh instance per event dispatch. State is scoped
 * to the request via attributes, not instance properties.
 */
readonly class UserGroupDetector
{
    public function __construct(
        private IndexingResultCollector $resultCollector,
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    /**
     * Removes fe_group from record data so TYPO3's access check grants access.
     *
     * @noinspection PhpUnused Dispatched by {@see \TYPO3\CMS\Core\Domain\Access\RecordAccessVoter::accessGranted()}
     */
    #[AsEventListener]
    public function checkEnableFields(RecordAccessGrantedEvent $event): void
    {
        if (!$this->isActive()) {
            return;
        }
        $record = $event->getRecord();
        $record['fe_group'] = '';
        $event->updateRecord($record);
    }

    /**
     * Removes the fe_group constraint from database queries so ALL content is fetched.
     *
     * @noinspection PhpUnused Dispatched by {@see \TYPO3\CMS\Core\Domain\Repository\PageRepository::getDefaultConstraints()}
     */
    #[AsEventListener]
    public function removeFeGroupConstraintFromDatabaseQuery(ModifyDefaultConstraintsForDatabaseQueryEvent $event): void
    {
        if (!$this->isActive()) {
            return;
        }
        $constraints = $event->getConstraints();
        if (!isset($constraints['fe_group'])) {
            return;
        }
        unset($constraints['fe_group']);
        $event->setConstraints($constraints);
    }

    /**
     * Skips the group access check when fetching page records.
     *
     * @noinspection PhpUnused Dispatched by {@see \TYPO3\CMS\Core\Domain\Repository\PageRepository::getPage()}
     */
    #[AsEventListener]
    public function skipPageGroupAccessCheck(BeforePageIsRetrievedEvent $event): void
    {
        if (!$this->isActive()) {
            return;
        }
        $event->skipGroupAccessCheck();
    }

    /**
     * Removes fe_group and extendToSubpages from page overlay records
     * so that access-restricted pages and their subpages are accessible.
     *
     * @noinspection PhpUnused Dispatched by {@see \TYPO3\CMS\Core\Domain\Repository\PageRepository::getLanguageOverlay()}
     */
    #[AsEventListener]
    public function clearPageOverlayAccessRestrictions(BeforeRecordLanguageOverlayEvent $event): void
    {
        if (!$this->isActive()) {
            return;
        }
        if ($event->getTable() !== 'pages') {
            return;
        }
        $pageInput = $event->getRecord();
        $pageInput['fe_group'] = '';
        $pageInput['extendToSubpages'] = '0';
        $event->setRecord($pageInput);
    }

    /**
     * Collects fe_group values from content elements during rendering.
     * Uses TcaSchemaFactory to determine the fe_group field name (no TCA manipulation needed).
     *
     * @noinspection PhpUnused Dispatched by {@see \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::start()}
     *
     * @throws UndefinedSchemaException
     */
    #[AsEventListener]
    public function collectContentElementFrontendGroups(AfterContentObjectRendererInitializedEvent $event): void
    {
        if (!$this->isActive()) {
            return;
        }
        $cObject = $event->getContentObjectRenderer();
        if (empty($cObject->currentRecord)) {
            return;
        }
        [$table] = explode(':', $cObject->currentRecord);
        if (empty($table) || $table === 'pages') {
            return;
        }

        if (!$this->tcaSchemaFactory->has($table)) {
            return;
        }
        $schema = $this->tcaSchemaFactory->get($table);
        if (!$schema->hasCapability(TcaSchemaCapability::RestrictionUserGroup)) {
            return;
        }

        $feGroupField = $schema->getCapability(TcaSchemaCapability::RestrictionUserGroup)->getFieldName();
        $feGroupValue = $cObject->data[$feGroupField] ?? null;

        if (empty($feGroupValue) || $feGroupValue === '-1') {
            $feGroupValue = 0;
        }

        $this->resultCollector->addFrontendGroup($feGroupValue);
    }

    private function isActive(): bool
    {
        return $this->resultCollector->isUserGroupDetectionActive();
    }
}
