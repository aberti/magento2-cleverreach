<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Helper;

class BackgroundProcess
{

    /**
     * Indicates that import is already started
     */
    const IMPORT_LOCKED = 0;

    /**
     * Indicates that there is no customers that need to be imported
     */
    const NOTHING_TO_IMPORT = 1;

    /**
     * Indicates that import was started
     */
    const IMPORT_STARTED = 2;

    /**
     * Indicates that has been some error in import process
     */
    const IMPORT_ERROR = 10;
    /**
     * Front-end controller URL that need to be executed via CURL
     *
     * @var string
     */
    private $url;

    /**
     * Protection from front-end execution without permissions
     *
     * @var string
     */
    private $password;

    /**
     * Total count of customers that needs to be imported
     *
     * @var int
     */
    private $count;

    /**
     * Current number of proceeded customers / subscribers
     *
     * @var int
     */
    private $current;

    /**
     * Indicates whether to continue previous process or to try to start from the beginning
     *
     * @var bool
     */
    private $continue = false;

    /**
     * Indicates start number of current import
     *
     * @var int
     */
    private $offset = 0;

    /**
     * Indicates batch size of import
     *
     * @var int
     */
    private $limit = 100;

    /**
     * Indicates which customer group needs to import
     *
     * @var int
     */
    private $groupId;

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    private $curl;

    /**
     * BackgroundProcess constructor.
     *
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     */
    public function __construct(\Magento\Framework\HTTP\Client\Curl $curl)
    {
        $this->curl = $curl;
    }

    /**
     * Sets background process or continues previous one
     *
     * @return boolean Return true if import process is successfully started, otherwise false
     */
    public function startBackgroundProcess()
    {
        if ($this->password === null || $this->url === null) {
            return false;
        }

        // send the hash pass
        $password = hash('sha256', $this->password);
        $params = [
            'password' => $password,
            'continue' => $this->continue,
            'startFrom' => $this->offset,
            'limit' => $this->limit,
            'groupId' => $this->groupId,
        ];

        $url = $this->url . '?' . http_build_query($params);

        try {
            $this->curl->setOptions([
                CURLOPT_POST => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);

            $this->curl->get($url);
        } catch (\Exception $e) {
            return !stripos($e->getMessage(), 'time out');
        }

        return true;
    }

    /**
     * Returns current state in percentage
     *
     * @return bool|int
     */
    public function getCurrentState()
    {
        if ($this->current === null || $this->count === null) {
            return false;
        }

        if ($this->count === 0) {
            return 100;
        }

        return (int) (($this->current / $this->count) * 100);
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     *
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     *
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * @param int $count
     *
     * @return $this
     */
    public function setCount($count)
    {
        $this->count = $count;

        return $this;
    }

    /**
     * @return int
     */
    public function getCurrent()
    {
        return $this->current;
    }

    /**
     * @param int $current
     *
     * @return $this
     */
    public function setCurrent($current)
    {
        $this->current = $current;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isContinue()
    {
        return $this->continue;
    }

    /**
     * @param boolean $continue
     *
     * @return $this
     */
    public function setContinue($continue)
    {
        $this->continue = $continue;

        return $this;
    }

    /**
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @param int $offset
     *
     * @return $this
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     *
     * @return $this
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @return int
     */
    public function getGroupId()
    {
        return $this->groupId;
    }

    /**
     * @param int $groupId
     *
     * @return $this
     */
    public function setGroupId($groupId)
    {
        $this->groupId = $groupId;

        return $this;
    }
}
