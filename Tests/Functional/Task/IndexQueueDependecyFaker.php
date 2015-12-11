<?php
namespace ApacheSolrForTypo3\Solr\Tests\Functional\Task;

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
 * @package ApacheSolrForTypo3\Solr\Tests\Functional\Task
 */
class IndexQueueDependecyFaker
{

    /**
     * @var string
     */
    static $requestId = '566b15f7b4931';

    /**
     * @var integer
     */
    static $callCount = 0;

    /**
     * @return string
     */
    public static function getRequestId()
    {
        return self::$requestId;
    }

    /**
     * @param string $url
     * @param boolean $flags
     * @param resource $context
     * @return string
     */
    public static function getHttpContent($url, $flags, $context)
    {
        if ($url === 'http://localhost/index.php?id=1&L=0') {
            $fakeResponse = new \stdClass();
            $fakeResponse->requestId = self::getRequestId();

            if (self::$callCount == 0) {
                $fakeResponse->actionResults['findUserGroups'] = serialize(array('1'));
            } else {
                $fakeResponse->actionResults['indexPage'] = serialize(array('pageIndexed' => 1));
            }

            self::$callCount++;
            return json_encode($fakeResponse);
        }
    }
}
