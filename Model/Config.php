<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Model;

class Config extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Constructor
     *
     * @return void
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('Logeecom\CleverReach\Model\ResourceModel\Config');
    }
}
