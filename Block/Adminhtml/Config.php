<?php

namespace Logeecom\CleverReach\Block\Adminhtml;

/**
 * Configuration for CleverReach template
 *
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */
class Config extends \Magento\Backend\Block\Template
{
    /**
     * @var \Magento\Customer\Model\ResourceModel\Group\Collection
     */
    private $groupCollection;

    /**
     * @var \Logeecom\CleverReach\Helper\Config
     */
    private $config;

    /**
     * @var \Magento\Backend\Model\UrlInterface
     */
    private $backendUrl;

    /**
     * @var \Magento\Framework\App\DeploymentConfig\Reader
     */
    private $configReader;

    /**
     * @var \Logeecom\CleverReach\Helper\CleverReachApiClient
     */
    private $apiClient;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    private $authSession;

    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    private $countryFactory;

    /**
     * @var int
     */
    private $responseCode = 0;

    /**
     * @var string
     */
    private $errorMessage = '';

    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Customer\Model\ResourceModel\Group\Collection $groupCollection
     * @param \Logeecom\CleverReach\Helper\Config $config
     * @param \Magento\Framework\App\DeploymentConfig\Reader $configReader
     * @param \Magento\Backend\Model\UrlInterface $backendUrl
     * @param \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient
     * @param \Magento\Backend\Model\Auth\Session\Proxy $authSession
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Customer\Model\ResourceModel\Group\Collection $groupCollection,
        \Logeecom\CleverReach\Helper\Config $config,
        \Magento\Framework\App\DeploymentConfig\Reader $configReader,
        \Magento\Backend\Model\UrlInterface $backendUrl,
        \Logeecom\CleverReach\Helper\CleverReachApiClient $apiClient,
        \Magento\Backend\Model\Auth\Session\Proxy $authSession,
        \Magento\Directory\Model\CountryFactory $countryFactory
    ) {
        parent::__construct($context);

        $this->groupCollection = $groupCollection;
        $this->config = $config;
        $this->configReader = $configReader;
        $this->backendUrl = $backendUrl;
        $this->apiClient = $apiClient;
        $this->scopeConfig = $context->getScopeConfig();
        $this->authSession = $authSession;
        $this->countryFactory = $countryFactory;
    }

    /**
     * Returns true if the settings has already been set, otherwise returns false
     *
     * @return bool
     */
    public function isConnected()
    {
        $status = false;
        if ($this->config->isConnected()) {
            $this->apiClient->setAuthMode('bearer', $this->config->getAccessToken());
            $response = $this->apiClient->get('/clients/whoami');

            if (isset($response['error']) && isset($response['error']['message'])
                && isset($response['error']['code'])) {
                $this->errorMessage = $response['error']['message'];
                $this->responseCode = $response['error']['code'];
                $this->config->setConnected(0);
            } else {
                $status = true;
            }
        }

        return $status;
    }

    /**
     * Returns response message and response code
     *
     * @return mixed
     */
    public function getMessageAndCode()
    {
        $result['errorMessage'] = $this->errorMessage;
        $result['responseCode'] = $this->responseCode;
        return $result;
    }

    /**
     * Gets admin URL
     */
    public function getAdminUrl()
    {
        $config = $this->configReader->load();
        $adminSuffix = $config['backend']['frontName'];

        return $this->getBaseUrl() . $adminSuffix;
    }

    /**
     * Gets reset to default action URL
     *
     * @return string
     */
    public function getResetUrl()
    {
        return $this->backendUrl->getUrl('cleverreach/configuration/reset');
    }

    /**
     * Returns authorization URL
     *
     * @return string
     */
    public function getAuthUrl()
    {
        $countryCode = $this->scopeConfig->getValue(
            \Magento\Store\Model\Information::XML_PATH_STORE_INFO_COUNTRY_CODE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $country = $countryCode ? $this->countryFactory->create()->loadByCode($countryCode)->getName() : '';

        $user = $this->authSession->getUser();
        $registerData = [
            'email' => $user->getEmail(),
            'firstname' => $user->getFirstName(),
            'lastname' => $user->getLastName(),
            'company' => $this->scopeConfig->getValue(
                \Magento\Store\Model\Information::XML_PATH_STORE_INFO_NAME,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'street' => $this->scopeConfig->getValue(
                \Magento\Store\Model\Information::XML_PATH_STORE_INFO_STREET_LINE1,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ) . $this->scopeConfig->getValue(
                \Magento\Store\Model\Information::XML_PATH_STORE_INFO_STREET_LINE2,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'zip' => $this->scopeConfig->getValue(
                \Magento\Store\Model\Information::XML_PATH_STORE_INFO_POSTCODE,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'city' => $this->scopeConfig->getValue(
                \Magento\Store\Model\Information::XML_PATH_STORE_INFO_CITY,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'country' => $country,
            'phone' => $this->scopeConfig->getValue(
                \Magento\Store\Model\Information::XML_PATH_STORE_INFO_PHONE,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
        ];

        $registerData = base64_encode(json_encode($registerData));

        return $this->apiClient->getLoginUrl(
            $this->_storeManager->getStore()->getBaseUrl() . 'cleverreach/oauth/callback/',
            $registerData
        );
    }

    /**
     * Reads Magento customer groups from database
     *
     * @return array
     */
    public function getMappings()
    {
        $mappings = [];

        foreach ($this->groupCollection->getItems() as $option) {
            $mappings[$option->getDataByKey('customer_group_id')] = $option->getDataByKey('customer_group_code');
        }

        $mappings['-1'] = __('Unregistered subscribers');

        return $mappings;
    }
}
