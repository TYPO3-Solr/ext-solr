<?php

declare(strict_types=1);

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Fixtures;

use ApacheSolrForTypo3\Solr\IndexQueue\IndexingService;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use Closure;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Test-only subclass of IndexingService that adds the typo3.testing.context
 * attribute to sub-requests. Required because the testing-framework's
 * FrontendUserHandler middleware unconditionally reads this attribute.
 */
class IndexingServiceForTesting extends IndexingService
{
    protected function buildServerRequest(Item $item, int $language): ServerRequestInterface
    {
        $request = parent::buildServerRequest($item, $language);

        if ($request->getAttribute('typo3.testing.context') === null) {
            $request = $request->withAttribute(
                'typo3.testing.context',
                new InternalRequestContext(),
            );
        }

        return $request;
    }

    /**
     * Create from an existing IndexingService by copying its DI-injected dependencies.
     */
    public static function fromProductionService(IndexingService $source): self
    {
        return Closure::bind(static function () use ($source): IndexingServiceForTesting {
            return new IndexingServiceForTesting(
                $source->frontendApplication,
                $source->connectionManager,
                $source->pagesRepository,
                $source->logger,
                $source->resultCollector,
                $source->siteFinder,
            );
        }, null, IndexingService::class)();
    }
}
