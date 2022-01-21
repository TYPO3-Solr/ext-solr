<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use Exception;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class TSFEBootstrapResult
 * @package ApacheSolrForTypo3\Solr\Tests\Integration
 */
class TSFEBootstrapResult
{

    /**
     * @var TypoScriptFrontendController
     */
    protected TypoScriptFrontendController $tsfe;

    /**
     * @var array
     */
    protected array $exceptions = [];

    /**
     * @return TypoScriptFrontendController
     */
    public function getTsfe(): TypoScriptFrontendController
    {
        return $this->tsfe;
    }

    /**
     * @param TypoScriptFrontendController $tsfe
     */
    public function setTsfe(TypoScriptFrontendController $tsfe)
    {
        $this->tsfe = $tsfe;
    }

    /**
     * @param Exception $exception
     */
    public function addExceptions(Exception $exception)
    {
        $this->exceptions[] = $exception;
    }

    /**
     * @return mixed
     */
    public function getExceptions()
    {
        return $this->exceptions;
    }
}
