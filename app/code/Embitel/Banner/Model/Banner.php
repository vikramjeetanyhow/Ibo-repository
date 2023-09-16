<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 */

namespace Embitel\Banner\Model;

use Magento\Framework\Model\AbstractModel;

class Banner extends AbstractModel
{
    /**
     * Define resource model
     */
    protected function _construct()
    {
        $this->_init('Embitel\Banner\Model\ResourceModel\Banner');
    }
}