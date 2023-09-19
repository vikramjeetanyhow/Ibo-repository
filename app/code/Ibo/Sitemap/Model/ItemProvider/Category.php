<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Ibo\Sitemap\Model\ItemProvider;

use Magento\Sitemap\Model\ResourceModel\Catalog\CategoryFactory;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use Magento\Catalog\Model\CategoryRepository;

class Category extends \Magento\Sitemap\Model\ItemProvider\Category 
{
    /**
     * Category factory.
     *
     * @var CategoryFactory
     */
    private $categoryFactory;

    /**
     * Sitemap item factory.
     *
     * @var SitemapItemInterfaceFactory
     */
    private $itemFactory;

    /**
     * Config reader.
     *
     * @var ConfigReaderInterface
     */
    private $configReader;

    /**
     * CategorySitemapItemResolver constructor.
     */
    public function __construct(
        \Magento\Sitemap\Model\ItemProvider\ConfigReaderInterface $configReader,
        CategoryFactory $categoryFactory,
        SitemapItemInterfaceFactory $itemFactory,
        CategoryRepository $categoryRepository
    ) {
        $this->categoryFactory = $categoryFactory;
        $this->itemFactory = $itemFactory;
        $this->configReader = $configReader;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems($storeId)
    {
        $collection = $this->categoryFactory->create()
            ->getCollection($storeId);
        

        $items = array_map(function ($item) use ($storeId) {

            $category = $this->categoryRepository->get($item->getId(), $storeId);

            if (strpos($item->getUrl(), "/b/")!==false){
                return $this->itemFactory->create([
                    'url' => $item->getUrl(),
                    'updatedAt' => $item->getUpdatedAt(),
                    'images' => $item->getImages(),
                    'priority' => $this->configReader->getPriorityBrand($storeId),
                    'changeFrequency' => $this->configReader->getChangeFrequencyBrandUrls($storeId),
                ]);
            }else{

                switch($category->getData('level')){
                    case 2:
                        $priorityNew = $this->configReader->getPriority($storeId);
                    break;

                    case 3:
                        $priorityNew = $this->configReader->getPrioritylevel2($storeId);
                    break;

                    case 4:
                        $priorityNew = $this->configReader->getPrioritylevel3($storeId);
                    break; 

                    default:
                        $priorityNew = $this->configReader->getPriority($storeId);

                }

                return $this->itemFactory->create([
                    'url' => $item->getUrl(),
                    'updatedAt' => $item->getUpdatedAt(),
                    'images' => $item->getImages(),
                    'priority' => $priorityNew,
                    'changeFrequency' => $this->configReader->getChangeFrequency($storeId),
                ]);
            }

        }, $collection);

        return $items;
    }
}
