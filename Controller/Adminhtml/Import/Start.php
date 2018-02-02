<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Controller\Adminhtml\Import;

use Logeecom\CleverReach\Helper\BackgroundProcess;
use Logeecom\CleverReach\Helper\Config;

/**
 * Starts a process of importing magento customers in cleverreach
 *
 * Class Start
 *
 * @package Logeecom\CleverReach\Controller\Adminhtml\Import
 */
class Start extends \Magento\Backend\App\Action
{
    /**
     * @var \Logeecom\CleverReach\Helper\Config
     */
    private $config;

    /**
     * @var \Logeecom\CleverReach\Helper\BackgroundProcess
     */
    private $backgroundProcessHelper;

    /**
     * @var \Logeecom\CleverReach\Helper\CleverReachApiClient
     */
    private $apiClient;

    /**
     * @var \Magento\Customer\Model\Customer
     */
    private $customerRepository;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var \Magento\Newsletter\Model\Subscriber
     */
    private $subscriberRepository;

    /**
     * Start constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Logeecom\CleverReach\Helper\Config $config
     * @param BackgroundProcess $background
     * @param \Magento\Customer\Model\Customer $customerRepository
     * @param \Magento\Newsletter\Model\Subscriber $subscriberRepository
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Logeecom\CleverReach\Helper\Config $config,
        \Logeecom\CleverReach\Helper\BackgroundProcess $background,
        \Magento\Customer\Model\Customer $customerRepository,
        \Magento\Newsletter\Model\Subscriber $subscriberRepository,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient
    ) {
        $this->config = $config;
        $this->backgroundProcessHelper = $background;
        $this->apiClient = $apiClient;
        $this->customerRepository = $customerRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
        $this->subscriberRepository = $subscriberRepository;
    }

    /**
     * Action which starts import
     */
    public function execute()
    {
        if (!$this->config->isConnected()) {
            return $this->resultJsonFactory->create()->setData([
                'status' => BackgroundProcess::IMPORT_ERROR,
                'message' => __('Import process cannot start'),
            ]);
        }

        $this->apiClient->setAuthMode('bearer', $this->config->getAccessToken());
        $response = $this->apiClient->get('/clients/whoami');

        if (isset($response['error']) && isset($response['error']['message']) && isset($response['error']['code'])) {
            return $this->resultJsonFactory->create()->setData([
                'status' => Config::HAD_SUCCESSFUL_CONNECTION,
                'message' => __('Error: ' . $response['error']['message'] . ' - Please connect again.'),
            ]);
        }

        $importStartTime = $this->config->getImportStartTime();

        if ($this->config->isImportLocked() && $importStartTime !== null &&
            (($importStartTime + 24 * 60 * 60) > time())
        ) {
            return $this->resultJsonFactory->create()->setData([
                'status' => BackgroundProcess::IMPORT_LOCKED,
                'message' => __('Process already started'),
            ]);
        }

        $customerGroups = $this->getCustomerGroups();

        if (empty($customerGroups)) {
            return $this->resultJsonFactory->create()->setData([
                'status' => BackgroundProcess::NOTHING_TO_IMPORT,
                'message' => __('Nothing to import'),
            ]);
        }

        $this->setImportConfigurations($customerGroups);

        if (!$this->startProcess($customerGroups)) {
            return $this->resultJsonFactory->create()->setData([
                'status' => BackgroundProcess::IMPORT_ERROR,
                'message' => __('Import process cannot start'),
            ]);
        }

        return $this->resultJsonFactory->create()->setData([
            'status' => BackgroundProcess::IMPORT_STARTED,
            'message' => __('Process successfully started and will continue in background'),
        ]);
    }

    /**
     * Returns sorted ids of customer groups
     *
     * @return array
     */
    private function getCustomerGroups()
    {
        $result = [];
        foreach (json_decode($this->config->getGroupMappings(), true) as $key => $value) {
            if ($value['crGroup'] != 0) {
                $result[] = $key;
            }
        }

        sort($result);

        return $result;
    }

    /**
     * Saves import configurations to database
     *
     * @param $customerGroups
     */
    private function setImportConfigurations($customerGroups)
    {
        $count = $this->customerRepository->getCollection()
            ->addFieldToFilter('group_id', ['in' => $customerGroups])
            ->count();

        // check if unregistered subscribers needs to be imported
        if (in_array(-1, $customerGroups)) {
            $count += $this->subscriberRepository->getCollection()
                ->addFieldToFilter('customer_id', 0)
                ->count();
        }

        $this->config->setImportLocked(1);
        $this->config->setImportProgress(0);
        $this->config->setTotalCustomersForImport($count);
        $this->config->setImportStartTime(time());
    }

    /**
     * Sets parameters and starts background import process
     *
     * @param $customerGroups
     *
     * @return bool
     */
    private function startProcess($customerGroups)
    {
        $this->backgroundProcessHelper->setPassword($this->config->getProductEndpointPassword());
        $this->backgroundProcessHelper->setUrl($this->_url->getBaseUrl() . 'cleverreach/import/importcustomers');
        $this->backgroundProcessHelper->setOffset(0);
        $this->backgroundProcessHelper->setLimit($this->config->getBatchSize());
        $this->backgroundProcessHelper->setGroupId($customerGroups[0]);

        return $this->backgroundProcessHelper->startBackgroundProcess();
    }
}
