<?php
class Scheduler {
	private static ?Scheduler $instance = null;

	const TASK_RC_EXCEPTION = -100;
	const DEFAULT_NAME = 'Default Scheduler';

	/** @var array<string, mixed> */
	private array $scheduled_tasks = [];

	private string $name;

	public function __construct(string $name = self::DEFAULT_NAME) {
		$this->set_name($name);

		if ($name === self::DEFAULT_NAME) {
			$this->add_scheduled_task('purge_orphaned_scheduled_tasks', '@weekly',
				$this->purge_orphaned_tasks(...)
			);
		}
	}

	public static function getInstance(): Scheduler {
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	/** Sets specific identifier for this instance of Scheduler used in debug logging */
	public function set_name(string $name) : void {
		$this->name = $name;
	}

	/**
	 * Adds a backend scheduled task which will be executed by updater (if due) during housekeeping.
	 *
	 * The granularity is not strictly guaranteed, housekeeping is invoked several times per hour
	 * depending on how fast feed batch was processed, but no more than once per minute.
	 *
	 * Tasks do not run in user context. Task names may not overlap. Plugins should register tasks
	 * via PluginHost methods (to be implemented later).
	 *
	 * Tasks should return an integer value (return code) which is stored in the database, a value of
	 * 0 is considered successful.
	 *
	 * @param string $task_name unique name for this task, plugins should prefix this with plugin name
	 * @param string $cron_expression schedule for this task in cron format
	 * @param Closure $callback task code that gets executed
	*/
	public function add_scheduled_task(string $task_name, string $cron_expression, Closure $callback) : bool {
		$task_name = strtolower($task_name);

		if (isset($this->scheduled_tasks[$task_name])) {
			user_error("[$this->name] Attempted to override already registered scheduled task $task_name", E_USER_WARNING);
			return false;
		} else {
			try {
				$cron = new Cron\CronExpression($cron_expression);
			} catch (InvalidArgumentException $e) {
				user_error("[$this->name] Attempt to register scheduled task $task_name failed: " . $e->getMessage(), E_USER_WARNING);
				return false;
			}

			$this->scheduled_tasks[$task_name] = [
				"cron" => $cron,
				"callback" => $callback,
			];
			return true;
		}
	}

	/**
	 * Execute scheduled tasks which are due to run and record last run timestamps.
	 */
	public function run_due_tasks() : void {
		Debug::log("[$this->name] Processing all scheduled tasks...");

		$tasks_succeeded = 0;
		$tasks_failed = 0;

		foreach ($this->scheduled_tasks as $task_name => $task) {
			$task_record = ORM::for_table('ttrss_scheduled_tasks')
				->where('task_name', $task_name)
				->find_one();

			if ($task_record)
				$last_run = $task_record->last_run;
			else
				$last_run = '1970-01-01 00:00';

			// because we don't schedule tasks every minute, we assume that task is due if its
			// next estimated run based on previous timestamp is in the past
			if ($task['cron']->getNextRunDate($last_run)->getTimestamp() - time() < 0) {
				Debug::log("=> Scheduled task $task_name is due, executing...");

				$task_started = time();

				try {
					$rc = (int) $task['callback']();
				} catch (Exception $e) {
					user_error("[$this->name] Scheduled task $task_name failed with exception: " . $e->getMessage(), E_USER_WARNING);

					$rc = self::TASK_RC_EXCEPTION;
				}

				$task_duration = time() - $task_started;

				if ($rc === 0) {
					++$tasks_succeeded;
					Debug::log("<= Scheduled task $task_name has finished in $task_duration seconds.");
				} else {
					$tasks_failed++;
					Debug::log("!! Scheduled task $task_name has failed with RC: $rc after $task_duration seconds.");
				}

				if ($task_record) {
					$task_record->last_run = Db::NOW();
					$task_record->last_duration = $task_duration;
					$task_record->last_rc = $rc;
					$task_record->last_cron_expression = $task['cron']->getExpression();

					$task_record->save();
				} else {
					$task_record = ORM::for_table('ttrss_scheduled_tasks')->create();

					$task_record->set([
						'task_name' => $task_name,
						'last_duration' => $task_duration,
						'last_rc' => $rc,
						'last_run' => Db::NOW(),
						'last_cron_expression' => $task['cron']->getExpression()
					]);

					$task_record->save();
				}
			}
		}

		Debug::log("[$this->name] Processing scheduled tasks finished with $tasks_succeeded tasks succeeded and $tasks_failed tasks failed.");
	}

	/**
	 * Purge records of scheduled tasks that aren't currently registered
	 * and haven't ran for a long time.
	 *
	 * @return int 0 if successful, 1 on failure
	 */
	private function purge_orphaned_tasks(): int {
		if (!$this->scheduled_tasks) {
			Debug::log(__METHOD__ . ' was invoked before scheduled tasks have been registered.  This should never happen.');
			return 1;
		}

		$result = ORM::for_table('ttrss_scheduled_tasks')
			->where_not_in('task_name', array_keys($this->scheduled_tasks))
			->where_raw("last_run < NOW() - INTERVAL '5 weeks'")
			->delete_many();

		if ($result) {
			$deleted_count = ORM::get_last_statement()->rowCount();

			if ($deleted_count)
				Debug::log("Purged {$deleted_count} orphaned scheduled tasks.");
		}

		return $result ? 0 : 1;
	}
}
