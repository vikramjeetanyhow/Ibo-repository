<?php
namespace Embitel\QuoteGraphQl\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Cart\ExtractQuoteAddressData;
use Magento\QuoteGraphQl\Model\Cart\ValidateAddressFromSchema;

/**
 * @inheritdoc
 */
class ShippingAddresses implements ResolverInterface
{
    /**
     * @var ExtractQuoteAddressData
     */
    private $extractQuoteAddressData;

    /**
     * @var ValidateAddressFromSchema
     */
    private $validateAddressFromSchema;

    /**
     * @param ExtractQuoteAddressData $extractQuoteAddressData
     * @param ValidateAddressFromSchema $validateAddressFromSchema
     */
    public function __construct(
        ExtractQuoteAddressData $extractQuoteAddressData,
        ValidateAddressFromSchema $validateAddressFromSchema
    ) {
        $this->extractQuoteAddressData = $extractQuoteAddressData;
        $this->validateAddressFromSchema = $validateAddressFromSchema;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /** @var Quote $cart */
        $cart = $value['model'];

        $addressesData = [];
        $shippingAddresses = $cart->getAllShippingAddresses();

        if (count($shippingAddresses)) {
            foreach ($shippingAddresses as $shippingAddress) {
                $address = $this->extractQuoteAddressData->execute($shippingAddress);
                /** Added temporary fix here - issue is when lastname is null shipping_address object in fetch cart is returned null after validation
                */
                if($address['lastname'] == '' || is_null($address['lastname'])) {
                    $address['lastname'] = ' ';
                }
                if ($this->validateAddressFromSchema->execute($address)) {
                    /* temporary code added till concrete solution is found */
                    if ($address['lastname'] = ' ') {
                        $address['lastname'] = '';
                    }
                    $addressesData[] = $address;
                }
            }
        }
        return $addressesData;
    }
}