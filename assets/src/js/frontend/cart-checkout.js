/**
 * Eilmo Cart Checkout bridge.
 *
 * Converts WooCommerce Cart Drawer selection into the existing Eilmo
 * Checkout Flow without creating a second order pipeline.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		root: '[data-eilmo-cart-checkout]',
		panel: '[data-eilmo-cart-checkout-panel]',
		close: '[data-eilmo-cart-checkout-close]',
		change: '[data-eilmo-cart-checkout-change]',
		loading: '[data-eilmo-cart-checkout-loading]',
		message: '[data-eilmo-cart-checkout-message]',
		content: '[data-eilmo-cart-checkout-content]',
		checkout: '[data-eilmo-checkout]',
		main: '.eilmo-cf-checkout__main',
		products: '.eilmo-cf-checkout__products',
		cartDrawer: '[data-eilmo-cart-drawer]',
		cartTrigger: '[data-eilmo-cart-drawer-trigger]',
	};

	const config =
		window.eilmoCfCartDrawer || {};

	let requestController = null;
	let preparing = false;
	let closeTimer = 0;
	let focusTimer = 0;
	let lastFocusTarget = null;
	const backgroundCheckouts = new Map();

	function getRoot() {
		const root =
			document.querySelector(
				SELECTORS.root
			);

		return root instanceof HTMLElement
			? root
			: null;
	}

	function getText(key, fallback) {
		const i18n =
			config.i18n &&
			typeof config.i18n === 'object'
				? config.i18n
				: {};

		return (
			typeof i18n[key] === 'string' &&
			i18n[key].trim() !== ''
		)
			? i18n[key]
			: fallback;
	}

	function buildRequest(action) {
		const body =
			new URLSearchParams();

		body.set(
			'action',
			String(action || '')
		);

		body.set(
			'nonce',
			String(config.nonce || '')
		);

		return body;
	}

	function getResponseData(payload) {
		return (
			payload &&
			payload.data &&
			typeof payload.data === 'object'
		)
			? payload.data
			: {};
	}

	function getResponseMessage(payload, fallback) {
		const data =
			getResponseData(payload);

		if (
			typeof data.message === 'string' &&
			data.message.trim() !== ''
		) {
			return data.message;
		}

		return fallback;
	}

	async function requestCheckout() {
		const action =
			config.actions &&
			config.actions.checkout
				? config.actions.checkout
				: '';

		if (
			!config.ajaxUrl ||
			!action
		) {
			throw new Error(
				getText(
					'checkoutError',
					'Unable to prepare checkout. Please try again.'
				)
			);
		}

		if (
			requestController instanceof
				AbortController
		) {
			requestController.abort();
		}

		requestController =
			new AbortController();

		const response = await fetch(
			config.ajaxUrl,
			{
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type':
						'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: buildRequest(action),
				signal: requestController.signal,
			}
		);

		let payload = null;

		try {
			payload =
				await response.json();
		} catch (error) {
			throw new Error(
				getText(
					'checkoutError',
					'Unable to prepare checkout. Please try again.'
				)
			);
		}

		const data =
			getResponseData(payload);

		if (
			response.status === 401 &&
			typeof data.login_url === 'string' &&
			data.login_url.trim() !== ''
		) {
			window.location.assign(
				data.login_url
			);

			return null;
		}

		if (
			!response.ok ||
			!payload ||
			payload.success !== true
		) {
			throw new Error(
				getResponseMessage(
					payload,
					getText(
						'checkoutError',
						'Unable to prepare checkout. Please try again.'
					)
				)
			);
		}

		return data;
	}

	function setLoading(loading) {
		const root = getRoot();

		if (!root) {
			return;
		}

		const loadingElement =
			root.querySelector(
				SELECTORS.loading
			);

		if (
			loadingElement instanceof HTMLElement
		) {
			loadingElement.hidden =
				!loading;
		}

		root.classList.toggle(
			'is-loading',
			Boolean(loading)
		);

		root.setAttribute(
			'aria-busy',
			loading ? 'true' : 'false'
		);
	}

	function showMessage(message) {
		const root = getRoot();

		if (!root) {
			return;
		}

		const target =
			root.querySelector(
				SELECTORS.message
			);

		if (!(target instanceof HTMLElement)) {
			return;
		}

		const text =
			String(message || '').trim();

		target.textContent = text;
		target.hidden = !text;
	}

	function clearContent() {
		const root = getRoot();

		if (!root) {
			return;
		}

		const content =
			root.querySelector(
				SELECTORS.content
			);

		if (content instanceof HTMLElement) {
			content.innerHTML = '';
		}
	}

	function openShell() {
		const root = getRoot();

		if (!root) {
			return false;
		}

		if (closeTimer) {
			window.clearTimeout(closeTimer);
			closeTimer = 0;
		}

		if (focusTimer) {
			window.clearTimeout(focusTimer);
			focusTimer = 0;
		}

		const externalTrigger =
			document.querySelector(
				SELECTORS.cartTrigger
			);

		lastFocusTarget =
			externalTrigger instanceof HTMLElement
				? externalTrigger
				: null;

		root.hidden = false;
		root.setAttribute(
			'aria-hidden',
			'false'
		);
		root.classList.remove('is-closing');
		document.querySelectorAll(SELECTORS.checkout).forEach(function (checkout) {
			if (root.contains(checkout) || backgroundCheckouts.has(checkout)) return;
			backgroundCheckouts.set(checkout, checkout.inert);
			checkout.inert = true;
			checkout.setAttribute('data-eilmo-modal-background', '');
		});

		document.body.classList.add(
			'eilmo-cf-cart-checkout-open'
		);

		window.requestAnimationFrame(
			function () {
				root.classList.add('is-open');
			}
		);

		/*
		 * Cart Drawer restores focus after its 300ms close animation.
		 * Focus this newer dialog just after that hand-off.
		 */
		focusTimer = window.setTimeout(
			function () {
				const close =
					root.querySelector(
						'.eilmo-cf-cart-checkout__close'
					);

				if (close instanceof HTMLElement) {
					close.focus({
						preventScroll: true,
					});
				}

				focusTimer = 0;
			},
			340
		);

		return true;
	}

	function closeCheckout(restoreFocus = true) {
		const root = getRoot();

		if (!root || root.hidden) {
			return;
		}

		if (
			requestController instanceof
				AbortController
		) {
			requestController.abort();
			requestController = null;
		}

		preparing = false;

		if (focusTimer) {
			window.clearTimeout(focusTimer);
			focusTimer = 0;
		}

		root.classList.remove('is-open');
		root.classList.add('is-closing');
		root.setAttribute(
			'aria-hidden',
			'true'
		);

		document.body.classList.remove(
			'eilmo-cf-cart-checkout-open'
		);
		backgroundCheckouts.forEach(function (wasInert, checkout) {
			checkout.inert = wasInert;
			checkout.removeAttribute('data-eilmo-modal-background');
		});
		backgroundCheckouts.clear();

		if (closeTimer) {
			window.clearTimeout(closeTimer);
		}

		closeTimer = window.setTimeout(
			function () {
				root.classList.remove('is-closing');
				root.hidden = true;
				root.removeAttribute('aria-busy');

				setLoading(false);
				showMessage('');
				clearContent();

				if (
					restoreFocus &&
					lastFocusTarget instanceof HTMLElement &&
					document.documentElement.contains(
						lastFocusTarget
					)
				) {
					lastFocusTarget.focus({
						preventScroll: true,
					});
				}

				closeTimer = 0;
			},
			240
		);
	}

	function removeWhatsApp(checkout) {
		if (!(checkout instanceof HTMLElement)) {
			return;
		}

		const whatsappSelectors = [
			'.eilmo-cf-whatsapp-order',
			'.eilmo-cf-whatsapp-order__button',
			'[data-eilmo-whatsapp-order]',
			'[data-eilmo-whatsapp-order-button]',
			'[data-eilmo-quick-whatsapp]',
		].join(',');

		checkout
			.querySelectorAll(
				whatsappSelectors
			)
			.forEach(
				function (node) {
					node.remove();
				}
			);
	}

	function normalizeItems(items) {
		const grouped =
			new Map();

		if (!Array.isArray(items)) {
			return [];
		}

		items.forEach(
			function (item) {
				if (
					!item ||
					typeof item !== 'object'
				) {
					return;
				}

				const productId =
					Number.parseInt(
						String(
							item.product_id ??
							item.productId ??
							0
						),
						10
					) || 0;

				const variationId =
					Number.parseInt(
						String(
							item.variation_id ??
							item.variationId ??
							0
						),
						10
					) || 0;

				const quantity =
					Number.parseInt(
						String(
							item.quantity ??
							0
						),
						10
					) || 0;

				if (
					productId <= 0 ||
					quantity <= 0
				) {
					return;
				}

				const key =
					productId + ':' +
					Math.max(0, variationId);

				grouped.set(
					key,
					(grouped.get(key) || 0) +
						quantity
				);
			}
		);

		return Array.from(
			grouped.entries()
		)
			.map(
				function (entry) {
					return entry[0] + ':' + entry[1];
				}
			)
			.sort();
	}

	function itemsMatch(expected, actual) {
		const expectedNormalized =
			normalizeItems(expected);

		const actualNormalized =
			normalizeItems(actual);

		if (
			expectedNormalized.length !==
			actualNormalized.length
		) {
			return false;
		}

		return expectedNormalized.every(
			function (value, index) {
				return value ===
					actualNormalized[index];
			}
		);
	}

	function refreshCheckout(checkout) {
		const checkoutApi =
			window.eilmoCfCheckout;

		if (
			checkoutApi &&
			typeof checkoutApi.refresh === 'function'
		) {
			/*
			 * checkout.js already refreshes the registered
			 * Eilmo calculation modules. Avoid running the
			 * same refresh pass twice.
			 */
			checkoutApi.refresh(checkout);
		} else {
			[
				window.eilmoCfComboOffers,
				window.eilmoCfOrderBump,
				window.eilmoCfDiscount,
				window.eilmoCfDelivery,
				window.eilmoCfAdvancePayment,
				window.eilmoCfPayment,
				window.eilmoCfSummary,
				window.eilmoCfCoupon,
			].forEach(
				function (api) {
					if (
						api &&
						typeof api.refresh === 'function'
					) {
						api.refresh(checkout);
					}
				}
			);
		}

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:quantityChange',
				{
					bubbles: true,
					detail: {
						checkout: checkout,
						cartCheckout: true,
					},
				}
			)
		);

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:summaryRefresh',
				{
					bubbles: true,
					detail: {
						checkout: checkout,
						cartCheckout: true,
					},
				}
			)
		);

		if (
			window.jQuery &&
			typeof window.jQuery === 'function'
		) {
			window.jQuery(document.body)
				.trigger('updated_checkout');
		}
	}

	function injectCheckout(data) {
		const root = getRoot();

		if (!root) {
			throw new Error(
				getText(
					'checkoutError',
					'Unable to prepare checkout. Please try again.'
				)
			);
		}

		const content =
			root.querySelector(
				SELECTORS.content
			);

		if (!(content instanceof HTMLElement)) {
			throw new Error(
				getText(
					'checkoutError',
					'Unable to prepare checkout. Please try again.'
				)
			);
		}

		const checkoutHtml =
			typeof data.checkoutHtml === 'string'
				? data.checkoutHtml
				: '';

		const selectionHtml =
			typeof data.selectionHtml === 'string'
				? data.selectionHtml
				: '';

		const adapterHtml =
			typeof data.adapterHtml === 'string'
				? data.adapterHtml
				: '';

		if (
			!checkoutHtml ||
			!selectionHtml ||
			!adapterHtml
		) {
			throw new Error(
				getText(
					'checkoutError',
					'Unable to prepare checkout. Please try again.'
				)
			);
		}

		/*
		 * MutationObserver callbacks run after this synchronous task.
		 * Replace the normal ProductRenderer grid with exact cart adapters
		 * before checkout modules get their initialization pass.
		 */
		content.innerHTML = checkoutHtml;

		const checkout =
			content.querySelector(
				SELECTORS.checkout
			);

		if (!(checkout instanceof HTMLElement)) {
			throw new Error(
				getText(
					'checkoutError',
					'Unable to prepare checkout. Please try again.'
				)
			);
		}

		removeWhatsApp(checkout);

		const main =
			checkout.querySelector(
				SELECTORS.main
			);

		const products =
			checkout.querySelector(
				SELECTORS.products
			);

		if (
			!(main instanceof HTMLElement) ||
			!(products instanceof HTMLElement)
		) {
			throw new Error(
				getText(
					'checkoutError',
					'Unable to prepare checkout. Please try again.'
				)
			);
		}

		products.innerHTML = adapterHtml;
		main.insertAdjacentHTML(
			'afterbegin',
			selectionHtml
		);

		checkout.dataset.cartCheckout =
			'yes';

		refreshCheckout(checkout);

		const checkoutApi =
			window.eilmoCfCheckout;

		if (
			!checkoutApi ||
			typeof checkoutApi.getItems !== 'function'
		) {
			throw new Error(
				getText(
					'checkoutError',
					'Unable to prepare checkout. Please try again.'
				)
			);
		}

		const actualItems =
			checkoutApi.getItems(checkout);

		if (
			!itemsMatch(
				data.items,
				actualItems
			)
		) {
			throw new Error(
				getText(
					'cartChanged',
					'Your cart changed while checkout was loading. Please review your cart and try again.'
				)
			);
		}

		return checkout;
	}

	async function prepareCheckout() {
		if (preparing) {
			return;
		}

		preparing = true;

		const drawerApi =
			window.eilmoCfCartDrawerApi;

		if (
			drawerApi &&
			typeof drawerApi.close === 'function'
		) {
			drawerApi.close();
		}

		if (!openShell()) {
			preparing = false;
			return;
		}

		clearContent();
		showMessage('');
		setLoading(true);

		try {
			const data =
				await requestCheckout();

			if (!data) {
				return;
			}

			injectCheckout(data);
			setLoading(false);
		} catch (error) {
			if (
				error &&
				error.name === 'AbortError'
			) {
				return;
			}

			clearContent();
			setLoading(false);
			showMessage(
				error instanceof Error
					? error.message
					: getText(
						'checkoutError',
						'Unable to prepare checkout. Please try again.'
					)
			);
		} finally {
			requestController = null;
			preparing = false;
		}
	}

	function getDrawerPosition() {
		const drawer =
			document.querySelector(
				SELECTORS.cartDrawer
			);

		if (!(drawer instanceof HTMLElement)) {
			return 'right';
		}

		const position =
			drawer.getAttribute(
				'data-position'
			) || 'right';

		return ['right', 'left', 'bottom'].includes(position)
			? position
			: 'right';
	}

	function reopenCartDrawer() {
		const api =
			window.eilmoCfCartDrawerApi;

		if (
			!api ||
			typeof api.open !== 'function'
		) {
			return;
		}

		const position =
			getDrawerPosition();

		let trigger =
			document.querySelector(
				SELECTORS.cartTrigger +
					'[data-eilmo-cart-drawer-position="' +
					position +
					'"]'
			);

		if (!(trigger instanceof HTMLElement)) {
			trigger =
				document.querySelector(
					SELECTORS.cartTrigger
				);
		}

		if (!(trigger instanceof HTMLElement)) {
			trigger =
				document.createElement('span');

			trigger.setAttribute(
				'data-eilmo-cart-drawer-position',
				position
			);
		}

		api.open(trigger);
	}

	function changeSelection() {
		closeCheckout(false);

		window.setTimeout(
			function () {
				reopenCartDrawer();
			},
			260
		);
	}

	function completeCartAfterOrderSuccess(event) {
		const checkout =
			event &&
			event.detail &&
			event.detail.checkout instanceof HTMLElement
				? event.detail.checkout
				: event.target instanceof Element
					? event.target.closest(
						SELECTORS.checkout
					)
					: null;

		if (
			!(checkout instanceof HTMLElement) ||
			checkout.dataset.cartCheckout !== 'yes'
		) {
			return;
		}

		const orderId =
			Number.parseInt(
				String(
					event.detail.orderId ||
						0
				),
				10
			) || 0;

		const orderKey =
			typeof event.detail.orderKey === 'string'
				? event.detail.orderKey.trim()
				: '';

		const action =
			config.actions &&
			config.actions.checkoutComplete
				? config.actions.checkoutComplete
				: '';

		if (
			!config.ajaxUrl ||
			!action ||
			orderId <= 0 ||
			!orderKey
		) {
			return;
		}

		const body =
			buildRequest(action);

		body.set(
			'order_id',
			String(orderId)
		);

		body.set(
			'order_key',
			orderKey
		);

		/*
		 * keepalive lets this request finish even when checkout.js
		 * immediately redirects to a thank-you/payment URL.
		 */
		try {
			fetch(
				config.ajaxUrl,
				{
					method: 'POST',
					credentials: 'same-origin',
					keepalive: true,
					headers: {
						'Content-Type':
							'application/x-www-form-urlencoded; charset=UTF-8',
					},
					body: body.toString(),
				}
			).catch(
				function () {
					/* Cart cleanup must never affect order success UI. */
				}
			);
		} catch (error) {
			if (
				navigator &&
				typeof navigator.sendBeacon === 'function'
			) {
				try {
					const payload =
						new Blob(
							[body.toString()],
							{
								type:
									'application/x-www-form-urlencoded;charset=UTF-8',
							}
						);

					navigator.sendBeacon(
						config.ajaxUrl,
						payload
					);
				} catch (beaconError) {
					/* Ignore cleanup transport failure. */
				}
			}
		}

		document
			.querySelectorAll(
				'[data-eilmo-cart-count]'
			)
			.forEach(
				function (count) {
					if (count instanceof HTMLElement) {
						count.textContent = '0';
						count.dataset.count = '0';
					}
				}
			);
	}

	function handleClick(event) {
		const target =
			event.target instanceof Element
				? event.target
				: null;

		if (!target) {
			return;
		}

		const close =
			target.closest(
				SELECTORS.close
			);

		if (close) {
			event.preventDefault();
			closeCheckout(true);
			return;
		}

		const change =
			target.closest(
				SELECTORS.change
			);

		if (change) {
			event.preventDefault();
			changeSelection();
		}
	}

	function handleKeydown(event) {
		const root = getRoot();

		if (
			event.key !== 'Escape' ||
			!root ||
			root.hidden ||
			!root.classList.contains('is-open')
		) {
			return;
		}

		event.preventDefault();
		closeCheckout(true);
	}

	function handleCartOrderNow() {
		prepareCheckout();
	}

	document.addEventListener(
		'eilmo:cartDrawerOrderNow',
		handleCartOrderNow
	);

	document.addEventListener(
		'click',
		handleClick
	);

	document.addEventListener(
		'keydown',
		handleKeydown
	);

	document.addEventListener(
		'eilmo:orderSuccess',
		completeCartAfterOrderSuccess
	);

	window.eilmoCfCartCheckout = {
		open: prepareCheckout,
		close: function () {
			closeCheckout(true);
		},
	};
})();
