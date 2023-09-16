<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 */

namespace Embitel\Banner\Controller\Adminhtml\Items;

class InlineEdit extends \Magento\Backend\App\Action
{

    protected $jsonFactory;

    /**
     * @var \Embitel\Banner\Model\BannerFactory
     */
    protected $bannerFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Embitel\Banner\Model\BannerFactory $bannerFactory,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->bannerFactory = $bannerFactory;
    }

    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];

        if ($this->getRequest()->getParam('isAjax')) {
            $postItems = $this->getRequest()->getParam('items', []);
            if (!count($postItems)) {
                $messages[] = __('Please correct the data sent.');
                $error = true;
            } else {
                foreach (array_keys($postItems) as $modelid) {
                    /** @var \Magento\Cms\Model\Block $block */
                    $bannerModel = $this->bannerFactory;
                    $model = $bannerModel->create()->load($modelid);                     
                    try {
                        $model->setData(array_merge($model->getData(), $postItems[$modelid]));
                        $model->save();
                    } catch (\Exception $e) {
                        $messages[] = "[Mytesting ID: {$modelid}]  {$e->getMessage()}";
                        $error = true;
                    }
                }
            }
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error
        ]);
    }
}