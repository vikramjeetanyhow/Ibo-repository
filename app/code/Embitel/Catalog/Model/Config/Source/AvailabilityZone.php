<?php
/**
 *
 * @category  Magento
 * @package   Embitel\Catalog
 * @author    Hitendra Badiani <hitendra.badiani@embitel.com>
 * @copyright 2022 Embitel Technologies (I) Pvt Ltd
 */

namespace Embitel\Catalog\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Eav\Model\Entity\Attribute\Source\SourceInterface;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Handles the category tree.
 */
class AvailabilityZone extends AbstractSource implements SourceInterface, OptionSourceInterface
{
    protected $optionFactory;

    /**
     * Retrieve option array
     *
     * @return string[]
     * phpcs:disable Magento2.Functions.StaticFunction
     */
    public static function getOptionArray()
    {
    	return ['KARNATAKAZONE' => __('KARNATAKAZONE'), 'CHENAIZONE' => __('CHENAIZONE'), 'GUJARATZONE' => __('GUJARATZONE')];
    }

    public function getAllOptions()
    {
        $result = [];

        foreach (self::getOptionArray() as $index => $value) {
            $result[] = ['value' => $index, 'label' => $value];
        }

        return $result;
    }

    /**
     * Retrieve option text by option value
     *
     * @param string $optionId
     * @return string
     */
    public function getOptionText($optionId)
    {
        $options = self::getOptionArray();

        return $options[$optionId] ?? null;
    }
}