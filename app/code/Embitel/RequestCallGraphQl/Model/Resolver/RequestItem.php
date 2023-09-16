<?php

declare(strict_types=1);

namespace Embitel\RequestCallGraphQl\Model\Resolver;

use Embitel\RequestCallGraphQl\Model\RequestCallData;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * CMS page field resolver, used for GraphQL request processing
 */
class RequestItem implements ResolverInterface
{
    /**
     * @var RequestCallData
     */
    private $requestCallData;

    /**
     * @param RequestCallData $requestCallData
     */
    public function __construct(
        RequestCallData $requestCallData
    ) {
        $this->requestCallData = $requestCallData;
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
        if (!isset($args['mobile_number']) || $args['mobile_number'] == '') {
            return [
                'status' => "failure",
                "message" => "Please enter mobile number."
            ];
        }

        return $this->requestCallData->getRequestCallItem($args['mobile_number']);
    }
}
