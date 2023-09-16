<?php
namespace Ibo\Emailer\Model\Service;
use Ibo\Emailer\Api\SendMailInterface;
use Ibo\Emailer\Helper\Email;
class SendEmailService implements SendMailInterface
{
    private $emailHelper;

    public function __construct(
        Email $emailHelper
    ) {
        $this->emailHelper = $emailHelper; 
    }

    public function send($email_info)
    {
        $response = [];
        $senderEmail = $email_info->getSenderEmail();
        $senderName = $email_info->getSenderName();
        $receiverEmail = $email_info->getReceiverEmail();
        $receiverName = $email_info->getReceiverName();
        $content = $email_info->getContent();
        $bcc = $email_info->getBcc();
        $cc = $email_info->getCc();
        $subject = $email_info->getSubject();
        $attachmentUrl = $email_info->getAttachmentUrl();
        $validateSenderEmail = $this->isValidMail($senderEmail);
        $validateReceiverEmail = $this->isValidMail($receiverEmail);
        $validateBccEmail = $this->isValidMail($bcc);
        $validateCcEmail = $this->isValidMail($cc);
        if(empty($senderEmail) 
            || empty($senderName) || empty($receiverEmail) 
            || empty($receiverName) || empty($subject) || empty($content)){
                $response[] = [
                    "code" => "400",
                    "error" => "Require mandatory fields(sender_email,sender_name,receiver_email,subject,content) to process the data."
                ];
        }

        if(empty($response) 
            && (empty($validateSenderEmail) || empty($validateReceiverEmail) || empty($validateBccEmail) || empty($validateCcEmail))){
                $response[] = [
                    "code" => "400",
                    "error" => "Invalid Mail id found for one of the field(sender_email,receiver_email,cc,bcc) to process the data."
                ];
        }

        if(empty($response)){
            $response = $this->emailHelper->sendMail($email_info);
        }

        return $response;
    }

    private function isValidMail($mail){
        $validateMail = true;
        if(!empty($mail)){
            $mailArray = explode(',',$mail);
            foreach($mailArray as $value){
                $validateMail = filter_var($value, FILTER_VALIDATE_EMAIL);
                if($validateMail == false){
                    break;
                }
            }
        }
        return $validateMail;
    }
}