<?php
namespace Ibo\Emailer\Api;
interface SendGridMailInterface
{
    /**
     * @api
     * @param \Ibo\Emailer\Api\Data\EmailInterface $email_info
     * @return array
     */
    public function send($email_info);
}
