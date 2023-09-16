<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 */

namespace Embitel\Banner\Controller\Adminhtml\Items;

class NewAction extends \Embitel\Banner\Controller\Adminhtml\Items
{

    public function execute()
    {
        $this->_forward('edit');
    }
}
