<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Embitel\CatalogGraphQl\Model\Resolver\DataProvider\EligiblePromotions;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Http\Context;

/**
 * CMS page field resolver, used for GraphQL request processing
 */
class EligiblePromotionsResolver implements ResolverInterface
{
    /**
     * @var EligibleDataProvider
     */
    private $eligibleDataProvider;

    /**
     * @var Configurable
     */
    private $configurable;

    private Context $httpContext;

    /**
     *
     * @param EligibleDataProvider $eligibleDataProvider
     * @param Configurable $configurable
     */
    public function __construct(
        EligiblePromotions $eligibleDataProvider,
        Configurable $configurable,
        Context $httpContext
    ) {
        $this->eligibleDataProvider = $eligibleDataProvider;
        $this->configurable = $configurable;
        $this->httpContext = $httpContext;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $customerId = null;
        $customerGroupId = $this->httpContext->getValue('ibo_customer_group_id');

        if ($context->getExtensionAttributes()->getIsCustomer()) {
            $customerId = $context->getUserId();
        }
        if (!array_key_exists('model', $value) || !$value['model'] instanceof ProductInterface) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /* @var $product ProductInterface */
        $product = $value['model'];
        $eligibleData = [];
        try {
            if ($product->getId() !== null) {
                //$eligibleData = $this->eligibleDataProvider->getDataByProductId($product->getId(), $customerId);
                $logFileName = BP . '/var/log/eligible_promotion.log';
                $writer = new \Zend\Log\Writer\Stream($logFileName);
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);

                if ($product->getTypeId() == "simple") {
                    $parentIds = $this->configurable->getParentIdsByChild($product->getId());
                    if (empty($parentIds)) {
                        $logger->info(__METHOD__);
                        $logger->info("Product ID:" . $product->getId());
                        $logger->info("from simple");
                        $eligibleData = $this->eligibleDataProvider->getDataByProductId($product->getId(), $customerId, $customerGroupId);
                    }
                } else {
                    $logger->info(__METHOD__);
                    $logger->info("Product ID:" . $product->getId());
                    $logger->info("from config");
                    $eligibleData = $this->eligibleDataProvider->getDataByProductId($product->getId(), $customerId, $customerGroupId);
                }
            }
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        }
        return $eligibleData;
    }
}
