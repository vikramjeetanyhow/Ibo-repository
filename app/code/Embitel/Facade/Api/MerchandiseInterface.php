<?php

namespace Embitel\Facade\Api;


interface MerchandiseInterface {

    /**
     * GET for Post api Data product 
     * @param string[] $offer_ids
     * @return array
     */
    public function getMerchandiseProductsSku($offer_ids);
}