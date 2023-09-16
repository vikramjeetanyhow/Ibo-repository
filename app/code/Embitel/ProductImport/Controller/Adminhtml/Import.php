<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\ProductImport\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;

abstract class Import extends Action
{

    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Embitel_ProductImport::import';

    /**
     * @var FileFactory
     */
    protected FileFactory $fileFactory;


    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        FileFactory $fileFactory
    )
    {
        parent::__construct($context);
    }
}
