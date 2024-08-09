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

interface ReceiptInterface
{
	const POS_RECEIPT_ID = 'pos_receipt_id';
    const TITLE = 'title';
    const HEADERLOGO = 'heaer_logo';
	const WIDTH = 'width';
    const BARCODEWIDTH = 'barcode_width';
	const FONTSIZE  = 'font_size';
	const HEADERDETAILS = 'header_details';
    const FOOTERDETAILS = 'footer_details';

	public function getId();

    public function getTitle();
    
    public function getHeaderLogo();

	public function getWidth();

	public function getBarcodeWidth();

	public function getFontSize();

	public function getHeaderDetails();

	public function getFooterDetails();

	public function setId($id);

    public function setTitle($title);
    
    public function setHeaderLogo($headerLogo);

	public function setWidth($width);

	public function setBarcodeWidth($barcodeWidth);

    public function setFontSize($fontSize);
    
	public function setHeaderDetails($headerDetails);

	public function setFooterDetails($footerDeatils);

}