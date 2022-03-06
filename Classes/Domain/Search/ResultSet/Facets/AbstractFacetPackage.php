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

use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

/**
 * Class AbstractFacetPackage
 */
abstract class AbstractFacetPackage
{
    /**
     * @var ObjectManagerInterface|null
     */
    protected ?ObjectManagerInterface $objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function setObjectManager(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @return string
     */
    abstract public function getParserClassName(): string;

    /**
     * @return FacetParserInterface
     * @throws InvalidFacetPackageException
     */
    public function getParser(): FacetParserInterface
    {
        $parser = $this->objectManager->get($this->getParserClassName());
        if (!$parser instanceof FacetParserInterface) {
            throw new InvalidFacetPackageException('Invalid parser for package ' . __CLASS__);
        }
        return $parser;
    }

    /**
     * @return string
     */
    public function getUrlDecoderClassName(): string
    {
        return DefaultUrlDecoder::class;
    }

    /**
     * @throws InvalidUrlDecoderException
     * @return FacetUrlDecoderInterface
     */
    public function getUrlDecoder(): FacetUrlDecoderInterface
    {
        $urlDecoder = $this->objectManager->get($this->getUrlDecoderClassName());
        if (!$urlDecoder instanceof FacetUrlDecoderInterface) {
            throw new InvalidUrlDecoderException('Invalid url-decoder for package ' . __CLASS__);
        }
        return $urlDecoder;
    }

    /**
     * @return string
     */
    public function getQueryBuilderClassName(): string
    {
        return DefaultFacetQueryBuilder::class;
    }

    /**
     * @throws InvalidQueryBuilderException
     * @return FacetQueryBuilderInterface
     */
    public function getQueryBuilder(): FacetQueryBuilderInterface
    {
        $urlDecoder = $this->objectManager->get($this->getQueryBuilderClassName());
        if (!$urlDecoder instanceof FacetQueryBuilderInterface) {
            throw new InvalidQueryBuilderException('Invalid query-builder for package ' . __CLASS__);
        }
        return $urlDecoder;
    }
}
