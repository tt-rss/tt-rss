'use strict'

/* global __, Notify, App, Feeds, xhr, Tables, fox */

/* exported CommonDialogs */
const	CommonDialogs = {
		closeInfoBox: function() {
			const dialog = dijit.byId("infoBox");
			if (dialog)	dialog.hide();
		},
		safeModeWarning: function() {
			const dialog = new fox.SingleUseDialog({
				title: __("Safe mode"),
				content: `<div class='alert alert-info'>
						${__('Tiny Tiny RSS is running in safe mode. All themes and plugins are disabled. You will need to log out and back in to disable it.')}
					</div>
					<footer class='text-center'>
						<button dojoType='dijit.form.Button' type='submit' class='alt-primary'>
							${__('Close this window')}
						</button>
					</footer>`
			});

			dialog.show();
		},
		subscribeToFeed: function() {
			xhr.json("backend.php",
					{op: "Feeds", method: "subscribeToFeed"},
					(reply) => {
						const dialog = new fox.SingleUseDialog({
							title: __("Subscribe to feed"),
							content: `
								<form onsubmit='return false'>

									${App.FormFields.hidden_tag("op", "Feeds")}
									${App.FormFields.hidden_tag("method", "add")}

									<div id='fadd_error_message' style='display : none' class='alert alert-danger'></div>

									<div id='fadd_multiple_notify' style='display : none'>
										<div class='alert alert-info'>
											${__("Provided URL is a HTML page referencing multiple feeds, please select required feed from the dropdown menu below.")}
										</div>
									</div>

									<section>
										<fieldset>
											<div class='pull-right'><img style='display : none' id='feed_add_spinner' src='${App.getInitParam('icon_oval')}'></div>
											<input style='font-size : 16px; width : 500px;'
												placeHolder="${__("Feed or site URL")}"
												dojoType='dijit.form.ValidationTextBox'
												required='1' name='feed' id='feedDlg_feedUrl'>
										</fieldset>

										${App.getInitParam('enable_feed_cats') ?
										`
											<fieldset>
												<label>${__('Place in category:')}</label>
												${reply.cat_select}
											</fieldset>
										` : ''}

										<fieldset>
											<label>${__("Update interval:")}</label>
											${App.FormFields.select_hash("update_interval", 0, reply.intervals.update)}
										</fieldset>
									</section>

									<div id="feedDlg_feedsContainer" style="display : none">
										<header>${__('Available feeds')}</header>
										<section>
											<fieldset>
												<select id="feedDlg_feedContainerSelect"
													dojoType="fox.form.Select" size="3">
													<script type="dojo/method" event="onChange" args="value">
														dijit.byId("feedDlg_feedUrl").attr("value", value);
													</script>
												</select>
											</fieldset>
										</section>
									</div>

									<div id='feedDlg_loginContainer' style='display : none'>
										<section>
											<fieldset>
												<input dojoType="dijit.form.TextBox" name='login'"
													placeHolder="${__("Login")}"
													autocomplete="new-password"
													style="width : 10em;">
												<input
													placeHolder="${__("Password")}"
													dojoType="dijit.form.TextBox" type='password'
													autocomplete="new-password"
													style="width : 10em;" name='pass'">
											</fieldset>
										</section>
									</div>

									<section>
										<label class='checkbox'>
											<input type='checkbox' name='need_auth' dojoType='dijit.form.CheckBox' id='feedDlg_loginCheck'
														onclick='App.displayIfChecked(this, "feedDlg_loginContainer")'>
													${__('This feed requires authentication.')}
										</label>
									</section>

									<footer>
										<button dojoType='dijit.form.Button' class='alt-primary' type='submit'
											onclick='App.dialogOf(this).execute()'>
											${__('Subscribe')}
										</button>
										<button dojoType='dijit.form.Button' onclick='App.dialogOf(this).hide()'>
											${__('Cancel')}
										</button>
									</footer>
								</form>
							`,
							show_error: function (msg, additional_info) {
								const elem = document.getElementById("fadd_error_message");

								elem.innerHTML = `${msg}${additional_info ? `<br><br><h4>${__('Additional information')}</h4>${additional_info}` : ''}`;

								Element.show(elem);
							},
							execute: function () {
								if (this.validate()) {
									const feed_url = this.attr('value').feed;

									Element.show("feed_add_spinner");
									Element.hide("fadd_error_message");

									xhr.json("backend.php", this.attr('value'), (reply) => {
										try {

											if (!reply) {
												Element.hide("feed_add_spinner");
												alert(__("Failed to parse output. This can indicate server timeout and/or network issues. Backend output was logged to browser console."));
												return;
											}

											const rc = reply['result'];

											Notify.close();
											Element.hide("feed_add_spinner");

											switch (parseInt(rc['code'])) {
												case 0:
													dialog.show_error(__("You are already subscribed to this feed."));
													break;
												case 1:
													dialog.hide();
													Notify.info(__("Subscribed to %s").replace("%s", feed_url));

													if (App.isPrefs())
														dijit.byId("feedTree").reload();
													else
														Feeds.reload();

													break;
												case 2:
													dialog.show_error(__("Specified URL seems to be invalid."));
													break;
												case 3:
													dialog.show_error(__("Specified URL doesn't seem to contain any feeds."), App.escapeHtml(rc['message']));
													break;
												case 4:
													{
														const feeds = rc['feeds'];

														Element.show("fadd_multiple_notify");

														const select = dijit.byId("feedDlg_feedContainerSelect");

														while (select.getOptions().length > 0)
															select.removeOption(0);

														select.addOption({value: '', label: __("Expand to select feed")});

														for (const feedUrl in feeds) {
															if (Object.prototype.hasOwnProperty.call(feeds, feedUrl)) {
																select.addOption({value: feedUrl, label: feeds[feedUrl]});
															}
														}

														Element.show('feedDlg_feedsContainer');
													}
													break;
												case 5:
													dialog.show_error(__("Couldn't download the specified URL."), App.escapeHtml(rc['message']));
													break;
												case 6:
													dialog.show_error(__("Invalid content."), App.escapeHtml(rc['message']));
													break;
												case 7:
													dialog.show_error(__("Error while creating feed database entry."));
													break;
												case 8:
													dialog.show_error(__("You are not allowed to perform this operation."));
													break;
											}

										} catch (e) {
											console.error(reply);
											App.Error.report(e);
										}
									});
								}
							},
						});

						dialog.show();
					});
		},
		showFeedsWithErrors: function() {

			xhr.json("backend.php", {op: "Pref_Feeds", method: "feedsWithErrors"}, (reply) => {

				const dialog = new fox.SingleUseDialog({
					id: "errorFeedsDlg",
					title: __("Feeds with update errors"),
					getSelectedFeeds: function () {
						return Tables.getSelected("error-feeds-list");
					},
					removeSelected: function () {
						const sel_rows = this.getSelectedFeeds();

						if (sel_rows.length > 0) {
							if (confirm(__("Remove selected feeds?"))) {
								Notify.progress("Removing selected feeds...", true);

								const query = {
									op: "Pref_Feeds", method: "remove",
									ids: sel_rows.toString()
								};

								xhr.post("backend.php", query, () => {
									Notify.close();
									dialog.hide();

									if (App.isPrefs())
										dijit.byId("feedTree").reload();
									else
										Feeds.reload();

								});
							}

						} else {
							alert(__("No feeds selected."));
						}
					},
					debugSelected: function() {
						const sel_rows = this.getSelectedFeeds();

						if (sel_rows.length > 0) {
							if (confirm(__("Debug selected feeds?"))) {
								Notify.progress("Opening debugger for selected feeds...", true);

								for (let i = 0; i < sel_rows.length; i++) {
									/* global __csrf_token */
									App.postOpenWindow("backend.php", {
										op: "Feeds",
										method: "updatedebugger",
										feed_id: sel_rows[i],
										csrf_token: __csrf_token,
									});
								}
							}
						} else {
							alert(__("No feeds selected."));
						}
					},
					content: `
						<div dojoType="fox.Toolbar">
							<div dojoType="fox.form.DropDownButton">
								<span>${__('Select')}</span>
								<div dojoType="dijit.Menu" style="display: none">
									<div onclick="Tables.select('error-feeds-list', true)"
										dojoType="dijit.MenuItem">${__('All')}</div>
									<div onclick="Tables.select('error-feeds-list', false)"
										dojoType="dijit.MenuItem">${__('None')}</div>
								</div>
							</div>
						</div>

						<div class='panel panel-scrollable'>
							<table width='100%' id='error-feeds-list'>

							${reply.map((row) => `
								<tr data-row-id='${row.id}' style='vertical-align: top'>
									<td class='checkbox'>
										<input onclick='Tables.onRowChecked(this)' dojoType="dijit.form.CheckBox"
											type="checkbox">
									</td>
									<td>
										<a href="#" title="${__("Click to edit feed")}" onclick="CommonDialogs.editFeed(${row.id})">
											${App.escapeHtml(row.title)}
										</a>
									</td>
									<td class='text-muted small' align='right' width='50%'>
											${App.escapeHtml(row.last_error)}
									</td>
									</tr>
							`).join("")}
							</table>
						</div>

						<footer>
							<button style='float : left' class='alt-danger' dojoType='dijit.form.Button' onclick='App.dialogOf(this).removeSelected()'>
								${__('Unsubscribe from selected feeds')}
							</button>
							<button style='float : left' class='alt-info' dojoType='dijit.form.Button' onclick='App.dialogOf(this).debugSelected()'>
								${__('Debug selected feeds')}
							</button>
							<button dojoType='dijit.form.Button' class='alt-primary' type='submit'>
								${__('Close this window')}
							</button>
						</footer>
					`
				});

				dialog.show();
			})
		},
		addLabel: function() {
			const caption = prompt(__("Please enter label caption:"), "");

			if (caption !== undefined && caption.trim().length > 0) {

				const query = {op: "Pref_Labels", method: "add", caption: caption.trim()};

				Notify.progress("Loading, please wait...", true);

				xhr.post("backend.php", query, () => {
					if (dijit.byId("labelTree")) {
						dijit.byId("labelTree").reload();
					} else {
						Feeds.reload();
					}
				});
			}
		},
		unsubscribeFeed: function(feed_id, title) {

			const msg = __("Unsubscribe from %s?").replace("%s", title);

			if (typeof title === "undefined" || confirm(msg)) {
				Notify.progress("Removing feed...");

				const query = {op: "Pref_Feeds", quiet: 1, method: "remove", ids: feed_id};

				xhr.post("backend.php", query, () => {
					if (App.isPrefs()) {
						dijit.byId("feedTree").reload();
					} else {
						if (feed_id === Feeds.getActive())
							setTimeout(() => {
									Feeds.openDefaultFeed();
								},
								100);

						if (feed_id < 0) Feeds.reload();
					}
				});
			}

			return false;
		},
		editFeed: function (feed_id) {
			if (feed_id <= 0)
				return alert(__("You can't edit this kind of feed."));

			const dialog = new fox.SingleUseDialog({
				id: "feedEditDlg",
				title: __("Edit feed"),
				feed_title: "",
				E_ICON_FILE_TOO_LARGE: 'E_ICON_FILE_TOO_LARGE',
				E_ICON_RENAME_FAILED: 'E_ICON_RENAME_FAILED',
				E_ICON_UPLOAD_FAILED: 'E_ICON_UPLOAD_FAILED',
				E_ICON_UPLOAD_SUCCESS: 'E_ICON_UPLOAD_SUCCESS',
				unsubscribe: function() {
					if (confirm(__("Unsubscribe from %s?").replace("%s", this.feed_title))) {
						dialog.hide();
						CommonDialogs.unsubscribeFeed(feed_id);
               }
				},
				uploadIcon: function(input) {
					if (input.files.length !== 0) {
						const icon_file = input.files[0];

						if (icon_file.type.indexOf("image/") === -1) {
							alert(__("Please select an image file."));
							return;
						}

						const fd = new FormData();
						fd.append('icon_file', icon_file)
						fd.append('feed_id', feed_id);
						fd.append('op', 'Pref_Feeds');
						fd.append('method', 'uploadIcon');
						fd.append('csrf_token', App.getInitParam("csrf_token"));

						const xhr = new XMLHttpRequest();

						xhr.open( 'POST', 'backend.php', true );
						xhr.onload = function () {
							const ret = JSON.parse(this.responseText);

							// TODO: make a notice box within panel content
							switch (ret.rc) {
								case dialog.E_ICON_FILE_TOO_LARGE:
									alert(__("Icon file is too large."));
									break;
								case dialog.E_ICON_UPLOAD_FAILED:
									alert(__("Upload failed."));
									break;
								case dialog.E_ICON_UPLOAD_SUCCESS:
									{
										if (App.isPrefs())
											dijit.byId("feedTree").reload();
										else
											Feeds.reload();

										const icon = dialog.domNode.querySelector(".feedIcon");

										if (icon) {
											icon.src = ret.icon_url;
											icon.show();
										}

										input.value = "";
									}
									break;
								default:
									alert(this.responseText);
									break;
							}
						};

						xhr.send(fd);

					}
				},
				removeIcon: function(id) {
					if (confirm(__("Remove stored feed icon?"))) {
						Notify.progress("Removing feed icon...", true);

						xhr.post("backend.php", {op: "Pref_Feeds", method: "removeicon", feed_id: id}, () => {
							Notify.info("Feed icon removed.");

							if (App.isPrefs())
								dijit.byId("feedTree").reload();
							else
								Feeds.reload();

							const icon = dialog.domNode.querySelector(".feedIcon");

							if (icon) {
								icon.src = "";
								icon.hide();
							}
						});
					}

					return false;
				},
				execute: function () {
					if (this.validate()) {
						Notify.progress("Saving data...", true);

						xhr.post("backend.php", dialog.attr('value'), () => {
							dialog.hide();
							Notify.close();

							if (App.isPrefs())
								dijit.byId("feedTree") && dijit.byId("feedTree").reload();
							else
								Feeds.reload();

						});
						return true;
					}
					return false;
				},
				content: __("Loading, please wait...")
			});

			const tmph = dojo.connect(dialog, 'onShow', function () {
				dojo.disconnect(tmph);

				xhr.json("backend.php", {op: "Pref_Feeds", method: "editfeed", id: feed_id}, (reply) => {
					const feed = reply.feed;
					const is_readonly = reply.user.access_level === App.UserAccessLevels.ACCESS_LEVEL_READONLY;

					// for unsub prompt
					dialog.feed_title = feed.title;

					// options tab
					const options = {
						include_in_digest: [ feed.include_in_digest, __('Include in email digest') ],
						always_display_enclosures: [ feed.always_display_enclosures, __('Always display image attachments') ],
						hide_images: [ feed.hide_images, __('Do not embed media') ],
						cache_images: [ feed.cache_images, __('Cache media') ],
						mark_unread_on_update: [ feed.mark_unread_on_update, __('Mark updated articles as unread') ]
					};

					dialog.attr('content',
					`
					<form onsubmit="return false">
						<div dojoType="dijit.layout.TabContainer" style="height : 450px">
							<div dojoType="dijit.layout.ContentPane" title="${__('General')}">

								${App.FormFields.hidden_tag("id", feed_id)}
								${App.FormFields.hidden_tag("op", "Pref_Feeds")}
								${App.FormFields.hidden_tag("method", "editSave")}

								<section>
									<fieldset>
										<input dojoType='dijit.form.ValidationTextBox' required='1'
											placeHolder="${__("Feed title")}"
											style='font-size : 16px; width: 530px' name='title' value="${App.escapeHtml(feed.title)}">
									</fieldset>

									<fieldset>
										<label>${__('URL:')}</label>
										<input dojoType='dijit.form.ValidationTextBox' required='1'
											placeHolder="${__("Feed URL")}"
											regExp='^(http|https)://.*' style='width : 300px'
											name='feed_url' value="${App.escapeHtml(feed.feed_url)}">

										${feed.last_error ?
											`<i class="material-icons"
												title="${App.escapeHtml(feed.last_error)}">error</i>
											` : ""}
									</fieldset>

									${reply.cats.enabled ?
										`
										<fieldset>
											<label>${__('Place in category:')}</label>
											${reply.cats.select}
										</fieldset>
										` : ""}

									<fieldset>
										<label>${__('Site URL:')}</label>
										<input dojoType='dijit.form.ValidationTextBox' required='1'
											placeHolder="${__("Site URL")}"
											regExp='^(http|https)://.*' style='width : 300px'
											name='site_url' value="${App.escapeHtml(feed.site_url)}">
									</fieldset>

									${reply.lang.enabled ?
										`
										<fieldset>
											<label>${__('Language:')}</label>
											${App.FormFields.select_tag("feed_language",
												feed.feed_language ? feed.feed_language : reply.lang.default,
												reply.lang.all)}
										</fieldset>
										` : ""}

									<hr/>

									<fieldset>
										<label>${__("Update interval:")}</label>
										${App.FormFields.select_hash("update_interval", is_readonly ? -1 : feed.update_interval,
											reply.intervals.update,
											{disabled: is_readonly})}
									</fieldset>
									<fieldset>
										<label>${__('Article purging:')}</label>

										${App.FormFields.select_hash("purge_interval",
																	feed.purge_interval,
																	reply.intervals.purge,
																	reply.force_purge ? {disabled: 1} : {})}

									</fieldset>
								</section>
							</div>
							<div dojoType="dijit.layout.ContentPane" title="${__('Authentication')}">
								<section>
									<fieldset>
										<label>${__("Login:")}</label>
										<input dojoType='dijit.form.TextBox'
											autocomplete='new-password'
											name='auth_login' value="${App.escapeHtml(feed.auth_login)}">
									</fieldset>
									<fieldset>
									<label>${__("Password:")}</label>
										<input dojoType='dijit.form.TextBox' type='password' name='auth_pass'
											autocomplete='new-password'
											value="${App.escapeHtml(feed.auth_pass)}">
									</fieldset>
								</section>
							</div>
							<div dojoType="dijit.layout.ContentPane" title="${__('Options')}">
								<section class="narrow">
									${Object.keys(options).map((name) =>
										`
											<fieldset class='narrow'>
												<label class="checkbox">
													${App.FormFields.checkbox_tag(name, options[name][0])}
													${options[name][1]}
												</label>
											</fieldset>
										`).join("")}
								</section>
							</div>
							<div dojoType="dijit.layout.ContentPane" title="${__('Icon')}">
								<div><img class='feedIcon' style="${feed.icon ? "" : "display : none"}" src="${feed.icon ? App.escapeHtml(feed.icon) : ""}"></div>

								<label class="dijitButton">
									${App.FormFields.icon("file_upload")}
									${__("Upload new icon...")}
									<input style="display: none" type="file" onchange="App.dialogOf(this).uploadIcon(this)">
								</label>

								${App.FormFields.button_tag(App.FormFields.icon("delete") + " " + __("Remove"), "", {class: "alt-danger", onclick: "App.dialogOf(this).removeIcon("+feed_id+")"})}
							</div>
							<div dojoType="dijit.layout.ContentPane" title="${__('Plugins')}">
								${reply.plugin_data}
							</div>
						</div>
						<footer>
							${App.FormFields.button_tag(App.FormFields.icon("delete") + " " + __("Unsubscribe"), "", {class: "pull-left alt-danger", onclick: "App.dialogOf(this).unsubscribe()"})}
							${App.FormFields.submit_tag(App.FormFields.icon("save") + " " + __("Save"), {onclick: "App.dialogOf(this).execute()"})}
							${App.FormFields.cancel_dialog_tag(__("Cancel"))}
						</footer>
					</form>
					`);
				})
			});

			dialog.show();
		},
		generatedFeed: function(feed, is_cat, search = "") {

			Notify.progress("Loading, please wait...", true);

			xhr.json("backend.php", {op: "Pref_Feeds", method: "getsharedurl", id: feed, is_cat: is_cat, search: search}, (reply) => {
				try {
					const dialog = new fox.SingleUseDialog({
						title: __("Show as feed"),
						regenFeedKey: function(feed, is_cat) {
							if (confirm(__("Generate new syndication address for this feed?"))) {

								Notify.progress("Trying to change address...", true);

								const query = {op: "Pref_Feeds", method: "regenFeedKey", id: feed, is_cat: is_cat};

								xhr.json("backend.php", query, (reply) => {
									const new_link = reply.link;
									const target = this.domNode.querySelector(".generated_url");

									if (new_link && target) {
										target.innerHTML = target.innerHTML.replace(/&amp;key=.*$/,
											"&amp;key=" + new_link);

										target.href = target.href.replace(/&key=.*$/,
											"&key=" + new_link);

										Notify.close();

									} else {
										Notify.error("Could not change feed URL.");
									}
								});
							}
							return false;
						},
						content: `
							<header>${__("%s can be accessed via the following secret URL:").replace("%s", App.escapeHtml(reply.title))}</header>
							<section>
								<div class='panel text-center'>
									<a class='generated_url' href="${App.escapeHtml(reply.link)}" target='_blank'>${App.escapeHtml(reply.link)}</a>
								</div>
							</section>
							<footer>
								<button dojoType='dijit.form.Button' style='float : left' class='alt-info'
									onclick='window.open("https://github.com/tt-rss/tt-rss/wiki/Generated-Feeds")'>
									<i class='material-icons'>help</i> ${__("More info...")}</button>
								<button dojoType='dijit.form.Button' onclick='return App.dialogOf(this).regenFeedKey(${JSON.stringify(feed)}, ${JSON.stringify(is_cat)})'>
									${App.FormFields.icon("refresh")}
									${__('Generate new URL')}
								</button>
								<button dojoType='dijit.form.Button' class='alt-primary' type='submit'>
									${__('Close this window')}
								</button>
							</footer>
						`
					});

					dialog.show();

					Notify.close();

				} catch (e) {
					App.Error.report(e);
				}
			});
		},
	};

// Expose to global scope for Dojo widget onclick handlers (needed since Dojo 1.17.3)
window.CommonDialogs = CommonDialogs;
