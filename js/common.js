'use strict';

/* global App, __csrf_token */

/* exported __ */
function __(msg) {
	if (typeof App !== "undefined") {
		return App.l10n.__(msg);
	} else {
		return msg;
	}
}

/* exported ngettext */
function ngettext(msg1, msg2, n) {
	return __((parseInt(n) > 1) ? msg2 : msg1);
}

// polyfill for safari https://raw.githubusercontent.com/pladaria/requestidlecallback-polyfill/master/index.js
window.requestIdleCallback =
	window.requestIdleCallback ||
		function (callback) {
			const start = Date.now();
			return setTimeout(() => {
				callback({
					didTimeout: false,
					timeRemaining: () => Math.max(0, 50 - (Date.now() - start))
					},
				);
			}, 1);
		};

window.cancelIdleCallback =
	window.cancelIdleCallback ||
		function (id) {
			clearTimeout(id);
		};

Element.prototype.setStyle = function(args) {
  Object.assign(this.style, args);
};

Element.prototype.show = function() {
	this.style.display = '';
};

Element.prototype.hide = function() {
	this.style.display = 'none';
};

Element.prototype.toggle = function() {
	this.visible() ? this.hide() : this.show();
};

// https://gist.github.com/alirezas/c4f9f43e9fe1abba9a4824dd6fc60a55
Element.prototype.fadeOut = function() {
	this.style.opacity = 1;
	const self = this;

	(function fade() {
		if ((self.style.opacity -= 0.1) < 0) {
			self.style.display = "none";
		} else {
			window.requestAnimationFrame(fade);
		}
	}());
};

Element.prototype.fadeIn = function(display = undefined){
	this.style.opacity = 0;
	this.style.display = display === undefined ? "block" : display;
	const self = this;

	(function fade() {
		let val = parseFloat(self.style.opacity);
		if (!((val += 0.1) > 1)) {
			self.style.opacity = val;
			window.requestAnimationFrame(fade);
		}
	}());
};

Element.prototype.visible = function() {
	return window.getComputedStyle(this).display !== "none"; //&& this.offsetHeight !== 0 && this.offsetWidth !== 0;
}

Element.visible = function(elem) {
	if (typeof elem === "string")
		elem = document.getElementById(elem);

	return elem.visible();
}

Element.show = function(elem) {
	if (typeof elem === "string")
		elem = document.getElementById(elem);

	return elem.show();
}

Element.hide = function(elem) {
	if (typeof elem === "string")
		elem = document.getElementById(elem);

	return elem.hide();
}

Element.toggle = function(elem) {
	if (typeof elem === "string")
		elem = document.getElementById(elem);

	return elem.toggle();
}

/* exported xhr */
const xhr = {
	_ts: 0,
	post: function(url, params = {}, complete = undefined, failed = undefined) {
		this._ts = new Date().getTime();

		return new Promise((resolve, reject) => {
			if (typeof __csrf_token !== "undefined")
				params = {...params, ...{csrf_token: __csrf_token}};

			dojo.xhrPost({url: url,
				postData: dojo.objectToQuery(params),
				handleAs: "text",
				error: function(error) {
					if (failed !== undefined)
						failed(error);

					reject(error);
				},
				load: function(data, ioargs) {
					if (typeof complete === 'function')
						complete(data, ioargs.xhr);

					resolve(data)
				}}
			);
		});
	},
	json: function(url, params = {}, complete = undefined, failed = undefined) {
		return new Promise((resolve, reject) =>
			this.post(url, params, null, failed).then((data) => {
				let obj;

				try {
					obj = JSON.parse(data);
				} catch (e) {
					console.error('xhr.json: JSON parse error', e);

					if (typeof failed === 'function')
						failed(e);

					reject(e);
					return;
				}

				if (obj && typeof App !== 'undefined') {
					if (!App.handleRpcJson(obj)) {

						if (typeof failed === 'function')
							failed(obj);

						reject(obj);
						return;
					}
				}

				if (typeof complete === 'function')
					complete(obj);

				resolve(obj);
			}));
	}
};

/* exported xhrPost */
function xhrPost(url, params = {}, complete = undefined) {
	return new Promise((resolve, reject) => {
		if (typeof __csrf_token !== "undefined")
			params = {...params, ...{csrf_token: __csrf_token}};

		dojo.xhrPost({url: url,
			postData: dojo.objectToQuery(params),
			handleAs: "text",
			error: function(error) {
				reject(error);
			},
			load: function(data, ioargs) {
				if (complete !== undefined)
					complete(ioargs.xhr);

				resolve(ioargs.xhr)
			}});
	});
}

/* exported xhrJson */
function xhrJson(url, params = {}, complete = undefined) {
	return xhr.json(url, params, complete);
}

/* common helpers not worthy of separate Dojo modules */

