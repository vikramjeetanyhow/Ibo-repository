<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Api;

interface RegisterRepositoryInterface
{
	public function save(\Anyhow\SupermaxPos\Api\Data\RegisterInterface $receiptId);

    public function getById($registerId);

    public function delete(\Anyhow\SupermaxPos\Api\Data\RegisterInterface $receiptId);

    public function deleteById($registerId);
}