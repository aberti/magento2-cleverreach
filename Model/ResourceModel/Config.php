<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Model\ResourceModel;

class Config extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Model Initialization
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init('cleverreach_config', 'id');
    }

    /**
     * Saves config value by path
     *
     * @param $path
     * @param $value
     * @return $this
     */
    public function saveConfig($path, $value)
    {
        $connection = $this->getConnection();
        $select = $connection->select()->from($this->getMainTable())->where(
            'path = ?',
            $path
        );

        $row = $connection->fetchRow($select);

        $newData = ['path' => $path, 'value' => $value];

        if ($row) {
            $whereCondition = [$this->getIdFieldName() . '=?' => $row[$this->getIdFieldName()]];
            $connection->update($this->getMainTable(), $newData, $whereCondition);
        } else {
            $connection->insert($this->getMainTable(), $newData);
        }

        return $this;
    }

    /**
     * Deletes config value by path
     *
     * @param $path
     * @return $this
     */
    public function deleteConfig($path)
    {
        $connection = $this->getConnection();
        $connection->delete($this->getMainTable(), [
            $connection->quoteInto('path = ?', $path),
        ]);

        return $this;
    }
}
