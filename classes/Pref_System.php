<?php

class Pref_System extends Handler_Administrative {

	private const LOG_PAGE_LIMIT = 15;

	function csrf_ignore(string $method): bool {
		return $method === 'index';
	}

	function clearLog(): void {
		$this->pdo->query("DELETE FROM ttrss_error_log");
	}

	function sendTestEmail(): void {
		$mail_address = clean($_REQUEST["mail_address"]);

		$mailer = new Mailer();

		$rc = $mailer->mail(["to_name" => "",
			"to_address" => $mail_address,
			"subject" => __("Test message from tt-rss"),
			"message" => ("This message confirms that tt-rss can send outgoing email.")
			]);

		print json_encode(['rc' => $rc, 'error' => $mailer->error()]);
	}

	function getscheduledtasks(): void {
		?>
		<table width='100%' class='event-log'>
		<tr>
			<th><?= __("Task name") ?></th>
			<th><?= __("Schedule") ?></th>
			<th><?= __("Last executed") ?></th>
			<th><?= __("Duration (seconds)") ?></th>
			<th><?= __("Return code") ?></th>
		</tr>
		<?php

		$task_records = ORM::for_table('ttrss_scheduled_tasks')
			->order_by_asc(['last_cron_expression', 'task_name'])
			->find_many();

		foreach ($task_records as $task) {
			$row_style = $task->last_rc === 0 ? 'text-success' : 'text-error';

			?>
			<tr>
				<td class="<?= $row_style ?>"><?= $task->task_name ?></td>
				<td><?= $task->last_cron_expression ?></td>
				<td><?= TimeHelper::make_local_datetime($task->last_run) ?></td>
				<td><?= $task->last_duration ?></td>
				<td><?= $task->last_rc ?></td>
			</tr>
			<?php
		}

		?>
		</table>
		<?php
	}

	function getphpinfo(): void {
		ob_start();
		phpinfo();
		$info = ob_get_contents();
		ob_end_clean();

		print preg_replace( '%^.*<body>(.*)</body>.*$%ms','$1', (string)$info);
	}

