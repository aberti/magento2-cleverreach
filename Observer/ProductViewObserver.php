<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ObjectManager;

/**
 * Observer for product view
 *
 * Class ProductViewObserver
 * @package Logeecom\CleverReach\Observer
 */
class ProductViewObserver implements ObserverInterface
{
    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $httpRequest;

    /**
     * @var \Magento\Framework\Logger\Monolog
     */
    private $monolog;

    /**
     * @var \Logeecom\CleverReach\Helper\Config
     */
    private $config;

    /**
     * ProductViewObserver constructor.
     * @param \Magento\Framework\App\RequestInterface $httpRequest
     * @param \Magento\Framework\Logger\Monolog $monolog
     * @param \Logeecom\CleverReach\Helper\Config $config
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $httpRequest,
        \Magento\Framework\Logger\Monolog $monolog,
        \Logeecom\CleverReach\Helper\Config $config
    ) {
    
        $this->httpRequest = $httpRequest;
        $this->config = $config;
        $this->monolog = $monolog;
    }

    /**
     * Listens for product view and check if customer followed link from CleverReach campaign
     *
     * @param Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $crMailing = $this->httpRequest->getParam('crmailing');

        if (!isset($crMailing)) {
            return;
        }

        /** @var  \Magento\Catalog\Model\Product\Interceptor $product */
        $product = $observer->getDataByKey('product');

        if ($product === null || $product->getId() === null) {
            return;
        }

        $catalogSession = ObjectManager::getInstance()->get('Magento\Catalog\Model\Session');
        $data = $catalogSession->getData('cr_campaign');

        if (empty($data)) {
            $data = [ $product->getId() => $crMailing ];
        } else {
            $data[$product->getId()] = $crMailing;
        }

        if ($this->config->isEnabledDebugMode()) {
            $this->monolog->addDebug('Added order tracking data: ' . json_encode($data));
        }

        $catalogSession->setData('cr_campaign', $data);
    }
}