/* exported Lists */
const Lists = {
	onRowChecked: function(elem) {
		// account for dojo checkboxes
		const checked = elem.domNode ? elem.attr('checked') : elem.checked;
		elem = elem.domNode || elem;
		elem.closest('li')?.classList.toggle('Selected', checked);
	},
	select: function(elem, selected) {
		if (typeof elem === "string")
			elem = document.getElementById(elem);

		elem.querySelectorAll("li").forEach((row) => {
			const checkNode = row.querySelector(".dijitCheckBox,input[type=checkbox]");
			if (checkNode) {
				const widget = dijit.getEnclosingWidget(checkNode);

				if (widget) {
					widget.attr("checked", selected);
				} else {
					checkNode.checked = selected;
				}

				this.onRowChecked(widget);
			}
		});
	},
	getSelected: function(elem) {
		const rv = [];

		if (typeof elem === "string")
			elem = document.getElementById(elem);

		elem.querySelectorAll("li").forEach((row) => {
			if (row.classList.contains('Selected')) {
				const rowVal = row.getAttribute("data-row-value");

				if (rowVal) {
					rv.push(rowVal);
				} else {
					// either older prefix-XXX notation or separate attribute
					const rowId = row.getAttribute("data-row-id") || row.id.replace(/^[A-Z]*?-/, "");

					if (!isNaN(rowId))
						rv.push(parseInt(rowId));
				}
			}
		});

		return rv;
	}
};

/* exported Tables */
const Tables = {
	onRowChecked: function(elem) {
		// account for dojo checkboxes
		const checked = elem.domNode ? elem.attr('checked') : elem.checked;
		elem = elem.domNode || elem;
		elem.closest('tr')?.classList.toggle('Selected', checked);
	},
	select: function(elem, selected) {
		if (typeof elem === "string")
			elem = document.getElementById(elem);

		elem.querySelectorAll("tr").forEach((row) => {
			const checkNode = row.querySelector(".dijitCheckBox,input[type=checkbox]");
			if (checkNode) {
				const widget = dijit.getEnclosingWidget(checkNode);

				if (widget) {
					widget.attr("checked", selected);
				} else {
					checkNode.checked = selected;
				}

				this.onRowChecked(widget);
			}
		});
	},
	getSelected: function(elem) {
		const rv = [];

		if (typeof elem === "string")
			elem = document.getElementById(elem);

		elem.querySelectorAll("tr").forEach((row) => {
			if (row.classList.contains('Selected')) {
				const rowVal = row.getAttribute("data-row-value");

				if (rowVal) {
					rv.push(rowVal);
				} else {
					// either older prefix-XXX notation or separate attribute
					const rowId = row.getAttribute("data-row-id") || row.id.replace(/^[A-Z]*?-/, "");

					if (!isNaN(rowId))
						rv.push(parseInt(rowId));
				}
			}
		});

		return rv;
	}
};

/* exported Cookie */
const Cookie = {
	set: function (name, value, lifetime) {
		const d = new Date();
		d.setTime(d.getTime() + lifetime * 1000);
		const expires = "expires=" + d.toUTCString();
		document.cookie = name + "=" + encodeURIComponent(value) + "; " + expires;
	},
	get: function (name) {
		name = name + "=";
		const ca = document.cookie.split(';');
		for (let i=0; i < ca.length; i++) {
			let c = ca[i];
			while (c.charAt(0) === ' ') c = c.substring(1);
			if (c.indexOf(name) === 0) return decodeURIComponent(c.substring(name.length, c.length));
		}
		return "";
	},
	delete: function(name) {
		const expires = "expires=Thu, 01-Jan-1970 00:00:01 GMT";
		document.cookie = name + "=; " + expires;
	}
};

/* runtime notifications */
/* exported Notify */
const Notify = {
	KIND_GENERIC: 0,
	KIND_INFO: 1,
	KIND_ERROR: 2,
	KIND_PROGRESS: 3,
	timeout: 0,
	default_timeout: 5 * 1000,
	close: function() {
		this.msg("");
	},
	msg: function(msg, keep, kind) {
		kind = kind || this.KIND_GENERIC;
		keep = keep || false;

		const notify = document.getElementById("notify");

		window.clearTimeout(this.timeout);

		if (!msg) {
			notify.classList.remove('visible');
			return;
		}

		let msgfmt = "<span class=\"msg\">%s</span>".replace("%s", __(msg));
		let icon;

		notify.className = "notify";

		switch (kind) {
			case this.KIND_INFO:
				notify.classList.add('notify_info')
				icon = 'notifications';
				break;
			case this.KIND_ERROR:
				notify.classList.add('notify_error');
				icon = 'error';
				break;
			case this.KIND_PROGRESS:
				notify.classList.add('notify_progress');
				icon = App.getInitParam('icon_oval');
				break;
			default:
				icon = 'notifications';
		}

		if (icon)
			if (icon.indexOf("data:image") !== -1)
				msgfmt = "<img src=\"%s\">".replace("%s", icon) + msgfmt;
			else
				msgfmt = "<i class='material-icons icon-notify'>%s</i>".replace("%s", icon) + msgfmt;

		msgfmt += "<i class='material-icons icon-close' title=\"" +
			__("Click to close") + "\" onclick=\"Notify.close()\">close</i>";

		notify.innerHTML = msgfmt;
		notify.classList.add('visible');

		if (!keep)
			this.timeout = window.setTimeout(() => {
				notify.classList.remove('visible');
			}, this.default_timeout);

	},
	info: function(msg, keep = false) {
		this.msg(msg, keep, this.KIND_INFO);
	},
	progress: function(msg, keep = true) {
		this.msg(msg, keep, this.KIND_PROGRESS);
	},
	error: function(msg, keep = true) {
		this.msg(msg, keep, this.KIND_ERROR);
	}
};

