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

namespace ApacheSolrForTypo3\Solr;

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * A content extractor to get clean, indexable content from HTML markup.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class HtmlContentExtractor
{
    /**
     * Unicode ranges which should get stripped before sending a document to solr.
     * This is necessary if a document (PDF, etc.) contains unicode characters which
     * are valid in the font being used in the document but are not available in the
     * font being used for displaying results.
     *
     * This is often the case if PDFs are being indexed where special fonts are used
     * for displaying bullets, etc. Usually those bullets reside in one of the unicode
     * "Private Use Zones" or the "Private Use Area" (plane 15 + 16)
     *
     * @see http://en.wikipedia.org/wiki/Unicode_block
     * @var array
     */
    protected static array $stripUnicodeRanges = [
        ['FFFD', 'FFFD'],
        // Replacement Character (�) @see http://en.wikipedia.org/wiki/Specials_%28Unicode_block%29
        ['E000', 'F8FF'],
        // Private Use Area (part of Plane 0)
        ['F0000', 'FFFFF'],
        // Supplementary Private Use Area (Plane 15)
        ['100000', '10FFFF'],
        // Supplementary Private Use Area (Plane 16)
    ];

    /**
     * The raw HTML markup content to extract clean content from.
     *
     * @var string
     */
    protected string $content = '';

    /**
     * Mapping of HTML tags to Solr document fields.
     *
     * @var array
     */
    protected array $tagToFieldMapping = [
        'h1' => 'tagsH1',
        'h2' => 'tagsH2H3',
        'h3' => 'tagsH2H3',
        'h4' => 'tagsH4H5H6',
        'h5' => 'tagsH4H5H6',
        'h6' => 'tagsH4H5H6',
        'u' => 'tagsInline',
        'b' => 'tagsInline',
        'strong' => 'tagsInline',
        'i' => 'tagsInline',
        'em' => 'tagsInline',
        'a' => 'tagsA',
    ];

    /**
     * @var TypoScriptConfiguration|null
     */
    protected ?TypoScriptConfiguration $configuration = null;

    /**
     * Constructor.
     *
     * @param string $content Content HTML markup
     */
    public function __construct(string $content)
    {
        // @extensionScannerIgnoreLine
        $this->content = $content;
    }

    /**
     * @return TypoScriptConfiguration
     */
    protected function getConfiguration(): TypoScriptConfiguration
    {
        if ($this->configuration === null) {
            $this->configuration = Util::getSolrConfiguration();
        }

        return $this->configuration;
    }

    /**
     * @param TypoScriptConfiguration $configuration
     */
    public function setConfiguration(TypoScriptConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Returns the cleaned indexable content from the page's HTML markup.
     *
     * The content is cleaned from HTML tags and control chars Solr could
     * stumble on.
     *
     * @return string Indexable, cleaned content ready for indexing.
     */
    public function getIndexableContent(): string
    {
        // @extensionScannerIgnoreLine
        $content = self::cleanContent($this->content);
        return trim($content);
    }

    /**
     * Strips html tags, and tab, new-line, carriage-return, &nbsp; whitespace
     * characters.
     *
     * @param string $content String to clean
     * @return string String cleaned from tags and special whitespace characters
     */
    public static function cleanContent(string $content): string
    {
        $content = self::stripControlCharacters($content);
        // remove Javascript
        $content = preg_replace('@<script[^>]*>.*?</script>@msi', '', $content);

        // remove internal CSS styles
        $content = preg_replace('@<style[^>]*>.*?</style>@msi', '', $content);

        // prevents concatenated words when stripping tags afterwards
        $content = str_replace(['<', '>'], [' <', '> '], $content);
        $content = str_replace(["\t", "\n", "\r", '&nbsp;'], ' ', $content);
        $content = strip_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        $content = self::stripUnicodeRanges($content);
        $content = preg_replace('/\s{2,}/u', ' ', $content);

        return trim($content);
    }

    /**
     * Strips control characters that cause Jetty/Solr to fail.
     *
     * @param string $content the content to sanitize
     * @return string the sanitized content
     * @see http://w3.org/International/questions/qa-forms-utf-8.html
     */
    public static function stripControlCharacters(string $content): string
    {
        // Printable utf-8 does not include any of these chars below x7F
        return preg_replace('@[\x00-\x08\x0B\x0C\x0E-\x1F]@', ' ', $content);
    }

    /**
     * Strips unusable unicode ranges
     *
     * @param string $content Content to sanitize
     * @return string Sanitized content
     */
    public static function stripUnicodeRanges(string $content): string
    {
        foreach (self::$stripUnicodeRanges as $range) {
            $content = self::stripUnicodeRange($content, $range[0], $range[1]);
        }
        return $content;
    }

    /**
     * Strips a UTF-8 character range
     *
     * @param string $content Content to sanitize
     * @param string $start Unicode range start character as uppercase hexadecimal string
     * @param string $end Unicode range end character as uppercase hexadecimal string
     * @return string Sanitized content
     * @noinspection Annotator
     */
    public static function stripUnicodeRange(string $content, string $start, string $end): string
    {
        return preg_replace(
            '/[\x{' . $start . '}-\x{' . $end . '}]/u',
            '',
            $content
        );
    }

    /**
     * Shortcut method to retrieve the raw content marked for indexing.
     *
     * @return string Content marked for indexing.
     */
    public function getContentMarkedForIndexing(): string
    {
        // @extensionScannerIgnoreLine
        return $this->content;
    }

    /**
     * Extracts HTML tag content from tags in the content marked for indexing.
     *
     * @return array A mapping of Solr document field names to content found in defined tags.
     */
    public function getTagContent(): array
    {
        $result = [];
        $matches = [];
        $content = $this->getContentMarkedForIndexing();

        // strip all ignored tags
        $content = strip_tags(
            $content,
            '<' . implode('><', array_keys($this->tagToFieldMapping)) . '>'
        );

        preg_match_all(
            '@<(' . implode(
                '|',
                array_keys($this->tagToFieldMapping)
            ) . ')[^>]*>(.*)</\1>@Ui',
            $content,
            $matches
        );

        foreach ($matches[1] as $key => $tag) {
            // We don't want to index links auto-generated by the url filter.
            $pattern = '@(?:http://|https://|ftp://|mailto:|smb://|afp://|file://|gopher://|news://|ssl://|sslv2://|sslv3://|tls://|tcp://|udp://|www\.)[a-zA-Z0-9]+@';
            if ($tag != 'a' || !preg_match($pattern, $matches[2][$key])) {
                $fieldName = $this->tagToFieldMapping[$tag];
                $hasContentForFieldName = empty($result[$fieldName]);
                $separator = ($hasContentForFieldName) ? '' : ' ';
                $result[$fieldName] = ($result[$fieldName] ?? '') . $separator . $matches[2][$key];
            }
        }

        return $result;
    }
}
