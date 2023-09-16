<?php
namespace Embitel\ConfigurableProductGraphQl\Model\Resolver;

use Magento\Catalog\Api\Data\ProductInterface;
use Embitel\ConfigurableProductGraphQl\Model\Options\Metadata;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver class for option selection metadata.
 */
class EsinMetadata implements ResolverInterface
{
    /**
     * @var Metadata
     */
    private $configurableSelectionMetadata;

    /**
     * @param Metadata $configurableSelectionMetadata
     */
    public function __construct(
        Metadata $configurableSelectionMetadata
    ) {
        $this->configurableSelectionMetadata = $configurableSelectionMetadata;
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        if (!isset($args['esin'])) {
            throw new LocalizedException(__('"Esin/Isin" value should be specified')); 
        }
        /** @var ProductInterface $product */
        $product = $value['model'];

        return $this->configurableSelectionMetadata->getAvailableSelections($product, $args['esin']);
    }
}
