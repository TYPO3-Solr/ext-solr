<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsAllowedCallable;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Class CategoryPathProvider
 *
 * Test class that returns a fixture path list just for testing.
 */
class CategoryPathProvider
{
    /**
     * Returns a list of paths concatenated with , only for testing
     */
    #[AsAllowedCallable]
    public function getPaths(
        string $content,
        array $conf,
        ServerRequestInterface $request,
    ): string {
        /** @var PageInformation|null $pageInformation */
        $pageInformation = $request->getAttribute('frontend.page.information');
        $pageId = $pageInformation?->getId() ?? 0;

        if ($pageId === 2) {
            return 'Men/Shoes \/ Socks,Accessoires/Socks';
        }

        return '';
    }
}
