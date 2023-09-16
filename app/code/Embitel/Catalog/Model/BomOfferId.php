<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\Catalog\Model;

use Magento\Catalog\Model\ProductFactory;
use Embitel\Catalog\Api\BomOfferIdInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles the BOM offer ids.
 */
class BomOfferId implements BomOfferIdInterface
{
    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @param ProductFactory $productFactory
     */
    public function __construct(
        ProductFactory $productFactory,
        LoggerInterface $logger
    ) {
        $this->productFactory = $productFactory;
        $this->logger = $logger;
    }

    /**
     * Retrieve list of BOM offer ids
     *
     * @param mixed $bom_skus
     * @return string
     */
    public function getList($bom_skus)
    {
        $bomOfferIds["bom"]["bom_skus_metadata"] = array();

        if (!empty($bom_skus)) {
            foreach ($bom_skus as $bom) {
                if (isset($bom["bom_offer_id"]) && !empty($bom["bom_offer_id"])) {
                    $product = $this->productFactory->create()->loadByAttribute('sku', $bom["bom_offer_id"]);
                    if ($product) {
                        $secondaryOfferIds = "";
                        if (!empty($product->getSecondaryOfferId())) {
                            $secondaryOfferIds = json_decode($product->getSecondaryOfferId());
                        }

                        $bomOfferIds["bom"]["bom_skus_metadata"][] = [
                            "bom_offer_id"          => $bom["bom_offer_id"],
                            "base_offer_id"         => $product->getBaseOfferId(),
                            "secondary_offer_ids"   => $secondaryOfferIds,
                            "inventory_basis"       => $product->getAttributeText('inventory_basis')
                        ];
                    }
                }
            }
        }

        echo json_encode($bomOfferIds["bom"], true);
    }
}
