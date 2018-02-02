<?php
/**
 * Class that is responsible for communication with CleverReach REST API
 *
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Helper;

use Magento\Framework\Exception\LocalizedException;

class CleverReachApiClient
{

    const CLIENT_ID = 'LAg5LPdzDi';
    const CLIENT_SECRET = 'YeXVRM0cYTdJE0XO4mAF3mgjs19GAMYz';
    const LOGIN_URL = 'https://rest.cleverreach.com/oauth/authorize.php';
    /**
     * @var int
     */
    public $responseCode = 0;
    /**
     * @var string
     */
    private $url;
    /**
     * @var string
     */
    private $postFormat = 'json';
    /**
     * @var string
     */
    private $returnFormat = 'json';
    /**
     * @var bool
     */
    private $authMode = false;
    /**
     * @var string
     */
    private $authModeSettingsToken = '';
    /**
     * @var bool
     */
    private $debugValues = [];
    /**
     * @var bool
     */
    private $throwExceptions = true;
    /**
     * @var bool
     */
    private $error = false;
    /**
     * @var string
     */
    private $tokenUrl = 'https://rest.cleverreach.com/oauth/token.php';

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    private $curl;

    /**
     * @var \Magento\Framework\Logger\Monolog
     */
    private $monolog;

    /**
     * CleverReachApi constructor.
     *
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Magento\Framework\Logger\Monolog   $monolog
     * @param string                              $token
     * @param string                              $mode
     * @param string                              $url
     */
    public function __construct(
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\Logger\Monolog $monolog,
        $token = '',
        $mode = 'bearer',
        $url = 'https://rest.cleverreach.com/v2'
    ) {
        $this->url = $url;
        $this->monolog = $monolog;
        $this->curl = $curl;
        $this->authMode = $mode;
        $this->authModeSettingsToken = $token;
    }

    /**
     * Set Auth Mode
     *
     * @param string $mode
     * @param mixed  $value
     */
    public function setAuthMode($mode = 'none', $value = false)
    {
        $this->authMode = $mode;
        $this->authModeSettingsToken = $value;
    }

    /**
     * Delete
     *
     * @param      $path
     * @param bool $data
     *
     * @return mixed|null
     * @throws \Exception
     */
    public function delete($path, $data = false)
    {
        return $this->get($path, $data, 'delete');
    }

    /**
     * Get
     *
     * @param        $path
     * @param bool   $data
     * @param string $mode
     *
     * @return mixed|null
     * @throws \Exception
     */
    public function get($path, $data = false, $mode = 'get')
    {
        $response = null;

        $this->resetDebug();
        if (is_string($data) && !json_decode($data)) {
            throw new LocalizedException(__('Data is string but no JSON'));
        }

        $url = sprintf("%s?%s", rtrim($this->url, '/') . $path, ($data ? http_build_query($data) : ''));
        $this->debug('url', $url);

        $this->setupCurl();

        switch ($mode) {
            case 'delete':
                $this->curl->setOption(CURLOPT_CUSTOMREQUEST, strtoupper($mode));
                $this->curl->setOption(CURLOPT_HTTPGET, false);
                $this->debug('mode', strtoupper($mode));
                break;

            default:
                $this->debug('mode', 'GET');
                break;
        }

        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);

        try {
            $this->curl->get($url);

            $response = $this->curl->getBody();
        } catch (\Exception $e) {
            if ($this->throwExceptions) {
                throw new LocalizedException(__($e->getMessage()));
            }
        }

        $this->debugEndTimer();

        return $this->returnResult($response);
    }

    /**
     * Micro time float
     *
     * @return float
     */
    public function microTimeFloat()
    {
        list($usec, $sec) = explode(' ', microtime());

        return ((float)$usec + (float)$sec);
    }

    /**
     * Put
     *
     * @param            $path
     * @param array      $data
     *
     * @return mixed|null
     */
    public function put($path, $data = [])
    {
        return $this->post($path, $data, 'put');
    }

    /**
     * Post
     *
     * @param        $path
     * @param        $data
     * @param string $mode
     *
     * @return mixed|null
     * @throws \Exception
     */
    public function post($path, $data = [], $mode = 'post')
    {
        $response = null;

        $this->resetDebug();
        $this->debug('url', rtrim($this->url, '/') . $path);

        if (is_string($data) && !json_decode($data)) {
            throw new LocalizedException(__('Data is string but no JSON'));
        }

        $this->setupCurl();

        $curlPostData = ($this->postFormat == 'json' ? json_encode($data) : $data);

        $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);

        switch ($mode) {
            case 'put':
                $this->curl->setOption(CURLOPT_CUSTOMREQUEST, 'PUT');
                $this->curl->setOption(CURLOPT_POST, false);
                break;

            default:
                $this->curl->setOption(CURLOPT_CUSTOMREQUEST, 'POST');
                $this->curl->setOption(CURLOPT_POST, 1);
                break;
        }

        $this->debug('mode', strtoupper($mode));

        try {
            $this->curl->setOption(CURLOPT_POSTFIELDS, $curlPostData);
            $this->curl->post(rtrim($this->url, '/') . $path, []);

            $response = $this->curl->getBody();

            $this->monolog->addDebug(json_encode($this->curl->getBody()));
        } catch (\Exception $e) {
            if ($this->throwExceptions) {
                throw new LocalizedException(__($e->getMessage()));
            }
        }

        $this->debugEndTimer();

        return $this->returnResult($response);
    }

    /**
     * Returns an associative array with fields: access_token, expires_in, token_type, scope.
     * If curl cannot be executed, exception is thrown with appropriate message
     *
     * @param string $code
     * @param string $redirectUrl
     *
     * @return mixed
     * @throws \Exception
     */
    public function getAccessToken($redirectUrl, $code = null)
    {
        // Assemble POST parameters for the request.
        $postFields = '&grant_type=authorization_code&client_id=' . self::CLIENT_ID . '&client_secret=' .
            self::CLIENT_SECRET . '&code=' . $code . '&redirect_uri=' . $redirectUrl;

        try {
            $this->curl->setOptions([
                CURLOPT_ENCODING => 1,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POSTFIELDS => $postFields,
            ]);

            $this->curl->post($this->tokenUrl, []);
        } catch (\Exception $e) {
            throw new LocalizedException(__('curl_exec() failed. Error: ' . $e->getMessage()));
        }

        return json_decode($this->curl->getBody(), true);
    }

    /**
     * Returns login URL
     *
     * @param string $redirectUrl
     * @param string $registerData
     * @return string
     */
    public function getLoginUrl($redirectUrl, $registerData = '')
    {
        return self::LOGIN_URL . '?response_type=code&client_id=' . self::CLIENT_ID . '&redirect_uri=' .
            urlencode($redirectUrl) . '&registerData=' . $registerData;
    }

    /**
     * @return boolean
     */
    public function isThrowExceptions()
    {
        return $this->throwExceptions;
    }

    /**
     * @param boolean $throwExceptions
     *
     * @return $this
     */
    public function setThrowExceptions($throwExceptions)
    {
        $this->throwExceptions = $throwExceptions;

        return $this;
    }

    /**
     * Returns customer groups formatted for configuration page
     *
     * @param $accessToken
     *
     * @return array
     * @throws \Exception
     */
    public function getFormattedGroups($accessToken)
    {
        $formattedGroups = [];
        $this->setAuthMode('bearer', $accessToken);

        $groups = $this->get('/groups.json');

        if ($groups) {
            foreach ($groups as $group) {
                if (!isset($group['id'])) {
                    continue;
                }

                $formattedGroups[] = [
                    'id' => $group['id'],
                    'name' => $group['name'],
                    'forms' => $this->get('/groups.json/' . $group['id'] . '/forms'),
                ];
            }
        }

        return $formattedGroups;
    }

    /**
     * Registers product endpoint on CleverReach
     *
     * @param $accessToken
     * @param $url
     * @param $name
     * @param $password
     */
    public function registerEndpoint($accessToken, $url, $name, $password)
    {
        $this->setAuthMode('bearer', $accessToken);

        $this->setThrowExceptions(false);
        $this->post('/mycontent', [
            'name' => $name . ' ' . $url,
            'url' => $url,
            'password' => $password,
        ]);

        $this->setThrowExceptions(true);
    }

    /**
     *  reset Debug
     */
    private function resetDebug()
    {
        $this->debugValues = [];
        $this->error = false;
        $this->debugStartTimer();
    }

    /**
     * debug Start Timer
     */
    private function debugStartTimer()
    {
        $this->debugValues['time'] = $this->microTimeFloat();
    }

    /**
     * debug
     *
     * @param $key
     * @param $value
     */
    private function debug($key, $value)
    {
        $this->debugValues[$key] = $value;
    }

    /**
     * setup Curl
     */
    private function setupCurl()
    {
        $header = [];

        switch ($this->postFormat) {
            case 'json':
                $header['content'] = 'Content-Type: application/json';
                break;
            default:
                $header['content'] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
                break;
        }

        switch ($this->authMode) {
            case 'webauth':
                $this->curl->setOption(
                    CURLOPT_USERPWD,
                    $this->authModeSettingsToken->login . ":" . $this->authModeSettingsToken->password
                );
                break;
            case 'jwt':
                $header['token'] = 'X-ACCESS-TOKEN: ' . $this->authModeSettingsToken;
                break;
            case 'bearer':
                $header['token'] = 'Authorization: Bearer ' . $this->authModeSettingsToken;
                break;
        }

        $this->debugValues['header'] = $header;

        $this->curl->setOption(CURLOPT_HTTPHEADER, $header);
    }

    /**
     * Debug End Timer
     */
    private function debugEndTimer()
    {
        $this->debugValues['time'] = $this->microTimeFloat() - $this->debugValues['time'];
    }

    /**
     * Returns Result
     *
     * @param      $in
     *
     * @return mixed|null
     */
    private function returnResult($in)
    {
        switch ($this->returnFormat) {
            case 'json':
                return json_decode($in, true);
            default:
                return $in;
        }
    }
}
