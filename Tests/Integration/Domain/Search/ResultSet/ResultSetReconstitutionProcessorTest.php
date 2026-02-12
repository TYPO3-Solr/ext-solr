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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Search\ResultSet;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\Option;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\ResultSetReconstitutionProcessor;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

class ResultSetReconstitutionProcessorTest extends IntegrationTestBase
{
    #[Test]
    public function canApplyRenderingInstructionsOnOptions(): void
    {
        $this->writeDefaultSolrTestSiteConfiguration();
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.com'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'My Type with special rendering',
                    'field' => 'type_stringS',
                    'renderingInstruction' => 'CASE',
                    'renderingInstruction.' => [
                        'key.' => [
                            'field' => 'optionValue',
                        ],
                        'page' => 'TEXT',
                        'page.' => [
                            'value' => 'Pages',
                        ],
                        'event' => 'TEXT',
                        'event.' => [
                            'value' => 'Events',
                        ],

                    ],
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        /** @var OptionsFacet $facet */
        $facet = $searchResultSet->getFacets()->getByPosition(0);

        /** @var Option $option1 */ // @extensionScannerIgnoreLine
        $option1 = $facet->getOptions()->getByPosition(0);
        self::assertSame('Pages', $option1->getLabel(), 'Rendering instructions have not been applied on the facet options');
    }

    #[Test]
    public function labelCanBeUsedAsCObject(): void
    {
        $this->writeDefaultSolrTestSiteConfiguration();
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.com'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse('fake_solr_response_with_multiple_fields_facets.json');

        // before the reconstitution of the domain object from the response we expect that no facets
        // are present
        self::assertEquals([], $searchResultSet->getFacets()->getArrayCopy());

        $facetConfiguration = [
            'facets.' => [
                'type.' => [
                    'label' => 'TEXT',
                    'label.' => [
                        'value' => 'My Type with special rendering',
                        'stdWrap.' => ['case' => 'upper'],
                    ],
                    'field' => 'type_stringS',
                ],
            ],
        ];

        $configuration = $this->getConfigurationArrayFromFacetConfigurationArray($facetConfiguration);
        $processor = $this->getConfiguredReconstitutionProcessor($configuration, $searchResultSet);
        $processor->process($searchResultSet);

        /** @var OptionsFacet $facet */
        $facet = $searchResultSet->getFacets()->getByPosition(0);
        self::assertSame('MY TYPE WITH SPECIAL RENDERING', $facet->getLabel(), 'Rendering instructions have not been applied on the facet options');
    }

    protected function initializeSearchResultSetFromFakeResponse(string $fixtureFile): SearchResultSet
    {
        $searchRequestMock = $this->createMock(SearchRequest::class);

        $fakeResponseJson = file_get_contents(__DIR__ . '/Fixtures/' . $fixtureFile);
        $fakeResponse = new ResponseAdapter($fakeResponseJson);

        $searchResultSet = new SearchResultSet();
        $searchResultSet->setUsedSearchRequest($searchRequestMock);
        $searchResultSet->setResponse($fakeResponse);

        return $searchResultSet;
    }

    protected function getConfigurationArrayFromFacetConfigurationArray(array $facetConfiguration): array
    {
        $configuration = [];
        $configuration['plugin.']['tx_solr.']['search.']['faceting.'] = $facetConfiguration;
        return $configuration;
    }

    protected function getConfiguredReconstitutionProcessor(array $configuration, SearchResultSet $searchResultSet): ResultSetReconstitutionProcessor
    {
        $typoScriptConfiguration = new TypoScriptConfiguration($configuration);

        /** @var SearchRequest|MockObject $usedSearchRequestMock */
        $usedSearchRequestMock = $searchResultSet->getUsedSearchRequest();
        $usedSearchRequestMock->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($typoScriptConfiguration);
        $usedSearchRequestMock->expects(self::any())->method('getActiveFacetNames')->willReturn([]);

        return new ResultSetReconstitutionProcessor();
    }
}
