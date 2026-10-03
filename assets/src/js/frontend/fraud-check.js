/**
 * Eilmo Checkout Flow - one-time live phone risk check.
 *
 * The browser never calculates risk. It transports a server-signed result
 * and adjusts payment choices for clarity; final enforcement is server-side.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	/*
	 * A page can contain both a shortcode/widget checkout and the product-page
	 * Quick Checkout integration. Guard the controller globally so optimization
	 * plugins or a mixed bundle/fallback queue cannot create two independent
	 * verification state stores for the same checkout.
	 */
	const runtimeModules = window.eilmoCfRuntimeModules && 'object' === typeof window.eilmoCfRuntimeModules
		? window.eilmoCfRuntimeModules
		: {};
	window.eilmoCfRuntimeModules = runtimeModules;
	if (true === runtimeModules.liveFraud) {
		return;
	}
	runtimeModules.liveFraud = true;

	const states = new WeakMap();

	function getConfig() {
		return window.eilmoCf && window.eilmoCf.liveFraud
			? window.eilmoCf.liveFraud
			: {};
	}

	function isEnabled() {
		return 'yes' === getConfig().enabled;
	}

	function normalizePhone(value) {
		let digits = String(value || '').replace(/\D+/g, '');
		if (13 === digits.length && 0 === digits.indexOf('880')) {
			digits = `0${digits.slice(3)}`;
		}
		if (10 === digits.length && '1' === digits.charAt(0)) {
			digits = `0${digits}`;
		}
		return /^01[3-9][0-9]{8}$/.test(digits) ? digits : '';
	}

	function getState(checkout) {
		if (!states.has(checkout)) {
			states.set(checkout, {
				phone: '',
				token: '',
				decision: '',
				message: '',
				checking: false,
				timer: 0,
				requestId: 0,
				paymentAvailable: false,
				allowedPaymentTypes: [],
				paymentDisplayMode: 'always',
			});
		}
		return states.get(checkout);
	}

	function getStatus(checkout) {
		let status = checkout.querySelector('[data-eilmo-live-fraud-status]');
		if (!(window.eilmoCfDom.isElement(status))) {
			status = document.createElement('div');
			status.setAttribute('data-eilmo-live-fraud-status', '');
			status.setAttribute('role', 'status');
			status.className = 'eilmo-cf-live-fraud-status';
			const phone = checkout.querySelector('[data-eilmo-customer-phone]');
			/*
			 * The input itself owns data-eilmo-customer-field, so targeting
			 * that selector would attempt to append inside <input> and throw
			 * before the AJAX request starts. Always target the field wrapper.
			 */
			const target = window.eilmoCfDom.isElement(phone)
				? (phone.closest('[data-eilmo-customer-field-wrap]') || phone.parentElement)
				: null;
			if (target) {
				target.appendChild(status);
			}
		}
		return window.eilmoCfDom.isElement(status) ? status : null;
	}

	function render(checkout, message, kind) {
		const status = getStatus(checkout);
		if (!status) {
			return;
		}
		status.textContent = window.eilmoCfLanguage ? window.eilmoCfLanguage.copy(checkout, message || '') : message || '';
		status.hidden = !message;
		status.className = 'eilmo-cf-live-fraud-status';
		if (kind) {
			status.classList.add(`eilmo-cf-live-fraud-status--${kind}`);
		}
	}

	function rememberOption(option, input) {
		if (!Object.prototype.hasOwnProperty.call(option.dataset, 'eilmoFraudHidden')) {
			option.dataset.eilmoFraudHidden = option.hidden ? 'yes' : 'no';
		}
		if (!Object.prototype.hasOwnProperty.call(input.dataset, 'eilmoFraudDisabled')) {
			input.dataset.eilmoFraudDisabled = input.disabled ? 'yes' : 'no';
		}
	}

	function restorePaymentOptions(checkout) {
		checkout.querySelectorAll('[data-eilmo-payment-option]').forEach((option) => {
			const input = option.querySelector('[data-eilmo-payment-type]');
			if (!(window.eilmoCfDom.isElement(option)) || !(window.eilmoCfDom.isElement(input, 'INPUT'))) {
				return;
			}
			if (Object.prototype.hasOwnProperty.call(option.dataset, 'eilmoFraudHidden')) {
				option.hidden = 'yes' === option.dataset.eilmoFraudHidden;
				delete option.dataset.eilmoFraudHidden;
			}
			if (Object.prototype.hasOwnProperty.call(input.dataset, 'eilmoFraudDisabled')) {
				input.disabled = 'yes' === input.dataset.eilmoFraudDisabled;
				delete input.dataset.eilmoFraudDisabled;
			}
			option.classList.remove('eilmo-cf-advance-payment-option--fraud-disabled');
			option.removeAttribute('aria-disabled');
		});
	}

	function restorePaymentSection(checkout) {
		const section = checkout.querySelector('[data-eilmo-advance-payment]');
		if (!(window.eilmoCfDom.isElement(section))) {
			return;
		}
		if (Object.prototype.hasOwnProperty.call(section.dataset, 'eilmoFraudHidden')) {
			section.hidden = 'yes' === section.dataset.eilmoFraudHidden;
			delete section.dataset.eilmoFraudHidden;
		}
		section.classList.remove('eilmo-cf-advance-payment--fraud-pending');
	}

	function rememberPaymentSection(checkout) {
		const section = checkout.querySelector('[data-eilmo-advance-payment]');
		if (window.eilmoCfDom.isElement(section) && !Object.prototype.hasOwnProperty.call(section.dataset, 'eilmoFraudHidden')) {
			section.dataset.eilmoFraudHidden = section.hidden ? 'yes' : 'no';
		}
		return window.eilmoCfDom.isElement(section) ? section : null;
	}

	function normalizePaymentTypes(types) {
		return Array.isArray(types)
			? [...new Set(types.map((type) => String(type || '')).filter((type) => ['cash_on_delivery', 'advance', 'full'].includes(type)))]
			: [];
	}

	function setPendingPaymentState(checkout) {
		const mode = 'conditional' === getConfig().paymentDisplayMode ? 'conditional' : 'always';
		restorePaymentOptions(checkout);
		restorePaymentSection(checkout);
		const section = rememberPaymentSection(checkout);
		if (!(window.eilmoCfDom.isElement(section))) {
			return;
		}
		if ('conditional' === mode) {
			section.hidden = true;
			return;
		}

		section.classList.add('eilmo-cf-advance-payment--fraud-pending');
		checkout.querySelectorAll('[data-eilmo-payment-option]').forEach((option) => {
			const input = option.querySelector('[data-eilmo-payment-type]');
			if (window.eilmoCfDom.isElement(option) && window.eilmoCfDom.isElement(input, 'INPUT')) {
				rememberOption(option, input);
				input.disabled = true;
				option.classList.add('eilmo-cf-advance-payment-option--fraud-disabled');
				option.setAttribute('aria-disabled', 'true');
			}
		});
	}

	function applyPaymentRule(checkout, allowedTypes, displayMode) {
		const allowed = normalizePaymentTypes(allowedTypes);
		const mode = 'conditional' === displayMode ? 'conditional' : 'always';
		restorePaymentOptions(checkout);
		restorePaymentSection(checkout);
		const section = rememberPaymentSection(checkout);
		if (window.eilmoCfDom.isElement(section)) {
			section.hidden = false;
		}

		let availableInput = null;
		let optionCount = 0;
		checkout.querySelectorAll('[data-eilmo-payment-option]').forEach((option) => {
			const input = option.querySelector('[data-eilmo-payment-type]');
			if (!(window.eilmoCfDom.isElement(option)) || !(window.eilmoCfDom.isElement(input, 'INPUT'))) {
				return;
			}
			optionCount += 1;
			rememberOption(option, input);
			const accepted = allowed.includes(input.value) && !input.disabled;
			if (!accepted) {
				if ('conditional' === mode) {
					option.hidden = true;
				}
				input.disabled = true;
				option.classList.add('eilmo-cf-advance-payment-option--fraud-disabled');
				option.setAttribute('aria-disabled', 'true');
			} else if (!availableInput) {
				availableInput = input;
			}
		});

		if (0 === optionCount) {
			return allowed.includes('full');
		}

		const checked = checkout.querySelector('[data-eilmo-payment-type]:checked');
		if (!(window.eilmoCfDom.isElement(checked, 'INPUT')) || checked.disabled || !allowed.includes(checked.value)) {
			if (!(window.eilmoCfDom.isElement(availableInput, 'INPUT'))) {
				return false;
			}
			availableInput.checked = true;
			availableInput.dispatchEvent(new Event('change', { bubbles: true }));
		}
		if (window.eilmoCfAdvancePayment && 'function' === typeof window.eilmoCfAdvancePayment.refresh) {
			window.eilmoCfAdvancePayment.refresh(checkout);
		}
		return window.eilmoCfDom.isElement(availableInput, 'INPUT') || (window.eilmoCfDom.isElement(checked, 'INPUT') && !checked.disabled && allowed.includes(checked.value));
	}

	function setBlocked(checkout, blocked) {
		checkout.dataset.fraudBlocked = blocked ? 'yes' : 'no';
		checkout.querySelectorAll('[data-eilmo-order-submit]').forEach(function (button) {
			button.disabled = blocked || checkout.dataset.emptySelection === 'yes' || button.getAttribute('aria-busy') === 'true';
			button.setAttribute('aria-disabled', button.disabled ? 'true' : 'false');
		});
	}

	function reset(checkout, phone) {
		const state = getState(checkout);
		const mode = 'conditional' === getConfig().paymentDisplayMode ? 'conditional' : 'always';
		state.phone = phone || '';
		state.token = '';
		state.decision = '';
		state.message = '';
		state.checking = false;
		state.paymentAvailable = false;
		state.allowedPaymentTypes = [];
		state.paymentDisplayMode = mode;
		state.requestId += 1;
		setPendingPaymentState(checkout);
		setBlocked(checkout, true);
		render(checkout, '', '');
	}

	async function check(checkout, phone) {
		const state = getState(checkout);
		const config = getConfig();
		if (!isEnabled() || !phone || (state.phone === phone && state.token)) {
			return;
		}
		reset(checkout, phone);
		state.checking = true;
		const requestId = state.requestId;
		render(checkout, config.checkingMessage || 'Checking delivery history…', 'checking');

		const body = new URLSearchParams();
		body.set('action', config.action || 'eilmo_cf_live_fraud_check');
		body.set('nonce', window.eilmoCf && window.eilmoCf.ajaxNonce ? window.eilmoCf.ajaxNonce : '');
		body.set('phone', phone);

		try {
			const response = await fetch(window.eilmoCf.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString(),
			});
			const responseText = await response.text();
			let result;
			try {
				result = JSON.parse(responseText);
			} catch (parseError) {
				throw new Error(config.checkFailedMessage || 'Phone verification failed. Please try again.');
			}
			if (requestId !== state.requestId || phone !== state.phone) {
				return;
			}
			if (!result || !result.success || !result.data || !result.data.token) {
				throw new Error(result && result.data && result.data.message ? result.data.message : (config.checkFailedMessage || 'Phone verification failed. Please try again.'));
			}
			state.checking = false;
			state.token = String(result.data.token);
			state.decision = String(result.data.decision || 'allow');
			state.message = String(result.data.message || 'Phone verification completed.');
			state.allowedPaymentTypes = normalizePaymentTypes(result.data.allowedPaymentTypes);
			state.paymentDisplayMode = 'conditional' === result.data.paymentDisplayMode ? 'conditional' : 'always';
			state.paymentAvailable = applyPaymentRule(checkout, state.allowedPaymentTypes, state.paymentDisplayMode);
			setBlocked(checkout, 'block' === state.decision || !state.paymentAvailable);
			render(checkout, state.paymentAvailable ? state.message : (window.eilmoCfLanguage ? window.eilmoCfLanguage.copy(checkout, 'An advance payment is required for this order.') : 'The required payment option is unavailable.'), state.paymentAvailable ? state.decision : 'error');
		} catch (error) {
			if (requestId !== state.requestId) {
				return;
			}
			state.checking = false;
			state.token = '';
			state.decision = '';
			state.message = error instanceof Error ? error.message : 'Phone verification failed.';
			render(checkout, state.message, 'error');
		}
	}

	function schedule(checkout) {
		const state = getState(checkout);
		const config = getConfig();
		window.clearTimeout(state.timer);
		const input = checkout.querySelector('[data-eilmo-customer-phone]');
		const phone = window.eilmoCfDom.isElement(input, 'INPUT') ? normalizePhone(input.value) : '';
		if (!phone) {
			reset(checkout, '');
			render(checkout, config.phoneRequiredMessage || 'Enter a valid phone number to see available payment options.', 'pending');
			return;
		}
		if (state.phone === phone && (state.token || state.checking)) {
			return;
		}
		state.timer = window.setTimeout(() => check(checkout, phone), Math.max(300, Number(config.debounce || 700)));
	}

	function init(checkout) {
		if (!isEnabled() || !(window.eilmoCfDom.isElement(checkout))) {
			return;
		}
		const input = checkout.querySelector('[data-eilmo-customer-phone]');
		if (!(window.eilmoCfDom.isElement(input, 'INPUT')) || 'yes' === input.dataset.eilmoFraudBound) {
			return;
		}
		input.dataset.eilmoFraudBound = 'yes';
		input.addEventListener('input', () => schedule(checkout));
		input.addEventListener('change', () => schedule(checkout));
		schedule(checkout);
	}

	function initialize(scope = document) {
		if (!isEnabled()) {
			return;
		}

		if (window.eilmoCfDom.isElement(scope) && scope.matches('[data-eilmo-checkout]')) {
			init(scope);
		}

		if (scope && 'function' === typeof scope.querySelectorAll) {
			scope.querySelectorAll('[data-eilmo-checkout]').forEach(init);
		}
	}

	window.eilmoCfFraudCheck = {
		getData(checkout) {
			if (!isEnabled()) {
				return {};
			}
			const state = getState(checkout);
			return { token: state.token };
		},
		validate(checkout) {
			if (!isEnabled()) {
				return true;
			}
			const input = checkout.querySelector('[data-eilmo-customer-phone]');
			const phone = window.eilmoCfDom.isElement(input, 'INPUT') ? normalizePhone(input.value) : '';
			const state = getState(checkout);
			const config = getConfig();
			if (!phone || state.phone !== phone || !state.token || state.checking || 'block' === state.decision || !state.paymentAvailable) {
				render(checkout, state.message || config.checkRequiredMessage || 'Please complete phone verification before placing the order.', 'error');
				return false;
			}
			return true;
		},
		refresh: init,
	};

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', () => initialize(document));
	} else {
		initialize(document);
	}

	/* Rebind after Elementor editor/frontend widget renders. */
	let elementorHookRegistered = false;
	function registerElementorHook() {
		if (elementorHookRegistered || !window.elementorFrontend || !window.elementorFrontend.hooks || 'function' !== typeof window.elementorFrontend.hooks.addAction) {
			return;
		}
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/eilmo-checkout-flow.default',
			($scope) => initialize($scope && $scope[0] instanceof Element ? $scope[0] : document)
		);
		elementorHookRegistered = true;
	}

	registerElementorHook();
	if (window.jQuery && 'function' === typeof window.jQuery) {
		window.jQuery(window).on('elementor/frontend/init', registerElementorHook);
	}

	/* Cover popup, shortcode fragments and Elementor DOM replacements. */
	if (window.MutationObserver && document.documentElement) {
		new MutationObserver((mutations) => {
			mutations.forEach((mutation) => {
				mutation.addedNodes.forEach((node) => {
					if (window.eilmoCfDom.isElement(node)) {
						initialize(node);
					}
				});
			});
		}).observe(document.documentElement, { childList: true, subtree: true });
	}
}());
