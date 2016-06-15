<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Markus Friedrich <markus.friedrich@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\ViewHelper\Multivalue;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * PHP Unit test for multi value view helper (ApacheSolrForTypo3\Solr\ViewHelper\Multivalue)
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class MultivalueTest extends AbstractViewHelperTest
{
    /**
     * Returns the multi value view helper
     *
     * @return Multivalue
     */
    protected function getMultivalueViewHelper()
    {
        return GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ViewHelper\\Multivalue');
    }

    /**
     * Sets the default glue in TypoScript
     *
     * @param string $glue
     * @return void
     */
    protected function setDefaultGlue($glue)
    {
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['viewHelpers.']['multivalue.']['glue'] = $glue;
        $this->configurationManager->reset();
    }

    /**
     * Provides data for the implosion tests
     *
     * @return array
     */
    public function implodeMultiValuesDataProvider()
    {
        return array(
            array('defaultGlue' => ',', 'multiValue' => serialize(array(1, 2, 3)), 'glue' => ';', 'expectedResult' => '1;2;3'),
            array('defaultGlue' => ',', 'multiValue' => serialize(array(1, 2, 3)), 'glue' => null, 'expectedResult' => '1,2,3'),
            array('defaultGlue' => null, 'multiValue' => serialize(array(1, 2, 3)), 'glue' => null, 'expectedResult' => '1, 2, 3'),
            array('defaultGlue' => ',', 'multiValue' => 'no-array', 'glue' => ',', 'expectedResult' => 'no-array')
        );
    }

    /**
     * Tests the multi value implosion
     *
     * @dataProvider implodeMultiValuesDataProvider
     * @test
     *
     * @param string $defaultGlue the default glue provided via TypoScript
     * @param string $glue the requested glue
     * @param mixed $multiValue the multi value to implode
     * @param string $expectedResult
     * @return void
     */
    public function canImplodeMultiValues($defaultGlue, $multiValue, $glue, $expectedResult)
    {
        $arguments = array($multiValue);
        if (!is_null($glue)) {
            $arguments[] = $glue;
        }
        $this->setDefaultGlue($defaultGlue);
        $implodedValue = $this->getMultivalueViewHelper()->execute($arguments);
        $this->assertEquals($expectedResult, $implodedValue, 'Returned value "' . $implodedValue . '"  doesn\'t match the expected result: ' . $expectedResult);
    }
}
