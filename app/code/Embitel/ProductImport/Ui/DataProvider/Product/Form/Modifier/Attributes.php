<?php
namespace Embitel\ProductImport\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\Stdlib\ArrayManager;

class Attributes extends AbstractModifier
{
    private $arrayManager;

    public function __construct(ArrayManager $arrayManager)
    {
        $this->arrayManager = $arrayManager;
    }

    public function modifyData(array $data)
    {
        return $data;
    }

    public function modifyMeta(array $meta)
    {
        $attribute = 'lot_control_parameters'; // Your attribute code goes here

        $path = $this->arrayManager->findPath($attribute, $meta, null, 'children');
        $meta = $this->arrayManager->set(
            "{$path}/arguments/data/config/disabled",
            $meta,
            true
        );
        return $meta;
    }
}