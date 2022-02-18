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

/**
 * The Operator ParameterProvider is responsible to build the solr query parameters
 * that are needed for the operator q.op.
 */
class Operator extends AbstractDeactivatable
{
    const OPERATOR_AND = 'AND';
    const OPERATOR_OR = 'OR';

    /**
     * @var string
     */
    protected $operator = 'AND';

    /**
     * Faceting constructor.
     *
     * @param bool $isEnabled
     * @param string $operator
     */
    public function __construct($isEnabled, $operator = Operator::OPERATOR_AND)
    {
        $this->isEnabled = $isEnabled;
        $this->setOperator($operator);
    }

    /**
     * @param string $operator
     */
    public function setOperator($operator)
    {
        if (!in_array($operator, [self::OPERATOR_AND, self::OPERATOR_OR])) {
            throw new \InvalidArgumentException("Invalid operator");
        }

        $this->operator = $operator;
    }

    /**
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @return Operator
     */
    public static function getEmpty(): Operator
    {
        return new Operator(false);
    }

    /**
     * @return Operator
     */
    public static function getAnd(): Operator
    {
        return new Operator(true, static::OPERATOR_AND);
    }

    /**
     * @return Operator
     */
    public static function getOr(): Operator
    {
        return new Operator(true, static::OPERATOR_OR);
    }

    /**
     * @param string $operator
     * @return Operator
     */
    public static function fromString($operator)
    {
        return new Operator(true, $operator);
    }
}
