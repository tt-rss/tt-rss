'use strict';

/* global __, Tables, Notify, xhr, App, fox */

const	Helpers = {
	AppPasswords: {
		getSelected: function() {
			return Tables.getSelected("app-password-list");
		},
		updateContent: function(data) {
			document.getElementById("app_passwords_holder").innerHTML = data;
			dojo.parser.parse("app_passwords_holder");
		},
		removeSelected: function() {
			const rows = this.getSelected();

			if (rows.length === 0) {
				alert("No passwords selected.");
			} else if (confirm(__("Remove selected app passwords?"))) {

				xhr.post("backend.php", {op: "Pref_Prefs", method: "deleteAppPasswords", "ids[]": rows}, (reply) => {
					this.updateContent(reply);
					Notify.close();
				});

				Notify.progress("Loading, please wait...");
			}
		},
		generate: function() {
			const title = prompt("Password description:")

			if (title) {
				xhr.post("backend.php", {op: "Pref_Prefs", method: "generateAppPassword", title: title}, (reply) => {
					this.updateContent(reply);
					Notify.close();
				});

				Notify.progress("Loading, please wait...");
			}
		},
	},
	Feeds: {
		clearFeedAccessKeys: function() {
			if (confirm(__("This will invalidate all previously generated feed URLs. Continue?"))) {
				Notify.progress("Clearing URLs...");

				xhr.post("backend.php", {op: "Pref_Feeds", method: "clearKeys"}, () => {
					Notify.info("Generated URLs cleared.");
				});
			}

			return false;
		},
	},
	Digest: {
		preview: function() {
			const dialog = new fox.SingleUseDialog({
				title: __("Digest preview"),
				content: `
					<div class='panel panel-scrollable digest-preview'>
						<div class='text-center'>${__("Loading, please wait...")}</div>
					</div>

					<footer class='text-center'>
						${App.FormFields.submit_tag(__('Close this window'))}
					</footer>
				`
			});

			const tmph = dojo.connect(dialog, 'onShow', function () {
				dojo.disconnect(tmph);

				xhr.json("backend.php", {op: "Pref_Prefs", method: "previewDigest"}, (reply) => {
					dialog.domNode.querySelector('.digest-preview').innerHTML = reply[0];
				});
			});

			dialog.show();

		}
	},
	System: {
		//
	},
	EventLog: {
		log_page: 0,
		refresh: function() {
			this.log_page = 0;
			this.update();
		},
		update: function() {
			xhr.post("backend.php", {
						op: "Pref_System",
						severity: dijit.byId("severity").attr('value'),
						page: Helpers.EventLog.log_page
					}, (reply) => {

				dijit.byId('systemTab').attr('content', reply);
				Notify.close();
			});
		},
		nextPage: function()  {
			this.log_page += 1;
			this.update();
		},
		prevPage: function() {
			if (this.log_page > 0) this.log_page -= 1;

			this.update();
		},
		clear: function() {
			if (confirm(__("Clear event log?"))) {

				Notify.progress("Loading, please wait...");

				xhr.post("backend.php", {op: "Pref_System", method: "clearLog"}, () => {
					Helpers.EventLog.refresh();
				});
			}
		},
	},
	Profiles: {
		edit: function() {
			const dialog = new fox.SingleUseDialog({
				id: "profileEditDlg",
				title: __("Manage profiles"),
				getSelectedProfiles: function () {
					return Tables.getSelected("pref-profiles-list");
				},
				cloneSelected: function() {
					const sel_rows = this.getSelectedProfiles();

					if (sel_rows.length === 1) {
						const new_title = prompt(__("Name for cloned profile:"));

						if (new_title) {
							xhr.post("backend.php", {op: "Pref_Prefs", method: "cloneprofile", "new_title": new_title, "old_profile": sel_rows[0]}, () => {
								Notify.close();
								dialog.refresh();
							});
						}

					} else {
						alert(__("Please select a single profile to clone."));
					}

				},
				removeSelected: function () {
					const sel_rows = this.getSelectedProfiles();

					if (sel_rows.length > 0) {
						if (confirm(__("Remove selected profiles? Active and default profiles will not be removed."))) {
							Notify.progress("Removing selected profiles...", true);

							xhr.post("backend.php", {op: "Pref_Prefs", method: "remprofiles", "ids[]": sel_rows}, () => {
								Notify.close();
								dialog.refresh();
							});
						}

					} else {
						alert(__("No profiles selected."));
					}
				},
				addProfile: function () {
					if (this.validate()) {
						Notify.progress("Creating profile...", true);

						const query = {op: "Pref_Prefs", method: "addprofile", title: dialog.attr('value').newprofile};

						xhr.post("backend.php", query, () => {
							Notify.close();
							dialog.refresh();
						});

					}
				},
				refresh: function() {
					xhr.json("backend.php", {op: 'Pref_Prefs', method: 'getprofiles'}, (reply) => {
						dialog.attr('content', `
							<div dojoType='fox.Toolbar'>
								<div dojoType='fox.form.DropDownButton'>
									<span>${__('Select')}</span>
									<div dojoType='dijit.Menu' style='display: none'>
										<div onclick="Tables.select('pref-profiles-list', true)"
											dojoType='dijit.MenuItem'>${__('All')}</div>
										<div onclick="Tables.select('pref-profiles-list', false)"
											dojoType='dijit.MenuItem'>${__('None')}</div>
									</div>
								</div>

								<div class="pull-right">
									<input name='newprofile' dojoType='dijit.form.ValidationTextBox' required='1'>
									${App.FormFields.button_tag(App.FormFields.icon("add_circle") + " " + __('Add'), "", {onclick: 'App.dialogOf(this).addProfile()'})}
								</div>
							</div>

							<form onsubmit='return false'>
								<div class='panel panel-scrollable'>
									<table width='100%' id='pref-profiles-list'>
										${reply.map((profile) => `
											<tr data-row-id="${profile.id}">
												<td class='checkbox'>
													${App.FormFields.checkbox_tag("", false, "", {onclick: 'Tables.onRowChecked(this)'})}
												</td>
												<td>
													${profile.id > 0 ?
														`<span dojoType='dijit.InlineEditBox' width='300px' autoSave='false'
															profile-id='${profile.id}'>${profile.title}
																<script type='dojo/method' event='onChange' args='value'>
																	xhr.post("backend.php",
																		{op: 'Pref_Prefs', method: 'saveprofile', value: value, id: this.attr('profile-id')}, () => {
																			//
																		});
																</script>
														</span>` : `${profile.title}`}
													${profile.active ? __("(active)") : ""}
													${profile.initialized ? "" : __("(empty)")}
												</td>
											</tr>
										`).join("")}
									</table>
								</div>

								<footer>
									${App.FormFields.button_tag(App.FormFields.icon("delete") + " " +__('Remove selected'), "",
										{class: 'pull-left alt-danger', onclick: 'App.dialogOf(this).removeSelected()'})}
									${App.FormFields.button_tag(App.FormFields.icon("content_copy") + " " + __('Clone'), "",
									{class: '', onclick: 'App.dialogOf(this).cloneSelected()'})}
									${App.FormFields.submit_tag(App.FormFields.icon("check") + " " + __('Activate'), {onclick: 'App.dialogOf(this).execute()'})}
									${App.FormFields.cancel_dialog_tag(__('Cancel'))}
								</footer>
							</form>
						`);
					});
				},
				execute: function () {
					const sel_rows = this.getSelectedProfiles();

					if (sel_rows.length === 1) {
						if (confirm(__("Activate selected profile?"))) {
							Notify.progress("Loading, please wait...");

							xhr.post("backend.php", {op: "Pref_Prefs", method: "activateprofile", id: sel_rows.toString()}, () => {
								window.location.reload();
							});
						}

					} else {
						alert(__("Please choose a profile to activate."));
					}
				},
				content: ""
			});

			dialog.refresh();
			dialog.show();
		},
	},
	Prefs: {
		customizeCSS: function() {
			const dialog = new fox.SingleUseDialog({
				title: __("Customize stylesheet"),
				apply: function() {
					xhr.post("backend.php", this.attr('value'), () => {
						Element.show("css_edit_apply_msg");
						document.getElementById("user_css_style").innerText = this.attr('value').value;
					});
				},
				execute: function () {
					Notify.progress('Saving data...', true);

					xhr.post("backend.php", this.attr('value'), () => {
						window.location.reload();
					});
				},
				content: `
					<div class='alert alert-info'>
						${__("You can override colors, fonts and layout of your currently selected theme with custom CSS declarations here.")}
					</div>

					${App.FormFields.hidden_tag('op', 'RPC')}
					${App.FormFields.hidden_tag('method', 'setpref')}
					${App.FormFields.hidden_tag('key', 'USER_STYLESHEET')}

					<div id='css_edit_apply_msg' style='display : none'>
						<div class='alert alert-warning'>
							${__("User CSS has been applied, you might need to reload the page to see all changes.")}
						</div>
					</div>

					<textarea class='panel user-css-editor' disabled='true' dojoType='dijit.form.SimpleTextarea'
						style='font-size : 12px;' name='value'>${__("Loading, please wait...")}</textarea>

					<footer>
						<button dojoType='dijit.form.Button' class='alt-success' onclick="App.dialogOf(this).apply()">
							${App.FormFields.icon("check")}
							${__('Apply')}
						</button>
						<button dojoType='dijit.form.Button' class='alt-primary' type='submit'>
							${App.FormFields.icon("refresh")}
							${__('Save and reload')}
						</button>
						<button dojoType='dijit.form.Button' onclick="App.dialogOf(this).hide()">
							${__('Cancel')}
						</button>
					</footer>
				`
			});

			const tmph = dojo.connect(dialog, 'onShow', function () {
				dojo.disconnect(tmph);

				xhr.json("backend.php", {op: "Pref_Prefs", method: "customizeCSS"}, (reply) => {

					const editor = dijit.getEnclosingWidget(dialog.domNode.querySelector(".user-css-editor"));

					editor.attr('value', reply.value);
					editor.attr('disabled', false);
				});

			});

			dialog.show();

		},
		confirmReset: function() {
			if (confirm(__("Reset to defaults?"))) {
				xhr.post("backend.php", {op: "Pref_Prefs", method: "resetconfig"}, (reply) => {
					Helpers.Prefs.refresh();
					Notify.info(reply);
				});
			}
		},
		refresh: function() {
			xhr.post("backend.php", { op: "Pref_Prefs" }, (reply) => {
				dijit.byId('prefsTab').attr('content', reply);
				Notify.close();
			});
		},
	},
	Plugins: {
		_list_of_plugins: [],
		_search_query: "",
		enableSelected: function() {
			const form = dijit.byId("changePluginsForm");

			if (form.validate()) {
				xhr.post("backend.php", form.getValues(), () => {
					Notify.close();
					if (confirm(__('Selected plugins have been enabled. Reload?'))) {
						window.location.reload();
					}
				})
			}
		},
		search: function() {
			this._search_query = dijit.byId("changePluginsForm").getValues().search;
			this.render_contents();
		},
		reload: function() {
			xhr.json("backend.php", {op: "Pref_Prefs", method: "getPluginsList"}, (reply) => {
				this._list_of_plugins = reply;
				this.render_contents();
			}, (e) => {
				this.render_error(e);
			});
		},
		render_error: function(e) {
			const container = document.querySelector(".prefs-plugin-list");

			container.innerHTML = `<li class='text-error'>${__("Error while loading plugins list: %s.").replace("%s", e)}</li>`;
		},
		render_contents: function() {
			const container = document.querySelector(".prefs-plugin-list");

			container.innerHTML = "";
			let results_rendered = 0;

			const is_admin = this._list_of_plugins.is_admin;

			const search_tokens = this._search_query
							.split(/ {1,}/)
							.filter((stoken) => (stoken.length > 0 ? stoken : null));

			this._list_of_plugins.plugins.forEach((plugin) => {

				if (search_tokens.length === 0 ||
					Object.values(plugin).filter((pval) =>
						search_tokens.filter((stoken) =>
							(pval.toString().indexOf(stoken) !== -1 ? stoken : null)
						).length === search_tokens.length).length > 0) {

						++results_rendered;

						// only user-enabled actually counts in the checkbox when saving because system plugin checkboxes are disabled (see below)
						container.innerHTML += `
							<li data-row-value="${App.escapeHtml(plugin.name)}" data-plugin-local="${plugin.is_local}"
								data-plugin-name="${App.escapeHtml(plugin.name)}" title="${plugin.is_system ? __("System plugins are enabled using global configuration.") : ""}">
								<label class="checkbox ${plugin.is_system ? "system text-info" : ""}">
									${App.FormFields.checkbox_tag("plugins[]", plugin.user_enabled || plugin.system_enabled, plugin.name,
										{disabled: plugin.is_system})}</div>
									<span class='name'>${plugin.name}:</span>
									<span class="description ${plugin.is_system ? "text-info" : ""}">
										${plugin.description}
									</span>
								</label>
								<div class='actions'>
									${plugin.is_system ?
										App.FormFields.button_tag(App.FormFields.icon("security"), "",
											{disabled: true}) : ''}
									${plugin.more_info ?
											App.FormFields.button_tag(App.FormFields.icon("help"), "",
												{class: 'alt-info', onclick: `App.openUrl(${JSON.stringify(plugin.more_info)})`}) : ''}
									${is_admin && plugin.is_local ?
										App.FormFields.button_tag(App.FormFields.icon("update"), "",
											{title: __("Update"), class: 'alt-warning', "data-update-btn-for-plugin": plugin.name, style: 'display : none',
												onclick: `Helpers.Plugins.update(${JSON.stringify(plugin.name)})`}) : ''}
									${is_admin && plugin.has_data ?
										App.FormFields.button_tag(App.FormFields.icon("clear"), "",
											{title: __("Clear data"), onclick: `Helpers.Plugins.clearData(${JSON.stringify(plugin.name)})`}) : ''}
									${is_admin && plugin.is_local ?
										App.FormFields.button_tag(App.FormFields.icon("delete"), "",
											{title: __("Uninstall"), onclick: `Helpers.Plugins.uninstall(${JSON.stringify(plugin.name)})`}) : ''}
								</div>
								<div class='version text-muted'>${plugin.version}</div>
							</li>
						`;
					} else {
						// if plugin is outside of search scope, keep current value in case of saving (only user-enabled is needed)
						container.innerHTML += App.FormFields.checkbox_tag("plugins[]", plugin.user_enabled, plugin.name, {style: 'display : none'});
					}
			});

			if (results_rendered === 0) {
				container.innerHTML += `<li class='text-center text-info'>${__("Could not find any plugins for this search query.")}</li>`;
			}

			dojo.parser.parse(container);

		},
		clearData: function(name) {
			if (confirm(__("Clear stored data for %s?").replace("%s", name))) {
				Notify.progress("Loading, please wait...");

				xhr.post("backend.php", {op: "Pref_Prefs", method: "clearPluginData", name: name}, () => {
					Helpers.Prefs.refresh();
				});
			}
		},
		uninstall: function(plugin) {
			const msg = __("Uninstall plugin %s?").replace("%s", plugin);

			if (confirm(msg)) {
				Notify.progress("Loading, please wait...");

				xhr.json("backend.php", {op: "Pref_Prefs", method: "uninstallPlugin", plugin: plugin}, (reply) => {
					if (reply?.status === true)
						Helpers.Plugins.reload();
					else {
						Notify.error("Plugin uninstallation failed.");
					}
				});

			}
		},
		install: function() {
			const dialog = new fox.SingleUseDialog({
				PI_RES_ALREADY_INSTALLED: "PI_RES_ALREADY_INSTALLED",
				PI_RES_SUCCESS: "PI_RES_SUCCESS",
				PI_ERR_NO_CLASS: "PI_ERR_NO_CLASS",
				PI_ERR_NO_INIT_PHP: "PI_ERR_NO_INIT_PHP",
				PI_ERR_EXEC_FAILED: "PI_ERR_EXEC_FAILED",
				PI_ERR_NO_TEMPDIR: "PI_ERR_NO_TEMPDIR",
				PI_ERR_PLUGIN_NOT_FOUND: "PI_ERR_PLUGIN_NOT_FOUND",
				PI_ERR_NO_WORKDIR: "PI_ERR_NO_WORKDIR",
				title: __("Available plugins"),
				need_refresh: false,
				entries: false,
				search_query: "",
				installed_plugins: [],
				onHide: function() {
					if (this.need_refresh) {
						Helpers.Prefs.refresh();
					}
				},
				performInstall: function(plugin) {

					const install_dialog = new fox.SingleUseDialog({
						title: __("Plugin installer"),
						content: `
						<ul class="panel panel-scrollable contents">
						<li class='text-center'>${__("Installing %s, please wait...").replace("%s", plugin)}</li>
						</ul>

						<footer class='text-center'>
							${App.FormFields.submit_tag(__("Close this window"))}
						</footer>`
					});

					const tmph = dojo.connect(install_dialog, 'onShow', function () {
						dojo.disconnect(tmph);

						const container = install_dialog.domNode.querySelector(".contents");

						xhr.json("backend.php", {op: "Pref_Prefs", method: "installPlugin", plugin: plugin}, (reply) => {
							if (!reply) {
								container.innerHTML = `<li class='text-center text-error'>${__("Operation failed: check event log.")}</li>`;
							} else {
								switch (reply.result) {
									case dialog.PI_RES_SUCCESS:
										container.innerHTML = `<li class='text-success text-center'>${__("Plugin has been installed.")}</li>`
										dialog.need_refresh = true;
										break;
									case dialog.PI_RES_ALREADY_INSTALLED:
										container.innerHTML =  `<li class='text-success text-center'>${__("Plugin is already installed.")}</li>`
										break;
									default:
										container.innerHTML = `
											<li>
												<h3 style="margin-top: 0">${plugin}</h3>
												<div class='text-error'>${reply.result}</div>
												${reply.stderr ? `<pre class="small text-error pre-wrap">${reply.stderr}</pre>` : ''}
												${reply.stdout ? `<pre class="small text-success pre-wrap">${reply.stdout}</pre>` : ''}
												<p class="small">
													${App.FormFields.icon("error_outline") + " " + __("Exited with RC: %d").replace("%d", reply.git_status)}
												</p>
											</li>
										`;
								}
							}
						});
					});

					install_dialog.show();

				},
				search: function() {
					this.search_query = this.attr('value').search.toLowerCase();

					window.requestIdleCallback(() => {
						this.render_contents();
					});

				},
				render_contents: function() {
					const container = dialog.domNode.querySelector(".contents");

					if (!dialog.entries) {
						container.innerHTML = `<li class='text-center text-error'>${__("Operation failed: check event log.")}</li>`;
					} else {
						container.innerHTML = "";

						let results_rendered = 0;

						const search_tokens = dialog.search_query
							.split(/ {1,}/)
							.filter((stoken) => (stoken.length > 0 ? stoken : null));

						dialog.entries.forEach((plugin) => {
							const is_installed = (dialog.installed_plugins
								.filter((p) => plugin.topics.map((t) => t.replace(/-/g, "_")).includes(p))).length > 0;

							if (search_tokens.length === 0 ||
									Object.values(plugin).filter((pval) =>
										search_tokens.filter((stoken) =>
											(pval.indexOf(stoken) !== -1 ? stoken : null)
										).length === search_tokens.length).length > 0) {

								++results_rendered;

								container.innerHTML += `
									<li data-row-value="${App.escapeHtml(plugin.name)}" class="${is_installed ? "plugin-installed" : ""}">
										${App.FormFields.button_tag((is_installed ?
												App.FormFields.icon("check") + " " +__("Already installed") :
												App.FormFields.icon("file_download") + " " +__('Install')), "", {class: 'alt-primary pull-right',
											disabled: is_installed,
											onclick: `App.dialogOf(this).performInstall(${JSON.stringify(plugin.name)})`})}

										<h3>${plugin.name}
											<a target="_blank" rel="noopener noreferrer" href="${App.escapeHtml(App.sanitizeUrl(plugin.html_url))}">
												${App.FormFields.icon("open_in_new_window")}
											</a>
										</h3>

										<div class='small text-muted'>${__("Updated: %s").replace("%s", plugin.last_update)}</div>

										<div class='description'>${plugin.description}</div>
									</li>
									`
							}
						});

						if (results_rendered === 0) {
							container.innerHTML = `<li class='text-center text-info'>${__("Could not find any plugins for this search query.")}</li>`;
						}

						dojo.parser.parse(container);
					}
				},
				reload: function() {
					const container = dialog.domNode.querySelector(".contents");
					container.innerHTML = `<li class='text-center'>${__("Looking for plugins...")}</li>`;

					xhr.json("backend.php", {op: "Pref_Prefs", method: "getAvailablePlugins"}, (reply) => {
						dialog.entries = reply;
						dialog.render_contents();
					});
				},
				content: `
					<div dojoType='fox.Toolbar'>
						<div class='pull-right'>
							<input name="search" placeholder="${__("Search...")}" type="search" dojoType="dijit.form.TextBox" onkeyup="App.dialogOf(this).search()">
						</div>
						<div style='height : 16px'>&nbsp;</div> <!-- disgusting -->
					</div>

					<ul style='clear : both' class="panel panel-scrollable-400px contents plugin-installer-list"> </ul>

					<footer>
						${App.FormFields.button_tag(App.FormFields.icon("refresh") + " " +__("Refresh"), "", {class: 'alt-primary', onclick: 'App.dialogOf(this).reload()'})}
						${App.FormFields.cancel_dialog_tag(__("Close"))}
					</footer>
				`,
			});

			const tmph = dojo.connect(dialog, 'onShow', function () {
				dojo.disconnect(tmph);

				dialog.installed_plugins = [...document.querySelectorAll('*[data-plugin-name]')].map((p) => p.getAttribute('data-plugin-name'));

				dialog.reload();
			});

			dialog.show();
		},
		update: function(name = null) {

			const dialog = new fox.SingleUseDialog({
				title: __("Update plugins"),
				need_refresh: false,
				plugins_to_update: [],
				plugins_to_check: [],
				onHide: function() {
					if (this.need_refresh) {
						Helpers.Prefs.refresh();
					}
				},
				performUpdate: function() {
					const container = dialog.domNode.querySelector(".update-results");

					dialog.attr('title', __('Updating...'));

					container.innerHTML = `<li class='text-center'>${__("Updating, please wait...")}</li>`;
					let enable_update_btn = false;

					xhr.json("backend.php", {op: "Pref_Prefs", method: "updateLocalPlugins", plugins: dialog.plugins_to_update.join(",")}, (reply) => {

						if (!reply) {
							container.innerHTML = `<li class='text-center text-error'>${__("Operation failed: check event log.")}</li>`;
						} else {
							container.innerHTML = "";

							reply.forEach((p) => {
								if (p.rv.git_status === 0)
									dialog.need_refresh = true;
								else
									enable_update_btn = true;

								container.innerHTML +=
								`
								<li>
									<h3>${p.plugin}</h3>
									${p.rv.stderr ? `<pre class="small text-error pre-wrap">${p.rv.stderr}</pre>` : ''}
									${p.rv.stdout ? `<pre class="small text-success pre-wrap">${p.rv.stdout}</pre>` : ''}
									<div class="small">
										${p.rv.git_status ? App.FormFields.icon("error_outline") + " " + __("Exited with RC: %d").replace("%d", p.rv.git_status) :
											App.FormFields.icon("check") + " " + __("Update done.")}
									</div>
								</li>
								`
							});
						}

						dialog.attr('title', __('Updates complete'));
						dijit.getEnclosingWidget(dialog.domNode.querySelector(".update-btn")).attr('disabled', !enable_update_btn);
					});
				},
				checkNextPlugin: function() {
					const name = dialog.plugins_to_check.shift();

					if (name) {
						this.checkUpdates(name);
					} else {
						const num_updated = dialog.plugins_to_update.length;

						if (num_updated > 0)
							dialog.attr('title',
								App.l10n.ngettext('Updates pending for %d plugin', 'Updates pending for %d plugins', num_updated)
									.replace("%d", num_updated));
						else
							dialog.attr('title', __("No updates available"));

						dijit.getEnclosingWidget(dialog.domNode.querySelector(".update-btn"))
									.attr('disabled', num_updated === 0);

					}
				},
				checkUpdates: function(name) {
					const container = dialog.domNode.querySelector(".update-results");

					dialog.attr('title', __("Checking: %s").replace("%s", name));

					//container.innerHTML = `<li class='text-center'>${__("Checking: %s...").replace("%s", name)}</li>`;

					xhr.json("backend.php", {op: "Pref_Prefs", method: "checkForPluginUpdates", name: name}, (reply) => {

						if (!reply) {
							container.innerHTML += `<li class='text-error'>${__("%s: Operation failed: check event log.").replace("%s", name)}</li>`;
						} else {

							reply.forEach((p) => {
								if (p.rv) {
									if (p.rv.need_update) {
										dialog.plugins_to_update.push(p.plugin);

										const update_button = dijit.getEnclosingWidget(
											document.querySelector(`*[data-update-btn-for-plugin="${p.plugin}"]`));

										if (update_button)
											update_button.domNode.show();
									}

									if (p.rv.need_update || p.rv.git_status !== 0) {
										container.innerHTML +=
										`
										<li><h3>${p.plugin}</h3>
											${p.rv.stderr ? `<pre class="small text-error pre-wrap">${p.rv.stderr}</pre>` : ''}
											${p.rv.stdout ? `<pre class="small text-success pre-wrap">${p.rv.stdout}</pre>` : ''}
											<div class="small">
												${p.rv.git_status ? App.FormFields.icon("error_outline") + " " + __("Exited with RC: %d").replace("%d", p.rv.git_status) :
													App.FormFields.icon("check") + " " + __("Ready to update")}
											</div>
										</li>
										`
									}
								}
								dialog.checkNextPlugin();
							});
						}

					});

				},
				content: `
					<ul class="panel panel-scrollable plugin-updater-list update-results">
					</ul>

					<footer>
						${App.FormFields.button_tag(App.FormFields.icon("update") + " " + __("Update"), "", {disabled: true, class: "update-btn alt-primary", onclick: "App.dialogOf(this).performUpdate()"})}
						${App.FormFields.cancel_dialog_tag(__("Close"))}
					</footer>
				`,
			});

			const tmph = dojo.connect(dialog, 'onShow', function () {
				dojo.disconnect(tmph);

				dialog.plugins_to_update = [];

				if (name) {
					dialog.checkUpdates(name);
				} else {
					dialog.plugins_to_check = [...document.querySelectorAll('*[data-plugin-name][data-plugin-local=true]')].map((p) => p.getAttribute('data-plugin-name'));
					dialog.checkNextPlugin();
				}
			});

			dialog.show();
		},
	},
	OPML: {
		import: function() {
			const opml_file = document.getElementById("opml_file");

			if (opml_file.value.length === 0) {
				alert(__("Please choose an OPML file first."));
				return false;
			} else {
				Notify.progress("Importing, please wait...", true);

				const xhr = new XMLHttpRequest();

				xhr.open( 'POST', 'backend.php', true );
				xhr.onload = function () {
					Notify.close();

					const dialog = new fox.SingleUseDialog({
						title: __("OPML Import"),
						onCancel: function () {
							this.execute();
						},
						execute: function () {
							const tree = dijit.byId('feedTree');

							if (tree) tree.reload();
						},
						content: `
							<div class='alert alert-info'>
								${__("If you have imported labels and/or filters, you might need to reload preferences to see your new data.")}
							</div>
							<div class='panel panel-scrollable'>
								${xhr.responseText}
							</div>
							<footer class='text-center'>
								<button dojoType='dijit.form.Button' type='submit' class='alt-primary'>
									${__('Close this window')}
								</button>
							</footer>
						`
					});

					dialog.show();
				};

				xhr.send(new FormData(document.getElementById("opml_import_form")));

				return false;
			}
		},
		export: function() {
			window.open("backend.php?op=OPML&method=export&" + dojo.formToQuery("opmlExportForm"));
		},
	}
};

// Expose to global scope for Dojo widget onclick handlers (needed since Dojo 1.17.3)
window.Helpers = Helpers;
