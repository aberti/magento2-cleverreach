<?php
/**
 * Controller responsible for exporting product from magento to CleverReach
 *
 * @package     Logeecom_CleverReach
 * @author      Logeecom
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Controller\Oauth;

class Callback extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Logeecom\CleverReach\Helper\CleverReachApiClient
     */
    private $apiClient;

    /**
     * @var \Logeecom\CleverReach\Helper\Config
     */
    private $config;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    private $resultPageFactory;

    /**
     * Product constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient
     * @param \Logeecom\CleverReach\Helper\Config $config
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient,
        \Logeecom\CleverReach\Helper\Config $config,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        $this->apiClient = $apiClient;
        $this->config = $config;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->storeManager = $storeManager;
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * Handles callback from CleverReach
     *
     * @return mixed
     */
    public function execute()
    {
        if (empty($this->_request->getParam('code'))) {
            return $this->resultJsonFactory->create()->setData([
                'status' => false,
                'message' => __('Wrong parameters'),
            ]);
        }

        $code = $this->_request->getParam('code');

        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $url = $baseUrl . 'cleverreach/oauth/callback/';
        $result = $this->apiClient->getAccessToken($url, $code);

        if (isset($result['error'])) {
            // error occurred, return appropriate status
            return $this->resultJsonFactory->create()->setData([
                'status' => $result['error'],
                'message' => __('Unsuccessful connection'),
            ]);
        }

        $this->config->setAccessToken($result['access_token']);
        $this->config->setConnected(1);

        return $this->resultPageFactory->create();
    }
}
