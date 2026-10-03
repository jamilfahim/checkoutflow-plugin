/**
 * Eilmo Checkout Flow - Global Meta Tracking.
 *
 * Works independently from the Eilmo Checkout Flow master switch.
 *
 * Browser Pixel:
 * - PageView
 * - ViewContent
 * - AddToCart
 * - InitiateCheckout
 * - Purchase
 *
 * Server-side CAPI mirror:
 * - ViewContent
 * - AddToCart
 * - InitiateCheckout
 *
 * Authoritative Purchase CAPI remains in PHP and is built from WC_Order.
 *
 * Purchase strategies:
 * - Basic: browser Purchase may fire and shares the stable event ID with CAPI.
 * - Advanced: browser Purchase is blocked; status-based Purchase is CAPI-only.
 *
 * Stable Purchase event ID:
 * eilmo_purchase_{ORDER_ID}
 *
 * Supported storefront paths:
 * - Eilmo Checkout Flow.
 * - WooCommerce classic product/cart/checkout.
 * - WooCommerce Checkout Block / Store API checkout.
 * - WooCommerce Order Received / Thank You page.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	/*
	 * The Elementor editor canvas is an interactive design preview, not a
	 * storefront visit. Do not emit browser or server tracking events from it.
	 */
	if (
		(
			window.frameElement &&
			window.frameElement.id === 'elementor-preview-iframe'
		) ||
		(
			window.elementorFrontend &&
			typeof window.elementorFrontend.isEditMode === 'function' &&
			window.elementorFrontend.isEditMode()
		)
	) {
		return;
	}

	/* The checkout bundle and standalone tracker can both be enqueued late. */
	if (window.eilmoCfMetaTrackingInitialized) {
		return;
	}
	window.eilmoCfMetaTrackingInitialized = true;

	const SELECTORS = {
		eiloCheckout: '[data-eilmo-checkout]',
		eiloItem: '[data-eilmo-product-item], [data-eilmo-item], [data-eilmo-quick-checkout-product-adapter]',
		eiloQuantity: '[data-eilmo-quantity-input]',
		eiloTitle: '.eilmo-cf-product-card__title, [data-eilmo-product-title], .product_title',
		quickCheckout: '[data-eilmo-quick-checkout]',
		quickCheckoutActions: '[data-eilmo-quick-checkout-actions]',
		quickCheckoutTrigger: '[data-eilmo-quick-checkout-trigger="order"]',
		wooCartForm: 'form.cart',
		wooQuantity: 'input.qty[name="quantity"], input[name="quantity"]',
	};

	const STORAGE = {
		purchasePrefix: 'eilmo_cf_meta_purchase:',
		checkoutPrefix: 'eilmo_cf_meta_checkout:',
	};

	const quantityState = new WeakMap();

	let checkoutViewContentFired = false;
	let storeApiCartSnapshot = new Map();
	let storeApiSnapshotReady = false;
	let storeApiSnapshotLoading = false;
	let recentNativeAdds = [];

	/**
	 * Convert value to non-negative number.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toNumber(value) {
		const number = Number.parseFloat(
			String(
				value ?? ''
			)
		);

		return Number.isFinite(
			number
		)
			? Math.max(
				0,
				number
			)
			: 0;
	}

	/**
	 * Convert value to non-negative integer.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toInteger(value) {
		const number = Number.parseInt(
			String(
				value ?? ''
			),
			10
		);

		return Number.isFinite(
			number
		)
			? Math.max(
				0,
				number
			)
			: 0;
	}

	/**
	 * Normalize PHP/WordPress boolean-like values.
	 *
	 * WordPress localized data may arrive as booleans,
	 * integers or strings depending on how the value was
	 * prepared in PHP.
	 *
	 * @param {*} value Value.
	 *
	 * @return {boolean}
	 */
	function isEnabled(value) {

		if (
			value === true ||
			value === 1
		) {
			return true;
		}

		if (
			typeof value !==
			'string'
		) {
			return false;
		}

		return [
			'1',
			'yes',
			'true',
			'on',
		].includes(
			value
				.trim()
				.toLowerCase()
		);
	}

	/**
	 * Normalize event/content identifier.
	 *
	 * @param {*} value Value.
	 *
	 * @return {string}
	 */
	function normalizeId(value) {

		return String(
			value ?? ''
		)
			.trim()
			.replace(
				/[^a-zA-Z0-9_.:-]/g,
				''
			)
			.slice(
				0,
				128
			);
	}

	/**
	 * Generate unique browser/server deduplication event ID.
	 *
	 * @param {string} prefix Prefix.
	 *
	 * @return {string}
	 */
	function generateEventId(prefix) {

		let random = '';

		if (
			window.crypto &&
			typeof window.crypto.randomUUID ===
				'function'
		) {
			random =
				window.crypto.randomUUID();
		} else if (
			window.crypto &&
			typeof window.crypto.getRandomValues ===
				'function'
		) {
			const values =
				new Uint32Array(
					4
				);

			window.crypto.getRandomValues(
				values
			);

			random =
				Array.from(
					values
				)
					.map(
						function (
							value
						) {
							return value
								.toString(
									16
								)
								.padStart(
									8,
									'0'
								);
						}
					)
					.join(
						''
					);
		} else {
			random =
				Date.now()
					.toString(
						36
					) +
				'_' +
				Math.random()
					.toString(
						36
					)
					.slice(
						2
					);
		}

		return normalizeId(
			'eilmo_' +
			prefix +
			'_' +
			random
		);
	}

	/**
	 * Get localized configuration.
	 *
	 * @return {Object}
	 */
	function getConfig() {

		const raw =
			window.eilmoCfMeta &&
			typeof window.eilmoCfMeta ===
				'object'
				? window.eilmoCfMeta
				: {};

		const browser =
			raw.browser &&
			typeof raw.browser ===
				'object'
				? raw.browser
				: {};

		const server =
			raw.server &&
			typeof raw.server ===
				'object'
				? raw.server
				: {};

		const woo =
			raw.woo &&
			typeof raw.woo ===
				'object'
				? raw.woo
				: {};

		const events =
			raw.events &&
			typeof raw.events ===
				'object'
				? raw.events
				: {};

		const purchaseTracking =
			raw.purchaseTracking &&
			typeof raw.purchaseTracking ===
				'object'
				? raw.purchaseTracking
				: {};

		const purchaseStrategy =
			String(
				purchaseTracking.strategy ||
					'basic'
			)
				.trim()
				.toLowerCase() ===
				'advanced'
					? 'advanced'
					: 'basic';

		const hasBrowserPurchaseFlag =
			Object.prototype
				.hasOwnProperty
				.call(
					purchaseTracking,
					'browserPurchase'
				);

		return {
			enabled:
				isEnabled(
					raw.enabled
				),

			browser: {
				enabled:
					isEnabled(
						browser.enabled
					),

				pixelId:
					String(
						browser.pixelId ||
							''
					)
						.replace(
							/\D+/g,
							''
						),
			},

			server: {
				enabled:
					isEnabled(
						server.enabled
					),

				ajaxUrl:
					String(
						server.ajaxUrl ||
							''
					),

				action:
					String(
						server.action ||
							''
					),

				nonce:
					String(
						server.nonce ||
							''
					),
			},

			woo: {
				storeApiCartUrl:
					String(
						woo.storeApiCartUrl ||
							''
					),
			},

			currency:
				String(
					raw.currency ||
					(
						window.eilmoCf &&
						window.eilmoCf.currency
							? window.eilmoCf.currency.code
							: ''
					)
				).trim(),

			events: {
				pageView:
					isEnabled(
						events.pageView
					),

				viewContent:
					isEnabled(
						events.viewContent
					),

				addToCart:
					isEnabled(
						events.addToCart
					),

				initiateCheckout:
					isEnabled(
						events.initiateCheckout
					),

				purchase:
					isEnabled(
						events.purchase
					),
			},

			purchaseTracking: {
				strategy:
					purchaseStrategy,

				browserPurchase:
					hasBrowserPurchaseFlag
						? isEnabled(
							purchaseTracking
								.browserPurchase
						)
						: purchaseStrategy ===
							'basic',
			},

			viewContent:
				raw.viewContent &&
				typeof raw.viewContent ===
					'object'
						? raw.viewContent
						: {},

			nativeCheckout:
				raw.nativeCheckout &&
				typeof raw.nativeCheckout ===
					'object'
						? raw.nativeCheckout
						: {},

			purchase:
				raw.purchase &&
				typeof raw.purchase ===
					'object'
						? raw.purchase
						: {},

			debug:
				isEnabled(
					raw.debug
				),
		};
	}

	/**
	 * Browser Pixel availability.
	 *
	 * @return {boolean}
	 */
	function browserEnabled() {

		const config =
			getConfig();

		return Boolean(
			config.enabled &&
			config.browser.enabled &&
			config.browser.pixelId &&
			typeof window.fbq ===
				'function'
		);
	}

	/**
	 * Server mirror availability.
	 *
	 * @return {boolean}
	 */
	function serverEnabled() {

		const config =
			getConfig();

		return Boolean(
			config.enabled &&
			config.server.enabled &&
			config.server.ajaxUrl &&
			config.server.action &&
			config.server.nonce
		);
	}

	/**
	 * Determine whether browser Purchase is allowed.
	 *
	 * Advanced Purchase tracking is authoritative
	 * server-side only.
	 *
	 * This extra guard protects against stale/cached
	 * frontend configuration accidentally containing
	 * Purchase data while Advanced mode is active.
	 *
	 * @return {boolean}
	 */
	function browserPurchaseAllowed() {

		const config =
			getConfig();

		return Boolean(
			config.events.purchase &&
			config.purchaseTracking
				.strategy ===
				'basic' &&
			config.purchaseTracking
				.browserPurchase &&
			browserEnabled()
		);
	}

	/**
	 * Debug helper.
	 *
	 * @param {string} message Message.
	 * @param {*}      data    Data.
	 *
	 * @return {void}
	 */
	function debug(
		message,
		data
	) {

		if (
			!getConfig().debug ||
			!window.console ||
			typeof window.console.debug !==
				'function'
		) {
			return;
		}

		window.console.debug(
			'[Eilmo Meta]',
			message,
			data || ''
		);
	}

	/**
	 * Send a standard browser Pixel event.
	 *
	 * @param {string} eventName Event name.
	 * @param {Object} data      Data.
	 * @param {string} eventId   Event ID.
	 *
	 * @return {boolean}
	 */
	function trackBrowser(
		eventName,
		data = {},
		eventId = ''
	) {

		if (
			!browserEnabled()
		) {
			return false;
		}

		const config =
			getConfig();

		try {

			if (eventId) {
				window.fbq(
					'trackSingle',
					config.browser.pixelId,
					eventName,
					data,
					{
						eventID:
							eventId,
					}
				);
			} else {
				window.fbq(
					'trackSingle',
					config.browser.pixelId,
					eventName,
					data
				);
			}

			debug(
				'Browser ' +
					eventName,
				{
					data:
						data,

					eventId:
						eventId,
				}
			);

			return true;
		} catch (error) {

			debug(
				'Browser tracking error',
				error
			);

			return false;
		}
	}

	/**
	 * Server mirror event allowlist.
	 *
	 * Purchase is intentionally excluded because the
	 * authoritative Purchase CAPI event is created from
	 * WC_Order in PHP.
	 *
	 * @param {string} eventName Event name.
	 *
	 * @return {boolean}
	 */
	function serverMirrorSupported(
		eventName
	) {

		return [
			'ViewContent',
			'AddToCart',
			'InitiateCheckout',
		].includes(
			eventName
		);
	}

	/**
	 * Send first-party event to PHP CAPI bridge.
	 *
	 * @param {string} eventName Event name.
	 * @param {Object} data      Custom data.
	 * @param {string} eventId   Event ID.
	 * @param {Object} customer  Optional customer fields.
	 *
	 * @return {boolean}
	 */
	function sendServerEvent(
		eventName,
		data,
		eventId,
		customer = {}
	) {

		if (
			!serverEnabled() ||
			!serverMirrorSupported(
				eventName
			) ||
			!eventId
		) {
			return false;
		}

		const config =
			getConfig();

		const body =
			new URLSearchParams();

		body.set(
			'action',
			config.server.action
		);

		body.set(
			'nonce',
			config.server.nonce
		);

		body.set(
			'event_name',
			eventName
		);

		body.set(
			'event_id',
			eventId
		);

		body.set(
			'source_url',
			window.location.href
		);

		body.set(
			'custom_data',
			JSON.stringify(
				data || {}
			)
		);

		body.set(
			'customer',
			JSON.stringify(
				customer || {}
			)
		);

		fetch(
			config.server.ajaxUrl,
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
					body.toString(),

				keepalive:
					true,
			}
		)
			.then(
				function (
					response
				) {
					return response
						.json()
						.catch(
							function () {
								return {};
							}
						);
				}
			)
			.then(
				function (
					response
				) {
					debug(
						'Server ' +
							eventName,
						{
							eventId:
								eventId,

							response:
								response,
						}
					);
				}
			)
			.catch(
				function (
					error
				) {
					debug(
						'Server tracking error',
						error
					);
				}
			);

		return true;
	}

	/**
	 * Send browser/server pair with one shared event ID.
	 *
	 * @param {string} eventName Event name.
	 * @param {Object} data      Data.
	 * @param {string} eventId   Event ID.
	 * @param {Object} customer  Customer.
	 *
	 * @return {boolean}
	 */
	function trackMirrored(
		eventName,
		data,
		eventId,
		customer = {}
	) {

		const browserSent =
			trackBrowser(
				eventName,
				data,
				eventId
			);

		const serverSent =
			sendServerEvent(
				eventName,
				data,
				eventId,
				customer
			);

		return (
			browserSent ||
			serverSent
		);
	}

	/**
	 * Safe storage get.
	 *
	 * @param {Storage} storage Storage.
	 * @param {string}  key     Key.
	 *
	 * @return {string}
	 */
	function storageGet(
		storage,
		key
	) {

		try {
			return String(
				storage.getItem(
					key
				) ||
					''
			);
		} catch (error) {
			return '';
		}
	}

	/**
	 * Safe storage set.
	 *
	 * @param {Storage} storage Storage.
	 * @param {string}  key     Key.
	 * @param {string}  value   Value.
	 *
	 * @return {void}
	 */
	function storageSet(
		storage,
		key,
		value
	) {

		try {
			storage.setItem(
				key,
				value
			);
		} catch (error) {
			/*
			 * Storage may be unavailable.
			 */
		}
	}

	/**
	 * Get Eilmo checkout from an event.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {HTMLElement|null}
	 */
	function getEilmoCheckoutFromEvent(
		event
	) {

		if (
			event.detail &&
			window.eilmoCfDom.isElement(event.detail.checkout)
		) {
			return event.detail.checkout;
		}

		if (
			window.eilmoCfDom.isElement(event.target)
		) {
			const checkout =
				event.target.closest(
					SELECTORS.eiloCheckout
				);

			return window.eilmoCfDom.isElement(checkout)
					? checkout
					: null;
		}

		return null;
	}

	/**
	 * Read positive integer from dataset aliases.
	 *
	 * @param {HTMLElement}   element Element.
	 * @param {Array<string>} aliases Aliases.
	 *
	 * @return {number}
	 */
	function getDatasetInteger(
		element,
		aliases
	) {

		if (
			!(
				window.eilmoCfDom.isElement(element)
			)
		) {
			return 0;
		}

		for (
			let index = 0;
			index < aliases.length;
			index += 1
		) {
			const value =
				toInteger(
					element.dataset[
						aliases[
							index
						]
					]
				);

			if (
				value > 0
			) {
				return value;
			}
		}

		return 0;
	}

	/**
	 * Get Eilmo item wrapper.
	 *
	 * @param {HTMLInputElement} input Quantity input.
	 *
	 * @return {HTMLElement|null}
	 */
	function getEilmoItem(
		input
	) {

		const item =
			input.closest(
				SELECTORS.eiloItem
			);

		return window.eilmoCfDom.isElement(item)
				? item
				: null;
	}

	/**
	 * Resolve Eilmo product ID.
	 *
	 * @param {HTMLElement}      item  Item.
	 * @param {HTMLInputElement} input Input.
	 *
	 * @return {number}
	 */
	function getEilmoProductId(
		item,
		input
	) {

		const aliases = [
			'productId',
			'parentProductId',
			'parentId',
		];

		let productId =
			getDatasetInteger(
				item,
				aliases
			);

		if (
			productId > 0
		) {
			return productId;
		}

		productId =
			getDatasetInteger(
				input,
				aliases
			);

		if (
			productId > 0
		) {
			return productId;
		}

		const child =
			item.querySelector(
				'[data-eilmo-product-id], input[name*="[product_id]"], input[name="product_id"]'
			);

		return window.eilmoCfDom.isElement(child)
				? toInteger(
					child.dataset
						.productId ||
					(
						window.eilmoCfDom.isElement(child, 'INPUT')
								? child.value
								: 0
					)
				)
				: 0;
	}

	/**
	 * Resolve Eilmo variation ID.
	 *
	 * @param {HTMLElement}      item  Item.
	 * @param {HTMLInputElement} input Input.
	 *
	 * @return {number}
	 */
	function getEilmoVariationId(
		item,
		input
	) {

		let variationId =
			getDatasetInteger(
				item,
				[
					'variationId',
				]
			);

		if (
			variationId > 0
		) {
			return variationId;
		}

		variationId =
			getDatasetInteger(
				input,
				[
					'variationId',
				]
			);

		if (
			variationId > 0
		) {
			return variationId;
		}

		const child =
			item.querySelector(
				'[data-eilmo-variation-id], input[name*="[variation_id]"], input[name="variation_id"]'
			);

		return window.eilmoCfDom.isElement(child)
				? toInteger(
					child.dataset
						.variationId ||
					(
						window.eilmoCfDom.isElement(child, 'INPUT')
								? child.value
								: 0
					)
				)
				: 0;
	}

	/**
	 * Resolve Eilmo content ID.
	 *
	 * @param {HTMLElement}      item  Item.
	 * @param {HTMLInputElement} input Input.
	 *
	 * @return {string}
	 */
	function getEilmoContentId(
		item,
		input
	) {

		const variationId =
			getEilmoVariationId(
				item,
				input
			);

		const productId =
			getEilmoProductId(
				item,
				input
			);

		return String(
			variationId > 0
				? variationId
				: productId
		);
	}

	/**
	 * Resolve displayed Eilmo item price.
	 *
	 * @param {HTMLElement}      item  Item.
	 * @param {HTMLInputElement} input Input.
	 *
	 * @return {number}
	 */
	function getEilmoItemPrice(
		item,
		input
	) {

		return toNumber(
			item.dataset.price ||
			item.dataset.productPrice ||
			input.dataset.price ||
			0
		);
	}

	/**
	 * Resolve Eilmo item title.
	 *
	 * @param {HTMLElement} item Item.
	 *
	 * @return {string}
	 */
	function getEilmoItemName(
		item
	) {

		const datasetName =
			String(
				item.dataset.productName ||
				item.dataset.name ||
				''
			).trim();

		if (datasetName) {
			return datasetName;
		}

		const title =
			item.querySelector(
				SELECTORS.eiloTitle
			);

		return window.eilmoCfDom.isElement(title)
				? String(
					title.textContent ||
						''
				).trim()
				: '';
	}

	/**
	 * Initialize Eilmo quantity snapshots.
	 *
	 * @param {Document|Element} scope Scope.
	 *
	 * @return {void}
	 */
	function rememberEilmoQuantities(
		scope = document
	) {

		if (
			!scope ||
			typeof scope.querySelectorAll !==
				'function'
		) {
			return;
		}

		scope
			.querySelectorAll(
				SELECTORS.eiloQuantity
			)
			.forEach(
				function (
					input
				) {
					if (
						window.eilmoCfDom.isElement(input, 'INPUT') &&
						!quantityState.has(
							input
						)
					) {
						quantityState.set(
							input,
							toInteger(
								input.value
							)
						);
					}
				}
			);
	}

	/**
	 * Track positive Eilmo quantity delta as AddToCart.
	 *
	 * @param {HTMLInputElement} input Input.
	 *
	 * @return {void}
	 */
	function trackEilmoQuantityIncrease(
		input
	) {

		const config =
			getConfig();

		if (
			!config.events.addToCart ||
			!(
				window.eilmoCfDom.isElement(input, 'INPUT')
			)
		) {
			return;
		}

		const current =
			toInteger(
				input.value
			);

		const previous =
			quantityState.has(
				input
			)
				? toInteger(
					quantityState.get(
						input
					)
				)
				: current;

		quantityState.set(
			input,
			current
		);

		const addedQuantity =
			current -
			previous;

		if (
			addedQuantity <= 0
		) {
			return;
		}

		const item =
			getEilmoItem(
				input
			);

		if (!item) {
			return;
		}

		const contentId =
			getEilmoContentId(
				item,
				input
			);

		if (
			!contentId ||
			contentId ===
				'0'
		) {
			return;
		}

		const data = {
			content_type:
				'product',

			content_ids: [
				contentId,
			],

			contents: [
				{
					id:
						contentId,

					quantity:
						addedQuantity,
				},
			],
		};

		if (
			config.currency
		) {
			data.currency =
				config.currency;
		}

		const itemName =
			getEilmoItemName(
				item
			);

		if (itemName) {
			data.content_name =
				itemName;
		}

		const price =
			getEilmoItemPrice(
				item,
				input
			);

		if (
			price > 0
		) {
			data.value =
				Number(
					(
						price *
						addedQuantity
					).toFixed(
						2
					)
				);
		}

		trackMirrored(
			'AddToCart',
			data,
			generateEventId(
				'add_to_cart'
			)
		);
	}

	/**
	 * Remember a native Woo add signature to prevent
	 * another theme/plugin compatibility path from
	 * creating the same AddToCart again.
	 *
	 * @param {Array<Object>} contents Contents.
	 *
	 * @return {void}
	 */
	function rememberNativeAdd(
		contents
	) {

		const now =
			Date.now();

		recentNativeAdds =
			recentNativeAdds.filter(
				function (
					item
				) {
					return (
						now -
						item.timestamp
					) <
						3000;
				}
			);

		contents.forEach(
			function (
				content
			) {
				const id =
					normalizeId(
						content.id
					);

				const quantity =
					toInteger(
						content.quantity
					);

				if (
					!id ||
					quantity <= 0
				) {
					return;
				}

				recentNativeAdds.push(
					{
						id:
							id,

						quantity:
							quantity,

						timestamp:
							now,
					}
				);
			}
		);
	}

	/**
	 * Determine if equivalent native add was tracked recently.
	 *
	 * @param {Array<Object>} contents Contents.
	 *
	 * @return {boolean}
	 */
	function wasNativeAddRecentlyTracked(
		contents
	) {

		const now =
			Date.now();

		recentNativeAdds =
			recentNativeAdds.filter(
				function (
					item
				) {
					return (
						now -
						item.timestamp
					) <
						3000;
				}
			);

		if (
			!contents.length
		) {
			return false;
		}

		return contents.every(
			function (
				content
			) {
				const id =
					normalizeId(
						content.id
					);

				const quantity =
					toInteger(
						content.quantity
					);

				return recentNativeAdds.some(
					function (
						recent
					) {
						return (
							recent.id ===
								id &&
							recent.quantity ===
								quantity
						);
					}
				);
			}
		);
	}

	/**
	 * Track a validated native Woo AddToCart payload.
	 *
	 * @param {Array<Object>} contents        Contents.
	 * @param {boolean}       skipRecentGuard Skip recent guard.
	 *
	 * @return {void}
	 */
	function trackNativeAddContents(
		contents,
		skipRecentGuard = false
	) {

		const config =
			getConfig();

		if (
			!config.events.addToCart ||
			!Array.isArray(
				contents
			) ||
			!contents.length
		) {
			return;
		}

		const normalizedContents =
			contents
				.map(
					function (
						content
					) {
						const id =
							normalizeId(
								content.id
							);

						const quantity =
							Math.max(
								1,
								toInteger(
									content.quantity
								) ||
									1
							);

						if (
							!id ||
							id ===
								'0'
						) {
							return null;
						}

						return {
							id:
								id,

							quantity:
								quantity,
						};
					}
				)
				.filter(
					Boolean
				);

		if (
			!normalizedContents
				.length
		) {
			return;
		}

		if (
			!skipRecentGuard &&
			wasNativeAddRecentlyTracked(
				normalizedContents
			)
		) {
			return;
		}

		const data = {
			content_type:
				'product',

			content_ids:
				Array.from(
					new Set(
						normalizedContents
							.map(
								function (
									content
								) {
									return content.id;
								}
							)
					)
				),

			contents:
				normalizedContents,
		};

		if (
			config.currency
		) {
			data.currency =
				config.currency;
		}

		rememberNativeAdd(
			normalizedContents
		);

		trackMirrored(
			'AddToCart',
			data,
			generateEventId(
				'add_to_cart'
			)
		);
	}

	/**
	 * Track WooCommerce core jQuery AJAX add-to-cart success.
	 *
	 * @param {*} button jQuery button.
	 *
	 * @return {void}
	 */
	function trackWooAjaxAddToCart(
		button
	) {

		if (
			!button ||
			typeof button.data !==
				'function'
		) {
			return;
		}

		const productId =
			toInteger(
				button.data(
					'product_id'
				)
			);

		if (
			productId <= 0
		) {
			return;
		}

		const quantity =
			Math.max(
				1,
				toInteger(
					button.data(
						'quantity'
					)
				) ||
					1
			);

		trackNativeAddContents(
			[
				{
					id:
						String(
							productId
						),

					quantity:
						quantity,
				},
			]
		);
	}

	/**
	 * Build native product-form contents.
	 *
	 * Supports simple, variable and grouped classic forms.
	 *
	 * @param {HTMLFormElement} form Form.
	 *
	 * @return {Array<Object>}
	 */
	function getNativeFormContents(
		form
	) {

		const groupedInputs =
			Array.from(
				form.querySelectorAll(
					'input[name^="quantity["]'
				)
			);

		if (
			groupedInputs.length
		) {
			return groupedInputs
				.map(
					function (
						input
					) {
						if (
							!(
								window.eilmoCfDom.isElement(input, 'INPUT')
							)
						) {
							return null;
						}

						const match =
							String(
								input.name
							).match(
								/^quantity\[(\d+)\]$/
							);

						if (!match) {
							return null;
						}

						const quantity =
							toInteger(
								input.value
							);

						if (
							quantity <= 0
						) {
							return null;
						}

						return {
							id:
								match[1],

							quantity:
								quantity,
						};
					}
				)
				.filter(
					Boolean
				);
		}

		const variationInput =
			form.querySelector(
				'input[name="variation_id"]'
			);

		const variationId =
			window.eilmoCfDom.isElement(variationInput, 'INPUT')
					? toInteger(
						variationInput.value
					)
					: 0;

		if (
			form.classList.contains(
				'variations_form'
			) &&
			variationId <= 0
		) {
			return [];
		}

		let productId =
			variationId;

		if (
			productId <= 0
		) {
			const addInput =
				form.querySelector(
					'input[name="add-to-cart"], button[name="add-to-cart"], .single_add_to_cart_button[value]'
				);

			if (
				window.eilmoCfDom.isElement(addInput)
			) {
				productId =
					toInteger(
						window.eilmoCfDom.isElement(addInput, 'INPUT') ||
						window.eilmoCfDom.isElement(addInput, 'BUTTON')
								? addInput.value
								: addInput.getAttribute(
									'value'
								)
					);
			}
		}

		if (
			productId <= 0
		) {
			return [];
		}

		const quantityInput =
			form.querySelector(
				SELECTORS.wooQuantity
			);

		const quantity =
			window.eilmoCfDom.isElement(quantityInput, 'INPUT')
					? Math.max(
						1,
						toInteger(
							quantityInput.value
						) ||
							1
					)
					: 1;

		return [
			{
				id:
					String(
						productId
					),

				quantity:
					quantity,
			},
		];
	}

	/**
	 * Track classic non-AJAX/native product-form submission.
	 *
	 * @param {SubmitEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleNativeWooCartFormSubmit(
		event
	) {

		const config =
			getConfig();

		if (
			!config.events.addToCart ||
			!(
				window.eilmoCfDom.isElement(event.target, 'FORM')
			) ||
			!event.target.matches(
				SELECTORS.wooCartForm
			)
		) {
			return;
		}

		const contents =
			getNativeFormContents(
				event.target
			);

		if (
			!contents.length
		) {
			return;
		}

		trackNativeAddContents(
			contents
		);
	}

	/**
	 * Track non-AJAX archive add-to-cart links before navigation.
	 *
	 * @param {MouseEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleNativeWooAddLinkClick(
		event
	) {

		const config =
			getConfig();

		if (
			!config.events.addToCart ||
			!(
				window.eilmoCfDom.isElement(event.target)
			)
		) {
			return;
		}

		const link =
			event.target.closest(
				'a.add_to_cart_button:not(.ajax_add_to_cart)[data-product_id]'
			);

		if (
			!(
				window.eilmoCfDom.isElement(link, 'A')
			)
		) {
			return;
		}

		const productId =
			toInteger(
				link.dataset.productId
			);

		if (
			productId <= 0
		) {
			return;
		}

		const quantity =
			Math.max(
				1,
				toInteger(
					link.dataset.quantity
				) ||
					1
			);

		trackNativeAddContents(
			[
				{
					id:
						String(
							productId
						),

					quantity:
						quantity,
				},
			]
		);
	}

	/**
	 * Normalize Store API cart items into a snapshot map.
	 *
	 * @param {Object} cart Store API cart response.
	 *
	 * @return {Map}
	 */
	function buildStoreApiSnapshot(
		cart
	) {

		const snapshot =
			new Map();

		const items =
			cart &&
			Array.isArray(
				cart.items
			)
				? cart.items
				: [];

		items.forEach(
			function (
				item
			) {

				if (
					!item ||
					typeof item !==
						'object'
				) {
					return;
				}

				const id =
					toInteger(
						item.id
					);

				const quantity =
					toInteger(
						item.quantity
					);

				const key =
					normalizeId(
						item.key ||
						(
							'product_' +
							id
						)
					);

				if (
					!key ||
					id <= 0 ||
					quantity <= 0
				) {
					return;
				}

				snapshot.set(
					key,
					{
						id:
							String(
								id
							),

						quantity:
							quantity,
					}
				);
			}
		);

		return snapshot;
	}

	/**
	 * Fetch current Store API cart.
	 *
	 * @return {Promise<Map>}
	 */
	function fetchStoreApiCartSnapshot() {

		const url =
			getConfig()
				.woo
				.storeApiCartUrl;

		if (!url) {
			return Promise.resolve(
				new Map()
			);
		}

		return fetch(
			url,
			{
				method:
					'GET',

				credentials:
					'same-origin',

				headers: {
					Accept:
						'application/json',
				},

				cache:
					'no-store',
			}
		)
			.then(
				function (
					response
				) {
					if (
						!response.ok
					) {
						throw new Error(
							'Store API cart returned ' +
								response.status
						);
					}

					return response.json();
				}
			)
			.then(
				buildStoreApiSnapshot
			);
	}

	/**
	 * Initialize cart snapshot used by Woo Blocks
	 * AddToCart diffing.
	 *
	 * @return {void}
	 */
	function initializeStoreApiSnapshot() {

		const config =
			getConfig();

		if (
			!config.events.addToCart ||
			!config.woo.storeApiCartUrl ||
			storeApiSnapshotLoading
		) {
			return;
		}

		storeApiSnapshotLoading =
			true;

		fetchStoreApiCartSnapshot()
			.then(
				function (
					snapshot
				) {
					storeApiCartSnapshot =
						snapshot;

					storeApiSnapshotReady =
						true;
				}
			)
			.catch(
				function (
					error
				) {
					debug(
						'Store API initial snapshot failed',
						error
					);
				}
			)
			.finally(
				function () {
					storeApiSnapshotLoading =
						false;
				}
			);
	}

	/**
	 * Handle successful Woo Blocks AddToCart event.
	 *
	 * The current Store API cart is compared with the
	 * previous snapshot so only positive quantity deltas
	 * become AddToCart events.
	 *
	 * @return {void}
	 */
	function handleWooBlocksAddedToCart() {

		const config =
			getConfig();

		if (
			!config.events.addToCart ||
			!config.woo.storeApiCartUrl
		) {
			return;
		}

		window.setTimeout(
			function () {

				fetchStoreApiCartSnapshot()
					.then(
						function (
							currentSnapshot
						) {

							if (
								!storeApiSnapshotReady
							) {
								storeApiCartSnapshot =
									currentSnapshot;

								storeApiSnapshotReady =
									true;

								return;
							}

							const added = [];

							currentSnapshot
								.forEach(
									function (
										current,
										key
									) {

										const previous =
											storeApiCartSnapshot
												.get(
													key
												);

										const previousQuantity =
											previous
												? toInteger(
													previous.quantity
												)
												: 0;

										const delta =
											toInteger(
												current.quantity
											) -
											previousQuantity;

										if (
											delta <= 0
										) {
											return;
										}

										added.push(
											{
												id:
													current.id,

												quantity:
													delta,
											}
										);
									}
								);

							storeApiCartSnapshot =
								currentSnapshot;

							if (
								added.length
							) {
								const freshAdded =
									added.filter(
										function (
											content
										) {
											return !wasNativeAddRecentlyTracked(
												[
													content,
												]
											);
										}
									);

								if (
									freshAdded.length
								) {
									trackNativeAddContents(
										freshAdded,
										true
									);
								}
							}
						}
					)
					.catch(
						function (
							error
						) {
							debug(
								'Store API AddToCart diff failed',
								error
							);
						}
					);
			},
			100
		);
	}

	/**
	 * Build aggregate ViewContent for an Eilmo checkout
	 * page when native single-product context is not
	 * available.
	 *
	 * @return {Object}
	 */
	function getEilmoViewContentData() {

		const config =
			getConfig();

		const ids = [];
		const contents = [];

		document
			.querySelectorAll(
				SELECTORS.eiloItem
			)
			.forEach(
				function (
					item
				) {

					if (
						!(
							window.eilmoCfDom.isElement(item)
						)
					) {
						return;
					}

					const input =
						item.querySelector(
							SELECTORS.eiloQuantity
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						)
					) {
						return;
					}

					const productId =
						getEilmoProductId(
							item,
							input
						);

					if (
						productId <= 0
					) {
						return;
					}

					const id =
						String(
							productId
						);

					if (
						ids.includes(
							id
						)
					) {
						return;
					}

					ids.push(
						id
					);

					contents.push(
						{
							id:
								id,

							quantity:
								1,
						}
					);
				}
			);

		if (
			!ids.length
		) {
			return {};
		}

		const data = {
			content_type:
				'product',

			content_ids:
				ids,

			contents:
				contents,
		};

		if (
			config.currency
		) {
			data.currency =
				config.currency;
		}

		return data;
	}

	/**
	 * Track ViewContent once per page.
	 *
	 * @return {void}
	 */
	function trackViewContent() {

		const config =
			getConfig();

		if (
			!config.events.viewContent ||
			checkoutViewContentFired
		) {
			return;
		}

		let data = {};

		if (
			config.viewContent &&
			Array.isArray(
				config.viewContent
					.content_ids
			) &&
			config.viewContent
				.content_ids
				.length
		) {
			data =
				config.viewContent;
		} else {
			data =
				getEilmoViewContentData();
		}

		if (
			!Array.isArray(
				data.content_ids
			) ||
			!data.content_ids.length
		) {
			return;
		}

		if (
			trackMirrored(
				'ViewContent',
				data,
				generateEventId(
					'view_content'
				)
			)
		) {
			checkoutViewContentFired =
				true;
		}
	}

	/**
	 * Get Eilmo checkout preview total.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function getEilmoCheckoutTotal(
		checkout
	) {

		const summary =
			window.eilmoCfSummary;

		if (
			summary &&
			typeof summary.getValues ===
				'function'
		) {
			const values =
				summary.getValues(
					checkout
				);

			if (
				values &&
				typeof values ===
					'object'
			) {
				const total =
					toNumber(
						values.grandTotal ??
						values.grand_total ??
						values.baseGrandTotal ??
						values.base_grand_total ??
						0
					);

				if (
					total > 0
				) {
					return total;
				}
			}
		}

		return toNumber(
			checkout.dataset.grandTotal ||
			checkout.dataset.baseGrandTotal ||
			0
		);
	}

	/**
	 * Build product contents from Eilmo checkout payload.
	 *
	 * @param {Object} payload Payload.
	 *
	 * @return {Object}
	 */
	function getPayloadContents(
		payload
	) {

		const items =
			payload &&
			Array.isArray(
				payload.items
			)
				? payload.items
				: [];

		const contentIds = [];
		const contents = [];

		let numItems = 0;

		items.forEach(
			function (
				item
			) {

				if (
					!item ||
					typeof item !==
						'object'
				) {
					return;
				}

				const productId =
					toInteger(
						item.product_id
					);

				const variationId =
					toInteger(
						item.variation_id
					);

				const quantity =
					Math.max(
						1,
						toInteger(
							item.quantity
						) ||
							1
					);

				const contentId =
					String(
						variationId > 0
							? variationId
							: productId
					);

				if (
					!contentId ||
					contentId ===
						'0'
				) {
					return;
				}

				if (
					!contentIds.includes(
						contentId
					)
				) {
					contentIds.push(
						contentId
					);
				}

				contents.push(
					{
						id:
							contentId,

						quantity:
							quantity,
					}
				);

				numItems +=
					quantity;
			}
		);

		return {
			contentIds:
				contentIds,

			contents:
				contents,

			numItems:
				numItems,
		};
	}

	/**
	 * Keep only safe Eilmo customer fields for the
	 * nonce-protected first-party CAPI bridge.
	 *
	 * @param {Object} payload Checkout payload.
	 *
	 * @return {Object}
	 */
	function getEilmoMetaCustomer(
		payload
	) {

		const source =
			payload &&
			payload.customer &&
			typeof payload.customer ===
				'object'
					? payload.customer
					: {};

		const keys = [
			'billing_first_name',
			'billing_last_name',
			'billing_phone',
			'billing_email',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
		];

		const customer = {};

		keys.forEach(
			function (
				key
			) {
				if (
					typeof source[
						key
					] ===
						'string' &&
					source[
						key
					]
						.trim() !==
						''
				) {
					customer[
						key
					] =
						source[
							key
						]
							.trim();
				}
			}
		);

		return customer;
	}

	/**
	 * Collect populated classic Woo checkout fields.
	 *
	 * @return {Object}
	 */
	function getNativeClassicCustomer() {

		const form =
			document.querySelector(
				'form.checkout'
			);

		if (
			!(
				window.eilmoCfDom.isElement(form, 'FORM')
			)
		) {
			return {};
		}

		const keys = [
			'billing_first_name',
			'billing_last_name',
			'billing_phone',
			'billing_email',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
		];

		const customer = {};

		keys.forEach(
			function (
				key
			) {

				const field =
					form.querySelector(
						'[name="' +
							key +
							'"]'
					);

				if (
					window.eilmoCfDom.isElement(field, 'INPUT') ||
					window.eilmoCfDom.isElement(field, 'SELECT')
				) {
					const value =
						String(
							field.value ||
								''
						).trim();

					if (value) {
						customer[
							key
						] =
							value;
					}
				}
			}
		);

		return customer;
	}

	/**
	 * Track native WooCommerce InitiateCheckout on
	 * checkout page load.
	 *
	 * This path is independent from Eilmo custom
	 * checkout events.
	 *
	 * @return {void}
	 */
	function trackNativeWooInitiateCheckout() {

		const config =
			getConfig();

		if (
			!config.events.initiateCheckout ||
			document.querySelector(
				SELECTORS.eiloCheckout
			)
		) {
			return;
		}

		const context =
			config.nativeCheckout;

		const eventId =
			normalizeId(
				context.event_id ||
					''
			);

		if (
			!eventId ||
			!Array.isArray(
				context.contents
			) ||
			!context.contents.length
		) {
			return;
		}

		const storageKey =
			STORAGE.checkoutPrefix +
			eventId;

		if (
			storageGet(
				window.sessionStorage,
				storageKey
			) ===
				'1'
		) {
			return;
		}

		const data = {
			content_type:
				String(
					context.content_type ||
						'product'
				),

			content_ids:
				Array.isArray(
					context.content_ids
				)
					? context.content_ids
						.map(
							String
						)
					: [],

			contents:
				context.contents,

			num_items:
				toInteger(
					context.num_items ||
						0
				),

			value:
				toNumber(
					context.value ||
						0
				),

			currency:
				String(
					context.currency ||
					config.currency ||
					''
				),
		};

		if (
			trackMirrored(
				'InitiateCheckout',
				data,
				eventId,
				getNativeClassicCustomer()
			)
		) {
			storageSet(
				window.sessionStorage,
				storageKey,
				'1'
			);
		}
	}

	/**
	 * Get Quick Checkout root from lifecycle event.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {HTMLElement|null}
	 */
	function getQuickCheckoutRootFromEvent(
		event
	) {

		const detail =
			event.detail &&
			typeof event.detail ===
				'object'
					? event.detail
					: {};

		if (
			window.eilmoCfDom.isElement(detail.root) &&
			detail.root.matches(
				SELECTORS.quickCheckout
			)
		) {
			return detail.root;
		}

		if (
			window.eilmoCfDom.isElement(event.target)
		) {
			const root =
				event.target.closest(
					SELECTORS.quickCheckout
				);

			return window.eilmoCfDom.isElement(root)
					? root
					: null;
		}

		return null;
	}

	/**
	 * Get Quick Checkout root controlled by an
	 * Order Now trigger.
	 *
	 * @param {HTMLElement} trigger Trigger.
	 *
	 * @return {HTMLElement|null}
	 */
	function getQuickCheckoutRootFromTrigger(
		trigger
	) {

		if (
			!(
				window.eilmoCfDom.isElement(trigger)
			)
		) {
			return null;
		}

		const controls =
			String(
				trigger.getAttribute(
					'aria-controls'
				) ||
					''
			).trim();

		if (controls) {
			const controlled =
				document.getElementById(
					controls
				);

			if (
				window.eilmoCfDom.isElement(controlled) &&
				controlled.matches(
					SELECTORS.quickCheckout
				)
			) {
				return controlled;
			}
		}

		const actions =
			trigger.closest(
				SELECTORS.quickCheckoutActions
			);

		if (
			!(
				window.eilmoCfDom.isElement(actions)
			)
		) {
			return null;
		}

		let sibling =
			actions.nextElementSibling;

		while (sibling) {

			if (
				window.eilmoCfDom.isElement(sibling) &&
				sibling.matches(
					SELECTORS.quickCheckout
				)
			) {
				return sibling;
			}

			sibling =
				sibling.nextElementSibling;
		}

		return null;
	}

	/**
	 * Build checkout payload directly from synchronized
	 * Eilmo items.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getQuickCheckoutDomPayload(
		checkout
	) {

		const items = [];

		checkout
			.querySelectorAll(
				SELECTORS.eiloQuantity
			)
			.forEach(
				function (
					input
				) {

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						)
					) {
						return;
					}

					const quantity =
						toInteger(
							input.value
						);

					if (
						quantity <= 0
					) {
						return;
					}

					const item =
						getEilmoItem(
							input
						);

					if (!item) {
						return;
					}

					const productId =
						getEilmoProductId(
							item,
							input
						);

					const variationId =
						getEilmoVariationId(
							item,
							input
						);

					if (
						productId <= 0
					) {
						return;
					}

					items.push(
						{
							product_id:
								productId,

							variation_id:
								variationId,

							quantity:
								quantity,
						}
					);
				}
			);

		return {
			items:
				items,
		};
	}

	/**
	 * Build Quick Checkout payload at successful open time.
	 *
	 * Existing checkout.js payload builder is preferred so
	 * Meta receives the same structure used by the order
	 * pipeline.
	 *
	 * Lifecycle product state and synchronized DOM items
	 * are fallbacks.
	 *
	 * @param {CustomEvent|null} event    Lifecycle event.
	 * @param {HTMLElement}      checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getQuickCheckoutPayload(
		event,
		checkout
	) {

		const checkoutApi =
			window.eilmoCfCheckout;

		if (
			checkoutApi &&
			typeof checkoutApi.buildPayload ===
				'function'
		) {
			try {
				const payload =
					checkoutApi.buildPayload(
						checkout
					);

				if (
					payload &&
					typeof payload ===
						'object' &&
					Array.isArray(
						payload.items
					) &&
					payload.items.length
				) {
					return payload;
				}
			} catch (error) {
				debug(
					'Quick Checkout payload build failed',
					error
				);
			}
		}

		const detail =
			event &&
			event.detail &&
			typeof event.detail ===
				'object'
					? event.detail
					: {};

		const product =
			detail.product &&
			typeof detail.product ===
				'object'
					? detail.product
					: {};

		const productId =
			toInteger(
				product.productId ??
					product.product_id ??
					0
			);

		if (
			productId > 0
		) {
			return {
				items: [
					{
						product_id:
							productId,

						variation_id:
							toInteger(
								product.variationId ??
								product.variation_id ??
								0
							),

						quantity:
							Math.max(
								1,
								toInteger(
									product.quantity ??
										1
								) ||
									1
							),
					},
				],
			};
		}

		return getQuickCheckoutDomPayload(
			checkout
		);
	}

	/**
	 * Track one Quick Checkout open as InitiateCheckout.
	 *
	 * The marker intentionally remains for the lifetime
	 * of the current page so closing/reopening the same
	 * drawer cannot inflate InitiateCheckout.
	 *
	 * @param {HTMLElement}      root     Quick Checkout root.
	 * @param {HTMLElement}      checkout Checkout.
	 * @param {CustomEvent|null} event    Lifecycle event.
	 *
	 * @return {void}
	 */
	function trackQuickCheckoutInitiated(
		root,
		checkout,
		event = null
	) {

		const config =
			getConfig();

		if (
			!config.events
				.initiateCheckout ||
			'yes' ===
				root.dataset
					.metaInitiateCheckoutTracked
		) {
			return;
		}

		const payload =
			getQuickCheckoutPayload(
				event,
				checkout
			);

		const productData =
			getPayloadContents(
				payload
			);

		if (
			!productData.contentIds
				.length ||
			!productData.contents
				.length
		) {
			return;
		}

		const data = {
			content_type:
				'product',

			content_ids:
				productData.contentIds,

			contents:
				productData.contents,

			num_items:
				productData.numItems,
		};

		if (
			config.currency
		) {
			data.currency =
				config.currency;
		}

		const total =
			getEilmoCheckoutTotal(
				checkout
			);

		if (
			total > 0
		) {
			data.value =
				total;
		}

		if (
			trackMirrored(
				'InitiateCheckout',
				data,
				generateEventId(
					'initiate_checkout'
				),
				getEilmoMetaCustomer(
					payload
				)
			)
		) {
			root.dataset
				.metaInitiateCheckoutTracked =
					'yes';
		}
	}

	/**
	 * Track Quick Checkout lifecycle event after a
	 * successful Order drawer open.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleEilmoQuickCheckoutOpen(
		event
	) {

		const root =
			getQuickCheckoutRootFromEvent(
				event
			);

		if (!root) {
			return;
		}

		const detail =
			event.detail &&
			typeof event.detail ===
				'object'
					? event.detail
					: {};

		const mode =
			String(
				detail.mode ||
				root.dataset.triggerMode ||
				root.dataset.mode ||
				'order'
			)
				.trim()
				.toLowerCase();

		if (
			'order' !== mode
		) {
			return;
		}

		const checkout =
			window.eilmoCfDom.isElement(detail.checkout)
					? detail.checkout
					: root.querySelector(
						SELECTORS.eiloCheckout
					);

		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return;
		}

		trackQuickCheckoutInitiated(
			root,
			checkout,
			event
		);
	}

	/**
	 * Compatibility fallback for Quick Checkout versions
	 * that do not dispatch eilmo:quickCheckoutOpen.
	 *
	 * The click itself is not tracked. Tracking waits
	 * until the next animation frame and verifies that
	 * the Order drawer actually opened.
	 *
	 * @param {MouseEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleEilmoQuickCheckoutTriggerClick(
		event
	) {

		if (
			!(
				window.eilmoCfDom.isElement(event.target)
			)
		) {
			return;
		}

		const trigger =
			event.target.closest(
				SELECTORS.quickCheckoutTrigger
			);

		if (
			!(
				window.eilmoCfDom.isElement(trigger)
			)
		) {
			return;
		}

		const root =
			getQuickCheckoutRootFromTrigger(
				trigger
			);

		if (!root) {
			return;
		}

		window.requestAnimationFrame(
			function () {

				if (
					'yes' !==
						root.dataset.open ||
					root.hidden ||
					'false' !==
						root.getAttribute(
							'aria-hidden'
						)
				) {
					return;
				}

				const checkout =
					root.querySelector(
						SELECTORS.eiloCheckout
					);

				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return;
				}

				trackQuickCheckoutInitiated(
					root,
					checkout
				);
			}
		);
	}

	/**
	 * Track Eilmo InitiateCheckout at validated submit time.
	 *
	 * Quick Checkout is tracked earlier when its Order
	 * drawer opens.
	 *
	 * This submit path remains as compatibility fallback
	 * for other Eilmo checkout surfaces that do not expose
	 * an open lifecycle event.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleEilmoOrderSubmitting(
		event
	) {

		const config =
			getConfig();

		if (
			!config.events
				.initiateCheckout
		) {
			return;
		}

		const checkout =
			getEilmoCheckoutFromEvent(
				event
			);

		if (!checkout) {
			return;
		}

		const quickRoot =
			checkout.closest(
				SELECTORS.quickCheckout
			);

		if (
			window.eilmoCfDom.isElement(quickRoot) &&
			'yes' ===
				quickRoot.dataset
					.metaInitiateCheckoutTracked
		) {
			return;
		}

		const detail =
			event.detail &&
			typeof event.detail ===
				'object'
					? event.detail
					: {};

		const payload =
			detail.payload &&
			typeof detail.payload ===
				'object'
					? detail.payload
					: {};

		const token =
			normalizeId(
				payload.checkout_token ||
				detail.checkoutToken ||
				checkout.dataset.checkoutToken ||
				''
			);

		const eventId =
			token
				? normalizeId(
					'eilmo_initiate_checkout_' +
						token
				)
				: generateEventId(
					'initiate_checkout'
				);

		const storageKey =
			STORAGE.checkoutPrefix +
			eventId;

		if (
			storageGet(
				window.sessionStorage,
				storageKey
			) ===
				'1'
		) {
			return;
		}

		const productData =
			getPayloadContents(
				payload
			);

		if (
			!productData.contentIds
				.length ||
			!productData.contents
				.length
		) {
			return;
		}

		const data = {
			content_type:
				'product',

			content_ids:
				productData.contentIds,

			contents:
				productData.contents,

			num_items:
				productData.numItems,
		};

		if (
			config.currency
		) {
			data.currency =
				config.currency;
		}

		const total =
			getEilmoCheckoutTotal(
				checkout
			);

		if (
			total > 0
		) {
			data.value =
				total;
		}

		if (
			trackMirrored(
				'InitiateCheckout',
				data,
				eventId,
				getEilmoMetaCustomer(
					payload
				)
			)
		) {
			storageSet(
				window.sessionStorage,
				storageKey,
				'1'
			);
		}
	}

	/**
	 * Track browser Purchase once and guard
	 * Thank You reloads.
	 *
	 * Browser Purchase is hard-blocked in Advanced
	 * Purchase mode.
	 *
	 * @param {Object} purchase Purchase context.
	 *
	 * @return {void}
	 */
	function trackPurchase(
		purchase
	) {

		const config =
			getConfig();

		if (
			!browserPurchaseAllowed() ||
			!purchase ||
			typeof purchase !==
				'object'
		) {
			return;
		}

		const orderId =
			toInteger(
				purchase.order_id ||
				purchase.orderId ||
				0
			);

		const eventId =
			normalizeId(
				purchase.event_id ||
				purchase.eventId ||
				(
					orderId > 0
						? 'eilmo_purchase_' +
							orderId
						: ''
				)
			);

		if (!eventId) {
			return;
		}

		const storageKey =
			STORAGE.purchasePrefix +
			eventId;

		if (
			storageGet(
				window.localStorage,
				storageKey
			) ===
				'1'
		) {
			return;
		}

		const data = {
			content_type:
				String(
					purchase.content_type ||
						'product'
				),

			content_ids:
				Array.isArray(
					purchase.content_ids
				)
					? purchase.content_ids
						.map(
							String
						)
					: [],

			contents:
				Array.isArray(
					purchase.contents
				)
					? purchase.contents
					: [],

			value:
				toNumber(
					purchase.value
				),

			currency:
				String(
					purchase.currency ||
					config.currency ||
					''
				),
		};

		const numItems =
			toInteger(
				purchase.num_items ||
				purchase.numItems ||
				0
			);

		if (
			numItems > 0
		) {
			data.num_items =
				numItems;
		}

		if (
			trackBrowser(
				'Purchase',
				data,
				eventId
			)
		) {
			storageSet(
				window.localStorage,
				storageKey,
				'1'
			);
		}
	}

	/**
	 * Eilmo no-redirect browser Purchase fallback.
	 *
	 * Normal redirecting flows are tracked from the
	 * validated WooCommerce Order Received page instead.
	 *
	 * Advanced status-based Purchase never uses this
	 * browser fallback.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleEilmoOrderSuccess(
		event
	) {

		const config =
			getConfig();

		if (
			!browserPurchaseAllowed()
		) {
			return;
		}

		const detail =
			event.detail &&
			typeof event.detail ===
				'object'
					? event.detail
					: {};

		const response =
			detail.response &&
			typeof detail.response ===
				'object'
					? detail.response
					: {};

		const redirect =
			String(
				detail.redirect ||
				response.redirect ||
				''
			).trim();

		if (
			redirect ||
			String(
				response.payment_flow ||
					''
			) ===
				'gateway_handoff'
		) {
			return;
		}

		const checkout =
			getEilmoCheckoutFromEvent(
				event
			);

		const orderId =
			toInteger(
				detail.orderId ||
					response.order_id ||
					0
			);

		if (
			orderId <= 0 ||
			!checkout
		) {
			return;
		}

		const payload =
			window.eilmoCfCheckout &&
			typeof window.eilmoCfCheckout
				.buildPayload ===
				'function'
					? window.eilmoCfCheckout
						.buildPayload(
							checkout
						)
					: {};

		const productData =
			getPayloadContents(
				payload
			);

		if (
			!productData.contentIds
				.length ||
			!productData.contents
				.length
		) {
			return;
		}

		const totals =
			response.totals &&
			typeof response.totals ===
				'object'
					? response.totals
					: {};

		trackPurchase(
			{
				event_id:
					'eilmo_purchase_' +
						orderId,

				order_id:
					orderId,

				content_type:
					'product',

				content_ids:
					productData.contentIds,

				contents:
					productData.contents,

				num_items:
					productData.numItems,

				value:
					toNumber(
						totals.grand_total ||
							getEilmoCheckoutTotal(
								checkout
							)
					),

				currency:
					config.currency,
			}
		);
	}

	/**
	 * Eilmo quantity lifecycle handler.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleEilmoQuantityChange(
		event
	) {

		const checkout =
			getEilmoCheckoutFromEvent(
				event
			);

		if (!checkout) {
			return;
		}

		checkout
			.querySelectorAll(
				SELECTORS.eiloQuantity
			)
			.forEach(
				function (
					input
				) {
					if (
						window.eilmoCfDom.isElement(input, 'INPUT')
					) {
						trackEilmoQuantityIncrease(
							input
						);
					}
				}
			);
	}

	/**
	 * Eilmo direct input/change compatibility fallback.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleEilmoInputChange(
		event
	) {

		if (
			!(
				window.eilmoCfDom.isElement(event.target, 'INPUT')
			) ||
			!event.target.matches(
				SELECTORS.eiloQuantity
			)
		) {
			return;
		}

		trackEilmoQuantityIncrease(
			event.target
		);
	}

	/**
	 * Register WooCommerce core AJAX AddToCart
	 * compatibility.
	 *
	 * @return {void}
	 */
	function registerWooCommerceAjaxAddToCart() {

		if (
			!window.jQuery ||
			typeof window.jQuery !==
				'function'
		) {
			return;
		}

		window.jQuery(
			document.body
		).on(
			'added_to_cart',
			function (
				event,
				fragments,
				cartHash,
				button
			) {

				void event;
				void fragments;
				void cartHash;

				trackWooAjaxAddToCart(
					button
				);
			}
		);
	}

	/**
	 * Initialize global Meta tracking.
	 *
	 * @return {void}
	 */
	function initialize() {

		const config =
			getConfig();

		if (
			!config.enabled
		) {
			return;
		}

		rememberEilmoQuantities(
			document
		);

		if (
			config.events.pageView &&
			browserEnabled()
		) {
			trackBrowser(
				'PageView',
				{}
			);
		}

		trackViewContent();

		trackNativeWooInitiateCheckout();

		/*
		 * Thank You page browser Purchase.
		 *
		 * browserPurchaseAllowed() guarantees this is
		 * Basic strategy only.
		 */
		if (
			browserPurchaseAllowed() &&
			config.purchase &&
			typeof config.purchase ===
				'object' &&
			toInteger(
				config.purchase.order_id ||
					0
			) > 0
		) {
			trackPurchase(
				config.purchase
			);
		}

		registerWooCommerceAjaxAddToCart();

		initializeStoreApiSnapshot();

		const observer =
			new MutationObserver(
				function (
					mutations
				) {

					mutations.forEach(
						function (
							mutation
						) {

							mutation.addedNodes
								.forEach(
									function (
										node
									) {

										if (
											window.eilmoCfDom.isElement(node)
										) {
											rememberEilmoQuantities(
												node
											);
										}
									}
								);
						}
					);

					if (
						config.events.viewContent &&
						!checkoutViewContentFired
					) {
						trackViewContent();
					}
				}
			);

		if (
			document.body
		) {
			observer.observe(
				document.body,
				{
					childList:
						true,

					subtree:
						true,
				}
			);
		}
	}

	/*
	 * Eilmo checkout lifecycle.
	 */
	document.addEventListener(
		'eilmo:quantityChange',
		handleEilmoQuantityChange
	);

	document.addEventListener(
		'eilmo:quickCheckoutOpen',
		handleEilmoQuickCheckoutOpen
	);

	document.addEventListener(
		'click',
		handleEilmoQuickCheckoutTriggerClick,
		true
	);

	document.addEventListener(
		'eilmo:orderSubmitting',
		handleEilmoOrderSubmitting
	);

	document.addEventListener(
		'eilmo:orderSuccess',
		handleEilmoOrderSuccess
	);

	document.addEventListener(
		'input',
		handleEilmoInputChange
	);

	document.addEventListener(
		'change',
		handleEilmoInputChange
	);

	/*
	 * Native/classic WooCommerce add-to-cart paths.
	 */
	document.addEventListener(
		'submit',
		handleNativeWooCartFormSubmit,
		true
	);

	document.addEventListener(
		'click',
		handleNativeWooAddLinkClick,
		true
	);

	/*
	 * WooCommerce Blocks successful AddToCart event.
	 */
	if (
		document.body
	) {
		document.body.addEventListener(
			'wc-blocks_added_to_cart',
			handleWooBlocksAddedToCart
		);
	}

	if (
		document.readyState ===
			'loading'
	) {
		document.addEventListener(
			'DOMContentLoaded',
			function () {

				/*
				 * Body may not have existed when
				 * this module was evaluated.
				 */
				if (
					document.body
				) {
					document.body
						.removeEventListener(
							'wc-blocks_added_to_cart',
							handleWooBlocksAddedToCart
						);

					document.body
						.addEventListener(
							'wc-blocks_added_to_cart',
							handleWooBlocksAddedToCart
						);
				}

				initialize();
			}
		);
	} else {

		if (
			document.body
		) {
			document.body
				.removeEventListener(
					'wc-blocks_added_to_cart',
					handleWooBlocksAddedToCart
				);

			document.body
				.addEventListener(
					'wc-blocks_added_to_cart',
					handleWooBlocksAddedToCart
				);
		}

		initialize();
	}
})();
