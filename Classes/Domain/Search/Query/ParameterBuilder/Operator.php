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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;

/**
 * The Operator ParameterProvider is responsible to build the solr query parameters
 * that are needed for the operator q.op.
 */
class Operator extends AbstractDeactivatable
{
    public const OPERATOR_AND = 'AND';
    public const OPERATOR_OR = 'OR';

    protected string $operator = 'AND';

    /**
     * Faceting constructor.
     */
    public function __construct(
        bool $isEnabled,
        string $operator = Operator::OPERATOR_AND,
    ) {
        $this->isEnabled = $isEnabled;
        $this->setOperator($operator);
    }

    public function setOperator(string $operator): void
    {
        if (!in_array($operator, [self::OPERATOR_AND, self::OPERATOR_OR])) {
            throw new InvalidArgumentException(
                'Invalid operator',
                8402616466,
            );
        }

        $this->operator = $operator;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public static function getEmpty(): Operator
    {
        return new Operator(false);
    }

    public static function getAnd(): Operator
    {
        return new Operator(true, static::OPERATOR_AND);
    }

    public static function getOr(): Operator
    {
        return new Operator(true, static::OPERATOR_OR);
    }

    public static function fromString(string $operator): Operator
    {
        return new Operator(true, $operator);
    }
}
