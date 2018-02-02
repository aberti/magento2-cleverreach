<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Controller\Adminhtml\Configuration;

use Logeecom\CleverReach\Helper\Config;
use Magento\Backend\App\Action\Context;

class Validate extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var \Logeecom\CleverReach\Helper\CleverReachApiClient
     */
    private $apiClient;

    /**
     * @var \Logeecom\CleverReach\Helper\Config
     */
    private $config;

    /**
     * @var \Logeecom\CleverReach\Helper\Data
     */
    private $dataHelper;

    /**
     * Check constructor.
     *
     * @param Context $context
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient
     * @param Config $config
     * @param \Logeecom\CleverReach\Helper\Data $dataHelper
     */
    public function __construct(
        Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient,
        \Logeecom\CleverReach\Helper\Config $config,
        \Logeecom\CleverReach\Helper\Data $dataHelper
    ) {
        parent::__construct($context);

        $this->resultJsonFactory = $resultJsonFactory;
        $this->apiClient = $apiClient;
        $this->config = $config;
        $this->dataHelper = $dataHelper;
    }

    /**
     * Executes an action that checks user credentials and acquires access token from CleverReach
     *
     * @throws \Exception
     */
    public function execute()
    {

        $connected = $this->config->isConnected();
        $accessToken = $this->config->getAccessToken();

        if (empty($connected)) {
            return $this->resultJsonFactory->create()->setData([
                'status' => 0,
                'message' => __('Unsuccessful connection')
            ]);
        }

        try {
            $groups = $this->apiClient->getFormattedGroups($accessToken);
        } catch (\Exception $e) {
            // error occurred, return appropriate status
            return $this->resultJsonFactory->create()->setData([
                'status' => Config::UNSUCCESSFUL_CONNECTION,
                'message' => $e->getMessage(),
            ]);
        }
        if ($this->config->compareGroups($groups)) {
            return $this->resultJsonFactory->create()->setData([
                'status' => Config::HAD_SUCCESSFUL_CONNECTION,
            ]);
        }

        return $this->resultJsonFactory->create()->setData([
            'status' => Config::SUCCESSFUL_CONNECTION,
            'message' => __('Successful connection'),
            'groups' => $groups,
        ]);
    }
}
