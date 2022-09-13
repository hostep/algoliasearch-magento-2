<?php

namespace Algolia\AlgoliaSearch\Observer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Module\Manager;

class ReindexProductOnLastItemPurchaseIfMsiDisable implements ObserverInterface
{
    protected $indexer;

    /** @var Manager  */
    protected $moduleManager;

    /** @var ProductRepositoryInterface  */
    protected  $productRepository;

    /** @var IndexerRegistry  */
    protected $indexerRegistry;

    /**
     * @param Manager $moduleManager
     * @param ProductRepositoryInterface $productRepository
     * @param IndexerRegistry $indexerRegistry
     */
    public function __construct(
        Manager $moduleManager,
        ProductRepositoryInterface $productRepository,
        IndexerRegistry $indexerRegistry
    ) {
        $this->moduleManager = $moduleManager;
        $this->productRepository = $productRepository;
        $this->indexer = $indexerRegistry->get('algolia_products');
    }

    public function execute(Observer $observer)
    {
        if (!$this->moduleManager->isEnabled('Magento_Inventory')) {
            $quote = $observer->getEvent()->getQuote();
            $productIds = [];
            foreach ($quote->getAllItems() as $item) {
                $productIds[$item->getProductId()] = $item->getProductId();
                $children = $item->getChildrenItems();
                if ($children) {
                    foreach ($children as $childItem) {
                        $productIds[$childItem->getProductId()] = $childItem->getProductId();
                    }
                }
            }

            if($productIds) {
                $productTobeReindex = [];
                foreach ($productIds as $productId) {
                    $product = $this->productRepository->getById($productId);
                    $stockInfo = $product->getData('quantity_and_stock_status');
                    if ($stockInfo['qty'] < 1) {
                        $productTobeReindex[] = $productId;
                    }
                }
                $this->indexer->reindexList($productTobeReindex);
            }
        }
    }
}
