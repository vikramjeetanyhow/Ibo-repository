<?php
/**
* @version [product Version 1.0.0] [Supported Magento Version 2.3.x.x]
* @category Anyhow Infosystems
* @package Magento Cxl Pos
* @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
* @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
* @license https://store.anyhowinfo.com/software-license
*/

namespace Anyhow\CxlPos\Api;

interface ProductInterface
{
    /**
     * Get products page-wise
     *
     * @param string $page_number
     * @param string $page_size
     * @return array
     */
    public function getproducts($page_number, $page_size);
}
