<?php
class Auth_Remote extends Auth_Base {

	/** redirect user to this URL after logout; .env:
	 * TTRSS_AUTH_REMOTE_POST_LOGOUT_URL=http://127.0.0.1/logout-redirect
	 */
	const AUTH_REMOTE_POST_LOGOUT_URL = "AUTH_REMOTE_POST_LOGOUT_URL";

	function about() {
		return [null,
			"Authenticates against external passwords (HTTP Authentication, SSL certificates)",
			"fox",
			true];
	}

	function init($host) {
		$host->add_hook($host::HOOK_AUTH_USER, $this);

		Config::add(self::AUTH_REMOTE_POST_LOGOUT_URL, "", Config::T_STRING);

		if (Config::get(self::AUTH_REMOTE_POST_LOGOUT_URL) != "") {
			$host->add_hook($host::HOOK_POST_LOGOUT, $this);
		}
	}

	function get_login_by_ssl_certificate() : string {
		$cert_serial = Pref_Prefs::_get_ssl_certificate_id();

		if ($cert_serial) {
			$sth = $this->pdo->prepare("SELECT login FROM ttrss_user_prefs2, ttrss_users
				WHERE pref_name = 'SSL_CERT_SERIAL' AND value = ? AND
				owner_uid = ttrss_users.id");
			$sth->execute([$cert_serial]);

			if ($row = $sth->fetch()) {
				return $row['login'];
			}
		}

		return "";
	}

	function authenticate($login, $password, $service = '') {
		$try_login = "";

		foreach (["REMOTE_USER", "HTTP_REMOTE_USER", "REDIRECT_REMOTE_USER", "PHP_AUTH_USER"] as $hdr) {
			if (!empty($_SERVER[$hdr])) {
				$try_login = strtolower($_SERVER[$hdr]);
				break;
			}
		}

		if (!$try_login) $try_login = $this->get_login_by_ssl_certificate();

		if ($try_login) {
			$user_id = $this->auto_create_user($try_login, $password);

			if ($user_id) {
				$_SESSION["fake_login"] = $try_login;
				$_SESSION["fake_password"] = "******";
				$_SESSION["hide_hello"] = true;
				$_SESSION["hide_logout"] = true;

				// LemonLDAP can send user informations via HTTP HEADER
				if (Config::get(Config::AUTH_AUTO_CREATE)) {
					// update user name
					$fullname = $_SERVER['HTTP_USER_NAME'] ?? $_SERVER['AUTHENTICATE_CN'] ?? '';
					if ($fullname){
						$sth = $this->pdo->prepare("UPDATE ttrss_users SET full_name = ? WHERE id = ?");
						$sth->execute([$fullname, $user_id]);
					}
					// update user email
					$email = $_SERVER['HTTP_USER_MAIL'] ?? $_SERVER['AUTHENTICATE_MAIL'] ?? '';
					if ($email){
						$sth = $this->pdo->prepare("UPDATE ttrss_users SET email = ? WHERE id = ?");
						$sth->execute([$email, $user_id]);
					}
				}

				return $user_id;
			}
		}

		return false;
	}

	function hook_post_logout($login, $user_id) {
		return [
			Config::get(self::AUTH_REMOTE_POST_LOGOUT_URL)
			];
	}

	function api_version() {
		return 2;
	}

}
