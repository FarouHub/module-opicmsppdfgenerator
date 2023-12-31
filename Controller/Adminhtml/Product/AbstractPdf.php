<?php

namespace Eadesigndev\PdfGeneratorPro\Controller\Adminhtml\Product;

use Eadesigndev\PdfGeneratorPro\Api\TemplatesRepositoryInterface;
use Eadesigndev\PdfGeneratorPro\Helper\Variable\Processors\ProductOutput;
use Eadesigndev\PdfGeneratorPro\Model\Pdfgenerator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Email\Model\Template\Config;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject\Factory as DataObject;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Registry;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;

/**
 * Class AbstractPdf
 *
 * @package Eadesigndev\PdfGeneratorPro\Controller\Adminhtml\Order
 * @SuppressWarnings(CouplingBetweenObjects)
 */
abstract class AbstractPdf extends Action
{
    /**
     * @var Registry
     */
    private $coreRegistry;

    /**
     * @var Config
     */
    private $emailConfig;

    /**
     * @var JsonFactory
     */
    public $resultJsonFactory;

    /**
     * @var DateTime
     */
    public $dateTime;

    /**
     * @var Output
     */
    public $helper;

    /**
     * @var FileFactory
     */
    public $fileFactory;

    /**
     * @var ForwardFactory
     */
    public $resultForwardFactory;

    /**
     * @var ExtensibleDataObjectConverter
     */
    public $extensibleDataObjectConverter;

    /**
     * @var int
     */
    public $templateId;

    /**
     * @var Pdfgenerator
     */
    public $templateModel;

    /**
     * @var int
     */
    public $sourceId;

    /**
     * @var object
     */
    public $sourceModel;

    /**
     * @var DataObject
     */
    private $dataObject;

    /**
     * @var TemplatesRepositoryInterface
     */
    private $templatesRepositoryInterface;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * AbstractPdf constructor.
     * @param Context $context
     * @param Config $emailConfig
     * @param JsonFactory $resultJsonFactory
     * @param ProductOutput $helper
     * @param DateTime $_dateTime
     * @param Registry $coreRegistry
     * @param FileFactory $fileFactory
     * @param ForwardFactory $resultForwardFactory
     * @param ExtensibleDataObjectConverter $extensibleDataObjectConverter
     * @param DataObject $dataObject
     * @param TemplatesRepositoryInterface $templatesRepositoryInterface
     * @param CustomerRepository $customerRepository
     * @SuppressWarnings(ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Config $emailConfig,
        JsonFactory $resultJsonFactory,
        ProductOutput $helper,
        DateTime $_dateTime,
        Registry $coreRegistry,
        FileFactory $fileFactory,
        ForwardFactory $resultForwardFactory,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        DataObject $dataObject,
        TemplatesRepositoryInterface $templatesRepositoryInterface,
        CustomerRepository $customerRepository
    ) {
        $this->fileFactory = $fileFactory;
        $this->helper = $helper;
        $this->extensibleDataObjectConverter = $extensibleDataObjectConverter;
        parent::__construct($context);
        $this->resultForwardFactory = $resultForwardFactory;
        $this->dateTime = $_dateTime;
        $this->dataObject = $dataObject;
        $this->templatesRepositoryInterface = $templatesRepositoryInterface;
        $this->customerRepository = $customerRepository;
        $this->emailConfig = $emailConfig;
        $this->coreRegistry = $coreRegistry;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * @return Forward
     */
    public function noRoute()
    {
        return $this->resultForwardFactory
            ->create()
            ->forward('noroute');
    }

    /**
     * @param $customerId
     * @return \Magento\Framework\DataObject
     */
    public function customer($customerId)
    {
        $customer = $this->customerRepository
            ->getById($customerId);

        $customerData = $this->extensibleDataObjectConverter->toFlatArray(
            $customer,
            [],
            '\Magento\Customer\Api\Data\CustomerInterface'
        );

        $pseudoCustomer = $this->dataObject->create($customerData);

        return $pseudoCustomer;
    }

    /**
     * @return $this
     */
    public function templateModel()
    {
        $templateId = $this->templateId;

        if (!$this->templateId) {
            $this->noRoute();
        }

        $templateModel = $this->templatesRepositoryInterface
            ->getById($templateId);

        if (!$templateModel) {
            $this->noRoute();
        }

        $this->templateModel = $templateModel;
        return $this->templateModel;
    }

    /**
     * @param $entity
     * @return object
     */
    public function sourceModel($entity)
    {
        $sourceId = $this->sourceId;

        if (!$sourceId) {
            $this->noRoute();
        }

        //@codingStandardsIgnoreLine
        $source = $this->_objectManager->create($entity)
            ->getById($sourceId);

        if (!$source) {
            $this->noRoute();
        }

        $this->sourceModel = $source;

        return $this->sourceModel;
    }

    /**
     * @return $this
     */
    public function processTemplate()
    {
        $helper = $this->helper;

        $helper->setSource($this->sourceModel);
        $helper->setTemplate($this->templateModel);

        if ($customerId = $this->sourceModel->getCustomerId()) {
            $pseudoCustomer = $this->customer($customerId);
            $helper->setCustomer($pseudoCustomer);
        }

        return $this;
    }

    /**
     * @param $fileName
     * @param $output
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function fileGenerator($fileName, $output)
    {
        $file = $this->fileFactory->create(
            $fileName,
            $output,
            DirectoryList::VAR_DIR,
            'application/pdf'
        );

        return $file;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function returnFile()
    {
        $helper = $this->helper;

        $this->processTemplate();
        $pdfFileData = $helper->template2Pdf();
        $output = $helper->PDFmerger();

        $fileName = $pdfFileData['filename'] . '.pdf';

        $file = $this->fileGenerator($fileName, $output);
        return $file;
    }
}
