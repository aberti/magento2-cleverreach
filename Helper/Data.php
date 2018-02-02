<?php
/**
 * Class that is responsible for endpoint registration
 *
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Setup\Model\Cron;

/**
 * Registration of endpoint in CleverReach
 *
 * Class Data
 * @package Logeecom\CleverReach\Helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Status for nonexistent product
     */
    const NO_PRODUCT = 8;

    /**
     * @var CleverReachApiClient
     */
    private $apiClient;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

    /**
     * Data constructor.
     * @param Context $context
     * @param Config $config
     * @param CleverReachApiClient $apiClient
     */
    public function __construct(
        Context $context,
        \Logeecom\CleverReach\Helper\Config $config,
        \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient
    ) {
        parent::__construct($context);

        $this->apiClient = $apiClient;
        $this->config = $config;
        $this->request = $context->getRequest();
    }

    /**
     * Sends Double-Opt-In email
     *
     * @param $formId
     * @param $email
     * @param $groupsId
     * @throws \Exception
     */
    public function sendDOIEmail($formId, $email, $groupsId)
    {
        $data = [
            "email" => $email,
            "groups_id" => $groupsId,
            "doidata" => [
                "user_ip" => $this->request->getServer('REMOTE_ADDR'),
                "referer" => $this->request->getServer('SERVER_NAME') . $this->request->getServer('REQUEST_URI'),
                "user_agent" => $this->request->getServer('HTTP_USER_AGENT'),
            ],
        ];

        $this->apiClient->setAuthMode('bearer', $this->config->getAccessToken());
        $this->apiClient->post('/forms.json/' . $formId . '/send/activate', $data);
    }
}
