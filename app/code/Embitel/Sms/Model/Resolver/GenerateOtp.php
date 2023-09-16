<?php

namespace Embitel\Sms\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlAlreadyExistsException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Embitel\Sms\Model\Customer\OtpGenerator;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;

/**
 * Generate Otp for customer resolver
 */
class GenerateOtp implements ResolverInterface
{

    /**
     * @var \Embitel\Sms\Model\Customer\OtpGenerator
     */
    protected $otpGenerator;

    /**
     *
     * @param OtpGenerator $otpGenerator     
     */
    public function __construct(
        OtpGenerator $otpGenerator        
    ) {
        $this->otpGenerator = $otpGenerator;        
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
        
        if (!isset($args['otpfor'])) {
            throw new GraphQlInputException(__('Something went wrong, Please try again'));
        }
        $data = $this->otpGenerator->generateOtpAndSendSms($args['mobilenumber'], $args['resend'], $args['otpfor']);
        return ['success' => $data['success'], 'msg' => $data['msg'],'can_resend_after' => $data['can_resend_after']];
    }
}
