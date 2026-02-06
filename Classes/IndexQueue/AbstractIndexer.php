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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use ApacheSolrForTypo3\Solr\FrontendSimulation\Exception\Exception as FrontendSimulationException;
use ApacheSolrForTypo3\Solr\FrontendSimulation\FrontendAwareEnvironment;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;
use Throwable;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * An abstract indexer class to collect a few common methods shared with other
 * indexers.
 */
abstract class AbstractIndexer
{
    /**
     * Holds the type of the data to be indexed, usually that is the table name.
     */
    protected string $type = '';

    /**
     * Holds field names that are denied to overwrite in thy indexing configuration.
     * @var string[]
     */
    protected static array $unAllowedOverrideFields = ['type'];

    public static function isAllowedToOverrideField(string $solrFieldName): bool
    {
        return !in_array($solrFieldName, static::$unAllowedOverrideFields);
    }

    /**
     * Adds fields to the document as defined in $indexingConfiguration
     *
     * @param Document $document base document to add fields to
     * @param array $indexingConfiguration Indexing configuration / mapping
     * @param array $data Record data
     * @param ServerRequest $request The simulated frontend request
     * @param int|SiteLanguage $language The site language object or UID as int
     * @return Document Modified document with added fields
     *
     * @throws AspectNotFoundException
     * @throws DBALException
     * @throws FrontendSimulationException
     * @throws JsonException
     * @throws SiteNotFoundException
     */
    protected function addDocumentFieldsFromTyposcript(
        Document $document,
        array $indexingConfiguration,
        array $data,
        ServerRequest $request,
        int|SiteLanguage $language,
    ): Document {
        $data = static::addVirtualContentFieldToRecord($document, $data);

        // mapping of record fields => solr document fields, resolving cObj
        foreach ($indexingConfiguration as $solrFieldName => $recordFieldName) {
            if (is_array($recordFieldName)) {
                // configuration for a content object, skipping
                continue;
            }

            if (!static::isAllowedToOverrideField($solrFieldName)) {
                throw new InvalidFieldNameException(
                    'Must not overwrite field .' . $solrFieldName,
                    1435441863,
                );
            }

            $fieldValue = $this->resolveFieldValue(
                $indexingConfiguration,
                $solrFieldName,
                $data,
                $request,
                $language,
            );
            if ($fieldValue === null
                || $fieldValue === ''
                || (is_array($fieldValue) && empty($fieldValue))
            ) {
                continue;
            }

            $document->setField($solrFieldName, $fieldValue);
        }

        /** @var PageInformation|null $pageInformation */
        $pageInformation = $request->getAttribute('frontend.page.information');
        $pageId = $pageInformation?->getId() ?? 0;

        $typoScriptConfiguration = $this->getTypoScriptConfiguration(
            $pageId,
            ($language instanceof SiteLanguage ? $language->getLanguageId() : $language),
        );
        if ($typoScriptConfiguration->isVectorSearchEnabled() && !isset($document['vectorContent'])) {
            $document->setField('vectorContent', $document['content']);
        }

        return $document;
    }

    /**
     * Adds the content of the field 'content' from the solr document as virtual field __solr_content in the record,
     * to have it available in TypoScript.
     */
    public static function addVirtualContentFieldToRecord(Document $document, array $data): array
    {
        if (isset($document['content'])) {
            $data['__solr_content'] = $document['content'];
            return $data;
        }
        return $data;
    }

