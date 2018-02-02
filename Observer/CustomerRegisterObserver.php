<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Observer for customer registration
 *
 * Class CustomerRegisterObserver
 * @package Logeecom\CleverReach\Observer
 */
class CustomerRegisterObserver implements ObserverInterface
{
    /**
     * @var \Logeecom\CleverReach\Helper\Config
     */
    private $config;

    /**
     * @var \Magento\Customer\Model\ResourceModel\Group\Collection
     */
    private $groupRepository;

    /**
     * @var \Logeecom\CleverReach\Helper\CleverReachApiClient
     */
    private $apiClient;

    /**
     * @var \Magento\Customer\Model\Customer\
     */
    private $customer;

    /**
     * @var \Logeecom\CleverReach\Helper\CustomerFormatter
     */
    private $formatter;

    /**
     * @var \Logeecom\CleverReach\Helper\Data
     */
    private $dataHelper;

    /**
     * @var \Magento\Newsletter\Model\Subscriber
     */
    private $subscriber;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $requestInterface;

    /**
     * @var \Magento\Framework\Logger\Monolog
     */
    private $monolog;

    /**
     * CustomerRegisterObserver constructor.
     *
     * @param \Logeecom\CleverReach\Helper\Config                    $config
     * @param \Magento\Customer\Model\ResourceModel\Group\Collection $groupRepository
     * @param \Logeecom\CleverReach\Helper\CleverReachApiClient      $apiClient
     * @param \Magento\Customer\Model\Customer                       $customer
     * @param \Logeecom\CleverReach\Helper\CustomerFormatter         $formatter
     * @param \Magento\Newsletter\Model\Subscriber                   $subscriber
     * @param \Logeecom\CleverReach\Helper\Data                      $dataHelper
     * @param \Magento\Framework\App\RequestInterface                $requestInterface
     * @param \Magento\Framework\Logger\Monolog                      $monolog
     */
    public function __construct(
        \Logeecom\CleverReach\Helper\Config $config,
        \Magento\Customer\Model\ResourceModel\Group\Collection $groupRepository,
        \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient,
        \Magento\Customer\Model\Customer $customer,
        \Logeecom\CleverReach\Helper\CustomerFormatter $formatter,
        \Magento\Newsletter\Model\Subscriber $subscriber,
        \Logeecom\CleverReach\Helper\Data $dataHelper,
        \Magento\Framework\App\RequestInterface $requestInterface,
        \Magento\Framework\Logger\Monolog $monolog
    ) {
        $this->config = $config;
        $this->groupRepository = $groupRepository;
        $this->apiClient = $apiClient;
        $this->customer = $customer;
        $this->formatter = $formatter;
        $this->dataHelper = $dataHelper;
        $this->subscriber = $subscriber;
        $this->requestInterface = $requestInterface;
        $this->monolog = $monolog;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // If not connected to cleverreach skip execution
        if (!$this->config->isConnected()) {
            return;
        }

        try {
            /** @var  \Magento\Customer\Model\Data\Customer $customerData */
            $customerData = $observer->getDataByKey('customer');

            $this->customer->updateData($customerData);

            $groupMappings = json_decode($this->config->getGroupMappings(), true);

            if (!isset($groupMappings[$this->customer->getGroupId()])) {
                return;
            }

            $listId = $groupMappings[$this->customer->getGroupId()]['crGroup'];

            if ($listId == 0) {
                return;
            }

            $customerData = $this->formatter->getFormattedCustomerData($this->customer);

            $this->apiClient->setAuthMode('bearer', $this->config->getAccessToken());

            $this->apiClient->setThrowExceptions(false);
            $this->apiClient->post('/groups.json/' . $listId . '/receivers', $customerData);
            $this->apiClient->setThrowExceptions(true);

            $checkSubscriber = $this->subscriber->loadByEmail($this->customer->getEmail());

            if ($checkSubscriber->isSubscribed() && $this->config->getDOIStatus()) {
                $formId = $groupMappings[$this->customer->getGroupId()]['optInForm'];

                // if form id is not 0, then mail must be send only via CleverReach,
                // otherwise it needs to be send via magento
                if ($formId != 0) {
                    $this->dataHelper->sendDOIEmail($formId, $this->customer->getEmail(), $listId);
                }
            }
        } catch (LocalizedException $e) {
            $this->monolog->addDebug('Exception on CustomerRegisterObserver: ' . $e->getMessage());
        }
    }
}
