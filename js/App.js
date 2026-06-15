'use strict';

/* global require, __, Article, Headlines, Filters, fox */
/* global xhr, PluginHost, Notify, Feeds, Cookie */
/* global CommonDialogs, Plugins */

const App = {
   _initParams: [],
	_rpc_seq: 0,
	hotkey_prefix: 0,
	hotkey_prefix_pressed: false,
	hotkey_prefix_timeout: 0,
   global_unread: -1,
   _widescreen_mode: false,
   _overlay_stack: [],
   _suppress_popstate: 0,
   _suppress_reconcile: false,
   _loading_progress: 0,
   _night_mode_retry_timeout: false,
   hotkey_actions: {},
   is_prefs: false,
   LABEL_BASE_INDEX: -1024,
	UserAccessLevels: {
		ACCESS_LEVEL_READONLY: -1
	},
   _translations: {},
   Hash: {
      get: function() {
         return dojo.queryToObject(window.location.hash.substring(1));
      },
      set: function(params) {
         const obj = dojo.queryToObject(window.location.hash.substring(1));
         window.location.hash = dojo.objectToQuery({...obj, ...params});
      }
   },
   l10n: {
      ngettext: function(msg1, msg2, n) {
         return self.__((parseInt(n) > 1) ? msg2 : msg1);
      },
      __: function(msg) {
         return App._translations[msg] ? App._translations[msg] : msg;
      }
   },
   FormFields: {
      attributes_to_string: function(attributes) {
         return Object.keys(attributes).map((k) =>
            `${App.escapeHtml(k)}="${App.escapeHtml(attributes[k])}"`)
            .join(" ");
      },
      hidden_tag: function(name, value, attributes = {}, id = "") {
         return `<input id="${App.escapeHtml(id)}" dojoType="dijit.form.TextBox" ${this.attributes_to_string(attributes)}
            style="display : none" name="${name}" value="${App.escapeHtml(value)}"></input>`
      },
      // allow html inside because of icons
      button_tag: function(value, type, attributes = {}) {
         return `<button dojoType="dijit.form.Button" ${this.attributes_to_string(attributes)}
            type="${type}">${value}</button>`

      },
      icon: function(icon, attributes = {}) {
         return `<i class="material-icons" ${this.attributes_to_string(attributes)}>${icon}</i>`;
      },
      submit_tag: function(value, attributes = {}) {
         return this.button_tag(value, "submit", {...{class: "alt-primary"}, ...attributes});
      },
      cancel_dialog_tag: function(value, attributes = {}) {
         return this.button_tag(value, "", {...{onclick: "App.dialogOf(this).hide()"}, ...attributes});
      },
      checkbox_tag: function(name, checked = false, value = "", attributes = {}, id = "") {
         // checked !== '0' prevents mysql "boolean" false to be implicitly cast as true
         return `<input dojoType="dijit.form.CheckBox" type="checkbox" name="${App.escapeHtml(name)}"
                     ${checked !== '0' && checked ? "checked" : ""}
                     ${value ? `value="${App.escapeHtml(value)}"` : ""}
                     ${this.attributes_to_string(attributes)} id="${App.escapeHtml(id)}">`
      },
      select_tag: function(name, value, values = [], attributes = {}, id = "") {
         value = String(value);

         return `
            <select name="${name}" dojoType="fox.form.Select" id="${App.escapeHtml(id)}" ${this.attributes_to_string(attributes)}>
               ${values.map((v) => {
                  v = String(v);
                  return `<option ${v === value ? 'selected="selected"' : ''} value="${App.escapeHtml(v)}">${App.escapeHtml(v)}</option>`
               }).join("")}
            </select>
         `
      },
      select_hash: function(name, value, values = {}, attributes = {}, id = "", params = {}) {
			let keys = Object.keys(values);
			value = String(value);

			if (params.numeric_sort)
				keys = keys.sort((a,b) => a - b);

         return `
            <select name="${name}" dojoType="fox.form.Select" id="${App.escapeHtml(id)}" ${this.attributes_to_string(attributes)}>
               ${keys.map((vk) =>
                     `<option ${vk === value ? 'selected="selected"' : ''} value="${App.escapeHtml(vk)}">${App.escapeHtml(values[vk])}</option>`
               ).join("")}
            </select>
         `
      }
   },
   Scrollable: {
		scrollByPages: function (elem, page_offset) {
			if (!elem) return;

			/* keep a line or so from the previous page  */
			const offset = (elem.offsetHeight - (page_offset > 0 ? 50 : -50)) * page_offset;

			this.scroll(elem, offset);
		},
		scroll: function(elem, offset) {
			if (!elem) return;

			elem.scrollTop += offset;
		},
		isChildVisible: function(elem, ctr) {
			if (!elem) return;

			const ctop = ctr.scrollTop;
			const cbottom = ctop + ctr.offsetHeight;

			const etop = elem.offsetTop;
			const ebottom = etop + elem.offsetHeight;

			return etop >= ctop && ebottom <= cbottom ||
				etop < ctop && ebottom > ctop || ebottom > cbottom && etop < cbottom;
		},
		fitsInContainer: function (elem, ctr) {
			if (!elem) return;

			return elem.offsetTop + elem.offsetHeight <= ctr.scrollTop + ctr.offsetHeight &&
				elem.offsetTop >= ctr.scrollTop;
		},
      scrollTo: function (elem, ctr, params = {}) {
         const force_to_top = params.force_to_top || false;

         if (!elem || !ctr) return;

         if (force_to_top || !App.Scrollable.fitsInContainer(elem, ctr)) {
            ctr.scrollTop = elem.offsetTop;
         }
      }
   },
   /** @deprecated use document.getElementById */
   byId: function(id) {
      return document.getElementById(id);
   },
   /** @deprecated use document.querySelector */
   find: function(query) {
      return document.querySelector(query)
   },
   /** @deprecated use document.querySelectorAll */
   findAll: function(query) {
      return document.querySelectorAll(query);
   },
   dialogOf: function (elem) {

      // elem could be a Dijit widget
      elem = elem.domNode ? elem.domNode : elem;

      return dijit.getEnclosingWidget(elem.closest('.dijitDialog'));
   },
   getPhArgs(plugin, method, args = {}) {
      return {...{op: "PluginHandler", plugin: plugin, method: method}, ...args};
   },
   label_to_feed_id: function(label) {
      return this.LABEL_BASE_INDEX - 1 - Math.abs(label);
   },
   feed_to_label_id: function(feed) {
      return this.LABEL_BASE_INDEX - 1 + Math.abs(feed);
   },
   getInitParam: function(k) {
		return this._initParams[k];
	},
	setInitParam: function(k, v) {
		this._initParams[k] = v;
	},
	nightModeChanged: function(is_night, link, retry = 0) {
		if (link) {
			if (retry < 15) {
				window.clearTimeout(this._night_mode_retry_timeout);

				this._night_mode_retry_timeout = window.setTimeout(
					() => this.nightModeChanged(is_night, link, ++retry),
					3000);
			}

			xhr.post("backend.php", {op: "RPC", method: "getRuntimeInfo"}, () => {
				const css_override = is_night ? App.getInitParam("default_dark_theme") : App.getInitParam("default_light_theme");

				link.setAttribute('href', css_override);

				window.clearTimeout(this._night_mode_retry_timeout);
			});
		}
	},
	setupNightModeDetection: function(callback) {
		if (!document.getElementById("theme_css")) {
			const mql = window.matchMedia('(prefers-color-scheme: dark)');

			try {
				mql.addEventListener("change", () => {
					this.nightModeChanged(mql.matches, document.getElementById("theme_auto_css"));
				});
			} catch {
				console.warn("exception while trying to set MQL event listener");
			}

			const link = document.createElement("link");
         link.rel = "stylesheet";
         link.id = "theme_auto_css";

			if (callback) {
						link.onload = function() {
							document.body.classList.remove('css_loading');
							callback();
						};

						link.onerror = function() {
							alert("Fatal error while loading application stylesheet: " + link.getAttribute("href"));
						}
					}

			this.nightModeChanged(mql.matches, link);

			document.head.prepend(link);
		} else {
			document.body.classList.remove('css_loading');

			if (callback) callback();
		}
	},
   postCurrentWindow: function(target, params) {
      const form = document.createElement("form");

      form.setAttribute("method", "post");
      form.setAttribute("action", App.getInitParam("self_url_prefix") + "/" + target);

      for (const [k,v] of Object.entries(params)) {
         const field = document.createElement("input");

         field.setAttribute("name", k);
         field.setAttribute("value", v);
         field.setAttribute("type", "hidden");

         form.appendChild(field);
      }

      document.body.appendChild(form);

      form.submit();

      form.parentNode.removeChild(form);
   },
   postOpenWindow: function(target, params) {
      const w = window.open("");

		if (w) {
			w.opener = null;

			const form = document.createElement("form");

			form.setAttribute("method", "post");
			form.setAttribute("action", App.getInitParam("self_url_prefix") + "/" + target);

			for (const [k,v] of Object.entries(params)) {
				const field = document.createElement("input");

				field.setAttribute("name", k);
				field.setAttribute("value", v);
				field.setAttribute("type", "hidden");

				form.appendChild(field);
			}

			w.document.body.appendChild(form);
			form.submit();
		}

   },
	urlParam: function(name) {
		try {
         const results = new RegExp('[?&]' + name + '=([^&#]*)').exec(window.location.href);
         return decodeURIComponent(results[1].replace(/\+/g, " ")) || 0;
      } catch {
         return 0;
      }
	},
	next_seq: function() {
		this._rpc_seq += 1;
		return this._rpc_seq;
	},
	get_seq: function() {
		return this._rpc_seq;
	},
	setLoadingProgress: function(p) {
		this._loading_progress += p;

		if (dijit.byId("loading_bar"))
			dijit.byId("loading_bar").update({progress: this._loading_progress});

		if (this._loading_progress >= 90) {
			document.getElementById("overlay").hide();
		}

	},
	isCombinedMode: function() {
		return !!this.getInitParam("combined_display_mode");
	},
	setCombinedMode: function(combined) {
		const value = combined ? "true" : "false";

		xhr.post("backend.php", {op: "RPC", method: "setpref", key: "COMBINED_DISPLAY_MODE", value: value}, () => {
			this.setInitParam("combined_display_mode",
				!this.getInitParam("combined_display_mode"));

			Article.close();
			Headlines.renderAgain();
		})
	},
	isExpandedMode: function() {
		return !!this.getInitParam("cdm_expanded");
	},
	setExpandedMode: function(expand) {
		if (App.isCombinedMode()) {
			const value = expand ? "true" : "false";

			xhr.post("backend.php", {op: "RPC", method: "setpref", key: "CDM_EXPANDED", value: value}, () => {
				this.setInitParam("cdm_expanded", !this.getInitParam("cdm_expanded"));
				Headlines.renderAgain();
			});
		} else {
			alert(__("This function is only available in combined mode."));
		}
	},
	getActionByHotkeySequence: function(sequence) {
		const hotkeys_map = this.getInitParam("hotkeys");

		for (const seq in hotkeys_map[1]) {
			if (Object.prototype.hasOwnProperty.call(hotkeys_map[1], seq)) {
				if (seq === sequence) {
					return hotkeys_map[1][seq];
				}
			}
		}
	},
	keyeventToAction: function(event) {

		const hotkeys_map = this.getInitParam("hotkeys");
		const keycode = event.which;
		const keychar = String.fromCharCode(keycode);

		if (keycode === 27) { // escape and drop prefix
			this.hotkey_prefix = false;
		}

		if (!this.hotkey_prefix && hotkeys_map[0].indexOf(keychar) !== -1) {

			this.hotkey_prefix = keychar;
			document.getElementById("cmdline").innerHTML = keychar;
			Element.show("cmdline");

			window.clearTimeout(this.hotkey_prefix_timeout);
			this.hotkey_prefix_timeout = window.setTimeout(() => {
				this.hotkey_prefix = false;
				Element.hide("cmdline");
			}, 3 * 1000);

			event.stopPropagation();

			return false;
		}

		Element.hide("cmdline");

		let hotkey_name;

		if (event.type === 'keydown') {
			hotkey_name = "(" + keycode + ")";

			// ensure ^*char notation
			if (event.shiftKey) hotkey_name = "*" + hotkey_name;
			if (event.ctrlKey) hotkey_name = "^" + hotkey_name;
			if (event.altKey) hotkey_name = "+" + hotkey_name;
			if (event.metaKey) hotkey_name = "%" + hotkey_name;
		} else {
			hotkey_name = keychar ? keychar : "(" + keycode + ")";
		}

		let hotkey_full = this.hotkey_prefix ? this.hotkey_prefix + " " + hotkey_name : hotkey_name;
		this.hotkey_prefix = false;

		let action_name = this.getActionByHotkeySequence(hotkey_full);

		// check for mode-specific hotkey
		if (!action_name) {
			hotkey_full = (this.isCombinedMode() ? "{C}" : "{3}") + hotkey_full;

			action_name = this.getActionByHotkeySequence(hotkey_full);
		}

		return action_name;
	},
	cleanupMemory: function(root) {
		const dijits = dojo.query("[widgetid]", dijit.byId(root).domNode).map(dijit.byNode);

		dijits.forEach(function (d) {
			dojo.destroy(d.domNode);
		});

		document.querySelectorAll(`#${root} *`).forEach(function (i) {
			i.parentNode ? i.parentNode.removeChild(i) : true;
		});
   },
   // htmlspecialchars()-alike for headlines data-content attribute
   escapeHtml: function(p) {
      if (typeof p !== 'string')
         return p;

      const map = {
         '&': '&amp;',
         '<': '&lt;',
         '>': '&gt;',
         '"': '&quot;',
         "'": '&#x27;',
         '/': '&#x2F;',
      };

      return p.replace(/[&<>"'/]/g, m => map[m]);
   },
   /**
    * Sanitize a URL for safe use in href attributes and window.open()
    * @param {string} url - URL to sanitize
    * @param {string} fallback - Optional fallback value if URL is invalid (default: empty string)
    * @return {string} Safe URL or fallback
    */
   sanitizeUrl: function(url, fallback = '') {
      if (!url || typeof url !== 'string') return fallback;

      // Remove NULL bytes and other control characters
      // eslint-disable-next-line no-control-regex
      const cleaned = url.replace(/[\x00-\x1F\x7F]/g, '');

      const trimmed = cleaned.trim();
      if (!trimmed) return fallback;

      return /^https?:\/\/.+/i.test(trimmed) ? trimmed : fallback;
   },
   openUrl: function(url) {
      const sanitized = this.sanitizeUrl(url);
      if (sanitized) {
         const w = window.open(sanitized);
         w.opener = null;
      }
   },
   unescapeHtml: function(p) {
      if (typeof p !== 'string' || p.indexOf('&') === -1)
         return p;

      return p.replace(/&(?:amp|lt|gt|quot|#x27|#x2F|#039|#47);/g, function(entity) {
         switch (entity) {
            case '&amp;': return '&';
            case '&lt;': return '<';
            case '&gt;': return '>';
            case '&quot;': return '"';
            case '&#x27;': case '&#039;': return "'";
            case '&#x2F;': case '&#47;': return '/';
            default: return entity;
         }
      });
   },
   getSelectedText: function() {
      const sel = window.getSelection();
      return sel ? sel.toString().trim() : "";
   },
   displayIfChecked: function(checkbox, elemId) {
      if (checkbox.checked) {
         Element.show(elemId);
      } else {
         Element.hide(elemId);
      }
   },
   hotkeyHelp: function() {
      xhr.post("backend.php", {op: "RPC", method: "hotkeyHelp"}, (reply) => {
         const dialog = new fox.SingleUseDialog({
            title: __("Keyboard shortcuts"),
            content: reply,
         });

         dialog.show();
      });
   },
	handleRpcJson: function(reply) {

		const netalert = document.querySelector('.net-alert');

      if (reply) {
         const error = reply['error'];
         const seq = reply['seq'];
         const message = reply['message'];
         const counters = reply['counters'];
         const runtime_info = reply['runtime-info'];

         if (error && error.code && error.code !== App.Error.E_SUCCESS) {
            console.warn("handleRpcJson: fatal error", error);
            this.Error.fatal(error.code, error.params);
            return false;
         }

         if (seq && this.get_seq() !== seq) {
            console.warn("handleRpcJson: sequence mismatch: ", seq, '!=', this.get_seq());
            return false;
         }

         // not in preferences
         if (typeof Feeds !== 'undefined') {
            if (message === 'UPDATE_COUNTERS') {
               Feeds.requestCounters(reply.feeds);
            }

            if (counters)
               Feeds.parseCounters(counters);
         }

         if (runtime_info)
            this.parseRuntimeInfo(runtime_info);

         if (netalert) netalert.hide();

         return true;
      } else {
         if (netalert) netalert.show();

         Notify.error("Communication problem with server.");

         return false;
		}
	},
	parseRuntimeInfo: function(data) {
		Object.keys(data).forEach((k) => {
         const v = data[k];

         if (k === "daemon_is_running" && v !== 1) {
            Notify.error("Update daemon is not running.", true);
            return;
         }

         if (k === "recent_log_events") {
            const alert = document.querySelector('.log-alert');

            if (alert) {
               v > 0 ? alert.show() : alert.hide();
            }
         }

         if (k === "daemon_stamp_ok" && v !== 1) {
            Notify.error("Update daemon is not updating feeds.", true);
            return;
         }

         if (typeof Feeds !== 'undefined') {
            if (k === "max_feed_id" || k === "num_feeds") {
               if (this.getInitParam(k) && this.getInitParam(k) !== v) {
                  Feeds.reload();
               }
            }
         }

         this.setInitParam(k, v);
		});

		PluginHost.run(PluginHost.HOOK_RUNTIME_INFO_LOADED, data);
	},
	backendSanityCallback: function(reply) {
		console.log("sanity check ok");

		const params = reply['init-params'];

		if (params) {

			Object.keys(params).forEach((k) => {
            switch (k) {
               case "label_base_index":
                  this.LABEL_BASE_INDEX = parseInt(params[k]);
                  break;
               case "cdm_auto_catchup":
                  {
                     const headlines = document.getElementById("headlines-frame");

                  // we could be in preferences
                     if (headlines)
                        headlines.setAttribute("data-auto-catchup", params[k] ? "true" : "false");
                  }
                  break;
               case "hotkeys":
                  // filter mnemonic definitions (used for help panel) from hotkeys map
                  // i.e. *(191)|Ctrl-/ -> *(191)
                  {
                     const tmp = [];

                     Object.keys(params[k][1]).forEach((sequence) => {
                        const filtered = sequence.replace(/\|.*$/, "");
                        tmp[filtered] = params[k][1][sequence];
                     });

                     params[k][1] = tmp;
                  }
                  break;
            }

            this.setInitParam(k, params[k]);
			});

			// PluginHost might not be available on non-index pages
			if (typeof PluginHost !== 'undefined')
				PluginHost.run(PluginHost.HOOK_PARAMS_LOADED, this._initParams);
		}

      const translations = reply['translations'];

      if (translations) {
         App._translations = translations;
      }

		this.initSecondStage();
	},
	Error: {
      E_SUCCESS: "E_SUCCESS",
      E_UNAUTHORIZED: "E_UNAUTHORIZED",
      E_SCHEMA_MISMATCH: "E_SCHEMA_MISMATCH",
      E_URL_SCHEME_MISMATCH: "E_URL_SCHEME_MISMATCH",
		fatal: function (error, params = {}) {
         if (error === App.Error.E_UNAUTHORIZED) {
            window.location.href = "index.php";
            return;
         } else if (error === App.Error.E_SCHEMA_MISMATCH) {
            window.location.href = "public.php?op=dbupdate";
            return;
         } else if (error === App.Error.E_URL_SCHEME_MISMATCH) {
            params.description = __("URL scheme reported by your browser (%a) doesn't match server-configured SELF_URL_PATH (%b), check X-Forwarded-Proto.")
               .replace("%a", params.client_scheme)
               .replace("%b", params.server_scheme);
            params.info = `SELF_URL_PATH: ${params.self_url_path}\nCLIENT_LOCATION: ${document.location.href}`
         }

			return this.report(error,
				{...{title: __("Fatal error")}, ...params});
		},
		report: function(error, params = {}) {
			if (!error) return;

			console.error("error.report:", error, params);

			const message = params.message ? params.message : error.toString();

			try {
				xhr.post("backend.php",
					{op: "RPC", method: "log",
						file: params.filename ? params.filename : error.fileName,
						line: params.lineno ? params.lineno : error.lineNumber,
						msg: message,
						context: error.stack});
			} catch (re) {
				console.error("[Error.report] exception while saving logging error on server", re);
			}

			try {
				const dialog = new fox.SingleUseDialog({
					title: params.title || __("Unhandled exception"),
					content: `
               <div class='exception-contents'>
                  <h3>${message}</h3>

                  ${params.description ? `<p>${params.description}</p>` : ''}

                  ${error.stack ?
                  `<header>${__('Stack trace')}</header>
                  <section>
                     <textarea readonly='readonly'>${error.stack}</textarea>
                  </section>` : ''}

                  ${params && params.info ?
                     `
                     <header>${__('Additional information')}</header>
                     <section>
                        <textarea readonly='readonly'>${params.info}</textarea>
                     </section>
                     ` : ''}
               </div>
               <footer class='text-center'>
                  <button dojoType="dijit.form.Button" class='alt-primary' type='submit'>
                     ${__('Close this window')}
                  </button>
               </footer>
            </div>`
				});

				dialog.show();
			} catch (de) {
				console.error("[Error.report] exception while showing error dialog", de);

				alert(error.stack ? error.stack : message);
			}

		},
		onWindowError: function (message, filename, lineno, colno, error) {
			// called without context (this) from window.onerror
			App.Error.report(error,
				{message: message, filename: filename, lineno: lineno, colno: colno});
		},
	},
	isPrefs() {
		return this.is_prefs;
   },
   audioCanPlay: function(ctype) {
      const a = document.createElement('audio');
      return a.canPlayType(ctype);
   },
	init: function(parser, is_prefs) {
		require(['dojo/aspect'], function(aspect) {
			aspect.before(dojo, 'xhrPost', function(args) {
				return [{...args, content: { ...args?.content, csrf_token: __csrf_token }}];
			});
		});

      this.is_prefs = is_prefs;
      window.onerror = this.Error.onWindowError;

      /* global __default_dark_theme, __default_light_theme */
      this.setInitParam("csrf_token", __csrf_token);
      this.setInitParam("default_light_theme", __default_light_theme);
      this.setInitParam("default_dark_theme", __default_dark_theme);

      this.setupNightModeDetection(() => {
         parser.parse();

         if (!this.checkBrowserFeatures())
            return;

         this.setLoadingProgress(30);
         this.initHotkeyActions();

         const params = {
            op: "RPC",
            method: "sanityCheck",
            clientTzOffset: new Date().getTimezoneOffset() * 60,
            hasSandbox: "sandbox" in document.createElement("iframe"),
            clientLocation: window.location.href
         };

         xhr.json("backend.php", params, (reply) => {
            try {
               this.backendSanityCallback(reply);
            } catch (e) {
               this.Error.report(e);
            }
         });
      });
   },
   checkBrowserFeatures: function() {
      let errorMsg = "";

      ['MutationObserver', 'requestIdleCallback'].forEach((t) => {
         if (!(t in window)) {
            errorMsg = `Browser check failed: <code>window.${t}</code> not found.`;
            throw new Error(errorMsg);
         }
      });

      if (typeof Promise.allSettled === "undefined") {
         errorMsg = `Browser check failed: <code>Promise.allSettled</code> is not defined.`;
         throw new Error(errorMsg);
      }

      return errorMsg === "";
   },
   updateRuntimeInfo: function() {
      xhr.json("backend.php", {op: "RPC", method: "getruntimeinfo"}, () => {
         // handled by xhr.json()
      });
   },
   initSecondStage: function() {

      document.onkeydown = (event) => this.hotkeyHandler(event);
      document.onkeypress = (event) => this.hotkeyHandler(event);

      if (this.is_prefs) {

         this.setLoadingProgress(70);
         Notify.close();

         let tab = this.urlParam('tab');

         if (tab) {
            tab = dijit.byId(tab + "Tab");
            if (tab) {
               dijit.byId("pref-tabs").selectChild(tab);

               const method = this.urlParam("method");

               if (method) {
                  switch (method) {
                     case "editfeed":
                        window.setTimeout(() => {
                           CommonDialogs.editFeed(this.urlParam('methodparam'))
                        }, 100);
                        break;
                     default:
                        console.warn("initSecondStage, unknown method:", method);
                  }
               }
            }
         } else {
            let tab = localStorage.getItem("ttrss:prefs-tab");

            if (tab) {
               tab = dijit.byId(tab);
               if (tab) {
                  dijit.byId("pref-tabs").selectChild(tab);
               }
            }
         }

         dojo.connect(dijit.byId("pref-tabs"), "selectChild", function (elem) {
            localStorage.setItem("ttrss:prefs-tab", elem.id);
            App.updateRuntimeInfo();
         });

      } else {

         Feeds.reload();
         Article.close();

         if (parseInt(Cookie.get("ttrss_fh_width")) > 0) {
            dijit.byId("feeds-holder").domNode.setStyle(
               {width: Cookie.get("ttrss_fh_width") + "px"});
         }

         dijit.byId("main").resize();

         dojo.connect(dijit.byId('feeds-holder'), 'resize',
            (args) => {
               if (args && args.w >= 0) {
                  Cookie.set("ttrss_fh_width", args.w, this.getInitParam("cookie_lifetime"));
               }
            });

         dojo.connect(dijit.byId('content-insert'), 'resize',
            (args) => {
               if (args && args.w >= 0 && args.h >= 0) {
						const cookie_suffix = this._widescreen_mode ? "wide" : "normal";

                  Cookie.set("ttrss_ci_width:" + cookie_suffix, args.w, this.getInitParam("cookie_lifetime"));
                  Cookie.set("ttrss_ci_height:" + cookie_suffix, args.h, this.getInitParam("cookie_lifetime"));
               }
            });

         dijit.byId('toolbar-main').setValues({
            view_mode: this.getInitParam("default_view_mode"),
            order_by: this.getInitParam("default_view_order_by")
         });

         this.setLoadingProgress(50);

         this._widescreen_mode = this.getInitParam("widescreen");
         this.setWideScreenMode(this.isWideScreenMode(), true);

         Headlines.initScrollHandler();

         this.initPullToRefresh();
         this.initSwipeToRead();
         this.initLongPressContextMenu();

         // If the viewport grows back to the docked layout (the hamburger
         // toggle is hidden there), make sure the drawer isn't left open.
         window.addEventListener("resize", () => {
            const toggle = document.querySelector(".sidebar-toggle");
            if (toggle && window.getComputedStyle(toggle).display === "none")
               this.toggleSidebar(false);
         });

         // Device Back gesture / browser Back dismisses the topmost phone-layout
         // overlay — an open Dijit menu/popup first (the toolbar Actions menu or
         // a long-press context menu), then the feed drawer, then an open article
         // — instead of leaving the app. See reconcileOverlayHistory(). A
         // history.back() we triggered ourselves (an overlay closed by other
         // means) also lands here, so those are skipped via _suppress_popstate.
         window.addEventListener("popstate", () => {
            if (this._suppress_popstate > 0) {
               this._suppress_popstate--;
               return;
            }
            if (this._overlay_stack.length === 0)
               return;

            // The browser already popped the top entry; tear down that overlay
            // without touching history again (_suppress_reconcile).
            const name = this._overlay_stack.pop();
            this._suppress_reconcile = true;
            try {
               // close() is a no-op if Dijit already dismissed the menu, which
               // just absorbs this Back (the entry lingered until now).
               if (name === "popup")
                  dijit.popup.close();
               else if (name === "drawer")
                  this.toggleSidebar(false);
               else if (this.isCombinedMode())
                  Article.cdmUnsetActive();
               else
                  Article.close();
            } finally {
               this._suppress_reconcile = false;
            }
         });

         // An open Dijit popup (the toolbar Actions menu, a long-press context
         // menu, a toolbar dropdown) sits above the drawer and article, so Back
         // should close it first. Every popup opens through the dijit.popup
         // singleton, so reconcile after open() to push the popup's history entry.
         //
         // We deliberately do NOT touch history from a close() hook: Dijit
         // dismisses menus on its own (an outside tap, a blur, the viewport
         // change a phone back-swipe causes), and removing the history entry at
         // close time -- which needs a synthetic history.back() -- races the real
         // Back. The synthetic back eats the entry first, so the user's Back then
         // finds nothing to absorb it and leaves the app. Instead the entry
         // lingers harmlessly: a Back pops it (the popstate handler closes the
         // popup, or no-ops if Dijit already dismissed it, absorbing the press),
         // and reconcileOverlayHistory prunes any leftover entry the next time
         // another overlay changes.
         //
         // Popups are non-modal by default: an outside tap dismisses the menu but
         // also activates whatever is behind it. To match the modal feed drawer
         // on the narrow layout we park a full-screen backdrop just beneath the
         // popup (below dijit.popup's z-index:1000, above the app) that swallows
         // that tap so it only closes the menu. Keeping the backdrop in sync with
         // dijit.popup._stack is a pure DOM toggle, so -- unlike the history work
         // above -- it is safe to drive from a close() hook.
         if (dijit.popup) {
            const backdrop = document.createElement("div");
            backdrop.id = "popup-backdrop";
            // Dismiss on the press itself (touchstart), and preventDefault it, so
            // the browser never synthesises the "compatibility" click that a tap
            // normally produces. Without that, closing the menu hides this
            // backdrop mid-gesture and the click then lands on whatever is now
            // exposed beneath -- a click-through, notably on iOS Safari. Reacting
            // to the press rather than the finger-up also stops the gesture that
            // opened the menu (its press landed on the opener before this backdrop
            // existed) from dismissing it the instant the finger lifts. The click
            // handler covers non-touch input (on touch the preventDefault above
            // suppresses it, so it does not double-fire).
            backdrop.addEventListener("touchstart", (ev) => {
               ev.preventDefault();
               dijit.popup.close();
            }, {passive: false});
            backdrop.addEventListener("click", () => dijit.popup.close());
            document.body.appendChild(backdrop);

            const popup = dijit.popup;
            const orig_open = popup.open;
            popup.open = function() {
               const ret = orig_open.apply(this, arguments);
               App._syncPopupBackdrop();
               App.reconcileOverlayHistory();
               return ret;
            };
            const orig_close = popup.close;
            popup.close = function() {
               const ret = orig_close.apply(this, arguments);
               App._syncPopupBackdrop();
               return ret;
            };
         }

         if (this.getInitParam('check_for_updates')) {
			window.setTimeout(() => {
              this.checkForUpdates();
              window.setInterval(() => {
                 this.checkForUpdates();
              }, 3600 * 1000);
            }, 60 * 1000);
         }

         PluginHost.run(PluginHost.HOOK_INIT_COMPLETE, null);
      }

      if (!this.getInitParam("bw_limit"))
         window.setInterval(() => {
            App.updateRuntimeInfo();
         }, 60 * 1000)

		if (App.getInitParam("safe_mode") && this.isPrefs()) {
			CommonDialogs.safeModeWarning();
		}

      console.log("second stage ok");

   },
	checkForUpdates: function() {
		xhr.json("backend.php", {op: 'RPC', method: 'checkforupdates'})
			.then((reply) => {
				const ttrss_icon_a = document.getElementById('updates-available');
				const plugin_icon_a = document.getElementById('plugin-updates-available');

				if (reply.changeset.id) {
					ttrss_icon_a.href = reply.changeset.compare_url;
					ttrss_icon_a.show();
				} else {
					ttrss_icon_a.hide();
				}

				if (reply.plugins.length)
					plugin_icon_a.show()
				else
					plugin_icon_a.hide();
			});
	},
   updateTitle: function() {
      let tmp = "Tiny Tiny RSS";

      if (this.global_unread > 0) {
         tmp = "(" + this.global_unread + ") " + tmp;
      }

      document.title = tmp;
   },
	hotkeyHandler: function(event) {
		if (event.target.nodeName === 'TEXTAREA')
			return;

		if (event.target.nodeName === 'INPUT') {
			const type = (event.target.type || 'text').toLowerCase();
			const text_input_types = ['text', 'password', 'email', 'search', 'tel', 'url', 'number', 'date', 'datetime-local', 'month', 'time', 'week'];

			if (text_input_types.includes(type))
				return;
		}

		// Arrow buttons and escape are not reported via keypress, handle them via keydown.
		if (event.type === 'keydown'
			&& !['Escape', 'ArrowLeft', 'ArrowUp', 'ArrowRight', 'ArrowDown', 'PageUp', 'PageDown', 'Insert', 'Delete'].includes(event.key))
			return;

		const action_name = this.keyeventToAction(event);

		if (action_name) {
			const action_func = this.hotkey_actions[action_name];

			if (typeof action_func === 'function') {
				action_func(event);
				event.stopPropagation();
				return false;
			}
		}
	},
	isWideScreenMode: function() {
		return !!this._widescreen_mode;
	},
   setWideScreenMode: function(wide, quiet = false) {

		if (this.isCombinedMode() && !quiet) {
			alert(__("Widescreen is not available in combined mode."));
			return;
		}

		this._widescreen_mode = wide;

      const article_id = Article.getActive();
      const headlines_frame = document.getElementById("headlines-frame");
      const content_insert = dijit.byId("content-insert");

      // TODO: setStyle stuff should probably be handled by CSS

      if (wide) {
         dijit.byId("headlines-wrap-inner").attr("design", 'sidebar');
         content_insert.attr("region", "trailing");

         content_insert.domNode.setStyle({width: '50%',
            height: 'auto',
            borderTopWidth: '0px' });

         if (parseInt(Cookie.get("ttrss_ci_width:wide")) > 0) {
            content_insert.domNode.setStyle(
               {width: Cookie.get("ttrss_ci_width:wide") + "px" });
         }

         headlines_frame.setStyle({ borderBottomWidth: '0px' });

      } else {

         content_insert.attr("region", "bottom");

         content_insert.domNode.setStyle({width: 'auto',
            height: '50%',
            borderTopWidth: '0px'});

         if (parseInt(Cookie.get("ttrss_ci_height:normal")) > 0) {
            content_insert.domNode.setStyle(
               {height: Cookie.get("ttrss_ci_height:normal") + "px" });
         }

         headlines_frame.setStyle({ borderBottomWidth: '1px' });
      }

      headlines_frame.setAttribute("data-is-wide-screen", wide ? "true" : "false");

      Article.close();

      if (article_id) Article.view(article_id);

      xhr.post("backend.php", {op: "RPC", method: "setWidescreen", wide: wide ? 1 : 0});
   },
   // True on the narrow/phone layout, where the hamburger toggle is visible.
   // Mirrors the media query that drives the responsive styles, so we use it
   // to gate phone-only behaviour (e.g. the Back gesture closing an article).
   isNarrowLayout: function() {
      const toggle = document.querySelector(".sidebar-toggle");
      return !!toggle && window.getComputedStyle(toggle).display !== "none";
   },
   // Show the modal backdrop (see the dijit.popup hooks in initSecondStage)
   // whenever a popup is open on the narrow layout, so an outside tap dismisses
   // the menu instead of falling through to the app. dijit.popup._stack is the
   // set of currently-open popups; submenus keep it non-empty so the backdrop
   // stays put until the last one closes.
   _syncPopupBackdrop: function() {
      const open = this.isNarrowLayout() && !!dijit.popup && dijit.popup._stack.length > 0;
      document.body.classList.toggle("popup-backdrop-open", open);
   },
   // Phone-layout overlays — an open article (expanded row in combined mode, or
   // the 3-panel pane; both set the active row), the feed drawer, and an open
   // Dijit menu/popup — each get a history entry so the device Back gesture
   // dismisses them one at a time instead of leaving the app. This reconciles
   // our pushed entries with what is actually open. They stack bottom-to-top as
   // [article, drawer, popup]: the drawer opens over an article (you can't tap a
   // row while it covers the list) and a popup opens over everything, so Back
   // closes the popup first, then the drawer, then the article. Called whenever
   // any overlay's state can change (Article.setActive/cdmUnsetActive,
   // toggleSidebar, and the dijit.popup open hook); a lingering popup entry left
   // after Dijit dismisses a menu on its own is pruned here on the next change.
   reconcileOverlayHistory: function() {
      if (this._suppress_reconcile)
         return;

      const open = [];
      if (this.isNarrowLayout()) {
         if (Article.getActive() !== 0) open.push("article");
         if (document.body.classList.contains("feeds-drawer-open")) open.push("drawer");
         // dijit.popup._stack holds the open popups (menus, dropdowns); collapse
         // them to a single topmost "popup" entry that Back closes all at once.
         if (dijit.popup && dijit.popup._stack.length) open.push("popup");
      }

      // Pop our top entries that are no longer open. Each history.back() fires a
      // popstate that isn't the user pressing Back, so count it to skip it.
      while (this._overlay_stack.length &&
             !open.includes(this._overlay_stack[this._overlay_stack.length - 1])) {
         this._overlay_stack.pop();
         this._suppress_popstate++;
         window.history.back();
      }

      // Push entries for newly-open overlays (article below drawer).
      for (const name of open)
         if (!this._overlay_stack.includes(name)) {
            this._overlay_stack.push(name);
            window.history.pushState({ttrss_overlay: name}, "");
         }
   },
   // Open/close the feed sidebar drawer (narrow/phone layout only). The
   // .feeds-drawer-open styles that slide the sidebar in are gated behind a
   // media query, so toggling the class is a no-op on the docked desktop layout.
   toggleSidebar: function(force) {
      const open = typeof force === "boolean" ?
         force : !document.body.classList.contains("feeds-drawer-open");

      document.body.classList.toggle("feeds-drawer-open", open);

      const toggle = document.querySelector(".sidebar-toggle");
      if (toggle)
         toggle.setAttribute("aria-expanded", open ? "true" : "false");

      // Keep the Back-gesture history entries in sync with the drawer state.
      this.reconcileOverlayHistory();
   },
   // Pull-to-refresh for the article list on the narrow/phone layout: a downward
   // drag while scrolled to the very top reloads the current feed once it passes
   // a threshold -- the same ForceUpdate reload as re-selecting the feed. A small
   // spinner badge slides down from the top edge to follow the gesture. The badge
   // lives in #headlines-wrap-inner rather than #headlines-frame because the
   // latter's innerHTML is rebuilt on every feed load (Headlines.onLoaded), which
   // would delete a child of it.
   initPullToRefresh: function() {
      const frame = document.getElementById("headlines-frame");
      const wrap = document.getElementById("headlines-wrap-inner");
      if (!frame || !wrap)
         return;

      const badge = document.createElement("div");
      badge.id = "headlines-ptr";
      badge.innerHTML = "<i class='material-icons'>refresh</i>";
      wrap.appendChild(badge);
      const icon = badge.firstElementChild;

      const THRESHOLD = 64;   // px of pull (pre-damping) needed to fire a refresh
      const MAX = 96;         // clamp the badge travel so it can't be hauled off the list

      let start_y = 0;
      let start_x = 0;
      let pulling = false;    // a qualifying downward drag from the top is active
      let refreshing = false; // a refresh fired; awaiting the feed to finish loading
      let safety_timeout = 0;

      // Past the threshold the pull gets progressively heavier (rubber-banding).
      const damp = (d) => d < THRESHOLD ? d : Math.min(MAX, THRESHOLD + (d - THRESHOLD) * 0.4);

      const moveTo = (travel) => {
         const progress = Math.min(1, travel / THRESHOLD);
         badge.style.transform = `translate(-50%, ${travel}px)`;
         badge.style.opacity = progress;
         icon.style.transform = `rotate(${progress * 180}deg)`;
         badge.classList.toggle("ready", travel >= THRESHOLD);
      };

      // Drop all inline overrides so the badge eases back to its parked CSS state.
      const settle = () => {
         badge.classList.remove("dragging", "ready");
         badge.style.transform = "";
         badge.style.opacity = "";
         icon.style.transform = "";
      };

      const stopRefreshing = () => {
         if (!refreshing)
            return;
         refreshing = false;
         window.clearTimeout(safety_timeout);
         badge.classList.remove("spinning");
         settle();
      };

      frame.addEventListener("touchstart", (ev) => {
         if (refreshing || pulling || ev.touches.length !== 1)
            return;
         if (!this.isNarrowLayout() || frame.scrollTop > 0)
            return;
         start_y = ev.touches[0].clientY;
         start_x = ev.touches[0].clientX;
         pulling = true;
      }, {passive: true});

      frame.addEventListener("touchmove", (ev) => {
         if (!pulling)
            return;
         const dist = ev.touches[0].clientY - start_y;
         const dx = ev.touches[0].clientX - start_x;
         // Moved up, the list managed to scroll, or this is really a sideways
         // swipe (swipe-to-read, initSwipeToRead owns those): hand it back.
         if (dist <= 0 || frame.scrollTop > 0 || Math.abs(dx) > dist) {
            pulling = false;
            settle();
            return;
         }
         // A genuine downward pull at the top: take over from native scroll so the
         // browser's own overscroll/refresh doesn't also fire.
         ev.preventDefault();
         badge.classList.add("dragging");
         moveTo(damp(dist));
      }, {passive: false});

      frame.addEventListener("touchend", (ev) => {
         if (!pulling)
            return;
         pulling = false;
         badge.classList.remove("dragging");
         const touch = ev.changedTouches && ev.changedTouches[0];
         const travel = touch ? damp(touch.clientY - start_y) : 0;
         if (travel >= THRESHOLD) {
            refreshing = true;
            badge.classList.remove("ready");
            badge.classList.add("spinning");
            badge.style.opacity = 1;
            badge.style.transform = `translate(-50%, ${THRESHOLD}px)`;
            icon.style.transform = "";
            // If the load never reports back (e.g. a network error), retract anyway.
            safety_timeout = window.setTimeout(stopRefreshing, 15 * 1000);
            Feeds.reloadCurrent();
         } else {
            settle();
         }
      }, {passive: true});

      frame.addEventListener("touchcancel", () => {
         if (pulling) {
            pulling = false;
            settle();
         }
      }, {passive: true});

      // The reloaded feed has rendered -- stop and retract the spinner.
      PluginHost.register(PluginHost.HOOK_FEED_LOADED, stopRefreshing);
   },
   // Swipe-to-dismiss on the article list (narrow/phone layout): a horizontal
   // drag across a headline row marks it read and slides it out of the list,
   // like the swipe-to-archive gesture in mobile mail apps. Either direction
   // does the same thing; the row reveals a coloured "mark read" affordance on
   // the edge it is dragged toward, which brightens once the drag passes the
   // commit threshold. Vertical drags are left to native scrolling and to
   // pull-to-refresh (which cedes horizontal-dominant drags to us).
   //
   // The row content is translated to follow the finger; the affordance is an
   // absolutely-positioned child parked just off that edge, so it rides in with
   // the row (no second transform to keep in sync). #headlines-frame clips its
   // horizontal overflow (overflow-x:hidden in the narrow theme) so the parked
   // affordance and the slid-off content stay hidden until revealed.
   initSwipeToRead: function() {
      const frame = document.getElementById("headlines-frame");
      if (!frame)
         return;

      const LOCK = 10;        // px of travel before we commit to an axis
      const THRESHOLD = 80;   // px of horizontal travel that fires the action

      let row = null;         // the headline row under the finger
      let action = null;      // the revealed "mark read" layer (a child of row)
      let start_x = 0, start_y = 0, dx = 0;
      let axis = "";          // "" undecided, "h" we own it, "v" native scroll

      const cleanup = () => {
         if (row) {
            row.classList.remove("swiping");
            row.style.transform = "";
         }
         if (action)
            action.remove();
         row = action = null;
         axis = "";
         dx = 0;
      };

      // Mark read (persisted by Headlines' row_observer -> catchupSelected) and
      // animate the row off-screen, then collapse its height so the rows below
      // slide up to fill the gap, and finally drop it from the DOM.
      const dismiss = (dir) => {
         const r = row, a = action;
         const id = parseInt(r.getAttribute("data-article-id"));
         row = action = null;   // release closure state; the node lives on alone
         axis = "";
         dx = 0;

         Headlines.toggleUnread(id, 0);

         const h = r.offsetHeight;
         const w = r.offsetWidth;
         r.style.height = h + "px";
         void r.offsetHeight;   // lock the height before transitioning it to 0
         r.classList.add("swipe-anim");
         r.style.transform = `translateX(${dir * w}px)`;
         r.style.height = "0px";
         r.style.opacity = "0";
         r.style.marginTop = r.style.marginBottom = "0px";
         r.style.paddingTop = r.style.paddingBottom = "0px";
         r.style.borderWidth = "0px";
         if (a)
            a.remove();
         // transitionend fires per-property; a timeout also covers the case
         // where a zero-duration transition emits nothing.
         window.setTimeout(() => r.remove(), 300);
      };

      frame.addEventListener("touchstart", (ev) => {
         if (!this.isNarrowLayout() || ev.touches.length !== 1) {
            axis = "v";
            return;
         }
         const r = ev.target.closest("#headlines-frame > div[id^=RROW]");
         // skip the active (open) article — it owns a Back-gesture overlay entry
         // — and any row already animating out.
         if (!r || r.classList.contains("active") || r.classList.contains("swipe-anim")) {
            axis = "v";
            return;
         }
         row = r;
         start_x = ev.touches[0].clientX;
         start_y = ev.touches[0].clientY;
         axis = "";
         dx = 0;
      }, {passive: true});

      frame.addEventListener("touchmove", (ev) => {
         if (!row || axis === "v")
            return;
         dx = ev.touches[0].clientX - start_x;
         const dy = ev.touches[0].clientY - start_y;

         if (axis === "") {
            if (Math.abs(dx) < LOCK && Math.abs(dy) < LOCK)
               return;
            if (Math.abs(dx) <= Math.abs(dy)) {   // vertical wins: it's a scroll
               row = null;
               axis = "v";
               return;
            }
            axis = "h";                            // horizontal wins: take over
            row.classList.add("swiping");
            action = document.createElement("div");
            action.className = "hl-swipe-action";
            action.innerHTML = "<i class='material-icons'>done_all</i>";
            row.insertBefore(action, row.firstChild);
         }

         ev.preventDefault();
         action.classList.toggle("from-left", dx > 0);
         action.classList.toggle("from-right", dx < 0);
         action.classList.toggle("ready", Math.abs(dx) >= THRESHOLD);
         row.style.transform = `translateX(${dx}px)`;
      }, {passive: false});

      frame.addEventListener("touchend", () => {
         if (!row || axis !== "h") {
            cleanup();
            return;
         }
         if (Math.abs(dx) >= THRESHOLD) {
            dismiss(dx > 0 ? 1 : -1);
            return;
         }
         // below threshold: ease the row back home, then drop the swiping state
         const r = row, a = action;
         row = action = null;
         axis = "";
         dx = 0;
         r.classList.add("swipe-anim");
         r.style.transform = "";
         window.setTimeout(() => {
            r.classList.remove("swiping", "swipe-anim");
            if (a)
               a.remove();
         }, 220);
      }, {passive: true});

      frame.addEventListener("touchcancel", cleanup, {passive: true});
   },
   // iOS Safari never fires a contextmenu event for a touch long-press, and
   // contextmenu is the only trigger the delegated dijit.Menu bindings listen
   // for (headline rows and group headers in Headlines.initHeadlinesMenu(),
   // feed tree rows in FeedTree._initContextMenus()) -- so on iPhones and
   // iPads those menus were unreachable. Time the press ourselves and
   // dispatch the contextmenu event dijit expects at the pressed element;
   // from there the selector delegation, currentTarget and menu position work
   // exactly as for a real right-click. Android *does* fire contextmenu
   // natively at ~500ms, so the timer sits above that: where a native event
   // exists it wins and cancels the pending press, and a late native
   // duplicate arriving just after a synthesised open is eaten.
   //
   // The menu opens while the finger is still down. When it lifts, the
   // browser synthesises the tap's compatibility mousedown/mouseup/click,
   // which would land on the just-opened menu (it opens at the finger) or on
   // the modal backdrop behind it and activate or dismiss something the user
   // never aimed at. So once the press qualifies, the gesture's compatibility
   // events are swallowed at the document, armed only until just after this
   // pointer's release so the next deliberate tap (e.g. on a menu item) is
   // unaffected.
   initLongPressContextMenu: function() {
      const LONG_PRESS_MS = 600;  // above Android's native long-press delay on purpose
      const MOVE_SLOP_PX = 10;    // finger drift allowed before it counts as a scroll

      ["feeds-holder", "headlines-frame"].forEach((container_id) => {
         const container = document.getElementById(container_id);
         if (!container)
            return;

         let pending = null;        // the press being timed
         let synthesized_at = 0;    // when we last dispatched a synthetic contextmenu

         const cancel = () => {
            if (pending) {
               window.clearTimeout(pending.timer);
               pending = null;
            }
         };

         const armReleaseSwallow = () => {
            const swallow = (ev) => {
               ev.preventDefault();
               ev.stopPropagation();
            };
            const types = ["mousedown", "mouseup", "click"];
            types.forEach((t) => document.addEventListener(t, swallow, {capture: true, once: true}));

            const disarm = () => {
               container.removeEventListener("pointerup", disarm);
               container.removeEventListener("pointercancel", disarm);
               // compatibility events follow the release almost immediately;
               // anything later is a new, deliberate tap
               window.setTimeout(() => {
                  types.forEach((t) => document.removeEventListener(t, swallow, {capture: true}));
               }, 150);
            };
            container.addEventListener("pointerup", disarm);
            container.addEventListener("pointercancel", disarm);
         };

         container.addEventListener("pointerdown", (ev) => {
            cancel();

            // a second finger means pinch or scroll, not a long-press
            if (ev.pointerType !== "touch" || !ev.isPrimary)
               return;

            pending = {
               pointer_id: ev.pointerId,
               x: ev.clientX,
               y: ev.clientY,
               target: ev.target,
               timer: window.setTimeout(() => {
                  const press = pending;
                  pending = null;

                  const synth = new MouseEvent("contextmenu", {bubbles: true,
                     cancelable: true, view: window, clientX: press.x, clientY: press.y});
                  synth.ttrss_synthesized = true;
                  synthesized_at = Date.now();

                  // Armed before dispatching, and regardless of whether a menu
                  // is bound here: dijit opens the menu on a deferred tick
                  // (Menu._scheduleOpen), so "did a menu open" can't be checked
                  // synchronously -- and a recognised long-press's release
                  // shouldn't click through to the app in any case, matching
                  // the native behaviour on Android.
                  armReleaseSwallow();
                  press.target.dispatchEvent(synth);
               }, LONG_PRESS_MS)
            };
         }, {passive: true});

         container.addEventListener("pointermove", (ev) => {
            if (pending && ev.pointerId === pending.pointer_id &&
                  (Math.abs(ev.clientX - pending.x) > MOVE_SLOP_PX ||
                   Math.abs(ev.clientY - pending.y) > MOVE_SLOP_PX))
               cancel();
         }, {passive: true});

         container.addEventListener("pointerup", cancel, {passive: true});
         container.addEventListener("pointercancel", cancel, {passive: true});

         // A native contextmenu -- Android's long-press or an actual right
         // click -- outranks the timer. The reverse race (our timer fired
         // first, the native event limped in after) would open the menu
         // twice, so a native event on the heels of a synthesised one is
         // eaten in the capture phase, before dijit's delegated bubble
         // handler can see it.
         container.addEventListener("contextmenu", (ev) => {
            if (ev.ttrss_synthesized)
               return;
            cancel();
            if (Date.now() - synthesized_at < 400) {
               ev.preventDefault();
               ev.stopImmediatePropagation();
            }
         }, {capture: true});
      });
   },
   initHotkeyActions: function() {
      if (this.is_prefs) {

         this.hotkey_actions["feed_subscribe"] = () => {
            CommonDialogs.subscribeToFeed();
         };

         this.hotkey_actions["create_label"] = () => {
            CommonDialogs.addLabel();
         };

         this.hotkey_actions["create_filter"] = () => {
            Filters.edit();
         };

         this.hotkey_actions["help_dialog"] = () => {
            this.hotkeyHelp();
         };

      } else {

         this.hotkey_actions["next_feed"] = () => {
            const [feed, is_cat] = Feeds.getNextFeed(
               Feeds.getActive(), Feeds.activeIsCat());

				if (feed !== false)
					Feeds.open({feed: feed, is_cat: is_cat, delayed: true})
         };
         this.hotkey_actions["next_unread_feed"] = () => {
            const [feed, is_cat] = Feeds.getNextFeed(
               Feeds.getActive(), Feeds.activeIsCat(), true);

				if (feed !== false)
					Feeds.open({feed: feed, is_cat: is_cat, delayed: true})
         };
         this.hotkey_actions["prev_feed"] = () => {
            const [feed, is_cat] = Feeds.getPreviousFeed(
               Feeds.getActive(), Feeds.activeIsCat());

				if (feed !== false)
					Feeds.open({feed: feed, is_cat: is_cat, delayed: true})
         };
         this.hotkey_actions["prev_unread_feed"] = () => {
            const [feed, is_cat] = Feeds.getPreviousFeed(
               Feeds.getActive(), Feeds.activeIsCat(), true);

				if (feed !== false)
					Feeds.open({feed: feed, is_cat: is_cat, delayed: true})
         };
         this.hotkey_actions["next_article_or_scroll"] = (event) => {
            if (this.isCombinedMode())
               Headlines.scroll(Headlines.line_scroll_offset, event);
            else
               Headlines.move('next');
         };
         this.hotkey_actions["prev_article_or_scroll"] = (event) => {
            if (this.isCombinedMode())
               Headlines.scroll(-Headlines.line_scroll_offset, event);
            else
               Headlines.move('prev');
         };
         this.hotkey_actions["next_article_noscroll"] = () => {
            Headlines.move('next');
         };
         this.hotkey_actions["prev_article_noscroll"] = () => {
            Headlines.move('prev');
         };
         this.hotkey_actions["next_article_noexpand"] = () => {
            Headlines.move('next', {no_expand: true});
         };
         this.hotkey_actions["prev_article_noexpand"] = () => {
            Headlines.move('prev', {no_expand: true});
         };
         this.hotkey_actions["search_dialog"] = () => {
            Feeds.search();
         };
         this.hotkey_actions["cancel_search"] = () => {
            Feeds.cancelSearch();
         };
         this.hotkey_actions["toggle_mark"] = () => {
            Headlines.selectionToggleMarked();
         };
         this.hotkey_actions["toggle_publ"] = () => {
            Headlines.selectionTogglePublished();
         };
         this.hotkey_actions["toggle_unread"] = () => {
            Headlines.selectionToggleUnread({no_error: 1});
         };
         this.hotkey_actions["edit_tags"] = () => {
            const id = Article.getActive();
            if (id) {
               Article.editTags(id);
            }
         };
         this.hotkey_actions["open_in_new_window"] = () => {
            if (Article.getActive()) {
               Article.openInNewWindow(Article.getActive());
            }
         };
         this.hotkey_actions["catchup_below"] = () => {
            Headlines.catchupRelativeTo(1);
         };
         this.hotkey_actions["catchup_above"] = () => {
            Headlines.catchupRelativeTo(0);
         };
         this.hotkey_actions["article_scroll_down"] = (event) => {
            if (this.isCombinedMode())
               Headlines.scroll(Headlines.line_scroll_offset, event);
            else
               Article.scroll(Headlines.line_scroll_offset, event);
         };
         this.hotkey_actions["article_scroll_up"] = (event) => {
            if (this.isCombinedMode())
               Headlines.scroll(-Headlines.line_scroll_offset, event);
            else
               Article.scroll(-Headlines.line_scroll_offset, event);
         };
         this.hotkey_actions["next_headlines_page"] = (event) => {
            Headlines.scrollByPages(1, event);
         };
         this.hotkey_actions["prev_headlines_page"] = (event) => {
            Headlines.scrollByPages(-1, event);
         };
         this.hotkey_actions["article_page_down"] = (event) => {
            if (this.isCombinedMode())
               Headlines.scrollByPages(1, event);
            else
               Article.scrollByPages(1, event);
         };
         this.hotkey_actions["article_page_up"] = (event) => {
            if (this.isCombinedMode())
               Headlines.scrollByPages(-1, event);
            else
               Article.scrollByPages(-1, event);
         };
         this.hotkey_actions["close_article"] = () => {
            if (this.isCombinedMode()) {
               Article.cdmUnsetActive();
            } else {
               Article.close();
            }
         };
         this.hotkey_actions["email_article"] = () => {
            if (typeof Plugins.Mail !== "undefined") {
               Plugins.Mail.onHotkey(Headlines.getSelected());
            } else {
               alert(__("Please enable mail or mailto plugin first."));
            }
         };
         this.hotkey_actions["select_all"] = () => {
            Headlines.select('all');
         };
         this.hotkey_actions["select_unread"] = () => {
            Headlines.select('unread');
         };
         this.hotkey_actions["select_marked"] = () => {
            Headlines.select('marked');
         };
         this.hotkey_actions["select_published"] = () => {
            Headlines.select('published');
         };
         this.hotkey_actions["select_invert"] = () => {
            Headlines.select('invert');
         };
         this.hotkey_actions["select_none"] = () => {
            Headlines.select('none');
         };
         this.hotkey_actions["feed_refresh"] = () => {
            if (typeof Feeds.getActive() !== "undefined") {
               Feeds.open({feed: Feeds.getActive(), is_cat: Feeds.activeIsCat()});
            }
         };
         this.hotkey_actions["feed_unhide_read"] = () => {
            Feeds.toggleUnread();
         };
         this.hotkey_actions["feed_subscribe"] = () => {
            CommonDialogs.subscribeToFeed();
         };
         this.hotkey_actions["feed_debug_update"] = () => {
            if (!Feeds.activeIsCat() && parseInt(Feeds.getActive()) > 0) {

               /* global __csrf_token */
               App.postOpenWindow("backend.php", {op: "Feeds", method: "updatedebugger",
                  feed_id: Feeds.getActive(), csrf_token: __csrf_token});

            } else {
               alert("You can't debug this kind of feed.");
            }
         };

         this.hotkey_actions["feed_debug_viewfeed"] = () => {

				let query = {
					...{op: "Feeds", method: "view", feed: Feeds.getActive(), timestamps: 1,
							debug: 1, cat: Feeds.activeIsCat(), csrf_token: __csrf_token},
					...dojo.formToObject("toolbar-main")
				};

				if (Feeds._search_query) {
					query = Object.assign(query, Feeds._search_query);
				}

				console.log('debug_viewfeed', query);

				App.postOpenWindow("backend.php", query);
         };

         this.hotkey_actions["feed_edit"] = () => {
            if (Feeds.activeIsCat())
               alert(__("You can't edit this kind of feed."));
            else
               CommonDialogs.editFeed(Feeds.getActive());
         };
         this.hotkey_actions["feed_catchup"] = () => {
            if (typeof Feeds.getActive() !== "undefined") {
               Feeds.catchupCurrent();
            }
         };
         this.hotkey_actions["feed_reverse"] = () => {
            Headlines.reverse();
         };
         this.hotkey_actions["feed_toggle_grid"] = () => {
            xhr.json("backend.php", {op: "RPC", method: "togglepref", key: "CDM_ENABLE_GRID"}, (reply) => {
               App.setInitParam("cdm_enable_grid", reply.value);
               Headlines.renderAgain();
            })
         };
         this.hotkey_actions["feed_toggle_vgroup"] = () => {
            xhr.post("backend.php", {op: "RPC", method: "togglepref", key: "VFEED_GROUP_BY_FEED"}, () => {
               Feeds.reloadCurrent();
            })
         };
         this.hotkey_actions["catchup_all"] = () => {
            Feeds.catchupAll();
         };
         this.hotkey_actions["cat_toggle_collapse"] = () => {
            if (Feeds.activeIsCat()) {
               dijit.byId("feedTree").collapseCat(Feeds.getActive());
            }
         };
         this.hotkey_actions["goto_read"] = () => {
            Feeds.open({feed: Feeds.FEED_RECENTLY_READ});
         };
         this.hotkey_actions["goto_all"] = () => {
            Feeds.open({feed: Feeds.FEED_ALL});
         };
         this.hotkey_actions["goto_fresh"] = () => {
            Feeds.open({feed: Feeds.FEED_FRESH});
         };
         this.hotkey_actions["goto_marked"] = () => {
            Feeds.open({feed: Feeds.FEED_STARRED});
         };
         this.hotkey_actions["goto_published"] = () => {
            Feeds.open({feed: Feeds.FEED_PUBLISHED});
         };
         this.hotkey_actions["goto_prefs"] = () => {
            App.openPreferences();
         };
			this.hotkey_actions['select_article_cursor'] = () => {
				const id = Article.getUnderPointer();
				if (id)
					document.getElementById(`RROW-${id}`)?.classList.toggle('Selected');
			};
         this.hotkey_actions["create_label"] = () => {
            CommonDialogs.addLabel();
         };
         this.hotkey_actions["create_filter"] = () => {
            Filters.edit();
         };
         this.hotkey_actions["collapse_sidebar"] = () => {
            Feeds.toggle();
         };
         this.hotkey_actions["toggle_full_text"] = () => {
            if (typeof Plugins.Af_Readability !== "undefined") {
               if (Article.getActive())
                  Plugins.Af_Readability.embed(Article.getActive());
            } else {
               alert(__("Please enable af_readability first."));
            }
         };
         this.hotkey_actions["toggle_widescreen"] = () => {
				this.setWideScreenMode(!this.isWideScreenMode());
         };
         this.hotkey_actions["help_dialog"] = () => {
            this.hotkeyHelp();
         };
         this.hotkey_actions["toggle_combined_mode"] = () => {
				App.setCombinedMode(!App.isCombinedMode());
         };
         this.hotkey_actions["toggle_cdm_expanded"] = () => {
				App.setExpandedMode(!App.isExpandedMode());
         };
         this.hotkey_actions["article_span_grid"] = () => {
            Article.cdmToggleGridSpan(Article.getActive());
         };
      }
   },
   openPreferences: function(tab) {
      document.location.href = "prefs.php" + (tab ? "?tab=" + tab : "");
   },
   onActionSelected: function(opid) {
      switch (opid) {
         case "qmcPrefs":
            App.openPreferences();
            break;
         case "qmcLogout":
            App.postCurrentWindow("public.php", {op: "logout", csrf_token: __csrf_token});
            break;
         case "qmcSearch":
            Feeds.search();
            break;
         case "qmcFilterFeeds":
            Feeds.filter();
            break;
         case "qmcAddFeed":
            CommonDialogs.subscribeToFeed();
            break;
         case "qmcDigest":
            window.location.href = "backend.php?op=Digest";
            break;
         case "qmcEditFeed":
            if (Feeds.activeIsCat())
               alert(__("You can't edit this kind of feed."));
            else
               CommonDialogs.editFeed(Feeds.getActive());
            break;
         case "qmcRemoveFeed":
            {
               const actid = Feeds.getActive();

               if (!actid) {
                  alert(__("Please select some feed first."));
                  return;
               }

               if (Feeds.activeIsCat()) {
                  alert(__("You can't unsubscribe from the category."));
                  return;
               }

               const fn = Feeds.getName(actid);

               if (confirm(__("Unsubscribe from %s?").replace("%s", fn))) {
                  CommonDialogs.unsubscribeFeed(actid);
               }
            }
            break;
         case "qmcCatchupAll":
            Feeds.catchupAll();
            break;
         case "qmcShowOnlyUnread":
            Feeds.toggleUnread();
            break;
         case "qmcToggleWidescreen":
            App.setWideScreenMode(!App.isWideScreenMode());
            break;
			case "qmcToggleCombined":
				App.setCombinedMode(!App.isCombinedMode());
				break;
			case "qmcToggleExpanded":
				App.setExpandedMode(!App.isExpandedMode());
				break;
         case "qmcHKhelp":
            this.hotkeyHelp()
            break;
         default:
            console.warn("quickMenuGo: unknown action:", opid);
      }
   },
}

