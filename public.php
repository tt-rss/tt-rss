<?php
	require_once __DIR__ . '/include/autoload.php';
	require_once __DIR__ . '/include/sessions.php';

	Config::sanity_check();

	startup_gettext();

	if (!init_plugins()) return;

	$method = clean($_REQUEST['op'] ?? '');

	// shortcut syntax for public (exposed) methods (?op=plugin--pmethod&...params)
	if (str_contains($method, PluginHost::PUBLIC_METHOD_DELIMITER)) {
		[$plugin, $pmethod] = explode(PluginHost::PUBLIC_METHOD_DELIMITER, $method, 2);

		// TODO: better implementation that won't modify $_REQUEST
		$_REQUEST["plugin"] = $plugin;
		$_REQUEST["pmethod"] = $pmethod;

		$method = "pluginhandler";
	}

	if (str_starts_with($method, "_")) {
		user_error("Refusing to invoke method $method which starts with underscore.", E_USER_WARNING);
		header("Content-Type: application/json");
		print Errors::to_json(Errors::E_UNAUTHORIZED);

		return;
	}

	$handler = PluginHost::getInstance()->lookup_handler('public', $method) ?: new Handler_Public($_REQUEST);

	if (implements_interface($handler, "IHandler") && $handler->before($method)) {

		if ($method && method_exists($handler, $method)) {
			$reflection = new ReflectionMethod($handler, $method);

			if ($reflection->getNumberOfRequiredParameters() == 0) {
				$handler->$method();
			} else {
				user_error("Refusing to invoke method $method which has required parameters.", E_USER_WARNING);
				header("Content-Type: application/json");
				print Errors::to_json(Errors::E_UNAUTHORIZED);

			}
		} else if (method_exists($handler, 'index')) {
			$handler->index();
		}

		$handler->after();
		return;
	}

	header("Content-Type: text/plain");
	print Errors::to_json(Errors::E_UNKNOWN_METHOD);