    /**
     * Resolves a field to its value depending on its configuration.
     *
     * This enables you to configure the indexer to put the item/record through
     * cObj processing if wanted/needed. Otherwise, the plain item/record value
     * is taken.
     *
     * @param array $indexingConfiguration Indexing configuration as defined in plugin.tx_solr_index.queue.[indexingConfigurationName].fields
     * @param string $solrFieldName A Solr field name that is configured in the indexing configuration
     * @param array $data A record or item's data
     * @param ServerRequest $request The simulated frontend request
     * @param int|SiteLanguage $language The language to use
     *
     * @return array|float|int|string|null The resolved string value to be indexed; null if value could not be resolved
     */
    protected function resolveFieldValue(
        array $indexingConfiguration,
        string $solrFieldName,
        array $data,
        ServerRequest $request,
        int|SiteLanguage $language,
    ): mixed {
        if (isset($indexingConfiguration[$solrFieldName . '.'])) {
            // configuration found => need to resolve a cObj
            // Use the frontend context from the simulated request
            /** @var Context $context */
            $context = $request->getAttribute('solr.frontend.context');
            $cObject = GeneralUtility::makeInstance(ContentObjectRenderer::class, null, $context);
            $cObject->setRequest($request);
            $cObject->start($data, $this->type);
            $fieldValue = $cObject->cObjGetSingle(
                $indexingConfiguration[$solrFieldName],
                $indexingConfiguration[$solrFieldName . '.'],
            );

            try {
                $unserializedFieldValue = @unserialize($fieldValue);
                if (is_array($unserializedFieldValue) || is_object($unserializedFieldValue)) {
                    $fieldValue = $unserializedFieldValue;
                }
            } catch (Throwable) {
                // Evil catch, but anyway do nothing to prevent flooding the logs on indexing.
                // If the cObject implementation does not provide data the fields are not present in index, which will be noticed and fixed by devs/integrators.
            }
        } else {
            $indexingFieldName = $indexingConfiguration[$solrFieldName] ?? null;
            if (empty($indexingFieldName) ||
                !is_string($indexingFieldName) ||
                !array_key_exists($indexingFieldName, $data)) {
                return null;
            }
            $fieldValue = $data[$indexingFieldName];
        }

        // detect and correct type for dynamic fields

        // find last underscore, substr from there, cut off last character (S/M)
        $fieldType = substr(
            $solrFieldName,
            strrpos($solrFieldName, '_') + 1,
            -1,
        );
        if (is_array($fieldValue)) {
            foreach ($fieldValue as $key => $value) {
                $fieldValue[$key] = $this->ensureFieldValueType(
                    $value,
                    $fieldType,
                );
            }
        } else {
            $fieldValue = $this->ensureFieldValueType($fieldValue, $fieldType);
        }

        return $fieldValue;
    }

    // Utility methods

    /**
     * Makes sure a field's value matches a (dynamic) field's type.
     *
     * @param mixed $value Value to be added to a document
     * @param string $fieldType The dynamic field's type
     * @return int|float|string|null Returns the value in the correct format for the field type
     */
    protected function ensureFieldValueType(mixed $value, string $fieldType): mixed
    {
        switch ($fieldType) {
            case 'int':
            case 'tInt':
                $value = MathUtility::canBeInterpretedAsInteger($value) ? (int)$value : null;
                break;

            case 'float':
            case 'tFloat':
                $value = MathUtility::canBeInterpretedAsInteger($value) || MathUtility::canBeInterpretedAsFloat($value) ? (float)$value : null;
                break;
            case 'long':
                // long and double do not exist in PHP
                // simply make sure it somehow looks like a number
                // <insert PHP rant here>
            case 'tLong':
                // remove anything that's not a number or negative/minus sign
                $value = preg_replace('/[^0-9\\-]/', '', $value);
                if (trim($value) === '') {
                    $value = null;
                }
                break;
            case 'double':
            case 'tDouble':
            case 'tDouble4':
                // as long as it's numeric we'll take it, int or float doesn't matter
                if (!is_numeric($value)) {
                    $value = null;
                }
                break;

            default:
                // assume things are correct for non-dynamic fields
        }

        return $value;
    }

    /**
     * @throws SiteNotFoundException
     * @throws AspectNotFoundException
     * @throws FrontendSimulationException
     * @throws JsonException
     * @throws DBALException
     */
    protected function getRequest(int $pageId, int $languageId): ?ServerRequest
    {
        /** @noinspection PhpInternalEntityUsedInspection */
        if (($GLOBALS['TYPO3_REQUEST'] ?? null)?->getAttribute('applicationType')
            === SystemEnvironmentBuilder::REQUESTTYPE_FE) {
            $request = $GLOBALS['TYPO3_REQUEST'];
        } else {
            $request = GeneralUtility::makeInstance(FrontendAwareEnvironment::class)
                ->getServerRequestByPageIdAndLanguageId($pageId, $languageId);
        }

        return $request;
    }

    /**
     * @throws AspectNotFoundException
     * @throws SiteNotFoundException
     * @throws FrontendSimulationException
     * @throws JsonException
     * @throws DBALException
     */
    protected function getFrontendTypoScript(int $pageId, int $languageId): ?FrontendTypoScript
    {
        $request = $this->getRequest($pageId, $languageId);
        return $request?->getAttribute('frontend.typoscript');
    }

    /**
     * @throws AspectNotFoundException
     * @throws SiteNotFoundException
     * @throws FrontendSimulationException
     * @throws JsonException
     * @throws DBALException
     */
    protected function getTypoScriptConfiguration(int $pageId, int $languageId): TypoScriptConfiguration
    {
        $frontendTypoScript = $this->getFrontendTypoScript($pageId, $languageId);
        return GeneralUtility::makeInstance(
            TypoScriptConfiguration::class,
            $frontendTypoScript?->getSetupArray() ?? [],
        );
    }
}
