<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Api\Data;

interface PriceReductionInterface
{
	const POS_PRICE_REDUCTION_ID = 'pos_price_reduction_id';
    const TITLE = 'title';
    const STATUS = 'status';
    const MAX_CAPACITY = 'max_capacity';

	public function getId();

    public function getTitle();

    public function getStatus();
    
    public function getMaxCapacity();


	public function setId($id);

    public function setTitle($title);

    public function setStatus($status);
    
    public function setMaxCapacity($max_capacity);

}