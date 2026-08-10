<?php
class Auth_Internal extends Auth_Base implements IAuthModule2 {

	public function about() {
		return [null,
			"Authenticates against internal tt-rss database",
			"fox",
			true];
	}

	public function init($host) {
		$host->add_hook($host::HOOK_AUTH_USER, $this);
	}

	/** @param string $service */
	public function authenticate($login, $password, $service = '') {

		$otp = (int) ($_REQUEST["otp"] ?? 0);

		// don't bother with null/null logins for auth_external etc
		if ($login && Config::get_schema_version() > 96) {

			$user_id = UserHelper::find_user_by_login($login);

			if ($user_id && UserHelper::is_otp_enabled($user_id)) {

				// only allow app passwords for service logins if OTP is enabled
				if ($service && Config::get_schema_version() > 138) {
					return $this->check_app_password($login, $password, $service);
				}

				if (Config::get(Config::DISABLE_LOGIN_FORM))
					return false;

				if ($otp) {
					if ($this->check_password($user_id, $password) && UserHelper::check_otp($user_id, $otp))
						return $user_id;
					else
						return false;

				} else {
					$return = urlencode(with_trailing_slash($_REQUEST["return"]));
					?>
					<!DOCTYPE html>
					<html>
						<head>
							<title>Tiny Tiny RSS</title>
							<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
							<?php foreach (["lib/dojo/dojo.js",
								"lib/dojo/tt-rss-layer.js",
								"js/common.js",
								"js/utility.js"] as $jsfile) {
									echo javascript_tag($jsfile);
								} ?>
								<style type="text/css">
									@media (prefers-color-scheme: dark) {
										body {
											background : #303030;
										}
									}

									body.css_loading * {
										display : none;
									}
								</style>

								<script type="text/javascript">
									require({cache:{}});

									const UtilityApp = {
										init: function() {
											require(['dojo/parser', "dojo/ready", 'dijit/form/Button', 'dijit/form/Form',
													'dijit/form/TextBox','dijit/form/ValidationTextBox'],function(parser, ready){
	                						ready(function() {
													parser.parse();
													dijit.byId("otp").focus();
												});
											});
										},
									};
								</script>
								<?= Config::get_override_links() ?>
							</head>
							<body class="flat ttrss_utility otp css_loading">
								<h1><?= __("Authentication") ?></h1>
								<div class="content">
									<form dojoType="dijit.form.Form" action="public.php?return=<?= $return ?>" method="post" class="otpform">

										<?php foreach (["login", "password", "bw_limit", "safe_mode", "remember_me", "profile"] as $key) {
											print \Controls\hidden_tag($key, $_POST[$key] ?? "");
										} ?>

										<?= \Controls\hidden_tag("op", "login") ?>

										<fieldset>
											<label><?= __("Please enter verification code (OTP):") ?></label>
											<input id="otp" dojoType="dijit.form.ValidationTextBox" required="1" autocomplete="off" size="6" name="otp" value=""/>
											<?= \Controls\submit_tag(__("Continue")) ?>
										</fieldset>
									</form>
								</div>
							</body>
						</html>
					<?php
					exit;
				}
			}
		}

		// service logins: check app passwords first but allow regular password
		// as a fallback if OTP is not enabled

		if ($service && Config::get_schema_version() > 138) {
			$user_id = $this->check_app_password($login, $password, $service);

			if ($user_id)
				return $user_id;
		}

		if (Config::get(Config::DISABLE_LOGIN_FORM))
			return false;

		if ($login) {
			$user = ORM::for_table('ttrss_users')
				->where_raw('LOWER(login) = LOWER(?)', [$login])
				->find_one();

			if ($user) {
				if (Config::get_schema_version() >= 145) {
					if ($user->last_auth_attempt) {
						$last_auth_attempt = strtotime($user->last_auth_attempt);

						if ($last_auth_attempt && time() - $last_auth_attempt < Config::get(Config::AUTH_MIN_INTERVAL)) {
							Logger::log(E_USER_NOTICE, "Too many authentication attempts for {$user->login}, throttled.");

							// start an empty session to deliver login error message
							if (session_status() != PHP_SESSION_ACTIVE)
								session_start();

							$_SESSION["login_error_msg"] = __("Too many authentication attempts, throttled.");

							$user->last_auth_attempt = Db::NOW();
							$user->save();

							return false;
						}
					}
				}

				$auth_result = $this->check_password($user->id, $password);

				if ($auth_result) {
					return $auth_result;
				} else {
					if (Config::get_schema_version() >= 145) {
						$user->last_auth_attempt = Db::NOW();
						$user->save();
					}
				}
			}
		}

		return false;
	}

