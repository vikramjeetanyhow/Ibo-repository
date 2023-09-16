<?php

declare(strict_types=1);

namespace Embitel\GetInTouchGraphQl\Model\Resolver;

use Embitel\GetInTouchGraphQl\Model\GetInTouchData;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * CMS page field resolver, used for GraphQL request processing
 */
class GetInTouchItem implements ResolverInterface
{
    /**
     * @var GetInTouchData
     */
    private $getInTouchData;

    /**
     * @param GetInTouchData $getInTouchData
     */
    public function __construct(
        GetInTouchData $getInTouchData
    ) {
        $this->getInTouchData = $getInTouchData;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!isset($args['customer_name']) || $args['customer_name'] == ''
                || !isset($args['email']) || $args['email'] == ''
                || !isset($args['contactus_message']) || $args['contactus_message'] == '') {
            return [
                'status' => "failure",
                "message" => "Please enter required data."
            ];
        }

        return $this->getInTouchData->getGetInTouchItem($args);
    }
}
