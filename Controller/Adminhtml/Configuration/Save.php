<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Controller\Adminhtml\Configuration;

use \Logeecom\CleverReach\Helper\Config;

/**
 * Class that is responsible for saving configuration
 */
class Save extends \Magento\Backend\App\Action
{
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
     * Save constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param Config $config
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
     * Controller action that saves configuration
     */
    public function execute()
    {
        $batchSize = $this->_request->getParam('batchSize');
        $productSearch = $this->_request->getParam('productSearch', 0);
        $debugMode = $this->_request->getParam('debugMode', 0);
        $groupMappings = $this->_request->getParam('groupMappings');

        if (!is_numeric($batchSize) || $batchSize < 50 || $batchSize > 250) {
            return $this->resultJsonFactory->create()->setData([
                'status' => Config::INCORRECT_BATCH,
                'message' => __('Batch size must be between 50 and 250'),
            ]);
        }

        // if product search is enabled, register endpoint on CleverReach
        if ($productSearch) {
            $password = $this->config->getProductEndpointPassword();

            if (!$password) {
                $password = hash('sha256', time());
                $this->config->setProductEndpointPassword($password);
            }

            $this->apiClient->registerEndpoint(
                $this->config->getAccessToken(),
                $this->_url->getBaseUrl() . 'cleverreach/endpoint/product',
                'Magento - Product search endpoint',
                $password
            );
        }

        $this->config->setBatchSize($batchSize);
        $this->config->setDebugMode($debugMode);
        $this->config->setProductSearch($productSearch);
        $this->config->setGroupMappings($groupMappings);

        return $this->resultJsonFactory->create()->setData([
            'status' => Config::CONFIGURATION_SET,
            'message' => __('Configuration saved successfully'),
        ]);
    }
}
