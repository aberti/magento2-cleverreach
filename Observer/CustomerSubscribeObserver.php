<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Newsletter\Model\Subscriber;

/**
 * Observer for customer subscription status update
 *
 * Class CustomerSubscribeObserver
 * @package Logeecom\CleverReach\Observer
 */
class CustomerSubscribeObserver implements ObserverInterface
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
     * @var \Magento\Framework\Logger\Monolog
     */
    private $storeManagerInterface;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $requestInterface;

    /**
     * @var \Logeecom\CleverReach\Helper\Data
     */
    private $dataHelper;

    /**
     * @var \Magento\Framework\Logger\Monolog
     */
    private $monolog;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $httpRequest;

    /**
     * @var \Logeecom\CleverReach\Helper\CustomerFormatter
     */
    private $formatter;

    /**
     * @var \Magento\Newsletter\Model\Subscriber
     */
    private $subscriber;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * CustomerSubscribeObserver constructor.
     *
     * @param \Logeecom\CleverReach\Helper\Config                    $config
     * @param \Magento\Customer\Model\ResourceModel\Group\Collection $groupRepository
     * @param \Logeecom\CleverReach\Helper\CleverReachApiClient      $apiClient
     * @param \Magento\Customer\Model\Customer                       $customer
     * @param \Magento\Store\Model\StoreManagerInterface             $storeManagerInterface
     * @param \Magento\Framework\App\RequestInterface                $requestInterface
     * @param \Logeecom\CleverReach\Helper\Data                      $dataHelper
     * @param \Magento\Framework\Logger\Monolog                      $monolog
     * @param \Logeecom\CleverReach\Helper\CustomerFormatter         $formatter
     * @param \Magento\Newsletter\Model\Subscriber                   $subscriber
     * @param \Magento\Framework\App\RequestInterface                $httpRequest
     * @param \Magento\Framework\App\State                           $state
     */
    public function __construct(
        \Logeecom\CleverReach\Helper\Config $config,
        \Magento\Customer\Model\ResourceModel\Group\Collection $groupRepository,
        \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient,
        \Magento\Customer\Model\Customer $customer,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magento\Framework\App\RequestInterface $requestInterface,
        \Logeecom\CleverReach\Helper\Data $dataHelper,
        \Magento\Framework\Logger\Monolog $monolog,
        \Logeecom\CleverReach\Helper\CustomerFormatter $formatter,
        \Magento\Newsletter\Model\Subscriber $subscriber,
        \Magento\Framework\App\RequestInterface $httpRequest,
        \Magento\Framework\App\State $state
    ) {
        $this->config = $config;
        $this->groupRepository = $groupRepository;
        $this->apiClient = $apiClient;
        $this->customer = $customer;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->requestInterface = $requestInterface;
        $this->dataHelper = $dataHelper;
        $this->monolog = $monolog;
        $this->httpRequest = $httpRequest;
        $this->formatter = $formatter;
        $this->subscriber = $subscriber;
        $this->state = $state;
    }

    /**
     * Listens for customer subscription status update and synchronize it with CleverReach system
     *
     * @param Observer $observer
     *
     * @throws \Exception
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->shouldFireEvent()) {
            return;
        }

        try {
            /** @var  \Magento\Newsletter\Model\Subscriber $subscriber */
            $subscriber = $observer->getDataByKey('subscriber');
            $token = $this->config->getAccessToken();
            $this->apiClient->setAuthMode('bearer', $token);

            // this is magento 2 bug, if customer is changed through magento 2 backend (save customer button),
            // customer is always subscribed
            if ($this->state->getAreaCode() === \Magento\Framework\App\Area::AREA_ADMINHTML &&
                $this->requestInterface->getControllerName() === 'index' &&
                $this->requestInterface->getActionName() === 'save'
            ) {
                $subscription = $this->requestInterface->getParam('subscription') == 1 ?
                    Subscriber::STATUS_SUBSCRIBED : Subscriber::STATUS_UNSUBSCRIBED;

                $subscriber->setStatus($subscription);
            }

            // Website id must be set in order to loadByEmail method can work.
            $customer = $this->customer->setWebsiteId($this->storeManagerInterface->getStore()->getWebsiteId())
                ->loadByEmail($subscriber->getEmail());

            $groupMappings = json_decode($this->config->getGroupMappings(), true);

            if (!isset($groupMappings[$customer->getGroupId()])) {
                return;
            }

            $listId = $groupMappings[$customer->getGroupId()]['crGroup'];

            if ($listId == 0) {
                return;
            }

            $email = $customer->getEmail() ? $customer->getEmail() : $subscriber->getEmail();

            if ($subscriber->isSubscribed() || $subscriber->getStatus() === Subscriber::STATUS_NOT_ACTIVE) {
                if ($customer->getId() == 0) {
                    $subscriberData = $this->formatter->getFormattedSubscriberData($subscriber);

                    $this->apiClient->setThrowExceptions(false);
                    $this->apiClient->post('/groups.json/' . $listId . '/receivers', $subscriberData);
                    $this->apiClient->setThrowExceptions(true);
                }

                if ($this->config->getDOIStatus()) {
                    $formId = $groupMappings[$customer->getGroupId()]['optInForm'];

                    // if form id is not 0, then mail must be send only via CleverReach,
                    // otherwise it needs to be send via magento
                    if ($formId != 0) {
                        $this->dataHelper->sendDOIEmail($formId, $email, $listId);
                    }
                } else {
                    $this->apiClient->put('/groups.json/' . $listId . '/receivers/' . $email . '/setactive');
                }
            } else {
                $this->apiClient->put('/groups.json/' . $listId . '/receivers/' . $email . '/setinactive');
            }
        } catch (LocalizedException $e) {
            $this->monolog->addDebug('Exception on CustomerSubscribeObserver: ' . $e->getMessage());
        }
    }

    /**
     * @return bool
     */
    private function shouldFireEvent()
    {
        if (!$this->config->isConnected()) {
            return false;
        }

        // If this event was fired by customer creating (on frontend), do nothing
        if ($this->requestInterface->getControllerName() === 'account' &&
            $this->requestInterface->getActionName() === 'createpost'
        ) {
            return false;
        }

        return true;
    }
}
