/**
 * Eilmo Checkout Flow - Abandoned Checkout.
 *
 * Handles:
 * - Meaningful checkout activity tracking.
 * - Debounced AJAX synchronization.
 * - Customer/product checkout snapshots.
 * - Successful checkout tracking cleanup.
 *
 * Important:
 *
 * This module never creates or modifies WooCommerce
 * orders.
 *
 * After checkout.js reports a successful order, this
 * module only removes its own Abandoned Checkout record.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	/*
	 * Editor interactions must never create or update Abandoned Checkout
	 * records. The live frontend continues to use the normal tracker.
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

	const SELECTORS = {
		checkout:
			'[data-eilmo-checkout]',

		quantityInput:
			'[data-eilmo-quantity-input]',

		paymentMethod:
			'[data-eilmo-payment-method]',

		paymentRadio:
			'[data-eilmo-payment-radio]',
	};

	const TOKEN_STORAGE_PREFIX =
		'eilmo_cf_abandoned_token:';

	const DEFAULT_DEBOUNCE =
		900;

	const timers =
		new WeakMap();

	const requestStates =
		new WeakMap();

	const initialized =
		new WeakSet();

	/**
	 * Successfully completed checkout instances.
	 *
	 * Once completed, no more tracking is permitted for
	 * that rendered checkout.
	 */
	const completed =
		new WeakSet();

	function isEnabled() {
		const config =
			window.eilmoCf || {};

		const abandoned =
			config.abandonedCheckout;

		return Boolean(
			abandoned &&
			typeof abandoned ===
				'object' &&
			(
				abandoned.enabled ===
					true ||
				abandoned.enabled ===
					'yes'
			)
		);
	}

	function getConfig() {
		const config =
			window.eilmoCf || {};

		const abandoned =
			config.abandonedCheckout &&
			typeof config.abandonedCheckout ===
				'object'
				? config.abandonedCheckout
				: {};

		return {
			ajaxUrl:
				typeof config.ajaxUrl ===
					'string'
					? config.ajaxUrl
					: '',

			nonce:
				typeof abandoned.nonce ===
					'string'
					? abandoned.nonce
					: '',

			action:
				typeof abandoned.action ===
					'string' &&
				abandoned.action !== ''
					? abandoned.action
					: 'eilmo_cf_track_abandoned_checkout',

			completeAction:
				'eilmo_cf_complete_abandoned_checkout',

			debounce:
				Number.isFinite(
					Number(
						abandoned.debounce
					)
				)
					? Math.max(
						300,
						Number(
							abandoned.debounce
						)
					)
					: DEFAULT_DEBOUNCE,
		};
	}

	function toNumber(value) {
		const parsed =
			Number.parseFloat(
				String(
					value ?? ''
				)
			);

		return Number.isFinite(
			parsed
		)
			? Math.max(
				0,
				parsed
			)
			: 0;
	}

	function toInteger(value) {
		const parsed =
			Number.parseInt(
				String(
					value ?? ''
				),
				10
			);

		return Number.isFinite(
			parsed
		)
			? Math.max(
				0,
				parsed
			)
			: 0;
	}

	function normalizeOfferIds(values) {
		if (
			!Array.isArray(
				values
			)
		) {
			return [];
		}

		return Array.from(
			new Set(
				values
					.map(
						function (value) {
							return String(
								value || ''
							)
								.trim()
								.toLowerCase()
								.replace(
									/[^a-z0-9_-]/g,
									''
								);
						}
					)
					.filter(
						Boolean
					)
			)
		);
	}

	function getCheckout(source) {
		if (
			window.eilmoCfDom.isElement(source) &&
			source.matches(
				SELECTORS.checkout
			)
		) {
			return source;
		}

		if (
			window.eilmoCfDom.isElement(source)
		) {
			const checkout =
				source.closest(
					SELECTORS.checkout
				);

			return window.eilmoCfDom.isElement(checkout)
					? checkout
					: null;
		}

		return null;
	}

	function getCheckoutFromEvent(event) {
		if (
			event instanceof
				CustomEvent &&
			event.detail &&
			window.eilmoCfDom.isElement(event.detail.checkout)
		) {
			return event.detail.checkout;
		}

		return window.eilmoCfDom.isElement(event.target)
				? getCheckout(
					event.target
				)
				: null;
	}

	function getStorageKey(checkout) {
		return (
			TOKEN_STORAGE_PREFIX +
			window.location.pathname +
			':' +
			(
				checkout.id ||
				'default'
			)
		);
	}

	function generateTrackingToken() {
		if (
			window.crypto &&
			typeof window.crypto.getRandomValues ===
				'function'
		) {
			const bytes =
				new Uint8Array(
					32
				);

			window.crypto.getRandomValues(
				bytes
			);

			return Array.from(
				bytes
			)
				.map(
					function (value) {
						return value
							.toString(
								16
							)
							.padStart(
								2,
								'0'
							);
					}
				)
				.join('');
		}

		let token =
			'';

		while (
			token.length < 64
		) {
			token +=
				Math.floor(
					Math.random() *
						0xffffffff
				)
					.toString(
						16
					)
					.padStart(
						8,
						'0'
					);
		}

		return token
			.slice(
				0,
				64
			)
			.toLowerCase();
	}

	function isValidToken(token) {
		return (
			typeof token ===
				'string' &&
			/^[a-f0-9]{64}$/i.test(
				token
			)
		);
	}

	function readTrackingToken(checkout) {
		const datasetToken =
			String(
				checkout.dataset
					.abandonedTrackingToken ||
					''
			)
				.trim()
				.toLowerCase();

		if (
			isValidToken(
				datasetToken
			)
		) {
			return datasetToken;
		}

		try {
			const stored =
				String(
					window.sessionStorage
						.getItem(
							getStorageKey(
								checkout
							)
						) ||
						''
				)
					.trim()
					.toLowerCase();

			return isValidToken(
				stored
			)
				? stored
				: '';
		} catch (error) {
			return '';
		}
	}

	function storeTrackingToken(
		checkout,
		token
	) {
		token =
			String(
				token || ''
			)
				.trim()
				.toLowerCase();

		if (
			!isValidToken(
				token
			)
		) {
			return;
		}

		checkout.dataset
			.abandonedTrackingToken =
				token;

		try {
			window.sessionStorage.setItem(
				getStorageKey(
					checkout
				),
				token
			);
		} catch (error) {
			/*
			 * Dataset storage is sufficient for the
			 * current page lifecycle.
			 */
		}
	}

	function getTrackingToken(checkout) {
		let token =
			readTrackingToken(
				checkout
			);

		if (!token) {
			token =
				generateTrackingToken();

			storeTrackingToken(
				checkout,
				token
			);
		}

		return token;
	}

	function clearTrackingToken(checkout) {
		try {
			window.sessionStorage.removeItem(
				getStorageKey(
					checkout
				)
			);
		} catch (error) {
			/*
			 * Nothing else required.
			 */
		}

		delete checkout.dataset
			.abandonedTrackingToken;
	}

	function getCheckoutApi() {
		return (
			window.eilmoCfCheckout &&
			typeof window.eilmoCfCheckout ===
				'object'
		)
			? window.eilmoCfCheckout
			: null;
	}

	function getCustomerSnapshot(checkout) {
		const api =
			getCheckoutApi();

		let customer =
			{};

		if (
			api &&
			typeof api.getCustomerData ===
				'function'
		) {
			const result =
				api.getCustomerData(
					checkout
				);

			if (
				result &&
				typeof result ===
					'object' &&
				!Array.isArray(
					result
				)
			) {
				customer =
					result;
			}
		}

		function value(name) {
			const fromApi =
				String(
					customer[name] || ''
				).trim();

			if (fromApi) {
				return fromApi;
			}

			const field =
				checkout.querySelector(
					`[name="${name}"]`
				);

			return (
				window.eilmoCfDom.isElement(field, 'INPUT') ||
				window.eilmoCfDom.isElement(field, 'SELECT') ||
				window.eilmoCfDom.isElement(field, 'TEXTAREA')
			)
				? String(field.value || '').trim()
				: '';
		}

		return {
			billing_first_name: value('billing_first_name'),
			billing_last_name: value('billing_last_name'),
			billing_phone: value('billing_phone'),
			billing_email: value('billing_email'),
			billing_address_1: value('billing_address_1'),
			billing_address_2: value('billing_address_2'),
			billing_city: value('billing_city'),
			billing_state: value('billing_state'),
			billing_postcode: value('billing_postcode'),
			billing_country: value('billing_country'),
		};
	}

	function getItems(checkout) {
		const api =
			getCheckoutApi();

		if (
			!api ||
			typeof api.getItems !==
				'function'
		) {
			return [];
		}

		const source =
			api.getItems(
				checkout
			);

		if (
			!Array.isArray(
				source
			)
		) {
			return [];
		}

		const items =
			[];

		source
			.slice(
				0,
				100
			)
			.forEach(
				function (item) {
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
						Math.min(
							999,
							toInteger(
								item.quantity
							)
						);

					if (
						productId <= 0 ||
						quantity <= 0
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

		return items;
	}

	function getComboIds(checkout) {
		const api =
			getCheckoutApi();

		if (
			!api ||
			typeof api.getComboData !==
				'function'
		) {
			return [];
		}

		const data =
			api.getComboData(
				checkout
			);

		return normalizeOfferIds(
			data &&
			Array.isArray(
				data.selected_ids
			)
				? data.selected_ids
				: []
		);
	}

	function getOrderBumpIds(checkout) {
		const api =
			getCheckoutApi();

		if (
			!api ||
			typeof api.getOrderBumpData !==
				'function'
		) {
			return [];
		}

		const data =
			api.getOrderBumpData(
				checkout
			);

		return normalizeOfferIds(
			data &&
			Array.isArray(
				data.selected_ids
			)
				? data.selected_ids
				: []
		);
	}

	function getDeliveryMethod(checkout) {
		const api =
			getCheckoutApi();

		if (
			!api ||
			typeof api.getDeliveryData !==
				'function'
		) {
			return '';
		}

		const data =
			api.getDeliveryData(
				checkout
			);

		if (
			!data ||
			typeof data !==
				'object'
		) {
			return '';
		}

		return String(
			data.method_id ||
				data.methodId ||
				''
		).trim();
	}

	function getPaymentMethod(checkout) {
		const radio =
			checkout.querySelector(
				`${SELECTORS.paymentRadio}:checked`
			);

		if (
			!(
				window.eilmoCfDom.isElement(radio, 'INPUT')
			)
		) {
			return '';
		}

		const method =
			radio.closest(
				SELECTORS.paymentMethod
			);

		return String(
			window.eilmoCfDom.isElement(method)
				? (
					method.dataset.methodKey ||
					method.dataset.gatewayId ||
					method.dataset.methodValue ||
					radio.value ||
					''
				)
				: (
					radio.value ||
					''
				)
		).trim();
	}

	function getTotals(checkout) {
		const summary =
			window.eilmoCfSummary;

		let values =
			{};

		if (
			summary &&
			typeof summary.getValues ===
				'function'
		) {
			const result =
				summary.getValues(
					checkout
				);

			if (
				result &&
				typeof result ===
					'object'
			) {
				values =
					result;
			}
		}

		const subtotal =
			toNumber(
				values.productTotal ??
					values.product_total ??
					0
			);

		let total =
			toNumber(
				values.grandTotal ??
					values.grand_total ??
					0
			);

		if (total <= 0) {
			total =
				toNumber(
					values.baseGrandTotal ??
						values.base_grand_total ??
						0
				);
		}

		return {
			subtotal:
				subtotal,

			total:
				total,
		};
	}

	function buildSnapshot(checkout) {
		const customer =
			getCustomerSnapshot(
				checkout
			);

		const totals =
			getTotals(
				checkout
			);

		return {
			billing_first_name:
				customer.billing_first_name,

			billing_last_name:
				customer.billing_last_name,

			billing_phone:
				customer.billing_phone,

			billing_email:
				customer.billing_email,

			billing_address_1:
				customer.billing_address_1,

			billing_address_2:
				customer.billing_address_2,

			billing_city:
				customer.billing_city,

			billing_state:
				customer.billing_state,

			billing_postcode:
				customer.billing_postcode,

			billing_country:
				customer.billing_country,

			delivery_method:
				getDeliveryMethod(
					checkout
				),

			payment_method:
				getPaymentMethod(
					checkout
				),

			subtotal:
				totals.subtotal,

			total:
				totals.total,

			cart_snapshot: {
				items:
					getItems(
						checkout
					),

				combo_offers:
					getComboIds(
						checkout
					),

				order_bumps:
					getOrderBumpIds(
						checkout
					),
			},
		};
	}

	function isMeaningful(snapshot) {
		if (
			!snapshot ||
			typeof snapshot !==
				'object'
		) {
			return false;
		}

		let phone = String(
			snapshot.billing_phone || ''
		).replace(/\D+/g, '');

		if (
			phone.length === 13 &&
			phone.indexOf('880') === 0
		) {
			phone = '0' + phone.slice(3);
		}

		if (
			phone.length === 10 &&
			phone.charAt(0) === '1'
		) {
			phone = '0' + phone;
		}

		return /^01[3-9][0-9]{8}$/.test(phone);
	}

	async function sendTrackingRequest(
		checkout,
		snapshot
	) {
		const config =
			getConfig();

		const token =
			getTrackingToken(
				checkout
			);

		if (
			!config.ajaxUrl ||
			!config.nonce ||
			!isValidToken(
				token
			)
		) {
			return;
		}

		const body =
			new FormData();

		body.append(
			'action',
			config.action
		);

		body.append(
			'nonce',
			config.nonce
		);

		body.append(
			'tracking_token',
			token
		);

		body.append(
			'checkout[billing_first_name]',
			snapshot.billing_first_name
		);

		body.append(
			'checkout[billing_last_name]',
			snapshot.billing_last_name
		);

		body.append(
			'checkout[billing_phone]',
			snapshot.billing_phone
		);

		body.append(
			'checkout[billing_email]',
			snapshot.billing_email
		);

		[
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
		].forEach(
			function (name) {
				body.append(
					`checkout[${name}]`,
					String(snapshot[name] || '')
				);
			}
		);

		body.append(
			'checkout[delivery_method]',
			snapshot.delivery_method
		);

		body.append(
			'checkout[payment_method]',
			snapshot.payment_method
		);

		body.append(
			'checkout[subtotal]',
			String(
				snapshot.subtotal
			)
		);

		body.append(
			'checkout[total]',
			String(
				snapshot.total
			)
		);

		body.append(
			'checkout[cart_snapshot]',
			JSON.stringify(
				snapshot.cart_snapshot
			)
		);

		await fetch(
			config.ajaxUrl,
			{
				method:
					'POST',

				body:
					body,

				credentials:
					'same-origin',
			}
		);
	}

	/**
	 * Tell the backend that this checkout successfully
	 * completed.
	 *
	 * The request is intentionally non-blocking because
	 * checkout.js may immediately redirect to the Thank
	 * You page.
	 *
	 * @param {string} token Tracking token.
	 *
	 * @return {void}
	 */
	function sendCompletionRequest(token) {
		const config =
			getConfig();

		if (
			!config.ajaxUrl ||
			!config.nonce ||
			!isValidToken(
				token
			)
		) {
			return;
		}

		const body =
			new FormData();

		body.append(
			'action',
			config.completeAction
		);

		body.append(
			'nonce',
			config.nonce
		);

		body.append(
			'tracking_token',
			token
		);

		/*
		 * sendBeacon is preferred because the checkout
		 * may redirect immediately after order success.
		 */
		if (
			navigator.sendBeacon &&
			typeof navigator.sendBeacon ===
				'function'
		) {
			try {
				const queued =
					navigator.sendBeacon(
						config.ajaxUrl,
						body
					);

				if (queued) {
					return;
				}
			} catch (error) {
				/*
				 * Fall through to fetch.
				 */
			}
		}

		fetch(
			config.ajaxUrl,
			{
				method:
					'POST',

				body:
					body,

				credentials:
					'same-origin',

				keepalive:
					true,
			}
		).catch(
			function () {
				/*
				 * Cleanup failure must never affect the
				 * successful customer order.
				 */
			}
		);
	}

	async function track(checkout) {
		if (
			!isEnabled() ||
			!(
				window.eilmoCfDom.isElement(checkout)
			) ||
			completed.has(
				checkout
			)
		) {
			return;
		}

		const snapshot =
			buildSnapshot(
				checkout
			);

		if (
			!isMeaningful(
				snapshot
			)
		) {
			return;
		}

		let state =
			requestStates.get(
				checkout
			);

		if (!state) {
			state = {
				inFlight:
					false,

				pending:
					false,
			};

			requestStates.set(
				checkout,
				state
			);
		}

		if (state.inFlight) {
			state.pending =
				true;

			return;
		}

		state.inFlight =
			true;

		state.pending =
			false;

		try {
			await sendTrackingRequest(
				checkout,
				snapshot
			);
		} catch (error) {
			/*
			 * Tracking must fail open.
			 */
		} finally {
			state.inFlight =
				false;

			if (
				state.pending &&
				!completed.has(
					checkout
				)
			) {
				state.pending =
					false;

				schedule(
					checkout,
					150
				);
			}
		}
	}

	function schedule(
		checkout,
		delay = null
	) {
		if (
			!isEnabled() ||
			!(
				window.eilmoCfDom.isElement(checkout)
			) ||
			completed.has(
				checkout
			)
		) {
			return;
		}

		const existing =
			timers.get(
				checkout
			);

		if (existing) {
			window.clearTimeout(
				existing
			);
		}

		const config =
			getConfig();

		const timer =
			window.setTimeout(
				function () {
					timers.delete(
						checkout
					);

					track(
						checkout
					);
				},
				delay === null
					? config.debounce
					: Math.max(
						0,
						Number(
							delay
						) || 0
					)
			);

		timers.set(
			checkout,
			timer
		);
	}

	function cancelScheduledTrack(checkout) {
		const timer =
			timers.get(
				checkout
			);

		if (!timer) {
			return;
		}

		window.clearTimeout(
			timer
		);

		timers.delete(
			checkout
		);
	}

	function isCustomerField(element) {
		return (
			(
				window.eilmoCfDom.isElement(element, 'INPUT') ||
				window.eilmoCfDom.isElement(element, 'SELECT') ||
				window.eilmoCfDom.isElement(element, 'TEXTAREA')
			) &&
			[
				'billing_first_name',
				'billing_last_name',
				'billing_phone',
				'billing_email',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_postcode',
				'billing_country',
			].includes(
				String(
					element.name ||
						''
				)
			)
		);
	}

	function handleInput(event) {
		if (
			!(
				window.eilmoCfDom.isElement(event.target)
			) ||
			!isCustomerField(
				event.target
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				event.target
			);

		if (checkout) {
			schedule(
				checkout
			);
		}
	}

	function handleChange(event) {
		if (
			!(
				window.eilmoCfDom.isElement(event.target)
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				event.target
			);

		if (!checkout) {
			return;
		}

		if (
			isCustomerField(
				event.target
			) ||
			event.target.matches(
				SELECTORS.quantityInput
			) ||
			event.target.matches(
				SELECTORS.paymentRadio
			)
		) {
			schedule(
				checkout
			);
		}
	}

	function handleModuleChange(event) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (checkout) {
			schedule(
				checkout
			);
		}
	}

	/**
	 * Handle successful Eilmo order.
	 *
	 * This does not touch WooCommerce.
	 *
	 * It only removes the current checkout's tracking
	 * record from the Abandoned Checkout table.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleOrderSuccess(event) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (!checkout) {
			return;
		}

		completed.add(
			checkout
		);

		cancelScheduledTrack(
			checkout
		);

		const token =
			readTrackingToken(
				checkout
			);

		if (
			isValidToken(
				token
			)
		) {
			sendCompletionRequest(
				token
			);
		}

		/*
		 * Never reuse this tracking token after a
		 * successfully completed checkout.
		 */
		clearTrackingToken(
			checkout
		);

		requestStates.delete(
			checkout
		);
	}

	function initializeCheckout(checkout) {
		if (
			initialized.has(
				checkout
			)
		) {
			return;
		}

		initialized.add(
			checkout
		);

		/*
		 * Do not immediately track page load.
		 */
	}

	function initialize(scope = document) {
		if (
			!isEnabled() ||
			!scope ||
			typeof scope.querySelectorAll !==
				'function'
		) {
			return;
		}

		if (
			window.eilmoCfDom.isElement(scope) &&
			scope.matches(
				SELECTORS.checkout
			)
		) {
			initializeCheckout(
				scope
			);
		}

		scope
			.querySelectorAll(
				SELECTORS.checkout
			)
			.forEach(
				function (checkout) {
					if (
						window.eilmoCfDom.isElement(checkout)
					) {
						initializeCheckout(
							checkout
						);
					}
				}
			);
	}

	function observe() {
		if (
			!isEnabled() ||
			!window.MutationObserver ||
			!document.documentElement
		) {
			return;
		}

		const observer =
			new MutationObserver(
				function (mutations) {
					mutations.forEach(
						function (mutation) {
							mutation.addedNodes
								.forEach(
									function (node) {
										if (
											window.eilmoCfDom.isElement(node)
										) {
											initialize(
												node
											);
										}
									}
								);
						}
					);
				}
			);

		observer.observe(
			document.documentElement,
			{
				childList:
					true,

				subtree:
					true,
			}
		);
	}

	[
		'eilmo:quantityChange',
		'eilmo:comboChange',
		'eilmo:orderBumpChange',
		'eilmo:deliveryChange',
		'eilmo:advancePaymentChange',
		'eilmo:paymentChange',
		'eilmo:couponChange',
		'eilmo:discountChange',
	].forEach(
		function (eventName) {
			document.addEventListener(
				eventName,
				handleModuleChange
			);
		}
	);

	document.addEventListener(
		'input',
		handleInput
	);

	document.addEventListener(
		'change',
		handleChange
	);

	document.addEventListener(
		'eilmo:orderSuccess',
		handleOrderSuccess
	);

	window.eilmoCfAbandonedCheckout = {
		track:
			track,

		schedule:
			schedule,

		buildSnapshot:
			buildSnapshot,

		getTrackingToken:
			getTrackingToken,

		clearTrackingToken:
			clearTrackingToken,

		initialize:
			initialize,
	};

	if (
		document.readyState ===
			'loading'
	) {
		document.addEventListener(
			'DOMContentLoaded',
			function () {
				initialize();
				observe();
			}
		);
	} else {
		initialize();
		observe();
	}
})();
