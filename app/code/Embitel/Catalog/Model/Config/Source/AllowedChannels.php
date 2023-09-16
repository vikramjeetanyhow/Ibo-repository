<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\Catalog\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
/**
 * Handles the category tree.
 */
class AllowedChannels extends AbstractSource
{
    protected $optionFactory;
    public function getAllOptions()
    {
        $this->_options = [];
        $this->_options[] = ['label' => 'OMNI', 'value' => 'OMNI'];
        $this->_options[] = ['label' => 'STORE', 'value' => 'STORE'];
        $this->_options[] = ['label' => 'ONLINE', 'value' => 'ONLINE'];
    
        return $this->_options;
    }
}