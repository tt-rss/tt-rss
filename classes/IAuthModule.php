<?php
interface IAuthModule {
	/**
	 * @param string $login
	 * @param string $password
	 * @param string $service
	 * @return int|false user_id
	 */
	public function authenticate($login, $password, $service = '');

	/** this is a pluginhost compatibility wrapper that invokes $this->authenticate(...$args) (Auth_Base)
 	 * @param string $login
	 * @param string $password
	 * @param string $service
	 * @return int|false user_id
	 */
	public function hook_auth_user($login, $password, $service = '');
}
