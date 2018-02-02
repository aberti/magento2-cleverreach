<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Controller\Adminhtml\Configuration;

/**
 * Class that is responsible configuration reset
 */
class Reset extends \Magento\Backend\App\Action
{
    /**
     * @var \Logeecom\CleverReach\Helper\Config
     */
    private $config;

    /**
     * Reset constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Logeecom\CleverReach\Helper\Config $config
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Logeecom\CleverReach\Helper\Config $config
    ) {
    
        $this->config = $config;
        parent::__construct($context);
    }

    /**
     * Controller action that resets configuration and
     * redirects to configuration page
     */
    public function execute()
    {
        $this->config->resetConfiguration();

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('cleverreach/configuration/index');

        return $resultRedirect;
    }
}
