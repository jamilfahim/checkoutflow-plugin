/**
 * Eilmo Checkout Flow - Multiple Products interactions.
 *
 * Every Simple product and exact WooCommerce variation remains an independent
 * authoritative order item. This module coordinates the compact Multiple
 * Products UI without reusing Single Product selection controls.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		root: '[data-eilmo-multiple-products]',
		checkout: '[data-eilmo-checkout]',
		card: '[data-eilmo-multiple-card]',
		productToggle: '[data-eilmo-multiple-product-toggle]',
		variationData: '[data-eilmo-multiple-variation-data]',
		variationChoice: '[data-eilmo-multiple-variation-choice]',
		attributeChoice: '[data-eilmo-multiple-attribute-choice]',
		attributeGroup: '[data-eilmo-multiple-attribute-group]',
		optionPrice: '[data-eilmo-multiple-option-price]',
		optionRegularPrice: '[data-eilmo-multiple-option-regular-price]',
		optionSaving: '[data-eilmo-multiple-option-saving]',
		optionBadge: '[data-eilmo-multiple-option-badge]',
		orderItem: '[data-eilmo-multiple-order-item]',
		quantity: '[data-eilmo-multiple-quantity]',
		quantityInput: '[data-eilmo-multiple-item-input]',
		quantityMinus: '[data-eilmo-multiple-quantity-minus]',
		quantityPlus: '[data-eilmo-multiple-quantity-plus]',
		price: '[data-eilmo-multiple-price]',
		saving: '[data-eilmo-multiple-saving]',
		stock: '[data-eilmo-multiple-stock]',
		saleBadge: '[data-eilmo-multiple-sale-badge]',
		mainImage: '[data-eilmo-multiple-main-image]',
		message: '[data-eilmo-multiple-message]',
		selectedItems: '[data-eilmo-multiple-selected-items]',
		selectedList: '[data-eilmo-multiple-selected-list]',
		selectedCount: '[data-eilmo-multiple-selected-count]',
		clearSelected: '[data-eilmo-multiple-clear]',
		removeSelected: '[data-eilmo-multiple-remove]',
	};

	const initialized = new WeakSet();

	/**
	 * Convert a value to a non-negative integer.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toInteger(value) {
		const number = Number.parseInt(String(value || '0'), 10);

		return Number.isFinite(number) ? Math.max(0, number) : 0;
	}

	/**
	 * Convert a value to a non-negative amount.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toAmount(value) {
		const number = Number.parseFloat(String(value || '0'));

		return Number.isFinite(number) ? Math.max(0, number) : 0;
	}

	/**
	 * Decode a localized HTML entity value.
	 *
	 * @param {string} value Value.
	 *
	 * @return {string}
	 */
	function decodeHtmlEntities(value) {
		const textarea = document.createElement('textarea');
		textarea.innerHTML = String(value || '');

		return textarea.value.replace(/\u00a0/g, ' ').trim();
	}

	/**
	 * Format an amount with the shared WooCommerce currency configuration.
	 *
	 * @param {number} amount Amount.
	 *
	 * @return {string}
	 */
	function formatPrice(amount) {
		const globalConfig = window.eilmoCf && window.eilmoCf.currency
			? window.eilmoCf.currency
			: {};
		const decimals = Number.isInteger(globalConfig.decimals)
			? Math.max(0, globalConfig.decimals)
			: 2;
		const decimalSeparator = typeof globalConfig.decimalSeparator === 'string'
			? decodeHtmlEntities(globalConfig.decimalSeparator)
			: '.';
		const thousandSeparator = typeof globalConfig.thousandSeparator === 'string'
			? decodeHtmlEntities(globalConfig.thousandSeparator)
			: ',';
		const symbol = decodeHtmlEntities(
			typeof globalConfig.symbol === 'string' ? globalConfig.symbol : '৳'
		);
		const position = typeof globalConfig.position === 'string'
			? globalConfig.position
			: 'left';
		const parts = toAmount(amount).toFixed(decimals).split('.');

		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);

		const formatted = decimals > 0
			? parts[0] + decimalSeparator + parts[1]
			: parts[0];

		switch (position) {
			case 'right':
				return formatted + symbol;
			case 'right_space':
				return formatted + '\u00a0' + symbol;
			case 'left_space':
				return symbol + '\u00a0' + formatted;
			case 'left':
			default:
				return symbol + formatted;
		}
	}

	/**
	 * Resolve a Multiple Products root.
	 *
	 * @param {Element|null} element Element.
	 *
	 * @return {HTMLElement|null}
	 */
	function getRoot(element) {
		if (!(window.eilmoCfDom.isElement(element))) {
			return null;
		}

		const root = element.closest(SELECTORS.root);

		return window.eilmoCfDom.isElement(root) ? root : null;
	}

	/**
	 * Resolve the checkout containing a Multiple Products root.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 *
	 * @return {HTMLElement|null}
	 */
	function getCheckout(root) {
		const checkout = root.closest(SELECTORS.checkout);

		return window.eilmoCfDom.isElement(checkout) ? checkout : null;
	}

	/**
	 * Get the parent-product selection mode.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 *
	 * @return {'single'|'multiple'}
	 */
	function getProductSelectionMode(root) {
		return root.dataset.productSelection === 'single' ? 'single' : 'multiple';
	}

	/**
	 * Get the exact-variation selection mode.
	 *
	 * Separate Attribute Sections always resolve one exact variation.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 *
	 * @return {'single'|'multiple'}
	 */
	function getVariationSelectionMode(root) {
		return root.dataset.variationLayout === 'grid' &&
			root.dataset.variationSelection === 'multiple'
			? 'multiple'
			: 'single';
	}

	/**
	 * Whether selected products may be removed.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 *
	 * @return {boolean}
	 */
	function allowsProductRemoval(root) {
		return root.dataset.allowProductRemoval !== 'no';
	}

	/**
	 * Whether quantity interaction may select an unselected card.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 *
	 * @return {boolean}
	 */
	function selectsOnQuantityChange(root) {
		return root.dataset.selectOnQuantityChange !== 'no';
	}

	/**
	 * Whether standard card quantity controls are enabled.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 *
	 * @return {boolean}
	 */
	function showsQuantity(root) {
		if (root.dataset.showQuantity) {
			return root.dataset.showQuantity === 'yes';
		}

		const checkout = getCheckout(root);

		return !checkout || checkout.dataset.showQuantity !== 'no';
	}

	/**
	 * Whether Compact Grid quantities are enabled.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 *
	 * @return {boolean}
	 */
	function showsGridQuantity(root) {
		return root.dataset.gridShowQuantity
			? root.dataset.gridShowQuantity === 'yes'
			: showsQuantity(root);
	}

	/**
	 * Parse server-prepared exact variation data for one product card.
	 *
	 * @param {HTMLElement} card Product card.
	 *
	 * @return {Array<Object>}
	 */
	function getVariations(card) {
		if (Array.isArray(card.eilmoMultipleVariations)) {
			return card.eilmoMultipleVariations;
		}

		const script = card.querySelector(SELECTORS.variationData);
		let variations = [];

		if (script) {
			try {
				const parsed = JSON.parse(script.textContent || '[]');
				variations = Array.isArray(parsed) ? parsed : [];
			} catch (error) {
				variations = [];
			}
		}

		card.eilmoMultipleVariations = variations;

		return variations;
	}

	/**
	 * Find an exact variation by ID.
	 *
	 * @param {HTMLElement} card Product card.
	 * @param {number} variationId Variation ID.
	 *
	 * @return {Object|null}
	 */
	function findVariation(card, variationId) {
		return getVariations(card).find(function (variation) {
			return toInteger(variation.id) === variationId;
		}) || null;
	}

	/**
	 * Get authoritative order items in a card.
	 *
	 * @param {HTMLElement} card Product card.
	 *
	 * @return {Array<HTMLElement>}
	 */
	function getOrderItems(card) {
		return Array.from(card.querySelectorAll(SELECTORS.orderItem)).filter(function (item) {
			return window.eilmoCfDom.isElement(item);
		});
	}

	/**
	 * Resolve an item's authoritative quantity input.
	 *
	 * @param {HTMLElement} item Order item.
	 *
	 * @return {HTMLInputElement|null}
	 */
	function getItemInput(item) {
		const input = item.querySelector(SELECTORS.quantityInput);

		return window.eilmoCfDom.isElement(input, 'INPUT') ? input : null;
	}

	/**
	 * Find an exact order item inside a product card.
	 *
	 * @param {HTMLElement} card Product card.
	 * @param {number} variationId Variation ID.
	 *
	 * @return {HTMLElement|null}
	 */
	function findOrderItem(card, variationId) {
		return getOrderItems(card).find(function (item) {
			return toInteger(item.dataset.variationId) === variationId;
		}) || null;
	}

	/**
	 * Read a quantity input's current value.
	 *
	 * @param {HTMLInputElement} input Quantity input.
	 *
	 * @return {number}
	 */
	function getQuantity(input) {
		return toInteger(input.value);
	}

	/**
	 * Read the last controller-owned quantity value.
	 *
	 * Direct input changes mutate input.value before the change event, so the
	 * stored value is needed to determine the real previous selection state.
	 *
	 * @param {HTMLInputElement} input Quantity input.
	 *
	 * @return {number}
	 */
	function getPreviousQuantity(input) {
		if (Object.prototype.hasOwnProperty.call(input.dataset, 'eilmoMultipleQuantity')) {
			return toInteger(input.dataset.eilmoMultipleQuantity);
		}

		return getQuantity(input);
	}

	/**
	 * Normalize a quantity against its WooCommerce maximum.
	 *
	 * @param {HTMLInputElement} input Quantity input.
	 * @param {*} value Requested value.
	 *
	 * @return {number}
	 */
	function normalizeQuantity(input, value) {
		let quantity = toInteger(value);

		if (input.hasAttribute('max')) {
			const maximum = toInteger(input.getAttribute('max'));

			if (maximum > 0) {
				quantity = Math.min(quantity, maximum);
			}
		}

		return quantity;
	}

	/**
	 * Determine whether an order item is selected.
	 *
	 * @param {HTMLElement} item Order item.
	 *
	 * @return {boolean}
	 */
	function isItemSelected(item) {
		const input = getItemInput(item);

		return Boolean(input && getQuantity(input) > 0);
	}

	/**
	 * Determine whether a parent-product card has selected order items.
	 *
	 * @param {HTMLElement} card Product card.
	 *
	 * @return {boolean}
	 */
	function isCardSelected(card) {
		return getOrderItems(card).some(isItemSelected);
	}

	/**
	 * Get selected attribute values from Separate Attribute Sections.
	 *
	 * @param {HTMLElement} card Product card.
	 *
	 * @return {Object<string, string>}
	 */
	function getSelectedAttributes(card) {
		const attributes = {};

		card.querySelectorAll(SELECTORS.attributeChoice + '.is-selected').forEach(function (button) {
			const key = String(button.dataset.attribute || '');
			const value = String(button.dataset.value || '');

			if (key) {
				attributes[key] = value;
			}
		});

		return attributes;
	}

	/**
	 * Test a complete or partial attribute selection against a variation.
	 * Empty WooCommerce variation values are wildcards.
	 *
	 * @param {Object} variation Variation.
	 * @param {Object<string, string>} selected Selected attributes.
	 *
	 * @return {boolean}
	 */
	function variationMatches(variation, selected) {
		const attributes = variation.attributes && typeof variation.attributes === 'object'
			? variation.attributes
			: {};

		return Object.keys(selected).every(function (key) {
			const variationValue = String(attributes[key] || '');

			return variationValue === '' || variationValue === String(selected[key]);
		});
	}

	/**
	 * Resolve the best purchasable variation for an attribute option.
	 *
	 * @param {HTMLElement} card Product card.
	 * @param {HTMLButtonElement} button Attribute option.
	 *
	 * @return {Object|null}
	 */
	function findContextualVariation(card, button) {
		const selected = getSelectedAttributes(card);
		const attribute = String(button.dataset.attribute || '');

		delete selected[attribute];
		selected[attribute] = String(button.dataset.value || '');

		const matches = getVariations(card).filter(function (variation) {
			return Boolean(variation.canPurchase) && variationMatches(variation, selected);
		});

		if (matches.length === 0) {
			return null;
		}

		const activeId = toInteger(card.dataset.activeVariationId);
		const active = matches.find(function (variation) {
			return toInteger(variation.id) === activeId;
		});

		return active || matches[0];
	}

	/**
	 * Synchronize contextual price, saving, and badge data on attribute options.
	 *
	 * @param {HTMLElement} card Product card.
	 */
	function updateAttributePricing(card) {
		card.querySelectorAll(SELECTORS.attributeChoice).forEach(function (option) {
			if (!(window.eilmoCfDom.isElement(option, 'BUTTON'))) {
				return;
			}

			const variation = findContextualVariation(card, option);
			const price = option.querySelector(SELECTORS.optionPrice);
			const regularPrice = option.querySelector(SELECTORS.optionRegularPrice);
			const saving = option.querySelector(SELECTORS.optionSaving);
			const badge = option.querySelector(SELECTORS.optionBadge);

			if (window.eilmoCfDom.isElement(price)) {
				price.innerHTML = variation ? String(variation.currentPriceHtml || '') : '';
				price.hidden = !variation || !variation.currentPriceHtml;
			}

			if (window.eilmoCfDom.isElement(regularPrice)) {
				regularPrice.innerHTML = variation ? String(variation.regularPriceHtml || '') : '';
				regularPrice.hidden = !variation || !variation.regularPriceHtml;
			}

			if (window.eilmoCfDom.isElement(saving)) {
				saving.innerHTML = variation ? String(variation.savingHtml || '') : '';
				saving.hidden = !variation || !variation.savingHtml;
			}

			if (window.eilmoCfDom.isElement(badge)) {
				const group = option.closest(SELECTORS.attributeGroup);
				const groupAllowsBadge = window.eilmoCfDom.isElement(group) && group.dataset.showBadge === 'yes';
				const visible = Boolean(groupAllowsBadge && variation && variation.badgeEnabled && variation.badgeText);

				badge.textContent = visible ? String(variation.badgeText || '') : '';
				badge.hidden = !visible;
				option.classList.toggle('has-badge', visible);
				badge.classList.remove(
					'eilmo-cf-multiple-option__badge--primary',
					'eilmo-cf-multiple-option__badge--success',
					'eilmo-cf-multiple-option__badge--warning'
				);
				badge.classList.add(
					'eilmo-cf-multiple-option__badge--' +
						(variation && ['success', 'warning'].includes(variation.badgeType)
							? variation.badgeType
							: 'primary')
				);
			}
		});
	}

	/**
	 * Disable attribute options that cannot complete a purchasable variation.
	 *
	 * @param {HTMLElement} card Product card.
	 */
	function updateAttributeAvailability(card) {
		const selected = getSelectedAttributes(card);
		const variations = getVariations(card).filter(function (variation) {
			return Boolean(variation.canPurchase);
		});

		card.querySelectorAll(SELECTORS.attributeChoice).forEach(function (button) {
			if (!(window.eilmoCfDom.isElement(button, 'BUTTON'))) {
				return;
			}

			const candidate = Object.assign({}, selected);
			const attribute = String(button.dataset.attribute || '');

			delete candidate[attribute];
			candidate[attribute] = String(button.dataset.value || '');

			button.disabled = !variations.some(function (variation) {
				return variationMatches(variation, candidate);
			});
		});
	}

	/**
	 * Update a card's selected attribute controls from an exact variation.
	 *
	 * @param {HTMLElement} card Product card.
	 * @param {Object} variation Variation.
	 */
	function updateAttributeChoiceStates(card, variation) {
		const attributes = variation.attributes && typeof variation.attributes === 'object'
			? variation.attributes
			: {};

		card.querySelectorAll(SELECTORS.attributeChoice).forEach(function (button) {
			const key = String(button.dataset.attribute || '');
			const variationValue = String(attributes[key] || '');

			if (variationValue === '') {
				return;
			}

			const selected = variationValue === String(button.dataset.value || '');
			button.classList.toggle('is-selected', selected);
			button.setAttribute('aria-pressed', selected ? 'true' : 'false');
		});
	}

	/**
	 * Update card-level price, saving, stock, badge, and image presentation.
	 *
	 * @param {HTMLElement} card Product card.
	 * @param {Object} variation Variation.
	 */
	function updatePresentation(card, variation) {
		const price = card.querySelector(SELECTORS.price);
		const saving = card.querySelector(SELECTORS.saving);
		const stock = card.querySelector(SELECTORS.stock);
		const saleBadge = card.querySelector(SELECTORS.saleBadge);
		const image = card.querySelector(SELECTORS.mainImage);

		if (window.eilmoCfDom.isElement(price)) {
			price.innerHTML = String(variation.priceHtml || '');
		}

		if (window.eilmoCfDom.isElement(saving)) {
			saving.innerHTML = String(variation.savingHtml || '');
			saving.hidden = !variation.savingHtml;
		}

		if (window.eilmoCfDom.isElement(stock)) {
			stock.innerHTML = String(variation.stockHtml || '');
		}

		if (window.eilmoCfDom.isElement(saleBadge)) {
			saleBadge.hidden = !variation.onSale;
		}

		if (window.eilmoCfDom.isElement(image, 'IMG') && variation.image) {
			image.src = String(variation.image);
			image.alt = String(variation.imageAlt || '');
		}
	}

	/**
	 * Set a card-local status message.
	 *
	 * @param {HTMLElement} card Product card.
	 * @param {string} message Message.
	 * @param {boolean} error Error state.
	 */
	function setMessage(card, message, error) {
		const element = card.querySelector(SELECTORS.message);

		if (!(window.eilmoCfDom.isElement(element))) {
			return;
		}

		element.textContent = String(message || '');
		element.classList.toggle('is-error', Boolean(error));
	}

	/**
	 * Get all selected authoritative order items.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 *
	 * @return {Array<Object>}
	 */
	function getSelectedItems(root) {
		const selected = [];

		root.querySelectorAll(SELECTORS.orderItem).forEach(function (item) {
			if (!(window.eilmoCfDom.isElement(item))) {
				return;
			}

			const input = getItemInput(item);
			const quantity = input ? getQuantity(input) : 0;

			if (quantity <= 0) {
				return;
			}

			selected.push({
				item: item,
				input: input,
				productId: toInteger(item.dataset.productId),
				variationId: toInteger(item.dataset.variationId),
				quantity: quantity,
				name: String(item.dataset.itemName || '').trim(),
				variation: String(item.dataset.itemVariation || '').trim(),
				image: String(item.dataset.itemImage || '').trim(),
				price: toAmount(item.dataset.price),
			});
		});

		return selected;
	}

	/**
	 * Get public, DOM-free selected item data.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 *
	 * @return {Array<Object>}
	 */
	function getPublicSelectedItems(root) {
		return getSelectedItems(root).map(function (entry) {
			return {
				productId: entry.productId,
				variationId: entry.variationId,
				quantity: entry.quantity,
			};
		});
	}

	/**
	 * Set one input value and retain it for direct-input comparisons.
	 *
	 * @param {HTMLInputElement} input Quantity input.
	 * @param {number} quantity Quantity.
	 * @param {Map<HTMLElement, HTMLInputElement>} changes Changed order items.
	 */
	function setInputValue(input, quantity, changes) {
		const previous = getPreviousQuantity(input);
		const normalized = normalizeQuantity(input, quantity);
		const item = input.closest(SELECTORS.orderItem);

		input.value = String(normalized);
		input.dataset.eilmoMultipleQuantity = String(normalized);

		if (window.eilmoCfDom.isElement(item) && previous !== normalized) {
			changes.set(item, input);
		}
	}

	/**
	 * Enforce parent-product Single selection.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 * @param {HTMLElement} currentCard Current product card.
	 * @param {Map<HTMLElement, HTMLInputElement>} changes Changed items.
	 */
	function enforceSingleProductSelection(root, currentCard, changes) {
		if (getProductSelectionMode(root) !== 'single') {
			return;
		}

		root.querySelectorAll(SELECTORS.card).forEach(function (card) {
			if (!(window.eilmoCfDom.isElement(card)) || card === currentCard) {
				return;
			}

			getOrderItems(card).forEach(function (item) {
				const input = getItemInput(item);

				if (input && getPreviousQuantity(input) > 0) {
					setInputValue(input, 0, changes);
				}
			});
		});
	}

	/**
	 * Enforce Single Variation selection inside one Variable product.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 * @param {HTMLElement} card Current product card.
	 * @param {HTMLElement} currentItem Current variation item.
	 * @param {Map<HTMLElement, HTMLInputElement>} changes Changed items.
	 */
	function enforceSingleVariationSelection(root, card, currentItem, changes) {
		if (
			card.dataset.productType !== 'variable' ||
			getVariationSelectionMode(root) !== 'single'
		) {
			return;
		}

		getOrderItems(card).forEach(function (item) {
			if (item === currentItem) {
				return;
			}

			const input = getItemInput(item);

			if (input && getPreviousQuantity(input) > 0) {
				setInputValue(input, 0, changes);
			}
		});
	}

	/**
	 * Synchronize a single parent-product card from authoritative quantities.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 * @param {HTMLElement} card Product card.
	 */
	function syncCard(root, card) {
		const selectedItems = getOrderItems(card).filter(isItemSelected);
		const selected = selectedItems.length > 0;

		card.classList.toggle('is-selected', selected);
		card.dataset.selected = selected ? 'yes' : 'no';

		card.querySelectorAll(SELECTORS.productToggle).forEach(function (button) {
			button.setAttribute('aria-pressed', selected ? 'true' : 'false');
		});

		getOrderItems(card).forEach(function (item) {
			const itemSelected = isItemSelected(item);
			const variationChoice = item.querySelector(SELECTORS.variationChoice);
			const quantity = item.querySelector(SELECTORS.quantity);

			item.classList.toggle('is-selected', itemSelected);

			if (window.eilmoCfDom.isElement(variationChoice, 'BUTTON')) {
				variationChoice.classList.toggle('is-selected', itemSelected);
				variationChoice.setAttribute('aria-pressed', itemSelected ? 'true' : 'false');
			}

			if (item.classList.contains('eilmo-cf-multiple-variation')) {
				if (window.eilmoCfDom.isElement(quantity)) {
					quantity.hidden = !itemSelected || !showsGridQuantity(root);
				}
			} else if (item.classList.contains('eilmo-cf-multiple-card__order-item')) {
				item.hidden = !itemSelected || !showsQuantity(root);
			}
		});

		if (selectedItems.length > 0 && card.dataset.productType === 'variable') {
			let activeId = toInteger(card.dataset.activeVariationId);
			const activeSelected = selectedItems.some(function (item) {
				return toInteger(item.dataset.variationId) === activeId;
			});

			if (!activeSelected) {
				activeId = toInteger(selectedItems[0].dataset.variationId);
			}

			const active = findVariation(card, activeId);

			if (active) {
				card.dataset.activeVariationId = String(activeId);
				updatePresentation(card, active);

				if (root.dataset.variationLayout === 'sections') {
					updateAttributeChoiceStates(card, active);
				}
			}
		}
	}

	/**
	 * Render the Multiple Products "Your Selected Items" list.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 */
	function renderSelectedItems(root) {
		const container = root.querySelector(SELECTORS.selectedItems);
		const list = root.querySelector(SELECTORS.selectedList);
		const count = root.querySelector(SELECTORS.selectedCount);
		const selected = getSelectedItems(root);

		if (window.eilmoCfDom.isElement(count)) {
			count.textContent = String(selected.length);
		}

		if (!(window.eilmoCfDom.isElement(container)) || !(window.eilmoCfDom.isElement(list))) {
			return;
		}

		container.hidden = root.dataset.showSelectedItems !== 'yes' || selected.length === 0;
		list.replaceChildren();

		selected.forEach(function (entry) {
			const row = document.createElement('div');
			row.className = 'eilmo-cf-multiple-selected__item';
			row.dataset.productId = String(entry.productId);
			row.dataset.variationId = String(entry.variationId);

			if (entry.image) {
				const image = document.createElement('img');
				image.className = 'eilmo-cf-multiple-selected__image';
				image.src = entry.image;
				image.alt = '';
				image.loading = 'lazy';
				row.appendChild(image);
			}

			const information = document.createElement('div');
			information.className = 'eilmo-cf-multiple-selected__information';

			const name = document.createElement('strong');
			name.className = 'eilmo-cf-multiple-selected__name';
			name.textContent = entry.name || 'Product';
			information.appendChild(name);

			if (entry.variation) {
				const variation = document.createElement('span');
				variation.className = 'eilmo-cf-multiple-selected__variation';
				variation.textContent = entry.variation;
				information.appendChild(variation);
			}

			if (entry.quantity > 1) {
				const quantity = document.createElement('span');
				quantity.className = 'eilmo-cf-multiple-selected__item-quantity';
				quantity.textContent = '× ' + String(entry.quantity);
				information.appendChild(quantity);
			}

			row.appendChild(information);

			const price = document.createElement('strong');
			price.className = 'eilmo-cf-multiple-selected__price';
			price.textContent = formatPrice(entry.price * entry.quantity);
			row.appendChild(price);

			if (allowsProductRemoval(root)) {
				const remove = document.createElement('button');
				remove.type = 'button';
				remove.className = 'eilmo-cf-multiple-selected__remove';
				remove.setAttribute('data-eilmo-multiple-remove', '');
				remove.setAttribute('aria-label', 'Remove ' + (entry.name || 'product'));
				remove.textContent = '×';
				row.appendChild(remove);
			}

			list.appendChild(row);
		});
	}

	/**
	 * Synchronize all cards and selected-item UI.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 */
	function syncRoot(root) {
		root.querySelectorAll(SELECTORS.card).forEach(function (card) {
			if (window.eilmoCfDom.isElement(card)) {
				syncCard(root, card);
			}
		});

		renderSelectedItems(root);
	}

	/**
	 * Dispatch shared quantity updates after the DOM reaches its final state.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 * @param {Map<HTMLElement, HTMLInputElement>} changes Changed items.
	 */
	function dispatchChanges(root, changes) {
		const checkout = getCheckout(root);
		const selectedItems = getPublicSelectedItems(root);

		changes.forEach(function (input, item) {
			item.dispatchEvent(new CustomEvent('eilmo:quantityChange', {
				bubbles: true,
				detail: {
					checkout: checkout,
					item: item,
					productId: toInteger(item.dataset.productId),
					variationId: toInteger(item.dataset.variationId),
					quantity: getQuantity(input),
					selectionMode: getProductSelectionMode(root),
					selectedItems: selectedItems,
				},
			}));
		});

		root.dispatchEvent(new CustomEvent('eilmo:multipleProductsChange', {
			bubbles: true,
			detail: {
				checkout: checkout,
				root: root,
				selectedItems: selectedItems,
				selectedProducts: Array.from(root.querySelectorAll(SELECTORS.card)).filter(function (card) {
					return window.eilmoCfDom.isElement(card) && isCardSelected(card);
				}).length,
				valid: root.dataset.requireProductSelection !== 'yes' || selectedItems.length > 0,
			},
		}));
	}

	/**
	 * Apply one authoritative quantity change.
	 *
	 * @param {HTMLInputElement} input Quantity input.
	 * @param {*} requested Requested quantity.
	 * @param {'control'|'input'|'selection'|'external'} source Change source.
	 */
	function setQuantity(input, requested, source) {
		const root = getRoot(input);
		const item = input.closest(SELECTORS.orderItem);
		const card = input.closest(SELECTORS.card);

		if (
			!root ||
			!(window.eilmoCfDom.isElement(item)) ||
			!(window.eilmoCfDom.isElement(card)) ||
			input.disabled ||
			item.dataset.purchasable === 'no'
		) {
			return;
		}

		const previous = getPreviousQuantity(input);
		let quantity = normalizeQuantity(input, requested);

		if (
			previous === 0 &&
			quantity > 0 &&
			(source === 'control' || source === 'input') &&
			!selectsOnQuantityChange(root) &&
			!isCardSelected(card)
		) {
			quantity = 0;
		}

		if (
			previous > 0 &&
			quantity === 0 &&
			!allowsProductRemoval(root) &&
			getOrderItems(card).filter(function (candidate) {
				const candidateInput = getItemInput(candidate);

				return Boolean(candidateInput && getPreviousQuantity(candidateInput) > 0);
			}).length <= 1
		) {
			quantity = 1;
		}

		const changes = new Map();

		if (quantity > 0) {
			enforceSingleProductSelection(root, card, changes);
			enforceSingleVariationSelection(root, card, item, changes);
		}

		setInputValue(input, quantity, changes);
		syncRoot(root);

		if (changes.size > 0) {
			dispatchChanges(root, changes);
		}
	}

	/**
	 * Apply an exact variation to one parent-product card.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 * @param {HTMLElement} card Product card.
	 * @param {number} variationId Variation ID.
	 * @param {boolean} select Add the variation to the order.
	 *
	 * @return {boolean}
	 */
	function applyVariation(root, card, variationId, select) {
		const variation = findVariation(card, variationId);

		if (!variation || !variation.canPurchase) {
			setMessage(card, 'This combination is currently unavailable.', true);
			return false;
		}

		card.dataset.activeVariationId = String(variationId);
		updatePresentation(card, variation);

		if (root.dataset.variationLayout === 'sections') {
			updateAttributeChoiceStates(card, variation);
			updateAttributeAvailability(card);
			updateAttributePricing(card);
		}

		setMessage(card, variation.label ? 'Selected: ' + variation.label : '', false);

		if (select) {
			const item = findOrderItem(card, variationId);
			const input = item ? getItemInput(item) : null;

			if (input) {
				setQuantity(input, Math.max(1, getPreviousQuantity(input)), 'selection');
			}
		}

		return true;
	}

	/**
	 * Select one Separate Attribute Sections option.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 * @param {HTMLElement} card Product card.
	 * @param {HTMLButtonElement} button Attribute option.
	 */
	function selectAttribute(root, card, button) {
		const group = button.closest(SELECTORS.attributeGroup);

		if (!(window.eilmoCfDom.isElement(group))) {
			return;
		}

		group.querySelectorAll(SELECTORS.attributeChoice).forEach(function (option) {
			const selected = option === button;
			option.classList.toggle('is-selected', selected);
			option.setAttribute('aria-pressed', selected ? 'true' : 'false');
		});

		const selected = getSelectedAttributes(card);
		const groupCount = card.querySelectorAll(SELECTORS.attributeGroup).length;

		updateAttributeAvailability(card);
		updateAttributePricing(card);

		if (Object.keys(selected).length < groupCount) {
			setMessage(card, 'Please select all options.', false);
			return;
		}

		const variation = getVariations(card).find(function (candidate) {
			return Boolean(candidate.canPurchase) && variationMatches(candidate, selected);
		});

		if (!variation) {
			setMessage(card, 'This combination is currently unavailable.', true);
			return;
		}

		applyVariation(root, card, toInteger(variation.id), true);
	}

	/**
	 * Clear every selected order item in one product card.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 * @param {HTMLElement} card Product card.
	 */
	function clearCard(root, card) {
		if (!allowsProductRemoval(root)) {
			return;
		}

		const changes = new Map();

		getOrderItems(card).forEach(function (item) {
			const input = getItemInput(item);

			if (input) {
				setInputValue(input, 0, changes);
			}
		});

		syncRoot(root);

		if (changes.size > 0) {
			dispatchChanges(root, changes);
		}
	}

	/**
	 * Select or remove one parent-product card.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 * @param {HTMLElement} card Product card.
	 */
	function toggleCard(root, card) {
		if (card.dataset.purchasable === 'no') {
			return;
		}

		if (isCardSelected(card)) {
			clearCard(root, card);
			return;
		}

		if (card.dataset.productType !== 'variable') {
			const item = getOrderItems(card)[0] || null;
			const input = item ? getItemInput(item) : null;

			if (input) {
				setQuantity(input, 1, 'selection');
			}

			return;
		}

		let variationId = toInteger(card.dataset.activeVariationId);

		if (variationId <= 0) {
			variationId = toInteger(card.dataset.defaultVariationId);
		}

		if (variationId <= 0 || !findVariation(card, variationId)) {
			const fallback = getVariations(card).find(function (variation) {
				return Boolean(variation.canPurchase);
			});
			variationId = fallback ? toInteger(fallback.id) : 0;
		}

		if (variationId > 0) {
			applyVariation(root, card, variationId, true);
		}
	}

	/**
	 * Clear all removable Multiple Products selections.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 */
	function clearAll(root) {
		if (!allowsProductRemoval(root)) {
			return;
		}

		const changes = new Map();

		root.querySelectorAll(SELECTORS.quantityInput).forEach(function (input) {
			if (window.eilmoCfDom.isElement(input, 'INPUT')) {
				setInputValue(input, 0, changes);
			}
		});

		syncRoot(root);

		if (changes.size > 0) {
			dispatchChanges(root, changes);
		}
	}

	/**
	 * Initialize one Multiple Products instance.
	 *
	 * @param {HTMLElement} root Multiple Products root.
	 */
	function initializeRoot(root) {
		if (initialized.has(root)) {
			return;
		}

		initialized.add(root);

		root.querySelectorAll(SELECTORS.card).forEach(function (card) {
			if (!(window.eilmoCfDom.isElement(card))) {
				return;
			}

			getOrderItems(card).forEach(function (item) {
				const input = getItemInput(item);

				if (input) {
					const normalized = normalizeQuantity(input, input.value);
					input.value = String(normalized);
					input.dataset.eilmoMultipleQuantity = String(normalized);
				}
			});

			if (card.dataset.productType !== 'variable') {
				return;
			}

			getVariations(card);

			const selectedItem = getOrderItems(card).find(isItemSelected);
			const selectedId = selectedItem ? toInteger(selectedItem.dataset.variationId) : 0;
			const defaultId = toInteger(card.dataset.defaultVariationId);
			const initialId = selectedId > 0 ? selectedId : defaultId;

			if (initialId > 0) {
				applyVariation(root, card, initialId, false);
			}

			if (root.dataset.variationLayout === 'sections') {
				updateAttributeAvailability(card);
				updateAttributePricing(card);
			}
		});

		syncRoot(root);
	}

	/**
	 * Initialize current Multiple Products instances.
	 *
	 * @param {ParentNode} context Search context.
	 */
	function initialize(context) {
		const scope = context && typeof context.querySelectorAll === 'function'
			? context
			: document;

		if (window.eilmoCfDom.isElement(scope) && scope.matches(SELECTORS.root)) {
			initializeRoot(scope);
		}

		scope.querySelectorAll(SELECTORS.root).forEach(function (root) {
			if (window.eilmoCfDom.isElement(root)) {
				initializeRoot(root);
			}
		});
	}

	/**
	 * Handle Multiple Products clicks.
	 *
	 * @param {MouseEvent} event Click event.
	 */
	function handleClick(event) {
		if (!(window.eilmoCfDom.isElement(event.target))) {
			return;
		}

		const remove = event.target.closest(SELECTORS.removeSelected);

		if (window.eilmoCfDom.isElement(remove, 'BUTTON')) {
			event.preventDefault();

			const root = getRoot(remove);
			const row = remove.closest('.eilmo-cf-multiple-selected__item');
			const productId = window.eilmoCfDom.isElement(row) ? toInteger(row.dataset.productId) : 0;
			const variationId = window.eilmoCfDom.isElement(row) ? toInteger(row.dataset.variationId) : 0;

			if (root) {
				const item = Array.from(root.querySelectorAll(SELECTORS.orderItem)).find(function (candidate) {
					return window.eilmoCfDom.isElement(candidate) &&
						toInteger(candidate.dataset.productId) === productId &&
						toInteger(candidate.dataset.variationId) === variationId;
				});
				const input = window.eilmoCfDom.isElement(item) ? getItemInput(item) : null;

				if (input) {
					setQuantity(input, 0, 'selection');
				}
			}

			return;
		}

		const clear = event.target.closest(SELECTORS.clearSelected);

		if (window.eilmoCfDom.isElement(clear, 'BUTTON')) {
			event.preventDefault();

			const root = getRoot(clear);

			if (root) {
				clearAll(root);
			}

			return;
		}

		const minus = event.target.closest(SELECTORS.quantityMinus);
		const plus = event.target.closest(SELECTORS.quantityPlus);

		if (window.eilmoCfDom.isElement(minus, 'BUTTON') || window.eilmoCfDom.isElement(plus, 'BUTTON')) {
			event.preventDefault();

			const button = window.eilmoCfDom.isElement(minus, 'BUTTON') ? minus : plus;
			const quantity = button.closest(SELECTORS.quantity);
			const input = quantity ? quantity.querySelector(SELECTORS.quantityInput) : null;

			if (window.eilmoCfDom.isElement(input, 'INPUT')) {
				setQuantity(
					input,
					getPreviousQuantity(input) + (window.eilmoCfDom.isElement(minus, 'BUTTON') ? -1 : 1),
					'control'
				);
			}

			return;
		}

		const variationChoice = event.target.closest(SELECTORS.variationChoice);

		if (window.eilmoCfDom.isElement(variationChoice, 'BUTTON')) {
			event.preventDefault();

			if (variationChoice.disabled) {
				return;
			}

			const root = getRoot(variationChoice);
			const card = variationChoice.closest(SELECTORS.card);
			const item = variationChoice.closest(SELECTORS.orderItem);

			if (!root || !(window.eilmoCfDom.isElement(card)) || !(window.eilmoCfDom.isElement(item))) {
				return;
			}

			const variationId = toInteger(variationChoice.dataset.variationId);
			const input = getItemInput(item);

			if (
				input &&
				getVariationSelectionMode(root) === 'multiple' &&
				getPreviousQuantity(input) > 0
			) {
				setQuantity(input, 0, 'selection');
				return;
			}

			if (variationId > 0) {
				applyVariation(root, card, variationId, true);
			}

			return;
		}

		const attributeChoice = event.target.closest(SELECTORS.attributeChoice);

		if (window.eilmoCfDom.isElement(attributeChoice, 'BUTTON')) {
			event.preventDefault();

			if (attributeChoice.disabled) {
				return;
			}

			const root = getRoot(attributeChoice);
			const card = attributeChoice.closest(SELECTORS.card);

			if (root && window.eilmoCfDom.isElement(card)) {
				selectAttribute(root, card, attributeChoice);
			}

			return;
		}

		const productToggle = event.target.closest(SELECTORS.productToggle);

		if (window.eilmoCfDom.isElement(productToggle, 'BUTTON')) {
			event.preventDefault();

			if (productToggle.disabled) {
				return;
			}

			const root = getRoot(productToggle);
			const card = productToggle.closest(SELECTORS.card);

			if (root && window.eilmoCfDom.isElement(card)) {
				toggleCard(root, card);
			}
		}
	}

	/**
	 * Handle direct Multiple quantity input changes.
	 *
	 * @param {Event} event Change event.
	 */
	function handleInput(event) {
		if (
			!(window.eilmoCfDom.isElement(event.target, 'INPUT')) ||
			!event.target.matches(SELECTORS.quantityInput)
		) {
			return;
		}

		setQuantity(event.target, event.target.value, 'input');
	}

	document.addEventListener('click', handleClick);
	document.addEventListener('change', handleInput);

	document.addEventListener('eilmo:setQuantity', function (event) {
		if (!event.detail || !(window.eilmoCfDom.isElement(event.target))) {
			return;
		}

		const checkout = event.target.closest(SELECTORS.checkout);

		if (!(window.eilmoCfDom.isElement(checkout))) {
			return;
		}

		const root = checkout.querySelector(SELECTORS.root);

		if (!(window.eilmoCfDom.isElement(root))) {
			return;
		}

		const productId = toInteger(event.detail.productId);
		const variationId = toInteger(event.detail.variationId);
		const item = Array.from(root.querySelectorAll(SELECTORS.orderItem)).find(function (candidate) {
			return window.eilmoCfDom.isElement(candidate) &&
				toInteger(candidate.dataset.productId) === productId &&
				toInteger(candidate.dataset.variationId) === variationId;
		});
		const input = window.eilmoCfDom.isElement(item) ? getItemInput(item) : null;

		if (!input) {
			return;
		}

		const delta = Number.parseInt(String(event.detail.delta || '0'), 10) || 0;
		const requested = Number.isFinite(Number(event.detail.quantity))
			? Number(event.detail.quantity)
			: getPreviousQuantity(input) + delta;

		setQuantity(input, requested, 'external');
	});

	const observer = new MutationObserver(function (mutations) {
		mutations.forEach(function (mutation) {
			mutation.addedNodes.forEach(function (node) {
				if (window.eilmoCfDom.isElement(node)) {
					initialize(node);
				}
			});
		});
	});

	window.eilmoCfMultipleProducts = {
		refresh: function (context) {
			const root = window.eilmoCfDom.isElement(context) && context.matches(SELECTORS.root)
				? context
				: window.eilmoCfDom.isElement(context)
					? context.querySelector(SELECTORS.root)
					: null;

			if (window.eilmoCfDom.isElement(root)) {
				initializeRoot(root);
				syncRoot(root);
			}
		},
		getSelectedItems: function (context) {
			const root = window.eilmoCfDom.isElement(context) && context.matches(SELECTORS.root)
				? context
				: window.eilmoCfDom.isElement(context)
					? context.querySelector(SELECTORS.root)
					: null;

			return window.eilmoCfDom.isElement(root) ? getPublicSelectedItems(root) : [];
		},
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initialize(document);
			observer.observe(document.documentElement, { childList: true, subtree: true });
		});
	} else {
		initialize(document);
		observer.observe(document.documentElement, { childList: true, subtree: true });
	}
})();