	private function _log_viewer(int $page, int $severity): void {
		$errno_values = match ($severity) {
			E_USER_ERROR => [E_ERROR, E_USER_ERROR, E_PARSE, E_COMPILE_ERROR],
			E_USER_WARNING => [E_ERROR, E_USER_ERROR, E_PARSE, E_COMPILE_ERROR, E_WARNING, E_USER_WARNING, E_DEPRECATED, E_USER_DEPRECATED],
			default => [],
		};

		if (count($errno_values) > 0) {
			$errno_qmarks = arr_qmarks($errno_values);
			$errno_filter_qpart = "errno IN ($errno_qmarks)";
		} else {
			$errno_filter_qpart = "true";
		}

		$offset = self::LOG_PAGE_LIMIT * $page;

		$sth = $this->pdo->prepare("SELECT
				COUNT(id) AS total_pages
			FROM
				ttrss_error_log
			WHERE
				$errno_filter_qpart");

		$sth->execute($errno_values);

		if ($res = $sth->fetch()) {
			$total_pages = (int)($res["total_pages"] / self::LOG_PAGE_LIMIT);
		} else {
			$total_pages = 0;
		}

		?>
		<div dojoType='dijit.layout.BorderContainer' gutters='false'>
			<div region='top' dojoType='fox.Toolbar'>

				<button dojoType='dijit.form.Button' onclick='Helpers.EventLog.refresh()'>
					<?= __('Refresh') ?>
				</button>

				<button dojoType='dijit.form.Button' <?= ($page <= 0 ? "disabled" : "") ?>
					onclick='Helpers.EventLog.prevPage()'>
					<?= __('&lt;&lt;') ?>
				</button>

				<button dojoType='dijit.form.Button' disabled>
					<?= T_sprintf('Page %d of %d', $page+1, $total_pages+1) ?>
				</button>

				<button dojoType='dijit.form.Button' <?= ($page >= $total_pages ? "disabled" : "") ?>
					onclick='Helpers.EventLog.nextPage()'>
					<?= __('&gt;&gt;') ?>
				</button>

				<button dojoType='dijit.form.Button'
					onclick='Helpers.EventLog.clear()'>
					<?= __('Clear') ?>
				</button>

				<div class='pull-right'>
					<label><?= __("Severity:") ?></label>

					<?= \Controls\select_hash("severity", $severity,
						[
							E_USER_ERROR => __("Errors"),
							E_USER_WARNING => __("Warnings"),
							E_USER_NOTICE => __("Everything")
						], ["onchange"=> "Helpers.EventLog.refresh()"], "severity") ?>
				</div>
			</div>

			<div style="padding : 0px" dojoType="dijit.layout.ContentPane" region="center">

				<table width='100%' class='event-log'>

					<tr>
						<th width='5%'><?= __("Error") ?></th>
						<th><?= __("Filename") ?></th>
						<th><?= __("Message") ?></th>
						<th width='5%'><?= __("User") ?></th>
						<th width='5%'><?= __("Date") ?></th>
					</tr>

					<?php
					$sth = $this->pdo->prepare("SELECT
							errno, errstr, filename, lineno, created_at, login, context
						FROM
							ttrss_error_log LEFT JOIN ttrss_users ON (owner_uid = ttrss_users.id)
						WHERE
							$errno_filter_qpart
						ORDER BY
							ttrss_error_log.id DESC
						LIMIT ". self::LOG_PAGE_LIMIT ." OFFSET $offset");

					$sth->execute($errno_values);

					while ($line = $sth->fetch()) {
						foreach ($line as $k => $v) { $line[$k] = htmlspecialchars($v ?? ''); }
						?>
						<tr>
							<td class='errno'>
								<?= Logger::ERROR_NAMES[$line["errno"]] . " (" . $line["errno"] . ")" ?>
							</td>
							<td class='filename'><?= $line["filename"] . ":" . $line["lineno"] ?></td>
							<td class='errstr'><?= $line["errstr"] . "\n" .  $line["context"] ?></td>
							<td class='login'><?= $line["login"] ?></td>
							<td class='timestamp'>
								<?= TimeHelper::make_local_datetime($line['created_at']) ?>
							</td>
						</tr>
					<?php } ?>
				</table>
			</div>
		</div>
		<?php
	}

	function index(): void {

		$severity = (int) ($_REQUEST["severity"] ?? E_USER_WARNING);
		$page = (int) ($_REQUEST["page"] ?? 0);
		?>
		<div dojoType='dijit.layout.AccordionContainer' region='center'>
			<?php if (Config::get(Config::LOG_DESTINATION) == Logger::LOG_DEST_SQL) { ?>
				<div dojoType='dijit.layout.AccordionPane' style='padding : 0' title='<i class="material-icons">report</i> <?= __('Event log') ?>'>
					<?php
						$this->_log_viewer($page, $severity);
					?>
				</div>
			<?php } ?>
			<div dojoType='dijit.layout.AccordionPane' style='padding : 0' title='<i class="material-icons">mail</i> <?= __('Email configuration') ?>'>
				<div dojoType="dijit.layout.ContentPane">

					<form dojoType="dijit.form.Form">
						<script type="dojo/method" event="onSubmit" args="evt">
							evt.preventDefault();
							if (this.validate()) {
								xhr.json("backend.php", this.getValues(), (reply) => {
									const msg = document.getElementById("mail-test-result");

									if (reply.rc) {
										msg.innerHTML = <?= json_encode(__('Email sent.')) ?>;
										msg.className = 'alert alert-success';
									} else {
										msg.innerHTML = reply.error;
										msg.className = 'alert alert-danger';
									}

									msg.show();
								})
							}
						</script>

						<?= \Controls\hidden_tag("op", "Pref_System") ?>
						<?= \Controls\hidden_tag("method", "sendTestEmail") ?>

						<?php
							$user = ORM::for_table('ttrss_users')->find_one($_SESSION["uid"]);
						?>

						<fieldset>
							<label><?= __("To:") ?></label>
							<?= \Controls\input_tag("mail_address",$user->email, "text", ['required' => 1]) ?>
							<?= \Controls\submit_tag(__("Send test email")) ?>
							<span style="display: none; margin-left : 10px" class="alert alert-error" id="mail-test-result">...</span>
						</fieldset>
					</form>
				</div>
			</div>
			<div dojoType='dijit.layout.AccordionPane' title='<i class="material-icons">alarm</i> <?= __('Scheduled tasks') ?>'>
					<script type='dojo/method' event='onSelected' args='evt'>
						if (this.domNode.querySelector('.loading'))
							window.setTimeout(() => {
								xhr.post("backend.php", {op: 'Pref_System', method: 'getscheduledtasks'}, (reply) => {
									this.attr('content', `<div class='phpinfo'>${reply}</div>`);
								});
							}, 200);
					</script>
					<span class='loading'><?= __("Loading, please wait...") ?></span>
			</div>
			<div dojoType='dijit.layout.AccordionPane' title='<i class="material-icons">info</i> <?= __('PHP Information') ?>'>
					<script type='dojo/method' event='onSelected' args='evt'>
						if (this.domNode.querySelector('.loading'))
							window.setTimeout(() => {
								xhr.post("backend.php", {op: 'Pref_System', method: 'getphpinfo'}, (reply) => {
									this.attr('content', `<div class='phpinfo'>${reply}</div>`);
								});
							}, 200);
					</script>
					<span class='loading'><?= __("Loading, please wait...") ?></span>
			</div>

			<?php PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB, "prefSystem") ?>
		</div>
		<?php
	}
}
