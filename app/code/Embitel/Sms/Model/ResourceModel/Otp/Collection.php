<?php

namespace Embitel\Sms\Model\ResourceModel\Otp;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    /**
     * Name of collection model
     */
    const EMBITEL_SMS_MODEL_NAME = \Embitel\Sms\Model\Otp::class;
    
    /**
     * @var string
     */
    protected $_modelName;

    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null,
        $modelName = self::EMBITEL_SMS_MODEL_NAME
    ) {
        $this->_modelName = $modelName;
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
    }

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init($this->_modelName, \Embitel\Sms\Model\ResourceModel\Otp::class);
    }
}
