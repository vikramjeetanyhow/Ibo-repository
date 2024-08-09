<?php
namespace Anyhow\SupermaxPos\Block\Adminhtml;

class BarcodePopup extends \Magento\Backend\Block\Template {
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\App\Request\Http $request,
        array $data = []
	) {
        parent::__construct($context, $data);
        $this->request = $request;
        $this->resource = $resourceConnection;
	}
    public function getAdminUrl()
    {
        return $this->getUrl('supermax/barcode/assign');
    }

    public function getAssignProductUrl()
    {
        return $this->getUrl('supermax/barcode/insertproduct');
    }

    public function outletId(){
        return $this->request->getParam('id');
    }
    /**
     * Block template
     *
     * @var string
     */
    protected $_template = 'barcodepopup.phtml';
}