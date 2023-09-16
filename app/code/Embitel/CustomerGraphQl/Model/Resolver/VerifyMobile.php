<?php

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Embitel\CustomerGraphQl\Model\Customer\ValidateMobile;

class VerifyMobile implements ResolverInterface
{

    /**
     * @var \Embitel\CustomerGraphQl\Model\Customer\ValidateMobile
     */
    protected $validateMobile; 

    /**
     *
     * @param ValidateMobile $validateMobile     
     */
    public function __construct(
        ValidateMobile $validateMobile
    ) {
        $this->validateMobile = $validateMobile;        
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
        /* check if mobile number is passed in graphql and validate the same */
        if (!isset($args['mobilenumber'])) {
            throw new GraphQlInputException(__('Mobile number value should be specified'));
        }

        if (isset($args['mobilenumber']) && !preg_match("/^([0]|\+91)?[6789]\d{9}$/", $args['mobilenumber'])) {
            throw new GraphQlInputException(__('Mobile number value is not valid'));
        }

        if (isset($args['mobilenumber']) && $this->validateMobile->isMobileExist($args['mobilenumber'])) {
            return [                
                'msg' => __('It seems your mobile no is not registered with us !'),
                'is_mobile_match' => $this->validateMobile->isMobileExist($args['mobilenumber'])
            ];
        }else{
            return [                
                'msg' => __('It seems your mobile no is registered with us !'),
                'is_mobile_match' => $this->validateMobile->isMobileExist($args['mobilenumber'])
            ];
        }        
    }
}
