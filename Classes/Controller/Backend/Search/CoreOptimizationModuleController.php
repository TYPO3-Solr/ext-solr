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

namespace ApacheSolrForTypo3\Solr\Controller\Backend\Search;

use ApacheSolrForTypo3\Solr\System\Mvc\Backend\Component\Exception\InvalidViewObjectNameException;
use ApacheSolrForTypo3\Solr\Utility\ManagedResourcesUtility;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\View\ViewInterface;

/**
 * Manage Synonyms and Stop words in Backend Module
 * @property ResponseInterface $response
 */
class CoreOptimizationModuleController extends AbstractModuleController
{
    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     * @throws DBALDriverException
     * @throws InvalidViewObjectNameException
     * @throws Throwable
     * @noinspection PhpUnused
     */
    protected function initializeView($view)
    {
        parent::initializeView($view);
        $this->generateCoreSelectorMenuUsingPageTree();
        $coreOptimizationTabs = $this->moduleTemplate->getDynamicTabMenu([], 'coreOptimization');
        $this->view->assign('tabs', $coreOptimizationTabs);
    }

    /**
     * Gets synonyms and stopwords for the currently selected core
     *
     * @return ResponseInterface
     *
     * @noinspection PhpUnused
     */
    public function indexAction(): ResponseInterface
    {
        if ($this->selectedSolrCoreConnection === null) {
            $this->view->assign('can_not_proceed', true);
            return $this->getModuleTemplateResponse();
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
            'stopWordsCount' => count($stopWords),
        ]);

        return $this->getModuleTemplateResponse();
    }

    /**
     * Add synonyms to selected core
     *
     * @param string $baseWord
     * @param string $synonyms
     * @param bool $overrideExisting
     * @return ResponseInterface
     *
     * @noinspection PhpUnused
     */
    public function addSynonymsAction(string $baseWord, string $synonyms, bool $overrideExisting): ResponseInterface
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

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * @param string $fileFormat
     * @return ResponseInterface
     *
     * @noinspection PhpUnused
     */
    public function exportStopWordsAction(string $fileFormat = 'txt'): ResponseInterface
    {
        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
        return $this->exportFile(
            implode(PHP_EOL, $coreAdmin->getStopWords()),
            'stopwords',
            $fileFormat
        );
    }

    /**
     * Exports synonyms to a download file.
     *
     * @param string $fileFormat
     * @return ResponseInterface
     *
     * @noinspection PhpUnused
     */
    public function exportSynonymsAction(string $fileFormat = 'txt'): ResponseInterface
    {
        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
        $synonyms = $coreAdmin->getSynonyms();
        return $this->exportFile(ManagedResourcesUtility::exportSynonymsToTxt($synonyms), 'synonyms', $fileFormat);
    }

    /**
     * @param array $synonymFileUpload
     * @param bool $overrideExisting
     * @param bool $deleteSynonymsBefore
     * @return ResponseInterface
     *
     * @noinspection PhpUnused
     */
    public function importSynonymListAction(
        array $synonymFileUpload,
        bool $overrideExisting = false,
        bool $deleteSynonymsBefore = false
    ): ResponseInterface {
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
        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * @param array $stopwordsFileUpload
     * @param bool $replaceStopwords
     *
     * @return ResponseInterface
     *
     * @noinspection PhpUnused
     */
    public function importStopWordListAction(array $stopwordsFileUpload, bool $replaceStopwords): ResponseInterface
    {
        $this->saveStopWordsAction(
            ManagedResourcesUtility::importStopwordsFromPlainTextContents($stopwordsFileUpload),
            $replaceStopwords
        );
        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * Delete complete synonym list
     *
     * @return ResponseInterface
     *
     * @noinspection PhpUnused
     */
    public function deleteAllSynonymsAction(): ResponseInterface
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
        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * Deletes a synonym mapping by its base word.
     *
     * @param string $baseWord Synonym mapping base word
     *
     * @return ResponseInterface
     *
     * @noinspection PhpUnused
     */
    public function deleteSynonymsAction(string $baseWord): ResponseInterface
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

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * Saves the edited stop word list to Solr
     *
     * @param string $stopWords
     * @param bool $replaceStopwords
     *
     * @return ResponseInterface
     *
     * @noinspection PhpUnused
     */
    public function saveStopWordsAction(string $stopWords, bool $replaceStopwords = true): ResponseInterface
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

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * @param string $content
     * @param string $type
     * @param string $fileExtension
     *
     * @return ResponseInterface
     */
    protected function exportFile(string $content, string $type = 'synonyms', string $fileExtension = 'txt'): ResponseInterface
    {
        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
        return  $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Cache-control', 'public')
            ->withHeader('Content-Description', 'File transfer')
            ->withHeader('Content-Description', 'File transfer')
            ->withHeader(
                'Content-disposition',
                'attachment; filename =' . $type . '_' . $coreAdmin->getPrimaryEndpoint()->getCore() . '.' . $fileExtension
            )
            ->withBody($this->streamFactory->createStream($content));
    }

    /**
     * Delete complete synonym list form solr
     *
     * @return bool
     */
    protected function deleteAllSynonyms(): bool
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
    protected function removeStopsWordsFromIndex($stopwordsToRemove): bool
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
     * Delete synonym entry if selected before
     * @param bool $overrideExisting
     * @param bool $deleteSynonymsBefore
     * @param string $baseWord
     */
    protected function deleteExistingSynonym(bool $overrideExisting, bool $deleteSynonymsBefore, string $baseWord)
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
