<?php
class Pref_Users extends Handler_Administrative {
		public function csrf_ignore(string $method): bool {
			return $method == "index";
		}

		public function edit(): void {
			$user = ORM::for_table('ttrss_users')
				->select_many('id', 'login', 'access_level', 'email', 'full_name', 'otp_enabled')
				->find_one((int)$_REQUEST["id"])
				->as_array();

			global $access_level_names;

			if ($user) {
				print json_encode([
					"user" => $user,
					"access_level_names" => $access_level_names
				]);
			}
		}

		public function userdetails(): void {
			$id = (int) clean($_REQUEST["id"]);

			$user = ORM::for_table('ttrss_users')
				->table_alias('u')
				->select_many('u.login', 'u.access_level')
				->select_many_expr([
					'created' => 'SUBSTRING_FOR_DATE(u.created,1,16)',
					'last_login' => 'SUBSTRING_FOR_DATE(u.last_login,1,16)',
					'stored_articles' => '(SELECT COUNT(ue.int_id) FROM ttrss_user_entries ue WHERE ue.owner_uid = u.id)',
				])
				->find_one($id);

			if ($user) {
				$created = TimeHelper::make_local_datetime($user->created);
				$last_login = TimeHelper::make_local_datetime($user->last_login);

				$user_owned_feeds = ORM::for_table('ttrss_feeds')
					->select_many('id', 'title', 'site_url')
					->where('owner_uid', $id)
					->order_by_expr('LOWER(title)')
					->find_many();

				?>

				<fieldset>
					<label><?= __('Registered') ?>:</label>
					<?= $created ?>
				</fieldset>

				<fieldset>
					<label><?= __('Last logged in') ?>:</label>
					<?= $last_login ?>
				</fieldset>

				<fieldset>
					<label><?= __('Subscribed feeds') ?>:</label>
					<?= count($user_owned_feeds) ?>
				</fieldset>

				<fieldset>
					<label><?= __('Stored articles') ?>:</label>
					<?= $user->stored_articles ?>
				</fieldset>

				<ul class="panel panel-scrollable list list-unstyled">
					<?php foreach ($user_owned_feeds as $feed) { ?>
						<li>
							<?php
								$icon_url = Feeds::_get_icon_url($feed->id, 'images/blank_icon.gif');
							?>

							<img class="icon" src="<?= htmlspecialchars($icon_url) ?>">

							<a target="_blank" href="<?= htmlspecialchars($feed->site_url) ?>">
								<?= htmlspecialchars($feed->title) ?>
							</a>
						</li>
					<?php } ?>
				</ul>

				<?php

			} else {
				print_error(__('User not found'));
			}

		}

		public function editSave(): void {
			$id = (int)$_REQUEST['id'];
			$password = clean($_REQUEST["password"]);
			$user = ORM::for_table('ttrss_users')->find_one($id);

			if ($user) {
				$login = clean($_REQUEST["login"]);

				if ($id == 1) $login = "admin";
				if (!$login) return;

				$user->login = mb_strtolower($login);
				$user->access_level = (int) clean($_REQUEST["access_level"]);
				$user->email = clean($_REQUEST["email"]);
				$user->otp_enabled = checkbox_to_sql_bool($_REQUEST["otp_enabled"] ?? "");

				// force new OTP secret when next enabled
				if (Config::get_schema_version() >= 143 && !$user->otp_enabled) {
					$user->otp_secret = null;
				}

				$user->save();
			}

			if ($password) {
				UserHelper::reset_password($id, false, $password);
			}
		}

		public function remove(): void {
			$ids = self::_param_to_int_array($_REQUEST['ids']);

			foreach ($ids as $id) {
				if ($id != $_SESSION['uid'] && $id != 1) {
					ORM::for_table('ttrss_tags')->where('owner_uid', $id)->delete_many();
					ORM::for_table('ttrss_feeds')->where('owner_uid', $id)->delete_many();
					ORM::for_table('ttrss_users')->where('id', $id)->delete_many();
				}
			}
		}

		public function add(): void {
			$login = clean($_REQUEST["login"]);

			if (!$login) return; // no blank usernames

			if (!UserHelper::find_user_by_login($login)) {

				$new_password = make_password();

				$user = ORM::for_table('ttrss_users')->create();

				$user->salt = UserHelper::get_salt();
				$user->login = mb_strtolower($login);
				$user->pwd_hash = UserHelper::hash_password($new_password, $user->salt);
				$user->access_level = 0;
				$user->created = Db::NOW();
				$user->save();

				if (!is_null(UserHelper::find_user_by_login($login))) {
					print T_sprintf("Added user %s with password %s",
						$login, $new_password);
				} else {
					print T_sprintf("Could not create user %s", $login);
				}
			} else {
				print T_sprintf("User %s already exists.", $login);
			}
		}

