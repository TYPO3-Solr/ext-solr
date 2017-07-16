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

use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * Manage Synonyms and Stop words in Backend Module
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
        $rawSynonyms = $this->selectedSolrCoreConnection->getSynonyms();
        foreach ($rawSynonyms as $baseWord => $synonymList) {
            $synonyms[$baseWord] = implode(', ', $synonymList);
        }

        $stopWords = $this->selectedSolrCoreConnection->getStopWords();
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
     * @return void
     */
    public function addSynonymsAction(string $baseWord, string $synonyms)
    {
        if (empty($baseWord) || empty($synonyms)) {
            $this->addFlashMessage(
                'Please provide a base word and synonyms.',
                'Missing parameter',
                FlashMessage::ERROR
            );
        } else {
            $baseWord = $this->stringUtility->toLower($baseWord);
            $synonyms = $this->stringUtility->toLower($synonyms);

            $this->selectedSolrCoreConnection->addSynonym(
                $baseWord,
                GeneralUtility::trimExplode(',', $synonyms, true)
            );
            $this->selectedSolrCoreConnection->reloadCore();

            $this->addFlashMessage(
                '"' . $synonyms . '" added as synonyms for base word "' . $baseWord . '"'
            );
        }

        $this->redirect('index');
    }

    /**
     * @return void
     */
    public function exportStopWordsAction()
    {
        $this->exportFile(implode(PHP_EOL, $this->selectedSolrCoreConnection->getStopWords()), 'stopwords');
    }

    /**
     * @return void
     */
    public function exportSynonymsAction()
    {
        $synonyms = $this->selectedSolrCoreConnection->getSynonyms();
        $contentLines = '';
        if (count($synonyms)) {
            foreach ($synonyms as $synonymBaseWord => $synonymWords) {
                $contentLines[] = ((in_array($synonymBaseWord, $synonymWords)) ?
                        '' : ($synonymBaseWord . ' => ')) . implode(',', $synonymWords);

            }
            $this->exportFile(implode(PHP_EOL, $contentLines), 'synonyms');

        } else {
            $this->addFlashMessage(
                'No synonyms to export.'
            );
        }
        $this->redirect('index');

    }

    /**
     * @param array $synonymFileUpload
     * @return void
     */
    public function uploadSynonymFileAction(array $synonymFileUpload)
    {
        $destinationFile = PATH_site . 'typo3temp/' . md5($_COOKIE['PHPSESSID']) .$synonymFileUpload['name'];
        GeneralUtility::upload_copy_move($synonymFileUpload['tmp_name'], $destinationFile);

        $fileHandler = fopen($destinationFile, 'r');
        while ($line = fgets($fileHandler)) {
            $lineParts = GeneralUtility::trimExplode('=>', $line, true);

            if (isset($lineParts[1])) {
                $baseWord = $this->stringUtility->toLower($lineParts[0]);
                $synonyms = GeneralUtility::trimExplode(',', $this->stringUtility->toLower($lineParts[1]), true);
            } else {
                $synonyms = GeneralUtility::trimExplode(',', $this->stringUtility->toLower($lineParts[0]), true);
                $baseWord = $this->stringUtility->toLower(reset($synonyms));
            }

            if (isset($baseWord) && !empty($synonyms)) {
                if ($this->selectedSolrCoreConnection->getSynonyms($baseWord)) {
                    $this->selectedSolrCoreConnection->deleteSynonym($baseWord);
                }
                $this->selectedSolrCoreConnection->addSynonym(
                    $baseWord,
                    $synonyms
                );
                $this->selectedSolrCoreConnection->reloadCore();
                $this->addFlashMessage(
                    '"' . implode(',', $synonyms) . '" added as synonyms for base word "' . $baseWord . '"'
                );
            }
        }
        fclose($fileHandler);
        $this->redirect('index');

    }
    /**
     * @param array $stopwordsFileUpload
     * @return void
     */
    public function uploadStopWordsFileAction(array $stopwordsFileUpload)
    {
        $destinationFile = PATH_site . 'typo3temp/' . md5($_COOKIE['PHPSESSID']) .$stopwordsFileUpload['name'];
        GeneralUtility::upload_copy_move($stopwordsFileUpload['tmp_name'], $destinationFile);

        $this->saveStopWordsAction(file_get_contents($destinationFile));

    }

    public function deleteAllSynonymsAction()
    {
        $synonyms = $this->selectedSolrCoreConnection->getSynonyms();
        $allSynonymsCouldBeDeleted = true;

        foreach ($synonyms as $baseWord => $synonym) {
            $deleteResponse = $this->selectedSolrCoreConnection->deleteSynonym($baseWord);
            $allSynonymsCouldBeDeleted = $allSynonymsCouldBeDeleted && $deleteResponse->getHttpStatus() == 200;
        }

        $reloadResponse = $this->selectedSolrCoreConnection->reloadCore();

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
        $deleteResponse = $this->selectedSolrCoreConnection->deleteSynonym($baseWord);
        $reloadResponse = $this->selectedSolrCoreConnection->reloadCore();

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
     * @return void
     */
    public function saveStopWordsAction(string $stopWords)
    {
        // lowercase stopword before saving because terms get lowercased before stopword filtering
        $newStopWords = $this->stringUtility->toLower($stopWords);
        $newStopWords = GeneralUtility::trimExplode("\n", $newStopWords, true);
        $oldStopWords = $this->selectedSolrCoreConnection->getStopWords();

        $wordsRemoved = true;
        $removedStopWords = array_diff($oldStopWords, $newStopWords);
        foreach ($removedStopWords as $word) {
            $response = $this->selectedSolrCoreConnection->deleteStopWord($word);
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

        $wordsAdded = true;
        $addedStopWords = array_diff($newStopWords, $oldStopWords);
        if (!empty($addedStopWords)) {
            $wordsAddedResponse = $this->selectedSolrCoreConnection->addStopWords($addedStopWords);
            $wordsAdded = ($wordsAddedResponse->getHttpStatus() == 200);
        }

        $reloadResponse = $this->selectedSolrCoreConnection->reloadCore();
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
     */
    protected function exportFile($content, $type = 'synonyms')
    {
        // output headers so that the file is downloaded rather than displayed
        header('Content-type: text/plain');
        header('Content-disposition: attachment; filename ='. $type . '_' .
            $this->selectedSolrCoreConnection->getCoreName(). '.txt');
        echo $content;
        die();
    }
}
