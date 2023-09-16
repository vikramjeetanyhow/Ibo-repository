<?php

declare(strict_types=1);

namespace Embitel\GetInTouchGraphQl\Model;

use Embitel\GetInTouch\Model\ResourceModel\GetInTouchItem\CollectionFactory;
use Embitel\GetInTouch\Model\GetInTouchItemFactory;
use Magento\Framework\Model\AbstractModel;

class GetInTouchData extends AbstractModel
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var GetInTouchItemFactory
     */
    private $getintouchItemFactory;

    /**
     * @param CollectionFactory $collectionFactory
     * @param GetInTouchItemFactory $getintouchItemFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        GetInTouchItemFactory $getintouchItemFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->getintouchItemFactory = $getintouchItemFactory;
    }

    /**
     * @param type $args
     * @return array
     */
    public function getGetInTouchItem($args)
    {
        try {
            $item = $this->getintouchItemFactory->create();
            $item->setData($args);
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
