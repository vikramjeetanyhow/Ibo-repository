<?php

namespace Embitel\SalesRule\Model\Promo\Quote;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\Filter\Date;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\SalesRule\Model\Rule;

/**
 * Class Save
 */
class Save extends \Magento\SalesRule\Controller\Adminhtml\Promo\Quote\Save
{
    private TimezoneInterface $timezone;
    private ?DataPersistorInterface $dataPersistor;

    public function __construct(Context $context, Registry $coreRegistry, FileFactory $fileFactory, Date $dateFilter, TimezoneInterface $timezone, DataPersistorInterface $dataPersistor = null)
    {
        parent::__construct($context, $coreRegistry, $fileFactory, $dateFilter, $timezone, $dataPersistor);
        $this->timezone = $timezone;
        $this->dataPersistor = $dataPersistor;
    }

    public function execute()
    {
        $data = $this->getRequest()->getPostValue();

        $data['coupon_use_in'] = implode(',', $data['coupon_use_in']);

        if ($data) {
            try {
                /** @var $model Rule */
                $model = $this->_objectManager->create(Rule::class);
                $this->_eventManager->dispatch(
                    'adminhtml_controller_salesrule_prepare_save',
                    ['request' => $this->getRequest()]
                );
                if (empty($data['from_date'])) {
                    $data['from_date'] = $this->timezone->formatDate();
                }

                $filterValues = ['from_date' => $this->_dateFilter];
                if ($this->getRequest()->getParam('to_date')) {
                    $filterValues['to_date'] = $this->_dateFilter;
                }
                $inputFilter = new \Zend_Filter_Input(
                    $filterValues,
                    [],
                    $data
                );
                $data = $inputFilter->getUnescaped();
                if (!$this->checkRuleExists($model)) {
                    throw new \Magento\Framework\Exception\LocalizedException(__('The wrong rule is specified.'));
                }

                $session = $this->_objectManager->get(\Magento\Backend\Model\Session::class);

                $validateResult = $model->validateData(new \Magento\Framework\DataObject($data));
                if ($validateResult !== true) {
                    foreach ($validateResult as $errorMessage) {
                        $this->messageManager->addErrorMessage($errorMessage);
                    }
                    $session->setPageData($data);
                    $this->dataPersistor->set('sale_rule', $data);
                    $this->_redirect('sales_rule/*/edit', ['id' => $model->getId()]);
                    return;
                }

                if (isset(
                        $data['simple_action']
                    ) && $data['simple_action'] == 'by_percent' && isset(
                        $data['discount_amount']
                    )
                ) {
                    $data['discount_amount'] = min(100, $data['discount_amount']);
                }
                if (isset($data['rule']['conditions'])) {
                    $data['conditions'] = $data['rule']['conditions'];
                }
                if (isset($data['rule']['actions'])) {
                    $data['actions'] = $data['rule']['actions'];
                }
                unset($data['rule']);
                $model->loadPost($data);

                $useAutoGeneration = (int)(
                    !empty($data['use_auto_generation']) && $data['use_auto_generation'] !== 'false'
                );
                $model->setUseAutoGeneration($useAutoGeneration);

                $session->setPageData($model->getData());

                $model->save();
                $this->messageManager->addSuccessMessage(__('You saved the rule.'));
                $session->setPageData(false);
                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('sales_rule/*/edit', ['id' => $model->getId()]);
                    return;
                }
                $this->_redirect('sales_rule/*/');
                return;
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                $id = (int)$this->getRequest()->getParam('rule_id');
                if (!empty($id)) {
                    $this->_redirect('sales_rule/*/edit', ['id' => $id]);
                } else {
                    $this->_redirect('sales_rule/*/new');
                }
                return;
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(
                    __('Something went wrong while saving the rule data. Please review the error log.')
                );
                $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->critical($e);
                $this->_objectManager->get(\Magento\Backend\Model\Session::class)->setPageData($data);
                $this->_redirect('sales_rule/*/edit', ['id' => $this->getRequest()->getParam('rule_id')]);
                return;
            }
        }
        $this->_redirect('sales_rule/*/');
    }

    /**
     * Check if Cart Price Rule with provided id exists.
     *
     * @param \Magento\SalesRule\Model\Rule $model
     * @return bool
     */
    private function checkRuleExists(\Magento\SalesRule\Model\Rule $model): bool
    {
        $id = $this->getRequest()->getParam('rule_id');
        if ($id) {
            $model->load($id);
            if ($model->getId() != $id) {
                return false;
            }
        }
        return true;
    }
}
