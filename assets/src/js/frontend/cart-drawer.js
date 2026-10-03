(function () {
	'use strict';

	const SELECTORS = {
		drawer: '[data-eilmo-cart-drawer]',
		trigger: '[data-eilmo-cart-drawer-trigger]',
		close: '[data-eilmo-cart-drawer-close]',
		items: '[data-eilmo-cart-drawer-items]',
		item: '[data-eilmo-cart-drawer-item]',
		quantity: '[data-eilmo-cart-quantity]',
		remove: '[data-eilmo-cart-remove]',
		subtotal: '[data-eilmo-cart-drawer-subtotal]',
		count: '[data-eilmo-cart-drawer-count]',
		globalCount: '[data-eilmo-cart-count]',
		message: '[data-eilmo-cart-drawer-message]',
		order: '[data-eilmo-cart-order-now]',
		login: '[data-eilmo-cart-order-login]',
	};

	const config =
		window.eilmoCfCartDrawer || {};

	const CART_SYNC_CHANNEL =
		'eilmo-cf-cart-drawer';

	const CART_SYNC_STORAGE_KEY =
		'eilmo_cf_cart_drawer_sync';

	let drawer = null;
	let closeTimer = 0;
	let refreshTimer = 0;
	let lastTrigger = null;
	let requestController = null;
	let cartSyncChannel = null;

	/**
	 * Get global Cart Drawer.
	 *
	 * @return {HTMLElement|null}
	 */
	function getDrawer() {

		if (
			drawer instanceof HTMLElement &&
			document.documentElement.contains(
				drawer
			)
		) {
			return drawer;
		}

		drawer =
			document.querySelector(
				SELECTORS.drawer
			);

		return drawer instanceof HTMLElement
			? drawer
			: null;
	}

	/**
	 * Normalize drawer position.
	 *
	 * @param {string} position Position.
	 *
	 * @return {string}
	 */
	function normalizePosition(
		position
	) {

		return [
			'right',
			'left',
			'bottom',
		].includes(
			position
		)
			? position
			: 'right';
	}

	/**
	 * Get position assigned to a trigger.
	 *
	 * @param {HTMLElement|null} trigger Trigger.
	 *
	 * @return {string}
	 */
	function getTriggerPosition(
		trigger
	) {

		if (
			!(
				trigger instanceof
					HTMLElement
			)
		) {
			return 'right';
		}

		return normalizePosition(
			trigger.getAttribute(
				'data-eilmo-cart-drawer-position'
			) ||
				'right'
		);
	}

	/**
	 * Determine whether drawer is open.
	 *
	 * @return {boolean}
	 */
	function isOpen() {

		const element =
			getDrawer();

		return Boolean(
			element &&
			element.classList.contains(
				'is-open'
			)
		);
	}

	/**
	 * Open Cart Drawer.
	 *
	 * @param {HTMLElement|null} trigger Trigger.
	 *
	 * @return {void}
	 */
	const backgroundCheckouts = new Map();

	function openDrawer(
		trigger
	) {

		const element =
			getDrawer();

		if (!element) {
			return;
		}

		if (closeTimer) {

			window.clearTimeout(
				closeTimer
			);

			closeTimer = 0;
		}

		lastTrigger =
			trigger instanceof HTMLElement
				? trigger
				: null;

		element.setAttribute(
			'data-position',
			getTriggerPosition(
				trigger
			)
		);

		element.hidden =
			false;

		element.classList.remove(
			'is-closing'
		);

        document.querySelectorAll('[data-eilmo-checkout]').forEach(function (checkout) {
            if (element.contains(checkout) || backgroundCheckouts.has(checkout)) return;
            backgroundCheckouts.set(checkout, checkout.inert);
            checkout.inert = true;
            checkout.setAttribute('data-eilmo-drawer-background', 'yes');
        });
		document.body.classList.add(
			'eilmo-cf-cart-drawer-open'
		);

		window.requestAnimationFrame(
			function () {

				element.classList.add(
					'is-open'
				);
			}
		);

		/*
		 * Always request authoritative cart state before
		 * displaying the Cart Drawer content.
		 */
		refresh();
	}

	/**
	 * Close Cart Drawer.
	 *
	 * @return {void}
	 */
	function closeDrawer() {

		const element =
			getDrawer();

		if (
			!element ||
			element.hidden
		) {
			return;
		}

		element.classList.remove(
			'is-open'
		);

		element.classList.add(
			'is-closing'
		);

        backgroundCheckouts.forEach(function (wasInert, checkout) {
            checkout.inert = wasInert;
            checkout.removeAttribute('data-eilmo-drawer-background');
        });
        backgroundCheckouts.clear();
		document.body.classList.remove(
			'eilmo-cf-cart-drawer-open'
		);

		if (closeTimer) {
			window.clearTimeout(
				closeTimer
			);
		}

		closeTimer =
			window.setTimeout(
				function () {

					element.classList.remove(
						'is-closing'
					);

					element.hidden =
						true;

					closeTimer =
						0;

					if (
						lastTrigger &&
						document.documentElement.contains(
							lastTrigger
						)
					) {
						lastTrigger.focus(
							{
								preventScroll:
									true,
							}
						);
					}
				},
				300
			);
	}

	/**
	 * Set drawer loading state.
	 *
	 * @param {boolean} busy Busy.
	 *
	 * @return {void}
	 */
	function setBusy(
		busy
	) {

		const element =
			getDrawer();

		if (!element) {
			return;
		}

		element.classList.toggle(
			'is-loading',
			Boolean(
				busy
			)
		);

		element.setAttribute(
			'aria-busy',
			busy
				? 'true'
				: 'false'
		);
	}

	/**
	 * Display Cart Drawer message.
	 *
	 * @param {string} message Message.
	 *
	 * @return {void}
	 */
	function showMessage(
		message
	) {

		const element =
			getDrawer();

		if (!element) {
			return;
		}

		const target =
			element.querySelector(
				SELECTORS.message
			);

		if (
			!(
				target instanceof
					HTMLElement
			)
		) {
			return;
		}

		const text =
			typeof message ===
				'string'
				? message.trim()
				: '';

		target.textContent =
			text;

		target.hidden =
			!text;
	}

	/**
	 * Build AJAX request.
	 *
	 * @param {string} action AJAX action.
	 * @param {Object} data   Request data.
	 *
	 * @return {URLSearchParams}
	 */
	function buildRequest(
		action,
		data = {}
	) {

		const body =
			new URLSearchParams();

		body.set(
			'action',
			action
		);

		body.set(
			'nonce',
			config.nonce || ''
		);

		Object.keys(
			data
		).forEach(
			function (key) {

				body.set(
					key,
					String(
						data[key]
					)
				);
			}
		);

		return body;
	}

	/**
	 * Send Cart Drawer AJAX request.
	 *
	 * @param {string} action AJAX action.
	 * @param {Object} data   Request data.
	 *
	 * @return {Promise<Object>}
	 */
	async function request(
		action,
		data = {}
	) {

		if (
			!config.ajaxUrl ||
			!action
		) {
			throw new Error(
				'Cart Drawer AJAX configuration is missing.'
			);
		}

		if (requestController) {

			requestController.abort();
		}

		requestController =
			new AbortController();

		setBusy(
			true
		);

		showMessage(
			''
		);

		try {

			const response =
				await fetch(
					config.ajaxUrl,
					{
						method:
							'POST',

						credentials:
							'same-origin',

						headers: {
							'Content-Type':
								'application/x-www-form-urlencoded; charset=UTF-8',
						},

						body:
							buildRequest(
								action,
								data
							),

						signal:
							requestController.signal,
					}
				);

			const payload =
				await response.json();

			if (
				!payload ||
				true !==
					payload.success ||
				!payload.data
			) {
				const message =
					payload &&
					payload.data &&
					typeof
						payload.data
							.message ===
							'string'
						? payload.data
							.message
						: 'Unable to update the cart.';

				throw new Error(
					message
				);
			}

			return payload.data;

		} finally {

			requestController =
				null;

			setBusy(
				false
			);
		}
	}

	/**
	 * Apply authoritative WooCommerce cart state.
	 *
	 * @param {Object} state State.
	 *
	 * @return {void}
	 */
	function applyState(
		state
	) {

		const element =
			getDrawer();

		if (
			!element ||
			!state
		) {
			return;
		}

		const items =
			element.querySelector(
				SELECTORS.items
			);

		if (
			items instanceof HTMLElement &&
			typeof state.itemsHtml ===
				'string'
		) {
			items.innerHTML =
				state.itemsHtml;
		}

		const subtotal =
			element.querySelector(
				SELECTORS.subtotal
			);

		if (
			subtotal instanceof HTMLElement &&
			typeof state.subtotalHtml ===
				'string'
		) {
			subtotal.innerHTML =
				state.subtotalHtml;
		}

		const count =
			Number.isFinite(
				Number(
					state.count
				)
			)
				? Math.max(
					0,
					Number(
						state.count
					)
				)
				: 0;

		element
			.querySelectorAll(
				SELECTORS.count
			)
			.forEach(
				function (node) {

					node.textContent =
						String(
							count
						);
				}
			);

		/*
		 * Update every global Cart Drawer trigger.
		 *
		 * This includes Elementor widgets, header icons
		 * and any custom trigger using data-eilmo-cart-count.
		 */
		document
			.querySelectorAll(
				SELECTORS.globalCount
			)
			.forEach(
				function (node) {

					node.textContent =
						String(
							count
						);

					node.setAttribute(
						'data-count',
						String(
							count
						)
					);
				}
			);

		const isEmpty =
			true ===
				state.isEmpty ||
			'true' ===
				state.isEmpty ||
			count <= 0;

		element.setAttribute(
			'data-cart-empty',
			isEmpty
				? 'yes'
				: 'no'
		);

		const order =
			element.querySelector(
				SELECTORS.order
			);

		if (
			order instanceof
				HTMLButtonElement
		) {
			order.disabled =
				isEmpty;
		}

		const login =
			element.querySelector(
				SELECTORS.login
			);

		if (
			login instanceof
				HTMLElement
		) {
			login.classList.toggle(
				'is-disabled',
				isEmpty
			);

			login.setAttribute(
				'aria-disabled',
				isEmpty
					? 'true'
					: 'false'
			);
		}

		document.dispatchEvent(
			new CustomEvent(
				'eilmo:cartDrawerUpdated',
				{
					detail: {
						count:
							count,

						isEmpty:
							isEmpty,
					},
				}
			)
		);
	}

	/**
	 * Refresh cart from server.
	 *
	 * @return {Promise<void>}
	 */
	async function refresh() {

		try {

			const state =
				await request(
					config.actions &&
						config.actions
							.refresh
				);

			applyState(
				state
			);

		} catch (error) {

			if (
				error &&
				'AbortError' ===
					error.name
			) {
				return;
			}

			showMessage(
				error instanceof Error
					? error.message
					: 'Unable to refresh the cart.'
			);
		}
	}

	/**
	 * Schedule a cart refresh.
	 *
	 * Multiple browser / WooCommerce events can fire
	 * almost simultaneously. Debouncing prevents duplicate
	 * AJAX calls while keeping the count responsive.
	 *
	 * @param {number} delay Delay.
	 *
	 * @return {void}
	 */
	function scheduleRefresh(
		delay = 80
	) {

		if (refreshTimer) {

			window.clearTimeout(
				refreshTimer
			);
		}

		refreshTimer =
			window.setTimeout(
				function () {

					refreshTimer =
						0;

					refresh();
				},
				Math.max(
					0,
					delay
				)
			);
	}

	/**
	 * Send cart-change signal to another browser tab.
	 *
	 * This does not contain cart data.
	 *
	 * The receiving tab always requests the authoritative
	 * state from WooCommerce.
	 *
	 * @return {void}
	 */
	function broadcastCartChange() {

		const message = {
			type:
				'cart_changed',

			time:
				Date.now(),
		};

		if (cartSyncChannel) {

			try {

				cartSyncChannel.postMessage(
					message
				);

				return;

			} catch (error) {

				/*
				 * Fall through to localStorage fallback.
				 */
			}
		}

		try {

			window.localStorage.setItem(
				CART_SYNC_STORAGE_KEY,
				JSON.stringify(
					message
				)
			);

		} catch (error) {

			/*
			 * Storage may be unavailable in some privacy
			 * modes. Focus / visibility synchronization
			 * still keeps the cart correct.
			 */
		}
	}

	/**
	 * Register cross-tab synchronization.
	 *
	 * BroadcastChannel is preferred. localStorage acts
	 * as a fallback for browsers where it is unavailable.
	 *
	 * @return {void}
	 */
	function registerCrossTabSync() {

		if (
			'BroadcastChannel' in
				window
		) {
			try {

				cartSyncChannel =
					new BroadcastChannel(
						CART_SYNC_CHANNEL
					);

				cartSyncChannel.addEventListener(
					'message',
					function (event) {

						if (
							!event.data ||
							'cart_changed' !==
								event.data.type
						) {
							return;
						}

						scheduleRefresh(
							60
						);
					}
				);

			} catch (error) {

				cartSyncChannel =
					null;
			}
		}

		window.addEventListener(
			'storage',
			function (event) {

				if (
					event.key !==
						CART_SYNC_STORAGE_KEY ||
					!event.newValue
				) {
					return;
				}

				scheduleRefresh(
					60
				);
			}
		);
	}

	/**
	 * Refresh when returning to a previous page/tab.
	 *
	 * This specifically fixes stale count caused by:
	 *
	 * - browser Back/Forward cache
	 * - switching between tabs
	 * - returning to a hidden page
	 *
	 * @return {void}
	 */
	function registerPageLifecycleSync() {

		window.addEventListener(
			'pageshow',
			function (event) {

				if (
					event.persisted
				) {
					scheduleRefresh(
						0
					);
				}
			}
		);

		document.addEventListener(
			'visibilitychange',
			function () {

				if (
					'visible' ===
						document.visibilityState
				) {
					scheduleRefresh(
						80
					);
				}
			}
		);

		window.addEventListener(
			'focus',
			function () {

				scheduleRefresh(
					80
				);
			}
		);
	}

	/**
	 * Update cart item quantity.
	 *
	 * @param {HTMLElement} item     Cart item.
	 * @param {number}      quantity Quantity.
	 *
	 * @return {Promise<void>}
	 */
	async function updateQuantity(
		item,
		quantity
	) {

		if (
			!(
				item instanceof
					HTMLElement
			)
		) {
			return;
		}

		const cartKey =
			item.getAttribute(
				'data-cart-key'
			) ||
				'';

		if (!cartKey) {
			return;
		}

		try {

			const state =
				await request(
					config.actions &&
						config.actions
							.quantity,
					{
						cart_key:
							cartKey,

						quantity:
							quantity,
					}
				);

			applyState(
				state
			);

			/*
			 * Inform other tabs/pages that WooCommerce
			 * cart state changed.
			 */
			broadcastCartChange();

		} catch (error) {

			if (
				error &&
				'AbortError' ===
					error.name
			) {
				return;
			}

			showMessage(
				error instanceof Error
					? error.message
					: 'Unable to update quantity.'
			);
		}
	}

	/**
	 * Remove cart item.
	 *
	 * @param {HTMLElement|null} item Cart item.
	 *
	 * @return {Promise<void>}
	 */
	async function removeItem(
		item
	) {

		if (
			!(
				item instanceof
					HTMLElement
			)
		) {
			return;
		}

		const cartKey =
			item.getAttribute(
				'data-cart-key'
			) ||
				'';

		if (!cartKey) {
			return;
		}

		try {

			const state =
				await request(
					config.actions &&
						config.actions
							.remove,
					{
						cart_key:
							cartKey,
					}
				);

			applyState(
				state
			);

			/*
			 * Keep every open tab synchronized.
			 */
			broadcastCartChange();

		} catch (error) {

			if (
				error &&
				'AbortError' ===
					error.name
			) {
				return;
			}

			showMessage(
				error instanceof Error
					? error.message
					: 'Unable to remove the item.'
			);
		}
	}

	/**
	 * Get current Cart Drawer products.
	 *
	 * @return {Array<Object>}
	 */
	function getItems() {

		const element =
			getDrawer();

		if (!element) {
			return [];
		}

		return Array.from(
			element.querySelectorAll(
				SELECTORS.item
			)
		)
			.filter(
				function (item) {

					return item instanceof
						HTMLElement;
				}
			)
			.map(
				function (item) {

					return {
						cartKey:
							item.getAttribute(
								'data-cart-key'
							) ||
								'',

						productId:
							Number(
								item.getAttribute(
									'data-product-id'
								) ||
									0
							),

						variationId:
							Number(
								item.getAttribute(
									'data-variation-id'
								) ||
									0
							),

						quantity:
							Number(
								item.getAttribute(
									'data-quantity'
								) ||
									1
							),
					};
				}
			)
			.filter(
				function (item) {

					return (
						item.productId >
							0 &&
						item.quantity >
							0
					);
				}
			);
	}

	/**
	 * Handle Cart Drawer clicks.
	 *
	 * @param {MouseEvent} event Click event.
	 *
	 * @return {void}
	 */
	function handleDocumentClick(
		event
	) {

		const target =
			event.target instanceof Element
				? event.target
				: null;

		if (!target) {
			return;
		}

		/*
		 * Global Cart Drawer trigger.
		 */
		const trigger =
			target.closest(
				SELECTORS.trigger
			);

		if (
			trigger instanceof
				HTMLElement
		) {
			event.preventDefault();

			openDrawer(
				trigger
			);

			return;
		}

		/*
		 * Close button / backdrop.
		 */
		const close =
			target.closest(
				SELECTORS.close
			);

		if (close) {
			event.preventDefault();

			closeDrawer();

			return;
		}

		const element =
			getDrawer();

		if (!element) {
			return;
		}

		/*
		 * Quantity control.
		 */
		const quantityButton =
			target.closest(
				SELECTORS.quantity
			);

		if (
			quantityButton instanceof
				HTMLButtonElement
		) {
			event.preventDefault();

			if (
				quantityButton.disabled
			) {
				return;
			}

			const item =
				quantityButton.closest(
					SELECTORS.item
				);

			if (
				!(
					item instanceof
						HTMLElement
				)
			) {
				return;
			}

			const current =
				Math.max(
					1,
					Number(
						item.getAttribute(
							'data-quantity'
						) ||
							1
					)
				);

			const direction =
				quantityButton.getAttribute(
					'data-eilmo-cart-quantity'
				);

			const next =
				'increase' ===
					direction
					? current + 1
					: current - 1;

			updateQuantity(
				item,
				next
			);

			return;
		}

		/*
		 * Remove item.
		 */
		const removeButton =
			target.closest(
				SELECTORS.remove
			);

		if (removeButton) {
			event.preventDefault();

			const item =
				removeButton.closest(
					SELECTORS.item
				);

			removeItem(
				item
			);

			return;
		}

		/*
		 * Disabled Login action.
		 */
		const login =
			target.closest(
				SELECTORS.login
			);

		if (
			login instanceof
				HTMLElement &&
			'true' ===
				login.getAttribute(
					'aria-disabled'
				)
		) {
			event.preventDefault();

			return;
		}

		/*
		 * Order Now.
		 *
		 * Final checkout integration listens for this
		 * event. Cart Drawer itself does not create orders.
		 */
		const order =
			target.closest(
				SELECTORS.order
			);

		if (
			order instanceof
				HTMLButtonElement
		) {
			event.preventDefault();

			if (
				order.disabled
			) {
				return;
			}

			const items =
				getItems();

			if (
				!items.length
			) {
				return;
			}

			document.dispatchEvent(
				new CustomEvent(
					'eilmo:cartDrawerOrderNow',
					{
						detail: {
							items:
								items,
						},
					}
				)
			);
		}
	}

	/**
	 * Handle Escape key.
	 *
	 * @param {KeyboardEvent} event Keyboard event.
	 *
	 * @return {void}
	 */
	function handleKeydown(
		event
	) {

		if (
			'Escape' ===
				event.key &&
			isOpen()
		) {
			event.preventDefault();

			closeDrawer();
		}
	}

	/**
	 * Handle a cart change originating on this page.
	 *
	 * Refresh the local UI and notify any other open tabs.
	 *
	 * @return {void}
	 */
	function handleLocalCartChange() {

		scheduleRefresh(
			40
		);

		broadcastCartChange();
	}

	/**
	 * Register classic WooCommerce cart events.
	 *
	 * @return {void}
	 */
	function registerWooCommerceEvents() {

		if (
			window.jQuery &&
			typeof window.jQuery ===
				'function'
		) {
			window.jQuery(
				document.body
			).on(
				'added_to_cart removed_from_cart updated_wc_div wc_fragments_refreshed',
				handleLocalCartChange
			);
		}

		/*
		 * WooCommerce Blocks can expose native cart
		 * events instead of the classic jQuery events.
		 *
		 * Listening for them is harmless on classic
		 * WooCommerce pages and improves compatibility.
		 */
		document.addEventListener(
			'wc-blocks_added_to_cart',
			handleLocalCartChange
		);

		document.addEventListener(
			'wc-blocks_removed_from_cart',
			handleLocalCartChange
		);
	}

	/**
	 * Initialize Cart Drawer frontend synchronization.
	 *
	 * @return {void}
	 */
	function initialize() {

		registerCrossTabSync();
		registerPageLifecycleSync();
		registerWooCommerceEvents();

		/*
		 * One lightweight initial synchronization makes
		 * the trigger reliable even when the page HTML
		 * came from browser/page cache.
		 */
		scheduleRefresh(
			120
		);
	}

	/**
	 * Public Cart Drawer API.
	 */
	window.eilmoCfCartDrawerApi = {
		open:
			openDrawer,

		close:
			closeDrawer,

		refresh:
			refresh,

		getItems:
			getItems,
	};

	document.addEventListener(
		'click',
		handleDocumentClick
	);

	document.addEventListener(
		'keydown',
		handleKeydown
	);

	if (
		'loading' ===
			document.readyState
	) {
		document.addEventListener(
			'DOMContentLoaded',
			initialize,
			{
				once:
					true,
			}
		);

	} else {

		initialize();
	}
})();