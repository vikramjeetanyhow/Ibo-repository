<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\ProductFilter\DataProvider\Product\LayeredNavigation;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Registry;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Magento\Eav\Model\Config;
use Magento\Swatches\Block\LayeredNavigation\RenderLayered;
use Magento\Swatches\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;


/**
 * @inheritdoc
 */
class LayerBuilder extends \Magento\CatalogGraphQl\DataProvider\Product\LayeredNavigation\LayerBuilder
{
    /**
     * @var LayerBuilderInterface[]
     */
    private $builders;

    /**
     *
     * @var Registry
     */
    protected $registry;

    /** @var Uid */
    private $uidEncoder;

    /**
     * @var AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var ModelConfig
     */
    protected $eavConfig;

    /**
     * @var SwatchData
     */
    private $swatchHelper;

    /**
     * @var RenderLayered
     */
    private $renderLayered;

    /**
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    protected $defaultAttributeList = ['category_id'];


    /**
    * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
    */
    protected $_categoryFactory;

    /**
     *
     * @param LayerBuilderInterface[] $builders
     * @param Registry $registry
     * @param ScopeConfigInterface $scopeConfig
     * @param AttributeRepository $attributeRepository
     * @param Uid $uidEncoder
     */
    public function __construct(
        array $builders,
        ScopeConfigInterface $scopeConfig,
        Registry $registry,
        AttributeRepository $attributeRepository,
        Uid $uidEncoder,
        Config $eavConfig,
        Data $swatchHelper,
        RenderLayered $renderLayered,
        CollectionFactory $categoryFactory
    ) {
        $this->builders = $builders;
        $this->registry = $registry;
        $this->scopeConfig = $scopeConfig;
        $this->attributeRepository = $attributeRepository;
        $this->uidEncoder = $uidEncoder;
        $this->eavConfig = $eavConfig;
        $this->swatchHelper = $swatchHelper;
        $this->renderLayered = $renderLayered;
        $this->_categoryFactory = $categoryFactory;
    }
    /**
     * @inheritdoc
     */
    public function build(AggregationInterface $aggregation, ?int $storeId): array
    {
        $layers = [];
        foreach ($this->builders as $builder) {
            $layers[] = $builder->build($aggregation, $storeId);
        }
        $layers = \array_merge([], ...$layers);

        $defaultAttribute = $this->scopeConfig->getValue("excludeattribute/att_config/exclude_attributes");
        if ($defaultAttribute!='') {
           $this->defaultAttributeList =  explode(',', $defaultAttribute);
        }

        //start custom code to add is_selected
        if (!$this->registry->registry('hide_product')) {
            $filteredAttrs = $this->registry->registry('filtered_attributes');
            $filteredAttributes = $filteredAttrs ? $filteredAttrs : [];

            $params = array_keys($filteredAttributes);
            foreach ($layers as $key => $value) {
                $attCode = $layers[$key]['attribute_code'];
                $filteredCategoryIds = $this->registry->registry('filtered_category_ids');

                if(!in_array($attCode, $this->defaultAttributeList) && is_array($filteredCategoryIds) && !empty($filteredCategoryIds)){
                    if(!$this->getAllowFilterAttribute($attCode, $filteredCategoryIds)){
                        unset($layers[$attCode.'_bucket']);
                        continue;
                    }
                }
                $selOptionValues = [];
                $isEncoded = false;
                $layers[$key]['is_selected'] = false;
                $layers[$key]['condition_type'] = ($attCode == 'price') ? null : 'in';
                if (in_array($attCode, $params)) {
                    $filterAttrs = $filteredAttributes[$attCode];
                    if ($attCode == 'price') {
                        $from = explode(':', $filterAttrs['from']);
                        $to = explode(':', $filterAttrs['to']);
                        for ($i=0; $i<count($from); $i++) {
                            $selOptionValues[] = $from[$i]."_".$to[$i];
                        }
                    } else {
                        $selOptionValues = array_shift($filterAttrs);
                    }
                    $layers[$key]['is_selected'] = true;
                } elseif ($attCode == 'category_id' && in_array('category_uid', $params)) {
                    $isEncoded = true;
                    $filterAttrs = $filteredAttributes['category_uid'];
                    $selOptionValues = array_shift($filterAttrs);
                    $layers[$key]['is_selected'] = true;
                    $layers[$key]['condition_type'] = 'eq';
                }
                $optionsCnt = count($layers[$key]['options']);
                for ($i=0; $i<$optionsCnt; $i++) {
                    $layers[$key]['options'][$i]['is_selected'] = false;
                    
                    $attOptId = $layers[$key]['options'][$i]['value'];
                    if($attCode == 'courier_flag'){
                        if($layers[$key]['options'][$i]['label']){
                            $layers[$key]['options'][$i]['label'] = 'Yes';
                        }else{
                            $layers[$key]['options'][$i]['label'] = 'No';
                        }
                    }
                    if($attCode == 'category_id'){
                        $iboCateId = $this->getIboCategoryId($attOptId);
                        $layers[$key]['options'][$i]['category_code'] = $iboCateId;
                    }

                    if ($isEncoded) {
                        $attOptId = $this->uidEncoder->encode((string) $attOptId);
                    }
                    if ((is_array($selOptionValues)
                            && in_array($attOptId, $selOptionValues)) || ($attOptId == $selOptionValues)) {
                        $layers[$key]['options'][$i]['is_selected'] = true;
                    }
                }
                // Start Swatch code added for aggregations - vijay.gupta@embitel.com
                $isSwatch = $this->eavConfig->getAttribute('catalog_product', $layers[$key]['attribute_code']);
                if ($this->swatchHelper->isSwatchAttribute($isSwatch)) {
                    for ($i = 0; $i < count($layers[$key]['options']); $i++) {
                        $hashcodeData = $this->swatchHelper->getSwatchesByOptionsId([$layers[$key]['options'][$i]['value']]);
                        $typeName = $this->getswatchType($hashcodeData[$layers[$key]['options'][$i]['value']]['type']);

                        $swatchOptionData = [
                            'type' => $typeName,
                            'value' => $hashcodeData[$layers[$key]['options'][$i]['value']]['value']
                        ];

                        $layers[$key]['options'][$i]['swatch_data'] = $swatchOptionData;
                    }
                }
                // End Swatch code added for aggregations - vijay.gupta@embitel.com
            } 
        }
        //end
        return \array_filter($layers);
    }

    public function getAllowFilterAttribute($attributeCode, $filteredCategoryIds)
    {
       $allow = false;
       $attribute = $this->attributeRepository->get($attributeCode);
       $attributeCategories = $attribute->getAttributeCategoryIds();
       if(isset($attributeCategories)){
        foreach ($filteredCategoryIds as $categoryId) {
            if(in_array($categoryId, json_decode($attributeCategories))){
                $allow = true;
            }
        }
       }else{
                $allow = false;
       }
       return $allow;
   }

    public function getswatchType($valueType)
    {
        switch ($valueType) {
            case 0:
                return 'TextSwatchData';
            case 1:
                return 'ColorSwatchData';
            case 2:
                return 'ImageSwatchData';
                break;
        }
    }

    public function getIboCategoryId($magentoCategoryId)
    {
        $categoryId = ''; 
        $collection = $this->_categoryFactory
            ->create()
            ->addAttributeToSelect('category_id')
            ->addAttributeToFilter('entity_id',['eq'=>$magentoCategoryId])
            ->setPageSize(1);

        if(!empty($collection->getFirstItem()))
        {
            $catObj = $collection->getFirstItem();
            $catData = $catObj->getData();
            $categoryId = $catData['category_id'];
        }
        return $categoryId;
    }
}