		public function resetPass(): void {
			UserHelper::reset_password(clean($_REQUEST["id"]));
		}

		public function index(): void {

			global $access_level_names;

			$user_search = clean($_REQUEST["search"] ?? "");

			if (array_key_exists("search", $_REQUEST)) {
				$_SESSION["prefs_user_search"] = $user_search;
			} else {
				$user_search = ($_SESSION["prefs_user_search"] ?? "");
			}

			$sort = clean($_REQUEST["sort"] ?? "");

			if (!$sort || $sort == "undefined") {
				$sort = "login";
			}

			if (!in_array($sort, ["login", "access_level", "created", "num_feeds", "created", "last_login"]))
				$sort = "login";

			if ($sort != "login") $sort = "$sort DESC";
			?>

			<div dojoType='dijit.layout.BorderContainer' gutters='false'>
				<div style='padding : 0px' dojoType='dijit.layout.ContentPane' region='top'>
					<div dojoType='fox.Toolbar'>

						<div style='float : right'>
							<form dojoType="dijit.form.Form" onsubmit="Users.reload(); return false;">
								<input dojoType='dijit.form.TextBox' id='user_search' size='20' type='search'
									value="<?= htmlspecialchars($user_search) ?>">
								<button dojoType='dijit.form.Button' type='submit'>
									<?= __('Search') ?>
								</button>
							</form>
						</div>

						<div dojoType='fox.form.DropDownButton'>
							<span><?= __('Select') ?></span>
							<div dojoType='dijit.Menu' style='display: none'>
								<div onclick="Tables.select('users-list', true)"
									dojoType='dijit.MenuItem'><?= __('All') ?></div>
								<div onclick="Tables.select('users-list', false)"
									dojoType='dijit.MenuItem'><?= __('None') ?></div>
								</div>
							</div>

						<button dojoType='dijit.form.Button' onclick='Users.add()'>
							<?= __('Create user') ?>
						</button>

						<button dojoType='dijit.form.Button' onclick='Users.removeSelected()'>
							<?= __('Remove') ?>
						</button>

						<button dojoType='dijit.form.Button' onclick='Users.resetSelected()'>
							<?= __('Reset password') ?>
						</button>

						<?php PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB_SECTION, "prefUsersToolbar") ?>

					</div>
				</div>
				<div style='padding : 0px' dojoType='dijit.layout.ContentPane' region='center'>

					<table width='100%' class='users-list' id='users-list'>

						<tr>
							<th></th>
							<th><a href='#' onclick="Users.reload('login')"><?= __('Login') ?></a></th>
							<th><a href='#' onclick="Users.reload('access_level')"><?= __('Access level') ?></a></th>
							<th><a href='#' onclick="Users.reload('num_feeds')"><?= __('Subscribed feeds') ?></a></th>
							<th><a href='#' onclick="Users.reload('created')"><?= __('Registered') ?></a></th>
							<th><a href='#' onclick="Users.reload('last_login')"><?= __('Last login') ?></a></th>
						</tr>

						<?php
							$users = ORM::for_table('ttrss_users')
								->table_alias('u')
								->left_outer_join("ttrss_feeds", ["owner_uid", "=", "u.id"], 'f')
									->select_expr('u.*,COUNT(f.id) AS num_feeds')
									->where_like("login", $user_search ? "%$user_search%" : "%")
									->order_by_expr($sort)
									->group_by_expr('u.id')
									->find_many();

							foreach ($users as $user) { ?>

								<tr data-row-id='<?= $user["id"] ?>' onclick='Users.edit(<?= $user["id"] ?>)' title="<?= __('Click to edit') ?>">
									<td class='checkbox'>
										<input onclick='Tables.onRowChecked(this); event.stopPropagation();'
										dojoType='dijit.form.CheckBox' type='checkbox'>
									</td>

									<td width='30%'>
										<i class='material-icons'>person</i>
										<strong><?= htmlspecialchars($user["login"]) ?></strong>
									</td>
									<td><?= $access_level_names[$user["access_level"]] ?></td>
									<td><?= $user["num_feeds"] ?></td>
									<td class='text-muted'><?= TimeHelper::make_local_datetime($user['created']) ?></td>
									<td class='text-muted'><?= TimeHelper::make_local_datetime($user['last_login']) ?></td>
								</tr>
						<?php } ?>
					</table>
				</div>
				<?php PluginHost::getInstance()->run_hooks(PluginHost::HOOK_PREFS_TAB, "prefUsers") ?>
			</div>
		<?php
	}

}
