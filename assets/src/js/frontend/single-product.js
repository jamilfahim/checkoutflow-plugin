/**
 * Eilmo Checkout Flow - consolidated Single Product interactions.
 *
 * WooCommerce product and variation IDs remain authoritative. This module only
 * coordinates compact option controls with the existing Eilmo quantity,
 * summary and order-submission contracts.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		root: '[data-eilmo-single-product]',
		variationData: '[data-eilmo-single-variation-data]',
		variationChoice: '[data-eilmo-single-variation-choice]',
		attributeChoice: '[data-eilmo-single-attribute-choice]',
		attributeGroup: '[data-eilmo-single-attribute-group]',
		optionPrice: '[data-eilmo-single-option-price]',
		optionRegularPrice: '[data-eilmo-single-option-regular-price]',
		optionSaving: '[data-eilmo-single-option-saving]',
		optionBadge: '[data-eilmo-single-option-badge]',
		variationItem: '[data-eilmo-item][data-variation-id]',
		quantityInput: '[data-eilmo-quantity-input]',
		price: '[data-eilmo-single-price]',
		savings: '[data-eilmo-single-savings]',
		stock: '[data-eilmo-single-stock]',
		saleBadge: '[data-eilmo-single-sale-badge]',
		customBadge: '[data-eilmo-single-custom-badge]',
		mainImage: '[data-eilmo-single-main-image]',
		mediaThumbnail: '[data-eilmo-single-media-thumbnail]',
		message: '[data-eilmo-single-selection-message]',
		selectedItems: '[data-eilmo-single-selected-items]',
		selectedCount: '[data-eilmo-single-selected-count]',
		clearSelected: '[data-eilmo-single-clear]',
		removeVariation: '[data-eilmo-single-remove-variation]',
		includeSimple: '[data-eilmo-single-include-simple]',
	};

	const initialized = new WeakSet();

	/**
	 * Parse server-prepared variation presentation data.
	 *
	 * @param {HTMLElement} root Single Product root.
	 *
	 * @return {Array<Object>}
	 */
	function getVariations(root) {
		if (Array.isArray(root.eilmoVariations)) {
			return root.eilmoVariations;
		}

		const script = root.querySelector(SELECTORS.variationData);
		let variations = [];

		if (script) {
			try {
				const parsed = JSON.parse(script.textContent || '[]');
				variations = Array.isArray(parsed) ? parsed : [];
			} catch (error) {
				variations = [];
			}
		}

		root.eilmoVariations = variations;

		return variations;
	}

	/**
	 * Resolve one variation by ID.
	 *
	 * @param {HTMLElement} root Single Product root.
	 * @param {number} variationId Variation ID.
	 *
	 * @return {Object|null}
	 */
	function findVariation(root, variationId) {
		return getVariations(root).find(function (variation) {
			return Number.parseInt(String(variation.id || 0), 10) === variationId;
		}) || null;
	}

	/**
	 * Multiple variation selection is intentionally limited to Compact Grid.
	 * This runtime guard also protects old shortcode/widget configurations.
	 *
	 * @param {HTMLElement} root Single Product root.
	 *
	 * @return {'single'|'multiple'}
	 */
	function getSelectionMode(root) {
		return root.dataset.variationLayout === 'grid' &&
			root.dataset.variationSelection === 'multiple'
			? 'multiple'
			: 'single';
	}

	/**
	 * Find the order item belonging to one exact variation.
	 *
	 * @param {HTMLElement} root Single Product root.
	 * @param {number} variationId Variation ID.
	 *
	 * @return {HTMLElement|null}
	 */
	function findVariationItem(root, variationId) {
		return Array.from(root.querySelectorAll(SELECTORS.variationItem)).find(function (candidate) {
			return Number.parseInt(candidate.dataset.variationId || '0', 10) === variationId;
		}) || null;
	}

	/**
	 * Get selected attribute values from the section controls.
	 *
	 * @param {HTMLElement} root Single Product root.
	 *
	 * @return {Object<string, string>}
	 */
	function getSelectedAttributes(root) {
		const attributes = {};

		root.querySelectorAll(SELECTORS.attributeChoice + '.is-selected').forEach(function (button) {
			const key = String(button.dataset.attribute || '');
			const value = String(button.dataset.value || '');

			if (key) {
				attributes[key] = value;
			}
		});

		return attributes;
	}

	/**
	 * Test a variation against a complete or partial attribute selection.
	 * Empty WooCommerce variation values act as wildcards.
	 *
	 * @param {Object} variation Variation data.
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
	 * Resolve the exact variation represented by an attribute option in the
	 * context of every other currently selected attribute.
	 *
	 * @param {HTMLElement} root Single Product root.
	 * @param {HTMLButtonElement} button Attribute option.
	 *
	 * @return {Object|null}
	 */
	function findContextualVariation(root, button) {
		const selected = getSelectedAttributes(root);
		const attribute = String(button.dataset.attribute || '');
		const value = String(button.dataset.value || '');

		delete selected[attribute];
		selected[attribute] = value;

		const matches = getVariations(root).filter(function (variation) {
			return Boolean(variation.canPurchase) && variationMatches(variation, selected);
		});

		if (matches.length === 0) {
			return null;
		}

		const activeVariationId = Number.parseInt(root.dataset.activeVariationId || '0', 10);
		const activeMatch = matches.find(function (variation) {
			return Number.parseInt(String(variation.id || 0), 10) === activeVariationId;
		});

		return activeMatch || matches[0];
	}

	/**
	 * Update Text/Image Grid prices from current attribute context.
	 * Ranges are never shown: every option uses one exact matching variation.
	 *
	 * @param {HTMLElement} root Single Product root.
	 */
	function updateAttributePricing(root) {
		root.querySelectorAll(SELECTORS.attributeChoice).forEach(function (option) {
			if (!(window.eilmoCfDom.isElement(option, 'BUTTON'))) {
				return;
			}

			const variation = findContextualVariation(root, option);
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
				const badgeVisible = Boolean(groupAllowsBadge && variation && variation.badgeEnabled && variation.badgeText);

				badge.textContent = badgeVisible ? String(variation.badgeText || '') : '';
				badge.hidden = !badgeVisible;
				option.classList.toggle('has-badge', badgeVisible);
				badge.classList.remove(
					'eilmo-cf-single-product__option-badge--primary',
					'eilmo-cf-single-product__option-badge--success',
					'eilmo-cf-single-product__option-badge--warning'
				);
				badge.classList.add(
					'eilmo-cf-single-product__option-badge--' +
						(variation && ['success', 'warning'].includes(variation.badgeType) ? variation.badgeType : 'primary')
				);
			}
		});
	}

	/**
	 * Update the customer-facing selection message.
	 *
	 * @param {HTMLElement} root Single Product root.
	 * @param {string} message Message.
	 * @param {boolean} error Error state.
	 */
	function setMessage(root, message, error) {
		const element = root.querySelector(SELECTORS.message);

		if (!element) {
			return;
		}

		element.textContent = message;
		element.classList.toggle('is-error', Boolean(error));
	}

	/**
	 * Update price, stock, badges and the main image for the selected variation.
	 *
	 * @param {HTMLElement} root Single Product root.
	 * @param {Object} variation Variation data.
	 */
	function updatePresentation(root, variation) {
		const price = root.querySelector(SELECTORS.price);
		const stock = root.querySelector(SELECTORS.stock);
		const savings = root.querySelector(SELECTORS.savings);
		const saleBadge = root.querySelector(SELECTORS.saleBadge);
		const customBadge = root.querySelector(SELECTORS.customBadge);
		const mainImage = root.querySelector(SELECTORS.mainImage);

		if (price) {
			price.innerHTML = String(variation.priceHtml || '');
		}

		if (stock) {
			stock.innerHTML = String(variation.stockHtml || '');
		}

		if (savings) {
			savings.innerHTML = String(variation.savingHtml || '');
			savings.hidden = !variation.savingHtml;
		}

		if (saleBadge) {
			saleBadge.hidden = !variation.onSale;
		}

		if (customBadge) {
			customBadge.hidden = !variation.badgeEnabled || !variation.badgeText;
			customBadge.textContent = String(variation.badgeText || '');
			customBadge.classList.remove(
				'eilmo-cf-single-product__badge--primary',
				'eilmo-cf-single-product__badge--success',
				'eilmo-cf-single-product__badge--warning'
			);
			customBadge.classList.add(
				'eilmo-cf-single-product__badge--' +
					(['success', 'warning'].includes(variation.badgeType) ? variation.badgeType : 'primary')
			);
		}

		if (mainImage && variation.image) {
			mainImage.src = String(variation.image);
			mainImage.alt = String(variation.imageAlt || '');
		}
	}

	/**
	 * Synchronize the active attribute-section state.
	 *
	 * Exact Compact Grid selected states are synchronized separately from the
	 * authoritative order quantities, so every selected card stays highlighted.
	 *
	 * @param {HTMLElement} root Single Product root.
	 * @param {Object} variation Variation data.
	 */
	function updateChoiceStates(root, variation) {
		const attributes = variation.attributes && typeof variation.attributes === 'object'
			? variation.attributes
			: {};

		root.querySelectorAll(SELECTORS.attributeChoice).forEach(function (button) {
			const key = String(button.dataset.attribute || '');
			const variationValue = String(attributes[key] || '');

			if (variationValue === '') {
				return;
			}

			const selected = variationValue !== '' && variationValue === String(button.dataset.value || '');

			button.classList.toggle('is-selected', selected);
			button.setAttribute('aria-pressed', selected ? 'true' : 'false');
		});
	}

	/**
	 * Synchronize Compact Grid cards from actual selected-item quantities.
	 *
	 * @param {HTMLElement} root Single Product root.
	 * @param {Set<number>} selectedIds Selected variation IDs.
	 */
	function syncVariationChoiceStates(root, selectedIds) {
		const multiple = getSelectionMode(root) === 'multiple';

		root.querySelectorAll(SELECTORS.variationChoice).forEach(function (button) {
			const variationId = Number.parseInt(button.dataset.variationId || '0', 10);
			const selected = selectedIds.has(variationId);

			button.classList.toggle('is-selected', selected);
			button.setAttribute('aria-pressed', selected ? 'true' : 'false');

			if (multiple && selected) {
				button.setAttribute('title', root.dataset.multipleRemoveLabel || 'Click again to remove');
			} else {
				button.removeAttribute('title');
			}
		});
	}

	/**
	 * Clear active/default option presentation after the selected order is empty.
	 *
	 * @param {HTMLElement} root Single Product root.
	 */
	function clearEmptySelectionState(root) {
		delete root.dataset.activeVariationId;

		root.querySelectorAll(SELECTORS.attributeChoice).forEach(function (button) {
			button.classList.remove('is-selected');
			button.setAttribute('aria-pressed', 'false');
		});

		updateAttributeAvailability(root);
		updateAttributePricing(root);
		setMessage(root, '', false);
	}

	/**
	 * Synchronize the clean Selected Items list with authoritative quantities.
	 *
	 * @param {HTMLElement} root Single Product root.
	 * @param {boolean} clearEmptyState Clear active/default UI when no item remains.
	 */
	function updateSelectedItems(root, clearEmptyState) {
		const container = root.querySelector(SELECTORS.selectedItems);
		const selectedIds = new Set();
		let count = 0;

		root.querySelectorAll(SELECTORS.variationItem).forEach(function (item) {
			const input = item.querySelector(SELECTORS.quantityInput);
			const selected = window.eilmoCfDom.isElement(input, 'INPUT') &&
				Number.parseInt(input.value || '0', 10) > 0;

			item.hidden = !selected;
			item.classList.toggle('is-selected-item', selected);

			if (selected) {
				selectedIds.add(Number.parseInt(item.dataset.variationId || '0', 10));
				count += 1;
			}
		});

		syncVariationChoiceStates(root, selectedIds);

		if (container) {
			container.hidden = count === 0 || root.dataset.showSelectedItems !== 'yes';
		}

		const countElement = root.querySelector(SELECTORS.selectedCount);

		if (countElement) {
			countElement.textContent = String(count);
		}

		if (count === 0 && clearEmptyState) {
			clearEmptySelectionState(root);
			return;
		}

		const activeVariationId = Number.parseInt(root.dataset.activeVariationId || '0', 10);

		if (count > 0 && !selectedIds.has(activeVariationId)) {
			const fallbackVariation = findVariation(root, Array.from(selectedIds)[0]);

			if (fallbackVariation) {
				root.dataset.activeVariationId = String(fallbackVariation.id || '');
				updateChoiceStates(root, fallbackVariation);
				updatePresentation(root, fallbackVariation);
			}
		}

		if (count > 0 && getSelectionMode(root) === 'multiple') {
			if (count === 1) {
				const variation = findVariation(root, Array.from(selectedIds)[0]);
				setMessage(root, variation && variation.label ? window.eilmoCfLanguage.copy(root.closest('[data-eilmo-checkout]'), 'Selected:') + ' ' + variation.label : '', false);
			} else {
				setMessage(root, String(count) + ' variations selected.', false);
			}
		}
	}

	/**
	 * Dispatch an authoritative quantity update through the existing module.
	 *
	 * @param {HTMLElement} root Single Product root.
	 * @param {number} variationId Variation ID.
	 * @param {number} quantity Quantity.
	 */
	function setVariationQuantity(root, variationId, quantity) {
		root.dispatchEvent(new CustomEvent('eilmo:setQuantity', {
			bubbles: true,
			detail: {
				productId: Number.parseInt(root.dataset.productId || '0', 10),
				variationId: variationId,
				quantity: quantity,
			},
		}));
	}

	/**
	 * Route selection through the existing quantity module.
	 *
	 * @param {HTMLElement} root Single Product root.
	 * @param {number} variationId Variation ID.
	 */
	function selectForOrder(root, variationId) {
		const item = findVariationItem(root, variationId);

		if (!item) {
			return;
		}

		const input = item.querySelector(SELECTORS.quantityInput);
		const current = input ? Number.parseInt(input.value || '0', 10) : 0;
		let nextQuantity = current > 0 ? current : 1;
        if (getSelectionMode(root) === 'single') {
            const previous = Array.from(root.querySelectorAll(SELECTORS.quantityInput)).find(field => Number(field.value) > 0);
            if (previous) nextQuantity = Math.max(1, Number(previous.value));
            if (input && Number(input.max) > 0) nextQuantity = Math.min(nextQuantity, Number(input.max));
        }

		if (getSelectionMode(root) === 'single') {
			root.querySelectorAll(SELECTORS.variationItem).forEach(function (candidate) {
				const candidateId = Number.parseInt(candidate.dataset.variationId || '0', 10);
				const candidateInput = candidate.querySelector(SELECTORS.quantityInput);

				if (
					candidateId > 0 &&
					candidateId !== variationId &&
					window.eilmoCfDom.isElement(candidateInput, 'INPUT') &&
					Number.parseInt(candidateInput.value || '0', 10) > 0
				) {
					setVariationQuantity(root, candidateId, 0);
				}
			});
		}

		setVariationQuantity(root, variationId, nextQuantity);
	}

	/**
	 * Apply one exact variation to the consolidated UI.
	 *
	 * @param {HTMLElement} root Single Product root.
	 * @param {number} variationId Variation ID.
	 * @param {boolean} select Whether to add/select the item.
	 *
	 * @return {boolean}
	 */
	function applyVariation(root, variationId, select) {
		const variation = findVariation(root, variationId);

		if (!variation || !variation.canPurchase) {
			setMessage(root, 'This combination is currently unavailable.', true);
			return false;
		}

		root.dataset.activeVariationId = String(variationId);
		updateChoiceStates(root, variation);
		updatePresentation(root, variation);
		updateAttributePricing(root);
		setMessage(root, variation.label ? window.eilmoCfLanguage.copy(root.closest('[data-eilmo-checkout]'), 'Selected:') + ' ' + variation.label : '', false);

		if (select) {
			selectForOrder(root, variationId);
		}

		return true;
	}

	/**
	 * Disable attribute options that cannot complete a purchasable variation.
	 *
	 * @param {HTMLElement} root Single Product root.
	 */
	function updateAttributeAvailability(root) {
		const selected = getSelectedAttributes(root);
		const variations = getVariations(root).filter(function (variation) {
			return Boolean(variation.canPurchase);
		});

		root.querySelectorAll(SELECTORS.attributeChoice).forEach(function (button) {
			const candidate = Object.assign({}, selected);
			const attribute = String(button.dataset.attribute || '');

			delete candidate[attribute];
			candidate[attribute] = String(button.dataset.value || '');
			const available = variations.some(function (variation) {
				return variationMatches(variation, candidate);
			});

			button.disabled = !available;
		});
	}

	/**
	 * Select one option within an attribute section and resolve its variation.
	 *
	 * @param {HTMLElement} root Single Product root.
	 * @param {HTMLButtonElement} button Option button.
	 */
	function selectAttribute(root, button) {
		const group = button.closest(SELECTORS.attributeGroup);

		if (!group) {
			return;
		}

		group.querySelectorAll(SELECTORS.attributeChoice).forEach(function (option) {
			const selected = option === button;
			option.classList.toggle('is-selected', selected);
			option.setAttribute('aria-pressed', selected ? 'true' : 'false');
		});

		const selected = getSelectedAttributes(root);
		const groupCount = root.querySelectorAll(SELECTORS.attributeGroup).length;

		updateAttributeAvailability(root);
		updateAttributePricing(root);

		if (Object.keys(selected).length < groupCount) {
			setMessage(root, '', false);
			return;
		}

		const match = getVariations(root).find(function (variation) {
			return Boolean(variation.canPurchase) && variationMatches(variation, selected);
		});

		if (!match) {
			setMessage(root, 'This combination is currently unavailable.', true);
			return;
		}

		applyVariation(root, Number.parseInt(String(match.id || 0), 10), true);
	}

	/**
	 * Initialize one Single Product instance.
	 *
	 * @param {HTMLElement} root Single Product root.
	 */
	function initializeRoot(root) {
		if (initialized.has(root)) {
			return;
		}

		initialized.add(root);

		if (root.dataset.productType !== 'variable') {
			const includeSimple = root.querySelector(SELECTORS.includeSimple);
			const input = root.querySelector(SELECTORS.quantityInput);

			if (includeSimple && window.eilmoCfDom.isElement(input, 'INPUT')) {
				includeSimple.setAttribute(
					'aria-pressed',
					Number.parseInt(input.value || '0', 10) > 0 ? 'true' : 'false'
				);
			}

			return;
		}

		getVariations(root);

		const defaultVariationId = Number.parseInt(root.dataset.defaultVariationId || '0', 10);

		if (defaultVariationId > 0) {
			applyVariation(root, defaultVariationId, false);
		}

		updateAttributeAvailability(root);
		updateAttributePricing(root);
		updateSelectedItems(root, false);
	}

	/**
	 * Initialize all current Single Product instances.
	 *
	 * @param {ParentNode} context Search context.
	 */
	function initialize(context) {
		const scope = context && typeof context.querySelectorAll === 'function' ? context : document;

		if (window.eilmoCfDom.isElement(scope) && scope.matches(SELECTORS.root)) {
			initializeRoot(scope);
		}

		scope.querySelectorAll(SELECTORS.root).forEach(function (root) {
			initializeRoot(root);
		});
	}

	document.addEventListener('click', function (event) {
		if (!(window.eilmoCfDom.isElement(event.target))) {
			return;
		}

		const removeButton = event.target.closest(SELECTORS.removeVariation);
		const includeSimple = event.target.closest(SELECTORS.includeSimple);

		if (window.eilmoCfDom.isElement(includeSimple, 'BUTTON')) {
			event.preventDefault();

			const root = includeSimple.closest(SELECTORS.root);
			const input = root ? root.querySelector(SELECTORS.quantityInput) : null;
			const current = window.eilmoCfDom.isElement(input, 'INPUT')
				? Number.parseInt(input.value || '0', 10)
				: 0;

			if (root) {
				setVariationQuantity(root, 0, current > 0 ? 0 : 1);
			}

			return;
		}

		if (window.eilmoCfDom.isElement(removeButton, 'BUTTON')) {
			event.preventDefault();

			const root = removeButton.closest(SELECTORS.root);
			const variationId = Number.parseInt(removeButton.dataset.variationId || '0', 10);

			if (root && variationId > 0) {
				setVariationQuantity(root, variationId, 0);
				updateSelectedItems(root, true);
			}

			return;
		}

		const clearButton = event.target.closest(SELECTORS.clearSelected);

		if (window.eilmoCfDom.isElement(clearButton, 'BUTTON')) {
			event.preventDefault();

			const root = clearButton.closest(SELECTORS.root);

			if (root) {
				root.querySelectorAll(SELECTORS.variationItem).forEach(function (item) {
					const variationId = Number.parseInt(item.dataset.variationId || '0', 10);
					const input = item.querySelector(SELECTORS.quantityInput);

					if (variationId > 0 && window.eilmoCfDom.isElement(input, 'INPUT') && Number.parseInt(input.value || '0', 10) > 0) {
						setVariationQuantity(root, variationId, 0);
					}
				});

				updateSelectedItems(root, true);
			}

			return;
		}

		const variationChoice = event.target.closest(SELECTORS.variationChoice);

		if (window.eilmoCfDom.isElement(variationChoice, 'BUTTON')) {
			event.preventDefault();

			if (variationChoice.disabled) {
				return;
			}

			const root = variationChoice.closest(SELECTORS.root);
			const variationId = Number.parseInt(variationChoice.dataset.variationId || '0', 10);

			if (root && variationId > 0) {
				const item = findVariationItem(root, variationId);
				const input = item ? item.querySelector(SELECTORS.quantityInput) : null;
				const selected = window.eilmoCfDom.isElement(input, 'INPUT') &&
					Number.parseInt(input.value || '0', 10) > 0;

				if (getSelectionMode(root) === 'multiple' && selected) {
					setVariationQuantity(root, variationId, 0);
					updateSelectedItems(root, true);
					return;
				}

				applyVariation(root, variationId, true);
			}

			return;
		}

		const attributeChoice = event.target.closest(SELECTORS.attributeChoice);

		if (window.eilmoCfDom.isElement(attributeChoice, 'BUTTON')) {
			event.preventDefault();

			if (attributeChoice.disabled) {
				return;
			}

			const root = attributeChoice.closest(SELECTORS.root);

			if (root) {
				selectAttribute(root, attributeChoice);
			}

			return;
		}

		const thumbnail = event.target.closest(SELECTORS.mediaThumbnail);

		if (window.eilmoCfDom.isElement(thumbnail, 'BUTTON')) {
			event.preventDefault();

			const root = thumbnail.closest(SELECTORS.root);
			const image = root ? root.querySelector(SELECTORS.mainImage) : null;

			if (!root || !image) {
				return;
			}

			image.src = String(thumbnail.dataset.image || '');
			image.alt = String(thumbnail.dataset.alt || '');

			root.querySelectorAll(SELECTORS.mediaThumbnail).forEach(function (button) {
				button.classList.toggle('is-active', button === thumbnail);
			});
		}
	});

	document.addEventListener('eilmo:quantityChange', function (event) {
		if (!event.detail || !(window.eilmoCfDom.isElement(event.detail.item))) {
			return;
		}

		const root = event.detail.item.closest(SELECTORS.root);
		const variationId = Number.parseInt(String(event.detail.variationId || 0), 10);

		if (root && variationId > 0) {
			if (Number(event.detail.quantity || 0) > 0) {
				applyVariation(root, variationId, false);
			}

			updateSelectedItems(root, true);
		} else if (root && root.dataset.productType === 'simple') {
			const includeSimple = root.querySelector(SELECTORS.includeSimple);

			if (includeSimple) {
				includeSimple.setAttribute(
					'aria-pressed',
					Number(event.detail.quantity || 0) > 0 ? 'true' : 'false'
				);
			}
		}
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

	window.eilmoCfSingleProduct = {
		refresh: initialize,
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
