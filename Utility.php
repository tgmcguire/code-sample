<?php

/*
	This is a utility class that handles sending email, either using SMTP via
	Swiftmailer, or the Mailgun API. This handles both cases in the event that the server
	running the app blocks SMTP traffic for some reason, as I discovered was the case on
	my temporary host.
*/

namespace APP\Util;

use RPC\View;
use Mailgun\Mailgun;

class Mail {
	public static function send($to, $subject, $replacements, $template_file="general", $options=null) {
		if (!$template_file) {
			$template_file = "general";
		}

		// Assemble message data
		$fromEmail = 'xxxxx@xxxxx.com';
		$fromName = 'xxxxx';

		$options['reply_to'] = "xxxxx@xxxxx.com";
		$options['reply_to_name'] = "xxxxx";

		if ($options['reply_to']) {
			$replyToEmail = $options['reply_to'];
			$replyToName = ($options['reply_to_name']) ?: $options['reply_to'];
		}

		if ($template_file == "plain") {
			$body = $replacements;

			$isPlain = true;
		} else {
			// initialize blank controller for message view
			$template = new \APP\View(APP_PATH.'/View', new \RPC\View\Cache(CACHE_PATH.'/view'));

			if (!is_array($replacements)) {
				$replacements = array(
					"title" => $subject,
					"content" => $replacements
				);
			}

			foreach ($replacements as $key => $value) {
				$template->$key = $value;
			}

			// Generate HTML from view
			ob_start();
			$template->display('email/'.$template_file.".php");
			$body = ob_get_contents();
			ob_end_clean();

			$isPlain = false;
		}

		// Send either using Mailgun directly or Swiftmailer if there's no Mailgun credentials
		if (getenv('MAILGUN_KEY')) {
			$mailgun = Mailgun::create(getenv('MAILGUN_KEY'));

			$message = [
				'from'    => $fromName." <".$fromEmail.">",
				'to'      => $to,
				'subject' => $subject,
			];

			if ($replyToEmail) {
				$message['h:Reply-To'] = $replyToName." <".$replyToEmail.">";
			}

			if ($isPlain) {
				$message['text'] = $body;
			} else {
				$message['html'] = $body;
			}

			$send = $mailgun->messages()->send(getenv('MAILGUN_DOMAIN'), $message);
		} else {
			// set up transport
			$transport = new \Swift_SmtpTransport(getenv('SMTP_HOST'), getenv('SMTP_PORT'));
			$transport->setUsername(getenv('SMTP_USERNAME'));
			$transport->setPassword(getenv('SMTP_PASSWORD'));

			$mailer = new \Swift_Mailer($transport);

			// set up message
			$message = new \Swift_Message();

			$message->setTo($to);
			$message->setFrom([$fromEmail => $fromName]);
			$message->setSubject($subject);

			if ($replyToEmail) {
				$message->setReplyTo($replyToEmail, $replyToName);
			}
			
			$message->setBody($body, ($isPlain) ? 'text/plain' : 'text/html');

			// send
			$send = $mailer->send($message);
		}

		return $send;
	}
}