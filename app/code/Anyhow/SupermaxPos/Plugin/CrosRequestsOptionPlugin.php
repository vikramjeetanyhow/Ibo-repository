<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Plugin;

use Magento\Framework\Webapi\Request;
use Magento\Framework\Exception\InputException;

class CrosRequestsOptionPlugin
{

    /**
     * @param Request $subject
     * @return void
     * @throws InputException
     */
    public function aroundGetHttpMethod(
        Request $subject
    ) {
        if (!$subject->isGet() && !$subject->isPost() && !$subject->isPut() && !$subject->isDelete() && !$subject->isOptions()) {
            throw new InputException(__('Request method is invalid.'));
        }
        return $subject->getMethod();
    }

}