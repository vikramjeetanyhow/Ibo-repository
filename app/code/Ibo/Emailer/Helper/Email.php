<?php
namespace Ibo\Emailer\Helper;
class Email extends \Magento\Framework\App\Helper\AbstractHelper
{
     
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Helper\Context $context
    ) {     
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    public function sendMail($emailInfo)
    {       
        $response = ['code' => "200"];
        try{
            $mail = new \Zend_Mail('utf-8');
            $mail->setFrom($emailInfo->getSenderEmail(), $emailInfo->getSenderName());
            $mail->setSubject($emailInfo->getSubject());
            if(!empty($emailInfo->getReceiverEmail())){
                $to = explode(',', $emailInfo->getReceiverEmail());
                $receiverName = !empty($emailInfo->getReceiverName()) ? $emailInfo->getReceiverName() : "";
                foreach($to as $key => $toMail){
                    $mail->addTo($toMail, $receiverName);
                }
            }
            if(!empty($emailInfo->getBcc())){
                $bcc = explode(',', $emailInfo->getBcc());
                foreach($bcc as $bccMail){
                    $mail->addBcc($bccMail);
                }
            }
            if(!empty($emailInfo->getCc())){
                $cc = explode(',', $emailInfo->getCc());
                foreach($cc as $ccMail){
                    $mail->addCc($ccMail);
                }
            }
            if(!empty($emailInfo->getContent())){
                $mail->setBodyHtml($emailInfo->getContent());
            }
            $attachmentUrl = $emailInfo->getAttachmentUrl();
            if(!empty($attachmentUrl)){
                $content = file_get_contents($attachmentUrl);
                $name = basename($attachmentUrl); // To get file name
                $ext = pathinfo($attachmentUrl, PATHINFO_EXTENSION); // To get extension
                $attachment = new \Zend_Mime_Part($content);
                $attachment->type = $ext; // attachment's mime type
                $attachment->disposition = \Zend_Mime::DISPOSITION_ATTACHMENT;
                $attachment->encoding = \Zend_Mime::ENCODING_BASE64;
                $attachment->filename = $name;
                $mail->addAttachment($attachment);
            }
            $mail->send();  
            $response['success'] = 'Mail Sent Successfully';
        } catch(Exception $e){
            $response['code'] = "400";
            $response['error'] = $e->getMessage();
        }
        return $response;
    }

    public function sendGridMail($emailInfo) {  
        $response = ['code' => "200"];
        try {
            $email = new \SendGrid\Mail\Mail(); 
            $email->setFrom($this->getSenderEmail(), $this->getSenderName());
            $email->setSubject($emailInfo->getSubject());

            if(!empty($emailInfo->getReceiverEmail())){
                $to = explode(',', $emailInfo->getReceiverEmail());
                $receiverName = !empty($emailInfo->getReceiverName()) ? $emailInfo->getReceiverName() : "";
                foreach($to as $key => $toMail){
                    $email->addTo($toMail, $receiverName);
                }
            }
            
            if(!empty($emailInfo->getBcc())){
                $bcc = explode(',', $emailInfo->getBcc());
                foreach($bcc as $bccMail){
                    $email->addBcc($bccMail);
                }
            }
            if(!empty($emailInfo->getCc())){
                $cc = explode(',', $emailInfo->getCc());
                foreach($cc as $ccMail){
                    $email->addCc($ccMail);
                }
            }

            if(!empty($emailInfo->getContent())) {
                $email->addContent("text/html", $emailInfo->getContent());
            }
            
            $attachmentUrl = $emailInfo->getAttachmentUrl();
            if(!empty($attachmentUrl)){
                $content = file_get_contents($attachmentUrl);
                $name = basename($attachmentUrl); // To get file name
                $ext = pathinfo($attachmentUrl, PATHINFO_EXTENSION); // To get extension
                $attachment = new \Zend_Mime_Part($content);
                $attachment->type = $ext; // attachment's mime type
                $attachment->disposition = \Zend_Mime::DISPOSITION_ATTACHMENT;
                $attachment->encoding = \Zend_Mime::ENCODING_BASE64;
                $attachment->filename = $name;
                $email->addAttachment($attachment);
            }

            $sendgrid = new \SendGrid($this->getApiKey());
            $sendgrid->send($email);
            $response['success'] = 'Mail Sent Successfully';
        } catch (Exception $e) {
            $response['code'] = "400";
            $response['error'] = $e->getMessage();
        }

        
        return $response;

    }
    
    private function getApiKey() {
       return $this->scopeConfig->getValue(
            "sendgrid/configuration/sendgrid_api_key",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            null
        );
    }

    public function getSenderName() {
        return $this->scopeConfig->getValue(
             "sendgrid/configuration/sender_name",
             \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
             null
         );
    }

    public function getSenderEmail() {
        return $this->scopeConfig->getValue(
            "sendgrid/configuration/sender_email",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            null
        );
    }

    public function getIsModuleEnable() {
        return (bool)$this->scopeConfig->getValue(
            "sendgrid/configuration/is_enable",
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            null
        );
    }
}