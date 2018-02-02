<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Observer;

use Magento\Customer\Model\Customer;
use Magento\Newsletter\Model\Subscriber;
use Magento\Sales\Model\Order;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderItemInterface as Item;

/**
 * Observer for order success
 *
 * Class OrderSuccessObserver
 * @package Logeecom\CleverReach\Observer
 */
class OrderSuccessObserver implements ObserverInterface
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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManagerInterface;

    /**
     * @var \Magento\Framework\Logger\Monolog
     */
    private $monolog;

    /**
     * OrderSuccessObserver constructor.
     *
     * @param \Logeecom\CleverReach\Helper\Config                    $config
     * @param \Magento\Customer\Model\ResourceModel\Group\Collection $groupRepository
     * @param \Logeecom\CleverReach\Helper\CleverReachApiClient      $apiClient
     * @param \Magento\Customer\Model\Customer                       $customer
     * @param \Logeecom\CleverReach\Helper\CustomerFormatter         $formatter
     * @param \Magento\Newsletter\Model\Subscriber                   $subscriber
     * @param \Logeecom\CleverReach\Helper\Data                      $dataHelper
     * @param \Magento\Store\Model\StoreManagerInterface             $storeManagerInterface
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
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magento\Framework\Logger\Monolog $monolog
    ) {
        $this->config = $config;
        $this->groupRepository = $groupRepository;
        $this->apiClient = $apiClient;
        $this->customer = $customer;
        $this->formatter = $formatter;
        $this->dataHelper = $dataHelper;
        $this->subscriber = $subscriber;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->monolog = $monolog;
    }

    /**
     * Listens for order success and send information to CleverReach
     *
     * @param Observer $observer
     *
     * @throws \Exception
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // If not connected to cleverreach skip execution
        if (!$this->config->isConnected()) {
            return;
        }

        /** @var Order $order */
        $order = $observer->getDataByKey('order');
        $groupMappings = json_decode($this->config->getGroupMappings(), true);

        // It may happen that user configures CleverReach plugin and later adds new customer groups.
        if (empty($groupMappings[$order->getCustomerGroupId()])) {
            return;
        }

        $groupMapping = $groupMappings[$order->getCustomerGroupId()];
        $listId = $groupMapping['crGroup'];
        $enabledDebugMode = $this->config->isEnabledDebugMode();

        foreach ($order->getItems() as $item) {
            if ($item->getProductType() === \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                continue;
            }

            $data = [
                'group_id' => $listId,
                'order_id' => $order->getId(),
                'product' => $item->getName(),
                'product_id' => $item->getSku(),
                'quantity' => $item->getQtyOrdered(),
                'price' => $item->getPrice(),
                'stamp' => strtotime($order->getCreatedAt()),
                'source' => $this->storeManagerInterface->getStore()->getBaseUrl() . ' | Magento shop export',
            ];

            try {
                $catalogSession = ObjectManager::getInstance()->get('Magento\Catalog\Model\Session');
                $productsViewedFromCleverReach = $catalogSession->getData('cr_campaign');
                // Check if this product is viewed from CleverReach campaign
                $this->checkIfProductIsViewed($productsViewedFromCleverReach, $item, $data);

                if ($enabledDebugMode) {
                    $this->monolog->addDebug('Order tracking data in session: ' .
                        json_encode($productsViewedFromCleverReach));
                    $this->monolog->addDebug('Sending order data: ' . json_encode($data, true));
                }

                $this->apiClient->setAuthMode('bearer', $this->config->getAccessToken());
                $result = $this->apiClient->post('/receivers.json/' . $order->getCustomerEmail() . '/orders', $data);

                if (!empty($result['error']['code']) && $result['error']['code'] === 404) {
                    // User is not created on CleverReach
                    $this->createNewRecipient($listId, $groupMapping, $order);

                    return;
                }

                if ($enabledDebugMode) {
                    $this->monolog->addDebug('Sending order data result for customer ' . $order->getCustomerEmail() .
                        ': ' . json_encode($result, true));
                }
            } catch (LocalizedException $e) {
                $this->monolog->addDebug('Exception on order: ' . $e->getMessage());
            }
        }
    }

    /**
     * @param $productsViewedFromCleverReach
     * @param Item $item
     * @param $data
     */
    private function checkIfProductIsViewed($productsViewedFromCleverReach, $item, &$data)
    {
        $products = $productsViewedFromCleverReach === null ? [] : $productsViewedFromCleverReach;
        if (array_key_exists($item->getProductId(), $products)) {
            $data['mailings_id'] = $products[$item->getProductId()];
        }
    }

    /**
     * Create new recipient if not exit on CR
     *
     * @param int $listId
     * @param array $groupMapping
     * @param Order $order
     */
    private function createNewRecipient($listId, $groupMapping, $order)
    {
        /** @var Customer $customer */
        $customer = $this->customer->setWebsiteId($this->storeManagerInterface->getStore()->getWebsiteId())
            ->loadByEmail($order->getCustomerEmail());
        $email = $customer->getEmail();

        if (empty($email)) {
            return;
        }

        $customerData = $this->formatter->getFormattedCustomerData($customer);
        $result = $this->apiClient->post('/groups.json/' . $listId . '/receivers', $customerData);

        $checkSubscriber = $this->subscriber->loadByEmail($this->customer->getEmail());

        if ($checkSubscriber->isSubscribed()) {
            if ($this->config->getDOIStatus()) {
                $this->apiClient->put('/groups.json/' . $listId . '/receivers/' . $email . '/setinactive');
                $formId = $groupMapping['optInForm'];

                // If form id is not 0, then mail must be send only via CleverReach,
                // otherwise it needs to be send via magento
                if ($formId != 0) {
                    $this->dataHelper->sendDOIEmail($formId, $email, $listId);
                }
            } else {
                $this->apiClient->put('/groups.json/' . $listId . '/receivers/' . $email . '/setactive');
            }
        }

        if ($this->config->isEnabledDebugMode()) {
            $this->monolog->addDebug('Sending new created recipient ' . $email .
                ': ' . json_encode($result, true));
        }
    }
}
