/**
 * Eilmo Checkout Flow - Elementor product actions and compact package selector.
 *
 * Product/variation buttons are presentation only. They drive the same DOM
 * selection contract used by Checkout Flow, while WooCommerce/PHP remains the
 * authoritative source for availability, pricing and order creation.
 */
(function () {
	'use strict';

	const S = {
		checkout: '[data-eilmo-checkout]',
		trigger: '[data-eilmo-product-trigger]',
		triggerText: '[data-eilmo-product-trigger-text]',
		selector: '[data-eilmo-external-variation-selector]',
		option: '[data-eilmo-external-variation-option]',
		singleRoot: '[data-eilmo-single-product]',
		item: '[data-eilmo-product-item], [data-eilmo-item]',
		qty: '[data-eilmo-quantity-input]',
		shelf: '[data-eilmo-external-products]',
		variationChoice: '[data-eilmo-single-variation-choice]',
	};

	function element(value, tag) {
		return window.eilmoCfDom && window.eilmoCfDom.isElement
			? window.eilmoCfDom.isElement(value, tag)
			: value instanceof Element && (!tag || value.tagName === tag);
	}

	function int(value) {
		const parsed = Number.parseInt(String(value || '0'), 10);
		return Number.isFinite(parsed) ? parsed : 0;
	}

	function money(checkout, amount) {
		if (window.eilmoCfLanguage && typeof window.eilmoCfLanguage.money === 'function') {
			return window.eilmoCfLanguage.money(Number(amount) || 0, checkout.dataset.checkoutLanguage || 'en');
		}
		return String(amount || '0');
	}

	function checkoutText(checkout, key, fallback) {
		if (window.eilmoCfLanguage && typeof window.eilmoCfLanguage.text === 'function') {
			return window.eilmoCfLanguage.text(checkout, key, fallback);
		}
		return fallback;
	}

	function syncCurrentProductIds(checkout) {
		if (!element(checkout)) return;
		const ids = [];
		checkout.querySelectorAll(S.item).forEach(function (item) {
			if (!element(item) || quantity(item) <= 0) return;
			if (item.closest('[data-eilmo-combo-offer], [data-eilmo-order-bump]')) return;
			const productId = int(item.dataset.productId);
			if (productId > 0 && !ids.includes(productId)) ids.push(productId);
		});
		checkout.setAttribute('data-eilmo-current-product-ids', JSON.stringify(ids));
	}

	function itemMatches(item, productId, variationId) {
		return int(item.dataset.productId) === productId && int(item.dataset.variationId) === variationId;
	}

	function checkoutMode(checkout) {
		return element(checkout) && checkout.dataset.productMode === 'multiple' ? 'multiple' : 'single';
	}

	function primaryProductId(checkout) {
		if (!element(checkout)) return 0;
		const explicit = int(checkout.dataset.primaryProductId);
		if (explicit > 0) return explicit;
		const root = checkout.querySelector(S.singleRoot);
		return element(root) ? int(root.dataset.productId) : 0;
	}

	function canUseProduct(checkout, productId) {
		if (!element(checkout) || productId <= 0) return false;
		if (checkoutMode(checkout) === 'multiple') return true;
		return primaryProductId(checkout) === productId;
	}

	function findCheckout(source, productId) {
		const direct = source.closest ? source.closest(S.checkout) : null;
		if (element(direct)) return direct;
		const checkouts = Array.from(document.querySelectorAll(S.checkout));
		if (productId > 0) {
			const matching = checkouts.find(function (checkout) {
				return Boolean(checkout.querySelector(S.singleRoot + '[data-product-id="' + productId + '"]'));
			});
			if (matching) return matching;
		}
		return checkouts.find(element) || null;
	}

	function findItem(checkout, productId, variationId) {
		return Array.from(checkout.querySelectorAll(S.item)).find(function (item) {
			return element(item) && itemMatches(item, productId, variationId);
		}) || null;
	}

	function quantity(item) {
		const input = item ? item.querySelector(S.qty) : null;
		return element(input, 'INPUT') ? Math.max(0, int(input.value)) : 0;
	}

	function dispatchQuantity(checkout, item, productId, variationId, nextQuantity) {
		checkout.dispatchEvent(new CustomEvent('eilmo:quantityChange', {
			bubbles: true,
			detail: {
				checkout: checkout,
				item: item,
				productId: productId,
				variationId: variationId,
				quantity: nextQuantity,
			},
		}));
	}

	function setQuantity(checkout, item, nextQuantity) {
		if (!element(item)) return;
		const input = item.querySelector(S.qty);
		if (!element(input, 'INPUT') || input.disabled) return;
		const productId = int(item.dataset.productId || input.dataset.productId);
		const variationId = int(item.dataset.variationId || input.dataset.variationId);
		const maxQuantity = int(item.dataset.maxQuantity || input.max || 0);
		let normalized = Math.max(0, int(nextQuantity));
		if (maxQuantity > 0) normalized = Math.min(normalized, maxQuantity);
		input.value = String(normalized);
		const output = item.querySelector('[data-eilmo-external-product-quantity]');
		if (element(output)) output.textContent = window.eilmoCfLanguage ? window.eilmoCfLanguage.digits(normalized, checkout.dataset.checkoutLanguage) : String(normalized);
		const minus = item.querySelector('[data-eilmo-external-qty-minus]');
		const plus = item.querySelector('[data-eilmo-external-qty-plus]');
		if (element(minus, 'BUTTON')) minus.disabled = normalized <= 1;
		if (element(plus, 'BUTTON')) plus.disabled = maxQuantity > 0 && normalized >= maxQuantity;
		syncCurrentProductIds(checkout);
		dispatchQuantity(checkout, item, productId, variationId, normalized);
	}

	function removeExternalItem(checkout, item) {
		if (!element(item)) return;
		setQuantity(checkout, item, 0);
		if (item.matches('.eilmo-cf-external-product-row')) item.remove();
		refreshShelf(checkout);
	}

	function clearProductVariations(checkout, productId, exceptVariationId) {
		checkout.querySelectorAll(S.item).forEach(function (item) {
			if (!element(item) || int(item.dataset.productId) !== productId) return;
			const variationId = int(item.dataset.variationId);
			if (variationId <= 0 || variationId === exceptVariationId || quantity(item) <= 0) return;
			removeExternalItem(checkout, item);
		});
	}

	function ensureExternalItem(checkout, data) {
		/* Single Product checkout never grows a second product row. Elementor
		 * triggers only synchronize/replace the configured primary product. */
		if (checkoutMode(checkout) !== 'multiple') return null;
		let item = findItem(checkout, data.productId, data.variationId);
		if (item) return item;
		const shelf = checkout.querySelector(S.shelf);
		if (!element(shelf)) return null;

		item = document.createElement('div');
		item.className = 'eilmo-cf-external-product-row';
		item.setAttribute('data-eilmo-item', '');
		item.setAttribute('data-eilmo-no-card-toggle', '');
		item.dataset.itemType = data.variationId > 0 ? 'variation' : 'simple';
		item.dataset.productId = String(data.productId);
		item.dataset.variationId = String(data.variationId);
		item.dataset.price = String(data.price || 0);
		item.dataset.maxQuantity = String(data.maxQuantity || 0);
		item.dataset.itemName = String(data.name || 'Product');
		item.dataset.itemVariation = String(data.variation || '');
		item.dataset.itemImage = String(data.image || '');
		item.dataset.purchasable = 'yes';

		const info = document.createElement('div');
		info.className = 'eilmo-cf-external-product-row__info';
		const name = document.createElement('strong');
		name.className = 'eilmo-cf-external-product-row__name';
		name.textContent = data.name || 'Product';
		info.appendChild(name);
		if (data.variation) {
			const variation = document.createElement('span');
			variation.className = 'eilmo-cf-external-product-row__variation';
			variation.textContent = data.variation;
			info.appendChild(variation);
		}
		item.appendChild(info);

		const price = document.createElement('strong');
		price.className = 'eilmo-cf-external-product-row__price';
		price.textContent = money(checkout, data.price || 0);
		item.appendChild(price);

		const qty = document.createElement('div');
		qty.className = 'eilmo-cf-external-product-row__quantity';
		qty.innerHTML = '<button type="button" data-eilmo-external-qty-minus aria-label="Decrease quantity">−</button><output data-eilmo-external-product-quantity>1</output><button type="button" data-eilmo-external-qty-plus aria-label="Increase quantity">+</button>';
		if (window.eilmoCfLanguage) qty.querySelectorAll('button').forEach(function (button) { button.setAttribute('aria-label', window.eilmoCfLanguage.copy(checkout, button.getAttribute('aria-label'))); });
		item.appendChild(qty);

		const remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'eilmo-cf-external-product-row__remove';
		remove.setAttribute('data-eilmo-external-product-remove', '');
		remove.setAttribute('aria-label', checkoutText(checkout, 'product.remove', 'Remove product'));
		remove.textContent = '×';
		item.appendChild(remove);

		const input = document.createElement('input');
		input.type = 'hidden';
		input.name = 'eilmo_cf_items[' + (data.variationId > 0 ? data.variationId : data.productId) + '][quantity]';
		input.value = '0';
		input.dataset.eilmoQuantityInput = '';
		input.setAttribute('data-eilmo-quantity-input', '');
		input.dataset.productId = String(data.productId);
		input.dataset.variationId = String(data.variationId);
		if (int(data.maxQuantity) > 0) input.max = String(int(data.maxQuantity));
		item.appendChild(input);

		shelf.appendChild(item);
		refreshShelf(checkout);
		return item;
	}

	function refreshShelf(checkout) {
		const shelf = checkout.querySelector(S.shelf);
		if (checkoutMode(checkout) !== 'multiple') {
			if (element(shelf)) shelf.hidden = true;
			syncCurrentProductIds(checkout);
			return;
		}
		if (!element(shelf)) return;
		const hasRows = Boolean(shelf.querySelector('.eilmo-cf-external-product-row'));
		const empty = shelf.querySelector('[data-eilmo-external-products-empty]');
		shelf.hidden = false;
		if (element(empty)) empty.hidden = hasRows;
		syncCurrentProductIds(checkout);
	}

	function selectData(source, data, desiredQuantity) {
		const checkout = findCheckout(source, data.productId);
		if (!checkout || data.canPurchase === false || !canUseProduct(checkout, data.productId)) return null;

		/*
		 * Single Product checkout owns exactly one variation of its primary
		 * product, so selecting a sibling variation replaces the previous one.
		 * Multiple Products checkout is a collection: each exact variation is an
		 * independent purchasable row and must not remove sibling variations.
		 */
		if (data.variationId > 0 && checkoutMode(checkout) === 'single') {
			clearProductVariations(checkout, data.productId, data.variationId);
		}
		let item = findItem(checkout, data.productId, data.variationId);
		if (!item && checkoutMode(checkout) === 'multiple') item = ensureExternalItem(checkout, data);
		if (!item) return null;

		/* A Single Product checkout always owns one primary item. Product Button
		 * clicks change/synchronize that item; they never append another row. */
		const nextQuantity = checkoutMode(checkout) === 'single'
			? Math.max(1, desiredQuantity)
			: Math.max(0, desiredQuantity);
		setQuantity(checkout, item, nextQuantity);
		refreshShelf(checkout);
		setTimeout(syncAll, 0);
		return checkout;
	}

	function dataFrom(elementNode) {
		return {
			productId: int(elementNode.dataset.productId),
			variationId: int(elementNode.dataset.variationId),
			price: Number(elementNode.dataset.price || 0) || 0,
			maxQuantity: int(elementNode.dataset.maxQuantity || 0),
			name: String(elementNode.dataset.itemName || ''),
			variation: String(elementNode.dataset.itemVariation || ''),
			image: String(elementNode.dataset.itemImage || ''),
			canPurchase: elementNode.dataset.canPurchase !== 'no',
			productType: String(elementNode.dataset.productType || ''),
		};
	}

	function resolveTriggerData(trigger) {
		const data = dataFrom(trigger);
		if (data.productType !== 'variable' || data.variationId > 0) return data;

		const checkout = findCheckout(trigger, data.productId);
		if (checkout) {
			const selectedItem = Array.from(checkout.querySelectorAll(S.item)).find(function (item) {
				return int(item.dataset.productId) === data.productId && int(item.dataset.variationId) > 0 && quantity(item) > 0;
			});
			if (selectedItem) {
				return {
					productId: data.productId,
					variationId: int(selectedItem.dataset.variationId),
					price: Number(selectedItem.dataset.price || 0) || 0,
					name: String(selectedItem.dataset.itemName || data.name),
					variation: String(selectedItem.dataset.itemVariation || ''),
					image: String(selectedItem.dataset.itemImage || data.image),
					canPurchase: true,
					productType: 'variation',
				};
			}
		}

		const selectedOption = document.querySelector(S.option + '.is-selected[data-product-id="' + data.productId + '"]');
		return element(selectedOption, 'BUTTON') ? dataFrom(selectedOption) : data;
	}

	function syncTrigger(trigger) {
		if (!element(trigger, 'BUTTON')) return;
		const baseData = dataFrom(trigger);
		const data = resolveTriggerData(trigger);
		const checkout = findCheckout(trigger, baseData.productId);
		let item = checkout ? findItem(checkout, data.productId, data.variationId) : null;
		if (!item && checkout && baseData.productType === 'variable' && baseData.variationId === 0) {
			item = Array.from(checkout.querySelectorAll(S.item)).find(function (candidate) {
				return int(candidate.dataset.productId) === baseData.productId && int(candidate.dataset.variationId) > 0 && quantity(candidate) > 0;
			}) || null;
		}
		const selected = Boolean(item && quantity(item) > 0);
		trigger.classList.toggle('is-selected', selected);
		trigger.setAttribute('aria-pressed', selected ? 'true' : 'false');
		const label = trigger.querySelector(S.triggerText);
		if (element(label)) {
			const nextLabel = selected
				? String(trigger.dataset.addedLabel || 'Selected')
				: String(trigger.dataset.addLabel || 'Buy Now');
			/*
			 * Do not rewrite an unchanged text node. Elementor continuously mutates
			 * the preview DOM while controls are edited. Replacing textContent on
			 * every sync creates a childList mutation, which used to retrigger our
			 * global MutationObserver and could lock the editor in an infinite loop.
			 */
			if (label.textContent !== nextLabel) label.textContent = nextLabel;
		}
	}

	function syncVariationSelector(selector) {
		if (!element(selector)) return;
		/* Manual selectors keep their local selection for a separate Product Button. */
		if (selector.dataset.autoAdd === 'no') return;
		const productId = int(selector.dataset.productId);
		const checkout = findCheckout(selector, productId);
		selector.querySelectorAll(S.option).forEach(function (option) {
			const variationId = int(option.dataset.variationId);
			const item = checkout ? findItem(checkout, productId, variationId) : null;
			const selected = Boolean(item && quantity(item) > 0);
			option.classList.toggle('is-selected', selected);
			option.setAttribute('aria-pressed', selected ? 'true' : 'false');
		});
	}

	function syncAll() {
		document.querySelectorAll(S.trigger).forEach(syncTrigger);
		document.querySelectorAll(S.selector).forEach(syncVariationSelector);
		document.querySelectorAll(S.checkout).forEach(function (checkout) { if (element(checkout)) refreshShelf(checkout); });
	}

	function currentVariation(root) {
		const selected = Array.from(root.querySelectorAll(S.item)).find(function (item) {
			return int(item.dataset.productId) === int(root.dataset.productId) && int(item.dataset.variationId) > 0 && quantity(item) > 0;
		});
		return selected ? int(selected.dataset.variationId) : int(root.dataset.defaultVariationId);
	}

	function refreshCompact(root) {
		const compact = root.querySelector('[data-eilmo-package-compact]');
		if (!element(compact)) return;
		const variationId = currentVariation(root);
		const choice = root.querySelector(S.variationChoice + '[data-variation-id="' + variationId + '"]');
		const label = compact.querySelector('[data-eilmo-package-compact-label]');
		const price = compact.querySelector('[data-eilmo-package-compact-price]');
		if (element(choice)) {
			const sourceLabel = choice.querySelector('.eilmo-cf-single-product__choice-label');
			const sourcePrice = choice.querySelector('.eilmo-cf-single-product__choice-price');
			if (element(label)) label.textContent = sourceLabel ? sourceLabel.textContent.trim() : '';
			if (element(price)) price.innerHTML = sourcePrice ? sourcePrice.innerHTML : '';
		}
	}

	function prepareCompactPackage(root) {
		if (!element(root) || root.dataset.showCheckoutSelector === 'no' || root.dataset.packageCompactReady === 'yes') return;
		const grid = root.querySelector('.eilmo-cf-single-product__variation-grid');
		if (!element(grid)) return;
		const choices = Array.from(grid.querySelectorAll(S.variationChoice));
		const maxVisible = Math.max(1, int(root.dataset.maxVisibleVariations || 4));
		if (choices.length <= maxVisible) return;
		root.dataset.packageCompactReady = 'yes';
		grid.hidden = true;

		const compact = document.createElement('div');
		compact.className = 'eilmo-cf-package-compact';
		compact.setAttribute('data-eilmo-package-compact', '');
		const checkout = root.closest(S.checkout);
		const changeLabel = checkoutText(checkout, 'product.change', 'Change');
		compact.innerHTML = '<div class="eilmo-cf-package-compact__info"><strong data-eilmo-package-compact-label></strong><span data-eilmo-package-compact-price></span></div><button type="button" class="eilmo-cf-package-compact__change" data-eilmo-package-change></button>';
		const changeButton = compact.querySelector('[data-eilmo-package-change]');
		if (element(changeButton, 'BUTTON')) changeButton.textContent = changeLabel;
		grid.parentNode.insertBefore(compact, grid.nextSibling);

		const modal = document.createElement('div');
		modal.className = 'eilmo-cf-package-modal';
		modal.setAttribute('data-eilmo-package-modal', '');
		modal.hidden = true;
		const panel = document.createElement('div');
		panel.className = 'eilmo-cf-package-modal__panel';
		const header = document.createElement('div');
		header.className = 'eilmo-cf-package-modal__header';
		const modalTitle = document.createElement('strong');
		modalTitle.textContent = checkoutText(checkout, 'product.choose', 'Choose Package');
		const closeButton = document.createElement('button');
		closeButton.type = 'button';
		closeButton.setAttribute('data-eilmo-package-close', '');
		closeButton.setAttribute('aria-label', checkoutText(checkout, 'common.close', 'Close'));
		closeButton.textContent = '×';
		header.appendChild(modalTitle);
		header.appendChild(closeButton);
		const list = document.createElement('div');
		list.className = 'eilmo-cf-package-modal__grid';
		choices.forEach(function (choice) { list.appendChild(choice.cloneNode(true)); });
		panel.appendChild(header); panel.appendChild(list); modal.appendChild(panel); root.appendChild(modal);
		refreshCompact(root);
	}

	function initialize(root) {
		const scope = element(root) ? root : document;
		scope.querySelectorAll(S.singleRoot).forEach(prepareCompactPackage);
		syncAll();
	}

	document.addEventListener('click', function (event) {
		if (!element(event.target)) return;

		const trigger = event.target.closest(S.trigger);
		if (element(trigger, 'BUTTON')) {
			event.preventDefault();
			if (trigger.disabled || trigger.dataset.canPurchase === 'no') return;
			const baseData = dataFrom(trigger);
			const data = resolveTriggerData(trigger);
			const checkout = findCheckout(trigger, baseData.productId);
			if (baseData.productType === 'variable' && data.variationId <= 0) {
				trigger.classList.add('has-selection-error');
				return;
			}
			trigger.classList.remove('has-selection-error');
			if (!checkout || !canUseProduct(checkout, data.productId)) {
				trigger.classList.add('has-selection-error');
				return;
			}
			const existing = findItem(checkout, data.productId, data.variationId);
			const selected = Boolean(existing && quantity(existing) > 0);
			if (selected && checkoutMode(checkout) === 'multiple' && trigger.dataset.allowRemove === 'yes') {
				removeExternalItem(checkout, existing);
			} else {
				const selectedCheckout = selectData(trigger, data, Math.max(1, int(trigger.dataset.quantity || 1)));
				if (selectedCheckout && trigger.dataset.scrollToCheckout === 'yes') selectedCheckout.scrollIntoView({behavior: 'smooth', block: 'start'});
			}
			syncAll();
			return;
		}

		const option = event.target.closest(S.option);
		if (element(option, 'BUTTON')) {
			event.preventDefault();
			if (option.disabled || option.dataset.canPurchase === 'no') return;
			const selector = option.closest(S.selector);
			const data = dataFrom(option);
			const allowDeselect = !selector || selector.dataset.allowDeselect !== 'no';

			/* Manual mode is a pure selector for a separate Product Button. */
			if (selector && selector.dataset.autoAdd === 'no') {
				const alreadySelected = option.classList.contains('is-selected');
				selector.querySelectorAll(S.option).forEach(function (candidate) {
					candidate.classList.remove('is-selected');
					candidate.setAttribute('aria-pressed', 'false');
				});
				if (!(alreadySelected && allowDeselect)) {
					option.classList.add('is-selected');
					option.setAttribute('aria-pressed', 'true');
				}
				return;
			}

			const checkout = findCheckout(option, data.productId);
			const existing = checkout ? findItem(checkout, data.productId, data.variationId) : null;
			const selected = Boolean(existing && quantity(existing) > 0);
			if (selected && allowDeselect && checkout) {
				removeExternalItem(checkout, existing);
			} else {
				selectData(option, data, 1);
			}
			syncAll();
			return;
		}

		const quantityButton = event.target.closest('[data-eilmo-external-qty-minus], [data-eilmo-external-qty-plus]');
		if (element(quantityButton, 'BUTTON')) {
			event.preventDefault();
			const item = quantityButton.closest('.eilmo-cf-external-product-row');
			const checkout = quantityButton.closest(S.checkout);
			if (item && checkout) {
				const current = quantity(item);
				const delta = quantityButton.hasAttribute('data-eilmo-external-qty-plus') ? 1 : -1;
				if (delta < 0 && current <= 1) return;
				setQuantity(checkout, item, current + delta);
				syncAll();
			}
			return;
		}

		const remove = event.target.closest('[data-eilmo-external-product-remove]');
		if (element(remove, 'BUTTON')) {
			event.preventDefault();
			const item = remove.closest('.eilmo-cf-external-product-row');
			const checkout = remove.closest(S.checkout);
			if (item && checkout) removeExternalItem(checkout, item);
			syncAll();
			return;
		}

		const change = event.target.closest('[data-eilmo-package-change]');
		if (element(change, 'BUTTON')) {
			event.preventDefault();
			const root = change.closest(S.singleRoot);
			const modal = root ? root.querySelector('[data-eilmo-package-modal]') : null;
			if (element(modal)) modal.hidden = false;
			return;
		}
		const close = event.target.closest('[data-eilmo-package-close]');
		if (element(close, 'BUTTON')) {
			event.preventDefault();
			const modal = close.closest('[data-eilmo-package-modal]');
			if (element(modal)) modal.hidden = true;
			return;
		}
		const modalChoice = event.target.closest('[data-eilmo-package-modal] ' + S.variationChoice);
		if (element(modalChoice, 'BUTTON')) {
			setTimeout(function () {
				const root = modalChoice.closest(S.singleRoot);
				const modal = modalChoice.closest('[data-eilmo-package-modal]');
				if (element(modal)) modal.hidden = true;
				if (element(root)) refreshCompact(root);
				syncAll();
			}, 0);
		}
	});

	document.addEventListener('eilmo:quantityChange', function (event) {
		setTimeout(function () {
			const checkout = event.detail && event.detail.checkout ? event.detail.checkout : (element(event.target) ? event.target.closest(S.checkout) : null);
			if (checkout) checkout.querySelectorAll(S.singleRoot).forEach(refreshCompact);
			syncAll();
		}, 0);
	});

	['eilmo:multipleProductsChange', 'eilmo:summaryRefresh'].forEach(function (name) {
		document.addEventListener(name, function () { setTimeout(syncAll, 0); });
	});

	let observerSyncQueued = false;
	const observer = new MutationObserver(function (mutations) {
		let relevant = false;
		mutations.some(function (mutation) {
			return Array.from(mutation.addedNodes || []).some(function (node) {
				if (!element(node)) return false;
				if (
					node.matches(S.checkout + ', ' + S.singleRoot + ', ' + S.trigger + ', ' + S.selector) ||
					node.querySelector(S.checkout + ', ' + S.singleRoot + ', ' + S.trigger + ', ' + S.selector)
				) {
					relevant = true;
					return true;
				}
				return false;
			});
		});
		if (!relevant || observerSyncQueued) return;
		observerSyncQueued = true;
		const schedule = window.requestAnimationFrame || function (callback) { return window.setTimeout(callback, 0); };
		schedule(function () {
			observerSyncQueued = false;
			initialize(document);
		});
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { initialize(document); observer.observe(document.documentElement, {childList: true, subtree: true}); });
	} else {
		initialize(document); observer.observe(document.documentElement, {childList: true, subtree: true});
	}
})();
