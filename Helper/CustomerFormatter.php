<?php
/**
 * Class that is responsible for formatting customer for CleverReach
 *
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Helper;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Helper\Context;

/**
 * Format customer for CleverReach
 *
 * Class CustomerFormatter
 * @package Logeecom\CleverReach\Helper
 */
class CustomerFormatter extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    private $orderRepository;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManagerInterface;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var \Magento\Framework\Logger\Monolog
     */
    private $monolog;

    /**
     * @var \Magento\Newsletter\Model\SubscriberFactory
     */
    private $subscriberFactory;

    /**
     * CustomerFormatter constructor.
     *
     * @param Context                                                    $context
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderRepository
     * @param \Magento\Store\Model\StoreManagerInterface                 $storeManagerInterface
     * @param \Magento\Framework\Logger\Monolog                          $monolog
     * @param Config                                                     $config
     * @param \Magento\Newsletter\Model\SubscriberFactory                $subscriberFactory
     */
    public function __construct(
        Context $context,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
        \Magento\Framework\Logger\Monolog $monolog,
        \Logeecom\CleverReach\Helper\Config $config,
        \Magento\Newsletter\Model\SubscriberFactory $subscriberFactory
    ) {
        parent::__construct($context);

        $this->orderRepository = $orderRepository;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->config = $config;
        $this->monolog = $monolog;
        $this->subscriberFactory = $subscriberFactory;
    }

    /**
     * Returns a formatted data for customer
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @param bool                             $import
     *
     * @return array
     */
    public function getFormattedCustomerData($customer, $import = false)
    {
        // website id must be set in order to loadByEmail method can work.
        $customer->setWebsiteId($this->storeManagerInterface->getStore()->getWebsiteId());

        $checkSubscriber = $this->subscriberFactory->create()->loadByEmail($customer->getEmail());

        if ($this->config->isEnabledDebugMode()) {
            $this->monolog->addDebug('Email: ' . $customer->getEmail());
            $this->monolog->addDebug('Registered: ' . strtotime($customer->getDataByKey('created_at')));
            $this->monolog->addDebug('Customer is subscribed: ' . $checkSubscriber->isSubscribed());
        }

        $activated = strtotime($customer->getDataByKey('created_at'));

        // if not subscribed or double opt-in is enabled, should be deactivated on CleverReach
        if (!$import && (!$checkSubscriber->isSubscribed() || $this->config->getDOIStatus())) {
            $activated = 0;
        }

        // On initial import of customers don't send DOI email foreach customer, they already did it
        if ($import && !$checkSubscriber->isSubscribed()) {
            $activated = 0;
        }

        return [
            'email' => $customer->getEmail(),
            'registered' => strtotime($customer->getDataByKey('created_at')),
            'activated' => $activated,
            'deactivated' => $activated == 0 ? '1' : '0',
            'source' => $this->storeManagerInterface->getStore()->getBaseUrl() . ' Magento shop export',
            'attributes' => $this->formatAttributes($customer),
            'orders' => $this->formatOrdersData($customer),
        ];
    }

    /**
     * Returns a formatted data for customer
     *
     * @param \Magento\Newsletter\Model\Subscriber $subscriber
     * @param bool                                 $import
     *
     * @return array
     */
    public function getFormattedSubscriberData($subscriber, $import = false)
    {
        if ($this->config->isEnabledDebugMode()) {
            $this->monolog->addDebug('Subscriber is subscribed: ' . $subscriber->isSubscribed());
        }

        $activated = time();

        // if not subscribed or double opt-in is enabled, should be deactivated on CleverReach
        if (!$import && (!$subscriber->isSubscribed() || $this->config->getDOIStatus())) {
            $activated = 0;
        }

        // On initial import of subscribers don't send DOI email foreach customer, they already did it
        if ($import && !$subscriber->isSubscribed()) {
            $activated = 0;
        }

        return [
            'email' => $subscriber->getEmail(),
            'registered' => time(),
            'activated' => $activated,
            'deactivated' => $activated == 0 ? '1' : '0',
            'source' => $this->storeManagerInterface->getStore()->getBaseUrl() . ' Magento shop export',
            'attributes' => [],
            'orders' => [],
        ];
    }

    /**
     * Sets required attributes for customer
     *
     * @param \Magento\Customer\Model\Customer $customer
     *
     * @return array
     */
    private function formatAttributes($customer)
    {
        $attributesData = [
            // get store name
            'store' => $customer->getStore()->getName(),
            // if gender is 1, the customer is male, otherwise female
            'salutation' => $customer->getDataByKey('gender') == 1 ? __('Mr') : __('Mrs'),
            'firstname' => $customer->getDataByKey('firstname'),
            'lastname' => $customer->getDataByKey('lastname'),
        ];

        if ($customer->getDefaultShippingAddress()) {
            $attributesData['street'] = $customer->getDefaultShippingAddress()->getStreet()[0];
            $attributesData['postal_number'] = $customer->getDefaultShippingAddress()->getPostcode();
            $attributesData['city'] = $customer->getDefaultShippingAddress()->getCity();
            $attributesData['country'] = $customer->getDefaultShippingAddress()->getCountryModel()->getName();
        }

        return $attributesData;
    }

    /**
     * Returns formatted orders data for given customer
     *
     * @param \Magento\Customer\Model\Customer $customer
     *
     * @return array
     */
    private function formatOrdersData($customer)
    {
        $ordersData = [];
        $orders = $this->orderRepository->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', $customer->getId())
            ->getItems();

        /** @var \Magento\Sales\Model\Order $order */
        foreach ($orders as $order) {
            foreach ($order->getItems() as $item) {
                if ($item->getProductType() === Configurable::TYPE_CODE) {
                    continue;
                }

                $ordersData[] = [
                    'order_id' => $order->getId(),
                    'product' => $item->getName(),
                    'product_id' => $item->getSku(),
                    'price' => $item->getPrice(),
                    'currency' => $order->getOrderCurrency()->getCurrencyCode(),
                    'amount' => $item->getRowTotal(),
                ];
            }
        }

        return $ordersData;
    }
}
