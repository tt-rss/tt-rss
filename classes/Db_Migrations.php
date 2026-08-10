<?php
class Db_Migrations {

	private string $base_filename = "schema.sql";
	private string $base_path;
	private string $migrations_path;
	private string $migrations_table;
	private bool $base_is_latest;
	private readonly PDO $pdo;
	private int $cached_version = 0;
	private int $cached_max_version = 0;
	private int $max_version_override;

	public function __construct() {
		$this->pdo = Db::pdo();
	}

	public function initialize_for_plugin(Plugin $plugin, bool $base_is_latest = true, string $schema_suffix = "sql"): void {
		$plugin_dir = PluginHost::getInstance()->get_plugin_dir($plugin);
		$this->initialize("{$plugin_dir}/{$schema_suffix}",
			strtolower("ttrss_migrations_plugin_" . $plugin::class),
			$base_is_latest);
	}

	public function initialize(string $root_path, string $migrations_table, bool $base_is_latest = true, int $max_version_override = 0): void {
		$this->base_path = "$root_path/pgsql";
		$this->migrations_path = $this->base_path . "/migrations";
		$this->migrations_table = $migrations_table;
		$this->base_is_latest = $base_is_latest;
		$this->max_version_override =  $max_version_override;
	}

	private function set_version(int $version): void {
		Debug::log("Updating table {$this->migrations_table} with version {$version}...", Debug::LOG_EXTENDED);

		$sth = $this->pdo->query("SELECT * FROM {$this->migrations_table}");

		if ($sth->fetch()) {
			$sth = $this->pdo->prepare("UPDATE {$this->migrations_table} SET schema_version = ?");
		} else {
			$sth = $this->pdo->prepare("INSERT INTO {$this->migrations_table} (schema_version) VALUES (?)");
		}

		$sth->execute([$version]);

		$this->cached_version = $version;
	}

	public function get_version() : int {
		if ($this->cached_version)
			return $this->cached_version;

		try {
			$sth = $this->pdo->query("SELECT * FROM {$this->migrations_table}");

			if ($res = $sth->fetch()) {
				return (int) $res['schema_version'];
			} else {
				return -1;
			}
		} catch (PDOException) {
			$this->create_migrations_table();

			return -1;
		}
	}

	private function create_migrations_table(): void {
		$this->pdo->query("CREATE TABLE IF NOT EXISTS {$this->migrations_table} (schema_version integer not null)");
	}

	/**
	 * @throws PDOException
	 * @return bool false if the migration failed, otherwise true (or an exception)
	 */
	private function migrate_to(int $version): bool {
		try {
			if ($version <= $this->get_version()) {
				Debug::log("Refusing to apply version $version: current version is higher", Debug::LOG_VERBOSE);
				return false;
			}

			if ($version == 0)
				Debug::log("Loading base database schema...", Debug::LOG_VERBOSE);
			else
				Debug::log("Starting migration to $version...", Debug::LOG_VERBOSE);

			$lines = $this->get_lines($version);

			if (count($lines) > 0) {
				$this->pdo->beginTransaction();

				foreach ($lines as $line) {
					Debug::log($line, Debug::LOG_EXTENDED);
					try {
						$this->pdo->query($line);
					} catch (PDOException $e) {
						Debug::log("Failed on line: $line", Debug::LOG_VERBOSE);
						throw $e;
					}
				}

				if ($version == 0 && $this->base_is_latest)
					$this->set_version($this->get_max_version());
				else
					$this->set_version($version);

				$this->pdo->commit();

				Debug::log("Migration finished, current version: " . $this->get_version(), Debug::LOG_VERBOSE);

				Logger::log(E_USER_NOTICE, "Applied migration to version $version for {$this->migrations_table}");
				return true;
			} else {
				Debug::log("Migration failed: schema file is empty or missing.", Debug::LOG_VERBOSE);
				return false;
			}

		} catch (PDOException $e) {
			Debug::log("Migration failed: " . $e->getMessage(), Debug::LOG_VERBOSE);
			try {
				$this->pdo->rollback();
			} catch (PDOException) {
				//
			}
			throw $e;
		}
	}

	public function get_max_version() : int {
		if ($this->max_version_override > 0)
			return $this->max_version_override;

		if ($this->cached_max_version)
			return $this->cached_max_version;

		$migrations = glob("{$this->migrations_path}/*.sql");

		if (count($migrations) > 0) {
			natsort($migrations);

			$this->cached_max_version = (int) basename(array_pop($migrations), ".sql");

		} else {
			$this->cached_max_version = 0;
		}

		return $this->cached_max_version;
	}

	public function is_migration_needed() : bool {
		return $this->get_version() != $this->get_max_version();
	}

	public function migrate() : bool {

		if ($this->get_version() == -1) {
			try {
				$this->migrate_to(0);
			} catch (PDOException $e) {
				user_error("Failed to load base schema for {$this->migrations_table}: " . $e->getMessage(), E_USER_WARNING);
				return false;
			}
		}

		for ($i = $this->get_version() + 1; $i <= $this->get_max_version(); $i++) {
			try {
				$this->migrate_to($i);
			} catch (PDOException $e) {
				user_error("Failed to apply migration {$i} for {$this->migrations_table}: " . $e->getMessage(), E_USER_WARNING);
				return false;
				//throw $e;
			}
		}

		return !$this->is_migration_needed();
	}

	/**
	 * @return array<int, string>
	 */
	private function get_lines(int $version) : array {
		if ($version > 0)
			$filename = "{$this->migrations_path}/{$version}.sql";
		else
			$filename = "{$this->base_path}/{$this->base_filename}";

		if (file_exists($filename)) {
			$lines = array_filter(preg_split("/[\r\n]/", file_get_contents($filename)),
				fn($line) => strlen(trim($line)) > 0 && !str_starts_with($line, "--"));

			return array_filter(explode(";", implode("", $lines)),
				fn($line) => strlen(trim($line)) > 0 && !in_array(strtolower($line), ["begin", "commit"]));

		} else {
			user_error("Requested schema file {$filename} not found.", E_USER_ERROR);
			return [];
		}
	}
}
