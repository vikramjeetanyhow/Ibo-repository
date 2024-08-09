<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Register;

use Magento\Framework\App\Filesystem\DirectoryList;

class Cashiereconcile extends \Magento\Backend\App\Action
{
    protected $uploaderFactory;
    protected $scopeConfig;

    protected $_locationFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        // \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxRegister\Collection $registerData,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\CurrencyInterface $currency,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUserCollection
        // \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone

    ) {
        parent::__construct($context);
        $this->_fileFactory = $fileFactory;
        // $this->scopeConfig = $scopeConfig;
        $this->_registerData = $registerData;
        $this->_filesystem = $filesystem;
        $this->_directory = $this->_filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->currency = $currency;
        $this->resource = $resourceConnection;
        $this->supermaxUserCollection = $supermaxUserCollection;
        // $this->timezone = $timezone;

    }

    public function execute()
    {
        $posRegisterId = $this->getRequest()->getParam('pos_register_id');
        $connection = $this->resource->getConnection();
        $supermaxRegisterTransTable = $this->resource->getTableName('ah_supermax_pos_register_transaction');
        $supermaxRegisterTransDetailTable = $this->resource->getTableName('ah_supermax_pos_register_transaction_detail');
        $supermaxRegister = $this->resource->getTableName('ah_supermax_pos_register');
        $userTable = $this->resource->getTableName('ah_supermax_pos_user');
        $storeTable = $this->resource->getTableName('ah_supermax_pos_outlet');

        $title = "Opening Float";
        $totalExpected = $totalHead = $totalDifference = 0.00;
        $cashExpected = $cardExpected = $customExpected = $offlineExpected = $floatamount = $walletExpected =  $emiExpected = $bankDepositExpected = $payLaterExpected = $partialBallanceExpected =  0.00;
        $cashHead = $cardHead = $customHead = $offlineHead = $walletHead = $emiHead = $bankDepositHead = $payLaterHead = $partialBallanceHead = 0.00;
        $CashierId = $noteHead = $openDate = $closeDate = $registerName = $cashierName = $outletId = $storeName = $reconcile_status = '';
        $cashDifference = $cardDifference = $customDifference = $offlineDifference = $walletDifference = $emiDifference = $bankDepositDifference = $payLaterDifference = $partialBallanceDifference =  0.00;
        $getHeadCashier = $connection->query("SELECT *  FROM $supermaxRegister WHERE pos_register_id = '" . $posRegisterId . "'");
        foreach ($getHeadCashier as $getHeadCashier) {
            $cashHead = (float) $getHeadCashier['head_cashier_cash_total'];
            $cardHead = (float) $getHeadCashier['head_cashier_card_total'];
            $customHead = (float) $getHeadCashier['head_cashier_custom_total'];
            $offlineHead = (float) $getHeadCashier['head_cashier_offline_total'];
            $walletHead = (float) $getHeadCashier['head_cashier_wallet_total'];
            $emiHead = (float) $getHeadCashier['head_cashier_emi_total'];
            $bankDepositHead = (float) $getHeadCashier['head_cashier_bank_deposit_total'];
            $payLaterHead = (float) $getHeadCashier['head_cashier_pay_later_total'];
            $partialBallanceHead = (float) $getHeadCashier['head_cashier_partial_balance_total'];
            $CashierId = $getHeadCashier['pos_user_id'];
            $noteHead = $getHeadCashier['head_cashier_close_note'];
            $openDate = $getHeadCashier['date_open'];
            // $this->timezone->date(new \DateTime(
            //     $getHeadCashier['date_open']))->format('Y-m-d h:i:s A');
            $closeDate = $getHeadCashier['date_close'];
            // $this->timezone->date(new \DateTime(
            //     $getHeadCashier['date_close']))->format('Y-m-d h:i:s A');
            $registerName = $getHeadCashier['name'];
            $reconcile_status = $getHeadCashier['reconciliation_status'];
        }

        $getUsername = $connection->query("SELECT * FROM $userTable WHERE pos_user_id = '" . $CashierId . "' ");
        foreach ($getUsername as $Username) {
            $cashierName = $Username['firstname'] . ' ' . $Username['lastname'];
            $outletId = $Username['pos_outlet_id'];
        }

        $getStorename = $connection->query("SELECT * FROM $storeTable WHERE pos_outlet_id = '" . $outletId . "' ");
        foreach ($getStorename as $Storename) {
            $storeName = $Storename['outlet_name'];
        }
        //float amount
        $getFloatAmount = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "'And title = '" . $title . "' ");
        foreach ($getFloatAmount as $FloatAmount) {
            $floatamount = (float) $FloatAmount['expected_total'];
        }
        //total expected
        // $getTotalTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' And title != '" . $title . "' ");
        // foreach ($getTotalTotalExpected as $getTotalExpected) {
        //     $totalExpected = (float) $getTotalExpected['expected_total'];
        // }
        //cash expected
        $getCashTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'cash' And title != '" . $title . "' ");
        foreach ($getCashTotalExpected as $getCashExpected) {
            $cashExpected = round((float) $getCashExpected['expected_total'], 2);
        }
        //card expected
        $getCardTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'card' ");
        foreach ($getCardTotalExpected as $getCardExpected) {
            $cardExpected = round((float) $getCardExpected['expected_total'], 2);
        }
        //custom expected
        $getCustomTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'upi' ");
        foreach ($getCustomTotalExpected as $getCustomExpected) {
            $customExpected = round((float) $getCustomExpected['expected_total'], 2);
        }
        //offline expected
        $getOfflineTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'offline' ");
        foreach ($getOfflineTotalExpected as $getOfflineExpected) {
            $offlineExpected = round((float) $getOfflineExpected['expected_total'], 2);
        }
        //wallet expected
        $getCashTotalExpected = $connection->query("SELECT SUM(amount) as total_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'wallet' ");
        foreach ($getCashTotalExpected as $getCashExpected) {
            $walletExpected = round((float) $getCashExpected['total_total'], 2);
        }

        //emi expected
        $getemiTotalExpected = $connection->query("SELECT SUM(amount) as total_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'emi' ");
        foreach ($getemiTotalExpected as $getemiExpected) {
            $emiExpected = round((float) $getemiExpected['total_total'], 2);
        }

        //bankDeposit expected
        $getbankDepositTotalExpected = $connection->query("SELECT SUM(amount) as total_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'bank_deposit' ");
        foreach ($getbankDepositTotalExpected as $getbankDepositExpected) {
            $bankDepositExpected = round((float) $getbankDepositExpected['total_total'], 2);
        }

        //pay_later expected
        $getpayLaterTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'pay_later' ");
        foreach ($getpayLaterTotalExpected as $getpayLaterExpected) {
            $payLaterExpected = round((float) $getpayLaterExpected['expected_total'], 2);
        }

         //partial_ballance expected
         $getPartialTotalExpected = $connection->query("SELECT SUM(amount) as expected_total FROM $supermaxRegisterTransDetailTable WHERE pos_register_id = '" . $posRegisterId . "' AND code = 'partial_payment_balance' ");
         foreach ($getPartialTotalExpected as $getPartialExpected) {
            $partialBallanceExpected = round((float) $getPartialExpected['expected_total'], 2);
         }

        $totalHead = round((float) ($cashHead + $cardHead + $customHead + $walletHead + $offlineHead + $emiHead + $bankDepositHead + $payLaterHead + $partialBallanceHead), 2);
        $totalExpected = round((float) ($cashExpected + $cardExpected + $customExpected + $walletExpected + $offlineExpected + $emiExpected + $bankDepositExpected + $payLaterExpected + $partialBallanceExpected), 2);

        $totalDifference = round((float) ($totalExpected - $totalHead), 2);
        $cashDifference = round((float) ($cashExpected - $cashHead), 2);
        $cardDifference = round((float) ($cardExpected - $cardHead), 2);
        $customDifference = round((float) ($customExpected - $customHead), 2);
        $walletDifference = round((float) ($walletExpected - $walletHead), 2);
        $offlineDifference = round((float) ($offlineExpected - $offlineHead), 2);
        $emiDifference = round((float) ($emiExpected - $emiHead), 2);
        $bankDepositDifference = round((float) ($bankDepositExpected - $bankDepositHead), 2);
        $payLaterDifference = round((float) ($payLaterExpected - $payLaterHead), 2);
        $partialBallanceDifference = round((float) ($partialBallanceExpected - $partialBallanceHead), 2);

        $name = date('d-m-Y');
        // $filepath = 'export/register-import-' . $name . '.doc';

        $imgPath = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
        $img_name = 'IBO-logo.png';
        $image_url = $imgPath . $img_name;

        $pdf = new \Zend_Pdf();
        $pdf->pages[] = $pdf->newPage(\Zend_Pdf_Page::SIZE_A4);
        $page = $pdf->pages[0]; // this will get reference to the first page.
        $style = new \Zend_Pdf_Style();
        $style->setLineColor(new \Zend_Pdf_Color_Rgb(0, 0, 0));
        $font = \Zend_Pdf_Font::fontWithName(\Zend_Pdf_Font::FONT_TIMES);
        $width = $page->getWidth();
        $hight = $page->getHeight();
        $x = 30;
        $pageTopalign = 850;
        $this->y = 850 - 100;
        $style->setFont($font, 15);
        $page->setStyle($style);

        $pdfImage = new \Zend_Pdf_Resource_Image_Png($image_url);

        // $page->drawImage($pdfImage, $x + 400, $this->y + 40, $page->getWidth() - 100, $this->y + 20);
        $page->drawImage($pdfImage, $x + 400, $this->y +20,$page->getWidth() - 100,$this->y + 50);

        $page->drawText(__("Cashier Declaration"), $x + 195, $this->y + 10, 'UTF-8');
        $page->drawRectangle(40, $this->y + 2, $page->getWidth() - 40, $this->y + 2, \Zend_Pdf_Page::SHAPE_DRAW_STROKE);

        $style->setFont($font, 12);
        $page->setStyle($style);
        $page->drawText(__("Cashier Name : %1", $cashierName), $x + 45, $this->y - 30, 'UTF-8');
        $page->drawText(__("Date  : %1", $name), $x + 340, $this->y - 30, 'UTF-8');

        $page->drawText(__("Cashier ID : %1", $CashierId), $x + 45, $this->y - 50, 'UTF-8');
        $page->drawText(__("Store  : %1", $storeName), $x + 340, $this->y - 50, 'UTF-8');

        $page->drawText(__("Opened At : %1", $openDate), $x + 45, $this->y - 75, 'UTF-8');
        $page->drawText(__("Close At:  : %1", $closeDate), $x + 340, $this->y - 75, 'UTF-8');

        $page->drawText(__("Register Name : %1", $registerName), $x + 45, $this->y - 100, 'UTF-8');
        $page->drawText(__("Reconciliation Status  : Done"), $x + 340, $this->y - 100, 'UTF-8');

        $page->drawText(__("Opening Float : %1", $floatamount), $x + 45, $this->y - 150, 'UTF-8');
        $page->drawText(__("Declaration: "), $x + 45, $this->y - 180, 'UTF-8');

        $page->drawText(__("Payment Type"), $x + 45, $this->y - 210, 'UTF-8');
        $page->drawText(__("Cashier Expected"), $x + 155, $this->y - 210, 'UTF-8');
        $page->drawText(__("Head cashier input "), $x + 280, $this->y - 210, 'UTF-8');
        $page->drawText(__("Difference "), $x + 390, $this->y - 210, 'UTF-8');

        $page->drawText(__("Cash "), $x + 45, $this->y - 230, 'UTF-8');
        $page->drawText(__("%1", round($cashExpected, 2)), $x + 155, $this->y - 230, 'UTF-8');
        $page->drawText(__("%1", round($cashHead, 2)), $x + 280, $this->y - 230, 'UTF-8');
        $page->drawText(__("%1", round($cashDifference, 2)), $x + 390, $this->y - 230, 'UTF-8');

        $page->drawText(__("Card "), $x + 45, $this->y - 250, 'UTF-8');
        $page->drawText(__("%1", round($cardExpected, 2)), $x + 155, $this->y - 250, 'UTF-8');
        $page->drawText(__("%1", round($cardHead, 2)), $x + 280, $this->y - 250, 'UTF-8');
        $page->drawText(__("%1", round($cardDifference, 2)), $x + 390, $this->y - 250, 'UTF-8');

        $page->drawText(__("UPI/QR "), $x + 45, $this->y - 270, 'UTF-8');
        $page->drawText(__("%1", round($customExpected, 2)), $x + 155, $this->y - 270, 'UTF-8');
        $page->drawText(__("%1", round($customHead, 2)), $x + 280, $this->y - 270, 'UTF-8');
        $page->drawText(__("%1", round($customDifference, 2)), $x + 390, $this->y - 270, 'UTF-8');

        $page->drawText(__("Offline"), $x + 45, $this->y - 290, 'UTF-8');
        $page->drawText(__("%1", round($offlineExpected, 2)), $x + 155, $this->y - 290, 'UTF-8');
        $page->drawText(__("%1", round($offlineHead, )), $x + 280, $this->y - 290, 'UTF-8');
        $page->drawText(__("%1", round($offlineDifference, 2)), $x + 390, $this->y - 290, 'UTF-8');

        $page->drawText(__("Wallet"), $x + 45, $this->y - 310, 'UTF-8');
        $page->drawText(__("%1", round($walletExpected, 2)), $x + 155, $this->y - 310, 'UTF-8');
        $page->drawText(__("%1", round($walletHead, 2)), $x + 280, $this->y - 310, 'UTF-8');
        $page->drawText(__("%1", round($walletDifference, 2)), $x + 390, $this->y - 310, 'UTF-8');

        $page->drawText(__("EMI"), $x + 45, $this->y - 330, 'UTF-8');
        $page->drawText(__("%1", round($emiExpected, 2)), $x + 155, $this->y - 330, 'UTF-8');
        $page->drawText(__("%1", round($emiHead,2)), $x + 280, $this->y - 330, 'UTF-8');
        $page->drawText(__("%1", round($emiDifference, 2)), $x + 390, $this->y - 330, 'UTF-8');

        $page->drawText(__("Bank Deposit"), $x + 45, $this->y - 350, 'UTF-8');
        $page->drawText(__("%1", round($bankDepositExpected, 2)), $x + 155, $this->y - 350, 'UTF-8');
        $page->drawText(__("%1", round($bankDepositHead, 2)), $x + 280, $this->y - 350, 'UTF-8');
        $page->drawText(__("%1", round($bankDepositDifference, 2)), $x + 390, $this->y - 350, 'UTF-8');

        $page->drawText(__("Pay Later"), $x + 45, $this->y - 370, 'UTF-8');
        $page->drawText(__("%1", round($payLaterExpected, 2)), $x + 155, $this->y - 370, 'UTF-8');
        $page->drawText(__("%1", round($payLaterHead, 2)), $x + 280, $this->y - 370, 'UTF-8');
        $page->drawText(__("%1", round($payLaterDifference, 2)), $x + 390, $this->y - 370, 'UTF-8');

        $page->drawText(__("Pay On Invoice"), $x + 45, $this->y - 390, 'UTF-8');
        $page->drawText(__("%1", round($partialBallanceExpected, 2)), $x + 155, $this->y - 390, 'UTF-8');
        $page->drawText(__("%1", round($partialBallanceHead, 2)), $x + 280, $this->y - 390, 'UTF-8');
        $page->drawText(__("%1", round($partialBallanceDifference, 2)), $x + 390, $this->y - 390, 'UTF-8');

        $page->drawText(__("Total"), $x + 45, $this->y - 410, 'UTF-8');
        $page->drawText(__("%1", round($totalExpected, 2)), $x + 155, $this->y - 410, 'UTF-8');
        $page->drawText(__("%1", round($totalHead, 2)), $x + 280, $this->y - 410, 'UTF-8');
        $page->drawText(__("%1", round($totalDifference, 2)), $x + 390, $this->y - 410, 'UTF-8');

        $page->drawText(__("Cashier Signature"), $x + 45, $this->y - 520, 'UTF-8');
        $page->drawText(__(" Head Cashier Signature "), $x + 390, $this->y - 520, 'UTF-8');

        $page->drawRectangle(40, $this->y - 192, $page->getWidth() - 40, $this->y - 430, \Zend_Pdf_Page::SHAPE_DRAW_STROKE);

        $fileName = 'cashier-declearation' . $name . '.pdf';

        $this->_fileFactory->create(
            $fileName,
            $pdf->render(),
            \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR, // this pdf will be saved in var directory with the name meetanshi.pdf
            'application/pdf'
        );
    }
}
