<?php
/**
 * Config controller
 *
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Controller\Adminhtml\Import;

class CheckProgress extends \Magento\Backend\App\Action
{
    /**
     * @var \Logeecom\CleverReach\Helper\Config
     */
    private $config;

    /**
     * @var \Logeecom\CleverReach\Helper\BackgroundProcess
     */
    private $backgroundProcessHelper;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $resultJsonFactory;

    /**
     * CheckProgress constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Logeecom\CleverReach\Helper\Config $config
     * @param \Logeecom\CleverReach\Helper\BackgroundProcess $background
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Logeecom\CleverReach\Helper\Config $config,
        \Logeecom\CleverReach\Helper\BackgroundProcess $background,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
    
        $this->config = $config;
        $this->backgroundProcessHelper = $background;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    /**
     * Returns current state of the import process
     *
     * @return mixed
     */
    public function execute()
    {
        return $this->resultJsonFactory->create()->setData([
            'status' => $this->backgroundProcessHelper->setCount($this->config->getTotalCustomersForImport())
                                                      ->setCurrent($this->config->getImportProgress())
                                                      ->getCurrentState(),
        ]);
    }
}
