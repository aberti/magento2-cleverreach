<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;

class Subscriber extends \Magento\Newsletter\Model\Subscriber
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
     * @var \Magento\Customer\Model\Customer
     */
    private $customer;

    /**
     * Sends out confirmation email
     */
    public function sendConfirmationRequestEmail()
    {
        $this->sendConfirmationEmail(__FUNCTION__);
    }

    /**
     * Sends out confirmation success email
     */
    public function sendConfirmationSuccessEmail()
    {
        $this->sendConfirmationEmail(__FUNCTION__);
    }

    /**
     * Check if Magento DOI email should be sent
     *
     * @param string parentMethod
     * @throws LocalizedException
     * @return Subscriber
     */
    private function sendConfirmationEmail($parentMethod = '')
    {
        $this->config = ObjectManager::getInstance()->get('Logeecom\CleverReach\Helper\Config');
        $this->apiClient = ObjectManager::getInstance()->get('Logeecom\CleverReach\Helper\CleverReachApiClient');
        $this->customer = ObjectManager::getInstance()->get('Magento\Customer\Model\Customer');

        // is double opt-in enabled in system configurations
        $isConfirmNeed = $this->config->getDOIStatus();

        // if double opt in is not enabled proceed with default magento behavior
        if (!$isConfirmNeed) {
            return parent::$parentMethod();
        }

        $this->apiClient->setAuthMode('bearer', $this->config->getAccessToken());

        try {
            $formId = 0;
            $customer = $this->customer->setWebsiteId($this->_storeManager->getStore()->getWebsiteId())
                ->loadByEmail($this->getEmail());

            $mappings = json_decode($this->config->getGroupMappings(), true);

            if (isset($mappings[$customer->getGroupId()])) {
                $formId = $mappings[$customer->getGroupId()]['optInForm'];
            }

            // CleverReach opt-in form is not selected in mappings table, email needs to be sent from magento
            if ($formId == 0) {
                return parent::$parentMethod();
            }
        } catch (LocalizedException $e) {
            throw new LocalizedException(__($e->getMessage()));
        }

        return $this;
    }
}
