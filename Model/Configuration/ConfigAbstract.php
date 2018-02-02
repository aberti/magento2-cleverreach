<?php
/**
 * Abstract class for module configurations
 *
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Model\Configuration;

abstract class ConfigAbstract
{
    /**
     * Status for incorrect batch
     */
    const INCORRECT_BATCH = 3;

    /**
     * Status for configuration set
     */
    const CONFIGURATION_SET = 4;

    /**
     * Status for configuration reset
     */
    const CONFIGURATION_RESET = 5;

    /**
     * Status for successful connection
     */
    const SUCCESSFUL_CONNECTION = 6;

    /**
     * Status for successful connection
     */
    const UNSUCCESSFUL_CONNECTION = 7;

    /**
     * Status for user that was successfully connected
     */
    const HAD_SUCCESSFUL_CONNECTION = 9;

    /**
     * Path prefix for configurations in configuration table
     */
    const CLEVER_REACH_GLOBAL = 'cleverreach/global/';

    /**
     * Gets access token for CleverReach from database
     *
     * @return mixed
     */
    public function getAccessToken()
    {
        return $this->getConfigValue('access_token');
    }

    /**
     * Returns password for product search from database
     *
     * @return mixed
     */
    public function getProductEndpointPassword()
    {
        return $this->getConfigValue('product_endpoint_password');
    }

    /**
     * Sets debug mode if specified
     *
     * @param $data
     * @return void
     */
    public function setDebugMode($data)
    {
        $this->setConfigValue('debug_mode', $data);
    }

    /**
     * Sets product search
     *
     * @param $data
     * @return void
     */
    public function setProductSearch($data)
    {
        $this->setConfigValue('product_search', $data);
    }

    /**
     * Sets CleverReach ClientID to system database
     *
     * @param $data
     */
    public function setClientId($data)
    {
        $this->setConfigValue('client_id', $data);
    }

    /**
     * Sets CleverReach Client Secret to system database
     *
     * @param $data
     */
    public function setClientSecret($data)
    {
        $this->setConfigValue('client_secret', $data);
    }

    /**
     * Sets access token for CleverReach to system database
     *
     * @param $data
     */
    public function setAccessToken($data)
    {
        $this->setConfigValue('access_token', $data);
    }

    /**
     * Sets password for product search
     *
     * @param $data
     * @return void
     */
    public function setProductEndpointPassword($data)
    {
        $this->setConfigValue('product_endpoint_password', $data);
    }

    /**
     * Sets the batch size for importing data into CleverReach system
     *
     * @param $data
     * @return void
     */
    public function setBatchSize($data)
    {
        $this->setConfigValue('batch_size', $data);
    }

    /**
     * Maps customer groups on Magento to subscriber lists on CleverReach
     *
     * @param $data
     * @return void
     */
    public function setGroupMappings($data)
    {
        $this->setConfigValue('group_mappings', $data, true);
    }

    /**
     * Returns a boolean value that represents whether the product search has been enabled or not
     *
     * @return mixed
     */
    public function isEnabledProductSearch()
    {
        $value = $this->getConfigValue('product_search');

        if ($value !== null && $value == 1) {
            return true;
        }

        return false;
    }

    /**
     * Returns a boolean value that represents whether the debug mode has been enabled or not
     *
     * @return mixed
     */
    public function isEnabledDebugMode()
    {
        $value = $this->getConfigValue('debug_mode');

        if ($value !== null && $value == 1) {
            return true;
        }

        return false;
    }

    /**
     * Returns client's secret
     *
     * @return mixed
     */
    public function getClientSecret()
    {
        return $this->getConfigValue('client_secret');
    }

    /**
     * Returns client's ID
     *
     * @return mixed
     */
    public function getClientId()
    {
        return $this->getConfigValue('client_id');
    }

    /**
     * Returns the batch size that was set in the system database
     *
     * @return mixed
     */
    public function getBatchSize()
    {
        return $this->getConfigValue('batch_size');
    }

    /**
     * Returns all the group mappings that have been set
     *
     * @return mixed
     */
    public function getGroupMappings()
    {
        return $this->getConfigValue('group_mappings', null, true);
    }

    /**
     * Sets import start time
     *
     * @param $data
     *
     * @return void
     */
    public function setImportStartTime($data)
    {
        $this->setConfigValue('import_start_time', $data);
    }

    /**
     * Gets import start time
     *
     * @return mixed
     */
    public function getImportStartTime()
    {
        return $this->getConfigValue('import_start_time');
    }

    /**
     * Locks or unlocks import
     *
     * @param $data
     *
     * @return void
     */
    public function setImportLocked($data)
    {
        $this->setConfigValue('import_locked', $data);
    }

    /**
     * Returns import lock status
     *
     * @return bool
     */
    public function isImportLocked()
    {
        $value = $this->getConfigValue('import_locked');

        if ($value != null && $value == 1) {
            return true;
        }

        return false;
    }

    /**
     * Sets number of customers for import
     *
     * @param $data
     *
     * @return void
     */
    public function setTotalCustomersForImport($data)
    {
        $this->setConfigValue('import_total_customers', $data);
    }

    /**
     * Gets number of customers for import
     *
     * @return int
     */
    public function getTotalCustomersForImport()
    {
        return (int)$this->getConfigValue('import_total_customers');
    }

    /**
     * Sets number of imported customers
     *
     * @param $data
     *
     * @return void
     */
    public function setImportProgress($data)
    {
        $this->setConfigValue('import_progress', $data);
    }

    /**
     * Gets number of imported customers
     *
     * @return int
     */
    public function getImportProgress()
    {
        return (int)$this->getConfigValue('import_progress');
    }

    /**
     * Sets import end time
     *
     * @param $data
     *
     * @return void
     */
    public function setImportEndTime($data)
    {
        $this->setConfigValue('import_end_time', $data);
    }

    /**
     * Gets import end time
     *
     * @return mixed
     */
    public function getImportEndTime()
    {
        return $this->getConfigValue('import_end_time');
    }

    /**
     * Sets connected flag
     *
     * @param $data
     * @return void
     */
    public function setConnected($data)
    {
        $this->setConfigValue('connected', $data);
    }

    /**
     * Gets connected flag
     *
     * @return bool
     */
    public function isConnected()
    {
        $value = $this->getConfigValue('connected');

        if ($value != null && $value == 1) {
            return true;
        }

        return false;
    }

    /**
     * Resets all configurations to default values
     */
    public function resetConfiguration()
    {
        $this->setDebugMode(1);
        $this->setProductSearch(1);
        $this->setAccessToken(null);
        $this->setClientId(null);
        $this->setClientSecret(null);
        $this->setBatchSize(100);
        $this->setGroupMappings(null);
        $this->setConnected(0);
        $this->setImportLocked(0);
    }

    /**
     * Gets all configuration for configuration page purposes
     *
     * @param $groups
     * @return array
     */
    public function getAllConfigs($groups)
    {
        $result = [
            'connected' => $this->isConnected()
        ];

        if ($result['connected']) {
            $result['configurations'] = [
                'clientId' => $this->getClientId(),
                'clientSecret' => $this->getClientSecret(),
                'productSearch' => $this->isEnabledProductSearch(),
                'debugMode' => $this->isEnabledDebugMode(),
                'mappings' => json_decode($this->getGroupMappings(), true),
                'groups' => $groups,
                'batchSize' => $this->getBatchSize(),
            ];

            $result['running'] = $this->isImportLocked();

            if ($result['running']) {
                $result['width'] = $this->getImportProgress();
            }
        }

        return $result;
    }

    /**
     * Gets status for double opt in from system configuration
     *
     * @return bool
     */
    abstract public function getDOIStatus();

    /**
     * Saves value to system
     *
     * @param string $name
     * @param mixed $value
     * @param bool $jsonEncode
     *
     * @return void
     */
    abstract public function setConfigValue($name, $value, $jsonEncode = false);

    /**
     * Returns a value from system database for given name
     *
     * @param string $name
     * @param null $default
     * @param bool $jsonDecode
     * @return mixed
     */
    abstract public function getConfigValue($name, $default = null, $jsonDecode = false);
}
