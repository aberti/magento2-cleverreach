<?php
/**
 * @package     Logeecom_CleverReach
 * @author      CleverReach
 * @copyright   2017 CleverReach
 */

namespace Logeecom\CleverReach\Controller\Endpoint;

use Logeecom\CleverReach\Helper\Data;

/**
 * Controller responsible for exporting product from magento to CleverReach
 *
 * Class Product
 *
 * @package Logeecom\CleverReach\Controller\Endpoint
 */
class Product extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Logeecom\CleverReach\Helper\Config
     */
    private $config;

    /**
     * @var \Magento\Catalog\Model\Product
     */
    private $productRepository;

    /**
     * @var \Magento\Catalog\Helper\Product
     */
    private $catalogProductHelper;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private $resultJsonFactory;

    /**
     * Product constructor.
     *
     * @param \Magento\Framework\App\Action\Context            $context
     * @param \Logeecom\CleverReach\Helper\Config              $config
     * @param \Magento\Catalog\Model\Product                   $productRepository
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Catalog\Helper\Product                  $catalogProductHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Logeecom\CleverReach\Helper\Config $config,
        \Magento\Catalog\Model\Product $productRepository,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Catalog\Helper\Product $catalogProductHelper
    ) {
        $this->config = $config;
        $this->productRepository = $productRepository;
        $this->catalogProductHelper = $catalogProductHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    /**
     * Returns information for product with given id or sku. If there is no such product, return appropriate message
     *
     * @return mixed
     */
    public function execute()
    {
        if (!$this->config->isEnabledProductSearch() ||
            $this->_request->getParam('password') !== $this->config->getProductEndpointPassword()
        ) {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setUrl($this->_url->getBaseUrl());

            return $resultRedirect;
        }

        $param = $this->_request->getParam('get');

        switch ($param) {
            case 'filter':
                $filter = [
                    'name' => 'Product SKU or ID',
                    'description' => '',
                    'required' => false,
                    'query_key' => 'sku',
                    'type' => 'input',
                ];

                $filters[] = (object)$filter;

                return $this->resultJsonFactory->create()->setData($filters);
            case 'search':
                $items = [
                    'settings' => [
                        'type' => 'product',
                        'link_editable' => false,
                        'link_text_editable' => false,
                        'image_size_editable' => false,
                    ],
                    'items' => [],
                ];

                $skuOrId = $this->_request->getParam('sku');

                $productCollection = $this->productRepository->getCollection()
                    ->addAttributeToSelect('*')
                    ->addAttributeToFilter([
                        [
                            'attribute' => 'sku',
                            'eq' => $skuOrId
                        ],
                        [
                            'attribute' => 'entity_id',
                            'eq' => $skuOrId
                        ]
                    ]);

                if ($productCollection->getSize() === 0) {
                    return $this->resultJsonFactory->create()->setData([
                        'status' => Data::NO_PRODUCT,
                        'message' => __('There is no product with given SKU or ID'),
                    ]);
                }

                /** @var  \Magento\Catalog\Model\Product $product */
                foreach ($productCollection as $product) {
                    if ($skuOrId !== $product->getId() && $skuOrId !== $product->getSku()) {
                        continue;
                    }

                    $items['items'][] = [
                        'title' => $product->getName(),
                        'description' => $product->getDescription(),
                        'image' => $this->catalogProductHelper->getImageUrl($product),
                        'price' => $product->getPrice(),
                        'url' => $this->catalogProductHelper->getProductUrl($product)
                    ];
                }

                $items = (object)$items;

                return $this->resultJsonFactory->create()->setData($items);
        }
    }
}
