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

use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationGuard;
use ApacheSolrForTypo3\Solr\Utility\ManagedResourcesUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\View\ViewInterface;

/**
 * Manage Synonyms and Stop words in Backend Module
 * @property ResponseInterface $response
 */
class CoreOptimizationModuleController extends AbstractModuleController
{
    private const LANGUAGE_DOMAIN = 'solr.modules.core_optimization';

    /**
     * Set up the doc header properly here
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     * @param ViewInterface $view
     *
     * @noinspection PhpUnused
     */
    protected function initializeView($view): void
    {
        parent::initializeView($view);

        $this->generateCoreSelectorMenuUsingPageTree();
    }

    /**
     * Gets synonyms and stopwords for the currently selected core
     *
     * @noinspection PhpUnused
     */
    public function indexAction(): ResponseInterface
    {
        if ($this->selectedSolrCoreConnection === null) {
            $this->moduleTemplate->assign('can_not_proceed', true);
            return $this->moduleTemplate->renderResponse('Backend/Search/CoreOptimizationModule/Index');
        }

        $managedResources = $this->runSolrCommunicationGuarded(function (): array {
            $synonyms = [];
            $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
            $rawSynonyms = $coreAdmin->getSynonyms();
            foreach ($rawSynonyms as $baseWord => $synonymList) {
                $synonyms[$baseWord] = implode(', ', $synonymList);
            }

            $stopWords = $coreAdmin->getStopWords();
            return [
                'synonyms' => $synonyms,
                'stopWords' => implode(PHP_EOL, $stopWords),
                'stopWordsCount' => count($stopWords),
            ];
        }) ?? [
            'synonyms' => [],
            'stopWords' => '',
            'stopWordsCount' => 0,
        ];

        $this->moduleTemplate->assignMultiple([
            'synonyms' => $managedResources['synonyms'],
            'stopWords' => $managedResources['stopWords'],
            'stopWordsCount' => $managedResources['stopWordsCount'],
        ]);

        return $this->moduleTemplate->renderResponse('Backend/Search/CoreOptimizationModule/Index');
    }

