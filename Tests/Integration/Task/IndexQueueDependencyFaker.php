<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Task;

/**
 * This class is used to fake the dependencies of the IndexQueue, to simulate a sucessful frontend
 * indexing call.
 *
 * It:
 *  * Uses the faked request id: 566b15f7b4931
 *  * Returns the fakes find UserGroups on the first http call.
 *  * Returns the faked indexing Response on the second http call.
 *
 * Class IndexQueueDependecyFaker
 */
class IndexQueueDependencyFaker
{

    /**
     * @var string
     */
    public static $requestId = '566b15f7b4931';

    /**
     * @var int
     */
    public static $callCount = 0;

    /**
     * @return string
     */
    public static function getRequestId()
    {
        return self::$requestId;
    }

    /**
     * @param string $url
     * @param bool $flags
     * @param resource $context
     * @return string
     */
    public static function getHttpContent($url, $flags, $context)
    {
        if ($url === 'http://localhost/index.php?id=1&L=0') {
            $fakeResponse = new \stdClass();
            $fakeResponse->requestId = self::getRequestId();

            if (self::$callCount == 0) {
                $fakeResponse->actionResults['findUserGroups'] = serialize(['1']);
            } else {
                $fakeResponse->actionResults['indexPage'] = serialize(['pageIndexed' => 1]);
            }

            self::$callCount++;
            return json_encode($fakeResponse);
        }
    }
}
