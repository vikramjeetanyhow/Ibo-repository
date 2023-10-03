<?php

namespace Embitel\Facade\Api;


interface MerchandiseInterface {

    /**
     * GET for Post api Data product 
     * @param string[] $sku
     * @return array
     */
    public function getMerchandiseProductsSku($sku);
}