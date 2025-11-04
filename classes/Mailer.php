<?php
class Mailer {
	private string $last_error = "";

	/**
	 * @param array{to_name?: string, to_address: string, subject: string, message: string, from_name?: string, from_address?: string, headers?: array<string, mixed>} $params
	 * @return bool|int bool if the default mail function handled the request, otherwise an int as described in Mailer#mail()
	 */
	function mail(array $params): bool|int {

		$to_name = $params["to_name"] ?? "";
		$to_address = $params["to_address"];
		$subject = $params["subject"];
		$message = $params["message"];
		// $message_html = $params["message_html"] ?? "";
		$from_name = $params["from_name"] ?? Config::get(Config::SMTP_FROM_NAME);
		$from_address = $params["from_address"] ?? Config::get(Config::SMTP_FROM_ADDRESS);
		$additional_headers = $params["headers"] ?? [];

		$from_combined = $from_name ? "$from_name <$from_address>" : $from_address;
		$to_combined = $to_name ? "$to_name <$to_address>" : $to_address;

		if (Config::get(Config::LOG_SENT_MAIL))
			Logger::log(E_USER_NOTICE, "Sending email from $from_combined to $to_combined [$subject]: $message");

		// HOOK_SEND_MAIL plugin instructions:
		// 1. return 1 or true if mail is handled
		// 2. return -1 if there's been a fatal error and no further action is allowed
		// 3. any other return value will allow cycling to the next handler and, eventually, to default mail() function
		// 4. set error message if needed via passed Mailer instance function set_error()

		$hooks_tried = 0;

		foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_SEND_MAIL) as $p) {
			$rc = $p->hook_send_mail($this, $params);

			if ($rc == 1)
				return $rc;

			if ($rc == -1)
				return 0;

			++$hooks_tried;
		}

		$rc = mail($to_combined, $subject, $message, implode("\r\n", [
			"From: $from_combined",
			'Content-Type: text/plain; charset=UTF-8',
			...$additional_headers,
		]));

		if (!$rc) {
			$this->set_error(error_get_last()['message'] ?? T_sprintf("Unknown error while sending email. Hooks tried: %d.", $hooks_tried));
		}

		return $rc;
	}

	function set_error(string $message): void {
		$this->last_error = $message;
		user_error("Error sending email: $message", E_USER_WARNING);
	}

	function error(): string {
		return $this->last_error;
	}
}
