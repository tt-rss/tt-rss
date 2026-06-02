<?php
	error_reporting(E_ERROR | E_PARSE);

	chdir("..");

	define('NO_SESSION_AUTOSTART', true);

	require_once __DIR__ . '/../include/autoload.php';
	require_once __DIR__ . '/../include/sessions.php';

	ini_set('session.use_cookies', "0");
	ini_set("session.gc_maxlifetime", "86400");

	// Block requests explicitly initiated by a web browser environment
	// or with an invalid content type
	if (isset($_SERVER['HTTP_SEC_FETCH_MODE'])) {
		// || !in_array($_SERVER['CONTENT_TYPE'] ?? '', ['text/json', 'application/json'])) {
		header('Content-Type: application/json');

		print json_encode([
			'seq' => -1,
			'status' => API::STATUS_ERR,
			'content' => ['error' => API::E_INCORRECT_USAGE],
		]);

		return;
	}

	ob_start();

	$_REQUEST = json_decode((string)file_get_contents("php://input"), true);

	if (!empty($_REQUEST["sid"])) {
		session_id($_REQUEST["sid"]);
		session_start();
	}

	startup_gettext();

	if (!init_plugins()) return;

	if (!empty($_SESSION["uid"])) {
		if (!Sessions::validate_session()) {
			header("Content-Type: application/json");

			print json_encode([
						"seq" => -1,
						"status" => API::STATUS_ERR,
						"content" => [ "error" => API::E_NOT_LOGGED_IN ]
					]);

			return;
		}

		UserHelper::load_user_plugins($_SESSION["uid"]);
	}

	$method = strtolower($_REQUEST["op"] ?? "");

	$handler = new API($_REQUEST ?? []);

	if ($handler->before($method)) {
		if ($method && method_exists($handler, $method)) {
			$handler->$method();
		} else /* if (method_exists($handler, 'index')) */ {
			$handler->index($method);
		}
		// API isn't currently overriding Handler#after()
		// $handler->after();
	}

	$content_length = ob_get_length();

	header("Api-Content-Length: $content_length");
	header("Content-Length: $content_length");

	ob_end_flush();

