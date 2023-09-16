<?php

declare(strict_types=1);

namespace Embitel\RequestCallGraphQl\Model;

use Embitel\RequestCall\Model\ResourceModel\RequestItem\CollectionFactory;
use Embitel\RequestCall\Model\RequestItemFactory;
use Magento\Framework\Model\AbstractModel;

class RequestCallData extends AbstractModel
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var RequestItemFactory
     */
    private $requestItemFactory;

    /**
     * @param CollectionFactory $collectionFactory
     * @param RequestItemFactory $requestItemFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        RequestItemFactory $requestItemFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->requestItemFactory = $requestItemFactory;
    }

    /**
     * Check if mobile number is already exist in the table then update request count
     * If mobile number don't exist then add new record.
     *
     * @param type $mobileNumber
     * @return array
     */
    public function getRequestCallItem($mobileNumber)
    {
        $items = $this->collectionFactory->create()
                ->addFieldToFilter("mobile_number", $mobileNumber);
        $count = 1;
        
        try {
            $item = $this->requestItemFactory->create();
            if ($items->getSize() > 0) {
                $itemExist = $items->getFirstItem();
                $count = $itemExist->getNoOfRequest() + 1;
                $item->load($itemExist->getId());
            }
            $item->setMobileNumber($mobileNumber);
            $item->setNoOfRequest($count);
            $item->save();
        } catch (\Exception $ex) {
            return [
                "status" => "failure",
                "message" => "There is some error: " . $ex->getMessage()
            ];
        }

        return [
            "status" => "success",
            "message" => "Your request has been placed successfully."
        ];
    }
}
