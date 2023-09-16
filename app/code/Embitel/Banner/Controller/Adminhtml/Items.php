<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Embitel\Banner\Controller\Adminhtml;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\SerializerInterface;
/**
 * Items controller
 */
abstract class Items extends \Magento\Backend\App\Action
{
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    /**
     * @var \Magento\Backend\Model\View\Result\ForwardFactory
     */
    protected $resultForwardFactory;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Embitel\Banner\Model\BannerFactory
     */
    protected $bannerFactory;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    protected $uploaderFactory;
    protected $adapterFactory;
    protected $filesystem;
    protected $csvParser;
    protected $backendSession;
    protected $logger;

    /**
     * Initialize Group Controller
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Embitel\Banner\Model\BannerFactory $bannerFactory,
        \Magento\Backend\Model\Session $backendSession,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\File\Csv $csvParser,
        DirectoryList $directoryList,
        UploaderFactory $uploaderFactory,
        AdapterFactory $adapterFactory,
        Filesystem $filesystem,
        SerializerInterface $serializer,
        \Magento\Framework\Filesystem\Driver\File $file
    ) {
        $this->_coreRegistry = $coreRegistry;
        parent::__construct($context);
        $this->resultForwardFactory = $resultForwardFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->directoryList = $directoryList;
        $this->uploaderFactory = $uploaderFactory;
        $this->adapterFactory = $adapterFactory;
        $this->filesystem = $filesystem;
        $this->_file = $file;
        $this->csvParser = $csvParser;
        $this->serializer = $serializer;
        $this->bannerFactory = $bannerFactory;
        $this->backendSession = $backendSession;
        $this->logger = $logger;
    }

    /**
     * Initiate action
     *
     * @return this
     */
    protected function _initAction()
    {
        $this->_view->loadLayout();
        $this->_setActiveMenu('Embitel_Banner::items')->_addBreadcrumb(__('Banner'), __('Banner'));
        return $this;
    }

    /**
     * Determine if authorized to perform group actions.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Embitel_Banner::items');
    }
}