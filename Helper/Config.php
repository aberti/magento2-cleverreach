<?php
/**
 * Class that extends the config abstract class, and has functionality for configuration handling
 *
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Helper;

class Config extends \Logeecom\CleverReach\Model\Configuration\ConfigAbstract
{
    /**
     * @var \Magento\Config\Model\ResourceModel\Config
     */
    private $resourceConfig;

    /**
     * @var \Magento\Customer\Model\ResourceModel\Group\Collection
     */
    private $groupRepository;

    /**
     * @var \Logeecom\CleverReach\Model\Config
     */
    private $configModel;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Config constructor.
     * @param \Logeecom\CleverReach\Model\ResourceModel\Config $resourceConfig
     * @param \Magento\Customer\Model\ResourceModel\Group\Collection $groupRepository
     * @param \Logeecom\CleverReach\Model\Config $configModel
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Logeecom\CleverReach\Model\ResourceModel\Config $resourceConfig,
        \Magento\Customer\Model\ResourceModel\Group\Collection $groupRepository,
        \Logeecom\CleverReach\Model\Config $configModel,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
    
        $this->resourceConfig = $resourceConfig;
        $this->groupRepository = $groupRepository;
        $this->configModel = $configModel;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Returns a value from system database for given name
     *
     * @param string $name
     * @param null $default
     * @param bool $jsonDecode
     * @return mixed
     */
    public function getConfigValue($name, $default = null, $jsonDecode = false)
    {
        $name = self::CLEVER_REACH_GLOBAL . $name;

        $item = $this->configModel->getCollection()->getItemByColumnValue('path', $name);

        if ($item === null) {
            return null;
        }

        $value = $item->getDataByKey('value');

        if ($default != null && $value == null) {
            $value = $default;
        }

        if ($jsonDecode) {
            return json_decode($value, true);
        }

        return $value;
    }

    /**
     * Saves value to system
     *
     * @param string $name
     * @param mixed $value
     * @param bool $jsonEncode
     *
     * @return void
     */
    public function setConfigValue($name, $value, $jsonEncode = false)
    {
        if ($jsonEncode) {
            $value = json_encode($value);
        }

        $name = self::CLEVER_REACH_GLOBAL . $name;
        $this->resourceConfig->saveConfig($name, $value);
    }

    /**
     * Gets status for double opt in
     *
     * @return bool
     */
    public function getDOIStatus()
    {
        $value = $this->scopeConfig->getValue(
            'newsletter/subscription/confirm',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return !($value === null || $value == 0);
    }

    /**
     * Compares mapped CleverReach groups in database and ones returned by CleverReach API
     *
     * @param array $cleverReachGroups
     * @return bool
     */
    public function compareGroups($cleverReachGroups)
    {
        $hadSuccessfulConnection = false;
        $mappedGroups = $this->getGroupMappings();
        $mappedGroups = isset($mappedGroups) ? json_decode($this->getGroupMappings(), true) : [];

        foreach ($mappedGroups as $mappedGroup) {
            foreach ($cleverReachGroups as $cleverReachGroup) {
                if ($mappedGroup['crGroup'] == $cleverReachGroup['id']) {
                    $hadSuccessfulConnection = true;
                    break;
                }
            }
            if ($hadSuccessfulConnection) {
                break;
            }
        }
        return $hadSuccessfulConnection;
    }
}
