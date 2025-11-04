'use strict'

/* global __, xhr, Notify, Tables, App, fox */

const	Users = {
	reload: function(sort) {
		return new Promise((resolve, reject) => {
			const user_search = document.getElementById("user_search");
			const search = user_search ? user_search.value : "";

			xhr.post("backend.php", { op: "Pref_Users", sort: sort, search: search }, (reply) => {
				dijit.byId('usersTab').attr('content', reply);
				Notify.close();
				resolve();
			}, (e) => { reject(e) });
		});
	},
	add: function() {
		const login = prompt(__("Please enter username:"), "");

		if (login) {
			Notify.progress("Adding user...");

			xhr.post("backend.php", {op: "Pref_Users", method: "add", login: login}, (reply) => {
				Users.reload().then(() => {
					Notify.info(reply);
				})
			});

		}
	},
	edit: function(id) {
		xhr.json('backend.php', {op: 'Pref_Users', method: 'edit', id: id}, (reply) => {
			const user = reply.user;
			const admin_disabled = (user.id === 1);

			const dialog = new fox.SingleUseDialog({
				id: "userEditDlg",
				title: __("Edit user"),
				execute: function () {
					if (this.validate()) {
						Notify.progress("Saving data...", true);

						xhr.post("backend.php", this.attr('value'), (reply) => {
							dialog.hide();
							Users.reload().then(() => {
								Notify.info(reply);
							});
						});
					}
				},
				content: `
					<form onsubmit='return false'>

						${App.FormFields.hidden_tag('id', user.id.toString())}
						${App.FormFields.hidden_tag('op', 'Pref_Users')}
						${App.FormFields.hidden_tag('method', 'editSave')}

						<div dojoType="dijit.layout.TabContainer" style="height : 400px">
							<div dojoType="dijit.layout.ContentPane" title="${__('Edit user')}">

								<section>
									<fieldset>
										<label>${__("Login:")}</label>
										<input style='font-size : 16px'
											${admin_disabled ? "disabled='1'" : ''}
											dojoType='dijit.form.ValidationTextBox' required='1'
											name='login' value="${App.escapeHtml(user.login)}">

										${admin_disabled ? App.FormFields.hidden_tag("login", user.login) : ''}
									</fieldset>

									<hr/>

									<fieldset>
										<label>${__('Access level: ')}</label>
										${App.FormFields.select_hash("access_level",
											user.access_level, reply.access_level_names, {disabled: admin_disabled.toString()}, "", {numeric_sort: true})}

										${admin_disabled ? App.FormFields.hidden_tag("access_level",
											user.access_level.toString()) : ''}
									</fieldset>
									<fieldset>
										<label>${__("New password:")}</label>
										<input dojoType='dijit.form.TextBox' type='password' size='20'
											placeholder='${__("Change password")}' name='password'>
									</fieldset>
									<fieldset>
									<label></label>
									<label class="checkbox">
										${App.FormFields.checkbox_tag("otp_enabled", user.otp_enabled)}
										${__('OTP enabled')}
									</fieldset>

									<hr/>

									<fieldset>
										<label>${__("Email:")}</label>
										<input dojoType='dijit.form.TextBox' size='30' name='email'
											value="${App.escapeHtml(user.email)}">
									</fieldset>
								</section>
							</div>
							<div dojoType="dijit.layout.ContentPane" title="${__('User details')}">
								<script type='dojo/method' event='onShow' args='evt'>
									if (this.domNode.querySelector('.loading')) {
										xhr.post("backend.php", {op: 'Pref_Users', method: 'userdetails', id: ${user.id}}, (reply) => {
											this.attr('content', reply);
										});
									}
								</script>
								<span class='loading'>${__("Loading, please wait...")}</span>
							</div>
						</div>

						<footer>
							<button dojoType='dijit.form.Button' class='alt-primary' type='submit' onclick='App.dialogOf(this).execute()'>
								${App.FormFields.icon("save")}
								${__('Save')}
							</button>
							<button dojoType='dijit.form.Button' onclick='App.dialogOf(this).hide()'>
								${__('Cancel')}
							</button>
						</footer>
					</form>
				`
			});

			dialog.show();
		});
	},
	resetSelected: function() {
		const rows = this.getSelection();

		if (rows.length === 0) {
			alert(__("No users selected."));
			return;
		}

		if (rows.length > 1) {
			alert(__("Please select one user."));
			return;
		}

		if (confirm(__("Reset password of selected user?"))) {
			Notify.progress("Resetting password for selected user...");

			const id = rows[0];

			xhr.post("backend.php", {op: "Pref_Users", method: "resetPass", id: id}, (reply) => {
				Notify.close();
				Notify.info(reply, true);
			});

		}
	},
	removeSelected: function() {
		const sel_rows = this.getSelection();

		if (sel_rows.length > 0) {
			if (confirm(__("Remove selected users? Neither default admin nor your account will be removed."))) {
				Notify.progress("Removing selected users...");

				const query = {
					op: "Pref_Users", method: "remove",
					ids: sel_rows.toString()
				};

				xhr.post("backend.php", query, () => {
					this.reload();
				});
			}

		} else {
			alert(__("No users selected."));
		}
	},
	getSelection :function() {
		return Tables.getSelected("users-list");
	}
}

// Expose to global scope for Dojo widget onclick handlers (needed since Dojo 1.17.3)
window.Users = Users;
