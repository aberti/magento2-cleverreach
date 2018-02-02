<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Controller\Adminhtml\Configuration;

class Get extends \Magento\Backend\App\Action
{
    /**
     * Authorization level of a basic admin session
     */
//    const ADMIN_RESOURCE = 'Magento_Backend::admin';
    const ADMIN_RESOURCE = 'Logeecom_CleverReach::config';

    /**
     * @var \Logeecom\CleverReach\Helper\Config
     */
    private $config;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var \Logeecom\CleverReach\Helper\CleverReachApiClient
     */
    private $apiClient;

    /**
     * Get constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Logeecom\CleverReach\Helper\Config $config
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Logeecom\CleverReach\Helper\Config $config,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient
    ) {
    
        $this->config = $config;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
        $this->apiClient = $apiClient;
    }

    /**
     * Controller action that returns configuration
     */
    public function execute()
    {
        $groups = [];

        if ($this->config->isConnected()) {
            $groups = $this->apiClient->getFormattedGroups($this->config->getAccessToken());
        }

        $data = $this->config->getAllConfigs($groups);

        return $this->resultJsonFactory->create()->setData($data);
    }
}