    /**
     * Add synonyms to selected core
     *
     * @noinspection PhpUnused
     */
    public function addSynonymsAction(string $baseWord, string $synonyms, bool $overrideExisting): ResponseInterface
    {
        $baseWord = mb_strtolower(trim($baseWord));
        $synonymList = GeneralUtility::trimExplode(',', mb_strtolower($synonyms), true);

        if ($baseWord === '' || $synonymList === []) {
            $this->addFlashMessage(
                $this->translate('flash.synonyms.missingParameter.message'),
                $this->translate('flash.title.missingParameter'),
                ContextualFeedbackSeverity::ERROR,
            );
        } else {
            $this->runSolrCommunicationGuarded(function () use ($baseWord, $synonymList, $overrideExisting): void {
                $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
                if ($overrideExisting && $coreAdmin->getSynonyms($baseWord)) {
                    $coreAdmin->deleteSynonym($baseWord);
                }
                $coreAdmin->addSynonym($baseWord, $synonymList);
                $coreAdmin->reloadCore();

                $this->addFlashMessage(
                    $this->translate('flash.synonyms.added.message', [
                        'baseWord' => $baseWord,
                        'count' => count($synonymList),
                        'synonyms' => implode(', ', $synonymList),
                    ]),
                );
            });
        }

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * @noinspection PhpUnused
     */
    public function exportStopWordsAction(string $fileFormat = 'txt'): ResponseInterface
    {
        return $this->runSolrCommunicationGuarded(function () use ($fileFormat): ResponseInterface {
            $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
            return $this->exportFile(
                implode(PHP_EOL, $coreAdmin->getStopWords()),
                'stopwords',
                $fileFormat,
            );
        }) ?? new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * Exports synonyms to a download file.
     *
     * @noinspection PhpUnused
     */
    public function exportSynonymsAction(string $fileFormat = 'txt'): ResponseInterface
    {
        return $this->runSolrCommunicationGuarded(function () use ($fileFormat): ResponseInterface {
            $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
            $synonyms = $coreAdmin->getSynonyms();
            return $this->exportFile(ManagedResourcesUtility::exportSynonymsToTxt($synonyms), 'synonyms', $fileFormat);
        }) ?? new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * @noinspection PhpUnused
     */
    public function importSynonymListAction(
        bool $overrideExisting = false,
        bool $deleteSynonymsBefore = false,
    ): ResponseInterface {
        $synonymFileUpload = $this->request->getUploadedFiles()['synonymFileUpload'] ?? null;
        if (!$synonymFileUpload instanceof UploadedFile) {
            $this->addFlashMessage(
                $this->translate('flash.synonyms.uploadMissing.message'),
                $this->translate('flash.title.missingParameter'),
                ContextualFeedbackSeverity::ERROR,
            );
            return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
        }

        $fileLines = ManagedResourcesUtility::importSynonymsFromPlainTextContents($synonymFileUpload);
        $this->runSolrCommunicationGuarded(function () use ($fileLines, $overrideExisting, $deleteSynonymsBefore): void {
            if ($deleteSynonymsBefore) {
                $this->deleteAllSynonyms();
            }

            $synonymCount = 0;

            $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
            foreach ($fileLines as $baseWord => $synonyms) {
                if (empty($baseWord) || empty($synonyms)) {
                    continue;
                }
                $this->deleteExistingSynonym($overrideExisting, $deleteSynonymsBefore, $baseWord);
                $coreAdmin->addSynonym($baseWord, $synonyms);
                $synonymCount++;
            }

            $coreAdmin->reloadCore();
            $this->addFlashMessage(
                $this->translate('flash.synonyms.imported.message', ['count' => $synonymCount]),
            );
        });

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * @noinspection PhpUnused
     */
    public function importStopWordListAction(bool $replaceStopwords): ResponseInterface
    {
        $stopwordsFileUpload = $this->request->getUploadedFiles()['stopwordsFileUpload'] ?? null;
        if (!$stopwordsFileUpload instanceof UploadedFile) {
            $this->addFlashMessage(
                $this->translate('flash.stopWords.uploadMissing.message'),
                $this->translate('flash.title.missingParameter'),
                ContextualFeedbackSeverity::ERROR,
            );
            return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
        }

        $this->saveStopWordsAction(
            ManagedResourcesUtility::importStopwordsFromPlainTextContents($stopwordsFileUpload),
            $replaceStopwords,
        );
        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * Delete complete synonym list
     *
     * @noinspection PhpUnused
     */
    public function deleteAllSynonymsAction(): ResponseInterface
    {
        $this->runSolrCommunicationGuarded(function (): void {
            $allSynonymsCouldBeDeleted = $this->deleteAllSynonyms();

            $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
            $reloadResponse = $coreAdmin->reloadCore();

            if ($allSynonymsCouldBeDeleted
                && $reloadResponse->getHttpStatus() == 200
            ) {
                $this->addFlashMessage(
                    $this->translate('flash.synonyms.deleteAll.success'),
                );
            } else {
                $this->addFlashMessage(
                    $this->translate('flash.synonyms.deleteAll.error'),
                    $this->translate('flash.title.error'),
                    ContextualFeedbackSeverity::ERROR,
                );
            }
        });
        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * Deletes a synonym mapping by its base word.
     *
     * @param string $baseWord Synonym mapping base word
     *
     * @noinspection PhpUnused
     */
    public function deleteSynonymsAction(string $baseWord): ResponseInterface
    {
        $this->runSolrCommunicationGuarded(function () use ($baseWord): void {
            $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
            $deleteResponse = $coreAdmin->deleteSynonym($baseWord);
            $reloadResponse = $coreAdmin->reloadCore();

            if ($deleteResponse->getHttpStatus() == 200
                && $reloadResponse->getHttpStatus() == 200
            ) {
                $this->addFlashMessage(
                    $this->translate('flash.synonyms.delete.success'),
                );
            } else {
                $this->addFlashMessage(
                    $this->translate('flash.synonyms.delete.error'),
                    $this->translate('flash.title.error'),
                    ContextualFeedbackSeverity::ERROR,
                );
            }
        });

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * Saves the edited stop word list to Solr
     *
     * @noinspection PhpUnused
     */
    public function saveStopWordsAction(string $stopWords, bool $replaceStopwords = true): ResponseInterface
    {
        // lowercase stopword before saving because terms get lowercased before stopword filtering
        $newStopWords = mb_strtolower($stopWords);
        $newStopWords = GeneralUtility::trimExplode("\n", $newStopWords, true);

        $this->runSolrCommunicationGuarded(function () use ($newStopWords, $replaceStopwords): void {
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
                    $this->translate('flash.stopWords.updated'),
                );
            }
        });

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    protected function exportFile(string $content, string $type = 'synonyms', string $fileExtension = 'txt'): ResponseInterface
    {
        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();
        return $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Cache-control', 'public')
            ->withHeader('Content-Description', 'File transfer')
            ->withHeader(
                'Content-disposition',
                'attachment; filename =' . $type . '_' . $coreAdmin->getPrimaryEndpoint()->getCore() . '.' . $fileExtension,
            )
            ->withBody($this->streamFactory->createStream($content));
    }

    /**
     * Delete complete synonym list form solr
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
     * Removes stop words from core/index.
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
                    $this->translate('flash.stopWords.removeFailed', ['word' => $word]),
                    $this->translate('flash.title.error'),
                    ContextualFeedbackSeverity::ERROR,
                );
                break;
            }
        }

        return $wordsRemoved;
    }

    private function runSolrCommunicationGuarded(callable $operation): mixed
    {
        return GeneralUtility::makeInstance(SolrCommunicationGuard::class)->run(
            $operation,
            function (SolrCommunicationException $exception) {
                $this->addFlashMessage(
                    $this->translate('flash.solrUnavailable.message'),
                    $this->translate('flash.title.error'),
                    ContextualFeedbackSeverity::ERROR,
                );
                return null;
            },
        );
    }

    private function translate(string $key, array $arguments = []): string
    {
        return LocalizationUtility::translate($key, self::LANGUAGE_DOMAIN, $arguments) ?? $key;
    }

    /**
     * Delete synonym entry if selected before
     */
    protected function deleteExistingSynonym(
        bool $overrideExisting,
        bool $deleteSynonymsBefore,
        string $baseWord,
    ): void {
        $coreAdmin = $this->selectedSolrCoreConnection->getAdminService();

        if (!$deleteSynonymsBefore &&
            $overrideExisting &&
            $coreAdmin->getSynonyms($baseWord)
        ) {
            $coreAdmin->deleteSynonym($baseWord);
        }
    }
}
