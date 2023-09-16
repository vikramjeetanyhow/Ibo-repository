<?php
 
namespace Ibo\DynamicBlockScript\Block;
 
use Magento\Framework\View\Element\Template;
use Magento\Backend\Block\Template\Context;
use Ibo\DynamicBlockScript\Model\ResourceModel\BlockScript\CollectionFactory;
 
class BlockScript extends Template
{
    public function __construct(CollectionFactory $blockCollection, Context $context, array $data = [])
    {
        $this->collection = $blockCollection;
        parent::__construct($context, $data);
    }

    public function getStaticScript()
    {
         $params = $this->_request->getParams();
         $identifier = $params['block_id'];
        $data = $this->collection->create()
                ->addFieldToFilter('identifier', $identifier)
                ->addFieldToSelect('script');
        $scriptData = $data->getFirstItem()->getData();
        $scriptdata = '';
        if (isset($scriptData['script'])) {
            $scriptdata = $scriptData['script'];
        }
        //print_r($scriptdata); exit;
        return $scriptdata;
    }
}
