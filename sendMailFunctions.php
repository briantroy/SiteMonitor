<?php

require_once "Mail.php";
require_once('Mail/mime.php');

function sendMail($to, $subj, $msgHtml, $msgTxt) {
        $from = "admin@brianandkelly.ws";
        $to = $to;
        $subject = $subj;
        
        
        $message = new Mail_mime();

        $message->setTXTBody($msgTxt);
        $message->setHTMLBody($msgHtml);

        $body = $message->get();
        
        $host = "ssl://smtp.gmail.com";
        $port = "465";
        $username = "admin@brianandkelly.ws";
        $password = "M@ggie01";

        $headers = array ('From' => $from,
          'To' => $to,
          'Subject' => $subject);
        $headers = $message->headers($headers);
        $smtp = Mail::factory('smtp',
          array ('host' => $host,
            'port' => $port,
            'auth' => true,
            'username' => $username,
            'password' => $password));

        $mail = $smtp->send($to, $headers, $body);

        if (PEAR::isError($mail)) {
          echo("<p>" . $mail->getMessage() . "</p>");
         } else {
          echo("<p>Message successfully sent!</p>");
         }
}
?>
