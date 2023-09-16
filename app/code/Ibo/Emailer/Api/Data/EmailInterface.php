<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ibo\Emailer\Api\Data;

/**
 * Order interface.
 *
 * An order is a document that a web store issues to a customer. Magento generates a sales order that lists the product
 * items, billing and shipping addresses, and shipping and payment methods. A corresponding external document, known as
 * a purchase order, is emailed to the customer.
 * @api
 * @since 100.0.2
 */
interface EmailInterface
{
    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */
    /*
     * Sender Email.
     */
    const SENDER_EMAIL = 'email';
    /*
     * Sender Name.
     */
    const SENDER_NAME = 'name';
    /*
     * Receiver Email.
     */
    const RECEIVER_EMAIL = 'receiver_email';
    /*
     * Receiver Name.
     */
    const RECEIVER_NAME = 'receiver_name';

    /*
     * Content
     */
    const CONTENT = 'content';

    /*
     * Subject Line.
     */
    const SUBJECT = 'subject';
    /*
     * Bcc.
     */
    const BCC = 'bcc';

    /*
     * cc.
     */
    const CC = 'cc';

    /*
     * attachement url.
     */
    const ATTACHMENT_URL = 'attachment_url';
 
    /**
     * Sender Email Id
     *
     * @return string|null
     */
    public function getSenderEmail();

    /**
     * Sender Email Name
     *
     * @return string|null
     */
    public function getSenderName();

    /**
     * Receiver Email Id
     *
     * @return string|null
     */
    public function getReceiverEmail();

    /**
     * Receiver Name
     *
     * @return string|null
     */
    public function getReceiverName();

    /**
     * BCC Info
     *
     * @return mixed
     */
    public function getBcc();

    /**
     * CC Info
     *
     * @return mixed
     */
    public function getCc();

    /**
     * Subject
     *
     * @return string|null
     */
    public function getSubject();

    /**
     * Content
     *
     * @return string|null
     */
    public function getContent();

    /**
     * Attachment Urrl
     *
     * @return string|null
     */
    public function getAttachmentUrl();


    /**
     * Set Sender Email Id
     *
     * @param string $senderId
     * @return $this
     */
    public function setSenderEmail($senderId);

    /**
     * Sets the Sender Name
     *
     * @param string $senderName
     * @return $this
     */
    public function setSenderName($senderName);

    /**
     * Sets the Receiver Email
     *
     * @param string $receiverEmail
     * @return $this
     */
    public function setReceiverEmail($receiverEmail);

    /**
     * Sets the Receiver Name
     *
     * @param string $receiverName
     * @return $this
     */
    public function setReceiverName($receiverName);

    /**
     * Sets the Bcc
     *
     * @param string $bcc
     * @return $this
     */
    public function setBcc($bcc);

    /**
     * Sets the Cc
     *
     * @param string $cc
     * @return $this
     */
    public function setCc($cc);

    /**
     * Sets the Subject
     *
     * @param string $subject
     * @return $this
     */
    public function setSubject($subject);

    /**
     * Sets the html content
     *
     * @param string $emailContent
     * @return $this
     */
    public function setContent($emailContent);

    /**
     * Sets the Attachment Urrl
     *
     * @param string $attachmentUrl
     * @return $this
     */
    public function setAttachmentUrl($attachmentUrl);
}
