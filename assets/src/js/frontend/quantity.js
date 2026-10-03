/**
 * Eilmo Checkout Flow quantity controls.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		checkout: '[data-eilmo-checkout]',
		item: '[data-eilmo-item]',
		quantity: '[data-eilmo-quantity]',
		input: '[data-eilmo-quantity-input]',
		minus: '[data-eilmo-quantity-minus]',
		plus: '[data-eilmo-quantity-plus]',
		select: '[data-eilmo-select-item]',
	};

	/**
	 * Get numeric input value.
	 *
	 * @param {HTMLInputElement} input Quantity input.
	 *
	 * @return {number}
	 */
	function getValue(input) {
		const value = parseInt(input.value, 10);

		return Number.isNaN(value) ? 0 : value;
	}

	/**
	 * Get maximum allowed quantity.
	 *
	 * @param {HTMLInputElement} input Quantity input.
	 *
	 * @return {number|null}
	 */
	function getMax(input) {
		if (!input.hasAttribute('max')) {
			return null;
		}

		const max = parseInt(input.getAttribute('max'), 10);

		return Number.isNaN(max) ? null : max;
	}

	/**
	 * Normalize quantity.
	 *
	 * @param {HTMLInputElement} input Quantity input.
	 * @param {number} value Quantity.
	 *
	 * @return {number}
	 */
	function normalizeQuantity(input, value) {
		let quantity = Math.max(0, parseInt(value, 10) || 0);
		const max = getMax(input);

		if (max !== null) {
			quantity = Math.min(quantity, max);
		}

		return quantity;
	}

	/**
	 * Get checkout wrapper.
	 *
	 * @param {HTMLElement} element Element.
	 *
	 * @return {HTMLElement|null}
	 */
	function getCheckout(element) {
		return element.closest(SELECTORS.checkout);
	}

	/**
	 * Get item card.
	 *
	 * @param {HTMLElement} element Element.
	 *
	 * @return {HTMLElement|null}
	 */
	function getItem(element) {
		return element.closest(SELECTORS.item);
	}

	/**
	 * Get checkout selection mode.
	 *
	 * @param {HTMLElement|null} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getSelectionMode(checkout) {
		if (!checkout) {
			return 'single';
		}

		return checkout.dataset.selection === 'multiple'
			? 'multiple'
			: 'single';
	}


	/**
	 * Whether visible quantity controls are enabled globally for product cards.
	 *
	 * @param {HTMLElement|null} checkout Checkout wrapper.
	 *
	 * @return {boolean}
	 */
	function quantityControlsEnabled(checkout) {
		return Boolean(
			checkout &&
			checkout.dataset.showQuantity === 'yes'
		);
	}

	/**
	 * Whether quantity controls should appear only after selection.
	 * This setting applies to both Separate Cards and Grouped Grid layouts.
	 *
	 * @param {HTMLElement|null} checkout Checkout wrapper.
	 *
	 * @return {boolean}
	 */
	function quantityAfterSelectEnabled(checkout) {
		return Boolean(
			quantityControlsEnabled(checkout) &&
			checkout &&
			checkout.dataset.quantityAfterSelect === 'yes'
		);
	}

	/**
	 * Defensive frontend presentation guard for stale/cached markup.
	 * Plugin Settings remain authoritative even when an older template has
	 * visible quantity or Select controls in its HTML.
	 *
	 * @param {HTMLElement} checkout Checkout wrapper.
	 */
	function enforceQuantityPresentation(checkout) {
		const quantityEnabled = quantityControlsEnabled(checkout);
		const afterSelect = quantityAfterSelectEnabled(checkout);
		const showSelectText = checkout.dataset.showSelectText !== 'no';

		checkout.querySelectorAll(SELECTORS.item).forEach(function (item) {
			const input = item.querySelector(SELECTORS.input);
			const quantity = item.querySelector(SELECTORS.quantity);
			const selectButton = item.querySelector(SELECTORS.select);
			const selected = window.eilmoCfDom.isElement(input, 'INPUT') && getValue(input) > 0;
			const forceQuantity = item.hasAttribute('data-eilmo-force-quantity');

			if (window.eilmoCfDom.isElement(quantity)) {
				const shouldShowQuantity = forceQuantity || (quantityEnabled && (!afterSelect || selected));
				quantity.hidden = !shouldShowQuantity;
				quantity.setAttribute('aria-hidden', shouldShowQuantity ? 'false' : 'true');
				if (shouldShowQuantity) {
					quantity.style.removeProperty('display');
				} else {
					quantity.style.setProperty('display', 'none');
				}
			}

			if (window.eilmoCfDom.isElement(selectButton, 'BUTTON')) {
				const shouldShowSelect = showSelectText && (!quantityEnabled || afterSelect) && !(afterSelect && selected);
				selectButton.hidden = !shouldShowSelect;
				if (shouldShowSelect) {
					selectButton.style.removeProperty('display');
				} else {
					selectButton.style.setProperty('display', 'none');
				}
			}
		});
	}

	/**
	 * Update selected card state.
	 *
	 * @param {HTMLElement} item Product/variation card.
	 */
	function updateSelectedState(item) {
		const input = item.querySelector(SELECTORS.input);

		if (!input) {
			return;
		}

		const selected = getValue(input) > 0;

		item.classList.toggle(
			'eilmo-cf-product-card--selected',
			selected
		);

		item.dataset.selected = selected ? 'yes' : 'no';

		const selectButton = item.querySelector(SELECTORS.select);

		if (window.eilmoCfDom.isElement(selectButton, 'BUTTON')) {
			selectButton.setAttribute(
				'aria-pressed',
				selected ? 'true' : 'false'
			);
		}
	}

	/**
	 * Enforce single-selection mode.
	 *
	 * Quantity of the currently selected item can still
	 * be greater than one.
	 *
	 * @param {HTMLElement} checkout Checkout wrapper.
	 * @param {HTMLElement} currentItem Current item.
	 */
	function enforceSingleSelection(checkout, currentItem) {
		if (getSelectionMode(checkout) !== 'single') {
			return;
		}

		const currentInput = currentItem.querySelector(
			SELECTORS.input
		);

		if (!currentInput || getValue(currentInput) <= 0) {
			return;
		}

		checkout
			.querySelectorAll(SELECTORS.item)
			.forEach(function (item) {
				if (item === currentItem) {
					return;
				}

				const input = item.querySelector(
					SELECTORS.input
				);

				if (!input || getValue(input) === 0) {
					return;
				}

				input.value = '0';

				updateSelectedState(item);
			});
	}

	/**
	 * Get currently selected products/variations.
	 *
	 * @param {HTMLElement} checkout Checkout wrapper.
	 *
	 * @return {Array}
	 */
	function getSelectedItems(checkout) {
		const selectedItems = [];

		checkout
			.querySelectorAll(SELECTORS.item)
			.forEach(function (item) {
				const input = item.querySelector(
					SELECTORS.input
				);

				if (!input) {
					return;
				}

				const quantity = getValue(input);

				if (quantity <= 0) {
					return;
				}

				selectedItems.push({
					productId: parseInt(
						item.dataset.productId || '0',
						10
					),
					variationId: parseInt(
						item.dataset.variationId || '0',
						10
					),
					quantity: quantity,
				});
			});

		return selectedItems;
	}

	/**
	 * Dispatch quantity-change event.
	 *
	 * Other modules such as Summary, Combo and Rules
	 * can listen to this event.
	 *
	 * @param {HTMLElement} checkout Checkout wrapper.
	 * @param {HTMLElement} item Product/variation card.
	 * @param {HTMLInputElement} input Quantity input.
	 */
	function dispatchQuantityChange(
		checkout,
		item,
		input
	) {
		const event = new CustomEvent(
			'eilmo:quantityChange',
			{
				bubbles: true,
				detail: {
					checkout: checkout,
					item: item,
					productId: parseInt(
						item.dataset.productId || '0',
						10
					),
					variationId: parseInt(
						item.dataset.variationId || '0',
						10
					),
					quantity: getValue(input),
					selectionMode:
						getSelectionMode(checkout),
					selectedItems:
						getSelectedItems(checkout),
				},
			}
		);

		item.dispatchEvent(event);
	}

	/**
	 * Apply quantity change.
	 *
	 * @param {HTMLInputElement} input Quantity input.
	 * @param {number} quantity New quantity.
	 */
	function setQuantity(input, quantity) {
		const item = getItem(input);
		const checkout = getCheckout(input);

		if (!item || !checkout || input.disabled) {
			return;
		}

		let normalized = normalizeQuantity(
			input,
			quantity
		);

		/*
		 * Product-card quantity visibility is a presentation setting only.
		 * Even when card quantity controls are hidden, other authoritative
		 * checkout UIs (notably Order Summary) may adjust the quantity.
		 * Product/variation max limits are already enforced by
		 * normalizeQuantity(), so do not clamp hidden-card quantities to 1.
		 */
		input.value = String(normalized);

		if (normalized > 0) {
			enforceSingleSelection(
				checkout,
				item
			);
		}

		updateSelectedState(item);
		enforceQuantityPresentation(checkout);

		dispatchQuantityChange(
			checkout,
			item,
			input
		);
	}

	/**
	 * Check whether card click should be ignored.
	 *
	 * Interactive elements need their normal behaviour.
	 *
	 * @param {EventTarget|null} target Click target.
	 *
	 * @return {boolean}
	 */
	function shouldIgnoreCardClick(target) {
		if (!(window.eilmoCfDom.isElement(target))) {
			return true;
		}

		return Boolean(
			target.closest(
				[
					'a',
					'button',
					'input',
					'select',
					'textarea',
					'label',
					'[role="button"]',
				].join(',')
			)
		);
	}

	/**
	 * Handle document click.
	 *
	 * @param {MouseEvent} event Click event.
	 */
	function handleClick(event) {
		if (!(window.eilmoCfDom.isElement(event.target))) {
			return;
		}

		/**
		 * Explicit grouped-grid Select button.
		 */
		const selectButton = event.target.closest(
			SELECTORS.select
		);

		if (window.eilmoCfDom.isElement(selectButton, 'BUTTON')) {
			event.preventDefault();

			const item = getItem(selectButton);
			const input = item
				? item.querySelector(SELECTORS.input)
				: null;

			if (
				!item ||
				!input ||
				input.disabled ||
				item.dataset.purchasable === 'no'
			) {
				return;
			}

			setQuantity(
				input,
				getValue(input) > 0 ? 0 : 1
			);

			return;
		}

		/**
		 * Minus button.
		 */
		const minusButton = event.target.closest(
			SELECTORS.minus
		);

		if (minusButton) {
			const quantity = minusButton.closest(
				SELECTORS.quantity
			);

			const input = quantity
				? quantity.querySelector(SELECTORS.input)
				: null;

			if (!input) {
				return;
			}

			setQuantity(
				input,
				getValue(input) - 1
			);

			return;
		}

		/**
		 * Plus button.
		 */
		const plusButton = event.target.closest(
			SELECTORS.plus
		);

		if (plusButton) {
			const quantity = plusButton.closest(
				SELECTORS.quantity
			);

			const input = quantity
				? quantity.querySelector(SELECTORS.input)
				: null;

			if (!input) {
				return;
			}

			setQuantity(
				input,
				getValue(input) + 1
			);

			return;
		}

		/**
		 * Product/variation card click.
		 *
		 * Only select the card when its quantity
		 * is currently zero.
		 */
		const item = event.target.closest(
			SELECTORS.item
		);

		if (!item) {
			return;
		}

		if (item.hasAttribute('data-eilmo-no-card-toggle')) {
			return;
		}

		if (shouldIgnoreCardClick(event.target)) {
			return;
		}

		if (
			item.dataset.purchasable === 'no'
		) {
			return;
		}

		const input = item.querySelector(
			SELECTORS.input
		);

		if (!input || input.disabled) {
			return;
		}

        /**
         * Toggle product/variation selection.
         *
         * Unselected:
         * 0 → 1
         *
         * Selected:
         * 1+ → 0
         */
        const currentQuantity = getValue(input);

        setQuantity(
            input,
            currentQuantity > 0 ? 0 : 1
        );
	}

	/**
	 * Handle direct quantity input.
	 *
	 * @param {Event} event Input event.
	 */
	function handleInput(event) {
		if (!(window.eilmoCfDom.isElement(event.target, 'INPUT'))) {
			return;
		}

		if (!event.target.matches(SELECTORS.input)) {
			return;
		}

		setQuantity(
			event.target,
			getValue(event.target)
		);
	}

	/**
	 * Initialize product selection states.
	 */
	function initialize(context = document) {
		const scope =
			context &&
			typeof context.querySelectorAll === 'function'
				? context
				: document;

		scope
			.querySelectorAll(SELECTORS.item)
			.forEach(function (item) {
				updateSelectedState(item);
			});

		if (
			window.eilmoCfDom.isElement(scope) &&
			scope.matches(SELECTORS.item)
		) {
			updateSelectedState(scope);
		}

		if (
			window.eilmoCfDom.isElement(scope) &&
			scope.matches(SELECTORS.checkout)
		) {
			enforceQuantityPresentation(scope);
		}

		scope
			.querySelectorAll(SELECTORS.checkout)
			.forEach(function (checkout) {
				enforceQuantityPresentation(checkout);
			});
	}

	window.eilmoCfQuantity = {
		refresh: initialize,
	};

	document.addEventListener(
		'eilmo:setQuantity',
		function (event) {
			const checkout = window.eilmoCfDom.isElement(event.target)
				? event.target.closest(SELECTORS.checkout)
				: null;
			if (!checkout || !event.detail) {
				return;
			}

			const productId = parseInt(event.detail.productId || '0', 10);
			const variationId = parseInt(event.detail.variationId || '0', 10);
			let matched = null;
			checkout.querySelectorAll(SELECTORS.item).forEach(function (item) {
				if (matched) return;
				if (parseInt(item.dataset.productId || '0', 10) === productId &&
					parseInt(item.dataset.variationId || '0', 10) === variationId) {
					matched = item;
				}
			});
			if (!matched) return;
			const input = matched.querySelector(SELECTORS.input);
			if (!input) return;
			const next = Number.isFinite(Number(event.detail.quantity))
				? Number(event.detail.quantity)
				: getValue(input) + (parseInt(event.detail.delta || '0', 10) || 0);
			setQuantity(input, next);
		}
	);

	document.addEventListener(
		'click',
		handleClick
	);

	document.addEventListener(
		'change',
		handleInput
	);

	if (document.readyState === 'loading') {
		document.addEventListener(
			'DOMContentLoaded',
			initialize
		);
	} else {
		initialize();
	}
})();
