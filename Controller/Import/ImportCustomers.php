<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Controller\Import;

/**
 * Responsible for importing piece of customers from Magento to CleverReach
 *
 * Class ImportCustomers
 * @package Logeecom\CleverReach\Controller\Import
 */
class ImportCustomers extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Logeecom\CleverReach\Helper\CleverReachApiClient
     */
    private $apiClient;

    /**
     * @var \Magento\Customer\Model\Customer
     */
    private $customerRepository;

    /**
     * @var \Logeecom\CleverReach\Helper\Config
     */
    private $config;

    /**
     * @var \Logeecom\CleverReach\Helper\BackgroundProcess
     */
    private $backgroundProcessHelper;

    /**
     * @var \Logeecom\CleverReach\Helper\CustomerFormatter
     */
    private $formatter;

    /**
     * @var int
     */
    private $currentProgress = 0;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var \Magento\Newsletter\Model\Subscriber
     */
    private $subscriber;

    /**
     * @var \Magento\Framework\Logger\Monolog
     */
    private $monolog;

    /**
     * ImportCustomers constructor.
     *
     * @param \Magento\Framework\App\Action\Context             $context
     * @param \Magento\Customer\Model\Customer                  $customerRepository
     * @param \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient
     * @param \Logeecom\CleverReach\Helper\BackgroundProcess    $background
     * @param \Logeecom\CleverReach\Helper\Config               $config
     * @param \Logeecom\CleverReach\Helper\CustomerFormatter    $formatter
     * @param \Magento\Framework\Controller\Result\JsonFactory  $resultJsonFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Customer $customerRepository,
        \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient,
        \Logeecom\CleverReach\Helper\BackgroundProcess $background,
        \Logeecom\CleverReach\Helper\Config $config,
        \Logeecom\CleverReach\Helper\CustomerFormatter $formatter,
        \Magento\Framework\Logger\Monolog $monolog,
        \Magento\Newsletter\Model\Subscriber $subscriber,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->apiClient = $apiClient;
        $this->customerRepository = $customerRepository;
        $this->backgroundProcessHelper = $background;
        $this->config = $config;
        $this->formatter = $formatter;
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->subscriber = $subscriber;
        $this->monolog = $monolog;
    }

    /**
     * Imports piece of customers from Magento to CleverReach
     *
     * @throws \Exception
     */
    public function execute()
    {
        $offset = $this->getRequest()->getParam('startFrom');
        $limit = $this->getRequest()->getParam('limit');
        $groupId = $this->getRequest()->getParam('groupId');
        $groupMappings = json_decode($this->config->getGroupMappings(), true);
        $listId = 0;

        if (isset($groupMappings[$groupId])) {
            $listId = $groupMappings[$groupId]['crGroup'];
        }

        // if customer group is not mapped to CleverReach, check next group
        if ($listId == 0) {
            $this->checkNextGroup();
        } else {
            $customers = $groupId == -1 ? $this->prepareSubscribers() : $this->prepareCustomers();
            $this->currentProgress = $this->config->getImportProgress() === null ?
                0 : $this->config->getImportProgress();

            // if there are no more customers in this group, check next group
            if (empty($customers)) {
                $this->checkNextGroup();
            } else {
                $this->apiClient->setAuthMode('bearer', $this->config->getAccessToken());

                // adding attributes to the cleverreach list
                if ($offset == 0) {
                    $this->addAttributes($this->apiClient, $listId);
                }

                // sending customers
                $this->apiClient->post('/groups.json/' . $listId . '/receivers', $customers);

                $this->currentProgress += count($customers);
                $this->config->setImportProgress($this->currentProgress);

                // if number of customers is not equal to batch size, check next group
                if (count($customers) != $limit) {
                    if ($this->config->isEnabledDebugMode()) {
                        $this->monolog->addDebug("checkNextGroup: Customers count " . count($customers));
                    }

                    $this->checkNextGroup();
                } else {
                    if ($this->config->isEnabledDebugMode()) {
                        $this->monolog->addDebug("continueProcess: GroupID: $groupId, Limit: $limit");
                    }

                    $this->continueProcess($offset + $limit, $limit, $groupId);
                }
            }
        }

        return $this->resultJsonFactory->create()->setData([
            'success' => true,
            'groupId' => $groupId,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Checks if the next group exists and continues the process, or ends process if it's not.
     */
    private function checkNextGroup()
    {
        $customerGroups = [];
        foreach (json_decode($this->config->getGroupMappings(), true) as $key => $value) {
            $customerGroups[] = $key;
        }

        // sorting customer groups so the next group can be selected by next higher id
        sort($customerGroups);

        // creating associative array so keys can be used for selecting the next group.
        $customerGroups = array_combine($customerGroups, $customerGroups);

        $nextGroupId = $this->getNextGroupId($customerGroups, $this->getRequest()->getParam('groupId'));

        if ($nextGroupId !== false) {
            $this->continueProcess(0, $this->config->getBatchSize(), $nextGroupId);
        } else {
            $this->config->setImportEndTime(time());
            $this->config->setImportLocked(0);
        }
    }

    /**
     * Returns id of the next customer group, or false if one doesn't exist.
     *
     * @param $customerGroups
     * @param $key
     *
     * @return mixed
     */
    private function getNextGroupId($customerGroups, $key)
    {
        $currentKey = key($customerGroups);
        while ($currentKey !== null && $currentKey != $key) {
            next($customerGroups);
            $currentKey = key($customerGroups);
        }

        return next($customerGroups);
    }

    /**
     * Sets parameters for next piece of customers, and starts background process for them.
     *
     * @param $offset
     * @param $limit
     * @param $groupId
     */
    private function continueProcess($offset, $limit, $groupId)
    {
        $this->backgroundProcessHelper->setOffset($offset);
        $this->backgroundProcessHelper->setContinue(true);
        $this->backgroundProcessHelper->setPassword($this->config->getProductEndpointPassword());
        $this->backgroundProcessHelper->setUrl($this->_url->getRouteUrl('cleverreach/import/importcustomers'));
        $this->backgroundProcessHelper->setLimit($limit);
        $this->backgroundProcessHelper->setGroupId($groupId);

        $this->backgroundProcessHelper->startBackgroundProcess();
    }

    /**
     * Returns properly formatted unregistered subscribers for sending to CleverReach
     *
     * @return array
     */
    private function prepareSubscribers()
    {
        $result = [];
        $subscribers = $this->subscriber->getCollection()
            ->addFieldToFilter('customer_id', 0)
            ->setOrder('subscriber_id', 'ASC')
            ->setPageSize($this->getRequest()->getParam('limit'))
            ->setCurPage($this->getCurrentPage());

        /** @var \Magento\Newsletter\Model\Subscriber $subscriber */
        foreach ($subscribers as $subscriber) {
            $result[] = $this->formatter->getFormattedSubscriberData($subscriber, true);
        }

        return $result;
    }

    /**
     * Returns properly formatted customers for sending to CleverReach
     *
     * @return array
     */
    private function prepareCustomers()
    {
        $result = [];
        $customers = $this->customerRepository->getCollection()
            ->addFieldToFilter('group_id', $this->getRequest()->getParam('groupId'))
            ->setOrder('entity_id', 'ASC')
            ->setPageSize($this->getRequest()->getParam('limit'))
            ->setCurPage($this->getCurrentPage());

        /** @var \Magento\Customer\Model\Customer $customer */
        foreach ($customers as $customer) {
            $result[] = $this->formatter->getFormattedCustomerData($customer, true);
        }

        return $result;
    }

    /**
     * @return int
     */
    private function getCurrentPage()
    {
        $batchSize = $this->config->getBatchSize();
        $startFrom = $this->getRequest()->getParam('startFrom');

        return (int)(($startFrom / $batchSize) + 1);
    }

    /**
     * Registers attributes for group
     *
     * @param \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient
     * @param                                                   $listID
     *
     * @throws \Exception
     */
    private function addAttributes($apiClient, $listID)
    {
        $apiClient->setThrowExceptions(false);

        $apiClient->post("/groups.json/$listID/attributes", ['name' => 'store', 'type' => 'text']);
        $apiClient->post("/groups.json/$listID/attributes", ['name' => 'salutation', 'type' => 'text']);
        $apiClient->post("/groups.json/$listID/attributes", ['name' => 'firstname', 'type' => 'text']);
        $apiClient->post("/groups.json/$listID/attributes", ['name' => 'lastname', 'type' => 'text']);
        $apiClient->post("/groups.json/$listID/attributes", ['name' => 'street', 'type' => 'text']);
        $apiClient->post("/groups.json/$listID/attributes", ['name' => 'postal_number', 'type' => 'text']);
        $apiClient->post("/groups.json/$listID/attributes", ['name' => 'city', 'type' => 'text']);
        $apiClient->post("/groups.json/$listID/attributes", ['name' => 'country', 'type' => 'text']);

        $apiClient->setThrowExceptions(true);
    }
}
