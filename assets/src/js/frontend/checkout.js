/**
 * Eilmo Checkout Flow - Checkout Submission.
 *
 * Handles:
 * - Final checkout validation.
 * - Product collection.
 * - Combo Offer selection and signed context.
 * - Order Bump selection.
 * - Customer information.
 * - Delivery method.
 * - Cash on Delivery / Advance / Full Payment Option.
 * - Coupon code.
 * - Manual payment methods.
 * - WooCommerce payment gateways.
 * - Gateway form values.
 * - Checkout idempotency token.
 * - Bot Protection security data.
 * - Order AJAX submission.
 * - Loading / error states.
 * - Payment redirects.
 *
 * Important:
 * Frontend totals are never authoritative.
 *
 * Products, Combo Offers, Order Bumps, prices,
 * discounts, coupon eligibility, delivery charge
 * and final totals are recalculated by the server.
 *
 * Order Bump frontend submission contains only
 * the selected configured Order Bump IDs.
 *
 * Product ID, variation ID, quantity and promotional
 * pricing for an Order Bump must be resolved again
 * from server-side Eilmo configuration.
 *
 * Bot Protection values are transport signals only.
 * Final validation is always performed server-side.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	/*
	 * Only one checkout submission controller may own the delegated click
	 * listener. This also protects sites where an optimizer accidentally emits
	 * both the production bundle and an individual fallback script.
	 */
	const runtimeModules = window.eilmoCfRuntimeModules && 'object' === typeof window.eilmoCfRuntimeModules
		? window.eilmoCfRuntimeModules
		: {};
	window.eilmoCfRuntimeModules = runtimeModules;
	if (true === runtimeModules.checkoutSubmit) {
		return;
	}
	runtimeModules.checkoutSubmit = true;

	/**
	 * Selectors.
	 */
	const SELECTORS = {
		checkout:
			'[data-eilmo-checkout]',

		orderSection:
			'[data-eilmo-order-submit-section]',

		submit:
			'[data-eilmo-order-submit]',

		submitText:
			'[data-eilmo-order-submit-text]',

		spinner:
			'[data-eilmo-order-spinner]',

		notice:
			'[data-eilmo-order-notice]',

		error:
			'[data-eilmo-order-error]',

		item:
			'[data-eilmo-product-item], [data-eilmo-item]',

		multipleProducts:
			'[data-eilmo-multiple-products]',

		quantityInput:
			'[data-eilmo-quantity-input]',

		comboSection:
			'[data-eilmo-combo-offers]',

		orderBumpSection:
			'[data-eilmo-order-bumps]',

		orderBumpOffer:
			'[data-eilmo-order-bump]',

		orderBumpInput:
			'[data-eilmo-order-bump-input]',

		delivery:
			'[data-eilmo-delivery]',

		advance:
			'[data-eilmo-advance-payment]',

		paymentType:
			'[data-eilmo-payment-type]',

		paymentSection:
			'[data-eilmo-payment-methods]',

		paymentMethod:
			'[data-eilmo-payment-method]',

		paymentRadio:
			'[data-eilmo-payment-radio]',

		gatewayFields:
			'[data-eilmo-gateway-fields]',

		transaction:
			'[data-eilmo-payment-transaction]',

		customerForm:
			'[data-eilmo-customer-form]',

		coupon:
			'[data-eilmo-coupon]',

		couponInput:
			'[data-eilmo-coupon-input]',

		securityStartToken:
			'[data-eilmo-security-start-token]',

		securityHoneypot:
			'[data-eilmo-security-honeypot]',
	};

	/**
	 * Determine whether this script runs inside the Elementor editor canvas.
	 * A normal frontend preview remains a real checkout; only the embedded
	 * editor iframe is interaction-only.
	 *
	 * @return {boolean} Editor canvas state.
	 */
	function isElementorEditorCanvas() {
		if (
			window.frameElement &&
			window.frameElement.id === 'elementor-preview-iframe'
		) {
			return true;
		}

		return Boolean(
			window.elementorFrontend &&
			typeof window.elementorFrontend.isEditMode === 'function' &&
			window.elementorFrontend.isEditMode()
		);
	}

	/**
	 * Checkouts currently submitting.
	 *
	 * Prevents duplicate requests from repeated
	 * Order Now clicks within the current page.
	 *
	 * Server-side idempotency provides the final
	 * duplicate-order protection.
	 *
	 * @type {WeakSet<HTMLElement>}
	 */
	const submitting =
		new WeakSet();

	/**
	 * Checkout token storage prefix.
	 *
	 * @type {string}
	 */
	const CHECKOUT_TOKEN_PREFIX =
		'eilmo_cf_checkout_token:';

	/**
	 * Convert value to positive integer.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toInteger(value) {
		const parsed =
			Number.parseInt(
				String(
					value ?? ''
				),
				10
			);

		if (
			!Number.isFinite(
				parsed
			)
		) {
			return 0;
		}

		return Math.max(
			0,
			parsed
		);
	}

	/**
	 * Normalize reusable offer IDs.
	 *
	 * IDs follow WordPress sanitize_key-compatible
	 * frontend normalization.
	 *
	 * @param {*} values Values.
	 *
	 * @return {Array<string>}
	 */
	function normalizeOfferIds(
		values
	) {
		if (
			!Array.isArray(
				values
			)
		) {
			return [];
		}

		const normalized =
			[];

		values.forEach(
			function (value) {
				const id =
					String(
						value ||
							''
					)
						.trim()
						.toLowerCase()
						.replace(
							/[^a-z0-9_-]/g,
							''
						);

				if (
					!id ||
					normalized.includes(
						id
					)
				) {
					return;
				}

				normalized.push(
					id
				);
			}
		);

		return normalized;
	}

	/**
	 * Get checkout wrapper.
	 *
	 * @param {Element|null} element Element.
	 *
	 * @return {HTMLElement|null}
	 */
	function getCheckout(element) {
		if (
			!(
				window.eilmoCfDom.isElement(element)
			)
		) {
			return null;
		}

		if (
			element.matches(
				SELECTORS.checkout
			)
		) {
			return window.eilmoCfDom.isElement(element)
					? element
					: null;
		}

		const checkout =
			element.closest(
				SELECTORS.checkout
			);

		return window.eilmoCfDom.isElement(checkout)
				? checkout
				: null;
	}

	/**
	 * Generate secure checkout token.
	 *
	 * Modern browsers use crypto.randomUUID().
	 *
	 * A crypto.getRandomValues() fallback is used
	 * when randomUUID() is unavailable.
	 *
	 * @return {string}
	 */
	function generateCheckoutToken() {
		if (
			window.crypto &&
			typeof window.crypto.randomUUID ===
				'function'
		) {
			return window.crypto
				.randomUUID();
		}

		if (
			window.crypto &&
			typeof window.crypto.getRandomValues ===
				'function'
		) {
			const values =
				new Uint32Array(
					4
				);

			window.crypto
				.getRandomValues(
					values
				);

			const random =
				Array.from(
					values
				)
					.map(
						function (value) {
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
					.join('');

			return (
				'eilmo_' +
				random
			).slice(
				0,
				96
			);
		}

		/*
		 * Last-resort compatibility fallback.
		 *
		 * The server still validates and locks the
		 * token. This value is an idempotency key,
		 * not an authentication secret.
		 */
		return (
			'eilmo_' +
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
				) +
			'_' +
			Math.random()
				.toString(
					36
				)
				.slice(
					2
				)
		).slice(
			0,
			96
		);
	}

	/**
	 * Get sessionStorage key for checkout instance.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getCheckoutTokenStorageKey(
		checkout
	) {
		const instanceId =
			checkout.id ||
			'default';

		return (
			CHECKOUT_TOKEN_PREFIX +
			window.location.pathname +
			':' +
			instanceId
		);
	}

	/**
	 * Get existing checkout token.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function readCheckoutToken(
		checkout
	) {
		const datasetToken =
			String(
				checkout.dataset
					.checkoutToken ||
				''
			).trim();

		if (datasetToken) {
			return datasetToken;
		}

		const storageKey =
			getCheckoutTokenStorageKey(
				checkout
			);

		try {
			return String(
				window.sessionStorage
					.getItem(
						storageKey
					) ||
				''
			).trim();
		} catch (error) {
			return '';
		}
	}

	/**
	 * Get or create checkout token.
	 *
	 * The same token is retained across:
	 *
	 * - Failed AJAX request.
	 * - Network retry.
	 * - Accidental repeated submission.
	 * - Page refresh within the same browser tab.
	 *
	 * It is cleared only after the server confirms
	 * that the checkout has produced an order.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getCheckoutToken(
		checkout
	) {
		let token =
			readCheckoutToken(
				checkout
			);

		if (!token) {
			token =
				generateCheckoutToken();
		}

		checkout.dataset.checkoutToken =
			token;

		const storageKey =
			getCheckoutTokenStorageKey(
				checkout
			);

		try {
			window.sessionStorage
				.setItem(
					storageKey,
					token
				);
		} catch (error) {
			/*
			 * sessionStorage can be unavailable in
			 * restricted browser environments.
			 *
			 * data-checkout-token continues protecting
			 * the current page lifecycle.
			 */
		}

		return token;
	}

	/**
	 * Clear completed checkout token.
	 *
	 * Once an order has been successfully created,
	 * the next intentional order must receive a new
	 * checkout token.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function clearCheckoutToken(
		checkout
	) {
		const storageKey =
			getCheckoutTokenStorageKey(
				checkout
			);

		try {
			window.sessionStorage
				.removeItem(
					storageKey
				);
		} catch (error) {
			/*
			 * No additional action required.
			 */
		}

		delete checkout.dataset
			.checkoutToken;
	}

	/**
	 * Get localized frontend text.
	 *
	 * @param {string} key      Key.
	 * @param {string} fallback Fallback.
	 *
	 * @return {string}
	 */
	function getText(
		key,
		fallback
	) {
		const config =
			window.eilmoCf || {};

		const i18n =
			config.i18n &&
			typeof config.i18n ===
				'object'
				? config.i18n
				: {};

		if (
			typeof i18n[key] ===
				'string' &&
			i18n[key] !== ''
		) {
			return i18n[key];
		}

		return fallback;
	}

	/**
	 * Scroll element into view.
	 *
	 * @param {Element|null} element Element.
	 *
	 * @return {void}
	 */
	function scrollToElement(element) {
		if (
			!(
				window.eilmoCfDom.isElement(element)
			)
		) {
			return;
		}

		try {
			element.scrollIntoView(
				{
					behavior:
						'smooth',

					block:
						'center',
				}
			);
		} catch (error) {
			element.scrollIntoView();
		}
	}

	/**
	 * Clear order messages.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function clearMessages(checkout) {
		checkout.querySelectorAll(SELECTORS.error).forEach(function (error) {
			if (window.eilmoCfDom.isElement(error)) {
				error.textContent = '';
				error.hidden = true;
			}
		});

		checkout.querySelectorAll(SELECTORS.notice).forEach(function (notice) {
			if (window.eilmoCfDom.isElement(notice)) {
				notice.textContent = '';
				notice.hidden = true;
			}
		});
	}

	/**
	 * Show checkout error.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      message  Message.
	 *
	 * @return {void}
	 */
	function showError(
		checkout,
		message
	) {
		const errors = Array.from(
			checkout.querySelectorAll(SELECTORS.error)
		);

		if (!errors.length) {
			return;
		}

		const errorMessage = String(
			message || getText('error', 'Something went wrong. Please try again.')
		);

		errors.forEach(function (error) {
			if (window.eilmoCfDom.isElement(error)) {
				error.textContent = window.eilmoCfLanguage.copy(checkout, errorMessage);
				error.hidden = false;
			}
		});

		checkout.querySelectorAll(SELECTORS.notice).forEach(function (notice) {
			if (window.eilmoCfDom.isElement(notice)) {
				notice.textContent = '';
				notice.hidden = true;
			}
		});

		const visibleError = errors.find(function (error) {
			return window.eilmoCfDom.isElement(error) && error.offsetParent !== null;
		});

		if (window.eilmoCfDom.isElement(visibleError)) {
			scrollToElement(visibleError);
		}
	}

	/**
	 * Show success/info notice.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      message  Message.
	 *
	 * @return {void}
	 */
	function showNotice(
		checkout,
		message
	) {
		const noticeMessage = String(message || '');

		checkout.querySelectorAll(SELECTORS.notice).forEach(function (notice) {
			if (window.eilmoCfDom.isElement(notice)) {
				notice.textContent = window.eilmoCfLanguage.copy(checkout, noticeMessage);
				notice.hidden = !noticeMessage;
			}
		});

		checkout.querySelectorAll(SELECTORS.error).forEach(function (error) {
			if (window.eilmoCfDom.isElement(error)) {
				error.textContent = '';
				error.hidden = true;
			}
		});
	}

	/**
	 * Set Order Now loading state.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {boolean}     loading  Loading.
	 *
	 * @return {void}
	 */
	function setLoading(
		checkout,
		loading
	) {
		checkout.querySelectorAll(SELECTORS.submit).forEach(function (button) {
			if (!(window.eilmoCfDom.isElement(button, 'BUTTON'))) {
				return;
			}

			const text = button.querySelector(SELECTORS.submitText);
			const spinner = button.querySelector(SELECTORS.spinner);
			const defaultLabel = button.dataset.label || 'Order Now';
			const processingLabel =
				button.dataset.processingLabel || getText('processing', 'Processing...');

			button.disabled = loading || checkout.dataset.emptySelection === 'yes' || checkout.dataset.fraudBlocked === 'yes';
			button.setAttribute('aria-busy', loading ? 'true' : 'false');
			button.classList.toggle('is-loading', loading);

			const section = button.closest(SELECTORS.orderSection);
			if (window.eilmoCfDom.isElement(section)) {
				section.classList.toggle('is-loading', loading);
			}

			if (window.eilmoCfDom.isElement(text)) {
				text.textContent = loading ? processingLabel : defaultLabel;
			}

			if (window.eilmoCfDom.isElement(spinner)) {
				spinner.hidden = !loading;
			}
		});
	}

	/**
	 * Get positive integer from dataset.
	 *
	 * @param {HTMLElement|null} element Element.
	 * @param {Array<string>}    keys    Keys.
	 *
	 * @return {number}
	 */
	function getDatasetInteger(
		element,
		keys
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
			index < keys.length;
			index += 1
		) {
			const value =
				toInteger(
					element.dataset[
						keys[index]
					] || 0
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
	 * Get integer from child element.
	 *
	 * @param {HTMLElement} item     Product item.
	 * @param {string}      selector Selector.
	 *
	 * @return {number}
	 */
	function getChildInteger(
		item,
		selector
	) {
		const field =
			item.querySelector(
				selector
			);

		if (
			!(
				window.eilmoCfDom.isElement(field)
			)
		) {
			return 0;
		}

		if (
			window.eilmoCfDom.isElement(field, 'INPUT') ||
			window.eilmoCfDom.isElement(field, 'SELECT')
		) {
			return toInteger(
				field.value
			);
		}

		return toInteger(
			field.getAttribute(
				'data-value'
			) || ''
		);
	}

	/**
	 * Get variation ID.
	 *
	 * @param {HTMLElement}       item  Item.
	 * @param {HTMLInputElement} input Quantity input.
	 *
	 * @return {number}
	 */
	function getVariationId(
		item,
		input
	) {
		const itemVariationId =
			getDatasetInteger(
				item,
				[
					'variationId',
				]
			);

		if (
			itemVariationId > 0
		) {
			return itemVariationId;
		}

		const inputVariationId =
			getDatasetInteger(
				input,
				[
					'variationId',
				]
			);

		if (
			inputVariationId > 0
		) {
			return inputVariationId;
		}

		return getChildInteger(
			item,
			[
				'[data-eilmo-variation-id]',
				'input[name*="[variation_id]"]',
				'input[name="variation_id"]',
			].join(',')
		);
	}

	/**
	 * Get product ID.
	 *
	 * @param {HTMLElement}       item  Item.
	 * @param {HTMLInputElement} input Quantity input.
	 *
	 * @return {number}
	 */
	function getProductId(
		item,
		input
	) {
		const itemProductId =
			getDatasetInteger(
				item,
				[
					'productId',
					'parentProductId',
					'parentId',
				]
			);

		if (
			itemProductId > 0
		) {
			return itemProductId;
		}

		const inputProductId =
			getDatasetInteger(
				input,
				[
					'productId',
					'parentProductId',
					'parentId',
				]
			);

		if (
			inputProductId > 0
		) {
			return inputProductId;
		}

		const childProductId =
			getChildInteger(
				item,
				[
					'[data-eilmo-product-id]',
					'input[name*="[product_id]"]',
					'input[name="product_id"]',
				].join(',')
			);

		if (
			childProductId > 0
		) {
			return childProductId;
		}

		return getVariationId(
			item,
			input
		);
	}

	/**
	 * Collect selected products.
	 *
	 * Prices are deliberately not submitted.
	 *
	 * Normal products remain independent from
	 * Combo and Order Bump purchase contexts.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Array<Object>}
	 */
	function getItems(checkout) {
		const items =
			new Map();

		checkout
			.querySelectorAll(
				SELECTORS.item
			)
			.forEach(
				function (item) {
					if (
						!(
							window.eilmoCfDom.isElement(item)
						)
					) {
						return;
					}

					/*
					 * Combo Offers and Order Bumps are independent
					 * purchase contexts.
					 *
					 * Never submit nested Combo / Order Bump markup
					 * as a normal checkout product even if a future
					 * renderer happens to use data-eilmo-item.
					 */
					if (
						item.closest(
							SELECTORS.comboSection
						) ||
						item.closest(
							SELECTORS.orderBumpSection
						)
					) {
						return;
					}

					const input =
						item.querySelector(
							SELECTORS
								.quantityInput
						);

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

					const productId =
						getProductId(
							item,
							input
						);

					if (
						productId <= 0
					) {
						return;
					}

					const variationId =
						getVariationId(
							item,
							input
						);

					/*
					 * Multiple Products may expose compatibility
					 * adapters for the same exact order item.
					 * Transport each product / variation only once;
					 * never add duplicate DOM quantities together.
					 */
					const key =
						variationId > 0
							? 'variation:' + variationId
							: 'product:' + productId;

					const existing =
						items.get(
							key
						);

					items.set(
						key,
						{
							product_id:
								productId,

							variation_id:
								variationId,

							quantity:
								existing
									? Math.max(
										existing.quantity,
										quantity
									)
									: quantity,
						}
					);
				}
			);

		return Array.from(
			items.values()
		);
	}

	/**
	 * Collect selected Combo Offer data.
	 *
	 * Only selected Combo IDs and signed per-checkout
	 * context are submitted.
	 *
	 * Frontend Combo prices are never authoritative.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getComboData(
		checkout
	) {
		const emptyData = {
			selected_ids:
				[],

			selection_mode:
				'multiple',

			context: {
				allowed_ids:
					[],

				instance_id:
					'',

				selection_mode:
					'multiple',

				signature:
					'',
			},
		};

		const api =
			window.eilmoCfComboOffers;

		if (
			!api ||
			typeof api.getData !==
				'function'
		) {
			return emptyData;
		}

		const data =
			api.getData(
				checkout
			);

		if (
			!data ||
			typeof data !==
				'object' ||
			Array.isArray(
				data
			)
		) {
			return emptyData;
		}

		const selectedIds =
			normalizeOfferIds(
				data.selected_ids
			);

		const rawContext =
			data.context &&
			typeof data.context ===
				'object' &&
			!Array.isArray(
				data.context
			)
				? data.context
				: {};

		const allowedIds =
			normalizeOfferIds(
				rawContext.allowed_ids
			);

		const signedSelectionMode =
			rawContext.selection_mode ===
				'single'
					? 'single'
					: 'multiple';

		const selectionMode =
			data.selection_mode ===
				'single'
					? 'single'
					: 'multiple';

		return {
			selected_ids:
				selectedIds,

			selection_mode:
				selectionMode,

			context: {
				allowed_ids:
					allowedIds,

				instance_id:
					String(
						rawContext.instance_id ||
							''
					).trim(),

				selection_mode:
					signedSelectionMode,

				signature:
					String(
						rawContext.signature ||
							''
					).trim(),
			},
		};
	}

	/**
	 * Collect selected Order Bump data.
	 *
	 * IMPORTANT:
	 *
	 * Only configured Order Bump IDs are submitted.
	 *
	 * The following frontend values are deliberately
	 * NOT included in the payload:
	 *
	 * - Product ID.
	 * - Variation ID.
	 * - Quantity.
	 * - Regular price.
	 * - Offer price.
	 * - Discount.
	 * - Discount compatibility settings.
	 *
	 * Those values must be loaded again from the
	 * server-side Order Bump configuration.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getSpecialDiscountContext(
		checkout
	) {
		let parsed = {};
		const rawContext =
			checkout.getAttribute(
				'data-eilmo-special-discount-context'
			) || '';

		if (rawContext) {
			try {
				const candidate = JSON.parse(rawContext);
				if (candidate && 'object' === typeof candidate && !Array.isArray(candidate)) {
					parsed = candidate;
				}
			} catch (error) {
				parsed = {};
			}
		}

		const scope = ['all', 'selected', 'none'].includes(String(parsed.scope || ''))
			? String(parsed.scope)
			: 'none';

		return {
			allowed_ids:
				normalizeOfferIds(parsed.allowed_ids),

			instance_id:
				String(parsed.instance_id || '').trim(),

			scope:
				scope,

			signature:
				String(parsed.signature || '').trim(),
		};
	}

	function getOrderBumpData(
		checkout
	) {
		const context = getSpecialDiscountContext(checkout);
		const section =
			checkout.querySelector(
				SELECTORS.orderBumpSection
			);

		if (
			!(
				window.eilmoCfDom.isElement(section)
			)
		) {
			return {
				selected_ids:
					[],

				selection_mode:
					'multiple',

				context:
					context,
			};
		}

		const selectionMode =
			section.dataset
				.selectionMode ===
					'single'
				? 'single'
				: 'multiple';

		const api =
			window.eilmoCfOrderBump;

		if (
			api &&
			typeof api.getSelectedIds ===
				'function'
		) {
			return {
				selected_ids:
					normalizeOfferIds(
						api.getSelectedIds(
							checkout
						)
					),

				selection_mode:
					selectionMode,

				context:
					context,
			};
		}

		/*
		 * DOM fallback.
		 *
		 * The server still resolves every selected
		 * Order Bump from saved settings.
		 */
		const selectedIds =
			[];

		section
			.querySelectorAll(
				SELECTORS.orderBumpInput +
					':checked'
			)
			.forEach(
				function (input) {
					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						) ||
						input.disabled
					) {
						return;
					}

					selectedIds.push(
						input.value
					);
				}
			);

		return {
			selected_ids:
				normalizeOfferIds(
					selectedIds
				),

			selection_mode:
				selectionMode,

			context:
				context,
		};
	}

	/**
	 * Collect customer information.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getCustomerData(
		checkout
	) {
		const api =
			window.eilmoCfCustomer;

		if (
			api &&
			typeof api.getData ===
				'function'
		) {
			const data =
				api.getData(
					checkout
				);

			if (
				data &&
				typeof data ===
					'object'
			) {
				return data;
			}
		}

		const fieldNames = [
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_phone',
			'billing_email',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
			'order_comments',
		];

		const customer =
			{};

		fieldNames.forEach(
			function (name) {
				const field =
					checkout.querySelector(
						`[name="${name}"]`
					);

				if (
					window.eilmoCfDom.isElement(field, 'INPUT') ||
					window.eilmoCfDom.isElement(field, 'SELECT') ||
					window.eilmoCfDom.isElement(field, 'TEXTAREA')
				) {
					customer[name] =
						field.value.trim();
				}
			}
		);

		return customer;
	}

	/**
	 * Get selected delivery method.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getDeliveryData(
		checkout
	) {
		const api =
			window.eilmoCfDelivery;

		if (
			api &&
			typeof api.getSelected ===
				'function'
		) {
			const selected =
				api.getSelected(
					checkout
				);

			if (
				selected &&
				typeof selected ===
					'object'
			) {
				return {
					method_id:
						String(
							selected.methodId ||
							selected.method_id ||
							''
						),
				};
			}
		}

		return {
			method_id:
				String(
					checkout.dataset
						.deliveryMethod ||
					''
				),
		};
	}

	/**
	 * Normalize Payment Option value.
	 *
	 * @param {*} value Value.
	 *
	 * @return {string}
	 */
	function normalizePaymentType(
		value
	) {
		const paymentType =
			String(
				value || ''
			)
				.trim()
				.toLowerCase();

		return [
			'cash_on_delivery',
			'advance',
			'full',
		].includes(
			paymentType
		)
			? paymentType
			: '';
	}

	/**
	 * Get selected top-level Payment Option.
	 *
	 * Priority:
	 *
	 * 1. Advance Payment runtime result.
	 * 2. Checked Payment Option input.
	 * 3. Checkout dataset.
	 * 4. Full Payment compatibility fallback.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getPaymentType(
		checkout
	) {
		const api =
			window
				.eilmoCfAdvancePayment;

		if (
			api &&
			typeof api.getResult ===
				'function'
		) {
			const result =
				api.getResult(
					checkout
				);

			if (
				result &&
				typeof result ===
					'object'
			) {
				const runtimeType =
					normalizePaymentType(
						result.paymentType ||
						result.payment_type ||
						''
					);

				if (runtimeType) {
					return runtimeType;
				}
			}
		}

		const checked =
			checkout.querySelector(
				`${SELECTORS.paymentType}:checked`
			);

		if (
			window.eilmoCfDom.isElement(checked, 'INPUT')
		) {
			const checkedType =
				normalizePaymentType(
					checked.value
				);

			if (checkedType) {
				return checkedType;
			}
		}

		const datasetType =
			normalizePaymentType(
				checkout.dataset
					.paymentType ||
					''
			);

		return datasetType ||
			'full';
	}

	/**
	 * Determine whether Cash on Delivery is selected.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function isCashOnDelivery(
		checkout
	) {
		return (
			'cash_on_delivery' ===
				getPaymentType(
					checkout
				)
		);
	}

	/**
	 * Get Cash on Delivery / Advance / Full Payment
	 * Option data.
	 *
	 * Only the selected Payment Option identifier is
	 * submitted. Pay Now and Remaining Due are always
	 * recalculated authoritatively by PHP.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getAdvanceData(
		checkout
	) {
		return {
			payment_type:
				getPaymentType(
					checkout
				),
		};
	}

	/** Get the signed, server-issued live fraud decision. */
	function getFraudData(
		checkout
	) {
		const api = window.eilmoCfFraudCheck;
		return api && 'function' === typeof api.getData
			? api.getData(checkout)
			: {};
	}

	/**
	 * Get selected payment card.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {HTMLElement|null}
	 */
	function getSelectedPaymentMethod(
		checkout
	) {
		/*
		 * Cash on Delivery is a top-level Payment Option.
		 * It deliberately has no selectable Payment Method
		 * card underneath it.
		 */
		if (
			isCashOnDelivery(
				checkout
			)
		) {
			return null;
		}

		const api =
			window.eilmoCfPayment;

		if (
			api &&
			typeof api
				.getSelectedMethod ===
					'function'
		) {
			const selected =
				api.getSelectedMethod(
					checkout
				);

			if (
				window.eilmoCfDom.isElement(selected)
			) {
				return selected;
			}
		}

		const radio =
			checkout.querySelector(
				`${SELECTORS.paymentRadio}:checked`
			);

		if (
			!(
				window.eilmoCfDom.isElement(radio, 'INPUT')
			)
		) {
			return null;
		}

		const method =
			radio.closest(
				SELECTORS.paymentMethod
			);

		return window.eilmoCfDom.isElement(method)
				? method
				: null;
	}

	/**
	 * Parse nested HTML field name.
	 *
	 * Example:
	 *
	 * wc_data[token]
	 *
	 * @param {string} name Field name.
	 *
	 * @return {Array<string>}
	 */
	function parseFieldName(name) {
		const matches =
			String(
				name || ''
			).match(
				/[^[\]]+/g
			);

		return Array.isArray(
			matches
		)
			? matches
			: [];
	}

	/**
	 * Assign nested object value.
	 *
	 * @param {Object} object Object.
	 * @param {string} name   Field name.
	 * @param {*}      value  Value.
	 *
	 * @return {void}
	 */
	function assignFieldValue(
		object,
		name,
		value
	) {
		const keys =
			parseFieldName(
				name
			);

		if (
			keys.length === 0
		) {
			return;
		}

		let target =
			object;

		for (
			let index = 0;
			index < keys.length;
			index += 1
		) {
			const key =
				keys[index];

			const isLast =
				index ===
					keys.length - 1;

			if (isLast) {
				if (
					Object.prototype
						.hasOwnProperty.call(
							target,
							key
						)
				) {
					if (
						!Array.isArray(
							target[key]
						)
					) {
						target[key] = [
							target[key],
						];
					}

					target[key].push(
						value
					);
				} else {
					target[key] =
						value;
				}

				return;
			}

			if (
				!target[key] ||
				typeof target[key] !==
					'object' ||
				Array.isArray(
					target[key]
				)
			) {
				target[key] =
					{};
			}

			target =
				target[key];
		}
	}

	/**
	 * Collect selected WooCommerce gateway fields.
	 *
	 * Card numbers inside secure Stripe iframes are
	 * intentionally never accessed here.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getGatewayData(
		checkout
	) {
		if (
			isCashOnDelivery(
				checkout
			)
		) {
			return {};
		}

		const method =
			getSelectedPaymentMethod(
				checkout
			);

		if (
			!(
				window.eilmoCfDom.isElement(method)
			)
		) {
			return {};
		}

		if (
			method.dataset
				.paymentSource !==
					'woocommerce_gateway'
		) {
			return {};
		}

		const result =
			{};

		method
			.querySelectorAll(
				'input[name], select[name], textarea[name]'
			)
			.forEach(
				function (field) {
					if (
						!(
							window.eilmoCfDom.isElement(field, 'INPUT') ||
							window.eilmoCfDom.isElement(field, 'SELECT') ||
							window.eilmoCfDom.isElement(field, 'TEXTAREA')
						)
					) {
						return;
					}

					if (
						field.disabled
					) {
						return;
					}

					const name =
						String(
							field.name || ''
						).trim();

					if (!name) {
						return;
					}

					/*
					 * Eilmo/WP transport fields must
					 * never be forwarded to a gateway.
					 */
					if (
						[
							'action',
							'nonce',
							'payload',
							'payment_method',
							'woocommerce_checkout_place_order',
						].includes(
							name
						)
					) {
						return;
					}

					if (
						name.indexOf(
							'eilmo_payment_transaction_'
						) ===
							0
					) {
						return;
					}

					if (
						window.eilmoCfDom.isElement(field, 'INPUT')
					) {
						if (
							(
								field.type ===
									'checkbox' ||
								field.type ===
									'radio'
							) &&
							!field.checked
						) {
							return;
						}

						if (
							field.type ===
								'file'
						) {
							return;
						}
					}

					if (
						window.eilmoCfDom.isElement(field, 'SELECT') &&
						field.multiple
					) {
						Array.from(
							field.selectedOptions
						).forEach(
							function (option) {
								assignFieldValue(
									result,
									name,
									option.value
								);
							}
						);

						return;
					}

					assignFieldValue(
						result,
						name,
						field.value
					);
				}
			);

		return result;
	}

	/**
	 * Get payment selection.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getPaymentData(
		checkout
	) {
		/*
		 * COD has no Payment Method UI, but keep the
		 * existing payment payload shape stable for
		 * checkout integrations. The server does not trust
		 * these values and rebuilds the canonical COD method
		 * from payment_type.
		 */
		if (
			isCashOnDelivery(
				checkout
			)
		) {
			return {
				method:
					'eilmo_cash_on_delivery',

				method_key:
					'cash_on_delivery',

				source:
					'eilmo_manual',

				gateway_id:
					'',

				native_payment_value:
					'cod',

				transaction_id:
					'',

				proof_token:
					'',

				gateway_data:
					{},
			};
		}

		const api =
			window.eilmoCfPayment;

		let data =
			null;

		if (
			api &&
			typeof api.getData ===
				'function'
		) {
			data =
				api.getData(
					checkout
				);
		}

		if (
			!data ||
			typeof data !==
				'object'
		) {
			const method =
				getSelectedPaymentMethod(
					checkout
				);

			if (!method) {
				return {
					method:
						'',

					method_key:
						'',

					source:
						'',

					gateway_id:
						'',

					native_payment_value:
						'',

					transaction_id:
						'',

					proof_token:
						'',

					gateway_data:
						{},
				};
			}

			const radio =
				method.querySelector(
					SELECTORS.paymentRadio
				);

			const transaction =
				method.querySelector(
					SELECTORS.transaction
				);

			const proofToken =
				method.querySelector(
					'[data-eilmo-payment-proof-token]'
				);

			data = {
				method:
					method.dataset
						.methodValue ||
					'',

				methodKey:
					method.dataset
						.methodKey ||
					'',

				source:
					method.dataset
						.paymentSource ||
					'',

				gatewayId:
					method.dataset
						.gatewayId ||
					'',

				nativePaymentValue:
					window.eilmoCfDom.isElement(radio, 'INPUT')
							? radio.value
							: '',

				transactionId:
					window.eilmoCfDom.isElement(transaction, 'INPUT')
							? transaction.value
							: '',

				proofToken:
					window.eilmoCfDom.isElement(proofToken, 'INPUT') &&
						proofToken.value
							? proofToken.value
							: (
								checkout.dataset
									.paymentProofToken ||
								''
							),
			};
		}

		return {
			method:
				String(
					data.method ||
					''
				),

			method_key:
				String(
					data.methodKey ||
					''
				),

			source:
				String(
					data.source ||
					''
				),

			gateway_id:
				String(
					data.gatewayId ||
					''
				),

			native_payment_value:
				String(
					data.nativePaymentValue ||
					data.gatewayId ||
					''
				),

			transaction_id:
				String(
					data.transactionId ||
					''
				).trim(),

			proof_token:
				String(
					data.proofToken ||
					checkout.dataset
						.paymentProofToken ||
					''
				).trim(),

			gateway_data:
				getGatewayData(
					checkout
				),
		};
	}

	/**
	 * Get applied coupon.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getCouponCode(
		checkout
	) {
		const api =
			window.eilmoCfCoupon;

		if (
			api &&
			typeof api.getResult ===
				'function'
		) {
			const result =
				api.getResult(
					checkout
				);

			if (
				result &&
				typeof result ===
					'object'
			) {
				if (
					result.applied ===
						true &&
					typeof result.code ===
						'string'
				) {
					return result.code
						.trim();
				}

				if (
					result.source ===
						'woocommerce'
				) {
					const input =
						checkout.querySelector(
							SELECTORS.couponInput
						);

					if (
						window.eilmoCfDom.isElement(input, 'INPUT')
					) {
						return input.value
							.trim();
					}
				}
			}
		}

		if (
			checkout.dataset
				.couponApplied ===
					'yes'
		) {
			return String(
				checkout.dataset
					.couponCode ||
				''
			).trim();
		}

		return '';
	}

	/**
	 * Collect Bot Protection security data.
	 *
	 * start_token is generated and signed by PHP.
	 *
	 * honeypot must remain empty during legitimate
	 * checkout usage.
	 *
	 * These values are never trusted by the browser.
	 * Final verification happens server-side.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getSecurityData(
		checkout
	) {
		const startTokenField =
			checkout.querySelector(
				SELECTORS
					.securityStartToken
			);

		const honeypotField =
			checkout.querySelector(
				SELECTORS
					.securityHoneypot
			);

		const startToken =
			window.eilmoCfDom.isElement(startTokenField, 'INPUT')
					? startTokenField.value
						.trim()
					: '';

		const honeypot =
			window.eilmoCfDom.isElement(honeypotField, 'INPUT')
					? honeypotField.value
						.trim()
					: '';

		return {
			start_token:
				startToken,

			honeypot:
				honeypot,
		};
	}

	/**
	 * Determine whether coupon request is active.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function isCouponLoading(
		checkout
	) {
		const coupon =
			checkout.querySelector(
				SELECTORS.coupon
			);

		return (
			window.eilmoCfDom.isElement(coupon) &&
			coupon.dataset.loading ===
				'yes'
		);
	}

	/**
	 * Validate products.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function validateProducts(
		checkout
	) {
		const multipleProducts =
			checkout.querySelector(
				SELECTORS.multipleProducts
			);

		const multipleProductsApi =
			window.eilmoCfMultipleProducts;

		/*
		 * Commit the latest Multiple Products controls
		 * to their order-item adapters before reading
		 * quantities for validation.
		 */
		if (
			window.eilmoCfDom.isElement(multipleProducts) &&
			multipleProductsApi &&
			typeof multipleProductsApi.refresh ===
				'function'
		) {
			multipleProductsApi.refresh(
				checkout
			);
		}

		if (
			getItems(
				checkout
			).length > 0
		) {
			return true;
		}

		showError(
			checkout,
			getText(
				'selectProduct',
				'Please select at least one product.'
			)
		);

		scrollToElement(
			checkout.querySelector(
				'.eilmo-cf-checkout__products'
			)
		);

		return false;
	}

	/**
	 * Validate Combo Offers.
	 *
	 * Combo selection is optional, but any selected
	 * Combo must belong to the signed allowed list
	 * for this checkout instance.
	 *
	 * Final validation is still repeated server-side.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function validateComboOffers(
		checkout
	) {
		const section =
			checkout.querySelector(
				SELECTORS.comboSection
			);

		if (
			!(
				window.eilmoCfDom.isElement(section)
			)
		) {
			return true;
		}

		const api =
			window.eilmoCfComboOffers;

		if (
			!api ||
			typeof api.validateCheckout !==
				'function'
		) {
			return true;
		}

		const valid =
			api.validateCheckout(
				checkout,
				false
			);

		if (!valid) {
			showError(
				checkout,
				getText(
					'comboInvalid',
					'One of the selected Combo Offers is no longer available. Please select again.'
				)
			);

			scrollToElement(
				section
			);
		}

		return valid;
	}

	/**
	 * Validate Order Bumps.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function validateOrderBumps(
		checkout
	) {
		const section =
			checkout.querySelector(
				SELECTORS.orderBumpSection
			);

		if (
			!(
				window.eilmoCfDom.isElement(section)
			)
		) {
			return true;
		}

		const data =
			getOrderBumpData(
				checkout
			);

		const selectedIds =
			Array.isArray(
				data.selected_ids
			)
				? data.selected_ids
				: [];

		if (
			selectedIds.length ===
				0
		) {
			return true;
		}

		if (
			'single' ===
				data.selection_mode &&
			selectedIds.length > 1
		) {
			showError(
				checkout,
				getText(
					'orderBumpSingle',
					'Please select only one Special Discount.'
				)
			);

			scrollToElement(
				section
			);

			return false;
		}

		let valid =
			true;

		section
			.querySelectorAll(
				SELECTORS.orderBumpInput +
					':checked'
			)
			.forEach(
				function (input) {
					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						)
					) {
						valid =
							false;

						return;
					}

					const offer =
						input.closest(
							SELECTORS.orderBumpOffer
						);

					if (
						window.eilmoCfDom.isElement(offer) &&
						'yes' === String(offer.dataset.expired || 'no')
					) {
						input.checked = false;
						return;
					}

					if (
						input.disabled
					) {
						const isEligibleAutoApply =
							window.eilmoCfDom.isElement(offer) &&
							offer.dataset.applyBehavior ===
								'auto_apply' &&
							offer.dataset.eligible ===
								'yes';

						if (!isEligibleAutoApply) {
							valid =
								false;

							return;
						}
					}

					const id =
						normalizeOfferIds(
							[
								input.value,
							]
						);

					if (
						id.length !==
							1
					) {
						valid =
							false;
					}
				}
			);

		if (!valid) {
			showError(
				checkout,
				getText(
					'orderBumpInvalid',
					'One of the selected Special Discounts is no longer available. Please select again.'
				)
			);

			scrollToElement(
				section
			);
		}

		return valid;
	}

	/**
	 * Validate delivery.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function validateDelivery(
		checkout
	) {
		const api =
			window.eilmoCfDelivery;

		if (
			!api ||
			typeof api.validateCheckout !==
				'function'
		) {
			return true;
		}

		const valid =
			api.validateCheckout(
				checkout
			);

		if (!valid) {
			scrollToElement(
				checkout.querySelector(
					SELECTORS.delivery
				)
			);
		}

		return valid;
	}

	/**
	 * Validate Cash on Delivery / Advance / Full Payment Option.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function validateAdvance(
		checkout
	) {
		const api =
			window
				.eilmoCfAdvancePayment;

		if (
			!api ||
			typeof api.validate !==
				'function'
		) {
			return true;
		}

		const valid =
			api.validate(
				checkout
			);

		if (!valid) {
			scrollToElement(
				checkout.querySelector(
					SELECTORS.advance
				)
			);
		}

		return valid;
	}

	/**
	 * Validate payment method.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function validatePayment(
		checkout
	) {
		/*
		 * COD deliberately hides the complete Payment
		 * Method section, so there is nothing to validate
		 * here. The Payment Option itself is validated by
		 * validateAdvance() and again authoritatively in PHP.
		 */
		if (
			isCashOnDelivery(
				checkout
			)
		) {
			return true;
		}

		const api =
			window.eilmoCfPayment;

		if (
			!api ||
			typeof api.validateCheckout !==
				'function'
		) {
			return true;
		}

		const valid =
			api.validateCheckout(
				checkout,
				true
			);

		if (!valid) {
			if (
				typeof api
					.focusFirstInvalid ===
						'function'
			) {
				api.focusFirstInvalid(
					checkout
				);
			} else {
				scrollToElement(
					checkout.querySelector(
						SELECTORS
							.paymentSection
					)
				);
			}
		}

		return valid;
	}

	/**
	 * Validate customer information.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function validateCustomer(
		checkout
	) {
		const api =
			window.eilmoCfCustomer;

		if (
			!api ||
			typeof api.validateCheckout !==
				'function'
		) {
			return true;
		}

		const valid =
			api.validateCheckout(
				checkout,
				true
			);

		if (!valid) {
			if (
				typeof api
					.focusFirstInvalid ===
						'function'
			) {
				api.focusFirstInvalid(
					checkout
				);
			} else {
				scrollToElement(
					checkout.querySelector(
						SELECTORS.customerForm
					)
				);
			}
		}

		return valid;
	}

	/**
	 * Validate complete checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function validateCheckout(
		checkout
	) {
		clearMessages(
			checkout
		);

		if (
			!validateProducts(
				checkout
			)
		) {
			return false;
		}

		if (
			!validateComboOffers(
				checkout
			)
		) {
			return false;
		}

		if (
			!validateOrderBumps(
				checkout
			)
		) {
			return false;
		}

		if (
			isCouponLoading(
				checkout
			)
		) {
			showError(
				checkout,
				getText(
					'couponProcessing',
					'Please wait for coupon validation to finish.'
				)
			);

			return false;
		}

		if (
			!validateDelivery(
				checkout
			)
		) {
			return false;
		}

		if (
			!validateAdvance(
				checkout
			)
		) {
			return false;
		}

		if (
			!validatePayment(
				checkout
			)
		) {
			return false;
		}

		if (
			!validateCustomer(
				checkout
			)
		) {
			return false;
		}

		const fraudApi = window.eilmoCfFraudCheck;
		if (
			fraudApi &&
			'function' === typeof fraudApi.validate &&
			!fraudApi.validate(checkout)
		) {
			return false;
		}

		return true;
	}

	/**
	 * Build final server payload.
	 *
	 * checkout_token is used only for idempotency.
	 *
	 * security contains Bot Protection transport
	 * values generated by the checkout renderer.
	 *
	 * No frontend calculated totals, Combo prices or
	 * Order Bump prices are submitted.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function buildPayload(
		checkout
	) {
		const orderBumpData =
			getOrderBumpData(
				checkout
			);

		const advanceData =
			getAdvanceData(
				checkout
			);

		const paymentData =
			getPaymentData(
				checkout
			);

		return {
			checkout_token:
				getCheckoutToken(
					checkout
				),

			security:
				getSecurityData(
					checkout
				),

			items:
				getItems(
					checkout
				),

			combo_offers:
				getComboData(
					checkout
				),

			/*
			 * Browser submits only selected configured
			 * Order Bump IDs.
			 *
			 * Product, variation, quantity, pricing,
			 * discount and compatibility rules are
			 * resolved authoritatively by the server.
			 */
			order_bumps: {
				selected_ids:
					normalizeOfferIds(
						orderBumpData
							.selected_ids
					),

				context:
					orderBumpData.context,
			},

			customer:
				getCustomerData(
					checkout
				),

			delivery:
				getDeliveryData(
					checkout
				),

			advance_payment:
				advanceData,

			fraud_check:
				getFraudData(
					checkout
				),

			payment:
				paymentData,

			coupon_code:
				getCouponCode(
					checkout
				),
		};
	}

	/**
	 * Get AJAX response message.
	 *
	 * @param {*}      response Response.
	 * @param {string} fallback Fallback.
	 *
	 * @return {string}
	 */
	function getResponseMessage(
		response,
		fallback
	) {
		if (
			response &&
			response.data &&
			typeof response.data
				.message ===
					'string' &&
			response.data.message !== ''
		) {
			return response.data
				.message;
		}

		if (
			response &&
			typeof response.message ===
				'string' &&
			response.message !== ''
		) {
			return response.message;
		}

		return fallback;
	}

	/**
	 * Parse AJAX response.
	 *
	 * @param {Response} response Response.
	 *
	 * @return {Promise<Object>}
	 */
	async function parseResponse(
		response
	) {
		const contentType =
			response.headers.get(
				'content-type'
			) || '';

		if (
			contentType.includes(
				'application/json'
			)
		) {
			return response.json();
		}

		const text =
			await response.text();

		try {
			return JSON.parse(
				text
			);
		} catch (error) {
			return {
				success:
					false,

				data: {
					message:
						text.trim() ||
						getText(
							'error',
							'Something went wrong. Please try again.'
						),
				},
			};
		}
	}

	/**
	 * Dispatch Eilmo checkout event.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      name     Event.
	 * @param {Object}      detail   Detail.
	 *
	 * @return {void}
	 */
	function dispatchCheckoutEvent(
		checkout,
		name,
		detail = {}
	) {
		checkout.dispatchEvent(
			new CustomEvent(
				name,
				{
					bubbles:
						true,

					detail:
						Object.assign(
							{
								checkout:
									checkout,
							},
							detail
						),
				}
			)
		);
	}

	/**
	 * Send order request.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {Object}      payload  Payload.
	 *
	 * @return {Promise<Object>}
	 */
	async function sendOrderRequest(
		checkout,
		payload
	) {
		const config =
			window.eilmoCf || {};

		const ajaxUrl =
			typeof config.ajaxUrl ===
				'string'
				? config.ajaxUrl
				: '';

		const nonce =
			typeof config.ajaxNonce ===
				'string'
				? config.ajaxNonce
				: '';

		if (
			!ajaxUrl ||
			!nonce
		) {
			throw new Error(
				getText(
					'error',
					'Something went wrong. Please try again.'
				)
			);
		}

		const body =
			new URLSearchParams();

		body.set(
			'action',
			'eilmo_cf_create_order'
		);

		body.set(
			'nonce',
			nonce
		);

		body.set(
			'payload',
			JSON.stringify(
				payload
			)
		);

		const response =
			await fetch(
				ajaxUrl,
				{
					method:
						'POST',

					headers: {
						'Content-Type':
							'application/x-www-form-urlencoded; charset=UTF-8',
					},

					body:
						body.toString(),

					credentials:
						'same-origin',
				}
			);

		const result =
			await parseResponse(
				response
			);

		if (
			!response.ok ||
			!result ||
			result.success !==
				true
		) {
			const message =
				getResponseMessage(
					result,
					getText(
						'error',
						'Something went wrong. Please try again.'
					)
				);

			const data =
				result &&
				result.data &&
				typeof result.data ===
					'object'
					? result.data
					: {};

			dispatchCheckoutEvent(
				checkout,
				'eilmo:orderError',
				{
					response:
						result,

					message:
						message,
				}
			);

			/*
			 * Existing order payment retry.
			 *
			 * At this point an order already exists,
			 * therefore the checkout token has completed
			 * its purpose and should be cleared before
			 * redirecting to payment.
			 */
			if (
				typeof data.retry_url ===
					'string' &&
				data.retry_url.trim() !==
					''
			) {
				clearCheckoutToken(
					checkout
				);

				showNotice(
					checkout,
					message
				);

				window.location.assign(
					data.retry_url
				);

				return {
					redirected:
						true,
				};
			}

			/*
			 * Regular errors intentionally retain the
			 * same checkout token.
			 *
			 * A retry must remain the same checkout
			 * attempt for server-side idempotency.
			 */
			throw new Error(
				message
			);
		}

		return result;
	}

	/**
	 * Handle successful order response.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {Object}      result   AJAX result.
	 *
	 * @return {void}
	 */
	function handleOrderSuccess(
		checkout,
		result
	) {
		const data =
			result &&
			result.data &&
			typeof result.data ===
				'object'
				? result.data
				: {};

		const orderId =
			toInteger(
				data.order_id ||
					0
			);

		const orderKey =
			typeof data.order_key ===
				'string'
				? data.order_key
				: '';

		const redirect =
			typeof data.redirect ===
				'string'
				? data.redirect.trim()
				: '';

		dispatchCheckoutEvent(
			checkout,
			'eilmo:orderSuccess',
			{
				response:
					data,

				orderId:
					orderId,

				orderKey:
					orderKey,

				redirect:
					redirect,

				checkoutToken:
					getCheckoutToken(
						checkout
					),
			}
		);

		/*
		 * Server confirmed successful order creation.
		 *
		 * The token must not be reused for a new
		 * intentional checkout.
		 */
		clearCheckoutToken(
			checkout
		);

		if (redirect) {
			showNotice(
				checkout,
				typeof data.message ===
					'string' &&
				data.message !== ''
					? data.message
					: getText(
						'orderSuccess',
						'Your order has been placed successfully.'
					)
			);

			window.location.assign(
				redirect
			);

			return;
		}

		showNotice(
			checkout,
			typeof data.message ===
				'string' &&
			data.message !== ''
				? data.message
				: getText(
					'orderSuccess',
					'Your order has been placed successfully.'
				)
		);
	}

	/**
	 * Submit Eilmo checkout.
	 *
	 * THIS is the final Order Now function.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Promise<void>}
	 */
	async function submitCheckout(
		checkout
	) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return;
		}

		if (
			submitting.has(
				checkout
			)
		) {
			return;
		}

		/*
		 * Complete Eilmo validation first.
		 */
		if (
			!validateCheckout(
				checkout
			)
		) {
			return;
		}

		/*
		 * Ensure this checkout attempt already has
		 * a persistent idempotency token before the
		 * request begins.
		 */
		getCheckoutToken(
			checkout
		);

		submitting.add(
			checkout
		);

		clearMessages(
			checkout
		);

		setLoading(
			checkout,
			true
		);

		try {

			/*
			 * Multiple Product quantities and variation
			 * choices must be synchronized before the
			 * final transport payload is collected.
			 */
			const multipleProductsApi =
				window.eilmoCfMultipleProducts;

			if (
				multipleProductsApi &&
				typeof multipleProductsApi.refresh ===
					'function'
			) {
				multipleProductsApi.refresh(
					checkout
				);
			}

			/*
			 * Combo selection may have changed very
			 * recently. Synchronize before payload.
			 */
			const comboApi =
				window.eilmoCfComboOffers;

			if (
				comboApi &&
				typeof comboApi.refresh ===
					'function'
			) {
				comboApi.refresh(
					checkout
				);
			}

			/*
			 * Order Bump selection may also have
			 * changed immediately before submission.
			 */
			const orderBumpApi =
				window.eilmoCfOrderBump;

			if (
				orderBumpApi &&
				typeof orderBumpApi.refresh ===
					'function'
			) {
				orderBumpApi.refresh(
					checkout
				);
			}

			/*
			 * Payment Option may have changed very recently.
			 * Refresh it before Payment Methods because COD
			 * controls whether the Payment Method section is
			 * required at all.
			 */
			const advanceApi =
				window.eilmoCfAdvancePayment;

			if (
				advanceApi &&
				typeof advanceApi.refresh ===
					'function'
			) {
				advanceApi.refresh(
					checkout
				);
			}

			/*
			 * Payment selection may have changed very
			 * recently. Synchronize after Payment Option.
			 */
			const paymentApi =
				window.eilmoCfPayment;

			if (
				paymentApi &&
				typeof paymentApi.refresh ===
					'function'
			) {
				paymentApi.refresh(
					checkout
				);
			}

			const payload =
				buildPayload(
					checkout
				);

			dispatchCheckoutEvent(
				checkout,
				'eilmo:orderSubmitting',
				{
					payload:
						payload,

					checkoutToken:
						payload.checkout_token,
				}
			);

			const result =
				await sendOrderRequest(
					checkout,
					payload
				);

			if (
				result &&
				result.redirected ===
					true
			) {
				return;
			}

			handleOrderSuccess(
				checkout,
				result
			);

		} catch (error) {
			const message =
				error instanceof
					Error &&
				error.message
					? error.message
					: getText(
						'error',
						'Something went wrong. Please try again.'
					);

			showError(
				checkout,
				message
			);

			dispatchCheckoutEvent(
				checkout,
				'eilmo:orderError',
				{
					message:
						message,

					error:
						error,

					checkoutToken:
						getCheckoutToken(
							checkout
						),
				}
			);

		} finally {
			submitting.delete(
				checkout
			);

			setLoading(
				checkout,
				false
			);
		}
	}

	/**
	 * Handle Order Now button.
	 *
	 * @param {MouseEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleClick(event) {
		if (
			!(
				window.eilmoCfDom.isElement(event.target)
			)
		) {
			return;
		}

		const button =
			event.target.closest(
				SELECTORS.submit
			);

		if (
			!(
				window.eilmoCfDom.isElement(button, 'BUTTON')
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				button
			);

		if (!checkout) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		/*
		 * Elementor editor previews calculations and interaction only.
		 * Never create an order from the editor canvas.
		 */
		if (isElementorEditorCanvas()) {
			return;
		}

		submitCheckout(
			checkout
		);
	}

	/**
	 * Refresh modules that expose a public refresh API.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function refreshCheckoutModules(
		checkout
	) {
		const modules = [
			window.eilmoCfProductGallery,
			window.eilmoCfQuantity,
			window.eilmoCfSingleProduct,
			window.eilmoCfMultipleProducts,
			window.eilmoCfComboOffers,
			window.eilmoCfOrderBump,
			window.eilmoCfDiscount,
			window.eilmoCfDelivery,
			window.eilmoCfAdvancePayment,
			window.eilmoCfPayment,
			window.eilmoCfSummary,
		];

		modules.forEach(
			function (api) {
				if (
					api &&
					typeof api.refresh ===
						'function'
				) {
					api.refresh(
						checkout
					);
				}
			}
		);

		const couponApi =
			window.eilmoCfCoupon;

		if (
			couponApi &&
			typeof couponApi.refresh ===
				'function'
		) {
			couponApi.refresh(
				checkout
			);
		}

	}

	/**
	 * Initialize checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function initializeCheckout(
		checkout
	) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return;
		}

		const section =
			checkout.querySelector(
				SELECTORS.orderSection
			);

		if (
			!(
				window.eilmoCfDom.isElement(section)
			)
		) {
			return;
		}

		const alreadyInitialized =
			section.dataset
				.eilmoInitialized ===
					'yes';

		if (!alreadyInitialized) {
			section.dataset
				.eilmoInitialized =
					'yes';

			/*
			 * Initialize persistent checkout token as soon
			 * as the checkout becomes active.
			 */
			if (!isElementorEditorCanvas()) {
				getCheckoutToken(
					checkout
				);
			}

			clearMessages(
				checkout
			);

			setLoading(
				checkout,
				false
			);
		}

		/*
		 * Always synchronize module state.
		 *
		 * Elementor may render fresh inner markup while
		 * the page itself remains loaded.
		 */
		refreshCheckoutModules(
			checkout
		);
	}

	/**
	 * Collect Checkout Flow instances from a render scope.
	 *
	 * @param {Document|Element|null} scope Render scope.
	 *
	 * @return {Array<HTMLElement>}
	 */
	function getCheckoutsFromScope(
		scope
	) {
		const checkouts =
			[];

		if (
			window.eilmoCfDom.isElement(scope) &&
			scope.matches(
				SELECTORS.checkout
			) &&
			window.eilmoCfDom.isElement(scope)
		) {
			checkouts.push(
				scope
			);
		}

		if (
			scope &&
			typeof scope.querySelectorAll ===
				'function'
		) {
			scope
				.querySelectorAll(
					SELECTORS.checkout
				)
				.forEach(
					function (checkout) {
						if (
							window.eilmoCfDom.isElement(checkout) &&
							!checkouts.includes(
								checkout
							)
						) {
							checkouts.push(
								checkout
							);
						}
					}
				);
		}

		return checkouts;
	}

	/**
	 * Initialize all checkout instances in a scope.
	 *
	 * @param {Document|Element|null} scope Render scope.
	 *
	 * @return {void}
	 */
	function initialize(
		scope = document
	) {
		getCheckoutsFromScope(
			scope
		).forEach(
			function (checkout) {
				initializeCheckout(
					checkout
				);
			}
		);
	}

	/**
	 * Elementor hook registration state.
	 *
	 * @type {boolean}
	 */
	let elementorHookRegistered =
		false;

	/**
	 * Register Checkout Flow widget-ready hook.
	 *
	 * @return {void}
	 */
	function registerElementorHook() {
		if (
			elementorHookRegistered ||
			!window.elementorFrontend ||
			!window.elementorFrontend.hooks ||
			typeof window.elementorFrontend
				.hooks.addAction !==
					'function'
		) {
			return;
		}

		window.elementorFrontend
			.hooks.addAction(
				'frontend/element_ready/eilmo-checkout-flow.default',
				function ($scope) {
					const scope =
						$scope &&
						window.eilmoCfDom.isElement($scope[0])
							? $scope[0]
							: null;

					if (scope) {
						initialize(
							scope
						);
					}
				}
			);

		elementorHookRegistered =
			true;
	}

	/**
	 * Initialize Elementor integration.
	 *
	 * @return {void}
	 */
	function initializeElementor() {
		registerElementorHook();

		if (
			window.jQuery &&
			typeof window.jQuery ===
				'function'
		) {
			window.jQuery(
				window
			).on(
				'elementor/frontend/init',
				registerElementorHook
			);
		}
	}

	/**
	 * Observe dynamically inserted checkout markup.
	 *
	 * This is a fallback for Elementor editor rerenders,
	 * AJAX fragments and other builder/runtime DOM swaps.
	 *
	 * @return {void}
	 */
	function observeDynamicCheckouts() {
		if (
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

	/**
	 * Public Checkout API.
	 */
	window.eilmoCfCheckout = {
		submit:
			submitCheckout,

		validateCheckout:
			validateCheckout,

		buildPayload:
			buildPayload,

		getItems:
			getItems,

		getComboData:
			getComboData,

		getOrderBumpData:
			getOrderBumpData,

		getCustomerData:
			getCustomerData,

		getDeliveryData:
			getDeliveryData,

		getAdvanceData:
			getAdvanceData,

		getPaymentType:
			getPaymentType,

		isCashOnDelivery:
			isCashOnDelivery,

		getPaymentData:
			getPaymentData,

		getGatewayData:
			getGatewayData,

		getSecurityData:
			getSecurityData,

		getCheckoutToken:
			getCheckoutToken,

		clearCheckoutToken:
			clearCheckoutToken,

		initialize:
			initialize,

		refresh:
			function (checkout) {
				if (
					window.eilmoCfDom.isElement(checkout)
				) {
					initializeCheckout(
						checkout
					);

					return;
				}

				initialize();
			},
	};

	document.addEventListener(
		'click',
		handleClick
	);

	initializeElementor();
	observeDynamicCheckouts();

	if (
		document.readyState ===
			'loading'
	) {
		document.addEventListener(
			'DOMContentLoaded',
			function () {
				initialize();
				registerElementorHook();
			}
		);
	} else {
		initialize();
		registerElementorHook();
	}
})();
