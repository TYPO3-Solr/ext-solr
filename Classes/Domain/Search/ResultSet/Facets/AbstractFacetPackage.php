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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class AbstractFacetPackage
 */
abstract class AbstractFacetPackage
{
    abstract public function getParserClassName(): string;

    /**
     * @throws InvalidFacetPackageException
     */
    public function getParser(): FacetParserInterface
    {
        $parser = GeneralUtility::makeInstance($this->getParserClassName());
        if (!$parser instanceof FacetParserInterface) {
            throw new InvalidFacetPackageException(
                'Invalid parser for package ' . __CLASS__,
                8634008284,
            );
        }

        return $parser;
    }

    public function getUrlDecoderClassName(): string
    {
        return DefaultUrlDecoder::class;
    }

    /**
     * @throws InvalidUrlDecoderException
     */
    public function getUrlDecoder(): FacetUrlDecoderInterface
    {
        $urlDecoder = GeneralUtility::makeInstance($this->getUrlDecoderClassName());
        if (!$urlDecoder instanceof FacetUrlDecoderInterface) {
            throw new InvalidUrlDecoderException(
                'Invalid url-decoder for package ' . __CLASS__,
                9144462614,
            );
        }

        return $urlDecoder;
    }

    public function getQueryBuilderClassName(): string
    {
        return DefaultFacetQueryBuilder::class;
    }

    /**
     * @throws InvalidQueryBuilderException
     */
    public function getQueryBuilder(): FacetQueryBuilderInterface
    {
        $facetQueryBuilder = GeneralUtility::makeInstance($this->getQueryBuilderClassName());
        if (!$facetQueryBuilder instanceof FacetQueryBuilderInterface) {
            throw new InvalidQueryBuilderException(
                'Invalid query-builder for package ' . __CLASS__,
                5405805432,
            );
        }

        return $facetQueryBuilder;
    }
}
