<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Ibo\Emailer\Model\Data;

use Ibo\Emailer\Api\Data\EmailInterface;
use Magento\Framework\DataObject;

/**
 * Custom Option info .
 */
class Email extends DataObject implements EmailInterface
{   
    /**
     * {@inheritdoc}
     */
    public function getSenderEmail()
    {
        return $this->getData(self::SENDER_EMAIL);
    }

    /**
     * {@inheritdoc}
     */
    public function setSenderEmail($senderEmail)
    {
        return $this->setData(self::SENDER_EMAIL, $senderEmail);
    }

    /**
     * {@inheritdoc}
     */
    public function getSenderName()
    {
        return $this->getData(self::SENDER_NAME);
    }

    /**
     * {@inheritdoc}
     */
    public function setSenderName($senderName)
    {
        return $this->setData(self::SENDER_NAME, $senderName);
    }

    /**
     * {@inheritdoc}
     */
    public function getReceiverEmail()
    {
        return $this->getData(self::RECEIVER_EMAIL);
    }

    /**
     * {@inheritdoc}
     */
    public function setReceiverEmail($receiverEmail)
    {
        return $this->setData(self::RECEIVER_EMAIL, $receiverEmail);
    }

    /**
     * {@inheritdoc}
     */
    public function getReceiverName()
    {
        return $this->getData(self::RECEIVER_NAME);
    }

    /**
     * {@inheritdoc}
     */
    public function setReceiverName($receiverName)
    {
        return $this->setData(self::RECEIVER_NAME, $receiverName);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        return $this->getData(self::CONTENT);
    }

    /**
     * {@inheritdoc}
     */
    public function setContent($content)
    {
        return $this->setData(self::CONTENT, $content);
    }

    /**
     * {@inheritdoc}
     */
    public function getBcc()
    {
        return $this->getData(self::BCC);
    }

    /**
     * {@inheritdoc}
     */
    public function setBcc($bcc)
    {
        return $this->setData(self::BCC, $bcc);
    }

    /**
     * {@inheritdoc}
     */
    public function getCc()
    {
        return $this->getData(self::CC);
    }

    /**
     * {@inheritdoc}
     */
    public function setCc($cc)
    {
        return $this->setData(self::CC, $cc);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubject()
    {
        return $this->getData(self::SUBJECT);
    }

    /**
     * {@inheritdoc}
     */
    public function setSubject($subject)
    {
        return $this->setData(self::SUBJECT, $subject);
    }

    /**
     * {@inheritdoc}
     */
    public function getAttachmentUrl()
    {
        return $this->getData(self::ATTACHMENT_URL);
    }

    /**
     * {@inheritdoc}
     */
    public function setAttachmentUrl($attachmentUrl)
    {
        return $this->setData(self::ATTACHMENT_URL, $attachmentUrl);
    }
}
