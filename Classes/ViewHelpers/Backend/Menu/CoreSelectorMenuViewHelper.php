<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Backend\Menu;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\ViewHelpers\Backend\AbstractSolrTagBasedViewHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Core selector menu view helper
 *
 */
class CoreSelectorMenuViewHelper extends AbstractSolrTagBasedViewHelper
{

    /**
     * @var string
     */
    protected $tagName = 'select';

    /**
     * @var \ApacheSolrForTypo3\Solr\Service\ModuleDataStorageService
     * @inject
     */
    protected $moduleDataStorageService;

    /**
     * Initialize the arguments.
     *
     * @return void
     */
    public function initializeArguments()
    {
        parent::initializeArguments();

        $this->registerUniversalTagAttributes();
    }

    /**
     * Renders a core select field
     *
     * @return string
     */
    public function render()
    {
        $this->tag->addAttribute('onchange',
            'jumpToUrl(document.URL + \'&tx_solr_tools_solradministration[action]=setCore&tx_solr_tools_solradministration[core]=\'+this.options[this.selectedIndex].value,this);');

        $currentSite = $this->moduleDataStorageService->loadModuleData()->getSite();
        $currentCore = $this->moduleDataStorageService->loadModuleData()->getCore();

        $connectionManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager');
        $cores = $connectionManager->getConnectionsBySite($currentSite);

        if (empty($currentCore)) {
            $currentCore = $cores[0];
        }

        $options = '';
        foreach ($cores as $core) {
            $selectedAttribute = '';
            if ($core->getPath() == $currentCore) {
                $selectedAttribute = ' selected="selected"';
            }

            $options .= '<option value="' . $core->getPath() . '"' . $selectedAttribute . '>'
                . $core->getPath()
                . '</option>';
        }

        $this->tag->setContent($options);

        return '<div class="coreSelector"><label>Select Core: </label>' . $this->tag->render() . '</div>';
    }
}
