<?php
namespace ApacheSolrForTypo3\Solr\ViewHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * A viewhelper to retrieve TS values and/or objects
 * Replaces viewhelpers ###TS:path.to.some.ts.property.or.content.object###
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Ts implements ViewHelper
{

    /**
     * instance of ContentObjectRenderer
     *
     * @var ContentObjectRenderer
     */
    protected $contentObject = null;

    /**
     * Constructor
     *
     * @param array $arguments
     */
    public function __construct(array $arguments = array())
    {
    }

    /**
     * @param array $arguments
     * @return string
     */
    public function execute(array $arguments = array())
    {
        $typoScriptPath = array_shift($arguments);


        // TODO add a feature to resolve content objects
        if (count($arguments)) {
            return $this->resolveTypoScriptPath($typoScriptPath, $arguments);
        } else {
            return $this->resolveTypoScriptPath($typoScriptPath);
        }
    }

    /**
     * Resolves a TS path and returns its value
     *
     * @param string $path a TS path, separated with dots
     * @return string
     */
    protected function resolveTypoScriptPath($path, $arguments = null)
    {
        $value = '';
        $pathExploded = explode('.', trim($path));
        $lastPathSegment = array_pop($pathExploded);
        /** @var \ApacheSolrForTypo3\Solr\Configuration\TypoScriptConfiguration $configuration */
        $configuration = Util::getSolrConfiguration();
        $pathBranch = $configuration->getObjectByPath($path);

        // generate ts content
        $cObj = $this->getContentObject();

        if (!isset($pathBranch[$lastPathSegment . '.'])) {
            $value = htmlspecialchars($pathBranch[$lastPathSegment]);
        } else {
            if (count($arguments)) {
                $data = array(
                    'arguments' => $arguments
                );

                $numberOfArguments = count($arguments);
                for ($i = 0; $i < $numberOfArguments; $i++) {
                    $data['argument_' . $i] = $arguments[$i];
                }
                $cObj->start($data);
            }


            $value = $cObj->cObjGetSingle(
                $pathBranch[$lastPathSegment],
                $pathBranch[$lastPathSegment . '.']
            );
        }

        return $value;
    }

    /**
     * @param ContentObjectRenderer $contentObject
     */
    public function setContentObject($contentObject)
    {
        $this->contentObject = $contentObject;
    }


    /**
     * Returns the viewhelper's internal cObj. If it has not been used yet, a
     * new cObj ist instantiated on demand.
     *
     * @return ContentObjectRenderer A content object.
     */
    protected function getContentObject()
    {
        if (is_null($this->contentObject)) {
            $this->contentObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
        }

        return $this->contentObject;
    }
}
