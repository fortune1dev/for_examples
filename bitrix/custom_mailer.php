<?
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function custom_mail($to, $subject, $message, $additionalHeaders = '') {
	global $userSmtp, $passSmtp;

	$sender     = '.....';
	$senderName = '.......';

	$usernameSmtp = $userSmtp;
	$passwordSmtp = $passSmtp;

	$host = 'email-smtp.eu-central-1.amazonaws.com';
	$port = 587;

	$bodyText = strip_tags($message);
	$bodyHtml = $message;
	$mail     = new PHPMailer(true);

	try {
		$mail->isSMTP();
		$mail->setFrom($sender, $senderName);
		$mail->Username   = $usernameSmtp;
		$mail->Password   = $passwordSmtp;
		$mail->Host       = $host;
		$mail->Port       = $port;
		$mail->SMTPAuth   = true;
		$mail->SMTPSecure = 'tls';
		$mail->addCustomHeader('X-SES-CONFIGURATION-SET', $configurationSet);

		$to        = str_replace(' ', '', $to);
		$recipient = explode(',', $to);
		foreach ($recipient as $addr)
			$mail->addAddress($addr);

		$headers      = explode("\n", $additionalHeaders);
		$attachHeader = 'Content-Type: multipart/mixed; boundary='; foreach ($headers as $h) {
			if (stripos($h, $attachHeader) === 0) {
				$bndr              = substr($h, strlen($attachHeader));
				$bndr              = trim($bndr, '"');
				$mail->ContentType = 'multipart/mixed; boundary="' . $bndr . '"';
			}
		}

		$mail->isHTML(true);
		$mail->CharSet = 'UTF-8';
		$mail->Subject = $subject;
		$mail->Body    = $bodyHtml;
		$mail->AltBody = $bodyText;

		$bRet = $mail->Send();

		$mail->ClearAddresses();
		$mail->ClearAttachments();

		return $bRet;

	} catch (phpmailerException $e) {
		AddMessage2Log("An error occurred. {" . $e->errorMessage() . "}" . PHP_EOL); //Catch errors from PHPMailer.
	} catch (Exception $e) {
		AddMessage2Log("Email not sent. {" . $mail->ErrorInfo . "}" . PHP_EOL); //Catch errors from Amazon SES.
	}
}
?>