<?php

namespace Ibo\RegionalPricing\Model\Config\Backend\Serialized;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value as ConfigValue;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;

class ArraySerialized extends ConfigValue
{
    protected $serializer;
    public function __construct(
        SerializerInterface $serializer,
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->serializer = $serializer;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }
    public function beforeSave()
    {
        $regions = $this->getValue();
        unset($regions['__empty']);
        $encodedValue = $this->serializer->serialize($regions);
        $this->setValue($encodedValue);
    }
    protected function _afterLoad()
    {
        $regions = $this->getValue();
        if ($regions) {
            if (!is_array($regions)) {
                $decodedValue = $this->serializer->unserialize($regions);
            }
            $this->setValue($decodedValue);
        }
    }
}
