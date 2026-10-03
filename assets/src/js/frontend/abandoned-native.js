/**
 * Native WooCommerce Abandoned Checkout tracking.
 * Supports Classic Checkout and the Checkout Block.
 */
(function () {
	'use strict';

	const config = window.eilmoCfNativeAbandoned || {};
	const COOKIE_NAME = 'eilmo_cf_abandoned_native_token';
	const STORAGE_KEY = 'eilmo_cf_abandoned_native:' + window.location.pathname;
	let timer = null;
	let inFlight = false;
	let pending = false;

	if (!config.ajaxUrl || !config.action || !config.nonce) {
		return;
	}

	function root() {
		return document.querySelector(
			'.wc-block-checkout, .wp-block-woocommerce-checkout, form.checkout'
		);
	}

	function source() {
		return document.querySelector('.wc-block-checkout, .wp-block-woocommerce-checkout')
			? 'block'
			: 'classic';
	}

	function generateToken() {
		if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
			const bytes = new Uint8Array(32);
			window.crypto.getRandomValues(bytes);
			return Array.from(bytes)
				.map((value) => value.toString(16).padStart(2, '0'))
				.join('');
		}

		let token = '';
		while (token.length < 64) {
			token += Math.floor(Math.random() * 0xffffffff)
				.toString(16)
				.padStart(8, '0');
		}
		return token.slice(0, 64).toLowerCase();
	}

	function validToken(token) {
		return typeof token === 'string' && /^[a-f0-9]{64}$/i.test(token);
	}

	function setCookie(token) {
		let cookie = COOKIE_NAME + '=' + encodeURIComponent(token) + '; path=/; SameSite=Lax';
		if (window.location.protocol === 'https:') {
			cookie += '; Secure';
		}
		document.cookie = cookie;
	}

	function getToken() {
		let token = '';
		try {
			token = String(window.sessionStorage.getItem(STORAGE_KEY) || '').trim().toLowerCase();
		} catch (error) {
			token = '';
		}

		if (!validToken(token)) {
			token = generateToken();
			try {
				window.sessionStorage.setItem(STORAGE_KEY, token);
			} catch (error) {
				// Cookie still keeps the native checkout/order cleanup link.
			}
		}

		setCookie(token);
		return token;
	}

	function queryValue(selectors) {
		const checkout = root();
		for (const selector of selectors) {
			const field = checkout ? checkout.querySelector(selector) : document.querySelector(selector);
			if (field && 'value' in field) {
				const value = String(field.value || '').trim();
				if (value) {
					return value;
				}
			}
		}
		return '';
	}

	function customer() {
		const data = {
			billing_first_name: queryValue([
				'[name="billing_first_name"]', '#billing_first_name',
				'[name="billing-first_name"]', '#billing-first_name'
			]),
			billing_last_name: queryValue([
				'[name="billing_last_name"]', '#billing_last_name',
				'[name="billing-last_name"]', '#billing-last_name'
			]),
			billing_phone: queryValue([
				'[name="billing_phone"]', '#billing_phone',
				'[name="billing-phone"]', '#billing-phone',
				'input[type="tel"]'
			]),
			billing_email: queryValue([
				'[name="billing_email"]', '#billing_email',
				'[name="billing-email"]', '#billing-email',
				'input[type="email"]'
			]),
			billing_address_1: queryValue([
				'[name="billing_address_1"]', '#billing_address_1',
				'[name="billing-address_1"]', '#billing-address_1'
			]),
			billing_city: queryValue([
				'[name="billing_city"]', '#billing_city',
				'[name="billing-city"]', '#billing-city'
			]),
		};

		/* Checkout Block often hides billing address when it matches shipping. */
		if (!data.billing_address_1) {
			data.billing_address_1 = queryValue([
				'[name="shipping_address_1"]', '#shipping_address_1',
				'[name="shipping-address_1"]', '#shipping-address_1'
			]);
		}
		if (!data.billing_city) {
			data.billing_city = queryValue([
				'[name="shipping_city"]', '#shipping_city',
				'[name="shipping-city"]', '#shipping-city'
			]);
		}

		return data;
	}

	function paymentMethod() {
		const selected = document.querySelector(
			'input[name="payment_method"]:checked, input[name="radio-control-wc-payment-method-options"]:checked'
		);
		return selected && 'value' in selected ? String(selected.value || '').trim() : '';
	}

	function deliveryMethod() {
		const selected = document.querySelector(
			'input[name^="shipping_method"]:checked, input[name*="shipping-rate"]:checked'
		);
		return selected && 'value' in selected ? String(selected.value || '').trim() : '';
	}

	function snapshot() {
		const data = customer();
		return Object.assign(data, {
			checkout_source: source(),
			delivery_method: deliveryMethod(),
			payment_method: paymentMethod(),
			subtotal: Number(config.subtotal || 0),
			total: Number(config.total || 0),
			cart_snapshot: {
				items: Array.isArray(config.items) ? config.items : [],
				combo_offers: [],
				order_bumps: [],
			},
		});
	}

	function meaningful(data) {
		let phone = String(data.billing_phone || '').replace(/\D+/g, '');
		if (phone.length === 13 && phone.indexOf('880') === 0) {
			phone = '0' + phone.slice(3);
		}
		if (phone.length === 10 && phone.charAt(0) === '1') {
			phone = '0' + phone;
		}
		return /^01[3-9][0-9]{8}$/.test(phone);
	}

	async function track() {
		const checkout = root();
		if (!checkout) {
			return;
		}

		const data = snapshot();
		if (!meaningful(data)) {
			return;
		}

		if (inFlight) {
			pending = true;
			return;
		}

		inFlight = true;
		pending = false;

		const body = new FormData();
		body.append('action', config.action);
		body.append('nonce', config.nonce);
		body.append('tracking_token', getToken());

		Object.keys(data).forEach(function (key) {
			const value = data[key];
			body.append(
				'checkout[' + key + ']',
				typeof value === 'object' ? JSON.stringify(value) : String(value == null ? '' : value)
			);
		});

		try {
			await fetch(config.ajaxUrl, {
				method: 'POST',
				body: body,
				credentials: 'same-origin',
			});
		} catch (error) {
			// Tracking must never interfere with checkout.
		} finally {
			inFlight = false;
			if (pending) {
				pending = false;
				schedule();
			}
		}
	}

	function schedule() {
		window.clearTimeout(timer);
		timer = window.setTimeout(track, Math.max(250, Number(config.debounce || 900)));
	}

	function relevantTarget(target) {
		if (!(target instanceof Element)) {
			return false;
		}
		const checkout = root();
		return !!checkout && checkout.contains(target);
	}

	document.addEventListener('input', function (event) {
		if (relevantTarget(event.target)) {
			schedule();
		}
	});

	document.addEventListener('change', function (event) {
		if (relevantTarget(event.target)) {
			schedule();
		}
	});
})();
