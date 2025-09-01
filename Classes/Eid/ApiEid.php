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

namespace ApacheSolrForTypo3\Solr\Eid;

use ApacheSolrForTypo3\Solr\Api;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ApiEid
{
    /**
     * Globally required params
     */
    protected const REQUIRED_PARAMS_GLOBAL = [
        'api',
        'apiKey',
    ];

    /**
     * Available methods and params
     */
    protected const API_METHODS = [
        'siteHash' => [
            'params' => [
                'required' => [
                    'siteIdentifier',
                ],
            ],
        ],
    ];

    /**
     * The main method for eID scripts.
     *
     * @throws ImmediateResponseException
     */
    public function main(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        // @todo, remove this backwards-compatibility-adjustment together with siteHashStrategy setting.
        if (isset($queryParams['domain'])) {
            $queryParams['siteIdentifier'] = $queryParams['domain'];
            $request = $request->withQueryParams($queryParams);
        }
        $this->validateRequest($request);
        return $this->{'get' . ucfirst($request->getQueryParams()['api']) . 'Response'}($request);
    }

    /**
     * Returns the site hash
     *
     * @noinspection PhpUnused
     */
    protected function getSiteHashResponse(ServerRequestInterface $request): JsonResponse
    {
        $queryParams = $request->getQueryParams();
        $siteIdentifier = $queryParams['siteIdentifier'];

        /** @var SiteHashService $siteHashService */
        $siteHashService = GeneralUtility::makeInstance(SiteHashService::class);
        $siteHash = $siteHashService->getSiteHashForSiteIdentifier($siteIdentifier);
        $jsonResponseContents = [
            'sitehash' => $siteHash,
        ];
        // @todo, remove this backwards-compatibility-adjustment together with siteHashStrategy setting.
        if (isset($queryParams['domain'])) {
            $deprecationMessage = 'The domain parameter for eID=tx_solr_api - api=siteHash is deprecated, please use siteIdentifier instead.';
            trigger_error(
                $deprecationMessage,
                E_USER_DEPRECATED,
            );
            $jsonResponseContents['deprecation notice'] = $deprecationMessage;
        }
        return new JsonResponse(
            $jsonResponseContents,
        );
    }

    /**
     * Validates request.
     *
     * @throws ImmediateResponseException
     */
    protected function validateRequest(ServerRequestInterface $request): void
    {
        $params = $request->getQueryParams();
        if (!Api::isValidApiKey($params['apiKey'] ?? '')) {
            throw new ImmediateResponseException(
                new JsonResponse(
                    ['errorMessage' => 'Invalid API key'],
                    403,
                ),
                403
            );
        }

        if (($params['api'] ?? null) === null || !array_key_exists($params['api'], self::API_METHODS)) {
            throw new ImmediateResponseException(
                new JsonResponse(
                    [
                        'errorMessage' => 'You must provide an available API method, e.g. siteHash. See: available methods in methods key.',
                        'methods' => $this->getApiMethodDefinitions(),
                    ],
                    400
                ),
                400
            );
        }

        $requiredApiParams = $this->getApiMethodDefinitions()[$params['api']]['params']['required'] ?? [];

        $requiredApiParams[] = 'eID';
        $missingParams = array_values(array_diff($requiredApiParams, array_keys($request->getQueryParams())));
        if (!empty($missingParams)) {
            throw new ImmediateResponseException(
                new JsonResponse(
                    [
                        'errorMessage' => 'Required API params are not provided. See: methods.',
                        'missing_params' => $missingParams,
                        'methods' => $this->getApiMethodDefinitions()[$params['api']],
                    ],
                    400
                ),
                400
            );
        }
    }

    /**
     * Returns the available methods and their params.
     */
    protected function getApiMethodDefinitions(): array
    {
        $apiMethodDefinitions = self::API_METHODS;
        foreach ($apiMethodDefinitions as $apiMethodName => $apiMethodDefinition) {
            $apiMethodDefinitions[$apiMethodName]['params']['required'] = array_merge(
                self::REQUIRED_PARAMS_GLOBAL,
                $apiMethodDefinition['params']['required']
            );
        }
        return $apiMethodDefinitions;
    }
}
