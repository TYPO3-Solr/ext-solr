<?php
namespace ApacheSolrForTypo3\Solr\Controller\Backend\Search;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solrs-support@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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

use ApacheSolrForTypo3\Solr\Utility\ManagedResourcesUtility;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * Manage Synonyms and Stop words in Backend Module
 * @property \TYPO3\CMS\Extbase\Mvc\Web\Response $response
 */
class CoreOptimizationModuleController extends AbstractModuleController
{
    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     * @return void
     */
    protected function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);

        $this->generateCoreSelectorMenuUsingPageTree();
        /* @var ModuleTemplate $module */ // holds the state of chosen tab
        $module = $this->objectManager->get(ModuleTemplate::class);
        $coreOptimizationTabs = $module->getDynamicTabMenu([], 'coreOptimization');
        $this->view->assign('tabs', $coreOptimizationTabs);
    }

    /**
     * Gets synonyms and stopwords for the currently selected core
     *
     * @return void
     */
    public function indexAction()
    {
        if ($this->selectedSolrCoreConnection === null) {
            $this->view->assign('can_not_proceed', true);
            return;
        }

        $synonyms = [];
        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
        $rawSynonyms = $coreAdmin->getSynonyms();
        foreach ($rawSynonyms as $baseWord => $synonymList) {
            $synonyms[$baseWord] = implode(', ', $synonymList);
        }

        $stopWords = $coreAdmin->getStopWords();
        $this->view->assignMultiple([
            'synonyms' => $synonyms,
            'stopWords' => implode(PHP_EOL, $stopWords),
            'stopWordsCount' => count($stopWords)
        ]);
    }

    /**
     * Add synonyms to selected core
     *
     * @param string $baseWord
     * @param string $synonyms
     * @param bool $overrideExisting
     * @return void
     */
    public function addSynonymsAction(string $baseWord, string $synonyms, $overrideExisting)
    {
        if (empty($baseWord) || empty($synonyms)) {
            $this->addFlashMessage(
                'Please provide a base word and synonyms.',
                'Missing parameter',
                FlashMessage::ERROR
            );
        } else {
            $baseWord = mb_strtolower($baseWord);
            $synonyms = mb_strtolower($synonyms);

            $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
            if ($overrideExisting && $coreAdmin->getSynonyms($baseWord)) {
                $coreAdmin->deleteSynonym($baseWord);
            }
            $coreAdmin->addSynonym($baseWord, GeneralUtility::trimExplode(',', $synonyms, true));
            $coreAdmin->reloadCore();

            $this->addFlashMessage(
                '"' . $synonyms . '" added as synonyms for base word "' . $baseWord . '"'
            );
        }

        $this->redirect('index');
    }

    /**
     * @param string $fileFormat
     * @return void
     */
    public function exportStopWordsAction($fileFormat = 'txt')
    {
        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
        $this->exportFile(
            implode(PHP_EOL, $coreAdmin->getStopWords()),
            'stopwords',
            $fileFormat
        );
    }

    /**
     * Exports synonyms to a download file.
     *
     * @param string $fileFormat
     * @return string
     */
    public function exportSynonymsAction($fileFormat = 'txt')
    {
        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
        $synonyms = $coreAdmin->getSynonyms();
        return $this->exportFile(ManagedResourcesUtility::exportSynonymsToTxt($synonyms), 'synonyms', $fileFormat);
    }

    /**
     * @param array $synonymFileUpload
     * @param bool $overrideExisting
     * @param bool $deleteSynonymsBefore
     * @return void
     */
    public function importSynonymListAction(array $synonymFileUpload, $overrideExisting, $deleteSynonymsBefore)
    {
        if ($deleteSynonymsBefore) {
            $this->deleteAllSynonyms();
        }

        $fileLines = ManagedResourcesUtility::importSynonymsFromPlainTextContents($synonymFileUpload);
        $synonymCount = 0;

        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
        foreach ($fileLines as $baseWord => $synonyms) {
            if (!isset($baseWord) || empty($synonyms)) {
                continue;
            }
            $this->deleteExistingSynonym($overrideExisting, $deleteSynonymsBefore, $baseWord);
            $coreAdmin->addSynonym($baseWord, $synonyms);
            $synonymCount++;
        }

        $coreAdmin->reloadCore();
        $this->addFlashMessage(
            $synonymCount . ' synonyms imported.'
        );
        $this->redirect('index');

    }

    /**
     * @param array $stopwordsFileUpload
     * @param bool $replaceStopwords
     * @return void
     */
    public function importStopWordListAction(array $stopwordsFileUpload, $replaceStopwords)
    {
        $this->saveStopWordsAction(
            ManagedResourcesUtility::importStopwordsFromPlainTextContents($stopwordsFileUpload),
            $replaceStopwords
        );
    }

    /**
     * Delete complete synonym list
     *
     * @return void
     */
    public function deleteAllSynonymsAction()
    {
        $allSynonymsCouldBeDeleted = $this->deleteAllSynonyms();

        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
        $reloadResponse = $coreAdmin->reloadCore();

        if ($allSynonymsCouldBeDeleted
            && $reloadResponse->getHttpStatus() == 200
        ) {
            $this->addFlashMessage(
                'All synonym removed.'
            );
        } else {
            $this->addFlashMessage(
                'Failed to remove all synonyms.',
                'An error occurred',
                FlashMessage::ERROR
            );
        }
        $this->redirect('index');
    }

    /**
     * Deletes a synonym mapping by its base word.
     *
     * @param string $baseWord Synonym mapping base word
     */
    public function deleteSynonymsAction($baseWord)
    {
        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
        $deleteResponse = $coreAdmin->deleteSynonym($baseWord);
        $reloadResponse = $coreAdmin->reloadCore();

        if ($deleteResponse->getHttpStatus() == 200
            && $reloadResponse->getHttpStatus() == 200
        ) {
            $this->addFlashMessage(
                'Synonym removed.'
            );
        } else {
            $this->addFlashMessage(
                'Failed to remove synonym.',
                'An error occurred',
                FlashMessage::ERROR
            );
        }

        $this->redirect('index');
    }

    /**
     * Saves the edited stop word list to Solr
     *
     * @param string $stopWords
     * @param bool $replaceStopwords
     * @return void
     */
    public function saveStopWordsAction(string $stopWords, $replaceStopwords = true)
    {
        // lowercase stopword before saving because terms get lowercased before stopword filtering
        $newStopWords = mb_strtolower($stopWords);
        $newStopWords = GeneralUtility::trimExplode("\n", $newStopWords, true);

        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
        $oldStopWords = $coreAdmin->getStopWords();

        if ($replaceStopwords) {
            $removedStopWords = array_diff($oldStopWords, $newStopWords);
            $wordsRemoved = $this->removeStopsWordsFromIndex($removedStopWords);
        } else {
            $wordsRemoved = true;
        }

        $wordsAdded = true;
        $addedStopWords = array_diff($newStopWords, $oldStopWords);
        if (!empty($addedStopWords)) {
            $wordsAddedResponse = $coreAdmin->addStopWords($addedStopWords);
            $wordsAdded = ($wordsAddedResponse->getHttpStatus() == 200);
        }

        $reloadResponse = $coreAdmin->reloadCore();
        if ($wordsRemoved && $wordsAdded && $reloadResponse->getHttpStatus() == 200) {
            $this->addFlashMessage(
                'Stop Words Updated.'
            );
        }

        $this->redirect('index');
    }

    /**
     * @param string $content
     * @param string $type
     * @param string $fileExtension
     * @return string
     */
    protected function exportFile($content, $type = 'synonyms', $fileExtension = 'txt') : string
    {
        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();

        $this->response->setHeader('Content-type', 'text/plain', true);
        $this->response->setHeader('Cache-control', 'public', true);
        $this->response->setHeader('Content-Description', 'File transfer', true);
        $this->response->setHeader(
            'Content-disposition',
            'attachment; filename =' . $type . '_' .
            $coreAdmin->getPrimaryEndpoint()->getCore() . '.' . $fileExtension,
            true
        );

        $this->response->setContent($content);
        $this->sendFileResponse();
    }

    /**
     * This method send the headers and content and does an exit, since without the exit TYPO3 produces and error.
     * @return void
     */
    protected function sendFileResponse()
    {
        $this->response->sendHeaders();
        $this->response->send();
        $this->response->shutdown();

        exit();
    }

    /**
     * Delete complete synonym list form solr
     *
     * @return bool
     */
    protected function deleteAllSynonyms() : bool
    {
        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
        $synonyms = $coreAdmin->getSynonyms();
        $allSynonymsCouldBeDeleted = true;

        foreach ($synonyms as $baseWord => $synonym) {
            $deleteResponse = $coreAdmin->deleteSynonym($baseWord);
            $allSynonymsCouldBeDeleted = $allSynonymsCouldBeDeleted && $deleteResponse->getHttpStatus() == 200;
        }

        return $allSynonymsCouldBeDeleted;
    }

    /**
     * @param $stopwordsToRemove
     * @return bool
     */
    protected function removeStopsWordsFromIndex($stopwordsToRemove) : bool
    {
        $wordsRemoved = true;
        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();

        foreach ($stopwordsToRemove as $word) {
            $response = $coreAdmin->deleteStopWord($word);
            if ($response->getHttpStatus() != 200) {
                $wordsRemoved = false;
                $this->addFlashMessage(
                    'Failed to remove stop word "' . $word . '".',
                    'An error occurred',
                    FlashMessage::ERROR
                );
                break;
            }
        }

        return $wordsRemoved;
    }

    /**
     * Delete synonym entry if selceted before
     * @param bool $overrideExisting
     * @param bool $deleteSynonymsBefore
     * @param string $baseWord
     */
    protected function deleteExistingSynonym($overrideExisting, $deleteSynonymsBefore, $baseWord)
    {
        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();

        if (!$deleteSynonymsBefore &&
            $overrideExisting &&
            $coreAdmin->getSynonyms($baseWord)
        ) {
            $coreAdmin->deleteSynonym($baseWord);
        }

    }
}
