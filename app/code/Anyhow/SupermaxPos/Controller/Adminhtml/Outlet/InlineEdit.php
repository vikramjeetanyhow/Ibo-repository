<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Outlet;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Anyhow\SupermaxPos\Api\Data\OutletInterface;
use Anyhow\SupermaxPos\Api\OutletRepositoryInterface as OutletRepository;

class InlineEdit extends \Magento\Backend\App\Action
{
    protected $OutletRepository;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonFactory;

    public function __construct(
        Context $context,
        OutletRepository $OutletRepository,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
        $this->OutletRepository = $OutletRepository;
        $this->jsonFactory = $jsonFactory;
    }
	
	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::outlet_save');
	}

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];

        $postItems = $this->getRequest()->getParam('items', []);
        if (!($this->getRequest()->getParam('isAjax') && count($postItems))) {
            return $resultJson->setData([
                'messages' => [__('Please correct the data sent.')],
                'error' => true,
            ]);
        }

        foreach (array_keys($postItems) as $outletId) {
            $outlet = $this->OutletRepository->getById($outletId);
            try {
                $outletData = $postItems[$outletId];
                $extendedoutletData = $outlet->getData();
                $this->setOutletData($outlet, $extendedoutletData, $outletData);
                $this->OutletRepository->save($outlet);
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $messages[] = $this->getErrorWithOutletId($outlet, $e->getMessage());
                $error = true;
            } catch (\RuntimeException $e) {
                $messages[] = $this->getErrorWithOutletId($outlet, $e->getMessage());
                $error = true;
            } catch (\Exception $e) {
                $messages[] = $this->getErrorWithOutletId(
                    $outlet,
                    __('Something went wrong while saving the store data.')
                );
                $error = true;
            }
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error
        ]);
    }

    protected function getErrorWithOutletId(OutletInterface $outlet, $errorText)
    {
        return ('[Outlet ID: '. $outlet->getId(). '] '.$errorText);
    }

    public function setOutletData(\Anyhow\SupermaxPos\Model\SupermaxPosOutlet $outlet, array $extendedoutletrData, array $outletData)
    {
        $outlet->setData(array_merge($outlet->getData(), $extendedoutletData, $outletData));
        return $this;
    }
}