	/**
	 * @param int $owner_uid
	 * @param string $password
	 * @param string $service
	 * @return int|false (false if failed, user id otherwise)
	 * @throws PDOException
	 * @throws Exception
	 */
	public function check_password(int $owner_uid, string $password, string $service = ''): false|int {

		$user = ORM::for_table('ttrss_users')->find_one($owner_uid);

		if ($user) {
			$salt = $user['salt'] ?? "";
			$login = $user['login'];
			$pwd_hash = $user['pwd_hash'];

			[$pwd_algo, $raw_hash] = explode(":", $pwd_hash, 2);

			// check app password only if service is specified
			if ($service && Config::get_schema_version() > 138) {
				return $this->check_app_password($login, $password, $service);
			}

			$test_hash = UserHelper::hash_password($password, $salt, $pwd_algo);

			if (hash_equals($pwd_hash, $test_hash)) {
				if ($pwd_algo != UserHelper::HASH_ALGOS[0]) {
					Logger::log(E_USER_NOTICE, "Upgrading password of user $login to " . UserHelper::HASH_ALGOS[0]);

					$new_hash = UserHelper::hash_password($password, $salt, UserHelper::HASH_ALGOS[0]);

					if ($new_hash) {
						$usth = $this->pdo->prepare("UPDATE ttrss_users SET pwd_hash = ? WHERE id = ?");
						$usth->execute([$new_hash, $owner_uid]);
					}
				}
				return $owner_uid;
			}
		}

		return false;
	}

	public function change_password(int $owner_uid, string $old_password, string $new_password) : string {

		if ($this->check_password($owner_uid, $old_password)) {

			$new_salt = UserHelper::get_salt();
			$new_password_hash = UserHelper::hash_password($new_password, $new_salt, UserHelper::HASH_ALGOS[0]);

			$user = ORM::for_table('ttrss_users')->find_one($owner_uid);

			$user->salt = $new_salt;
			$user->pwd_hash = $new_password_hash;
			$user->save();

			if ($_SESSION["uid"] ?? 0 == $owner_uid)
				$_SESSION["pwd_hash"] = $new_password_hash;

			if ($user->email) {
				$mailer = new Mailer();

				$tpl = new Templator();

				$tpl->readTemplateFromFile("password_change_template.txt");

				$tpl->setVariable('LOGIN', $user->login);
				$tpl->setVariable('TTRSS_HOST', Config::get_self_url());

				$tpl->addBlock('message');

				$tpl->generateOutputToString($message);

				$mailer->mail(["to_name" => $user->login,
					"to_address" => $user->email,
					"subject" => "[tt-rss] Password change notification",
					"message" => $message]);

			}

			return __("Password has been changed.");
		} else {
			return "ERROR: ".__('Old password is incorrect.');
		}
	}

	/**
	 * @param string $login
	 * @param string $password
	 * @param string $service
	 * @return false|int (false if failed, user id otherwise)
	 * @throws PDOException
	 * @throws Exception
	 */
	private function check_app_password(string $login, string $password, string $service): false|int {
		$sth = $this->pdo->prepare("SELECT p.id, p.pwd_hash, u.id AS uid
			FROM ttrss_app_passwords p, ttrss_users u
			WHERE p.owner_uid = u.id AND LOWER(u.login) = LOWER(?) AND service = ?");
		$sth->execute([$login, $service]);

		while ($row = $sth->fetch()) {
			[$pwd_algo, $raw_hash, $salt] = explode(":", $row["pwd_hash"]);

			$test_hash = UserHelper::hash_password($password, $salt, $pwd_algo);

			if (hash_equals("$pwd_algo:$raw_hash", $test_hash)) {
				$pass = ORM::for_table('ttrss_app_passwords')->find_one($row["id"]);
				$pass->last_used = Db::NOW();

				if ($pwd_algo != UserHelper::HASH_ALGOS[0]) {
					// upgrade password to current algo
					Logger::log(E_USER_NOTICE, "Upgrading app password of user $login to " . UserHelper::HASH_ALGOS[0]);

					$new_hash = UserHelper::hash_password($password, $salt);

					if ($new_hash) {
						$pass->pwd_hash = "$new_hash:$salt";
					}
				}

				$pass->save();

				return $row['uid'];
			}
		}

		return false;
	}

	public function api_version() {
		return 2;
	}

}
