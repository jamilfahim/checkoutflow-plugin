(function ($) {
	'use strict';

	var config = window.eilmoCFNativeFraud || {};
	var state = new WeakMap();

	function checkoutForm() {
		return document.querySelector('form.checkout, form.woocommerce-checkout');
	}

	function normalizePhone(value) {
		var digits = String(value || '').replace(/\D+/g, '');
		if (13 === digits.length && 0 === digits.indexOf('880')) digits = '0' + digits.slice(3);
		if (10 === digits.length && '1' === digits.charAt(0)) digits = '0' + digits;
		return /^01[3-9][0-9]{8}$/.test(digits) ? digits : '';
	}

	function getState(form) {
		if (!state.has(form)) {
			state.set(form, { phone: '', token: '', timer: 0, request: 0, allowed: [], checking: false });
		}
		return state.get(form);
	}

	function tokenField(form) {
		return form ? form.querySelector('[data-eilmo-native-fraud-token]') : null;
	}

	function statusNode(form) {
		var phone = form.querySelector('#billing_phone, input[name="billing_phone"]');
		if (!(phone instanceof HTMLInputElement)) return null;
		var wrap = phone.closest('.form-row') || phone.parentElement;
		if (!wrap) return null;
		var node = wrap.querySelector('[data-eilmo-native-fraud-status]');
		if (!node) {
			node = document.createElement('div');
			node.className = 'eilmo-cf-native-fraud-status';
			node.setAttribute('data-eilmo-native-fraud-status', '');
			node.setAttribute('role', 'status');
			wrap.appendChild(node);
		}
		return node;
	}

	function showStatus(form, message, kind) {
		var node = statusNode(form);
		if (!node) return;
		node.textContent = String(message || '');
		node.hidden = !message;
		node.className = 'eilmo-cf-native-fraud-status' + (kind ? ' eilmo-cf-native-fraud-status--' + kind : '');
	}

	function paymentInputs(form) {
		return Array.prototype.slice.call(form.querySelectorAll('[data-eilmo-native-payment-type]'));
	}

	function applyAllowed(form, allowed, displayMode) {
		var normalized = Array.isArray(allowed) ? allowed.map(String) : [];
		var mode = 'conditional' === String(displayMode || '') ? 'conditional' : 'always';
		var first = null;
		paymentInputs(form).forEach(function (input) {
			var card = input.closest('[data-eilmo-native-payment-option]');
			var ok = normalized.indexOf(String(input.value || '')) !== -1;
			input.disabled = !ok;
			if (card) {
				card.hidden = !ok && 'conditional' === mode;
				card.classList.toggle('is-fraud-disabled', !ok);
				card.setAttribute('aria-disabled', ok ? 'false' : 'true');
			}
			if (ok && !first) first = input;
		});
		var current = form.querySelector('[data-eilmo-native-payment-type]:checked');
		if (!(current instanceof HTMLInputElement) || current.disabled) {
			if (first instanceof HTMLInputElement) {
				first.checked = true;
				$(first).trigger('change');
				return true;
			}
			return false;
		}
		return true;
	}

	function setPending(form, pending) {
		var isPending = Boolean(pending);
		var controls = form.querySelector('[data-eilmo-native-payment-controls]');
		if (controls) {
			controls.classList.toggle('is-fraud-pending', isPending);
			controls.setAttribute('aria-busy', isPending ? 'true' : 'false');
		}

		/*
		 * Keep WooCommerce's real gateway radios enabled so update_checkout can
		 * serialize the chosen payment method, but make both Eilmo payment
		 * surfaces inert while the signed phone decision is pending. `inert`
		 * blocks pointer and keyboard interaction without changing form values.
		 */
		['[data-eilmo-native-payment-options]', '[data-eilmo-native-payment-methods]'].forEach(function (selector) {
			var section = form.querySelector(selector);
			if (!(section instanceof HTMLElement)) return;
			section.toggleAttribute('inert', isPending);
			section.setAttribute('aria-disabled', isPending ? 'true' : 'false');
		});

		paymentInputs(form).forEach(function (input) {
			if (isPending) {
				if (!Object.prototype.hasOwnProperty.call(input.dataset, 'eilmoNativeFraudWasDisabled')) {
					input.dataset.eilmoNativeFraudWasDisabled = input.disabled ? 'yes' : 'no';
				}
				input.disabled = true;
			} else if (Object.prototype.hasOwnProperty.call(input.dataset, 'eilmoNativeFraudWasDisabled')) {
				input.disabled = 'yes' === input.dataset.eilmoNativeFraudWasDisabled;
				delete input.dataset.eilmoNativeFraudWasDisabled;
			}
		});
	}

	function clearToken(form) {
		var field = tokenField(form);
		if (field instanceof HTMLInputElement) field.value = '';
	}

	function schedule(form) {
		var st = getState(form);
		window.clearTimeout(st.timer);
		var phoneInput = form.querySelector('#billing_phone, input[name="billing_phone"]');
		var phone = phoneInput instanceof HTMLInputElement ? normalizePhone(phoneInput.value) : '';
		if (!phone) {
			st.phone = '';
			st.token = '';
			st.allowed = [];
			clearToken(form);
			setPending(form, true);
			showStatus(form, config.phoneRequiredMessage || 'Enter a valid phone number to see available payment options.', 'pending');
			return;
		}
		if (st.phone === phone && (st.token || st.checking)) return;
		st.timer = window.setTimeout(function () { verify(form, phone); }, Math.max(250, Number(config.debounce || 700)));
	}

	async function verify(form, phone) {
		var st = getState(form);
		st.phone = phone;
		st.token = '';
		st.checking = true;
		st.request += 1;
		var requestId = st.request;
		clearToken(form);
		setPending(form, true);
		showStatus(form, config.checkingMessage || 'Checking delivery history…', 'checking');

		var body = new URLSearchParams();
		body.set('action', String(config.action || 'eilmo_cf_live_fraud_check'));
		body.set('nonce', String(config.ajaxNonce || ''));
		body.set('phone', phone);

		try {
			var response = await fetch(String(config.ajaxUrl || ''), {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			});
			var result = await response.json();
			if (requestId !== st.request || phone !== st.phone) return;
			if (!result || !result.success || !result.data || !result.data.token) {
				throw new Error(result && result.data && result.data.message ? result.data.message : (config.checkFailedMessage || 'Phone verification failed. Please try again.'));
			}
			st.checking = false;
			st.token = String(result.data.token || '');
			st.allowed = Array.isArray(result.data.allowedPaymentTypes) ? result.data.allowedPaymentTypes.map(String) : [];
			var field = tokenField(form);
			if (field instanceof HTMLInputElement) field.value = st.token;
			setPending(form, false);
			var available = applyAllowed(form, st.allowed, result.data.paymentDisplayMode || config.paymentDisplayMode);
			var blocked = 'block' === String(result.data.decision || '') || !available;
			showStatus(
				form,
				blocked ? (result.data.message || config.blockedMessage || 'Ordering is unavailable for this phone number.') : (result.data.message || 'Phone verification completed.'),
				blocked ? 'error' : 'success'
			);
		} catch (error) {
			if (requestId !== st.request) return;
			st.checking = false;
			st.token = '';
			clearToken(form);
			setPending(form, false);
			showStatus(form, error instanceof Error ? error.message : (config.checkFailedMessage || 'Phone verification failed. Please try again.'), 'error');
		}
	}

	function init() {
		var form = checkoutForm();
		if (!form) return;
		var phone = form.querySelector('#billing_phone, input[name="billing_phone"]');
		if (!(phone instanceof HTMLInputElement)) return;
		if ('yes' !== phone.dataset.eilmoNativeFraudBound) {
			phone.dataset.eilmoNativeFraudBound = 'yes';
			phone.addEventListener('input', function () { schedule(form); });
			phone.addEventListener('change', function () { schedule(form); });
		}
		schedule(form);
	}

	$(document.body).on('updated_checkout', function () {
		window.setTimeout(init, 0);
	});

	$(init);
})(jQuery);
