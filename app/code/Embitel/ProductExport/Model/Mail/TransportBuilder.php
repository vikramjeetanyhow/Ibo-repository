<?php

namespace Embitel\ProductExport\Model\Mail;

use Laminas\Mime\Mime;
use Laminas\Mime\PartFactory;
use Magento\Framework\Mail\AddressConverter;
use Magento\Framework\Mail\EmailMessageInterfaceFactory;
use Magento\Framework\Mail\MessageInterface;
use Magento\Framework\Mail\MessageInterfaceFactory;
use Magento\Framework\Mail\MimeMessageInterfaceFactory;
use Magento\Framework\Mail\MimePartInterfaceFactory;
use Magento\Framework\Mail\Template\FactoryInterface;
use Magento\Framework\Mail\Template\SenderResolverInterface;
use Magento\Framework\Mail\TransportInterfaceFactory;
use Magento\Framework\ObjectManagerInterface;

class TransportBuilder extends \Magento\Framework\Mail\Template\TransportBuilder
{
    private SenderResolverInterface $senderResolver;
    private ?MessageInterfaceFactory $messageFactory;
    private ?EmailMessageInterfaceFactory $emailMessageInterfaceFactory;
    private ?MimeMessageInterfaceFactory $mimeMessageInterfaceFactory;
    private ?MimePartInterfaceFactory $mimePartInterfaceFactory;
    private ?AddressConverter $addressConverter;
    private ?PartFactory $partFactory;
    private array $addAttachment;

    /**
     * @param FactoryInterface $templateFactory
     * @param MessageInterface $message
     * @param SenderResolverInterface $senderResolver
     * @param ObjectManagerInterface $objectManager
     * @param TransportInterfaceFactory $mailTransportFactory
     * @param MessageInterfaceFactory|null $messageFactory
     * @param EmailMessageInterfaceFactory|null $emailMessageInterfaceFactory
     * @param MimeMessageInterfaceFactory|null $mimeMessageInterfaceFactory
     * @param MimePartInterfaceFactory|null $mimePartInterfaceFactory
     * @param AddressConverter|null $addressConverter
     * @param PartFactory|null $partFactory
     */
    public function __construct(FactoryInterface $templateFactory, MessageInterface $message, SenderResolverInterface $senderResolver, ObjectManagerInterface $objectManager, TransportInterfaceFactory $mailTransportFactory, MessageInterfaceFactory $messageFactory = null, EmailMessageInterfaceFactory $emailMessageInterfaceFactory = null, MimeMessageInterfaceFactory $mimeMessageInterfaceFactory = null, MimePartInterfaceFactory $mimePartInterfaceFactory = null, AddressConverter $addressConverter = null, PartFactory $partFactory = null)
    {
        parent::__construct($templateFactory, $message, $senderResolver, $objectManager, $mailTransportFactory, $messageFactory, $emailMessageInterfaceFactory, $mimeMessageInterfaceFactory, $mimePartInterfaceFactory, $addressConverter);
        $this->templateFactory = $templateFactory;
        $this->message = $message;
        $this->senderResolver = $senderResolver;
        $this->objectManager = $objectManager;
        $this->mailTransportFactory = $mailTransportFactory;
        $this->messageFactory = $messageFactory;
        $this->emailMessageInterfaceFactory = $emailMessageInterfaceFactory;
        $this->mimeMessageInterfaceFactory = $mimeMessageInterfaceFactory;
        $this->mimePartInterfaceFactory = $mimePartInterfaceFactory;
        $this->addressConverter = $addressConverter;
        $this->partFactory = $partFactory;
    }


    public function addAttachment(?string $content, ?string $filename, ?string $fileType) {

        $attachment = $this->partFactory->create();
        $attachment->setContent($content)
            ->setType($fileType)
            ->setDisposition(Mime::DISPOSITION_ATTACHMENT)
            ->setEncoding(Mime::ENCODING_BASE64);
        $this->addAttachment[] = $attachment;

        $this;
    }

}