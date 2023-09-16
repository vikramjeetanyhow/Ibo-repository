<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ibo\DynamicBlockScript\Model\BlockScript\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Class IsActive
 */

class Blocks implements OptionSourceInterface
{
    protected $googleStore;

    public function __construct(
        BlockRepositoryInterface $blockRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->blockRepository = $blockRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray()
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $cmsBlocks = $this->blockRepository->getList($searchCriteria)->getItems();

        $options = [];

        foreach ($cmsBlocks as $block) {
            $options[] = ['value' => $block->getIdentifier(), 'label' => $block->getTitle()];
        }
        return $options;
    }
}
