/**
 * Eilmo Checkout Flow - Special Offer.
 *
 * Handles:
 * - Order Bump selection.
 * - Single / multiple selection mode.
 * - Frontend preview totals.
 * - Checkout state synchronization.
 * - Summary recalculation events.
 * - Public Order Bump API.
 *
 * Important:
 *
 * Frontend prices are preview values only.
 * Final Order Bump product and pricing are
 * resolved again server-side using the selected
 * Order Bump ID.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	/**
	 * Selectors.
	 */
	const SELECTORS = {
		checkout:
			'[data-eilmo-checkout]',

		section:
			'[data-eilmo-special-offers]',

		offer:
			'[data-eilmo-special-offer]',

		input:
			'[data-eilmo-special-offer-input]',
	};

	/**
	 * Convert value to number.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toNumber(value) {
		const parsed =
			Number.parseFloat(
				String(
					value ?? ''
				)
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
	 * Get checkout.
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
	 * Get Order Bump section.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {HTMLElement|null}
	 */
	function getSection(checkout) {
		const section =
			checkout.querySelector(
				SELECTORS.section
			);

		return window.eilmoCfDom.isElement(section)
				? section
				: null;
	}

	/**
	 * Build the live cart context used by Special Discount conditions.
	 * Server-side order validation rebuilds the same context from trusted data.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @return {Object}
	 */
	function parseIds(value) {
		try {
			const parsed = JSON.parse(String(value || '[]'));
			if (!Array.isArray(parsed)) return [];
			return parsed.map(function (id) { return Number.parseInt(String(id || '0'), 10) || 0; })
				.filter(function (id, index, all) { return id > 0 && all.indexOf(id) === index; });
		} catch (error) {
			return [];
		}
	}

	function getEligibilityContext(checkout) {
		let productTotal = 0;
		let cartQuantity = 0;
		const productQuantities = {};
		const productTotals = {};
		const productIds = [];
		const seen = new Set();

		function addLine(productId, variationId, quantity, lineTotal) {
			if (productId <= 0 || quantity <= 0) return;
			cartQuantity += quantity;
			productIds.push(productId);
			productQuantities[productId] = toNumber(productQuantities[productId]) + quantity;
			productTotals[productId] = toNumber(productTotals[productId]) + lineTotal;
			if (variationId > 0) {
				productIds.push(variationId);
				productQuantities[variationId] = toNumber(productQuantities[variationId]) + quantity;
				productTotals[variationId] = toNumber(productTotals[variationId]) + lineTotal;
			}
		}

		checkout.querySelectorAll('[data-eilmo-product-item], [data-eilmo-item]').forEach(function (item) {
			if (!(window.eilmoCfDom.isElement(item)) || item.closest('[data-eilmo-combo-offer], [data-eilmo-order-bump]')) return;
			const input = item.querySelector('[data-eilmo-quantity-input]');
			if (!(window.eilmoCfDom.isElement(input, 'INPUT'))) return;
			const quantity = toNumber(input.value);
			const productId = Number.parseInt(String(item.dataset.productId || input.dataset.productId || '0'), 10) || 0;
			const variationId = Number.parseInt(String(item.dataset.variationId || input.dataset.variationId || '0'), 10) || 0;
			const key = variationId > 0 ? `v:${variationId}` : `p:${productId}`;
			if (quantity <= 0 || seen.has(key)) return;
			seen.add(key);
			const price = toNumber(item.dataset.price || item.dataset.productPrice || input.dataset.price || 0);
			const lineTotal = price * quantity;
			productTotal += lineTotal;
			addLine(productId, variationId, quantity, lineTotal);
		});

		checkout.querySelectorAll('[data-eilmo-combo-offer]').forEach(function (combo) {
			if (!(window.eilmoCfDom.isElement(combo))) return;
			const input = combo.querySelector('[data-eilmo-combo-input]');
			if (!(window.eilmoCfDom.isElement(input, 'INPUT')) || !input.checked || input.disabled) return;
			const components = Array.from(combo.querySelectorAll('.eilmo-cf-combo-offer__item[data-product-id]'));
			const regularTotal = components.reduce(function (sum, item) { return sum + toNumber(item.dataset.lineTotal || 0); }, 0);
			const comboTotal = toNumber(combo.dataset.comboTotal);
			const scale = regularTotal > 0 ? Math.min(1, comboTotal / regularTotal) : 0;
			productTotal += comboTotal;
			components.forEach(function (item) {
				if (!(window.eilmoCfDom.isElement(item))) return;
				const quantity = toNumber(item.dataset.quantity || 1);
				const productId = Number.parseInt(String(item.dataset.productId || '0'), 10) || 0;
				const variationId = Number.parseInt(String(item.dataset.variationId || '0'), 10) || 0;
				addLine(productId, variationId, quantity, toNumber(item.dataset.lineTotal || 0) * scale);
			});
		});

		return {
			productTotal: productTotal,
			cartQuantity: cartQuantity,
			productQuantities: productQuantities,
			productTotals: productTotals,
			productIds: productIds.filter(function (id, index, all) { return id > 0 && all.indexOf(id) === index; }),
			currentProductIds: parseIds(checkout.getAttribute('data-eilmo-current-product-ids')),
		};
	}

	function getScopedEligibilityContext(offer, context) {
		const scope = ['current_product', 'selected_products', 'whole_cart'].includes(String(offer.dataset.ruleScope || ''))
			? String(offer.dataset.ruleScope)
			: 'whole_cart';
		if ('whole_cart' === scope) return context;
		let ids = 'current_product' === scope
			? (context.currentProductIds || []).slice()
			: parseIds(offer.dataset.scopeProductIds);
		const requiredProduct = Number.parseInt(String(offer.dataset.conditionProductId || '0'), 10) || 0;
		if ('selected_products' === scope && 0 === ids.length && requiredProduct > 0) ids.push(requiredProduct);
		let productTotal = 0;
		let cartQuantity = 0;
		const productQuantities = {};
		ids.forEach(function (id) {
			productTotal += toNumber((context.productTotals || {})[id]);
			const quantity = toNumber((context.productQuantities || {})[id]);
			if (quantity > 0) {
				productQuantities[id] = quantity;
				cartQuantity += quantity;
			}
		});
		return {productTotal: productTotal, cartQuantity: cartQuantity, productQuantities: productQuantities, productIds: ids};
	}

	function formatAmount(value) {
		const config = window.eilmoCf || {};
		const currency = config.currency && 'object' === typeof config.currency ? config.currency : {};
		const decoder = document.createElement('textarea');
		decoder.innerHTML = String(currency.symbol || config.currencySymbol || '৳');
		const symbol = decoder.value;
		return symbol + toNumber(value).toFixed(Number.isFinite(Number(currency.decimals)) ? Number(currency.decimals) : 2);
	}

	/**
	 * Determine whether a rendered offer has expired.
	 *
	 * @param {HTMLElement} offer Offer card.
	 * @param {number}      now   Current Unix timestamp.
	 * @return {boolean}
	 */
	function isOfferExpired(offer, now = Math.floor(Date.now() / 1000)) {
		if ('yes' === String(offer.dataset.expired || 'no')) {
			return true;
		}

		const end = Number.parseInt(String(offer.dataset.endTimestamp || '0'), 10);
		return Number.isFinite(end) && end > 0 && now >= end;
	}

	/**
	 * Briefly explain a live price refresh without displaying an error.
	 *
	 * @param {HTMLElement} checkout Checkout wrapper.
	 * @param {HTMLElement} section  Special Offer section.
	 * @return {void}
	 */
	function showExpiredNotice(checkout, section) {
		// Special Discounts are automatic and intentionally silent in checkout.
		// Eligibility/totals still refresh, but no campaign notification is shown.
		void checkout;
		void section;
	}

	/** @param {HTMLElement} checkout Checkout. */
	function updateEligibility(checkout) {
		const context = getEligibilityContext(checkout);

		checkout.querySelectorAll(SELECTORS.offer).forEach(function (offer) {
			if (!(window.eilmoCfDom.isElement(offer))) { return; }
			const input = offer.querySelector(SELECTORS.input);
			if (!(window.eilmoCfDom.isElement(input, 'INPUT'))) { return; }

			if (isOfferExpired(offer)) {
				input.checked = false;
				input.disabled = true;
				offer.hidden = true;
				offer.dataset.expired = 'yes';
				offer.dataset.eligible = 'no';
				offer.classList.remove('is-selected', 'is-applied', 'is-eligible');
				offer.classList.add('is-expired');
				offer.setAttribute('aria-disabled', 'true');
				return;
			}

			const type = String(offer.dataset.conditionType || 'always');
			const scoped = getScopedEligibilityContext(offer, context);
			let eligible = true;
			let remainingAmount = 0;
			let remainingQuantity = 0;
			const requiredProduct = Number.parseInt(String(offer.dataset.conditionProductId || '0'), 10) || 0;

			if (['order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal'].includes(type)) {
				const minimum = toNumber(offer.dataset.conditionMinimum);
				const maximum = toNumber(offer.dataset.conditionMaximum);
				eligible = scoped.productTotal >= minimum && (maximum <= 0 || scoped.productTotal <= maximum);
				remainingAmount = Math.max(0, minimum - scoped.productTotal);
			} else if (['specific_product', 'product_exists'].includes(type)) {
				const required = Math.max(1, toNumber(offer.dataset.conditionProductQuantity || 1));
				const current = toNumber((scoped.productQuantities || {})[requiredProduct]);
				eligible = requiredProduct > 0 && current >= required;
				remainingQuantity = Math.max(0, required - current);
			} else if (['cart_quantity', 'minimum_quantity', 'buy_x_quantity'].includes(type)) {
				const required = Math.max(1, toNumber(offer.dataset.conditionCartQuantity || 1));
				eligible = scoped.cartQuantity >= required;
				remainingQuantity = Math.max(0, required - scoped.cartQuantity);
			} else if ('current_product' === type) {
				eligible = Array.isArray(scoped.productIds) && scoped.productIds.length > 0 && scoped.cartQuantity > 0;
			}

			const ruleScope = String(offer.dataset.ruleScope || 'whole_cart');
			if (['current_product', 'selected_products'].includes(ruleScope) && (!Array.isArray(scoped.productIds) || 0 === scoped.productIds.length || toNumber(scoped.cartQuantity) <= 0)) {
				eligible = false;
			}

			const autoApply = 'auto_apply' === String(offer.dataset.applyBehavior || 'customer_selectable');
			if (!eligible) { input.checked = false; }
			if (autoApply) { input.checked = eligible; }
			input.disabled = !eligible || autoApply;
			offer.hidden = !eligible && 'hide' === String(offer.dataset.ineligibleAction || 'show_locked');
			offer.classList.toggle('is-eligible', eligible);
			offer.classList.toggle('is-ineligible', !eligible);
			offer.classList.toggle('is-auto-applied', autoApply && eligible);
			offer.dataset.eligible = eligible ? 'yes' : 'no';
			offer.setAttribute('aria-disabled', eligible ? 'false' : 'true');

			const progress = offer.querySelector('[data-eilmo-special-discount-progress]');
			if (window.eilmoCfDom.isElement(progress)) {
				let text = String(offer.dataset.lockedText || '');
				const compactCard = offer.classList.contains('eilmo-cf-special-discount-card--compact');
				const genericAmountText = 'Add {remaining_amount} more to unlock this reward.';
				if (compactCard && !eligible) {
					if (['order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal'].includes(type)) { text = 'Add {remaining_amount} more'; }
					else { text = 'Add {remaining_quantity} more'; }
				} else if (!text || ('order_amount' !== type && genericAmountText === text)) {
					if (['order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal'].includes(type)) { text = 'Add {remaining_amount} more to unlock this reward.'; }
					else if (['specific_product', 'product_exists'].includes(type)) { text = 'Add {remaining_quantity} more of product {required_product} to unlock this reward.'; }
					else if (['cart_quantity', 'minimum_quantity', 'buy_x_quantity'].includes(type)) { text = 'Add {remaining_quantity} more product(s) to unlock this reward.'; }
				}
				text = text.split('{remaining_amount}').join(formatAmount(remainingAmount))
					.split('{remaining_quantity}').join(String(remainingQuantity))
					.split('{required_product}').join(requiredProduct > 0 ? `#${requiredProduct}` : '');
				progress.textContent = text;
				progress.hidden = eligible || !text;
			}
		});
	}

	/**
	 * Get selected Order Bump IDs.
	 *
	 * Only IDs are returned.
	 *
	 * Product IDs and prices must never become
	 * authoritative from frontend data.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Array<string>}
	 */
	function getSelectedIds(checkout) {
		const selected =
			[];

		checkout
			.querySelectorAll(
				`${SELECTORS.input}:checked`
			)
			.forEach(
				function (input) {
					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						)
					) {
						return;
					}

					const offer = input.closest(SELECTORS.offer);
					const offerType = window.eilmoCfDom.isElement(offer) ? String(offer.dataset.offerType || '') : '';
					const applyBehavior = window.eilmoCfDom.isElement(offer) ? String(offer.dataset.applyBehavior || '') : '';
					/* Monetary and Free Delivery auto-rules are owned by SpecialDiscountEngine. */
					if ('auto_apply' === applyBehavior && 'free_gift' !== offerType) {
						return;
					}

					const id =
						String(
							input.value ||
								''
						).trim();

					if (
						!id ||
						selected.includes(
							id
						)
					) {
						return;
					}

					selected.push(
						id
					);
				}
			);

		return selected;
	}

	/**
	 * Get selected frontend preview totals.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getTotals(checkout) {
		let regularTotal =
			0;

		let discount =
			0;

		let bumpTotal =
			0;

		let selectedCount =
			0;

		checkout
			.querySelectorAll(
				SELECTORS.offer
			)
			.forEach(
				function (offer) {
					if (
						!(
							window.eilmoCfDom.isElement(offer)
						)
					) {
						return;
					}

					const input =
						offer.querySelector(
							SELECTORS.input
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						) ||
						!input.checked
					) {
						return;
					}

					selectedCount +=
						1;

					regularTotal +=
						toNumber(
							offer.dataset
								.regularTotal
						);

					discount +=
						toNumber(
							offer.dataset
								.discount
						);

					bumpTotal +=
						toNumber(
							offer.dataset
								.bumpTotal
						);
				}
			);

		return {
			selectedCount:
				selectedCount,

			regularTotal:
				regularTotal,

			discount:
				discount,

			bumpTotal:
				bumpTotal,
		};
	}

	/**
	 * Update selected card state.
	 *
	 * @param {HTMLElement} offer Offer.
	 *
	 * @return {void}
	 */
	function updateOfferState(offer) {
		const input =
			offer.querySelector(
				SELECTORS.input
			);

		const selected =
			window.eilmoCfDom.isElement(input, 'INPUT') &&
			input.checked;

		const eligible =
			'no' !== String(
				offer.dataset.eligible ||
					'yes'
			);

		offer.classList.toggle(
			'is-selected',
			selected
		);

		offer.dataset.selected =
			selected
				? 'yes'
				: 'no';

		offer.classList.toggle(
			'is-applied',
			selected
		);

		const addText =
			offer.querySelector(
				'[data-eilmo-special-add-text]'
			);

		const appliedText =
			offer.querySelector(
				'[data-eilmo-special-applied-text]'
			);

		if (window.eilmoCfDom.isElement(addText)) {
			addText.hidden = selected || !eligible;
		}

		if (window.eilmoCfDom.isElement(appliedText)) {
			appliedText.hidden = !selected;
		}

		const addedAction = offer.querySelector('[data-eilmo-special-added-action]');
		if (window.eilmoCfDom.isElement(addedAction)) {
			addedAction.hidden = !eligible;
		}

		const progress =
			offer.querySelector(
				'[data-eilmo-special-discount-progress]'
			);

		if (
			selected &&
			window.eilmoCfDom.isElement(progress)
		) {
			progress.hidden = true;
		}
	}

	/**
	 * Disable a selectable Free Delivery card when a Coupon or
	 * Automatic Discount already provides free delivery.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function updateFreeDeliveryAvailability(checkout) {
		const alreadyApplied =
			'yes' === checkout.dataset.automaticDiscountFreeDelivery ||
			'yes' === checkout.dataset.couponFreeDelivery;

		checkout
			.querySelectorAll(
				`${SELECTORS.offer}[data-free-delivery="yes"]`
			)
			.forEach(function (offer) {
				if (!(window.eilmoCfDom.isElement(offer))) {
					return;
				}

				const input = offer.querySelector(SELECTORS.input);
				if (!(window.eilmoCfDom.isElement(input, 'INPUT'))) {
					return;
				}

				if (alreadyApplied) {
					input.checked = false;
				}

				input.disabled = alreadyApplied;
				offer.classList.toggle('is-already-applied', alreadyApplied);
				offer.setAttribute('aria-disabled', alreadyApplied ? 'true' : 'false');

				const addText = offer.querySelector('[data-eilmo-special-add-text]');
				if (window.eilmoCfDom.isElement(addText)) {
					addText.textContent = alreadyApplied
						? 'Already Applied'
						: String(offer.dataset.buttonText || 'Add Offer');
				}
			});
	}

	/**
	 * Synchronize checkout Order Bump state.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {boolean}     dispatch Dispatch change event.
	 *
	 * @return {Object}
	 */
	function sync(
		checkout,
		dispatch = true
	) {
		updateEligibility(checkout);
		updateFreeDeliveryAvailability(checkout);

		const selectedIds =
			getSelectedIds(
				checkout
			);

		const totals =
			getTotals(
				checkout
			);

		checkout
			.querySelectorAll(
				SELECTORS.offer
			)
			.forEach(
				function (offer) {
					if (
						window.eilmoCfDom.isElement(offer)
					) {
						updateOfferState(
							offer
						);
					}
				}
			);

		checkout.dataset
			.orderBumpSelectedCount =
				String(
					totals
						.selectedCount
				);
		checkout.dataset.specialOfferSelectedCount = String( totals.selectedCount );

		checkout.dataset
			.orderBumpRegularTotal =
				String(
					totals
						.regularTotal
				);
		checkout.dataset.specialOfferRegularTotal = String( totals.regularTotal );

		checkout.dataset
			.orderBumpDiscount =
				String(
					totals
						.discount
				);
		checkout.dataset.specialOfferDiscount = String( totals.discount );

		checkout.dataset
			.orderBumpTotal =
				String(
					totals
						.bumpTotal
				);
		checkout.dataset.specialOfferTotal = String( totals.bumpTotal );

		/* Free Delivery is published only by the unified Special Discount engine. */
		checkout.dataset.specialOfferFreeDelivery = 'no';

		const result = {
			selectedIds:
				selectedIds,

			selectedCount:
				totals
					.selectedCount,

			regularTotal:
				totals
					.regularTotal,

			discount:
				totals
					.discount,

			bumpTotal:
				totals
					.bumpTotal,
		};

		if (dispatch) {
			checkout.dispatchEvent(
				new CustomEvent(
					'eilmo:specialOfferChange',
					{
						bubbles: true,
						detail: Object.assign(
							{ checkout: checkout },
							result
						),
					}
				)
			);

			/* Historical event retained for internal integrations. */
			checkout.dispatchEvent(
				new CustomEvent(
					'eilmo:orderBumpChange',
					{
						bubbles:
							true,

						detail: Object.assign(
							{
								checkout:
									checkout,
							},
							result
						),
					}
				)
			);

			/*
			 * Generic refresh event.
			 *
			 * summary.js will be updated to include
			 * Order Bump totals in its calculation.
			 */
			checkout.dispatchEvent(
				new CustomEvent(
					'eilmo:summaryRefresh',
					{
						bubbles:
							true,

						detail: {
							checkout:
								checkout,

							source:
								'order-bump',
						},
					}
				)
			);
		}

		return result;
	}

	/**
	 * Update all Special Offer countdowns.
	 *
	 * The browser hides an expired card immediately. The same end
	 * time is validated again by PHP before order creation.
	 *
	 * @return {void}
	 */
	function updateTimers() {
		const now =
			Math.floor(
				Date.now() / 1000
			);

		document
			.querySelectorAll(
				'[data-eilmo-special-offer-timer]'
			)
			.forEach(function (timer) {
				if (!(window.eilmoCfDom.isElement(timer))) {
					return;
				}

				const end = Number.parseInt(
					String(timer.dataset.endTimestamp || '0'),
					10
				);

				if (!Number.isFinite(end) || end <= 0) {
					return;
				}

				let remaining = Math.max(0, end - now);
				const days = Math.floor(remaining / 86400);
				remaining -= days * 86400;
				const hours = Math.floor(remaining / 3600);
				remaining -= hours * 3600;
				const minutes = Math.floor(remaining / 60);
				const seconds = remaining - minutes * 60;
				const values = { days: days, hours: hours, minutes: minutes, seconds: seconds };

				Object.keys(values).forEach(function (key) {
					const node = timer.querySelector(`[data-eilmo-timer-${key}]`);
					if (window.eilmoCfDom.isElement(node)) {
						node.textContent = String(values[key]).padStart(2, '0');
					}
				});

				if (end <= now) {
					const offer = timer.closest(SELECTORS.offer);
					if (!(window.eilmoCfDom.isElement(offer))) {
						const automaticCard = timer.closest('[data-eilmo-discount-tier]');
						if (window.eilmoCfDom.isElement(automaticCard)) {
							if ('yes' === automaticCard.dataset.expired) {
								return;
							}
							automaticCard.dataset.expired = 'yes';
							automaticCard.hidden = true;
							const checkout = getCheckout(automaticCard);
							const section = checkout ? getSection(checkout) : null;
							if (checkout && section) {
								showExpiredNotice(checkout, section);
							}
							if (checkout && window.eilmoCfDiscount && 'function' === typeof window.eilmoCfDiscount.refresh) {
								window.eilmoCfDiscount.refresh(checkout);
							}
						}
						return;
					}

					const checkout = getCheckout(offer);
					if ('yes' === offer.dataset.expired) {
						return;
					}
					offer.dataset.expired = 'yes';
					const input = offer.querySelector(SELECTORS.input);
					if (window.eilmoCfDom.isElement(input, 'INPUT')) {
						input.checked = false;
						input.disabled = true;
					}
					offer.hidden = true;
					if (checkout) {
						const section = getSection(checkout);
						if (section) {
							showExpiredNotice(checkout, section);
						}
						sync(checkout, true);
					}
				}
			});
	}

	/**
	 * Enforce single-selection mode.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLInputElement} changedInput Changed input.
	 *
	 * @return {void}
	 */
	function enforceSingleSelection(
		checkout,
		changedInput
	) {
		const section =
			getSection(
				checkout
			);

		if (
			!section ||
			section.dataset
				.selectionMode !==
					'single' ||
			!changedInput.checked
		) {
			return;
		}

		section
			.querySelectorAll(
				SELECTORS.input
			)
			.forEach(
				function (input) {
					if (
						window.eilmoCfDom.isElement(input, 'INPUT') &&
						!input.disabled &&
						input !==
							changedInput
					) {
						input.checked =
							false;
					}
				}
			);
	}

	/**
	 * Handle input change.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleChange(event) {
		if (
			!(
				window.eilmoCfDom.isElement(event.target, 'INPUT')
			) ||
			!event.target.matches(
				SELECTORS.input
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

		enforceSingleSelection(
			checkout,
			event.target
		);

		sync(
			checkout,
			true
		);
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
		const section =
			getSection(
				checkout
			);

		if (!section) {
			return;
		}

		if (
			section.dataset
				.eilmoInitialized ===
					'yes'
		) {
			return;
		}

		section.dataset
			.eilmoInitialized =
				'yes';

		sync(
			checkout,
			false
		);
	}

	/**
	 * Initialize all checkout instances.
	 *
	 * @return {void}
	 */
	function initialize() {
		document
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

		updateTimers();
	}

	/**
	 * Refresh overlap state after another promotion source changes.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handlePromotionChange(event) {
		const checkout = getCheckout(event.target);
		if (checkout) {
			sync(checkout, false);
			if (
				window.eilmoCfDelivery &&
				typeof window.eilmoCfDelivery.refresh === 'function'
			) {
				window.eilmoCfDelivery.refresh(checkout);
			}
		}
	}

	/**
	 * Public Special Offer API.
	 */
	const specialOfferApi = {

		/**
		 * Get selected IDs.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Array<string>}
		 */
		getSelectedIds:
			getSelectedIds,

		/**
		 * Get preview totals.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object}
		 */
		getTotals:
			getTotals,

		/**
		 * Synchronize checkout.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object}
		 */
		refresh: function (
			checkout
		) {
			if (
				!(
					window.eilmoCfDom.isElement(checkout)
				)
			) {
				return {
					selectedIds:
						[],

					selectedCount:
						0,

					regularTotal:
						0,

					discount:
						0,

					bumpTotal:
						0,
				};
			}

			return sync(
				checkout,
				true
			);
		},
	};

	window.eilmoCfSpecialOffer = specialOfferApi;
	window.eilmoCfOrderBump = specialOfferApi;

	document.addEventListener(
		'change',
		handleChange
	);

	document.addEventListener(
		'eilmo:discountChange',
		handlePromotionChange
	);

	document.addEventListener(
		'eilmo:couponChange',
		handlePromotionChange
	);

	[
		'eilmo:quantityChange',
		'eilmo:multipleProductsChange',
		'eilmo:comboChange',
	].forEach(function (eventName) {
		document.addEventListener(eventName, handlePromotionChange);
	});

	window.setInterval(
		updateTimers,
		1000
	);

	if (
		document.readyState ===
			'loading'
	) {
		document.addEventListener(
			'DOMContentLoaded',
			initialize
		);
	} else {
		initialize();
	}
})();
