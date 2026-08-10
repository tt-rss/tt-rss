<?php
class PluginHandler extends Handler_Protected {
	public function csrf_ignore(string $method): bool {
		return true;
	}

	public function catchall(string $method): void {
		$plugin_name = clean($_REQUEST["plugin"]);
		$plugin = PluginHost::getInstance()->get_plugin($plugin_name);
		$csrf_token = ($_POST["csrf_token"] ?? "");

		if ($plugin) {
			if (method_exists($plugin, $method)) {
				if (validate_csrf($csrf_token) || $plugin->csrf_ignore($method)) {
					$plugin->$method();
				} else {
					user_error("Rejected {$plugin_name}->{$method}(): invalid CSRF token.", E_USER_WARNING);
					print Errors::to_json(Errors::E_UNAUTHORIZED);
				}
			} else {
				user_error("Rejected {$plugin_name}->{$method}(): unknown method.", E_USER_WARNING);
				print Errors::to_json(Errors::E_UNKNOWN_METHOD);
			}
		} else {
			user_error("Rejected {$plugin_name}->{$method}(): unknown plugin.", E_USER_WARNING);
			print Errors::to_json(Errors::E_UNKNOWN_PLUGIN);
		}
	}
}
