<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\PageDocumentPostProcessor;
use ApacheSolrForTypo3\Solr\System\Mvc\Frontend\Controller\OverriddenTypoScriptFrontendController;

class TestPostProcessor implements PageDocumentPostProcessor{

    /**
     * Allows Modification of the PageDocument
     * Can be used to trigger actions when all contextual variables of the pageDocument to be indexed are known
     *
     * @param \Apache_Solr_Document $pageDocument the generated page document
     * @param OverriddenTypoScriptFrontendController $page the page object with information about page id or language
     * @return void
     */
    public function postProcessPageDocument(\Apache_Solr_Document $pageDocument, OverriddenTypoScriptFrontendController $page)
    {
        $pageDocument->addField('postProcessorField_stringS','postprocessed');
    }
}
