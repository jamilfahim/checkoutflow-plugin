/* BEGIN src/js/frontend/checkout-language.js */
(function () {
 'use strict';
 if (window.eilmoCfLanguage) return;
 const catalogs = new WeakMap();
 function catalog(checkout) {
  if (!checkout) return {};
  const raw = checkout.getAttribute('data-checkout-catalog') || '{}';
  const saved = catalogs.get(checkout);
  if (saved && saved.raw === raw) return saved.value;
  let value = {};
  try { value = JSON.parse(raw); } catch (_) { /* Invalid catalogs use caller fallbacks. */ }
  catalogs.set(checkout, {raw, value});
  return value;
 }
 const digits = (text, language) => language === 'bn' ? String(text).replace(/[0-9]/g, d => '০১২৩৪৫৬৭৮৯'[Number(d)]) : String(text);
 const normalizeDigits = text => String(text).replace(/[০-৯]/g, d => String('০১২৩৪৫৬৭৮৯'.indexOf(d)));
 function text(checkout, key, fallback, params = {}) {
  let value = catalog(checkout)[key] || fallback || key;
  Object.keys(params).forEach(name => { value = value.split('{' + name + '}').join(String(params[name])); });
  return value;
 }
 function copy(checkout, source) {
  const map = checkout && checkout.getAttribute('data-checkout-source-catalog');
  if (!map) return source;
  let originals;
  try { originals = JSON.parse(map); } catch (_) { return source; }
  const key = Object.keys(originals).find(key => originals[key] === source);
  return key ? text(checkout, key, source) : source;
 }
 function money(amount, language) {
  const config = window.eilmoCf && window.eilmoCf.currency || {};
  const decoder = document.createElement('textarea'); decoder.innerHTML = config.symbol || '৳';
  const symbol = decoder.value.trim();
  const precision = Number.isFinite(Number(config.decimals)) ? Number(config.decimals) : 2;
  const number = Number(amount) || 0;
  const parts = number.toFixed(Math.max(0, precision)).split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, config.thousandSeparator || ',');
  const value = parts[0] + (parts[1] && /[1-9]/.test(parts[1]) ? (config.decimalSeparator || '.') + parts[1] : '');
  const position = symbol === '৳' ? 'left' : config.position || 'left';
  return digits(position.startsWith('right') ? value + (position.endsWith('space') ? ' ' : '') + symbol : symbol + (position.endsWith('space') ? ' ' : '') + value, language);
 }
 window.eilmoCfLanguage = {text, copy, digits, normalizeDigits, money};
 document.addEventListener('input', function (event) {
  const input = event.target;
  if (!input || !input.closest || !input.closest('[data-eilmo-checkout]')) return;
  if (input.matches('input[type="tel"],input[data-eilmo-quantity-input]')) {
   const normalized = normalizeDigits(input.value);
   if (normalized !== input.value) input.value = normalized;
  }
 }, true);
})();
/* END src/js/frontend/checkout-language.js */
/**
 * Eilmo Checkout Flow v2.0.11 production frontend bundle.
 * Generated deterministically from src/Core/Assets.php order.
 */
'use strict';

/* BEGIN src/js/frontend/dom.js */
/** DOM type checks also support elements adopted by Elementor from its parent frame. */
(function () {
	'use strict';
	window.eilmoCfDom = {
		isElement: function (value, tagName) {
			return !!value && value.nodeType === 1 && (!tagName || value.tagName === tagName);
		},
	};
})();
/* END src/js/frontend/dom.js */

/* BEGIN src/js/frontend/product-gallery.js */
/**
 * Eilmo Checkout Flow Product Gallery.
 *
 * Lightweight slider, thumbnail navigation, swipe and lightbox.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTOR = '[data-eilmo-product-gallery]';
	const INITIALIZED = 'eilmoGalleryInitialized';

	let lightbox = null;
	let lightboxImages = [];
	let lightboxIndex = 0;
	let lightboxLoop = true;
	let lastFocusedElement = null;
	let lightboxTouchStartX = null;

	/**
	 * Normalize slider index.
	 *
	 * @param {number} index Requested index.
	 * @param {number} total Total slides.
	 * @param {boolean} loop Whether looping is enabled.
	 *
	 * @return {number}
	 */
	function normalizeIndex(index, total, loop) {
		if (total <= 0) {
			return 0;
		}

		if (loop) {
			return ((index % total) + total) % total;
		}

		return Math.max(0, Math.min(index, total - 1));
	}

	/**
	 * Build shared lightbox once.
	 *
	 * @return {HTMLElement}
	 */
	function getLightbox() {
		if (window.eilmoCfDom.isElement(lightbox)) {
			return lightbox;
		}

		const wrapper = document.createElement('div');
		wrapper.className = 'eilmo-cf-gallery-lightbox';
		wrapper.hidden = true;
		wrapper.setAttribute('role', 'dialog');
		wrapper.setAttribute('aria-modal', 'true');
		wrapper.setAttribute('aria-label', 'Product image viewer');

		wrapper.innerHTML = `
			<div class="eilmo-cf-gallery-lightbox__backdrop" data-eilmo-gallery-lightbox-close></div>
			<div class="eilmo-cf-gallery-lightbox__dialog">
				<button
					type="button"
					class="eilmo-cf-gallery-lightbox__close"
					data-eilmo-gallery-lightbox-close
					aria-label="Close image viewer"
				>
					<svg class="eilmo-cf-gallery-lightbox__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M6 6l12 12M18 6L6 18"></path>
					</svg>
				</button>

				<button
					type="button"
					class="eilmo-cf-gallery-lightbox__arrow eilmo-cf-gallery-lightbox__arrow--prev"
					data-eilmo-gallery-lightbox-prev
					aria-label="Previous image"
				>
					<svg class="eilmo-cf-gallery-lightbox__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M15 18l-6-6 6-6"></path>
					</svg>
				</button>

				<div class="eilmo-cf-gallery-lightbox__stage">
					<img
						class="eilmo-cf-gallery-lightbox__image"
						data-eilmo-gallery-lightbox-image
						alt=""
					>
					<div
						class="eilmo-cf-gallery-lightbox__counter"
						data-eilmo-gallery-lightbox-counter
					></div>
				</div>

				<button
					type="button"
					class="eilmo-cf-gallery-lightbox__arrow eilmo-cf-gallery-lightbox__arrow--next"
					data-eilmo-gallery-lightbox-next
					aria-label="Next image"
				>
					<svg class="eilmo-cf-gallery-lightbox__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M9 18l6-6-6-6"></path>
					</svg>
				</button>
			</div>
		`;

		document.body.appendChild(wrapper);
		lightbox = wrapper;

		wrapper.addEventListener('click', function (event) {
			if (!(window.eilmoCfDom.isElement(event.target))) {
				return;
			}

			if (event.target.closest('[data-eilmo-gallery-lightbox-close]')) {
				closeLightbox();
				return;
			}

			if (event.target.closest('[data-eilmo-gallery-lightbox-prev]')) {
				showLightboxImage(lightboxIndex - 1);
				return;
			}

			if (event.target.closest('[data-eilmo-gallery-lightbox-next]')) {
				showLightboxImage(lightboxIndex + 1);
			}
		});

		wrapper.addEventListener('touchstart', function (event) {
			if (event.touches.length !== 1) {
				lightboxTouchStartX = null;
				return;
			}

			lightboxTouchStartX = event.touches[0].clientX;
		}, { passive: true });

		wrapper.addEventListener('touchend', function (event) {
			if (lightboxTouchStartX === null || event.changedTouches.length !== 1) {
				return;
			}

			const distance = event.changedTouches[0].clientX - lightboxTouchStartX;
			lightboxTouchStartX = null;

			if (Math.abs(distance) < 45) {
				return;
			}

			showLightboxImage(distance < 0 ? lightboxIndex + 1 : lightboxIndex - 1);
		}, { passive: true });

		document.addEventListener('keydown', function (event) {
			if (!lightbox || lightbox.hidden) {
				return;
			}

			if ('Escape' === event.key) {
				closeLightbox();
				return;
			}

			if ('ArrowLeft' === event.key) {
				showLightboxImage(lightboxIndex - 1);
				return;
			}

			if ('ArrowRight' === event.key) {
				showLightboxImage(lightboxIndex + 1);
			}
		});

		return wrapper;
	}

	/**
	 * Update lightbox image.
	 *
	 * @param {number} requestedIndex Requested image index.
	 *
	 * @return {void}
	 */
	function showLightboxImage(requestedIndex) {
		const wrapper = getLightbox();
		const total = lightboxImages.length;

		if (total <= 0) {
			return;
		}

		lightboxIndex = normalizeIndex(
			requestedIndex,
			total,
			lightboxLoop
		);

		const imageData = lightboxImages[lightboxIndex];
		const image = wrapper.querySelector('[data-eilmo-gallery-lightbox-image]');
		const counter = wrapper.querySelector('[data-eilmo-gallery-lightbox-counter]');
		const prev = wrapper.querySelector('[data-eilmo-gallery-lightbox-prev]');
		const next = wrapper.querySelector('[data-eilmo-gallery-lightbox-next]');

		if (window.eilmoCfDom.isElement(image, 'IMG')) {
			image.src = imageData.src;
			image.alt = imageData.alt || '';
		}

		if (window.eilmoCfDom.isElement(counter)) {
			counter.textContent = `${lightboxIndex + 1} / ${total}`;
		}

		if (window.eilmoCfDom.isElement(prev, 'BUTTON')) {
			prev.hidden = total <= 1;
			prev.disabled = !lightboxLoop && lightboxIndex <= 0;
		}

		if (window.eilmoCfDom.isElement(next, 'BUTTON')) {
			next.hidden = total <= 1;
			next.disabled = !lightboxLoop && lightboxIndex >= total - 1;
		}
	}

	/**
	 * Open lightbox.
	 *
	 * @param {Array<{src:string,alt:string}>} images Images.
	 * @param {number} index Current index.
	 * @param {boolean} loop Loop setting.
	 * @param {HTMLElement|null} trigger Trigger element.
	 *
	 * @return {void}
	 */
	function openLightbox(images, index, loop, trigger) {
		if (!Array.isArray(images) || images.length <= 0) {
			return;
		}

		const wrapper = getLightbox();

		lightboxImages = images;
		lightboxLoop = loop;
		lastFocusedElement = trigger;

		wrapper.hidden = false;
		document.documentElement.classList.add('eilmo-cf-gallery-lightbox-open');

		showLightboxImage(index);

		const close = wrapper.querySelector('[data-eilmo-gallery-lightbox-close]');

		if (window.eilmoCfDom.isElement(close)) {
			close.focus();
		}
	}

	/**
	 * Close lightbox.
	 *
	 * @return {void}
	 */
	function closeLightbox() {
		if (!(window.eilmoCfDom.isElement(lightbox))) {
			return;
		}

		lightbox.hidden = true;
		document.documentElement.classList.remove('eilmo-cf-gallery-lightbox-open');

		if (window.eilmoCfDom.isElement(lastFocusedElement)) {
			lastFocusedElement.focus();
		}

		lastFocusedElement = null;
	}

	/**
	 * Initialize one gallery.
	 *
	 * @param {HTMLElement} gallery Gallery wrapper.
	 *
	 * @return {void}
	 */
	function initializeGallery(gallery) {
		if (gallery.dataset[INITIALIZED] === 'yes') {
			return;
		}

		const track = gallery.querySelector('[data-eilmo-gallery-track]');
		const slides = Array.from(gallery.querySelectorAll('[data-eilmo-gallery-slide]'));

		if (!(window.eilmoCfDom.isElement(track)) || slides.length <= 0) {
			return;
		}

		gallery.dataset[INITIALIZED] = 'yes';

		const loop = gallery.dataset.loop !== 'no';
		const lightboxEnabled = gallery.dataset.lightbox === 'yes';
		const prev = gallery.querySelector('[data-eilmo-gallery-prev]');
		const next = gallery.querySelector('[data-eilmo-gallery-next]');
		const dots = Array.from(gallery.querySelectorAll('[data-eilmo-gallery-dot]'));
		const thumbnails = Array.from(gallery.querySelectorAll('[data-eilmo-gallery-thumbnail]'));
		let index = 0;
		let touchStartX = null;
		let suppressLightboxClickUntil = 0;

		const lightboxData = slides.map(function (slide) {
			const image = slide.querySelector('.eilmo-cf-product-gallery__image');

			return {
				src: window.eilmoCfDom.isElement(image, 'IMG')
					? (image.dataset.fullSrc || image.src)
					: '',
				alt: window.eilmoCfDom.isElement(image, 'IMG')
					? image.alt
					: '',
			};
		}).filter(function (image) {
			return Boolean(image.src);
		});

		function update(requestedIndex) {
			index = normalizeIndex(
				requestedIndex,
				slides.length,
				loop
			);

			track.style.transform = `translate3d(-${index * 100}%, 0, 0)`;

			slides.forEach(function (slide, slideIndex) {
				slide.classList.toggle('is-active', slideIndex === index);
			});

			dots.forEach(function (dot, dotIndex) {
				dot.classList.toggle('is-active', dotIndex === index);
				dot.setAttribute('aria-current', dotIndex === index ? 'true' : 'false');
			});

			thumbnails.forEach(function (thumbnail, thumbIndex) {
				thumbnail.classList.toggle('is-active', thumbIndex === index);
				thumbnail.setAttribute('aria-current', thumbIndex === index ? 'true' : 'false');
			});

			if (window.eilmoCfDom.isElement(prev, 'BUTTON')) {
				prev.disabled = !loop && index <= 0;
			}

			if (window.eilmoCfDom.isElement(next, 'BUTTON')) {
				next.disabled = !loop && index >= slides.length - 1;
			}
		}

		if (window.eilmoCfDom.isElement(prev)) {
			prev.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				update(index - 1);
			});
		}

		if (window.eilmoCfDom.isElement(next)) {
			next.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				update(index + 1);
			});
		}

		dots.forEach(function (dot) {
			dot.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				update(Number.parseInt(dot.getAttribute('data-eilmo-gallery-dot') || '0', 10));
			});
		});

		thumbnails.forEach(function (thumbnail) {
			thumbnail.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				update(Number.parseInt(thumbnail.getAttribute('data-eilmo-gallery-thumbnail') || '0', 10));
			});
		});

		if (lightboxEnabled) {
			gallery.querySelectorAll('[data-eilmo-gallery-open-lightbox]').forEach(function (button) {
				button.addEventListener('click', function (event) {
					event.preventDefault();
					event.stopPropagation();

					/*
					 * A horizontal touch swipe can emit a synthetic
					 * click afterwards. Ignore that click so swiping
					 * the slider never opens the lightbox by accident.
					 */
					if (Date.now() < suppressLightboxClickUntil) {
						return;
					}

					const slide = button.closest('[data-eilmo-gallery-slide]');
					const slideIndex = window.eilmoCfDom.isElement(slide)
						? Number.parseInt(slide.dataset.index || '0', 10)
						: index;

					openLightbox(
						lightboxData,
						Number.isFinite(slideIndex) ? slideIndex : index,
						loop,
						window.eilmoCfDom.isElement(button) ? button : null
					);
				});
			});
		}

		const viewport = gallery.querySelector('.eilmo-cf-product-gallery__viewport');

		if (window.eilmoCfDom.isElement(viewport) && slides.length > 1) {
			viewport.addEventListener('touchstart', function (event) {
				if (event.touches.length !== 1) {
					touchStartX = null;
					return;
				}

				touchStartX = event.touches[0].clientX;
			}, { passive: true });

			viewport.addEventListener('touchend', function (event) {
				if (touchStartX === null || event.changedTouches.length !== 1) {
					return;
				}

				const distance = event.changedTouches[0].clientX - touchStartX;
				touchStartX = null;

				if (Math.abs(distance) < 45) {
					return;
				}

				suppressLightboxClickUntil = Date.now() + 350;
				update(distance < 0 ? index + 1 : index - 1);
			}, { passive: true });
		}

		/*
		 * Prevent gallery controls inside a simple product card
		 * from bubbling into the product-card selection handler.
		 */
		gallery.addEventListener('click', function (event) {
			if (window.eilmoCfDom.isElement(event.target) && event.target.closest('button')) {
				event.stopPropagation();
			}
		});

		update(0);
	}

	/**
	 * Initialize all current Product Galleries.
	 *
	 * @param {ParentNode} root Root node.
	 *
	 * @return {void}
	 */
	function initializeAll(root) {
		const scope = root && typeof root.querySelectorAll === 'function'
			? root
			: document;

		if (window.eilmoCfDom.isElement(scope) && scope.matches(SELECTOR)) {
			initializeGallery(scope);
		}

		scope.querySelectorAll(SELECTOR).forEach(function (gallery) {
			if (window.eilmoCfDom.isElement(gallery)) {
				initializeGallery(gallery);
			}
		});
	}

	window.eilmoCfProductGallery = {
		refresh: initializeAll,
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initializeAll(document);
		});
	} else {
		initializeAll(document);
	}

	/*
	 * Elementor preview and other dynamic checkout renders can
	 * inject the widget after DOMContentLoaded.
	 */
	if ('MutationObserver' in window) {
		const observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (window.eilmoCfDom.isElement(node)) {
						initializeAll(node);
					}
				});
			});
		});

		observer.observe(document.documentElement, {
			childList: true,
			subtree: true,
		});
	}
})();
/* END src/js/frontend/product-gallery.js */

/* BEGIN src/js/frontend/quantity.js */
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
/* END src/js/frontend/quantity.js */

/* BEGIN src/js/frontend/single-product.js */
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
/* END src/js/frontend/single-product.js */

/* BEGIN src/js/frontend/multiple-products.js */
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
/* END src/js/frontend/multiple-products.js */

/* BEGIN src/js/frontend/summary.js */
/**
 * Eilmo Checkout Flow - Live Summary.
 *
 * Frontend calculations are UI previews only.
 * Normal Products, Combo Offers and Order Bumps remain
 * independent purchase contexts. Final totals must always
 * be recalculated and validated server-side.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		checkout: '[data-eilmo-checkout]',
		item: '[data-eilmo-product-item], [data-eilmo-item]',
		quantityInput: '[data-eilmo-quantity-input]',
		comboOffer: '[data-eilmo-combo-offer]',
		comboInput: '[data-eilmo-combo-input]',
		orderBumpOffer: '[data-eilmo-order-bump]',
		orderBumpInput: '[data-eilmo-order-bump-input]',
		summaryShell: '[data-eilmo-summary-shell]',
		summaryPanel: '[data-eilmo-summary-panel]',
		toggle: '[data-eilmo-summary-toggle]',
		close: '[data-eilmo-summary-close]',
		backdrop: '[data-eilmo-summary-backdrop]',
		mobileCheckoutDetailsTarget: '[data-eilmo-mobile-checkout-details-target]',
		summaryCheckoutDetails: '[data-eilmo-summary-checkout-details]',
		summaryOrderAction: '[data-eilmo-summary-order-action]',
		summaryWhatsAppAction: '[data-eilmo-summary-whatsapp-action]',
		summaryRelocationAnchor: '[data-eilmo-summary-relocation-anchor]',
		advancePayment: '[data-eilmo-advance-payment]',
		paymentType: '[data-eilmo-payment-type]',
	};

	const DATASET_KEYS = {
		'product-total': 'productTotal',
		'combo-discount': 'comboDiscount',
		'order-bump-regular-total': 'orderBumpRegularTotal',
		'order-bump-discount': 'orderBumpDiscount',
		'order-bump-total': 'orderBumpTotal',
		'automatic-discount': 'automaticDiscount',
		'coupon-discount': 'couponDiscount',
		'delivery-charge': 'deliveryCharge',
		'advance-payment': 'advanceAmount',
		'full-payment-discount': 'fullPaymentDiscount',
		'grand-total': 'grandTotal',
		'pay-now': 'payNow',
		'remaining-due': 'remainingDue',
	};

	/**
	 * Convert value to non-negative number.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toNumber(value) {
		const number =
			Number.parseFloat(
				String(
					value
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
	 * Convert value to quantity.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toQuantity(value) {
		const quantity =
			Number.parseInt(
				String(
					value
				),
				10
			);

		return Number.isFinite(
			quantity
		)
			? Math.max(
				0,
				quantity
			)
			: 0;
	}

	/**
	 * Get WooCommerce price decimals.
	 *
	 * @return {number}
	 */
	function getPriceDecimals() {
		const config =
			window.eilmoCf ||
			{};

		const currency =
			config.currency &&
			'object' ===
				typeof config.currency
				? config.currency
				: {};

		let decimals =
			Number(
				currency.decimals
			);

		if (
			!Number.isFinite(
				decimals
			)
		) {
			decimals =
				Number(
					config.priceDecimals
				);
		}

		if (
			!Number.isFinite(
				decimals
			)
		) {
			return 2;
		}

		return Math.max(
			0,
			Math.floor(
				decimals
			)
		);
	}

	/**
	 * Round monetary amount.
	 *
	 * @param {*} value Amount.
	 *
	 * @return {number}
	 */
	function roundMoney(value) {
		const multiplier =
			Math.pow(
				10,
				getPriceDecimals()
			);

		return (
			Math.round(
				(
					toNumber(
						value
					) +
					Number.EPSILON
				) *
					multiplier
			) /
			multiplier
		);
	}

	/**
	 * Decode HTML entities.
	 *
	 * @param {string} value Value.
	 *
	 * @return {string}
	 */
	function decodeHtmlEntities(value) {
		if (
			'string' !==
				typeof value
		) {
			return '';
		}

		const textarea =
			document.createElement(
				'textarea'
			);

		textarea.innerHTML =
			value;

		return textarea.value
			.replace(
				/\u00a0/g,
				' '
			)
			.trim();
	}

	/**
	 * Get currency configuration.
	 *
	 * @return {Object}
	 */
	function getCurrencyConfig() {
		const config =
			window.eilmoCf &&
			window.eilmoCf.currency
				? window.eilmoCf.currency
				: {};

		return {
			symbol:
				decodeHtmlEntities(
					'string' ===
						typeof config.symbol
						? config.symbol
						: '৳'
				),

			position:
				'string' ===
					typeof config.position
					? config.position
					: 'left',

			decimals:
				Number.isInteger(
					config.decimals
				)
					? config.decimals
					: getPriceDecimals(),

			decimalSeparator:
				'string' ===
					typeof config.decimalSeparator
					? decodeHtmlEntities(
						config.decimalSeparator
					)
					: '.',

			thousandSeparator:
				'string' ===
					typeof config.thousandSeparator
					? decodeHtmlEntities(
						config.thousandSeparator
					)
					: ',',
		};
	}

	/**
	 * Format number.
	 *
	 * @param {number} amount Amount.
	 *
	 * @return {string}
	 */
	function formatNumber(amount) {
		const config =
			getCurrencyConfig();

		const parts =
			roundMoney(
				amount
			)
				.toFixed(
					config.decimals
				)
				.split(
					'.'
				);

		parts[0] =
			parts[0].replace(
				/\B(?=(\d{3})+(?!\d))/g,
				config.thousandSeparator
			);

		if (config.decimals > 0 && parts.length > 1) {
			const decimals = parts[1].replace(/0+$/, '');
			return parts[0] + (decimals ? config.decimalSeparator + decimals : '');
		}

		return parts[0];
	}

	/**
	 * Format WooCommerce-style currency.
	 *
	 * @param {number} amount Amount.
	 *
	 * @return {string}
	 */
	function formatPrice(amount) {
		const config =
			getCurrencyConfig();

		const formatted =
			formatNumber(
				amount
			);

		switch (
			config.position
		) {
			case 'left':

				return (
					config.symbol +
					formatted
				);

			case 'left_space':

				return (
					config.symbol +
					'\u00A0' +
					formatted
				);

			case 'right_space':

				return (
					formatted +
					'\u00A0' +
					config.symbol
				);

			case 'right':
			default:

				return (
					formatted +
					config.symbol
				);
		}
	}

	/**
	 * Get localized Free label.
	 *
	 * @return {string}
	 */
	function getFreeLabel() {
		if (
			window.eilmoCf &&
			window.eilmoCf.i18n &&
			'string' ===
				typeof window.eilmoCf
					.i18n.free
		) {
			return window.eilmoCf
				.i18n.free;
		}

		return 'Free';
	}

	/**
	 * Normalize amount key.
	 *
	 * @param {string} key Key.
	 *
	 * @return {string}
	 */
	function normalizeAmountKey(key) {
		return String(
			key ||
				''
		);
	}

	/**
	 * Determine whether amount is a discount.
	 *
	 * @param {string} key Key.
	 *
	 * @return {boolean}
	 */
	function isDiscountAmount(key) {
		return [
			'combo-discount',
			'order-bump-discount',
			'automatic-discount',
			'coupon-discount',
			'full-payment-discount',
		].includes(
			normalizeAmountKey(
				key
			)
		);
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
	 * Get checkout from event.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {HTMLElement|null}
	 */
	function getCheckoutFromEvent(event) {
		if (
			event.detail &&
			window.eilmoCfDom.isElement(event.detail.checkout)
		) {
			return event.detail
				.checkout;
		}

		return window.eilmoCfDom.isElement(event.target)
				? getCheckout(
					event.target
				)
				: null;
	}

	/**
	 * Get Summary shell.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {HTMLElement|null}
	 */
	function getSummaryShell(checkout) {
		const shell =
			checkout.querySelector(
				SELECTORS.summaryShell
			);

		return window.eilmoCfDom.isElement(shell)
				? shell
				: null;
	}

	/**
	 * Get amount element.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      key      Key.
	 *
	 * @return {HTMLElement|null}
	 */
	function getAmountElement(
		checkout,
		key
	) {
		const element =
			checkout.querySelector(
				'[data-eilmo-summary-amount="' +
					normalizeAmountKey(
						key
					) +
					'"]'
			);

		return window.eilmoCfDom.isElement(element)
				? element
				: null;
	}

	/**
	 * Get amount from checkout dataset.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      key      Key.
	 *
	 * @return {number}
	 */
	function getDatasetAmount(
		checkout,
		key
	) {
		const datasetKey =
			DATASET_KEYS[
				normalizeAmountKey(
					key
				)
			];

		if (!datasetKey) {
			return 0;
		}

		return toNumber(
			checkout.dataset[
				datasetKey
			] ||
				0
		);
	}

	/**
	 * Store amount in checkout dataset.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      key      Key.
	 * @param {number}      amount   Amount.
	 *
	 * @return {void}
	 */
	function setDatasetAmount(
		checkout,
		key,
		amount
	) {
		const datasetKey =
			DATASET_KEYS[
				normalizeAmountKey(
					key
				)
			];

		if (!datasetKey) {
			return;
		}

		checkout.dataset[
			datasetKey
		] =
			String(
				roundMoney(
					amount
				)
			);
	}

	/**
	 * Get stored amount.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      key      Key.
	 *
	 * @return {number}
	 */
	function getAmount(
		checkout,
		key
	) {
		const normalizedKey =
			normalizeAmountKey(
				key
			);

		const element =
			getAmountElement(
				checkout,
				normalizedKey
			);

		if (element) {
			return toNumber(
				element.dataset.value ||
					0
			);
		}

		return getDatasetAmount(
			checkout,
			normalizedKey
		);
	}

	/**
	 * Determine whether selected delivery is free.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function deliveryIsFree(checkout) {
		return (
			'string' ===
				typeof checkout.dataset
					.deliveryMethod &&
			'' !==
				checkout.dataset
					.deliveryMethod &&
			'yes' ===
				checkout.dataset
					.deliveryIsFree
		);
	}

	/**
	 * Format Summary amount.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      key      Key.
	 * @param {number}      amount   Amount.
	 *
	 * @return {string}
	 */
	function formatSummaryAmount(
		checkout,
		key,
		amount
	) {
		const normalizedKey =
			normalizeAmountKey(
				key
			);

		const safeAmount =
			roundMoney(
				amount
			);

		if (
			'delivery-charge' ===
				normalizedKey &&
			deliveryIsFree(
				checkout
			)
		) {
			return getFreeLabel();
		}

		const formatted =
			formatPrice(
				safeAmount
			);

		if (
			isDiscountAmount(
				normalizedKey
			) &&
			safeAmount > 0
		) {
			return (
				'\u2212' +
				formatted
			);
		}

		return formatted;
	}

	/**
	 * Set Summary amount.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      key      Key.
	 * @param {number}      amount   Amount.
	 *
	 * @return {void}
	 */
	function setAmount(
		checkout,
		key,
		amount
	) {
		const normalizedKey =
			normalizeAmountKey(
				key
			);

		const safeAmount =
			roundMoney(
				amount
			);

		const formatted =
			formatSummaryAmount(
				checkout,
				normalizedKey,
				safeAmount
			);

		setDatasetAmount(
			checkout,
			normalizedKey,
			safeAmount
		);

		if (isDiscountAmount(normalizedKey) || ['advance-payment', 'pay-now', 'remaining-due'].includes(normalizedKey)) {
			const keepZeroBreakdownVisible =
				checkout.dataset.emptySelection === 'yes' &&
				['pay-now', 'remaining-due'].includes(normalizedKey);
			checkout
				.querySelectorAll(
					'[data-eilmo-summary-row="' +
						normalizedKey +
						'"]'
				)
				.forEach(function (row) {
					if (window.eilmoCfDom.isElement(row)) {
						row.hidden = !keepZeroBreakdownVisible && safeAmount <= 0;
					}
				});
		}

		[
			'data-eilmo-summary-amount',
			'data-eilmo-mobile-summary-amount',
		].forEach(
			function (attribute) {
				checkout
					.querySelectorAll(
						'[' +
							attribute +
							'="' +
							normalizedKey +
							'"]'
					)
					.forEach(
						function (element) {
							if (
								!(
									window.eilmoCfDom.isElement(element)
								)
							) {
								return;
							}

							element.dataset.value =
								String(
									safeAmount
								);

							element.textContent = window.eilmoCfLanguage ? window.eilmoCfLanguage.digits(formatted, checkout.dataset.checkoutLanguage) : formatted;
						}
					);
			}
		);
	}

	/**
	 * Calculate normal selected product total.
	 *
	 * Combo and Order Bump contexts are excluded here.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function calculateProductTotal(checkout) {
		let total =
			0;

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
					 * Normal products only.
					 *
					 * Same WooCommerce product can exist
					 * again as Combo or Order Bump.
					 */
					if (
						item.closest(
							SELECTORS.comboOffer
						) ||
						item.closest(
							SELECTORS.orderBumpOffer
						)
					) {
						return;
					}

					const input =
						item.querySelector(
							SELECTORS.quantityInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						)
					) {
						return;
					}

					const quantity =
						toQuantity(
							input.value
						);

					if (
						quantity <= 0
					) {
						return;
					}

					let price =
						toNumber(
							item.dataset.price
						);

					if (
						price <= 0
					) {
						price =
							toNumber(
								item.dataset
									.productPrice
							);
					}

					if (
						price <= 0
					) {
						price =
							toNumber(
								input.dataset.price
							);
					}

					if (
						price <= 0
					) {
						return;
					}

					total +=
						price *
							quantity;
				}
			);

		return roundMoney(
			total
		);
	}

	/**
	 * Calculate promotional source totals.
	 *
	 * Used for Combo Offers and Order Bumps.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {Object}      config   Source configuration.
	 *
	 * @return {Object}
	 */
	function calculatePromotionalTotals(
		checkout,
		config
	) {
		const totals = {
			selectedCount:
				0,

			regularTotal:
				0,

			discount:
				0,

			payableTotal:
				0,

			automaticEligibleTotal:
				0,

			couponEligibleTotal:
				0,

			fullPaymentEligibleRegularTotal:
				0,

			fullPaymentEligibleTotal:
				0,

			automaticCouponOverlapTotal:
				0,

			automaticFullPaymentOverlapTotal:
				0,

			fullPaymentCouponOverlapTotal:
				0,

			automaticFullPaymentCouponOverlapTotal:
				0,
		};

		checkout
			.querySelectorAll(
				config.offerSelector
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
							config.inputSelector
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						) ||
						!input.checked ||
						input.disabled
					) {
						return;
					}

					if (
						config.checkAvailability &&
						'no' ===
							offer.dataset.available
					) {
						return;
					}

					const regularTotal =
						roundMoney(
							offer.dataset
								.regularTotal ||
								0
						);

					const discount =
						roundMoney(
							Math.min(
								toNumber(
									offer.dataset[
										config.discountKey
									] ||
										0
								),
								regularTotal
							)
						);

					const fallbackTotal =
						roundMoney(
							Math.max(
								0,
								regularTotal -
									discount
							)
						);

					const rawPayable =
						undefined !==
							offer.dataset[
								config.payableKey
							] &&
						'' !==
							String(
								offer.dataset[
									config.payableKey
								]
							).trim()
							? offer.dataset[
								config.payableKey
							]
							: fallbackTotal;

					const payableTotal =
						roundMoney(
							Math.min(
								regularTotal,
								toNumber(
									rawPayable
								)
							)
						);

					/*
					 * Automatic Discount:
					 *
					 * Combo default      = no.
					 * Order Bump default = no.
					 */
					const automaticEligible =
						'yes' ===
							offer.dataset
								.applyAutomaticDiscount;

					/*
					 * Coupon:
					 *
					 * Combo default      = yes.
					 * Order Bump default = no.
					 */
					const couponEligible =
						config.couponDefaultYes
							? (
								'no' !==
									offer.dataset
										.applyCoupon
							)
							: (
								'yes' ===
									offer.dataset
										.applyCoupon
							);

					/*
					 * Full Payment Discount:
					 *
					 * Combo default      = yes.
					 * Order Bump default = yes.
					 *
					 * Only explicit "no" excludes.
					 */
					const fullPaymentEligible =
						'no' !==
							offer.dataset
								.applyFullPaymentDiscount;

					totals.selectedCount +=
						1;

					totals.regularTotal +=
						regularTotal;

					totals.discount +=
						discount;

					totals.payableTotal +=
						payableTotal;

					if (automaticEligible) {
						totals
							.automaticEligibleTotal +=
								payableTotal;
					}

					if (couponEligible) {
						totals
							.couponEligibleTotal +=
								payableTotal;
					}

					if (fullPaymentEligible) {
						totals
							.fullPaymentEligibleRegularTotal +=
								regularTotal;

						totals
							.fullPaymentEligibleTotal +=
								payableTotal;
					}

					if (
						automaticEligible &&
						couponEligible
					) {
						totals
							.automaticCouponOverlapTotal +=
								payableTotal;
					}

					if (
						automaticEligible &&
						fullPaymentEligible
					) {
						totals
							.automaticFullPaymentOverlapTotal +=
								payableTotal;
					}

					if (
						fullPaymentEligible &&
						couponEligible
					) {
						totals
							.fullPaymentCouponOverlapTotal +=
								payableTotal;
					}

					if (
						automaticEligible &&
						fullPaymentEligible &&
						couponEligible
					) {
						totals
							.automaticFullPaymentCouponOverlapTotal +=
								payableTotal;
					}
				}
			);

		Object.keys(
			totals
		).forEach(
			function (key) {
				if (
					'selectedCount' ===
						key
				) {
					return;
				}

				totals[key] =
					roundMoney(
						totals[key]
					);
			}
		);

		totals.discount =
			Math.min(
				totals.discount,
				totals.regularTotal
			);

		totals.payableTotal =
			Math.min(
				totals.payableTotal,
				totals.regularTotal
			);

		return totals;
	}

	/**
	 * Calculate Combo totals.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function calculateComboTotals(checkout) {
		const totals =
			calculatePromotionalTotals(
				checkout,
				{
					offerSelector:
						SELECTORS.comboOffer,

					inputSelector:
						SELECTORS.comboInput,

					discountKey:
						'comboDiscount',

					payableKey:
						'comboTotal',

					couponDefaultYes:
						true,

					checkAvailability:
						true,
				}
			);

		return {
			...totals,

			comboTotal:
				totals.payableTotal,
		};
	}

	/**
	 * Calculate Order Bump totals.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function calculateOrderBumpTotals(checkout) {
		const totals =
			calculatePromotionalTotals(
				checkout,
				{
					offerSelector:
						SELECTORS.orderBumpOffer,

					inputSelector:
						SELECTORS.orderBumpInput,

					discountKey:
						'discount',

					payableKey:
						'bumpTotal',

					couponDefaultYes:
						false,

					checkAvailability:
						false,
				}
			);

		return {
			...totals,

			bumpTotal:
				totals.payableTotal,
		};
	}

	/**
	 * Determine whether Payment Options section exists.
	 *
	 * Historical markup/API names still use
	 * "advance payment" for backward compatibility.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function hasAdvancePayment(checkout) {
		return Boolean(
			checkout.querySelector(
				SELECTORS.advancePayment
			)
		);
	}

	/**
	 * Normalize Payment Option type.
	 *
	 * @param {*} value Value.
	 *
	 * @return {string}
	 */
	function normalizePaymentType(value) {
		const type =
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
			type
		)
			? type
			: '';
	}

	/**
	 * Get selected Cash on Delivery / Advance / Full
	 * Payment Option.
	 *
	 * Priority:
	 *
	 * 1. Checked Payment Option radio.
	 * 2. Shared checkout dataset.
	 * 3. Advance Payment public API result.
	 * 4. Full Payment compatibility fallback.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getPaymentType(checkout) {
		const section =
			checkout.querySelector(
				SELECTORS.advancePayment
			);

		if (
			window.eilmoCfDom.isElement(section)
		) {
			const selected =
				section.querySelector(
					SELECTORS.paymentType +
						':checked'
				);

			if (
				window.eilmoCfDom.isElement(selected, 'INPUT')
			) {
				const selectedType =
					normalizePaymentType(
						selected.value
					);

				if (selectedType) {
					return selectedType;
				}
			}
		}

		const datasetType =
			normalizePaymentType(
				checkout.dataset
					.paymentType ||
					''
			);

		if (datasetType) {
			return datasetType;
		}

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
				'object' ===
					typeof result
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

		return 'full';
	}

	/**
	 * Determine whether Cash on Delivery is selected.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function isCashOnDelivery(checkout) {
		return (
			'cash_on_delivery' ===
				getPaymentType(
					checkout
				)
		);
	}

	/**
	 * Allocate discount proportionally to an eligible
	 * overlapping source.
	 *
	 * @param {number} discount    Discount.
	 * @param {number} sourceTotal Source total.
	 * @param {number} subsetTotal Overlap total.
	 *
	 * @return {number}
	 */
	function allocateDiscountShare(
		discount,
		sourceTotal,
		subsetTotal
	) {
		const safeDiscount =
			roundMoney(
				discount
			);

		const safeSourceTotal =
			roundMoney(
				sourceTotal
			);

		const safeSubsetTotal =
			roundMoney(
				Math.min(
					toNumber(
						subsetTotal
					),
					safeSourceTotal
				)
			);

		if (
			safeDiscount <= 0 ||
			safeSourceTotal <= 0 ||
			safeSubsetTotal <= 0
		) {
			return 0;
		}

		return roundMoney(
			Math.min(
				safeSubsetTotal,
				safeDiscount *
					(
						safeSubsetTotal /
							safeSourceTotal
					)
			)
		);
	}

	/**
	 * Calculate current Summary.
	 *
	 * Calculation order:
	 *
	 * Normal Product
	 * +
	 * Combo
	 * - Combo Own Discount
	 * +
	 * Order Bump
	 * - Order Bump Own Discount
	 * ↓
	 * Automatic Discount
	 * ↓
	 * Coupon
	 * ↓
	 * Delivery
	 * ↓
	 * Full Payment Discount
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function calculateSummary(checkout) {
		const hasProducts = hasSelectedProducts(checkout);
		const baseProductTotal =
			calculateProductTotal(
				checkout
			);

		const combo =
			calculateComboTotals(
				checkout
			);

		const orderBump =
			calculateOrderBumpTotals(
				checkout
			);

		/*
		 * Regular Product Total before promotional
		 * purchase-context discounts.
		 */
		const productTotal =
			roundMoney(
				baseProductTotal +
					combo.regularTotal +
					orderBump.regularTotal
			);

		const comboOfferDiscount =
			roundMoney(
				Math.min(
					combo.discount,
					combo.regularTotal
				)
			);

		const orderBumpDiscount =
			roundMoney(
				Math.min(
					orderBump.discount,
					orderBump.regularTotal
				)
			);

		/*
		 * Actual payable product amount after Combo and
		 * Order Bump own offer pricing.
		 */
		const afterOfferDiscounts =
			roundMoney(
				Math.max(
					0,
					baseProductTotal +
						combo.comboTotal +
						orderBump.bumpTotal
				)
			);

		/*
		 * --------------------------------------
		 * Automatic Discount
		 * --------------------------------------
		 *
		 * Normal:
		 * yes.
		 *
		 * Combo:
		 * apply_automatic_discount.
		 *
		 * Order Bump:
		 * apply_automatic_discount.
		 */
		const automaticDiscountBase =
			roundMoney(
				baseProductTotal +
					combo
						.automaticEligibleTotal +
					orderBump
						.automaticEligibleTotal
			);

		const automaticDiscount =
			roundMoney(
				Math.min(
					getAmount(
						checkout,
						'automatic-discount'
					),
					automaticDiscountBase,
					afterOfferDiscounts
				)
			);

		const afterAutomaticDiscount =
			roundMoney(
				Math.max(
					0,
					afterOfferDiscounts -
						automaticDiscount
				)
			);

		/*
		 * --------------------------------------
		 * Coupon Discount
		 * --------------------------------------
		 *
		 * Normal:
		 * yes.
		 *
		 * Combo:
		 * apply_coupon, default yes.
		 *
		 * Order Bump:
		 * apply_coupon, default no.
		 */
		const couponBaseBeforeAutomatic =
			roundMoney(
				baseProductTotal +
					combo
						.couponEligibleTotal +
					orderBump
						.couponEligibleTotal
			);

		/*
		 * Only Automatic Discount belonging to
		 * Coupon-eligible contexts may reduce the
		 * Coupon preview basis.
		 */
		const automaticCouponOverlapBase =
			roundMoney(
				baseProductTotal +
					combo
						.automaticCouponOverlapTotal +
					orderBump
						.automaticCouponOverlapTotal
			);

		const couponAutomaticDiscount =
			allocateDiscountShare(
				automaticDiscount,
				automaticDiscountBase,
				automaticCouponOverlapBase
			);

		const couponDiscountBase =
			roundMoney(
				Math.max(
					0,
					couponBaseBeforeAutomatic -
						couponAutomaticDiscount
				)
			);

		const couponDiscount =
			roundMoney(
				Math.min(
					getAmount(
						checkout,
						'coupon-discount'
					),
					couponDiscountBase,
					afterAutomaticDiscount
				)
			);

		const discountedProductTotal =
			roundMoney(
				Math.max(
					0,
					afterAutomaticDiscount -
						couponDiscount
				)
			);

		const totalDiscount =
			roundMoney(
				Math.min(
					productTotal,
					comboOfferDiscount +
						orderBumpDiscount +
						automaticDiscount +
						couponDiscount
				)
			);

		/*
		 * Delivery.
		 */
		const deliveryCharge =
			hasProducts
				? roundMoney(
					getAmount(
						checkout,
						'delivery-charge'
					)
				)
				: 0;

		const paymentType =
			getPaymentType(
				checkout
			);

		/*
		 * Advance amount.
		 *
		 * Cash on Delivery never has an advance amount.
		 * A stale Advance preview may still exist in the
		 * DOM/dataset after switching options, therefore
		 * the effective Summary amount is forced to zero.
		 */
		const storedAdvancePayment =
			roundMoney(
				getAmount(
					checkout,
					'advance-payment'
				)
			);

		const advancePayment =
			!hasProducts || 'cash_on_delivery' ===
				paymentType
					? 0
					: storedAdvancePayment;

		/*
		 * Base Grand Total excludes Full Payment
		 * Discount so the discount never discounts itself.
		 */
		const baseGrandTotal =
			roundMoney(
				discountedProductTotal +
					deliveryCharge
			);

		let fullPaymentDiscount =
			roundMoney(
				getAmount(
					checkout,
					'full-payment-discount'
				)
			);

		if (
			'full' !==
				paymentType
		) {
			fullPaymentDiscount =
				0;
		}

		/*
		 * advance.js calculates the source-aware
		 * Full Payment Discount.
		 *
		 * If its eligible grand-total snapshot exists,
		 * use it as an additional stale-preview guard.
		 */
		const hasFullPaymentEligibleGrandTotal =
			'string' ===
				typeof checkout.dataset
					.fullPaymentEligibleGrandTotal;

		const fullPaymentEligibleGrandTotal =
			hasFullPaymentEligibleGrandTotal
				? toNumber(
					checkout.dataset
						.fullPaymentEligibleGrandTotal
				)
				: baseGrandTotal;

		fullPaymentDiscount =
			roundMoney(
				Math.min(
					fullPaymentDiscount,
					baseGrandTotal,
					fullPaymentEligibleGrandTotal
				)
			);

		const grandTotal =
			roundMoney(
				Math.max(
					0,
					baseGrandTotal -
						fullPaymentDiscount
				)
			);

		const advanceRuleType =
			'string' ===
				typeof checkout.dataset
					.advanceRuleType
				? checkout.dataset
					.advanceRuleType
				: '';

		let payNow =
			grandTotal;

		/*
		 * Cash on Delivery:
		 *
		 * - Nothing is payable during checkout.
		 * - The full authoritative Grand Total remains due.
		 * - Full Payment Discount is already forced to zero
		 *   above because paymentType is not "full".
		 */
		if (
			'cash_on_delivery' ===
				paymentType
		) {
			payNow =
				0;

		} else if (
			hasAdvancePayment(
				checkout
			)
		) {
			if (
				'full' ===
					paymentType
			) {
				payNow =
					grandTotal;

			} else if (
				'no_advance' ===
					advanceRuleType
			) {
				payNow =
					0;

			} else if (
				advancePayment > 0
			) {
				payNow =
					Math.min(
						advancePayment,
						grandTotal
					);

			} else if (
				'yes' ===
					checkout.dataset
						.advanceReady
			) {
				payNow =
					Math.min(
						toNumber(
							checkout.dataset
								.payNow ||
								0
						),
						grandTotal
					);
			}
		}

		payNow =
			roundMoney(
				payNow
			);

		const remainingDue =
			roundMoney(
				Math.max(
					0,
					grandTotal -
						payNow
				)
			);

		return {
			baseProductTotal:
				baseProductTotal,

			base_product_total:
				baseProductTotal,

			/*
			 * Combo.
			 */
			comboSelectedCount:
				combo.selectedCount,

			combo_selected_count:
				combo.selectedCount,

			comboRegularTotal:
				combo.regularTotal,

			combo_regular_total:
				combo.regularTotal,

			comboTotal:
				combo.comboTotal,

			combo_total:
				combo.comboTotal,

			comboOfferDiscount:
				comboOfferDiscount,

			combo_offer_discount:
				comboOfferDiscount,

			/*
			 * Canonical Combo own discount aliases.
			 *
			 * This is no longer an alias for
			 * Automatic Discount.
			 */
			comboDiscount:
				comboOfferDiscount,

			combo_discount:
				comboOfferDiscount,

			comboAutomaticEligibleTotal:
				combo
					.automaticEligibleTotal,

			combo_automatic_eligible_total:
				combo
					.automaticEligibleTotal,

			comboCouponEligibleTotal:
				combo
					.couponEligibleTotal,

			combo_coupon_eligible_total:
				combo
					.couponEligibleTotal,

			comboFullPaymentEligibleRegularTotal:
				combo
					.fullPaymentEligibleRegularTotal,

			combo_full_payment_eligible_regular_total:
				combo
					.fullPaymentEligibleRegularTotal,

			comboFullPaymentEligibleTotal:
				combo
					.fullPaymentEligibleTotal,

			combo_full_payment_eligible_total:
				combo
					.fullPaymentEligibleTotal,

			/*
			 * Order Bump.
			 */
			orderBumpSelectedCount:
				orderBump
					.selectedCount,

			order_bump_selected_count:
				orderBump
					.selectedCount,

			orderBumpRegularTotal:
				orderBump
					.regularTotal,

			order_bump_regular_total:
				orderBump
					.regularTotal,

			orderBumpDiscount:
				orderBumpDiscount,

			order_bump_discount:
				orderBumpDiscount,

			orderBumpTotal:
				orderBump
					.bumpTotal,

			order_bump_total:
				orderBump
					.bumpTotal,

			orderBumpAutomaticEligibleTotal:
				orderBump
					.automaticEligibleTotal,

			order_bump_automatic_eligible_total:
				orderBump
					.automaticEligibleTotal,

			orderBumpCouponEligibleTotal:
				orderBump
					.couponEligibleTotal,

			order_bump_coupon_eligible_total:
				orderBump
					.couponEligibleTotal,

			orderBumpFullPaymentEligibleRegularTotal:
				orderBump
					.fullPaymentEligibleRegularTotal,

			order_bump_full_payment_eligible_regular_total:
				orderBump
					.fullPaymentEligibleRegularTotal,

			orderBumpFullPaymentEligibleTotal:
				orderBump
					.fullPaymentEligibleTotal,

			order_bump_full_payment_eligible_total:
				orderBump
					.fullPaymentEligibleTotal,

			/*
			 * Product totals.
			 */
			productTotal:
				productTotal,

			product_total:
				productTotal,

			afterOfferDiscounts:
				afterOfferDiscounts,

			after_offer_discounts:
				afterOfferDiscounts,

			/*
			 * Automatic Discount.
			 */
			automaticDiscountBase:
				automaticDiscountBase,

			automatic_discount_base:
				automaticDiscountBase,

			automaticDiscount:
				automaticDiscount,

			automatic_discount:
				automaticDiscount,

			/*
			 * Coupon.
			 */
			couponBaseBeforeAutomatic:
				couponBaseBeforeAutomatic,

			coupon_base_before_automatic:
				couponBaseBeforeAutomatic,

			couponAutomaticDiscount:
				couponAutomaticDiscount,

			coupon_automatic_discount:
				couponAutomaticDiscount,

			couponDiscountBase:
				couponDiscountBase,

			coupon_discount_base:
				couponDiscountBase,

			couponDiscount:
				couponDiscount,

			coupon_discount:
				couponDiscount,

			totalDiscount:
				totalDiscount,

			total_discount:
				totalDiscount,

			discountedProductTotal:
				discountedProductTotal,

			discounted_product_total:
				discountedProductTotal,

			/*
			 * Delivery / payment.
			 */
			deliveryCharge:
				deliveryCharge,

			delivery_charge:
				deliveryCharge,

			paymentType:
				paymentType,

			payment_type:
				paymentType,

			isCashOnDelivery:
				'cash_on_delivery' ===
					paymentType,

			is_cash_on_delivery:
				'cash_on_delivery' ===
					paymentType,

			isAdvancePayment:
				'advance' ===
					paymentType,

			is_advance_payment:
				'advance' ===
					paymentType,

			isFullPayment:
				'full' ===
					paymentType,

			is_full_payment:
				'full' ===
					paymentType,

			advancePayment:
				advancePayment,

			advance_payment:
				advancePayment,

			advanceRuleType:
				advanceRuleType,

			advance_rule_type:
				advanceRuleType,

			fullPaymentDiscount:
				fullPaymentDiscount,

			full_payment_discount:
				fullPaymentDiscount,

			fullPaymentEligibleGrandTotal:
				fullPaymentEligibleGrandTotal,

			full_payment_eligible_grand_total:
				fullPaymentEligibleGrandTotal,

			baseGrandTotal:
				baseGrandTotal,

			base_grand_total:
				baseGrandTotal,

			grandTotal:
				grandTotal,

			grand_total:
				grandTotal,

			payNow:
				payNow,

			pay_now:
				payNow,

			remainingDue:
				remainingDue,

			remaining_due:
				remainingDue,
		};
	}

	/**
	 * Render currently selected normal product/variation items above totals.
	 *
	 * @param {HTMLElement} checkout Checkout wrapper.
	 */
	function renderSelectedItems(checkout) {
		const container = checkout.querySelector('[data-eilmo-summary-items]');
		if (!container) return;
		const list = container.querySelector('[data-eilmo-summary-items-list]');
		if (!list) return;

		const showThumbnail = container.dataset.showThumbnail !== 'no';
		const showVariation = container.dataset.showVariation !== 'no';
		const showQuantity = false;
		const showPrice = container.dataset.showPrice !== 'no';
		const selected = [];

		checkout.querySelectorAll(SELECTORS.item).forEach(function (item) {
			/*
			 * Normal checkout products only. Combo Offers and Order Bumps use
			 * their own summary rows even if nested markup exposes an item adapter.
			 */
			if (
				item.closest(SELECTORS.comboOffer) ||
				item.closest(SELECTORS.orderBumpOffer)
			) {
				return;
			}

			const input = item.querySelector(SELECTORS.quantityInput);
			if (!input) return;
			const quantity = toQuantity(input.value);
			if (quantity <= 0) return;
			selected.push({
				productId: parseInt(item.dataset.productId || '0', 10),
				variationId: parseInt(item.dataset.variationId || '0', 10),
				name: String(item.dataset.itemName || '').trim(),
				variation: String(item.dataset.itemVariation || '').trim(),
				image: String(item.dataset.itemImage || '').trim(),
				price: toNumber(item.dataset.price || 0),
				quantity: quantity,
				locked: item.dataset.summaryQuantityLocked === 'yes',
			});
		});

		// Itemize selected offers separately; their quantity belongs to the
		// offer, so ordinary product quantity buttons must not edit them.
		checkout.querySelectorAll(SELECTORS.comboOffer + ', ' + SELECTORS.orderBumpOffer).forEach(function (offer) {
			const input = offer.querySelector(SELECTORS.comboInput + ', ' + SELECTORS.orderBumpInput);
			if (!input || !input.checked) return;
			const isCombo = offer.matches(SELECTORS.comboOffer);
			const title = offer.querySelector(isCombo ? '.eilmo-cf-combo-offer__title' : '.eilmo-cf-special-discount-card__title');
			const components = Array.from(offer.querySelectorAll('.eilmo-cf-combo-offer__item')).map(function (item) {
				const name = item.querySelector('.eilmo-cf-combo-offer__item-title');
				return (name ? name.textContent.trim() : '') + ' × ' + (item.dataset.quantity || '1');
			}).join(' + ');
			if (!isCombo && 'free_gift' === offer.dataset.offerType) return;
			if (!isCombo && 'product' !== offer.dataset.offerType) return;
			selected.push({
				name: title ? title.textContent.trim() : 'Offer',
				variation: components,
				quantity: 1,
				price: toNumber(isCombo ? offer.dataset.comboTotal : offer.dataset.bumpTotal),
				locked: true,
				isCombo: isCombo,
				comboId: isCombo ? String(offer.dataset.comboId || '').trim() : '',
			});
		});

		list.innerHTML = '';
		container.hidden = selected.length === 0;
		selected.forEach(function (entry) {
			const row = document.createElement('div');
			row.className = 'eilmo-cf-summary-item';
			row.dataset.productId = String(entry.productId);
			row.dataset.variationId = String(entry.variationId);

			if (showThumbnail && entry.image) {
				const img = document.createElement('img');
				img.className = 'eilmo-cf-summary-item__image';
				img.src = entry.image;
				img.alt = '';
				img.loading = 'lazy';
				row.appendChild(img);
			}

			const info = document.createElement('div');
			info.className = 'eilmo-cf-summary-item__info';
			const name = document.createElement('div');
			name.className = 'eilmo-cf-summary-item__name';
			name.textContent = (entry.name || 'Product') + ((entry.locked || !showQuantity) ? ' × ' + entry.quantity : '');
			info.appendChild(name);
			if (showVariation && entry.variation) {
				const variation = document.createElement('div');
				variation.className = 'eilmo-cf-summary-item__variation';
				variation.textContent = entry.variation;
				info.appendChild(variation);
			}
			row.appendChild(info);

			if (showQuantity && !entry.locked) {
				const qty = document.createElement('div');
				qty.className = 'eilmo-cf-summary-item__quantity';
				qty.innerHTML = '<button type="button" data-eilmo-summary-qty-minus aria-label="Decrease quantity"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14"/></svg></button><span>' + entry.quantity + '</span><button type="button" data-eilmo-summary-qty-plus aria-label="Increase quantity"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5v14"/><path d="M5 12h14"/></svg></button>';
				if (window.eilmoCfLanguage) qty.querySelectorAll('button').forEach(button => button.setAttribute('aria-label', window.eilmoCfLanguage.copy(checkout,button.getAttribute('aria-label'))));
				row.appendChild(qty);
			}

			if (showPrice) {
				const price = document.createElement('strong');
				price.className = 'eilmo-cf-summary-item__price';
				price.textContent = window.eilmoCfLanguage ? window.eilmoCfLanguage.digits(formatPrice(entry.price * entry.quantity),checkout.dataset.checkoutLanguage) : formatPrice(entry.price * entry.quantity);
				row.appendChild(price);
			}

			if (entry.isCombo && entry.comboId) {
				const remove = document.createElement('button');
				remove.type = 'button';
				remove.className = 'eilmo-cf-summary-item__remove';
				remove.setAttribute('data-eilmo-summary-combo-remove', '');
				remove.dataset.comboId = entry.comboId;
				remove.setAttribute('aria-label', 'Remove combo');
				remove.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>';
				if (window.eilmoCfLanguage) {
					remove.setAttribute('aria-label', window.eilmoCfLanguage.copy(checkout, 'Remove combo'));
				}
				row.appendChild(remove);
			}

			list.appendChild(row);
		});
	}

	/**
	 * Handle quantity buttons rendered inside Order Summary.
	 *
	 * @param {MouseEvent} event Click event.
	 */
	function handleSummaryItemQuantity(event) {
		if (!(window.eilmoCfDom.isElement(event.target))) return;

		const comboRemove = event.target.closest('[data-eilmo-summary-combo-remove]');
		if (comboRemove) {
			const checkout = comboRemove.closest(SELECTORS.checkout);
			const comboId = String(comboRemove.dataset.comboId || '').trim();
			if (checkout && comboId && window.eilmoCfComboOffers && typeof window.eilmoCfComboOffers.deselect === 'function') {
				event.preventDefault();
				window.eilmoCfComboOffers.deselect(checkout, comboId, true);
			}
			return;
		}

	}

	/**
	 * Update Summary UI and shared checkout state.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function hasSelectedProducts(checkout) {
		return Array.from(checkout.querySelectorAll(SELECTORS.quantityInput)).some(input => !input.disabled && toQuantity(input.value) > 0)
			|| Array.from(checkout.querySelectorAll(SELECTORS.comboInput)).some(input => input.checked && !input.disabled);
	}

	function updateSpecialRewardPresentation(checkout, values) {
		const gifts = [];
		checkout.querySelectorAll('[data-eilmo-order-bump][data-offer-type="free_gift"]').forEach(function (offer) {
			const input = offer.querySelector(SELECTORS.orderBumpInput);
			if (!input || !input.checked || input.disabled) return;
			const title = String(offer.dataset.rewardTitle || '').trim();
			if (title && !gifts.includes(title)) gifts.push(title);
		});

		const giftRow = checkout.querySelector('[data-eilmo-summary-row="free-gift"]');
		const giftText = checkout.querySelector('[data-eilmo-summary-text="free-gift"]');
		if (giftRow instanceof HTMLElement) { giftRow.hidden = gifts.length === 0; }
		if (giftText instanceof HTMLElement) { giftText.textContent = gifts.length ? gifts.join(', ') + ' — FREE' : ''; }

	}

	function updateSummary(checkout) {
		renderSelectedItems(checkout);
		const hasProducts = hasSelectedProducts(checkout);
		checkout.dataset.emptySelection = hasProducts ? 'no' : 'yes';
		checkout.querySelectorAll('[data-eilmo-order-submit]').forEach(function (button) {
			button.disabled = !hasProducts || checkout.dataset.fraudBlocked === 'yes' || button.getAttribute('aria-busy') === 'true';
		});

		const values = calculateSummary(checkout);
		updateSpecialRewardPresentation(checkout, values);

		/*
		 * Summary rows.
		 */
		setAmount(
			checkout,
			'product-total',
			values.productTotal
		);

		setAmount(
			checkout,
			'combo-discount',
			values.comboOfferDiscount
		);

		setAmount(
			checkout,
			'order-bump-regular-total',
			values.orderBumpRegularTotal
		);

		setAmount(
			checkout,
			'order-bump-discount',
			values.orderBumpDiscount
		);

		setAmount(
			checkout,
			'order-bump-total',
			values.orderBumpTotal
		);

		setAmount(
			checkout,
			'automatic-discount',
			values.automaticDiscount
		);

		setAmount(
			checkout,
			'coupon-discount',
			values.couponDiscount
		);

		setAmount(
			checkout,
			'delivery-charge',
			values.deliveryCharge
		);

		setAmount(
			checkout,
			'advance-payment',
			values.advancePayment
		);

		setAmount(
			checkout,
			'full-payment-discount',
			values.fullPaymentDiscount
		);

		setAmount(
			checkout,
			'grand-total',
			values.grandTotal
		);

		setAmount(
			checkout,
			'pay-now',
			values.payNow
		);

		setAmount(
			checkout,
			'remaining-due',
			values.remainingDue
		);

		/*
		 * Shared state.
		 */
		Object.assign(
			checkout.dataset,
			{
				baseProductTotal:
					String(
						values
							.baseProductTotal
					),

				productTotal:
					String(
						values
							.productTotal
					),

				comboSelectedCount:
					String(
						values
							.comboSelectedCount
					),

				comboRegularTotal:
					String(
						values
							.comboRegularTotal
					),

				comboTotal:
					String(
						values
							.comboTotal
					),

				comboDiscount:
					String(
						values
							.comboOfferDiscount
					),

				comboAutomaticEligibleTotal:
					String(
						values
							.comboAutomaticEligibleTotal
					),

				comboCouponEligibleTotal:
					String(
						values
							.comboCouponEligibleTotal
					),

				comboFullPaymentEligibleRegularTotal:
					String(
						values
							.comboFullPaymentEligibleRegularTotal
					),

				comboFullPaymentEligibleTotal:
					String(
						values
							.comboFullPaymentEligibleTotal
					),

				orderBumpSelectedCount:
					String(
						values
							.orderBumpSelectedCount
					),

				orderBumpRegularTotal:
					String(
						values
							.orderBumpRegularTotal
					),

				orderBumpDiscount:
					String(
						values
							.orderBumpDiscount
					),

				orderBumpTotal:
					String(
						values
							.orderBumpTotal
					),

				orderBumpAutomaticEligibleTotal:
					String(
						values
							.orderBumpAutomaticEligibleTotal
					),

				orderBumpCouponEligibleTotal:
					String(
						values
							.orderBumpCouponEligibleTotal
					),

				orderBumpFullPaymentEligibleRegularTotal:
					String(
						values
							.orderBumpFullPaymentEligibleRegularTotal
					),

				orderBumpFullPaymentEligibleTotal:
					String(
						values
							.orderBumpFullPaymentEligibleTotal
					),

				automaticDiscountBase:
					String(
						values
							.automaticDiscountBase
					),

				automaticDiscount:
					String(
						values
							.automaticDiscount
					),

				couponBaseBeforeAutomatic:
					String(
						values
							.couponBaseBeforeAutomatic
					),

				couponAutomaticDiscount:
					String(
						values
							.couponAutomaticDiscount
					),

				couponDiscountBase:
					String(
						values
							.couponDiscountBase
					),

				couponDiscount:
					String(
						values
							.couponDiscount
					),

				discountedProductTotal:
					String(
						values
							.discountedProductTotal
					),

				baseGrandTotal:
					String(
						values
							.baseGrandTotal
					),

				fullPaymentDiscount:
					String(
						values
							.fullPaymentDiscount
					),

				fullPaymentEligibleGrandTotal:
					String(
						values
							.fullPaymentEligibleGrandTotal
					),

				grandTotal:
					String(
						values
							.grandTotal
					),

				payableGrandTotal:
					String(
						values
							.grandTotal
					),

				paymentType:
					values.paymentType,

				cashOnDeliverySelected:
					values.isCashOnDelivery
						? 'yes'
						: 'no',

				paymentMethodRequired:
					values.isCashOnDelivery
						? 'no'
						: 'yes',

				advanceAmount:
					String(
						values
							.advancePayment
					),

				payNow:
					String(
						values
							.payNow
					),

				remainingDue:
					String(
						values
							.remainingDue
					),
			}
		);

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:summaryChange',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						values:
							values,
					},
				}
			)
		);

		return values;
	}

	/**
	 * Get responsive Summary mode.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getActiveSummaryMode(checkout) {
		if (
			window.innerWidth <=
				767
		) {
			return (
				checkout.dataset
					.summaryMobile ||
					'inline'
			);
		}

		if (
			window.innerWidth <=
				1024
		) {
			return (
				checkout.dataset
					.summaryTablet ||
					'below'
			);
		}

		return (
			checkout.dataset
				.summaryDesktop ||
				'right_sticky'
		);
	}

	/**
	 * Determine drawer mode.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function isDrawerMode(checkout) {
		return (
			'bottom_drawer' ===
				getActiveSummaryMode(
					checkout
				)
		);
	}

	/**
	 * Update drawer accessibility.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {boolean}     open     Open.
	 *
	 * @return {void}
	 */
	function updateDrawerAccessibility(
		checkout,
		open
	) {
		const panel =
			checkout.querySelector(
				SELECTORS.summaryPanel
			);

		checkout
			.querySelectorAll(
				SELECTORS.toggle
			)
			.forEach(
				function (toggle) {
					toggle.setAttribute(
						'aria-expanded',
						open
							? 'true'
							: 'false'
					);
				}
			);

		if (panel) {
            open = open || !isDrawerMode(checkout);
			panel.setAttribute(
				'aria-hidden',
				open
					? 'false'
					: 'true'
			);
		}
	}

	/**
	 * Open drawer.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function openDrawer(checkout) {
		if (
			!isDrawerMode(
				checkout
			)
		) {
			return;
		}

		const shell =
			getSummaryShell(
				checkout
			);

		if (!shell) {
			return;
		}

		shell.dataset.summaryOpen =
			'yes';

		checkout.classList.add(
			'eilmo-cf-summary-drawer-open'
		);

		updateDrawerAccessibility(
			checkout,
			true
		);

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:summaryOpen',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,
					},
				}
			)
		);
	}

	/**
	 * Close drawer.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function closeDrawer(checkout) {
		const shell =
			getSummaryShell(
				checkout
			);

		if (!shell) {
			return;
		}

		shell.dataset.summaryOpen =
			'no';

		checkout.classList.remove(
			'eilmo-cf-summary-drawer-open'
		);

		updateDrawerAccessibility(
			checkout,
			false
		);

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:summaryClose',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,
					},
				}
			)
		);
	}

	/**
	 * Toggle drawer.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function toggleDrawer(checkout) {
		const shell =
			getSummaryShell(
				checkout
			);

		if (!shell) {
			return;
		}

		if (
			'yes' ===
				shell.dataset
					.summaryOpen
		) {
			closeDrawer(
				checkout
			);

			return;
		}

		openDrawer(
			checkout
		);
	}

	/**
	 * Handle click.
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

		const toggle =
			event.target.closest(
				SELECTORS.toggle
			);

		if (toggle) {
			const checkout =
				getCheckout(
					toggle
				);

			if (!checkout) {
				return;
			}

			event.preventDefault();

			toggleDrawer(
				checkout
			);

			return;
		}

		const close =
			event.target.closest(
				SELECTORS.close
			);

		if (close) {
			const checkout =
				getCheckout(
					close
				);

			if (!checkout) {
				return;
			}

			event.preventDefault();

			closeDrawer(
				checkout
			);

			return;
		}

		const backdrop =
			event.target.closest(
				SELECTORS.backdrop
			);

		if (!backdrop) {
			return;
		}

		const checkout =
			getCheckout(
				backdrop
			);

		if (checkout) {
			closeDrawer(
				checkout
			);
		}
	}

	/**
	 * Handle Escape.
	 *
	 * @param {KeyboardEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleKeydown(event) {
		if (
			'Escape' !==
				event.key
		) {
			return;
		}

		document
			.querySelectorAll(
				SELECTORS.checkout
			)
			.forEach(
				function (checkout) {
					if (
						!(
							window.eilmoCfDom.isElement(checkout)
						)
					) {
						return;
					}

					const shell =
						getSummaryShell(
							checkout
						);

					if (
						shell &&
						'yes' ===
							shell.dataset
								.summaryOpen
					) {
						closeDrawer(
							checkout
						);
					}
				}
			);
	}

	/**
	 * Simple checkout refresh handler.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleSimpleRefresh(event) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (checkout) {
			updateSummary(
				checkout
			);
		}
	}

	/**
	 * Handle delivery change.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleDeliveryChange(event) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (!checkout) {
			return;
		}

		const detail =
			event.detail &&
			'object' ===
				typeof event.detail
				? event.detail
				: {};

		const methodId =
			'string' ===
				typeof detail.methodId
				? detail.methodId
				: '';

		const charge =
			roundMoney(
				detail.charge ||
					0
			);

		const baseCharge =
			roundMoney(
				detail.baseCharge ||
					0
			);

		checkout.dataset.deliveryMethod =
			methodId;

		checkout.dataset.deliveryCharge =
			String(
				charge
			);

		checkout.dataset.deliveryBaseCharge =
			String(
				baseCharge
			);

		checkout.dataset.deliveryIsFree =
			true ===
				detail.isFree
				? 'yes'
				: 'no';

		checkout.dataset.freeDeliveryApplied =
			true ===
				detail.freeDeliveryApplied
				? 'yes'
				: 'no';

		setAmount(
			checkout,
			'delivery-charge',
			charge
		);

		updateSummary(
			checkout
		);
	}

	/**
	 * Handle Automatic Discount.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleDiscountChange(event) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (!checkout) {
			return;
		}

		const detail =
			event.detail &&
			'object' ===
				typeof event.detail
				? event.detail
				: {};

		setAmount(
			checkout,
			'automatic-discount',
			toNumber(
				undefined !==
					detail.automaticDiscount
					? detail
						.automaticDiscount
					: (
						detail.discount ||
							0
					)
			)
		);

		updateSummary(
			checkout
		);
	}

	/**
	 * Handle Coupon Discount.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleCouponChange(event) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (!checkout) {
			return;
		}

		const detail =
			event.detail &&
			'object' ===
				typeof event.detail
				? event.detail
				: {};

		setAmount(
			checkout,
			'coupon-discount',
			toNumber(
				detail.couponDiscount ||
					0
			)
		);

		updateSummary(
			checkout
		);
	}

	/**
	 * Handle Cash on Delivery / Advance / Full Payment calculation.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleAdvancePaymentChange(event) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (!checkout) {
			return;
		}

		const detail =
			event.detail &&
			'object' ===
				typeof event.detail
				? event.detail
				: {};

		const paymentType =
			normalizePaymentType(
				detail.paymentType ||
				detail.payment_type ||
					''
			) ||
			getPaymentType(
				checkout
			);

		const advanceAmount =
			'cash_on_delivery' ===
				paymentType
					? 0
					: roundMoney(
						detail.advanceAmount ||
						detail.advance_amount ||
							0
					);

		const appliedFullPaymentDiscount =
			'full' ===
				paymentType
					? roundMoney(
						detail
							.appliedFullPaymentDiscount ||
						detail
							.fullPaymentDiscount ||
							0
					)
					: 0;

		checkout.dataset.advanceReady =
			'yes';

		checkout.dataset.paymentType =
			paymentType;

		checkout.dataset.advanceAmount =
			String(
				advanceAmount
			);

		checkout.dataset.advanceRuleId =
			'string' ===
				typeof detail.ruleId
				? detail.ruleId
				: '';

		checkout.dataset.advanceRuleType =
			'cash_on_delivery' ===
				paymentType
					? 'cash_on_delivery'
					: (
						'string' ===
							typeof detail.ruleType
								? detail.ruleType
								: ''
					);

		checkout.dataset.fullPaymentDiscount =
			String(
				appliedFullPaymentDiscount
			);

		checkout.dataset.fullPaymentDiscountPreview =
			String(
				roundMoney(
					detail
						.fullPaymentDiscountPreview ||
						0
				)
			);

		if (
			undefined !==
				detail.fullPaymentProductTotal
		) {
			checkout.dataset
				.fullPaymentProductTotal =
					String(
						roundMoney(
							detail
								.fullPaymentProductTotal
						)
					);
		}

		if (
			undefined !==
				detail
					.fullPaymentDiscountedProductTotal
		) {
			checkout.dataset
				.fullPaymentDiscountedProductTotal =
					String(
						roundMoney(
							detail
								.fullPaymentDiscountedProductTotal
						)
					);
		}

		if (
			undefined !==
				detail
					.fullPaymentEligibleGrandTotal
		) {
			checkout.dataset
				.fullPaymentEligibleGrandTotal =
					String(
						roundMoney(
							detail
								.fullPaymentEligibleGrandTotal
						)
					);
		}

		checkout.dataset.baseGrandTotal =
			String(
				roundMoney(
					detail.baseGrandTotal ||
						0
				)
			);

		checkout.dataset.payableGrandTotal =
			String(
				roundMoney(
					detail.grandTotal ||
						0
				)
			);

		const eventGrandTotal =
			roundMoney(
				detail.grandTotal ||
				detail.grand_total ||
				checkout.dataset
					.grandTotal ||
					0
			);

		const eventPayNow =
			'cash_on_delivery' ===
				paymentType
					? 0
					: roundMoney(
						detail.payNow ||
						detail.pay_now ||
							0
					);

		const eventRemainingDue =
			'cash_on_delivery' ===
				paymentType
					? eventGrandTotal
					: roundMoney(
						detail.remainingDue ||
						detail.remaining_due ||
							0
					);

		checkout.dataset.payNow =
			String(
				eventPayNow
			);

		checkout.dataset.remainingDue =
			String(
				eventRemainingDue
			);

		checkout.dataset.cashOnDeliverySelected =
			'cash_on_delivery' ===
				paymentType
					? 'yes'
					: 'no';

		checkout.dataset.paymentMethodRequired =
			'cash_on_delivery' ===
				paymentType
					? 'no'
					: 'yes';

		setAmount(
			checkout,
			'advance-payment',
			advanceAmount
		);

		setAmount(
			checkout,
			'full-payment-discount',
			appliedFullPaymentDiscount
		);

		updateSummary(
			checkout
		);
	}

	/**
	 * Handle generic Summary refresh.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleSummaryRefresh(event) {
		const detail =
			event.detail &&
			'object' ===
				typeof event.detail
				? event.detail
				: {};

		/*
		 * Order Bump already has a dedicated change event.
		 */
		if (
			'order-bump' ===
				detail.source
		) {
			return;
		}

		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (checkout) {
			updateSummary(
				checkout
			);
		}
	}

	/**
	 * Keep mobile Summary drawer focused on order summary content only.
	 *
	 * Checkout details/order actions are rendered once by PHP. On mobile,
	 * move those existing DOM nodes into the normal checkout flow rather
	 * than cloning them. This preserves all payment/customer state and
	 * event listeners while preventing duplicate form controls.
	 *
	 * @param {HTMLElement} checkout Checkout wrapper.
	 *
	 * @return {void}
	 */
	function syncMobileSummaryContent(checkout) {
		const target = checkout.querySelector(SELECTORS.mobileCheckoutDetailsTarget);
		const panel = checkout.querySelector(SELECTORS.summaryPanel);

		if (!target || !panel) {
			return;
		}

		const details = checkout.querySelector(SELECTORS.summaryCheckoutDetails);
		const orderAction = checkout.querySelector(SELECTORS.summaryOrderAction);
		const whatsappAction = checkout.querySelector(SELECTORS.summaryWhatsAppAction);
		const anchor = panel.querySelector(SELECTORS.summaryRelocationAnchor);
		const mobile = window.matchMedia('(max-width: 767px)').matches;

		if (mobile) {
			if (orderAction && orderAction.parentElement !== target) {
				target.appendChild(orderAction);
			}

			if (whatsappAction && whatsappAction.parentElement !== target) {
				target.appendChild(whatsappAction);
			}

			if (details && details.parentElement !== target) {
				target.appendChild(details);
			}

			return;
		}

		if (orderAction && orderAction.parentElement !== panel) {
			if (anchor) {
				panel.insertBefore(orderAction, anchor);
			} else {
				panel.appendChild(orderAction);
			}
		}

		if (whatsappAction && whatsappAction.parentElement !== panel) {
			if (anchor) {
				panel.insertBefore(whatsappAction, anchor);
			} else {
				panel.appendChild(whatsappAction);
			}
		}

		if (details && details.parentElement !== panel) {
			if (anchor) {
				panel.insertBefore(details, anchor);
			} else {
				panel.appendChild(details);
			}
		}
	}

	/**
	 * Handle responsive resize.
	 *
	 * @return {void}
	 */
	function handleResize() {
		document
			.querySelectorAll(
				SELECTORS.checkout
			)
			.forEach(
				function (checkout) {
					if ( window.eilmoCfDom.isElement(checkout) ) {
						syncMobileSummaryContent( checkout );
					}

					if (
						window.eilmoCfDom.isElement(checkout) &&
						!isDrawerMode(
							checkout
						)
					) {
						closeDrawer(
							checkout
						);
					}
				}
			);
	}

	/**
	 * Initialize one checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function initializeCheckout(checkout) {
		syncMobileSummaryContent( checkout );

		updateSummary(
			checkout
		);

		const shell =
			getSummaryShell(
				checkout
			);

		if (!shell) {
			return;
		}

		let open =
			'yes' ===
				shell.dataset
					.summaryOpen;

		if (
			isDrawerMode(
				checkout
			) &&
			'yes' ===
				checkout.dataset
					.mobileSummaryCollapsed
		) {
			open =
				false;

			shell.dataset.summaryOpen =
				'no';
		}

		checkout.classList.toggle(
			'eilmo-cf-summary-drawer-open',
			isDrawerMode( checkout ) && open
		);

		updateDrawerAccessibility(
			checkout,
			open
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
	}

	/**
	 * Public Summary API.
	 */
	window.eilmoCfSummary = {

		/**
		 * Format price.
		 *
		 * @param {number} amount Amount.
		 *
		 * @return {string}
		 */
		formatPrice:
			function (amount) {
				return formatPrice(
					amount
				);
			},

		/**
		 * Refresh one or all checkout instances.
		 *
		 * @param {HTMLElement|null} checkout Checkout.
		 *
		 * @return {void}
		 */
		refresh:
			function (checkout) {
				if (
					window.eilmoCfDom.isElement(checkout)
				) {
					updateSummary(
						checkout
					);

					return;
				}

				document
					.querySelectorAll(
						SELECTORS.checkout
					)
					.forEach(
						function (item) {
							if (
								window.eilmoCfDom.isElement(item)
							) {
								updateSummary(
									item
								);
							}
						}
					);
			},

		/**
		 * Set external amount.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 * @param {string}      key      Key.
		 * @param {number}      amount   Amount.
		 *
		 * @return {void}
		 */
		setAmount:
			function (
				checkout,
				key,
				amount
			) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return;
				}

				setAmount(
					checkout,
					key,
					amount
				);

				updateSummary(
					checkout
				);
			},

		/**
		 * Get amount.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 * @param {string}      key      Key.
		 *
		 * @return {number}
		 */
		getAmount:
			function (
				checkout,
				key
			) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return 0;
				}

				return getAmount(
					checkout,
					key
				);
			},

		/**
		 * Get complete Summary values.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object|null}
		 */
		getValues:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return null;
				}

				return calculateSummary(
					checkout
				);
			},

		/**
		 * Get selected Payment Option.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {string}
		 */
		getPaymentType:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return 'full';
				}

				return getPaymentType(
					checkout
				);
			},

		/**
		 * Determine whether COD is selected.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {boolean}
		 */
		isCashOnDelivery:
			function (checkout) {
				return (
					window.eilmoCfDom.isElement(checkout) &&
					isCashOnDelivery(
						checkout
					)
				);
			},

		/**
		 * Get Combo totals.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object}
		 */
		getComboTotals:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return {
						selectedCount:
							0,

						regularTotal:
							0,

						discount:
							0,

						payableTotal:
							0,

						comboTotal:
							0,

						automaticEligibleTotal:
							0,

						couponEligibleTotal:
							0,

						fullPaymentEligibleRegularTotal:
							0,

						fullPaymentEligibleTotal:
							0,

						automaticCouponOverlapTotal:
							0,

						automaticFullPaymentOverlapTotal:
							0,

						fullPaymentCouponOverlapTotal:
							0,

						automaticFullPaymentCouponOverlapTotal:
							0,
					};
				}

				return calculateComboTotals(
					checkout
				);
			},

		/**
		 * Get Order Bump totals.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object}
		 */
		getOrderBumpTotals:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return {
						selectedCount:
							0,

						regularTotal:
							0,

						discount:
							0,

						payableTotal:
							0,

						bumpTotal:
							0,

						automaticEligibleTotal:
							0,

						couponEligibleTotal:
							0,

						fullPaymentEligibleRegularTotal:
							0,

						fullPaymentEligibleTotal:
							0,

						automaticCouponOverlapTotal:
							0,

						automaticFullPaymentOverlapTotal:
							0,

						fullPaymentCouponOverlapTotal:
							0,

						automaticFullPaymentCouponOverlapTotal:
							0,
					};
				}

				return calculateOrderBumpTotals(
					checkout
				);
			},

		/**
		 * Open mobile Summary.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {void}
		 */
		open:
			function (checkout) {
				if (
					window.eilmoCfDom.isElement(checkout)
				) {
					openDrawer(
						checkout
					);
				}
			},

		/**
		 * Close mobile Summary.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {void}
		 */
		close:
			function (checkout) {
				if (
					window.eilmoCfDom.isElement(checkout)
				) {
					closeDrawer(
						checkout
					);
				}
			},
	};

	document.addEventListener(
		'click',
		handleSummaryItemQuantity
	);

	document.addEventListener(
		'eilmo:quantityChange',
		handleSimpleRefresh
	);

	document.addEventListener(
		'eilmo:comboChange',
		handleSimpleRefresh
	);

	document.addEventListener(
		'eilmo:orderBumpChange',
		handleSimpleRefresh
	);

	document.addEventListener(
		'eilmo:deliveryChange',
		handleDeliveryChange
	);

	document.addEventListener(
		'eilmo:discountChange',
		handleDiscountChange
	);

	document.addEventListener(
		'eilmo:couponChange',
		handleCouponChange
	);

	document.addEventListener(
		'eilmo:advancePaymentChange',
		handleAdvancePaymentChange
	);

	document.addEventListener(
		'eilmo:summaryRefresh',
		handleSummaryRefresh
	);

	document.addEventListener(
		'click',
		handleClick
	);

	document.addEventListener(
		'keydown',
		handleKeydown
	);

	window.addEventListener(
		'resize',
		handleResize
	);

	if (
		'loading' ===
			document.readyState
	) {
		document.addEventListener(
			'DOMContentLoaded',
			initialize
		);
	} else {
		initialize();
	}
})();
/* END src/js/frontend/summary.js */

/* BEGIN src/js/frontend/order-display.js */
/**
 * Eilmo Checkout Flow - live order button amount.
 *
 * Keeps every Order Now UI instance inside one checkout synchronized with
 * the authoritative Summary state. This includes below-payment, summary and
 * mobile sticky buttons rendered at the same time.
 *
 * @package EilmoCheckout
 */
(function () {
	'use strict';

	function decodeHtmlEntities(value) {
		const textarea = document.createElement('textarea');
		textarea.innerHTML = String(value || '');
		return textarea.value.replace(/\u00a0/g, ' ').trim();
	}

	function formatPrice(amount) {
		const config = window.eilmoCf && window.eilmoCf.currency
			? window.eilmoCf.currency
			: {};
		const parsedDecimals = Number.parseInt(config.decimals, 10);
		const decimals = Number.isFinite(parsedDecimals) ? Math.max(0, parsedDecimals) : 2;
		const decimalSeparator = decodeHtmlEntities(config.decimalSeparator || '.');
		const thousandSeparator = decodeHtmlEntities(config.thousandSeparator || ',');
		const symbol = decodeHtmlEntities(config.symbol || '৳');
		const position = String(config.position || 'left');
		const number = Math.max(0, Number.parseFloat(amount) || 0);
		const parts = number.toFixed(decimals).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);
		const formatted = decimals > 0 ? parts[0] + decimalSeparator + parts[1] : parts[0];
		if (position === 'right') return formatted + symbol;
		if (position === 'right_space') return formatted + '\u00A0' + symbol;
		if (position === 'left_space') return symbol + '\u00A0' + formatted;
		return symbol + formatted;
	}

	function toNumber(value) {
		const parsed = Number.parseFloat(String(value ?? ''));
		return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
	}

	function readValues(checkout) {
		return {
			grandTotal: toNumber(checkout.dataset.grandTotal),
			payNow: toNumber(checkout.dataset.payNow),
			paymentType: String(checkout.dataset.paymentType || ''),
		};
	}

	function chooseAmount(source, values) {
		const grandTotal = toNumber(values && (values.grandTotal ?? values.grand_total));
		const payNow = toNumber(values && (values.payNow ?? values.pay_now));
		const paymentType = String(values && (values.paymentType ?? values.payment_type) || '');

		if (source === 'grand_total') return grandTotal;
		if (source === 'pay_now') return payNow;

		/* Auto follows the amount the customer actually pays now. COD is the
		 * exception: Pay Now is intentionally zero, while the actionable order
		 * value is the Grand Total. */
		return paymentType === 'cash_on_delivery'
			? grandTotal
			: (payNow > 0 ? payNow : grandTotal);
	}

	function update(checkout, values) {
		if (!(window.eilmoCfDom.isElement(checkout))) return;
		if (checkout.dataset.emptySelection === 'yes') {
			checkout.querySelectorAll('[data-eilmo-mobile-summary-amount]').forEach(function (element) {
				element.textContent = '—';
			});
		}

        const state = values || readValues(checkout);
        const paymentType = String(state && (state.paymentType || state.payment_type) || 'cash_on_delivery');
        checkout.querySelectorAll('[data-eilmo-order-submit]').forEach(function (button) {
            const text = button.querySelector('[data-eilmo-order-submit-text]');
            const language = checkout.dataset.checkoutLanguage || 'en';
            const amount = chooseAmount('auto', state);
            const formatted = window.eilmoCfLanguage ? window.eilmoCfLanguage.money(amount, language) : formatPrice(amount);
            const templateKey = paymentType === 'cash_on_delivery' ? 'cod' : ((paymentType === 'full' || paymentType === 'full_payment') ? 'full' : 'advance');
            const configuredRaw = String(button.dataset[templateKey + 'Template'] || '').trim();
            const isLegacyFullTemplate = templateKey === 'full' && [
                '{amount} Pay and confirm order',
                '{amount} পেমেন্ট করে অর্ডার করুন'
            ].includes(configuredRaw);
            const configured = isLegacyFullTemplate
                ? (language === 'bn' ? '{amount} পেমেন্ট করুন' : 'Pay {amount}')
                : configuredRaw;
            const fallback = configured
                ? configured.replace(/\{amount\}/g, formatted)
                : (templateKey === 'cod'
                    ? (language === 'bn' ? 'অর্ডার কনফার্ম করুন' : 'Confirm order')
                    : (templateKey === 'full'
                        ? (language === 'bn' ? formatted + ' পেমেন্ট করুন' : 'Pay ' + formatted)
                        : (language === 'bn' ? formatted + ' পেমেন্ট করে অর্ডার করুন' : formatted + ' Pay and confirm order')));
            button.dataset.label = fallback;
            if (text && button.getAttribute('aria-busy') !== 'true') text.textContent = fallback;
            const extra = button.querySelector('[data-eilmo-order-submit-amount]');
            if (extra) extra.hidden = true;
        });
        checkout.querySelectorAll('[data-eilmo-order-submit][data-show-amount="yes"]').forEach(function (button) {
			const target = button.querySelector('[data-eilmo-order-submit-amount]');
			if (!target) return;
			if (checkout.dataset.emptySelection === 'yes') {
				target.textContent = '— Select a product';
				return;
			}
			const amount = chooseAmount(button.dataset.amountSource || 'auto', values || readValues(checkout));
			target.textContent = '— ' + (window.eilmoCfLanguage ? window.eilmoCfLanguage.digits(formatPrice(amount),checkout.dataset.checkoutLanguage) : formatPrice(amount));
		});
	}

	function refreshCheckout(checkout, values) {
		if (!(window.eilmoCfDom.isElement(checkout))) return;
		update(checkout, values || readValues(checkout));
	}

	function initialize(root) {
		const scope = window.eilmoCfDom.isElement(root) || root instanceof Document ? root : document;
		if (window.eilmoCfDom.isElement(scope) && scope.matches('[data-eilmo-checkout]')) {
			refreshCheckout(scope);
		}
		scope.querySelectorAll('[data-eilmo-checkout]').forEach(function (checkout) {
			refreshCheckout(checkout);
		});
	}

	document.addEventListener('eilmo:summaryChange', function (event) {
		const detailCheckout = event.detail && window.eilmoCfDom.isElement(event.detail.checkout)
			? event.detail.checkout
			: null;
		const eventCheckout = window.eilmoCfDom.isElement(event.target)
			? event.target.closest('[data-eilmo-checkout]')
			: null;
		const checkout = detailCheckout || eventCheckout;
		if (!checkout) return;
		refreshCheckout(checkout, event.detail && event.detail.values ? event.detail.values : readValues(checkout));
	});

	/* Summary may initialize before this file when scripts execute after DOM
	 * ready. Reading the Summary dataset here fixes that race and also makes
	 * preselected/server-restored checkouts immediately correct. */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { initialize(document); });
	} else {
		initialize(document);
	}

	/* Builder/AJAX fallback for dynamically inserted duplicate action UIs. */
	if (window.MutationObserver && document.documentElement) {
		new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (window.eilmoCfDom.isElement(node)) initialize(node);
				});
			});
		}).observe(document.documentElement, { childList: true, subtree: true });
	}
})();
/* END src/js/frontend/order-display.js */

/* BEGIN src/js/frontend/discount.js */
/**
 * Eilmo Checkout Flow - Automatic Discounts.
 *
 * Handles:
 * - Automatic discount range matching.
 * - Percentage discounts.
 * - Fixed discounts.
 * - Maximum discount caps.
 * - Free delivery rules.
 * - Spend More, Save More notice status.
 * - Active and upcoming discount tiers.
 * - Normal Product / Combo / Order Bump isolation.
 * - Summary synchronization through events.
 *
 * Important:
 *
 * Automatic Discount basis:
 *
 * Normal Products
 * +
 * Combo Offers explicitly allowing Automatic Discount
 * +
 * Order Bumps explicitly allowing Automatic Discount
 *
 * Combo Offers and Order Bumps use their payable
 * promotional totals after their own offer discounts,
 * not their regular subtotals.
 *
 * Frontend calculations are UI previews only.
 * Final checkout totals must always be
 * recalculated and validated server-side.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		checkout:
			'[data-eilmo-checkout]',

		notice:
			'[data-eilmo-discount-notice]',

		status:
			'[data-eilmo-discount-status]',

		tier:
			'[data-eilmo-discount-tier]',

		productItem:
			'[data-eilmo-product-item], [data-eilmo-item]',

		quantityInput:
			'[data-eilmo-quantity-input]',

		comboOffer:
			'[data-eilmo-combo-offer]',

		comboInput:
			'[data-eilmo-combo-input]',

		orderBumpOffer:
			'[data-eilmo-order-bump]',

		orderBumpInput:
			'[data-eilmo-order-bump-input]',
	};

	const initialized =
		new WeakSet();

	const scheduled =
		new WeakSet();

	const results =
		new WeakMap();

	/**
	 * Convert value to a non-negative number.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toNumber(value) {
		const number =
			Number.parseFloat(
				String(
					value
				)
			);

		if (
			!Number.isFinite(
				number
			)
		) {
			return 0;
		}

		return Math.max(
			0,
			number
		);
	}

	/**
	 * Convert value to a non-negative integer identity.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toInteger(value) {
		const number =
			Number.parseInt(
				String(
					value || 0
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
	 * Get WooCommerce price decimals.
	 *
	 * @return {number}
	 */
	function getPriceDecimals() {
		const config =
			window.eilmoCf ||
			{};

		const currency =
			config.currency &&
			'object' ===
				typeof config.currency
				? config.currency
				: {};

		let decimals =
			Number(
				currency.decimals
			);

		if (
			!Number.isFinite(
				decimals
			)
		) {
			decimals =
				Number(
					config.priceDecimals
				);
		}

		if (
			!Number.isFinite(
				decimals
			)
		) {
			return 2;
		}

		return Math.max(
			0,
			Math.floor(
				decimals
			)
		);
	}

	/**
	 * Round monetary value.
	 *
	 * @param {number} value Value.
	 *
	 * @return {number}
	 */
	function roundMoney(value) {
		const multiplier =
			Math.pow(
				10,
				getPriceDecimals()
			);

		return (
			Math.round(
				(
					toNumber(
						value
					) +
					Number.EPSILON
				) *
					multiplier
			) /
				multiplier
		);
	}

	/**
	 * Parse JSON safely.
	 *
	 * @param {string} value    JSON.
	 * @param {*}      fallback Fallback.
	 *
	 * @return {*}
	 */
	function parseJSON(
		value,
		fallback
	) {
		if (!value) {
			return fallback;
		}

		try {

			const parsed =
				JSON.parse(
					value
				);

			return null ===
				parsed
					? fallback
					: parsed;

		} catch (error) {

			return fallback;
		}
	}

	/**
	 * Decode HTML entities.
	 *
	 * @param {string} value Value.
	 *
	 * @return {string}
	 */
	function decodeHTML(value) {
		const textarea =
			document.createElement(
				'textarea'
			);

		textarea.innerHTML =
			String(
				value ||
					''
			);

		return textarea.value;
	}

	/**
	 * Strip HTML.
	 *
	 * @param {string} value Value.
	 *
	 * @return {string}
	 */
	function stripHTML(value) {
		const element =
			document.createElement(
				'div'
			);

		element.innerHTML =
			String(
				value ||
					''
			);

		return decodeHTML(
			element.textContent ||
				element.innerText ||
				''
		);
	}

	/**
	 * Get currency configuration.
	 *
	 * @return {Object}
	 */
	function getCurrencyConfig() {
		const config =
			window.eilmoCf ||
			{};

		const currency =
			config.currency &&
			'object' ===
				typeof config.currency
				? config.currency
				: {};

		return {
			symbol:
				decodeHTML(
					'string' ===
						typeof currency.symbol
						? currency.symbol
						: (
							config.currencySymbol ||
								'৳'
						)
				),

			position:
				'string' ===
					typeof currency.position
					? currency.position
					: (
						config.currencyPosition ||
							'left'
					),

			decimals:
				getPriceDecimals(),

			decimalSeparator:
				'string' ===
					typeof currency.decimalSeparator
					? decodeHTML(
						currency.decimalSeparator
					)
					: (
						config.decimalSeparator ||
							'.'
					),

			thousandSeparator:
				'string' ===
					typeof currency.thousandSeparator
					? decodeHTML(
						currency.thousandSeparator
					)
					: (
						config.thousandSeparator ||
							','
					),
		};
	}

	/**
	 * Format price.
	 *
	 * @param {number} amount Amount.
	 *
	 * @return {string}
	 */
	function formatPrice(amount) {
		const safeAmount =
			roundMoney(
				amount
			);

		if (
			window.eilmoCfSummary &&
			'function' ===
				typeof window
					.eilmoCfSummary
					.formatPrice
		) {
			return stripHTML(
				window
					.eilmoCfSummary
					.formatPrice(
						safeAmount
					)
			);
		}

		const config =
			getCurrencyConfig();

		const parts =
			safeAmount
				.toFixed(
					config.decimals
				)
				.split(
					'.'
				);

		parts[0] =
			parts[0].replace(
				/\B(?=(\d{3})+(?!\d))/g,
				config.thousandSeparator
			);

		const formattedNumber =
			config.decimals > 0
				? (
					parts[0] +
					config.decimalSeparator +
					parts[1]
				)
				: parts[0];

		switch (
			config.position
		) {
			case 'right':

				return (
					formattedNumber +
					config.symbol
				);

			case 'right_space':

				return (
					formattedNumber +
					' ' +
					config.symbol
				);

			case 'left_space':

				return (
					config.symbol +
					' ' +
					formattedNumber
				);

			case 'left':
			default:

				return (
					config.symbol +
					formattedNumber
				);
		}
	}

	/**
	 * Format percentage.
	 *
	 * @param {number} value Percentage.
	 *
	 * @return {string}
	 */
	function formatPercentage(value) {
		const percentage =
			Math.min(
				100,
				toNumber(
					value
				)
			);

		return (
			Number.parseFloat(
				percentage.toFixed(
					2
				)
			).toString() +
				'%'
		);
	}

	/**
	 * Replace placeholders.
	 *
	 * @param {string} text   Text.
	 * @param {Object} values Values.
	 *
	 * @return {string}
	 */
	function replaceTokens(
		text,
		values
	) {
		let output =
			String(
				text ||
					''
			);

		Object.keys(
			values
		).forEach(
			function (key) {
				output =
					output
						.split(
							'{' +
								key +
								'}'
						)
						.join(
							String(
								values[
									key
								]
							)
						);
			}
		);

		return output;
	}

	/**
	 * Calculate selected normal-product total.
	 *
	 * IMPORTANT:
	 *
	 * This function represents NORMAL checkout
	 * products only.
	 *
	 * Combo Offer products are never added here.
	 * Order Bumps are handled separately.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function calculateProductTotal(
		checkout
	) {
		const itemTotals =
			new Map();

		checkout
			.querySelectorAll(
				SELECTORS.productItem
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
					 * Extra isolation safety.
					 *
					 * If another component happens to
					 * reuse data-eilmo-item, never allow
					 * Combo or Order Bump markup to enter
					 * the normal-product calculation.
					 */
					if (
						item.closest(
							'[data-eilmo-combo-offer]'
						) ||
						item.closest(
							'[data-eilmo-order-bump]'
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
						toNumber(
							input.value
						);

					if (
						quantity <= 0
					) {
						return;
					}

					let price =
						toNumber(
							item.dataset
								.price
						);

					if (
						price <= 0
					) {
						price =
							toNumber(
								item.dataset
									.productPrice
							);
					}

					if (
						price <= 0
					) {
						price =
							toNumber(
								input.dataset
									.price
							);
					}

					if (
						price <= 0
					) {
						return;
					}

					const productId =
						toInteger(
							item.dataset
								.productId ||
							input.dataset
								.productId
						);

					const variationId =
						toInteger(
							item.dataset
								.variationId ||
							input.dataset
								.variationId
						);

					/*
					 * Multiple Products may expose compatibility
					 * adapters for one exact order item. Keep one
					 * preview amount per Simple product / variation;
					 * never sum duplicate DOM representations.
					 */
					const key =
						variationId > 0
							? 'variation:' + variationId
							: productId > 0
								? 'product:' + productId
								: item;

					const lineTotal =
						roundMoney(
							price *
								quantity
						);

					const existing =
						toNumber(
							itemTotals.get(
								key
							) || 0
						);

					itemTotals.set(
						key,
						Math.max(
							existing,
							lineTotal
						)
					);
				}
			);

		let total =
			0;

		itemTotals.forEach(
			function (lineTotal) {
				total +=
					toNumber(
						lineTotal
					);
			}
		);

		return roundMoney(
			total
		);
	}

	/**
	 * Build product/quantity condition context for the unified rule engine.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @return {Object}
	 */
	function getConditionContext(checkout) {
		let productTotal = 0;
		let cartQuantity = 0;
		const productQuantities = {};
		const productTotals = {};
		const discountProductTotals = {};
		const productIds = [];
		const seen = new Set();

		function addLine(productId, variationId, quantity, lineTotal, discountEligible) {
			if (quantity <= 0 || productId <= 0) return;
			cartQuantity += quantity;
			productIds.push(productId);
			productQuantities[productId] = toNumber(productQuantities[productId]) + quantity;
			productTotals[productId] = roundMoney(toNumber(productTotals[productId]) + lineTotal);
			if (discountEligible) {
				discountProductTotals[productId] = roundMoney(toNumber(discountProductTotals[productId]) + lineTotal);
			}
			if (variationId > 0) {
				productIds.push(variationId);
				productQuantities[variationId] = toNumber(productQuantities[variationId]) + quantity;
				productTotals[variationId] = roundMoney(toNumber(productTotals[variationId]) + lineTotal);
				if (discountEligible) {
					discountProductTotals[variationId] = roundMoney(toNumber(discountProductTotals[variationId]) + lineTotal);
				}
			}
		}

		checkout.querySelectorAll(SELECTORS.productItem).forEach(function (item) {
			if (!(window.eilmoCfDom.isElement(item)) || item.closest('[data-eilmo-combo-offer], [data-eilmo-order-bump]')) return;
			const input = item.querySelector(SELECTORS.quantityInput);
			if (!(window.eilmoCfDom.isElement(input, 'INPUT'))) return;
			const quantity = toNumber(input.value);
			const productId = toInteger(item.dataset.productId || input.dataset.productId);
			const variationId = toInteger(item.dataset.variationId || input.dataset.variationId);
			const key = variationId > 0 ? `v:${variationId}` : `p:${productId}`;
			if (quantity <= 0 || seen.has(key)) return;
			seen.add(key);
			const price = toNumber(item.dataset.price || item.dataset.productPrice || input.dataset.price || 0);
			const lineTotal = roundMoney(price * quantity);
			productTotal += lineTotal;
			addLine(productId, variationId, quantity, lineTotal, true);
		});

		checkout.querySelectorAll(SELECTORS.comboOffer).forEach(function (combo) {
			if (!(window.eilmoCfDom.isElement(combo))) return;
			const input = combo.querySelector(SELECTORS.comboInput);
			if (!(window.eilmoCfDom.isElement(input, 'INPUT')) || !input.checked || input.disabled) return;
			const components = Array.from(combo.querySelectorAll('.eilmo-cf-combo-offer__item[data-product-id]'));
			const regularTotal = components.reduce(function (sum, item) {
				return sum + toNumber(item.dataset.lineTotal || 0);
			}, 0);
			const comboTotal = roundMoney(combo.dataset.comboTotal || Math.max(0, toNumber(combo.dataset.regularTotal) - toNumber(combo.dataset.comboDiscount)));
			const scale = regularTotal > 0 ? Math.min(1, comboTotal / regularTotal) : 0;
			const discountEligible = 'yes' === String(combo.dataset.applyAutomaticDiscount || 'no');
			productTotal += comboTotal;
			components.forEach(function (item) {
				if (!(window.eilmoCfDom.isElement(item))) return;
				const quantity = toNumber(item.dataset.quantity || 1);
				const productId = toInteger(item.dataset.productId);
				const variationId = toInteger(item.dataset.variationId);
				const componentTotal = roundMoney(toNumber(item.dataset.lineTotal || 0) * scale);
				addLine(productId, variationId, quantity, componentTotal, discountEligible);
			});
		});

		let currentProductIds = parseJSON(checkout.getAttribute('data-eilmo-current-product-ids'), []);
		if (!Array.isArray(currentProductIds)) currentProductIds = [];
		currentProductIds = currentProductIds.map(toInteger).filter(function (id, index, all) {
			return id > 0 && all.indexOf(id) === index;
		});

		return {
			productTotal: roundMoney(productTotal),
			cartQuantity: cartQuantity,
			productQuantities: productQuantities,
			productTotals: productTotals,
			discountProductTotals: discountProductTotals,
			productIds: productIds.filter(function (id, index, all) { return id > 0 && all.indexOf(id) === index; }),
			currentProductIds: currentProductIds,
		};
	}

	function getRuleScopeIds(rule, context) {
		if ('current_product' === rule.ruleScope) {
			return Array.isArray(context.currentProductIds) ? context.currentProductIds : [];
		}
		if ('selected_products' === rule.ruleScope) {
			const ids = Array.isArray(rule.scopeProductIds) ? rule.scopeProductIds.slice() : [];
			if (0 === ids.length && rule.conditionProductId > 0) ids.push(rule.conditionProductId);
			return ids;
		}
		return [];
	}

	function getScopedConditionContext(rule, context) {
		if (!rule || 'whole_cart' === rule.ruleScope) {
			return {
				productTotal: roundMoney(context.productTotal || 0),
				cartQuantity: toNumber(context.cartQuantity),
				productQuantities: context.productQuantities || {},
				scopeProductIds: context.productIds || [],
			};
		}
		const ids = getRuleScopeIds(rule, context);
		let productTotal = 0;
		let cartQuantity = 0;
		const productQuantities = {};
		ids.forEach(function (id) {
			const quantity = toNumber((context.productQuantities || {})[id]);
			const total = toNumber((context.productTotals || {})[id]);
			if (quantity > 0) {
				productQuantities[id] = quantity;
				cartQuantity += quantity;
			}
			productTotal += total;
		});
		return {
			productTotal: roundMoney(productTotal),
			cartQuantity: cartQuantity,
			productQuantities: productQuantities,
			scopeProductIds: ids,
		};
	}

	function getRuleDiscountBase(rule, context, automaticDiscountBase) {
		if (!rule || 'whole_cart' === rule.ruleScope) return roundMoney(automaticDiscountBase);
		const ids = getRuleScopeIds(rule, context);
		let total = 0;
		ids.forEach(function (id) {
			total += toNumber((context.discountProductTotals || {})[id]);
		});
		return roundMoney(Math.min(toNumber(automaticDiscountBase), total));
	}

	/**
	 * Calculate Combo Offer amount eligible for
	 * Automatic Discount.
	 *
	 * Default compatibility is NO.
	 *
	 * Only:
	 *
	 * data-apply-automatic-discount="yes"
	 *
	 * makes a selected Combo Offer eligible.
	 *
	 * The payable combo total is used after the
	 * Combo Offer's own discount has already been
	 * applied.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function calculateComboAutomaticTotal(
		checkout
	) {
		let total =
			0;

		checkout
			.querySelectorAll(
				SELECTORS
					.comboOffer
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
							SELECTORS
								.comboInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						) ||
						!input.checked ||
						input.disabled
					) {
						return;
					}

					/*
					 * Explicit opt-in only.
					 *
					 * Missing attribute defaults to NO.
					 */
					if (
						'yes' !==
							offer.dataset
								.applyAutomaticDiscount
					) {
						return;
					}

					const regularTotal =
						roundMoney(
							offer.dataset
								.regularTotal ||
								0
						);

					const discount =
						Math.min(
							roundMoney(
								offer.dataset
									.comboDiscount ||
									0
							),
							regularTotal
						);

					const fallbackTotal =
						roundMoney(
							Math.max(
								0,
								regularTotal -
									discount
							)
						);

					const comboTotal =
						roundMoney(
							undefined !==
								offer.dataset
									.comboTotal &&
							'' !==
								String(
									offer.dataset
										.comboTotal
								)
									.trim()
								? offer.dataset
									.comboTotal
								: fallbackTotal
						);

					total +=
						Math.min(
							regularTotal,
							comboTotal
						);
				}
			);

		return roundMoney(
			total
		);
	}

	/**
	 * Calculate Order Bump amount eligible for
	 * Automatic Discount.
	 *
	 * Default compatibility is NO.
	 *
	 * Only:
	 *
	 * data-apply-automatic-discount="yes"
	 *
	 * makes a selected Order Bump eligible.
	 *
	 * The payable bump total is used after the
	 * Order Bump's own discount has already been
	 * applied.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function calculateOrderBumpAutomaticTotal(
		checkout
	) {
		let total =
			0;

		checkout
			.querySelectorAll(
				SELECTORS
					.orderBumpOffer
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
							SELECTORS
								.orderBumpInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						) ||
						!input.checked ||
						input.disabled
					) {
						return;
					}

					/*
					 * Explicit opt-in only.
					 *
					 * Missing attribute defaults to NO.
					 */
					if (
						'yes' !==
							offer.dataset
								.applyAutomaticDiscount
					) {
						return;
					}

					const regularTotal =
						roundMoney(
							offer.dataset
								.regularTotal ||
								0
						);

					const discount =
						Math.min(
							roundMoney(
								offer.dataset
									.discount ||
									0
							),
							regularTotal
						);

					const fallbackTotal =
						roundMoney(
							Math.max(
								0,
								regularTotal -
									discount
							)
						);

					const bumpTotal =
						roundMoney(
							undefined !==
								offer.dataset
									.bumpTotal &&
							'' !==
								String(
									offer.dataset
										.bumpTotal
								)
									.trim()
								? offer.dataset
									.bumpTotal
								: fallbackTotal
						);

					total +=
						Math.min(
							regularTotal,
							bumpTotal
						);
				}
			);

		return roundMoney(
			total
		);
	}

	/**
	 * Get authoritative frontend Automatic Discount base.
	 *
	 * Calculation:
	 *
	 * Normal Products
	 * +
	 * Eligible Combo Offer payable totals
	 * +
	 * Eligible Order Bump payable totals
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getAutomaticDiscountBase(
		checkout
	) {
		const normalProductTotal =
			calculateProductTotal(
				checkout
			);

		const comboTotal =
			calculateComboAutomaticTotal(
				checkout
			);

		const orderBumpTotal =
			calculateOrderBumpAutomaticTotal(
				checkout
			);

		const automaticDiscountBase =
			roundMoney(
				normalProductTotal +
					comboTotal +
					orderBumpTotal
			);

		return {
			normalProductTotal:
				normalProductTotal,

			comboTotal:
				comboTotal,

			orderBumpTotal:
				orderBumpTotal,

			automaticDiscountBase:
				automaticDiscountBase,
		};
	}

	/**
	 * Get frontend rules.
	 *
	 * @param {HTMLElement} notice Notice.
	 *
	 * @return {Array}
	 */
	function getRules(notice) {
		const parsed =
			parseJSON(
				notice.getAttribute(
					'data-rules'
				),
				[]
			);

		if (
			!Array.isArray(
				parsed
			)
		) {
			return [];
		}

		return parsed
			.filter(
				function (rule) {
					return (
						rule &&
						'object' ===
							typeof rule
					);
				}
			)
			.map(
				function (rule) {
					return {
						id:
							String(
								rule.id ||
									''
							),

						name:
							String(
								rule.name ||
									''
							),

						minimum:
							roundMoney(
								rule.minimum
							),

						maximum:
							roundMoney(
								rule.maximum
							),

						type:
							String(
								rule.type ||
									'percentage'
							),

						value:
							toNumber(
								rule.value
							),

						maximumDiscount:
							roundMoney(
								rule
									.maximum_discount
							),

						priority:
							Number.parseInt(
								String(
									rule.priority ||
										10
								),
								10
							) ||
								10,

						stopProcessing:
							true ===
								rule
									.stop_processing ||
							'yes' ===
								rule
									.stop_processing,

						conditionType:
							String(rule.condition_type || 'order_amount'),

						conditionProductId:
							toInteger(rule.condition_product_id),

						conditionProductQuantity:
							Math.max(1, toNumber(rule.condition_product_quantity || 1)),

						conditionCartQuantity:
							Math.max(1, toNumber(rule.condition_cart_quantity || 1)),

						ruleScope:
							['current_product', 'selected_products', 'whole_cart'].includes(String(rule.rule_scope || ''))
								? String(rule.rule_scope)
								: 'whole_cart',

						scopeProductIds:
							Array.isArray(rule.scope_product_ids)
								? rule.scope_product_ids.map(toInteger).filter(function (id, index, all) { return id > 0 && all.indexOf(id) === index; })
								: [],

						freeDeliveryMethodId:
							String(rule.free_delivery_method_id || ''),

						freeDeliveryHideOtherMethods:
							true === rule.free_delivery_hide_other_methods || 'yes' === rule.free_delivery_hide_other_methods,

						compatibilityRule:
							Boolean(rule.compatibility_rule),

						ineligibleAction:
							String(rule.ineligible_action || 'show_locked'),

						lockedText:
							String(rule.locked_text || ''),

						appliedText:
							String(rule.applied_text || 'Applied'),

						endTimestamp:
							toInteger(rule.end_timestamp),
					};
				}
			);
	}

	/**
	 * Get notification texts.
	 *
	 * @param {HTMLElement} notice Notice.
	 *
	 * @return {Object}
	 */
	function getTexts(notice) {
		const texts =
			parseJSON(
				notice.getAttribute(
					'data-texts'
				),
				{}
			);

		return (
			texts &&
			'object' ===
				typeof texts
		)
			? texts
			: {};
	}

	/**
	 * Check whether rule matches.
	 *
	 * Range:
	 *
	 * minimum <= amount < maximum
	 *
	 * maximum = 0 means no maximum.
	 *
	 * @param {Object} rule   Rule.
	 * @param {number} amount Amount.
	 *
	 * @return {boolean}
	 */
	function ruleMatches(
		rule,
		amount,
		context = {}
	) {
		if (rule.endTimestamp > 0 && Math.floor(Date.now() / 1000) >= rule.endTimestamp) return false;
		const scoped = getScopedConditionContext(rule, context);
		const type = String(rule.conditionType || 'order_amount');
		if ('always' === type) {
			return 'whole_cart' === rule.ruleScope || ((scoped.scopeProductIds || []).length > 0 && toNumber(scoped.cartQuantity) > 0);
		}
		if (['specific_product', 'product_exists'].includes(type)) {
			const quantities = scoped.productQuantities || {};
			return rule.conditionProductId > 0 && toNumber(quantities[rule.conditionProductId]) >= rule.conditionProductQuantity;
		}
		if (['cart_quantity', 'minimum_quantity', 'buy_x_quantity'].includes(type)) {
			return toNumber(scoped.cartQuantity) >= rule.conditionCartQuantity;
		}
		if ('current_product' === type) {
			return (scoped.scopeProductIds || []).length > 0 && toNumber(scoped.cartQuantity) > 0;
		}
		const scopedAmount = toNumber(scoped.productTotal);
		if (scopedAmount < rule.minimum) return false;
		if (rule.maximum > 0 && scopedAmount > rule.maximum) return false;
		return true;
	}

	/**
	 * Find matching automatic discount rule.
	 *
	 * Normal ranges use:
	 *
	 * minimum <= amount < maximum
	 *
	 * Special rule:
	 * The highest enabled tier becomes the final tier.
	 * Once the customer reaches/passes that tier's
	 * configured maximum, the same final tier continues.
	 *
	 * Example:
	 *
	 * 500 - 2000  = 7%
	 * 3000 - 5000 = 9%
	 *
	 * 5400+ still receives 9%.
	 *
	 * @param {Array}  rules  Rules.
	 * @param {number} amount Automatic Discount base.
	 *
	 * @return {Object|null}
	 */
	function findMatchingRule(
		rules,
		amount,
		context = {}
	) {
		if (
			!Array.isArray(
				rules
			) ||
			0 ===
				rules.length
		) {
			return null;
		}

		/*
		 * First try normal half-open
		 * range matching.
		 */
		const matches =
			rules.filter(
				function (rule) {
					return ruleMatches(
						rule,
						amount,
						context
					);
				}
			);

		if (
			matches.length > 0
		) {
			matches.sort(
				function (
					first,
					second
				) {
					if (
						first.priority !==
							second.priority
					) {
						return (
							first.priority -
								second.priority
						);
					}

					return (
						second.minimum -
							first.minimum
					);
				}
			);

			return matches[0];
		}

		/*
		 * No normal range matched.
		 *
		 * Find the highest configured tier.
		 */
		const orderedRules =
			rules
				.filter(function (rule) { return !rule.conditionType || ['order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal'].includes(rule.conditionType); })
				.slice()
				.sort(
					function (
						first,
						second
					) {
						if (
							first.minimum !==
								second.minimum
						) {
							return (
								second.minimum -
									first.minimum
							);
						}

						return (
							first.priority -
								second.priority
						);
					}
				);

		const finalRule =
			orderedRules[0];

		if (!finalRule) {
			return null;
		}

		/*
		 * Final tier with no maximum
		 * is already open-ended.
		 */
		if (
			finalRule.maximum <= 0 &&
			amount >=
				finalRule.minimum
		) {
			return finalRule;
		}

		/*
		 * If customer exceeds the maximum
		 * of the final configured tier,
		 * continue applying that tier.
		 */
		if (
			finalRule.maximum > 0 &&
			amount >=
				finalRule.maximum
		) {
			return finalRule;
		}

		return null;
	}

	/**
	 * Find every matching Special Discount rule.
	 *
	 * The old tier engine returned only one winner. Unified Special Discounts
	 * are independent rewards, so a monetary reward and Free Delivery (or two
	 * separate monetary rewards) may be active at the same time. The historical
	 * final-tier continuation is retained only when no normal rule matches.
	 *
	 * @param {Array}  rules   Rules.
	 * @param {number} amount  Automatic Discount base.
	 * @param {Object} context Product and quantity context.
	 *
	 * @return {Array}
	 */
	function findMatchingRules(
		rules,
		amount,
		context = {}
	) {
		if (!Array.isArray(rules) || 0 === rules.length) {
			return [];
		}

		const matches = rules
			.filter(function (rule) {
				return ruleMatches(rule, amount, context);
			})
			.sort(function (first, second) {
				if (first.priority !== second.priority) {
					return first.priority - second.priority;
				}

				return second.minimum - first.minimum;
			});

		if (matches.length > 0) {
			return matches;
		}

		const continuedRule = findMatchingRule(rules, amount, context);

		return continuedRule ? [continuedRule] : [];
	}

	/**
	 * Find nearest upcoming rule.
	 *
	 * @param {Array}  rules  Rules.
	 * @param {number} amount Automatic Discount base.
	 *
	 * @return {Object|null}
	 */
	function findUpcomingRule(
		rules,
		amount
	) {
		const upcoming =
			rules
				.filter(
					function (rule) {
						return (
							(!rule.conditionType || ['order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal'].includes(rule.conditionType)) &&
							rule.minimum >
								amount
						);
					}
				)
				.sort(
					function (
						first,
						second
					) {
						if (
							first.minimum !==
								second.minimum
						) {
							return (
								first.minimum -
									second.minimum
							);
						}

						return (
							first.priority -
								second.priority
						);
					}
				);

		return upcoming.length > 0
			? upcoming[0]
			: null;
	}

	/**
	 * Calculate rule discount.
	 *
	 * @param {Object} rule         Rule.
	 * @param {number} productTotal Automatic Discount base.
	 *
	 * @return {Object}
	 */
	function calculateRuleDiscount(
		rule,
		productTotal
	) {
		const result = {
			type:
				rule.type,

			rawDiscount:
				0,

			discount:
				0,

			freeDelivery:
				false,

			capped:
				false,

			clamped:
				false,
		};

		if (
			'free_delivery' ===
				rule.type
		) {
			result.freeDelivery =
				true;

			return result;
		}

		let rawDiscount =
			0;

		if (
			'fixed' ===
				rule.type
		) {
			rawDiscount =
				rule.value;
		} else {
			rawDiscount =
				productTotal *
					(
						Math.min(
							100,
							rule.value
						) /
							100
					);
		}

		rawDiscount =
			roundMoney(
				rawDiscount
			);

		let discount =
			rawDiscount;

		if (
			rule.maximumDiscount > 0 &&
			discount >
				rule.maximumDiscount
		) {
			discount =
				rule.maximumDiscount;

			result.capped =
				true;
		}

		if (
			discount >
				productTotal
		) {
			discount =
				productTotal;

			result.clamped =
				true;
		}

		result.rawDiscount =
			rawDiscount;

		result.discount =
			roundMoney(
				discount
			);

		return result;
	}

	/**
	 * Get discount label.
	 *
	 * Tier list always represents configured offer.
	 * Active status can show actual capped/clamped saving.
	 *
	 * @param {Object}  rule        Rule.
	 * @param {Object}  calculation Calculation.
	 * @param {boolean} preview     Preview.
	 *
	 * @return {string}
	 */
	function getDiscountLabel(
		rule,
		calculation,
		preview
	) {
		if (
			'free_delivery' ===
				rule.type
		) {
			return 'Free Delivery';
		}

		if (
			'fixed' ===
				rule.type
		) {
			if (
				!preview &&
				calculation &&
				(
					calculation.capped ||
					calculation.clamped
				)
			) {
				return formatPrice(
					calculation.discount
				);
			}

			return formatPrice(
				rule.value
			);
		}

		if (
			!preview &&
			calculation &&
			(
				calculation.capped ||
					calculation.clamped
			)
		) {
			return formatPrice(
				calculation.discount
			);
		}

		return formatPercentage(
			rule.value
		);
	}

	/**
	 * Get active status.
	 *
	 * @param {Object} rule         Rule.
	 * @param {Object} calculation Calculation.
	 * @param {number} productTotal Automatic Discount base.
	 * @param {Object} texts        Texts.
	 *
	 * @return {string}
	 */
	function getActiveStatus(
		rule,
		calculation,
		productTotal,
		texts
	) {
		if (
			'free_delivery' ===
				rule.type
		) {
			return 'Free Delivery unlocked.';
		}

		const discountLabel =
			getDiscountLabel(
				rule,
				calculation,
				false
			);

		return replaceTokens(
			texts.active_status ||
				'You are getting {discount} OFF.',
			{
				discount:
					discountLabel,

				current_total:
					formatPrice(
						productTotal
					),

				minimum_amount:
					formatPrice(
						rule.minimum
					),

				maximum_amount:
					rule.maximum > 0
						? formatPrice(
							rule.maximum
						)
						: '',
			}
		);
	}

	/**
	 * Get upcoming status.
	 *
	 * @param {Object} rule         Rule.
	 * @param {number} productTotal Automatic Discount base.
	 * @param {Object} texts        Texts.
	 *
	 * @return {string}
	 */
	function getUpcomingStatus(
		rule,
		productTotal,
		texts
	) {
		const remainingAmount =
			roundMoney(
				Math.max(
					0,
					rule.minimum -
						productTotal
				)
			);

		if (
			'free_delivery' ===
				rule.type
		) {
			return (
				'Add ' +
					formatPrice(
						remainingAmount
					) +
					' more to unlock Free Delivery.'
			);
		}

		const calculation =
			calculateRuleDiscount(
				rule,
				Math.max(
					rule.minimum,
					productTotal
				)
			);

		const discountLabel =
			getDiscountLabel(
				rule,
				calculation,
				true
			);

		return replaceTokens(
			texts.upcoming_status ||
				'Add {remaining_amount} more to unlock {discount} OFF.',
			{
				discount:
					discountLabel,

				remaining_amount:
					formatPrice(
						remainingAmount
					),

				current_total:
					formatPrice(
						productTotal
					),

				minimum_amount:
					formatPrice(
						rule.minimum
					),

				maximum_amount:
					rule.maximum > 0
						? formatPrice(
							rule.maximum
						)
						: '',
			}
		);
	}

	/**
	 * Update status text.
	 *
	 * @param {HTMLElement} notice Notice.
	 * @param {string}      text   Text.
	 *
	 * @return {void}
	 */
	function updateStatus(
		notice,
		text
	) {
		const status =
			notice.querySelector(
				SELECTORS.status
			);

		if (
			!(
				window.eilmoCfDom.isElement(status)
			)
		) {
			return;
		}

		status.textContent =
			String(
				text ||
					''
			);

		status.hidden =
			'' ===
				status.textContent
					.trim();
	}

	/**
	 * Update tier state.
	 *
	 * Tier list always remains visible.
	 *
	 * @param {HTMLElement} notice       Notice.
	 * @param {Array}       rules        Rules.
	 * @param {Array}       activeRules  Active rules.
	 * @param {Object|null} upcomingRule Upcoming rule.
	 * @param {number}      productTotal Automatic Discount base.
	 * @param {Object}      context      Product and quantity context.
	 *
	 * @return {void}
	 */
	function updateTierStates(
		notice,
		rules,
		activeRules,
		upcomingRule,
		productTotal,
		context
	) {
		const rulesById =
			new Map();
		const activeRuleIds = new Set(
			(Array.isArray(activeRules) ? activeRules : [])
				.map(function (rule) {
					return String((rule && rule.id) || '');
				})
				.filter(Boolean)
		);

		rules.forEach(
			function (rule) {
				rulesById.set(
					rule.id,
					rule
				);
			}
		);

		notice
			.querySelectorAll(
				SELECTORS.tier
			)
			.forEach(
				function (tier) {
					if (
						!(
							window.eilmoCfDom.isElement(tier)
						)
					) {
						return;
					}

					const ruleId =
						String(
							tier.dataset
								.ruleId ||
								''
						);

					const rule =
						rulesById.get(
							ruleId
						);

					const active = activeRuleIds.has(ruleId);

					const upcoming =
						Boolean(
							upcomingRule &&
							upcomingRule.id ===
								ruleId
						);

					const expired = Boolean(rule && rule.endTimestamp > 0 && Math.floor(Date.now() / 1000) >= rule.endTimestamp);
					const eligible = Boolean(rule && !expired && ruleMatches(rule, productTotal, context));
					const compactCard = tier.classList.contains('eilmo-cf-special-discount-card--compact');

					const completed = eligible;

					let progressText = '';
					if (rule) {
						const genericAmountText = 'Add {remaining_amount} more to unlock this reward.';
						if (active) {
							progressText = '';
						} else if ('specific_product' === rule.conditionType) {
							const current = toNumber((context.productQuantities || {})[rule.conditionProductId]);
							const remaining = Math.max(0, rule.conditionProductQuantity - current);
							progressText = eligible ? 'Eligible' : (compactCard ? `Add ${remaining} more` : (!rule.lockedText || genericAmountText === rule.lockedText ? `Add ${remaining} more required product` : rule.lockedText));
						} else if ('cart_quantity' === rule.conditionType) {
							const remaining = Math.max(0, rule.conditionCartQuantity - toNumber(context.cartQuantity));
							progressText = eligible ? 'Eligible' : (compactCard ? `Add ${remaining} more` : (!rule.lockedText || genericAmountText === rule.lockedText ? `Add ${remaining} more item${1 === remaining ? '' : 's'}` : rule.lockedText));
						} else if (['order_amount', 'minimum_spend', 'minimum_subtotal', 'selected_product_subtotal'].includes(rule.conditionType) || !rule.conditionType) {
							const remaining = Math.max(0, rule.minimum - productTotal);
							progressText = eligible ? 'Eligible' : (compactCard ? `Add ${formatPrice(remaining)} more` : (rule.lockedText || `Spend ${formatPrice(remaining)} more to unlock`));
						} else {
							progressText = eligible ? 'Eligible' : (rule.lockedText || 'Offer locked');
						}

						progressText = String(progressText || '')
							.split('{remaining_amount}').join(formatPrice(Math.max(0, rule.minimum - productTotal)))
							.split('{remaining_quantity}').join(String(
								'specific_product' === rule.conditionType
									? Math.max(0, rule.conditionProductQuantity - toNumber((context.productQuantities || {})[rule.conditionProductId]))
									: Math.max(0, rule.conditionCartQuantity - toNumber(context.cartQuantity))
							))
							.split('{required_product}').join(rule.conditionProductId > 0 ? `#${rule.conditionProductId}` : '');
					}

					tier.classList.toggle(
						'eilmo-cf-discount-notice__tier--active',
						active
					);

					tier.classList.toggle(
						'eilmo-cf-discount-notice__tier--next',
						upcoming
					);

					tier.classList.toggle(
						'eilmo-cf-discount-notice__tier--completed',
						completed
					);

					tier.classList.toggle('is-selected', active);
					tier.classList.toggle('is-eligible', eligible);
					tier.classList.toggle('is-ineligible', !eligible);
					tier.dataset.eligible = eligible ? 'yes' : 'no';
					tier.hidden = Boolean(expired || (rule && !eligible && 'hide' === rule.ineligibleAction));

					const progress = tier.querySelector('[data-eilmo-special-discount-auto-status]');
					if (window.eilmoCfDom.isElement(progress)) {
						progress.textContent = progressText;
						progress.hidden = active || !progressText;
					}

					const action = tier.querySelector('[data-eilmo-special-discount-auto-action]');
					if (window.eilmoCfDom.isElement(action)) {
						action.hidden = !active;
					}

					tier.dataset.active =
						active
							? 'yes'
							: 'no';

					tier.dataset.next =
						upcoming
							? 'yes'
							: 'no';

					tier.dataset.completed =
						completed
							? 'yes'
							: 'no';
				}
			);
	}

	/**
	 * Store automatic discount state.
	 *
	 * automaticDiscount is the canonical
	 * discount dataset key.
	 *
	 * automaticDiscountBase is the canonical
	 * eligible basis dataset key.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLElement} notice   Notice.
	 * @param {Object}      result   Result.
	 *
	 * @return {void}
	 */
	function storeResult(
		checkout,
		notice,
		result
	) {
		checkout.dataset
			.automaticDiscountReady =
				'yes';

		checkout.dataset
			.automaticDiscount =
				String(
					result.discount
				);

		checkout.dataset
			.automaticDiscountBase =
				String(
					result
						.automaticDiscountBase
				);

		checkout.dataset
			.automaticDiscountNormalProductTotal =
				String(
					result
						.normalProductTotal
				);

		checkout.dataset
			.automaticDiscountComboTotal =
				String(
					result
						.comboAutomaticTotal
				);

		checkout.dataset
			.automaticDiscountOrderBumpTotal =
				String(
					result
						.orderBumpAutomaticTotal
				);

		checkout.dataset
			.automaticDiscountRuleId =
				result.ruleId;

		checkout.dataset
			.automaticDiscountRuleIds =
				Array.isArray(result.ruleIds)
					? result.ruleIds.join(',')
					: result.ruleId;

		checkout.dataset
			.automaticDiscountRuleType =
				result.ruleType;

		checkout.dataset
			.automaticDiscountLabel =
				result.discountLabel;

		checkout.dataset
			.automaticDiscountFreeDelivery =
				result.freeDelivery
					? 'yes'
					: 'no';

		checkout.dataset.automaticDiscountFreeDeliveryMethod =
			String(result.freeDeliveryMethodId || '');

		checkout.dataset.automaticDiscountFreeDeliveryHideOtherMethods =
			result.freeDeliveryHideOtherMethods ? 'yes' : 'no';

		/*
		 * Keep productTotal for compatibility with
		 * existing markup.
		 *
		 * It now means Automatic Discount eligible
		 * product total, NOT global Summary Product Total.
		 */
		notice.dataset
			.productTotal =
				String(
					result
						.automaticDiscountBase
				);

		notice.dataset
			.automaticDiscountBase =
				String(
					result
						.automaticDiscountBase
				);

		notice.dataset
			.normalProductTotal =
				String(
					result
						.normalProductTotal
				);

		notice.dataset
			.comboAutomaticTotal =
				String(
					result
						.comboAutomaticTotal
				);

		notice.dataset
			.orderBumpAutomaticTotal =
				String(
					result
						.orderBumpAutomaticTotal
				);

		notice.dataset
			.discount =
				String(
					result.discount
				);

		notice.dataset
			.activeRuleId =
				result.ruleId;

		notice.dataset
			.activeRuleIds =
				Array.isArray(result.ruleIds)
					? result.ruleIds.join(',')
					: result.ruleId;

		notice.dataset
			.activeRuleType =
				result.ruleType;

		results.set(
			checkout,
			result
		);
	}

	/**
	 * Dispatch Automatic Discount change.
	 *
	 * Summary and other modules can consume
	 * this event.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {Object}      result   Result.
	 *
	 * @return {void}
	 */
	function dispatchChange(
		checkout,
		result
	) {
		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:discountChange',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						source:
							'automatic',

						/*
						 * Backward compatibility.
						 *
						 * productTotal now means the
						 * Automatic Discount eligible
						 * base, not Summary Product Total.
						 */
						productTotal:
							result
								.automaticDiscountBase,

						normalProductTotal:
							result
								.normalProductTotal,

						comboAutomaticTotal:
							result
								.comboAutomaticTotal,

						orderBumpAutomaticTotal:
							result
								.orderBumpAutomaticTotal,

						automaticDiscountBase:
							result
								.automaticDiscountBase,

						ruleId:
							result.ruleId,

						ruleIds:
							result.ruleIds,

						ruleType:
							result.ruleType,

						discount:
							result.discount,

						automaticDiscount:
							result.discount,

						discountLabel:
							result.discountLabel,

						freeDelivery:
							result.freeDelivery,

						hasActiveRule:
							result.hasActiveRule,

						hasUpcomingRule:
							result.hasUpcomingRule,
					},
				}
			)
		);
	}

	/**
	 * Refresh Automatic Discount.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object|null}
	 */
	function refresh(checkout) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return null;
		}

		const notice =
			checkout.querySelector(
				SELECTORS.notice
			);

		/*
		 * No notice means Automatic Discounts are
		 * not active for this checkout.
		 *
		 * This happens when:
		 *
		 * - Discounts are disabled.
		 * - Notice is disabled.
		 * - No automatic rules exist.
		 * - Full Payment Discount has precedence.
		 */
		if (
			!(
				window.eilmoCfDom.isElement(notice)
			)
		) {
			return null;
		}

		/*
		 * --------------------------------------
		 * Automatic Discount basis
		 * --------------------------------------
		 *
		 * NORMAL PRODUCT
		 * +
		 * explicitly eligible Combo Offer
		 * +
		 * explicitly eligible Order Bump
		 */
		const base =
			getAutomaticDiscountBase(
				checkout
			);

		const normalProductTotal =
			base.normalProductTotal;

		const comboAutomaticTotal =
			base.comboTotal;

		const orderBumpAutomaticTotal =
			base.orderBumpTotal;

		const productTotal =
			base.automaticDiscountBase;

		const conditionContext =
			getConditionContext(checkout);

		const rules =
			getRules(
				notice
			);

		const texts =
			getTexts(
				notice
			);

		let activeRule = null;
		let activeRules = [];

		let upcomingRule =
			null;

		let discount =
			0;

		let discountLabel =
			'';

		let ruleId =
			'';

		let ruleType =
			'';

		let freeDelivery =
			false;

		if (productTotal > 0) {
			activeRules = findMatchingRules(
				rules,
				productTotal,
				conditionContext
			);
			activeRule = activeRules[0] || null;
		}

		let freeDeliveryMethodId = '';
		let freeDeliveryHideOtherMethods = false;

		if (activeRule) {
			const calculations = activeRules.map(function (rule) {
				return {
					rule: rule,
					discountBase: getRuleDiscountBase(rule, conditionContext, productTotal),
					calculation: calculateRuleDiscount(rule, getRuleDiscountBase(rule, conditionContext, productTotal)),
				};
			});
			const calculation = calculations[0].calculation;

			discount = roundMoney(
				calculations.reduce(function (total, entry) {
					return total + entry.calculation.discount;
				}, 0)
			);

			freeDelivery = calculations.some(function (entry) {
				return entry.calculation.freeDelivery;
			});

			const freeDeliveryRule = activeRules.find(function (rule) {
				return 'free_delivery' === rule.type;
			});
			if (freeDeliveryRule) {
				freeDeliveryMethodId = String(freeDeliveryRule.freeDeliveryMethodId || '');
				freeDeliveryHideOtherMethods = Boolean(freeDeliveryRule.freeDeliveryHideOtherMethods);
			}

			discountLabel =
				getDiscountLabel(
					activeRule,
					calculation,
					false
				);

			ruleId =
				activeRule.id;

			ruleType =
				activeRule.type;

			updateStatus(
				notice,
				getActiveStatus(
					activeRule,
					calculation,
					productTotal,
					texts
				)
			);

		} else {

			upcomingRule =
				findUpcomingRule(
					rules,
					productTotal
				);

			if (
				productTotal <= 0
			) {
				updateStatus(
					notice,
					texts.empty_status ||
						'Select products to unlock your discount.'
				);

			} else if (
				upcomingRule
			) {
				updateStatus(
					notice,
					getUpcomingStatus(
						upcomingRule,
						productTotal,
						texts
					)
				);

			} else {
				updateStatus(
					notice,
					''
				);
			}
		}

		/*
		 * Before selecting an eligible product,
		 * highlight the first available discount
		 * tier as next.
		 */
		if (
			productTotal <= 0 &&
			!upcomingRule
		) {
			upcomingRule =
				findUpcomingRule(
					rules,
					0
				);
		}

		updateTierStates(
			notice,
			rules,
			activeRules,
			upcomingRule,
			productTotal,
			conditionContext
		);

		discount =
			roundMoney(
				discount
			);

		/*
		 * Final safety:
		 *
		 * Automatic Discount can never exceed the
		 * eligible Automatic Discount basis.
		 */
		discount =
			Math.min(
				discount,
				productTotal
			);

		discount =
			roundMoney(
				discount
			);

		const result = {
			/*
			 * Backward-compatible productTotal.
			 *
			 * This is intentionally the Automatic
			 * Discount base.
			 */
			productTotal:
				productTotal,

			normalProductTotal:
				normalProductTotal,

			comboAutomaticTotal:
				comboAutomaticTotal,

			orderBumpAutomaticTotal:
				orderBumpAutomaticTotal,

			automaticDiscountBase:
				productTotal,

			ruleId:
				ruleId,

			ruleIds:
				activeRules.map(function (rule) {
					return rule.id;
				}),

			ruleType:
				ruleType,

			discount:
				discount,

			automaticDiscount:
				discount,

			discountLabel:
				discountLabel,

			freeDelivery:
				freeDelivery,

			freeDeliveryMethodId:
				freeDeliveryMethodId,

			freeDeliveryHideOtherMethods:
				freeDeliveryHideOtherMethods,

			hasActiveRule:
				activeRules.length > 0,

			hasUpcomingRule:
				null !==
					upcomingRule,
		};

		storeResult(
			checkout,
			notice,
			result
		);

		/*
		 * Summary listens to this event and updates
		 * the automatic-discount row.
		 *
		 * We intentionally do not call
		 * Summary.setAmount() here to prevent
		 * duplicate recalculations.
		 */
		dispatchChange(
			checkout,
			result
		);

		return result;
	}

	/**
	 * Schedule refresh.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function scheduleRefresh(
		checkout
	) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			) ||
			scheduled.has(
				checkout
			)
		) {
			return;
		}

		scheduled.add(
			checkout
		);

		Promise.resolve().then(
			function () {
				scheduled.delete(
					checkout
				);

				refresh(
					checkout
				);
			}
		);
	}

	/**
	 * Get checkout from event.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {HTMLElement|null}
	 */
	function getCheckoutFromEvent(
		event
	) {
		if (
			event.detail &&
			window.eilmoCfDom.isElement(event.detail.checkout)
		) {
			return event.detail
				.checkout;
		}

		if (
			window.eilmoCfDom.isElement(event.target)
		) {
			const checkout =
				event.target.closest(
					SELECTORS.checkout
				);

			return window.eilmoCfDom.isElement(checkout)
					? checkout
					: null;
		}

		return null;
	}

	/**
	 * Initialize one checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function initialize(
		checkout
	) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			) ||
			initialized.has(
				checkout
			)
		) {
			return;
		}

		const notice =
			checkout.querySelector(
				SELECTORS.notice
			);

		if (
			!(
				window.eilmoCfDom.isElement(notice)
			)
		) {
			return;
		}

		initialized.add(
			checkout
		);

		refresh(
			checkout
		);
	}

	/**
	 * Initialize all checkouts.
	 *
	 * @return {void}
	 */
	function initializeAll() {
		document
			.querySelectorAll(
				SELECTORS.checkout
			)
			.forEach(
				function (checkout) {
					if (
						window.eilmoCfDom.isElement(checkout)
					) {
						initialize(
							checkout
						);
					}
				}
			);
	}

	/**
	 * Normal product quantity changed.
	 *
	 * Automatic Discount must recalculate.
	 */
	document.addEventListener(
		'eilmo:quantityChange',
		function (event) {
			const checkout =
				getCheckoutFromEvent(
					event
				);

			if (checkout) {
				scheduleRefresh(
					checkout
				);
			}
		}
	);

	/**
	 * Multiple Products selection changed.
	 *
	 * Quantity events normally cover the same interaction, while
	 * this root event also protects controller-level selection sync.
	 * scheduleRefresh() coalesces both into one calculation.
	 */
	document.addEventListener(
		'eilmo:multipleProductsChange',
		function (event) {
			const checkout =
				getCheckoutFromEvent(
					event
				);

			if (checkout) {
				scheduleRefresh(
					checkout
				);
			}
		}
	);

	/**
	 * Order Bump selection changed.
	 *
	 * Recalculate only because a selected Order Bump
	 * MAY explicitly allow Automatic Discount.
	 *
	 * Order Bumps with:
	 *
	 * apply_automatic_discount = no
	 *
	 * still produce the same Automatic Discount base.
	 */
	document.addEventListener(
		'eilmo:orderBumpChange',
		function (event) {
			const checkout =
				getCheckoutFromEvent(
					event
				);

			if (checkout) {
				scheduleRefresh(
					checkout
				);
			}
		}
	);

	/**
	 * Combo Offer selection changed.
	 *
	 * Recalculate because a selected Combo Offer
	 * MAY explicitly allow Automatic Discount.
	 *
	 * Combo Offers with:
	 *
	 * apply_automatic_discount = no
	 *
	 * do not change the Automatic Discount base.
	 */
	document.addEventListener(
		'eilmo:comboChange',
		function (event) {
			const checkout =
				getCheckoutFromEvent(
					event
				);

			if (checkout) {
				scheduleRefresh(
					checkout
				);
			}
		}
	);

	/**
	 * Public Automatic Discount API.
	 */
	window.eilmoCfDiscount = {

		/**
		 * Refresh one checkout.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object|null}
		 */
		refresh:
			refresh,

		/**
		 * Get current result.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object|null}
		 */
		getResult:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return null;
				}

				return (
					results.get(
						checkout
					) ||
						null
				);
			},

		/**
		 * Get current Automatic Discount.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {number}
		 */
		getDiscount:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return 0;
				}

				const result =
					results.get(
						checkout
					);

				if (result) {
					return toNumber(
						result.discount
					);
				}

				return toNumber(
					checkout.dataset
						.automaticDiscount ||
						0
				);
			},

		/**
		 * Get current Automatic Discount base.
		 *
		 * Eligible Combo Offers and eligible Order Bumps
		 * are included with their payable promotional totals.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {number}
		 */
		getBase:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return 0;
				}

				const result =
					results.get(
						checkout
					);

				if (
					result &&
					Number.isFinite(
						Number(
							result
								.automaticDiscountBase
						)
					)
				) {
					return toNumber(
						result
							.automaticDiscountBase
					);
				}

				return getAutomaticDiscountBase(
					checkout
				)
					.automaticDiscountBase;
			},

		/**
		 * Get current base breakdown.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object}
		 */
		getBaseBreakdown:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return {
						normalProductTotal:
							0,

						comboTotal:
							0,

						orderBumpTotal:
							0,

						automaticDiscountBase:
							0,
					};
				}

				return getAutomaticDiscountBase(
					checkout
				);
			},
	};

	if (
		'loading' ===
			document.readyState
	) {
		document.addEventListener(
			'DOMContentLoaded',
			initializeAll
		);
	} else {
		initializeAll();
	}
})();
/* END src/js/frontend/discount.js */

/* BEGIN src/js/frontend/combo.js */
/**
 * Eilmo Checkout Flow - Combo Offers.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		checkout:
			'[data-eilmo-checkout]',

		section:
			'[data-eilmo-combo-offers]',

		offer:
			'[data-eilmo-combo-offer]',

		input:
			'[data-eilmo-combo-input]',

		action:
			'.eilmo-cf-combo-offer__action',

		trigger:
			'[data-eilmo-combo-trigger]',
	};

	const initializedSections =
		new WeakSet();

	/**
	 * Normalize Combo ID.
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
			.toLowerCase()
			.replace(
				/[^a-z0-9_-]/g,
				''
			);
	}

	/**
	 * Normalize IDs.
	 *
	 * @param {*} values Values.
	 *
	 * @return {Array<string>}
	 */
	function normalizeIds(values) {
		if (
			!Array.isArray(
				values
			)
		) {
			return [];
		}

		const result =
			[];

		values.forEach(
			function (value) {
				const id =
					normalizeId(
						value
					);

				if (
					!id ||
					result.includes(
						id
					)
				) {
					return;
				}

				result.push(
					id
				);
			}
		);

		return result;
	}

	/**
	 * Normalize selection mode.
	 *
	 * @param {*} value Value.
	 *
	 * @return {string}
	 */
	function normalizeSelectionMode(
		value
	) {
		return value ===
			'single'
				? 'single'
				: 'multiple';
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
	 * Get section.
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
	 * Get localized text.
	 *
	 * @param {string} key Key.
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
	 * Get signed context.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getContext(checkout) {
		const section =
			getSection(
				checkout
			);

		const empty = {
			allowed_ids:
				[],

			instance_id:
				'',

			selection_mode:
				'multiple',

			signature:
				'',
		};

		if (!section) {
			return empty;
		}

		const raw =
			String(
				section.dataset
					.eilmoComboContext ||
					''
			).trim();

		if (!raw) {
			return empty;
		}

		try {
			const parsed =
				JSON.parse(
					raw
				);

			if (
				!parsed ||
				typeof parsed !==
					'object' ||
				Array.isArray(
					parsed
				)
			) {
				return empty;
			}

			return {
				allowed_ids:
					normalizeIds(
						parsed.allowed_ids
					),

				instance_id:
					String(
						parsed.instance_id ||
							''
					).trim(),

				selection_mode:
					normalizeSelectionMode(
						parsed.selection_mode
					),

				signature:
					String(
						parsed.signature ||
							''
					).trim(),
			};

		} catch (error) {
			return empty;
		}
	}

	/**
	 * Get UI selection mode.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getUiSelectionMode(
		checkout
	) {
		const section =
			getSection(
				checkout
			);

		if (!section) {
			return 'multiple';
		}

		return normalizeSelectionMode(
			section.dataset
				.selectionMode
		);
	}

	/**
	 * Get authoritative frontend mode.
	 *
	 * Signed context is preferred.
	 *
	 * Server still verifies the signature.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getSelectionMode(
		checkout
	) {
		const context =
			getContext(
				checkout
			);

		if (
			context.signature &&
			context.selection_mode
		) {
			return context.selection_mode;
		}

		return getUiSelectionMode(
			checkout
		);
	}

	/**
	 * Get offer cards.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Array<HTMLElement>}
	 */
	function getOfferCards(
		checkout
	) {
		const section =
			getSection(
				checkout
			);

		if (!section) {
			return [];
		}

		return Array.from(
			section.querySelectorAll(
				SELECTORS.offer
			)
		).filter(
			function (offer) {
				return window.eilmoCfDom.isElement(offer);
			}
		);
	}

	/**
	 * Get input.
	 *
	 * @param {HTMLElement} offer Offer.
	 *
	 * @return {HTMLInputElement|null}
	 */
	function getOfferInput(
		offer
	) {
		const input =
			offer.querySelector(
				SELECTORS.input
			);

		return window.eilmoCfDom.isElement(input, 'INPUT')
				? input
				: null;
	}

	/**
	 * Get offer ID.
	 *
	 * @param {HTMLElement} offer Offer.
	 *
	 * @return {string}
	 */
	function getOfferId(
		offer
	) {
		const datasetId =
			normalizeId(
				offer.dataset
					.comboId ||
					''
			);

		if (datasetId) {
			return datasetId;
		}

		const input =
			getOfferInput(
				offer
			);

		return input
			? normalizeId(
				input.value
			)
			: '';
	}

	/**
	 * Is available.
	 *
	 * @param {HTMLElement} offer Offer.
	 *
	 * @return {boolean}
	 */
	function isOfferAvailable(
		offer
	) {
		const input =
			getOfferInput(
				offer
			);

		if (
			input &&
			input.disabled
		) {
			return false;
		}

		return offer.dataset
			.available !==
				'no';
	}

	/**
	 * Is selected.
	 *
	 * @param {HTMLElement} offer Offer.
	 *
	 * @return {boolean}
	 */
	function isOfferSelected(
		offer
	) {
		const input =
			getOfferInput(
				offer
			);

		return Boolean(
			input &&
			input.checked &&
			!input.disabled
		);
	}

	/**
	 * Get selected IDs.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Array<string>}
	 */
	function getSelectedIds(
		checkout
	) {
		const selected =
			[];

		getOfferCards(
			checkout
		).forEach(
			function (offer) {
				if (
					!isOfferSelected(
						offer
					)
				) {
					return;
				}

				const id =
					getOfferId(
						offer
					);

				if (
					id &&
					!selected.includes(
						id
					)
				) {
					selected.push(
						id
					);
				}
			}
		);

		return selected;
	}

	/**
	 * Numeric dataset.
	 *
	 * @param {HTMLElement} offer Offer.
	 * @param {string} key Key.
	 *
	 * @return {number}
	 */
	function getNumericDataset(
		offer,
		key
	) {
		const value =
			Number.parseFloat(
				String(
					offer.dataset[
						key
					] ||
						'0'
				)
			);

		return Number.isFinite(
			value
		)
			? Math.max(
				0,
				value
			)
			: 0;
	}

	/**
	 * Selected Combo presentation values.
	 *
	 * Display only.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Array<Object>}
	 */
	function getSelectedOffers(
		checkout
	) {
		const selected =
			[];

		getOfferCards(
			checkout
		).forEach(
			function (offer) {
				if (
					!isOfferSelected(
						offer
					)
				) {
					return;
				}

				const id =
					getOfferId(
						offer
					);

				if (!id) {
					return;
				}

				selected.push(
					{
						combo_id:
							id,

						regular_total:
							getNumericDataset(
								offer,
								'regularTotal'
							),

						combo_total:
							getNumericDataset(
								offer,
								'comboTotal'
							),

						discount:
							getNumericDataset(
								offer,
								'comboDiscount'
							),
					}
				);
			}
		);

		return selected;
	}

	/**
	 * Get payload data.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getData(
		checkout
	) {
		const context =
			getContext(
				checkout
			);

		return {
			selected_ids:
				getSelectedIds(
					checkout
				),

			selection_mode:
				getSelectionMode(
					checkout
				),

			context: {
				allowed_ids:
					context.allowed_ids,

				instance_id:
					context.instance_id,

				selection_mode:
					context.selection_mode,

				signature:
					context.signature,
			},
		};
	}

	/**
	 * Action label.
	 *
	 * @param {HTMLElement} offer Offer.
	 * @param {boolean} selected Selected.
	 *
	 * @return {void}
	 */
	function updateActionLabel(
		offer,
		selected
	) {
		const action =
			offer.querySelector(
				SELECTORS.action
			);

		if (
			!(
				window.eilmoCfDom.isElement(action)
			)
		) {
			return;
		}

		action.textContent =
			selected
				? getText(
					'comboSelected',
					'Selected'
				)
				: getText(
					'comboAdd',
					'Add Combo'
				);
	}

	/**
	 * Sync offer.
	 *
	 * @param {HTMLElement} offer Offer.
	 *
	 * @return {void}
	 */
	function syncOfferState(
		offer
	) {
		const selected =
			isOfferSelected(
				offer
			);

		const available =
			isOfferAvailable(
				offer
			);

		offer.dataset.selected =
			selected
				? 'yes'
				: 'no';

		offer.classList.toggle(
			'is-selected',
			selected
		);

		offer.classList.toggle(
			'is-unavailable',
			!available
		);

		updateActionLabel(
			offer,
			selected
		);
	}

	/**
	 * Single mode enforcement.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLInputElement|null} preferred Preferred.
	 *
	 * @return {void}
	 */
	function enforceSingleSelection(
		checkout,
		preferred = null
	) {
		if (
			getSelectionMode(
				checkout
			) !== 'single'
		) {
			return;
		}

		const checked =
			getOfferCards(
				checkout
			)
				.map(
					getOfferInput
				)
				.filter(
					function (input) {
						return (
							window.eilmoCfDom.isElement(input, 'INPUT') &&
							input.checked &&
							!input.disabled
						);
					}
				);

		if (
			checked.length <= 1
		) {
			return;
		}

		const keep =
			window.eilmoCfDom.isElement(preferred, 'INPUT') &&
			preferred.checked &&
			!preferred.disabled
				? preferred
				: checked[0];

		checked.forEach(
			function (input) {
				if (
					input !==
						keep
				) {
					input.checked =
						false;
				}
			}
		);
	}

	/**
	 * Sync checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function syncCheckout(
		checkout
	) {
		enforceSingleSelection(
			checkout
		);

		getOfferCards(
			checkout
		).forEach(
			syncOfferState
		);
	}

	/**
	 * Find the checkout controlled by an external Combo Button.
	 *
	 * A button inside a checkout controls that checkout. A button elsewhere on
	 * the Elementor page controls the first checkout whose signed Combo state
	 * contains the requested Combo ID.
	 *
	 * @param {HTMLElement} trigger Trigger button.
	 *
	 * @return {HTMLElement|null}
	 */
	function getTriggerCheckout(trigger) {
		const direct = getCheckout(trigger);
		if (direct) {
			return direct;
		}

		const comboId = normalizeId(trigger.dataset.comboId || '');
		if (!comboId) {
			return null;
		}

		const checkouts = Array.from(document.querySelectorAll(SELECTORS.checkout));
		for (const checkout of checkouts) {
			if (!(window.eilmoCfDom.isElement(checkout))) {
				continue;
			}

			const hasOffer = getOfferCards(checkout).some(function (offer) {
				return getOfferId(offer) === comboId;
			});

			if (hasOffer) {
				return checkout;
			}
		}

		return null;
	}

	/**
	 * Synchronize one external Combo Button with checkout state.
	 *
	 * @param {HTMLElement} trigger Trigger button.
	 *
	 * @return {void}
	 */
	function syncTrigger(trigger) {
		if (!(window.eilmoCfDom.isElement(trigger))) {
			return;
		}

		const comboId = normalizeId(trigger.dataset.comboId || '');
		const checkout = getTriggerCheckout(trigger);
		const selected = Boolean(
			comboId &&
			checkout &&
			getSelectedIds(checkout).includes(comboId)
		);

		trigger.classList.toggle('is-selected', selected);
		trigger.setAttribute('aria-pressed', selected ? 'true' : 'false');

		const label = trigger.querySelector('[data-eilmo-combo-trigger-text]');
		if (window.eilmoCfDom.isElement(label)) {
			const addLabel = String(trigger.dataset.addLabel || getText('comboAdd', 'Add Combo'));
			const addedLabel = String(trigger.dataset.addedLabel || getText('comboSelected', 'Combo Added'));
			label.textContent = selected ? addedLabel : addLabel;
		}
	}

	/**
	 * Synchronize all external Combo Buttons.
	 *
	 * @return {void}
	 */
	function syncTriggers() {
		document.querySelectorAll(SELECTORS.trigger).forEach(function (trigger) {
			if (window.eilmoCfDom.isElement(trigger)) {
				syncTrigger(trigger);
			}
		});
	}

	/**
	 * Dispatch change.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string} changedId Changed Combo.
	 *
	 * @return {void}
	 */
	function dispatchChange(
		checkout,
		changedId = ''
	) {
		const data =
			getData(
				checkout
			);

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:comboChange',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						changedId:
							normalizeId(
								changedId
							),

						selectedIds:
							data.selected_ids,

						selectedOffers:
							getSelectedOffers(
								checkout
							),

						selectionMode:
							data.selection_mode,

						context:
							data.context,
					},
				}
			)
		);

		syncTriggers();
	}

	/**
	 * Set selection.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLElement} offer Offer.
	 * @param {boolean} selected Selected.
	 * @param {boolean} notify Notify.
	 *
	 * @return {boolean}
	 */
	function setOfferSelected(
		checkout,
		offer,
		selected,
		notify = true
	) {
		if (
			!isOfferAvailable(
				offer
			)
		) {
			return false;
		}

		const input =
			getOfferInput(
				offer
			);

		if (!input) {
			return false;
		}

		if (
			selected &&
			getSelectionMode(
				checkout
			) === 'single'
		) {
			getOfferCards(
				checkout
			).forEach(
				function (
					otherOffer
				) {
					if (
						otherOffer ===
							offer
					) {
						return;
					}

					const otherInput =
						getOfferInput(
							otherOffer
						);

					if (otherInput) {
						otherInput.checked =
							false;
					}
				}
			);
		}

		input.checked =
			Boolean(
				selected
			);

		enforceSingleSelection(
			checkout,
			input
		);

		syncCheckout(
			checkout
		);

		if (notify) {
			dispatchChange(
				checkout,
				getOfferId(
					offer
				)
			);
		}

		return true;
	}

	/**
	 * Select Combo.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string} comboId Combo.
	 * @param {boolean} notify Notify.
	 *
	 * @return {boolean}
	 */
	function selectCombo(
		checkout,
		comboId,
		notify = true
	) {
		const id =
			normalizeId(
				comboId
			);

		const offer =
			getOfferCards(
				checkout
			).find(
				function (candidate) {
					return (
						getOfferId(
							candidate
						) === id
					);
				}
			);

		if (!offer) {
			return false;
		}

		return setOfferSelected(
			checkout,
			offer,
			true,
			notify
		);
	}

	/**
	 * Deselect Combo.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string} comboId Combo.
	 * @param {boolean} notify Notify.
	 *
	 * @return {boolean}
	 */
	function deselectCombo(
		checkout,
		comboId,
		notify = true
	) {
		const id =
			normalizeId(
				comboId
			);

		const offer =
			getOfferCards(
				checkout
			).find(
				function (candidate) {
					return (
						getOfferId(
							candidate
						) === id
					);
				}
			);

		if (!offer) {
			return false;
		}

		return setOfferSelected(
			checkout,
			offer,
			false,
			notify
		);
	}

	/**
	 * Clear selection.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {boolean} notify Notify.
	 *
	 * @return {void}
	 */
	function clearSelection(
		checkout,
		notify = true
	) {
		getOfferCards(
			checkout
		).forEach(
			function (offer) {
				const input =
					getOfferInput(
						offer
					);

				if (input) {
					input.checked =
						false;
				}
			}
		);

		syncCheckout(
			checkout
		);

		if (notify) {
			dispatchChange(
				checkout
			);
		}
	}

	/**
	 * Toggle offer.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLElement} offer Offer.
	 *
	 * @return {void}
	 */
	function toggleOffer(
		checkout,
		offer
	) {
		if (
			!isOfferAvailable(
				offer
			)
		) {
			return;
		}

		const input =
			getOfferInput(
				offer
			);

		if (!input) {
			return;
		}

		if (
			getSelectionMode(
				checkout
			) === 'single' &&
			input.checked
		) {
			setOfferSelected(
				checkout,
				offer,
				false,
				true
			);

			return;
		}

		setOfferSelected(
			checkout,
			offer,
			!input.checked,
			true
		);
	}

	/**
	 * Validate.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {boolean} showMessages Messages.
	 *
	 * @return {boolean}
	 */
	function validateCheckout(
		checkout,
		showMessages = true
	) {
		const section =
			getSection(
				checkout
			);

		if (!section) {
			return true;
		}

		const context =
			getContext(
				checkout
			);

		if (
			!context.signature ||
			!context.instance_id
		) {
			if (showMessages) {
				dispatchValidationError(
					checkout,
					getText(
						'comboInvalid',
						'Combo Offer verification failed. Please refresh the page and try again.'
					)
				);
			}

			return false;
		}

		/*
		 * UI mode and signed mode must match.
		 */
		if (
			getUiSelectionMode(
				checkout
			) !==
				context.selection_mode
		) {
			if (showMessages) {
				dispatchValidationError(
					checkout,
					getText(
						'comboInvalid',
						'Combo Offer settings have changed. Please refresh the page and try again.'
					)
				);
			}

			return false;
		}

		const selectedIds =
			getSelectedIds(
				checkout
			);

		const invalid =
			selectedIds.some(
				function (id) {
					return (
						!context.allowed_ids.includes(
							id
						)
					);
				}
			);

		if (invalid) {
			if (showMessages) {
				dispatchValidationError(
					checkout,
					getText(
						'comboInvalid',
						'One of the selected Combo Offers is no longer available.'
					)
				);
			}

			return false;
		}

		if (
			context.selection_mode ===
				'single' &&
			selectedIds.length > 1
		) {
			if (showMessages) {
				dispatchValidationError(
					checkout,
					getText(
						'comboSingle',
						'Please select only one Combo Offer.'
					)
				);
			}

			return false;
		}

		return true;
	}

	/**
	 * Dispatch validation error.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string} message Message.
	 *
	 * @return {void}
	 */
	function dispatchValidationError(
		checkout,
		message
	) {
		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:comboValidationError',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						message:
							String(
								message || ''
							),
					},
				}
			)
		);
	}

	/**
	 * Refresh.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function refresh(
		checkout
	) {
		if (
			window.eilmoCfDom.isElement(checkout)
		) {
			syncCheckout(
				checkout
			);
		}
	}

	/**
	 * Native input change.
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

		const input =
			event.target;

		const checkout =
			getCheckout(
				input
			);

		if (!checkout) {
			return;
		}

		const offer =
			input.closest(
				SELECTORS.offer
			);

		if (
			!(
				window.eilmoCfDom.isElement(offer)
			)
		) {
			return;
		}

		enforceSingleSelection(
			checkout,
			input
		);

		syncCheckout(
			checkout
		);

		dispatchChange(
			checkout,
			getOfferId(
				offer
			)
		);
	}

	/**
	 * Click handler.
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

		const trigger = event.target.closest(SELECTORS.trigger);
		if (window.eilmoCfDom.isElement(trigger)) {
			event.preventDefault();

			const checkout = getTriggerCheckout(trigger);
			const comboId = normalizeId(trigger.dataset.comboId || '');
			if (!checkout || !comboId) {
				return;
			}

			const selected = getSelectedIds(checkout).includes(comboId);
			if (selected) {
				if (trigger.dataset.allowRemove !== 'yes') {
					syncTrigger(trigger);
					return;
				}
				deselectCombo(checkout, comboId, true);
			} else {
				selectCombo(checkout, comboId, true);
			}

			syncTriggers();
			return;
		}

		const offer =
			event.target.closest(
				SELECTORS.offer
			);

		if (
			!(
				window.eilmoCfDom.isElement(offer)
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				offer
			);

		if (!checkout) {
			return;
		}

		if (
			event.target.closest(
				[
					'input',
					'button',
					'a',
					'select',
					'textarea',
					'label',
				].join(',')
			)
		) {
			return;
		}

		event.preventDefault();

		toggleOffer(
			checkout,
			offer
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

		if (
			!section ||
			initializedSections.has(
				section
			)
		) {
			return;
		}

		initializedSections.add(
			section
		);

		getOfferCards(
			checkout
		).forEach(
			function (offer) {
				const input =
					getOfferInput(
						offer
					);

				if (
					input &&
					!isOfferAvailable(
						offer
					)
				) {
					input.checked =
						false;

					input.disabled =
						true;
				}
			}
		);

		syncCheckout(
			checkout
		);
	}

	/**
	 * Initialize.
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

		syncTriggers();
	}

	window.eilmoCfComboOffers = {
		getData:
			getData,

		getContext:
			getContext,

		getSelectedIds:
			getSelectedIds,

		getSelectedOffers:
			getSelectedOffers,

		getSelectionMode:
			getSelectionMode,

		validateCheckout:
			validateCheckout,

		refresh:
			refresh,

		select:
			selectCombo,

		deselect:
			deselectCombo,

		clear:
			clearSelection,

		syncTriggers:
			syncTriggers,
	};

	document.addEventListener(
		'change',
		handleChange
	);

	document.addEventListener(
		'click',
		handleClick
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
/* END src/js/frontend/combo.js */

/* BEGIN src/js/frontend/delivery.js */
/**
 * Eilmo Checkout Flow - Delivery.
 *
 * Handles:
 * - Delivery method selection.
 * - Combo-priced checkout totals.
 * - Dynamic free-delivery thresholds.
 * - Automatic Discount free-delivery rules.
 * - Coupon free-delivery rules.
 * - Minimum amount 0 = immediately free.
 * - All delivery methods free.
 * - Specific delivery method free.
 * - Hide other methods.
 * - Restoring normal delivery charges.
 * - Delivery validation.
 * - Summary synchronization.
 * - Multiple checkout instances.
 *
 * Calculation order:
 *
 * Normal Products
 * + Combo Regular Total
 * - Combo Discount
 * - Automatic Discount
 * - Coupon Discount
 * = Delivery calculation basis.
 *
 * Important:
 * All frontend calculations are UI previews only.
 * The server-side DeliveryCalculator remains the
 * final authority for delivery availability and charge.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	/**
	 * Selectors.
	 *
	 * @type {Object}
	 */
	const SELECTORS = {
		checkout:
			'[data-eilmo-checkout], .eilmo-cf-checkout',

		item:
			'[data-eilmo-product-item], [data-eilmo-item]',

		quantityInput:
			'[data-eilmo-quantity-input]',

		comboOffer:
			'[data-eilmo-combo-offer]',

		comboInput:
			'[data-eilmo-combo-input]',

		orderBumpOffer:
			'[data-eilmo-order-bump]',

		delivery:
			'[data-eilmo-delivery]',

		method:
			'[data-eilmo-delivery-method]',

		input:
			'[data-eilmo-delivery-input]',

		price:
			'[data-eilmo-delivery-price]',

		saving:
			'[data-eilmo-delivery-saving]',

		error:
			'[data-eilmo-delivery-error]',
	};

	/**
	 * CSS classes.
	 *
	 * @type {Object}
	 */
	const CLASSES = {
		selected:
			'eilmo-cf-delivery-method--selected',

		free:
			'eilmo-cf-delivery-method--free',

		error:
			'eilmo-cf-delivery--has-error',
	};

	/**
	 * Scheduled checkout refreshes.
	 *
	 * Prevents duplicate recalculation when one action
	 * causes multiple dependent feature events.
	 *
	 * @type {WeakSet}
	 */
	const scheduled =
		new WeakSet();

	/**
	 * Convert value to non-negative number.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toNumber(value) {
		const number =
			Number.parseFloat(
				String(value)
			);

		return Number.isFinite(number)
			? Math.max(
				0,
				number
			)
			: 0;
	}

	/**
	 * Convert value to quantity.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toQuantity(value) {
		const quantity =
			Number.parseFloat(
				String(value)
			);

		if (
			!Number.isFinite(
				quantity
			)
		) {
			return 0;
		}

		return Math.max(
			0,
			quantity
		);
	}

	/**
	 * Convert value to a non-negative integer identity.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toInteger(value) {
		const number =
			Number.parseInt(
				String(
					value || 0
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
	 * Get WooCommerce price decimals.
	 *
	 * @return {number}
	 */
	function getPriceDecimals() {
		const config =
			window.eilmoCf || {};

		const currency =
			config.currency &&
			'object' ===
				typeof config.currency
				? config.currency
				: {};

		let decimals =
			Number(
				currency.decimals
			);

		if (
			!Number.isFinite(
				decimals
			)
		) {
			decimals =
				Number(
					config.priceDecimals
				);
		}

		if (
			!Number.isFinite(
				decimals
			)
		) {
			return 2;
		}

		return Math.max(
			0,
			Math.floor(
				decimals
			)
		);
	}

	/**
	 * Round monetary value.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function roundMoney(value) {
		const multiplier =
			Math.pow(
				10,
				getPriceDecimals()
			);

		return (
			Math.round(
				(
					toNumber(
						value
					) +
					Number.EPSILON
				) *
				multiplier
			) /
			multiplier
		);
	}

	/**
	 * Decode localized HTML entities.
	 *
	 * @param {string} value Value.
	 *
	 * @return {string}
	 */
	function decodeHtmlEntities(value) {
		if (
			'string' !==
				typeof value
		) {
			return '';
		}

		const textarea =
			document.createElement(
				'textarea'
			);

		textarea.innerHTML =
			value;

		return textarea.value
			.replace(
				/\u00a0/g,
				' '
			)
			.trim();
	}

	/**
	 * Get currency configuration.
	 *
	 * @return {Object}
	 */
	function getCurrencyConfig() {
		const config =
			window.eilmoCf &&
			window.eilmoCf.currency
				? window.eilmoCf.currency
				: {};

		return {
			symbol:
				decodeHtmlEntities(
					'string' ===
						typeof config.symbol
						? config.symbol
						: '৳'
				),

			position:
				'string' ===
					typeof config.position
					? config.position
					: 'left',

			decimals:
				Number.isInteger(
					config.decimals
				)
					? config.decimals
					: 2,

			decimalSeparator:
				'string' ===
					typeof config.decimalSeparator
					? decodeHtmlEntities(
						config.decimalSeparator
					)
					: '.',

			thousandSeparator:
				'string' ===
					typeof config.thousandSeparator
					? decodeHtmlEntities(
						config.thousandSeparator
					)
					: ',',
		};
	}

	/**
	 * Format number.
	 *
	 * @param {number} amount Amount.
	 *
	 * @return {string}
	 */
	function formatNumber(amount) {
		const config =
			getCurrencyConfig();

		const safeAmount =
			roundMoney(
				amount
			);

		const fixed =
			safeAmount.toFixed(
				config.decimals
			);

		const parts =
			fixed.split(
				'.'
			);

		parts[0] =
			parts[0].replace(
				/\B(?=(\d{3})+(?!\d))/g,
				config.thousandSeparator
			);

		if (
			config.decimals > 0 &&
			parts.length > 1
		) {
			return (
				parts[0] +
				config.decimalSeparator +
				parts[1]
			);
		}

		return parts[0];
	}

	/**
	 * Format WooCommerce-style price.
	 *
	 * Uses Summary formatter when available.
	 *
	 * @param {number} amount Amount.
	 *
	 * @return {string}
	 */
	function formatPrice(amount) {
		if (
			window.eilmoCfSummary &&
			'function' ===
				typeof window
					.eilmoCfSummary
					.formatPrice
		) {
			return window
				.eilmoCfSummary
				.formatPrice(
					amount
				);
		}

		const config =
			getCurrencyConfig();

		const formatted =
			formatNumber(
				amount
			);

		switch (
			config.position
		) {
			case 'left':
				return (
					config.symbol +
					formatted
				);

			case 'left_space':
				return (
					config.symbol +
					'\u00A0' +
					formatted
				);

			case 'right_space':
				return (
					formatted +
					'\u00A0' +
					config.symbol
				);

			case 'right':
			default:
				return (
					formatted +
					config.symbol
				);
		}
	}

	/**
	 * Get localized Free label.
	 *
	 * @return {string}
	 */
	function getFreeLabel() {
		if (
			window.eilmoCf &&
			window.eilmoCf.i18n &&
			'string' ===
				typeof window
					.eilmoCf
					.i18n
					.free
		) {
			return window
				.eilmoCf
				.i18n
				.free;
		}

		return 'Free';
	}

	/**
	 * Get required-selection message.
	 *
	 * @return {string}
	 */
	function getRequiredMessage() {
		if (
			window.eilmoCf &&
			window.eilmoCf.i18n &&
			'string' ===
				typeof window
					.eilmoCf
					.i18n
					.deliveryRequired
		) {
			return window
				.eilmoCf
				.i18n
				.deliveryRequired;
		}

		return 'Please select a delivery method.';
	}

	/**
	 * Get checkout wrapper.
	 *
	 * @param {Element} element Element.
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

		const checkout =
			element.closest(
				SELECTORS.checkout
			);

		return window.eilmoCfDom.isElement(checkout)
				? checkout
				: null;
	}

	/**
	 * Get checkout from custom event.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {HTMLElement|null}
	 */
	function getCheckoutFromEvent(
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
			return getCheckout(
				event.target
			);
		}

		return null;
	}

	/**
	 * Get delivery section.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {HTMLElement|null}
	 */
	function getDelivery(checkout) {
		const delivery =
			checkout.querySelector(
				SELECTORS.delivery
			);

		return window.eilmoCfDom.isElement(delivery)
				? delivery
				: null;
	}

	/**
	 * Calculate regular selected product total.
	 *
	 * Combo component products are calculated separately.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function calculateBaseProductTotal(
		checkout
	) {
		const itemTotals =
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
					 * Normal products only. Combo Offers and Order
					 * Bumps have independent payable totals and must
					 * never enter this base through nested adapters.
					 */
					if (
						item.closest(
							SELECTORS.comboOffer
						) ||
						item.closest(
							SELECTORS.orderBumpOffer
						)
					) {
						return;
					}

					const input =
						item.querySelector(
							SELECTORS.quantityInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						)
					) {
						return;
					}

					const quantity =
						toQuantity(
							input.value
						);

					if (
						quantity <= 0
					) {
						return;
					}

					let price =
						toNumber(
							item.dataset.price
						);

					if (
						price <= 0
					) {
						price =
							toNumber(
								item.dataset
									.productPrice
							);
					}

					if (
						price <= 0
					) {
						price =
							toNumber(
								input.dataset.price
							);
					}

					if (price <= 0) {
						return;
					}

					const productId =
						toInteger(
							item.dataset
								.productId ||
							input.dataset
								.productId
						);

					const variationId =
						toInteger(
							item.dataset
								.variationId ||
							input.dataset
								.variationId
						);

					/*
					 * Multiple Products may expose more than one DOM
					 * adapter for the same exact order item. Keep one
					 * amount per Simple product / variation and never
					 * add duplicate representations together.
					 */
					const key =
						variationId > 0
							? 'variation:' + variationId
							: productId > 0
								? 'product:' + productId
								: item;

					const lineTotal =
						roundMoney(
							price *
								quantity
						);

					const existing =
						toNumber(
							itemTotals.get(
								key
							) || 0
						);

					itemTotals.set(
						key,
						Math.max(
							existing,
							lineTotal
						)
					);
				}
			);

		let total =
			0;

		itemTotals.forEach(
			function (lineTotal) {
				total +=
					toNumber(
						lineTotal
					);
			}
		);

		return roundMoney(
			total
		);
	}

	/**
	 * Get selected Combo Offer totals.
	 *
	 * Frontend Combo totals are display previews only.
	 *
	 * Final Combo items and pricing are recalculated
	 * server-side.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getComboTotals(
		checkout
	) {
		/*
		 * Prefer Summary's canonical Combo calculation.
		 */
		if (
			window.eilmoCfSummary &&
			'function' ===
				typeof window
					.eilmoCfSummary
					.getComboTotals
		) {
			const values =
				window.eilmoCfSummary
					.getComboTotals(
						checkout
					);

			if (
				values &&
				'object' ===
					typeof values
			) {
				const regularTotal =
					roundMoney(
						values.regularTotal ||
							0
					);

				const discount =
					roundMoney(
						Math.min(
							toNumber(
								values.discount ||
									0
							),
							regularTotal
						)
					);

				const hasComboTotal =
					Object.prototype
						.hasOwnProperty.call(
							values,
							'comboTotal'
						);

				return {
					selectedCount:
						Math.max(
							0,
							Number.parseInt(
								String(
									values.selectedCount ||
										0
								),
								10
							) || 0
						),

					regularTotal:
						regularTotal,

					discount:
						discount,

					comboTotal:
						roundMoney(
							Math.min(
								regularTotal,
								hasComboTotal
									? toNumber(
										values.comboTotal
									)
									: Math.max(
										0,
										regularTotal -
											discount
									)
							)
						),
				};
			}
		}

		let regularTotal =
			0;

		let discount =
			0;

		let comboTotal =
			0;

		let selectedCount =
			0;

		/*
		 * Fallback to Combo public API.
		 */
		if (
			window.eilmoCfComboOffers &&
			'function' ===
				typeof window
					.eilmoCfComboOffers
					.getSelectedOffers
		) {
			const offers =
				window.eilmoCfComboOffers
					.getSelectedOffers(
						checkout
					);

			if (
				Array.isArray(
					offers
				)
			) {
				offers.forEach(
					function (offer) {
						if (
							!offer ||
							'object' !==
								typeof offer
						) {
							return;
						}

						const offerRegular =
							roundMoney(
								offer.regular_total ||
									0
							);

						const offerDiscount =
							roundMoney(
								Math.min(
									toNumber(
										offer.discount ||
											0
									),
									offerRegular
								)
							);

						const hasComboTotal =
							Object.prototype
								.hasOwnProperty.call(
									offer,
									'combo_total'
								);

						const offerCombo =
							roundMoney(
								Math.min(
									offerRegular,
									hasComboTotal
										? toNumber(
											offer.combo_total
										)
										: Math.max(
											0,
											offerRegular -
												offerDiscount
										)
								)
							);

						regularTotal =
							roundMoney(
								regularTotal +
									offerRegular
							);

						discount =
							roundMoney(
								discount +
									offerDiscount
							);

						comboTotal =
							roundMoney(
								comboTotal +
									offerCombo
							);

						selectedCount +=
							1;
					}
				);

				return {
					selectedCount:
						selectedCount,

					regularTotal:
						regularTotal,

					discount:
						roundMoney(
							Math.min(
								discount,
								regularTotal
							)
						),

					comboTotal:
						comboTotal,
				};
			}
		}

		/*
		 * DOM fallback.
		 */
		checkout
			.querySelectorAll(
				SELECTORS.comboOffer
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
							SELECTORS.comboInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						) ||
						!input.checked ||
						input.disabled ||
						'no' ===
							offer.dataset.available
					) {
						return;
					}

					const offerRegular =
						roundMoney(
							offer.dataset
								.regularTotal ||
								0
						);

					const offerDiscount =
						roundMoney(
							Math.min(
								toNumber(
									offer.dataset
										.comboDiscount ||
										0
								),
								offerRegular
							)
						);

					const hasComboTotal =
						'string' ===
							typeof offer
								.dataset
								.comboTotal;

					const offerCombo =
						roundMoney(
							Math.min(
								offerRegular,
								hasComboTotal
									? toNumber(
										offer.dataset
											.comboTotal
									)
									: Math.max(
										0,
										offerRegular -
											offerDiscount
									)
							)
						);

					regularTotal =
						roundMoney(
							regularTotal +
								offerRegular
						);

					discount =
						roundMoney(
							discount +
								offerDiscount
						);

					comboTotal =
						roundMoney(
							comboTotal +
								offerCombo
						);

					selectedCount +=
						1;
				}
			);

		return {
			selectedCount:
				selectedCount,

			regularTotal:
				regularTotal,

			discount:
				roundMoney(
					Math.min(
						discount,
						regularTotal
					)
				),

			comboTotal:
				comboTotal,
		};
	}

	/**
	 * Get current Automatic Discount.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function getAutomaticDiscount(
		checkout
	) {
		if (
			window.eilmoCfDiscount &&
			'function' ===
				typeof window
					.eilmoCfDiscount
					.getDiscount
		) {
			return roundMoney(
				window.eilmoCfDiscount
					.getDiscount(
						checkout
					)
			);
		}

		return roundMoney(
			checkout.dataset
				.automaticDiscount ||
				0
		);
	}

	/**
	 * Get current Coupon Discount.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function getCouponDiscount(
		checkout
	) {
		if (
			window.eilmoCfSummary &&
			'function' ===
				typeof window
					.eilmoCfSummary
					.getAmount
		) {
			return roundMoney(
				window.eilmoCfSummary
					.getAmount(
						checkout,
						'coupon-discount'
					)
			);
		}

		return roundMoney(
			checkout.dataset
				.couponDiscount ||
				0
		);
	}

	/**
	 * Build current delivery calculation totals.
	 *
	 * Server-side OrderCreator calculates delivery after:
	 *
	 * Combo pricing
	 * Automatic Discount
	 * Coupon Discount
	 *
	 * Full Payment Discount is intentionally NOT included
	 * because it is applied after Delivery.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getDeliveryTotals(
		checkout
	) {
		const baseProductTotal =
			calculateBaseProductTotal(
				checkout
			);

		const combo =
			getComboTotals(
				checkout
			);

		const regularProductTotal =
			roundMoney(
				baseProductTotal +
					combo.regularTotal
			);

		const comboDiscount =
			roundMoney(
				Math.min(
					combo.discount,
					regularProductTotal
				)
			);

		const afterComboDiscount =
			roundMoney(
				Math.max(
					0,
					regularProductTotal -
						comboDiscount
				)
			);

		const automaticDiscount =
			roundMoney(
				Math.min(
					getAutomaticDiscount(
						checkout
					),
					afterComboDiscount
				)
			);

		const afterAutomaticDiscount =
			roundMoney(
				Math.max(
					0,
					afterComboDiscount -
						automaticDiscount
				)
			);

		const couponDiscount =
			roundMoney(
				Math.min(
					getCouponDiscount(
						checkout
					),
					afterAutomaticDiscount
				)
			);

		const discountedProductTotal =
			roundMoney(
				Math.max(
					0,
					afterAutomaticDiscount -
						couponDiscount
				)
			);

		return {
			baseProductTotal:
				baseProductTotal,

			comboSelectedCount:
				combo.selectedCount,

			comboRegularTotal:
				combo.regularTotal,

			comboDiscount:
				comboDiscount,

			comboTotal:
				combo.comboTotal,

			regularProductTotal:
				regularProductTotal,

			automaticDiscount:
				automaticDiscount,

			couponDiscount:
				couponDiscount,

			discountedProductTotal:
				discountedProductTotal,

			deliveryBasisTotal:
				discountedProductTotal,
		};
	}

	/**
	 * Get current product total used by Delivery.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {number}
	 */
	function getProductTotal(
		checkout
	) {
		return getDeliveryTotals(
			checkout
		).deliveryBasisTotal;
	}

	/**
	 * Get external free-delivery state.
	 *
	 * External sources:
	 *
	 * - Automatic Discount free-delivery rule.
	 * - Applied Coupon free-delivery rule.
	 *
	 * These sources override configured delivery
	 * threshold charge behavior and make all currently
	 * available delivery methods free.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getExternalFreeDeliveryState(
		checkout
	) {
		const automaticDiscount = 'yes' === checkout.dataset.automaticDiscountFreeDelivery;
		const coupon = 'yes' === checkout.dataset.couponFreeDelivery;
		const methodId = coupon ? '' : String(checkout.dataset.automaticDiscountFreeDeliveryMethod || '');
		const hideOtherMethods = !coupon && 'yes' === checkout.dataset.automaticDiscountFreeDeliveryHideOtherMethods;

		return {
			active: coupon || automaticDiscount,
			coupon: coupon,
			automaticDiscount: automaticDiscount,
			source: coupon ? 'coupon' : (automaticDiscount ? 'special_discount' : ''),
			methodId: methodId,
			hideOtherMethods: hideOtherMethods,
		};
	}

	/**
	 * Get selected delivery input.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 *
	 * @return {HTMLInputElement|null}
	 */
	function getSelectedInput(
		delivery
	) {
		const input =
			delivery.querySelector(
				SELECTORS.input +
					':checked'
			);

		return window.eilmoCfDom.isElement(input, 'INPUT')
				? input
				: null;
	}

	/**
	 * Get method wrapper.
	 *
	 * @param {HTMLInputElement} input Input.
	 *
	 * @return {HTMLElement|null}
	 */
	function getMethodFromInput(
		input
	) {
		const method =
			input.closest(
				SELECTORS.method
			);

		return window.eilmoCfDom.isElement(method)
				? method
				: null;
	}

	/**
	 * Determine whether configured method exists.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 * @param {string}      methodId Method ID.
	 *
	 * @return {boolean}
	 */
	function methodExists(
		delivery,
		methodId
	) {
		if (!methodId) {
			return false;
		}

		return Array.from(
			delivery.querySelectorAll(
				SELECTORS.method
			)
		).some(
			function (method) {
				return (
					window.eilmoCfDom.isElement(method) &&
					method.dataset
						.methodId ===
						methodId
				);
			}
		);
	}

	/**
	 * Update method price UI.
	 *
	 * @param {HTMLElement} method      Method.
	 * @param {number}      charge      Current charge.
	 * @param {boolean}     freeApplied Free applied.
	 * @param {string}      freeSource  Free source.
	 *
	 * @return {void}
	 */
	function updateMethodPrice(
		method,
		charge,
		freeApplied,
		freeSource = ''
	) {
		const price =
			method.querySelector(
				SELECTORS.price
			);

		if (
			window.eilmoCfDom.isElement(price)
		) {
			price.textContent =
				charge <= 0
					? getFreeLabel()
					: formatPrice(
						charge
					);
		}

		/*
		 * A naturally zero-price method is also visually
		 * represented as Free.
		 */
		method.classList.toggle(
			CLASSES.free,
			charge <= 0
		);

		method.dataset.freeDelivery =
			freeApplied
				? 'yes'
				: 'no';

		method.dataset.freeDeliverySource =
			freeApplied
				? String(
					freeSource ||
						'configured'
				)
				: '';

		const saving =
			method.querySelector(
				SELECTORS.saving
			);

		if (
			window.eilmoCfDom.isElement(saving)
		) {
			const baseCharge =
				toNumber(
					method.dataset
						.baseCharge ||
						0
				);

			/*
			 * Show the saving notice only when a normally
			 * paid delivery method becomes free.
			 */
			saving.hidden =
				!(
					freeApplied &&
					baseCharge > 0
				);
		}
	}

	/**
	 * Restore one method to normal charge.
	 *
	 * @param {HTMLElement} method Method.
	 *
	 * @return {void}
	 */
	function restoreMethod(
		method
	) {
		const baseCharge =
			toNumber(
				method.dataset
					.baseCharge ||
					0
			);

		method.dataset.charge =
			String(
				baseCharge
			);

		const input =
			method.querySelector(
				SELECTORS.input
			);

		if (
			window.eilmoCfDom.isElement(input, 'INPUT')
		) {
			input.dataset.charge =
				String(
					baseCharge
				);

			input.dataset.baseCharge =
				String(
					baseCharge
				);
		}

		updateMethodPrice(
			method,
			baseCharge,
			false
		);

		method.hidden =
			false;
	}

	/**
	 * Apply dynamic free-delivery settings.
	 *
	 * Configured free delivery:
	 *
	 * - Minimum 0 = immediately active.
	 * - Blank method = all methods free.
	 * - Specific method = only that method free.
	 * - Hide Other Methods applies to configured
	 *   specific-method rules.
	 *
	 * External free delivery:
	 *
	 * - Coupon free delivery.
	 * - Automatic Discount free delivery.
	 * - Makes all currently available methods free.
	 * - Does not hide delivery alternatives.
	 *
	 * @param {HTMLElement} delivery     Delivery.
	 * @param {number}      productTotal Delivery calculation basis.
	 *
	 * @return {void}
	 */
	function applyFreeDelivery(
		delivery,
		productTotal
	) {
		const checkout = getCheckout(delivery);
		const reward = checkout
			? getExternalFreeDeliveryState(checkout)
			: {active: false, coupon: false, automaticDiscount: false, source: '', methodId: '', hideOtherMethods: false};

		/*
		 * Delivery JS no longer evaluates its own threshold. The unified Special
		 * Discount preview publishes an already-resolved reward, while PHP remains
		 * authoritative. Coupon free delivery stays an independent coupon benefit.
		 */
		const freeDeliveryActive = reward.active;
		const freeMethodId = String(reward.methodId || '');
		const hideOtherMethods = Boolean(reward.hideOtherMethods);
		const allMethodsFree = reward.coupon || '' === freeMethodId;
		const specificMethodExists = allMethodsFree || methodExists(delivery, freeMethodId);

		delivery.dataset.orderTotal = String(productTotal);
		delivery.dataset.freeDeliveryActive = freeDeliveryActive ? 'yes' : 'no';
		delivery.dataset.configuredFreeDeliveryActive = 'no';
		delivery.dataset.externalFreeDeliveryActive = freeDeliveryActive ? 'yes' : 'no';
		delivery.dataset.couponFreeDelivery = reward.coupon ? 'yes' : 'no';
		delivery.dataset.automaticDiscountFreeDelivery = reward.automaticDiscount ? 'yes' : 'no';
		delivery.dataset.freeDeliverySource = reward.source;

		delivery.querySelectorAll(SELECTORS.method).forEach(function (method) {
			if (!(window.eilmoCfDom.isElement(method))) return;
			restoreMethod(method);
			if (!freeDeliveryActive || !specificMethodExists) return;

			const methodId = method.dataset.methodId || '';
			const shouldBeFree = allMethodsFree || methodId === freeMethodId;
			if (shouldBeFree) {
				method.dataset.charge = '0';
				const input = method.querySelector(SELECTORS.input);
				if (window.eilmoCfDom.isElement(input, 'INPUT')) input.dataset.charge = '0';
				updateMethodPrice(method, 0, true, reward.source);
			}

			if (reward.coupon || allMethodsFree || !hideOtherMethods) {
				method.hidden = false;
			} else {
				method.hidden = methodId !== freeMethodId;
			}
		});

		ensureValidSelection(delivery);
	}

	/**
	 * Ensure selected method remains available.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 *
	 * @return {void}
	 */
	function ensureValidSelection(
		delivery
	) {
		const selected =
			getSelectedInput(
				delivery
			);

		if (selected) {
			const selectedMethod =
				getMethodFromInput(
					selected
				);

			if (
				selectedMethod &&
				!selectedMethod.hidden &&
				!selected.disabled
			) {
				return;
			}

			selected.checked =
				false;
		}

		const methods =
			Array.from(
				delivery.querySelectorAll(
					SELECTORS.method
				)
			);

		const available =
			methods.find(
				function (method) {
					if (
						!(
							window.eilmoCfDom.isElement(method)
						) ||
						method.hidden
					) {
						return false;
					}

					const input =
						method.querySelector(
							SELECTORS.input
						);

					return (
						window.eilmoCfDom.isElement(input, 'INPUT') &&
						!input.disabled
					);
				}
			);

		if (!available) {
			return;
		}

		const input =
			available.querySelector(
				SELECTORS.input
			);

		if (
			window.eilmoCfDom.isElement(input, 'INPUT')
		) {
			input.checked =
				true;
		}
	}

	/**
	 * Update selected card state.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 *
	 * @return {void}
	 */
	function updateSelectedClasses(
		delivery
	) {
		delivery
			.querySelectorAll(
				SELECTORS.method
			)
			.forEach(
				function (method) {
					if (
						!(
							window.eilmoCfDom.isElement(method)
						)
					) {
						return;
					}

					const input =
						method.querySelector(
							SELECTORS.input
						);

					const selected =
						window.eilmoCfDom.isElement(input, 'INPUT') &&
						input.checked;

					method.classList.toggle(
						CLASSES.selected,
						selected
					);

					method.dataset.selected =
						selected
							? 'yes'
							: 'no';

					method.setAttribute(
						'aria-selected',
						selected
							? 'true'
							: 'false'
					);
				}
			);
	}

	/**
	 * Build selected method state.
	 *
	 * @param {HTMLInputElement|null} input Input.
	 *
	 * @return {Object}
	 */
	function getMethodState(
		input
	) {
		const empty = {
			methodId:
				'',

			label:
				'',

			charge:
				0,

			baseCharge:
				0,

			isFree:
				false,

			freeDeliveryApplied:
				false,

			freeDeliverySource:
				'',
		};

		if (
			!(
				window.eilmoCfDom.isElement(input, 'INPUT')
			)
		) {
			return empty;
		}

		const method =
			getMethodFromInput(
				input
			);

		if (!method) {
			return empty;
		}

		const charge =
			toNumber(
				method.dataset.charge ||
					input.dataset.charge ||
					0
			);

		const baseCharge =
			toNumber(
				method.dataset.baseCharge ||
					input.dataset.baseCharge ||
					0
			);

		const labelElement =
			method.querySelector(
				'.eilmo-cf-delivery-method__label'
			);

		return {
			methodId:
				method.dataset.methodId ||
				input.dataset.methodId ||
				input.value ||
				'',

			label:
				labelElement
					? labelElement
						.textContent
						.trim()
					: '',

			charge:
				charge,

			baseCharge:
				baseCharge,

			/*
			 * Includes both:
			 *
			 * - rule-generated free delivery;
			 * - naturally zero-cost methods.
			 */
			isFree:
				charge <= 0,

			freeDeliveryApplied:
				'yes' ===
					method.dataset
						.freeDelivery,

			freeDeliverySource:
				method.dataset
					.freeDeliverySource ||
					'',
		};
	}

	/**
	 * Clear validation error.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 *
	 * @return {void}
	 */
	function clearError(
		delivery
	) {
		delivery.classList.remove(
			CLASSES.error
		);

		const error =
			delivery.querySelector(
				SELECTORS.error
			);

		if (
			!(
				window.eilmoCfDom.isElement(error)
			)
		) {
			return;
		}

		error.textContent =
			'';

		error.hidden =
			true;
	}

	/**
	 * Show validation error.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 * @param {string}      message  Message.
	 *
	 * @return {void}
	 */
	function showError(
		delivery,
		message
	) {
		delivery.classList.add(
			CLASSES.error
		);

		const error =
			delivery.querySelector(
				SELECTORS.error
			);

		if (
			!(
				window.eilmoCfDom.isElement(error)
			)
		) {
			return;
		}

		error.textContent =
			message;

		error.hidden =
			false;
	}

	/**
	 * Validate delivery selection.
	 *
	 * Client validation is UX only.
	 * Server validation remains authoritative.
	 *
	 * @param {HTMLElement} delivery    Delivery.
	 * @param {boolean}     showMessage Show message.
	 *
	 * @return {boolean}
	 */
	function validate(
		delivery,
		showMessage = true
	) {
		const required =
			'yes' ===
				delivery.dataset
					.required;

		if (!required) {
			clearError(
				delivery
			);

			return true;
		}

		const selected =
			getSelectedInput(
				delivery
			);

		if (selected) {
			const method =
				getMethodFromInput(
					selected
				);

			if (
				method &&
				!method.hidden &&
				!selected.disabled
			) {
				clearError(
					delivery
				);

				return true;
			}
		}

		if (showMessage) {
			showError(
				delivery,
				getRequiredMessage()
			);
		}

		return false;
	}

	/**
	 * Dispatch delivery change.
	 *
	 * Summary / Advance Payment listen to this event.
	 *
	 * @param {HTMLElement} delivery Delivery.
	 * @param {Object|null} totals   Delivery totals.
	 *
	 * @return {void}
	 */
	function dispatchDeliveryChange(
		delivery,
		totals = null
	) {
		const checkout =
			getCheckout(
				delivery
			);

		if (!checkout) {
			return;
		}

		const calculationTotals =
			totals &&
			'object' ===
				typeof totals
				? totals
				: getDeliveryTotals(
					checkout
				);

		const selected =
			getSelectedInput(
				delivery
			);

		const state =
			getMethodState(
				selected
			);

		delivery.dataset.selectedMethod =
			state.methodId;

		delivery.dataset.selectedCharge =
			String(
				state.charge
			);

		delivery.dataset.selectedBaseCharge =
			String(
				state.baseCharge
			);

		delivery.dataset.freeDeliveryApplied =
			state.freeDeliveryApplied
				? 'yes'
				: 'no';

		delivery.dataset.selectedFreeDeliverySource =
			state.freeDeliverySource ||
				'';

		/*
		 * Store calculation context.
		 */
		delivery.dataset.baseProductTotal =
			String(
				calculationTotals
					.baseProductTotal
			);

		delivery.dataset.comboRegularTotal =
			String(
				calculationTotals
					.comboRegularTotal
			);

		delivery.dataset.comboDiscount =
			String(
				calculationTotals
					.comboDiscount
			);

		delivery.dataset.regularProductTotal =
			String(
				calculationTotals
					.regularProductTotal
			);

		delivery.dataset.automaticDiscount =
			String(
				calculationTotals
					.automaticDiscount
			);

		delivery.dataset.couponDiscount =
			String(
				calculationTotals
					.couponDiscount
			);

		delivery.dataset.deliveryBasisTotal =
			String(
				calculationTotals
					.deliveryBasisTotal
			);

		/*
		 * Shared checkout delivery state.
		 */
		checkout.dataset.deliveryMethod =
			state.methodId;

		checkout.dataset.deliveryCharge =
			String(
				state.charge
			);

		checkout.dataset.deliveryBaseCharge =
			String(
				state.baseCharge
			);

		checkout.dataset.deliveryIsFree =
			state.isFree
				? 'yes'
				: 'no';

		checkout.dataset.freeDeliveryApplied =
			state.freeDeliveryApplied
				? 'yes'
				: 'no';

		checkout.dataset.freeDeliverySource =
			state.freeDeliverySource ||
				'';

		checkout.dataset.deliveryBasisTotal =
			String(
				calculationTotals
					.deliveryBasisTotal
			);

		delivery.dispatchEvent(
			new CustomEvent(
				'eilmo:deliveryChange',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						delivery:
							delivery,

						methodId:
							state.methodId,

						label:
							state.label,

						charge:
							state.charge,

						baseCharge:
							state.baseCharge,

						isFree:
							state.isFree,

						freeDeliveryApplied:
							state
								.freeDeliveryApplied,

						freeDeliverySource:
							state
								.freeDeliverySource,

						couponFreeDelivery:
							'yes' ===
								delivery.dataset
									.couponFreeDelivery,

						automaticDiscountFreeDelivery:
							'yes' ===
								delivery.dataset
									.automaticDiscountFreeDelivery,

						/*
						 * Calculation context.
						 */
						baseProductTotal:
							calculationTotals
								.baseProductTotal,

						comboSelectedCount:
							calculationTotals
								.comboSelectedCount,

						comboRegularTotal:
							calculationTotals
								.comboRegularTotal,

						comboDiscount:
							calculationTotals
								.comboDiscount,

						comboOfferDiscount:
							calculationTotals
								.comboDiscount,

						comboTotal:
							calculationTotals
								.comboTotal,

						regularProductTotal:
							calculationTotals
								.regularProductTotal,

						automaticDiscount:
							calculationTotals
								.automaticDiscount,

						couponDiscount:
							calculationTotals
								.couponDiscount,

						discountedProductTotal:
							calculationTotals
								.discountedProductTotal,

						productTotal:
							calculationTotals
								.deliveryBasisTotal,

						deliveryBasisTotal:
							calculationTotals
								.deliveryBasisTotal,

						freeDeliveryActive:
							'yes' ===
								delivery.dataset
									.freeDeliveryActive,

						valid:
							validate(
								delivery,
								false
							),
					},
				}
			)
		);
	}

	/**
	 * Refresh delivery state for one checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function refreshCheckout(
		checkout
	) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return;
		}

		const delivery =
			getDelivery(
				checkout
			);

		if (!delivery) {
			return;
		}

		const totals =
			getDeliveryTotals(
				checkout
			);

		applyFreeDelivery(
			delivery,
			totals.deliveryBasisTotal
		);

		updateSelectedClasses(
			delivery
		);

		clearError(
			delivery
		);

		dispatchDeliveryChange(
			delivery,
			totals
		);
	}

	/**
	 * Schedule delivery refresh.
	 *
	 * Combo change can cause:
	 *
	 * comboChange
	 * -> discountChange
	 * -> coupon/summary calculations
	 *
	 * Scheduling prevents unnecessary duplicate immediate
	 * refreshes while keeping state scoped to one checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function scheduleRefresh(
		checkout
	) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			) ||
			scheduled.has(
				checkout
			)
		) {
			return;
		}

		scheduled.add(
			checkout
		);

		Promise.resolve().then(
			function () {
				scheduled.delete(
					checkout
				);

				refreshCheckout(
					checkout
				);
			}
		);
	}

	/**
	 * Handle radio selection.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleChange(
		event
	) {
		const input =
			event.target;

		if (
			!(
				window.eilmoCfDom.isElement(input, 'INPUT')
			) ||
			!input.matches(
				SELECTORS.input
			) ||
			!input.checked
		) {
			return;
		}

		const delivery =
			input.closest(
				SELECTORS.delivery
			);

		if (
			!(
				window.eilmoCfDom.isElement(delivery)
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				delivery
			);

		clearError(
			delivery
		);

		updateSelectedClasses(
			delivery
		);

		dispatchDeliveryChange(
			delivery,
			checkout
				? getDeliveryTotals(
					checkout
				)
				: null
		);
	}

	/**
	 * Handle checkout calculation dependency change.
	 *
	 * Applies to:
	 *
	 * - Product quantities.
	 * - Combo selection.
	 * - Coupon state.
	 * - Automatic Discount state.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleCalculationChange(
		event
	) {
		const checkout =
			getCheckoutFromEvent(
				event
			);

		if (!checkout) {
			return;
		}

		scheduleRefresh(
			checkout
		);
	}

	/**
	 * Validate checkout delivery.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function validateCheckout(
		checkout
	) {
		const delivery =
			getDelivery(
				checkout
			);

		if (!delivery) {
			return true;
		}

		return validate(
			delivery,
			true
		);
	}

	/**
	 * Get selected delivery method.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object|null}
	 */
	function getSelected(
		checkout
	) {
		const delivery =
			getDelivery(
				checkout
			);

		if (!delivery) {
			return null;
		}

		return getMethodState(
			getSelectedInput(
				delivery
			)
		);
	}

	/**
	 * Get current delivery calculation totals.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object|null}
	 */
	function getTotals(
		checkout
	) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return null;
		}

		return getDeliveryTotals(
			checkout
		);
	}

	/**
	 * Initialize one checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function initializeCheckout(
		checkout
	) {
		const delivery =
			getDelivery(
				checkout
			);

		if (!delivery) {
			return;
		}

		if (
			'yes' ===
				delivery.dataset
					.eilmoInitialized
		) {
			return;
		}

		delivery.dataset.eilmoInitialized =
			'yes';

		/*
		 * Calculate immediately on page load.
		 *
		 * Important when:
		 *
		 * Free Delivery = ON
		 * Minimum Amount = 0
		 *
		 * It also restores / applies current Coupon,
		 * Automatic Discount and Combo dependent state.
		 */
		refreshCheckout(
			checkout
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
	}

	/**
	 * Public Delivery API.
	 */
	window.eilmoCfDelivery = {

		/**
		 * Refresh delivery.
		 *
		 * @param {HTMLElement|null} checkout Checkout.
		 *
		 * @return {void}
		 */
		refresh:
			function (checkout) {
				if (
					window.eilmoCfDom.isElement(checkout)
				) {
					refreshCheckout(
						checkout
					);

					return;
				}

				document
					.querySelectorAll(
						SELECTORS.checkout
					)
					.forEach(
						function (item) {
							if (
								window.eilmoCfDom.isElement(item)
							) {
								refreshCheckout(
									item
								);
							}
						}
					);
			},

		/**
		 * Validate delivery.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {boolean}
		 */
		validateCheckout:
			validateCheckout,

		/**
		 * Get selected delivery method.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object|null}
		 */
		getSelected:
			getSelected,

		/**
		 * Get current delivery basis and related totals.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object|null}
		 */
		getTotals:
			getTotals,

		/**
		 * Get current threshold calculation amount.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {number}
		 */
		getProductTotal:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return 0;
				}

				return getProductTotal(
					checkout
				);
			},
	};

	document.addEventListener(
		'change',
		handleChange
	);

	/*
	 * Product / Combo / Discount / Coupon changes all
	 * affect Delivery calculation.
	 */
	[
		'eilmo:quantityChange',
		'eilmo:multipleProductsChange',
		'eilmo:comboChange',
		'eilmo:orderBumpChange',
		'eilmo:couponChange',
		'eilmo:discountChange',
	].forEach(
		function (eventName) {
			document.addEventListener(
				eventName,
				handleCalculationChange
			);
		}
	);

	if (
		'loading' ===
			document.readyState
	) {
		document.addEventListener(
			'DOMContentLoaded',
			initialize
		);
	} else {
		initialize();
	}
})();
/* END src/js/frontend/delivery.js */

/* BEGIN src/js/frontend/advance.js */
/**
 * Eilmo Checkout Flow - Payment Options.
 *
 * Handles Cash on Delivery, Advance Payment and Full Payment Discount.
 * Frontend calculations are previews only. Final totals
 * must always be recalculated and validated server-side.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const selectors = {
		checkout: '[data-eilmo-checkout]',
		section: '[data-eilmo-advance-payment]',
		option: '[data-eilmo-payment-option]',
		paymentType: '[data-eilmo-payment-type]',
		paymentMethods: '[data-eilmo-payment-methods]',
		advanceLabel: '[data-eilmo-advance-option-label]',
		advanceAmount: '[data-eilmo-advance-option-amount]',
		advanceSubtitle: '[data-eilmo-advance-option-subtitle]',
		advanceBadge: '[data-eilmo-advance-payment-badge]',
		fullLabel: '[data-eilmo-full-payment-label]',
		fullDescription: '[data-eilmo-full-payment-description]',
		fullDiscountBadge: '[data-eilmo-full-payment-discount-badge]',
		paymentContext: '[data-eilmo-payment-context]',
		paymentContextTitle: '[data-eilmo-payment-context-title]',
		paymentContextDescription: '[data-eilmo-payment-context-description]',
		paymentMethodsPayNote: '[data-eilmo-payment-methods-pay-note]',
		error: '[data-eilmo-advance-error]',
		quantityInput: '[data-eilmo-quantity-input]',
		productItem: '[data-eilmo-product-item], [data-eilmo-item]',
		comboOffer: '[data-eilmo-combo-offer]',
		comboInput: '[data-eilmo-combo-input]',
		orderBumpOffer: '[data-eilmo-order-bump]',
		orderBumpInput: '[data-eilmo-order-bump-input]',
		deliveryInput: '[data-eilmo-delivery-input]:checked',
	};

	const initialized = new WeakSet();
	const scheduled = new WeakSet();
	const results = new WeakMap();

	const paymentOptionIcons = Object.freeze({
		cash_on_delivery:
			'<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2.5" stroke="currentColor" stroke-width="1.7"/><path d="M6.5 9.5H8M16 14.5H17.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="12" cy="12" r="2.4" stroke="currentColor" stroke-width="1.7"/></svg>',
		advance:
			'<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><path d="M4 7.5A3.5 3.5 0 0 1 7.5 4H18a2 2 0 0 1 2 2v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><rect x="4" y="7" width="17" height="13" rx="3" stroke="currentColor" stroke-width="1.7"/><path d="M15 11h7v5h-7a2.5 2.5 0 0 1 0-5Z" stroke="currentColor" stroke-width="1.7"/><circle cx="16" cy="13.5" r=".8" fill="currentColor"/></svg>',
		full:
			'<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.7"/><path d="M3 10h18M7 15h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="m15.5 15 1.4 1.4 2.7-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
	});

	const paymentMethodFallbackIcon =
		'<svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><rect x="4" y="5" width="16" height="14" rx="3" stroke="currentColor" stroke-width="1.7"/><path d="M4 10h16M8 15h3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';

	function addPaymentOptionIcons(section) {
		section
			.querySelectorAll(selectors.option)
			.forEach(function (option) {
				if (
					!(window.eilmoCfDom.isElement(option)) ||
					option.querySelector('.eilmo-cf-advance-payment-option__type-icon')
				) {
					return;
				}

				const paymentType = normalizePaymentType(
					option.dataset.paymentType || ''
				);
				const iconMarkup = paymentOptionIcons[paymentType];

				if (!iconMarkup) {
					return;
				}

				const icon = document.createElement('span');
				icon.className = 'eilmo-cf-advance-payment-option__type-icon';
				icon.setAttribute('aria-hidden', 'true');
				icon.innerHTML = iconMarkup;

				const content = option.querySelector(
					'.eilmo-cf-advance-payment-option__content'
				);

				option.insertBefore(icon, content || null);
			});
	}

	function addPaymentMethodFallbackIcons(wrapper) {
		wrapper
			.querySelectorAll('[data-eilmo-payment-method]')
			.forEach(function (method) {
				if (!(window.eilmoCfDom.isElement(method))) {
					return;
				}

				const heading = method.querySelector(
					'.eilmo-cf-payment-method__heading'
				);

				if (
					!(window.eilmoCfDom.isElement(heading)) ||
					heading.querySelector('.eilmo-cf-payment-method__icon')
				) {
					return;
				}

				const icon = document.createElement('span');
				icon.className =
					'eilmo-cf-payment-method__icon eilmo-cf-payment-method__icon--fallback';
				icon.setAttribute('aria-hidden', 'true');
				icon.innerHTML = paymentMethodFallbackIcon;
				heading.appendChild(icon);
			});
	}

	function prepareUnifiedPaymentExperience(checkout, section) {
		section.classList.add(
			'eilmo-cf-advance-payment--unified'
		);

		addPaymentOptionIcons(section);

		const methodsWrapper = checkout.querySelector(
			'.eilmo-cf-checkout__payment-methods'
		);
		const paymentFooter = checkout.querySelector(
			'.eilmo-cf-checkout__payment-footer'
		);
		const error = section.querySelector(
			'[data-eilmo-advance-error]'
		);
		const oldContext = section.querySelector(
			selectors.paymentContext
		);
		let completion = section.querySelector(
			'.eilmo-cf-advance-payment__completion'
		);

		if (window.eilmoCfDom.isElement(oldContext)) {
			oldContext.remove();
		}

		if (!(window.eilmoCfDom.isElement(completion))) {
			completion = document.createElement('div');
			completion.className =
				'eilmo-cf-advance-payment__completion';
			completion.hidden = true;
			section.insertBefore(completion, error || null);
		}

		if (window.eilmoCfDom.isElement(methodsWrapper)) {
			methodsWrapper.classList.add(
				'eilmo-cf-advance-payment__methods'
			);
			completion.appendChild(methodsWrapper);
			addPaymentMethodFallbackIcons(methodsWrapper);
		}

		if (window.eilmoCfDom.isElement(paymentFooter)) {
			paymentFooter.classList.add(
				'eilmo-cf-advance-payment__footer'
			);
			section.insertBefore(paymentFooter, error || null);
		}
	}

	function toNumber(value) {
		const number = Number.parseFloat(String(value));

		if (!Number.isFinite(number)) {
			return 0;
		}

		return Math.max(0, number);
	}

	function getPriceDecimals() {
		const config = window.eilmoCf || {};

		const currency =
			config.currency &&
			'object' === typeof config.currency
				? config.currency
				: {};

		let decimals = Number(currency.decimals);

		if (!Number.isFinite(decimals)) {
			decimals = Number(config.priceDecimals);
		}

		if (!Number.isFinite(decimals)) {
			return 2;
		}

		return Math.max(
			0,
			Math.floor(decimals)
		);
	}

	function roundMoney(value) {
		const multiplier =
			Math.pow(
				10,
				getPriceDecimals()
			);

		return (
			Math.round(
				(
					toNumber(value) +
					Number.EPSILON
				) *
					multiplier
			) /
				multiplier
		);
	}

	function isEnabled(value) {
		return (
			true === value ||
			1 === value ||
			'1' === value ||
			'yes' === value ||
			'true' === value
		);
	}

	function parseJSON(
		value,
		fallback
	) {
		if (!value) {
			return fallback;
		}

		try {
			const parsed =
				JSON.parse(value);

			return null === parsed
				? fallback
				: parsed;
		} catch (error) {
			return fallback;
		}
	}

	function decodeHTML(value) {
		const element =
			document.createElement(
				'textarea'
			);

		element.innerHTML =
			String(value || '');

		return element.value;
	}

	function stripHTML(value) {
		const element =
			document.createElement(
				'div'
			);

		element.innerHTML =
			String(value || '');

		return decodeHTML(
			element.textContent ||
			element.innerText ||
			''
		);
	}

	function getCurrencyConfig() {
		const config = window.eilmoCf || {};

		const currency =
			config.currency &&
			'object' === typeof config.currency
				? config.currency
				: {};

		return {
			symbol:
				decodeHTML(
					'string' === typeof currency.symbol
						? currency.symbol
						: config.currencySymbol || '৳'
				),

			position:
				'string' === typeof currency.position
					? currency.position
					: config.currencyPosition || 'left',

			decimals:
				getPriceDecimals(),

			decimalSeparator:
				'string' === typeof currency.decimalSeparator
					? decodeHTML(
						currency.decimalSeparator
					)
					: config.decimalSeparator || '.',

			thousandSeparator:
				'string' === typeof currency.thousandSeparator
					? decodeHTML(
						currency.thousandSeparator
					)
					: config.thousandSeparator || ',',
		};
	}

	function formatPrice(amount) {
		const safeAmount =
			roundMoney(amount);

		if (
			window.eilmoCfSummary &&
			'function' ===
				typeof window.eilmoCfSummary.formatPrice
		) {
			return stripHTML(
				window.eilmoCfSummary.formatPrice(
					safeAmount
				)
			);
		}

		const config =
			getCurrencyConfig();

		const parts =
			safeAmount
				.toFixed(config.decimals)
				.split('.');

		parts[0] =
			parts[0].replace(
				/\B(?=(\d{3})+(?!\d))/g,
				config.thousandSeparator
			);

		const formattedNumber =
			config.decimals > 0
				? (
					parts[0] +
					config.decimalSeparator +
					parts[1]
				)
				: parts[0];

		switch (config.position) {
			case 'right':
				return (
					formattedNumber +
					config.symbol
				);

			case 'right_space':
				return (
					formattedNumber +
					' ' +
					config.symbol
				);

			case 'left_space':
				return (
					config.symbol +
					' ' +
					formattedNumber
				);

			case 'left':
			default:
				return (
					config.symbol +
					formattedNumber
				);
		}
	}

	function replaceTokens(
		text,
		values
	) {
		let output =
			String(text || '');

		Object.keys(
			values
		).forEach(
			function (key) {
				output =
					output
						.split(
							'{' +
								key +
								'}'
						)
						.join(
							String(
								values[key]
							)
						);
			}
		);

		return output;
	}

	function formatPercentage(value) {
		const number =
			Math.min(
				100,
				toNumber(value)
			);

		return (
			Number.parseFloat(
				number.toFixed(2)
			).toString() +
				'%'
		);
	}

	function getCheckoutFromEvent(event) {
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
					selectors.checkout
				);

			return window.eilmoCfDom.isElement(checkout)
					? checkout
					: null;
		}

		return null;
	}

	function calculateProductTotal(
		checkout
	) {
		let total = 0;
		let matchedItems = 0;

		checkout
			.querySelectorAll(
				selectors.productItem
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

					if (
						item.closest(
							selectors.comboOffer
						) ||
						item.closest(
							selectors.orderBumpOffer
						)
					) {
						return;
					}

					const input =
						item.querySelector(
							selectors.quantityInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						)
					) {
						return;
					}

					const quantity =
						toNumber(input.value);

					if (quantity <= 0) {
						return;
					}

					let price =
						toNumber(
							item.dataset.price
						);

					if (price <= 0) {
						price =
							toNumber(
								item.dataset
									.productPrice
							);
					}

					if (price <= 0) {
						price =
							toNumber(
								input.dataset.price
							);
					}

					if (price <= 0) {
						return;
					}

					total +=
						price *
							quantity;

					matchedItems += 1;
				}
			);

		/*
		 * Native WooCommerce checkout already owns the cart. It does not
		 * render Eilmo's product-selector rows, so DOM-only calculation
		 * would incorrectly report an empty product total and replace the
		 * server-rendered Advance preview with “Select a product…”.
		 *
		 * The native bridge mirrors WooCommerce's authoritative cart total
		 * onto the checkout form. Use that value only when no Eilmo product
		 * rows exist, keeping Quick Checkout behavior unchanged.
		 */
		if (0 === matchedItems && window.eilmoCfDom.isElement(checkout)) {
			const nativeTotal = toNumber(
				checkout.dataset.productTotal || 0
			);

			if (nativeTotal > 0) {
				return roundMoney(nativeTotal);
			}

			const section = checkout.querySelector(
				selectors.section
			);

			if (window.eilmoCfDom.isElement(section)) {
				const sectionTotal = toNumber(
					section.dataset.productTotal || 0
				);

				if (sectionTotal > 0) {
					return roundMoney(sectionTotal);
				}
			}
		}

		return roundMoney(total);
	}

	function getPromotionalTotals(
		checkout,
		config
	) {
		let regularTotal = 0;
		let discount = 0;
		let payableTotal = 0;
		let selectedCount = 0;

		let automaticEligibleTotal = 0;
		let couponEligibleTotal = 0;

		let fullPaymentEligibleRegularTotal = 0;
		let fullPaymentEligibleTotal = 0;

		let automaticFullPaymentOverlapTotal = 0;
		let automaticCouponOverlapTotal = 0;
		let fullPaymentCouponOverlapTotal = 0;
		let automaticFullPaymentCouponOverlapTotal = 0;

		checkout
			.querySelectorAll(
				config.offerSelector
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
							config.inputSelector
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						) ||
						!input.checked ||
						input.disabled
					) {
						return;
					}

					if (
						config.checkAvailability &&
						'no' ===
							offer.dataset.available
					) {
						return;
					}

					const offerRegularTotal =
						roundMoney(
							offer.dataset
								.regularTotal ||
								0
						);

					const offerDiscount =
						roundMoney(
							Math.min(
								toNumber(
									offer.dataset[
										config.discountKey
									] ||
										0
								),
								offerRegularTotal
							)
						);

					const fallbackTotal =
						roundMoney(
							Math.max(
								0,
								offerRegularTotal -
									offerDiscount
							)
						);

					const rawPayable =
						undefined !==
							offer.dataset[
								config.payableKey
							] &&
						'' !==
							String(
								offer.dataset[
									config.payableKey
								]
							).trim()
							? offer.dataset[
								config.payableKey
							]
							: fallbackTotal;

					const offerPayableTotal =
						roundMoney(
							Math.min(
								offerRegularTotal,
								toNumber(
									rawPayable
								)
							)
						);

					const automaticEligible =
						'yes' ===
							offer.dataset
								.applyAutomaticDiscount;

					const couponEligible =
						config.couponDefaultYes
							? (
								'no' !==
									offer.dataset
										.applyCoupon
							)
							: (
								'yes' ===
									offer.dataset
										.applyCoupon
							);

					const fullPaymentEligible =
						'no' !==
							offer.dataset
								.applyFullPaymentDiscount;

					regularTotal =
						roundMoney(
							regularTotal +
								offerRegularTotal
						);

					discount =
						roundMoney(
							discount +
								offerDiscount
						);

					payableTotal =
						roundMoney(
							payableTotal +
								offerPayableTotal
						);

					selectedCount += 1;

					if (automaticEligible) {
						automaticEligibleTotal =
							roundMoney(
								automaticEligibleTotal +
									offerPayableTotal
							);
					}

					if (couponEligible) {
						couponEligibleTotal =
							roundMoney(
								couponEligibleTotal +
									offerPayableTotal
							);
					}

					if (fullPaymentEligible) {
						fullPaymentEligibleRegularTotal =
							roundMoney(
								fullPaymentEligibleRegularTotal +
									offerRegularTotal
							);

						fullPaymentEligibleTotal =
							roundMoney(
								fullPaymentEligibleTotal +
									offerPayableTotal
							);
					}

					if (
						automaticEligible &&
						fullPaymentEligible
					) {
						automaticFullPaymentOverlapTotal =
							roundMoney(
								automaticFullPaymentOverlapTotal +
									offerPayableTotal
							);
					}

					if (
						automaticEligible &&
						couponEligible
					) {
						automaticCouponOverlapTotal =
							roundMoney(
								automaticCouponOverlapTotal +
									offerPayableTotal
							);
					}

					if (
						fullPaymentEligible &&
						couponEligible
					) {
						fullPaymentCouponOverlapTotal =
							roundMoney(
								fullPaymentCouponOverlapTotal +
									offerPayableTotal
							);
					}

					if (
						automaticEligible &&
						fullPaymentEligible &&
						couponEligible
					) {
						automaticFullPaymentCouponOverlapTotal =
							roundMoney(
								automaticFullPaymentCouponOverlapTotal +
									offerPayableTotal
							);
					}
				}
			);

		return {
			selectedCount:
				selectedCount,

			regularTotal:
				roundMoney(
					regularTotal
				),

			discount:
				roundMoney(
					Math.min(
						discount,
						regularTotal
					)
				),

			payableTotal:
				roundMoney(
					payableTotal
				),

			automaticEligibleTotal:
				roundMoney(
					automaticEligibleTotal
				),

			couponEligibleTotal:
				roundMoney(
					couponEligibleTotal
				),

			fullPaymentEligibleRegularTotal:
				roundMoney(
					fullPaymentEligibleRegularTotal
				),

			fullPaymentEligibleTotal:
				roundMoney(
					fullPaymentEligibleTotal
				),

			automaticFullPaymentOverlapTotal:
				roundMoney(
					automaticFullPaymentOverlapTotal
				),

			automaticCouponOverlapTotal:
				roundMoney(
					automaticCouponOverlapTotal
				),

			fullPaymentCouponOverlapTotal:
				roundMoney(
					fullPaymentCouponOverlapTotal
				),

			automaticFullPaymentCouponOverlapTotal:
				roundMoney(
					automaticFullPaymentCouponOverlapTotal
				),
		};
	}

	function getComboTotals(
		checkout
	) {
		const totals =
			getPromotionalTotals(
				checkout,
				{
					offerSelector:
						selectors.comboOffer,

					inputSelector:
						selectors.comboInput,

					discountKey:
						'comboDiscount',

					payableKey:
						'comboTotal',

					couponDefaultYes:
						true,

					checkAvailability:
						true,
				}
			);

		return {
			...totals,

			comboTotal:
				totals.payableTotal,
		};
	}

	function getOrderBumpTotals(
		checkout
	) {
		const totals =
			getPromotionalTotals(
				checkout,
				{
					offerSelector:
						selectors.orderBumpOffer,

					inputSelector:
						selectors.orderBumpInput,

					discountKey:
						'discount',

					payableKey:
						'bumpTotal',

					couponDefaultYes:
						false,

					checkAvailability:
						false,
				}
			);

		return {
			...totals,

			bumpTotal:
				totals.payableTotal,
		};
	}

	function getDeliveryCharge(
		checkout
	) {
		if (
			'string' ===
				typeof checkout.dataset
					.deliveryCharge
		) {
			return roundMoney(
				checkout.dataset
					.deliveryCharge
			);
		}

		const input =
			checkout.querySelector(
				selectors.deliveryInput
			);

		if (!input) {
			return 0;
		}

		return roundMoney(
			input.dataset.charge ||
				0
		);
	}

	function getAutomaticDiscount(
		checkout
	) {
		if (
			window.eilmoCfDiscount &&
			'function' ===
				typeof window
					.eilmoCfDiscount
					.getDiscount
		) {
			return roundMoney(
				window
					.eilmoCfDiscount
					.getDiscount(
						checkout
					)
			);
		}

		if (
			'string' ===
				typeof checkout.dataset
					.automaticDiscount
		) {
			return roundMoney(
				checkout.dataset
					.automaticDiscount
			);
		}

		if (
			window.eilmoCfSummary &&
			'function' ===
				typeof window
					.eilmoCfSummary
					.getAmount
		) {
			return roundMoney(
				window
					.eilmoCfSummary
					.getAmount(
						checkout,
						'automatic-discount'
					)
			);
		}

		return 0;
	}

	function getCouponDiscount(
		checkout
	) {
		if (
			window.eilmoCfSummary &&
			'function' ===
				typeof window
					.eilmoCfSummary
					.getAmount
		) {
			return roundMoney(
				window
					.eilmoCfSummary
					.getAmount(
						checkout,
						'coupon-discount'
					)
			);
		}

		return roundMoney(
			checkout.dataset
				.couponDiscount ||
				0
		);
	}

	function allocateDiscountShare(
		discount,
		sourceTotal,
		subsetTotal
	) {
		const safeDiscount =
			roundMoney(
				discount
			);

		const safeSourceTotal =
			roundMoney(
				sourceTotal
			);

		const safeSubsetTotal =
			roundMoney(
				Math.min(
					subsetTotal,
					safeSourceTotal
				)
			);

		if (
			safeDiscount <= 0 ||
			safeSourceTotal <= 0 ||
			safeSubsetTotal <= 0
		) {
			return 0;
		}

		return roundMoney(
			Math.min(
				safeSubsetTotal,
				safeDiscount *
					(
						safeSubsetTotal /
							safeSourceTotal
					)
			)
		);
	}

	function getTotals(
		checkout
	) {
		const baseProductTotal =
			calculateProductTotal(
				checkout
			);

		const combo =
			getComboTotals(
				checkout
			);

		const orderBump =
			getOrderBumpTotals(
				checkout
			);

		const productTotal =
			roundMoney(
				baseProductTotal +
					combo.regularTotal +
					orderBump.regularTotal
			);

		const comboDiscount =
			roundMoney(
				Math.min(
					combo.discount,
					combo.regularTotal
				)
			);

		const orderBumpDiscount =
			roundMoney(
				Math.min(
					orderBump.discount,
					orderBump.regularTotal
				)
			);

		const payableProductTotalBeforeAutomatic =
			roundMoney(
				baseProductTotal +
					combo.comboTotal +
					orderBump.bumpTotal
			);

		const automaticDiscountBase =
			roundMoney(
				baseProductTotal +
					combo.automaticEligibleTotal +
					orderBump.automaticEligibleTotal
			);

		const automaticDiscount =
			roundMoney(
				Math.min(
					getAutomaticDiscount(
						checkout
					),
					automaticDiscountBase,
					payableProductTotalBeforeAutomatic
				)
			);

		const afterAutomaticDiscount =
			roundMoney(
				Math.max(
					0,
					payableProductTotalBeforeAutomatic -
						automaticDiscount
				)
			);

		const couponDiscount =
			roundMoney(
				Math.min(
					getCouponDiscount(
						checkout
					),
					afterAutomaticDiscount
				)
			);

		const discountedProductTotal =
			roundMoney(
				Math.max(
					0,
					afterAutomaticDiscount -
						couponDiscount
				)
			);

		const deliveryCharge =
			getDeliveryCharge(
				checkout
			);

		const fullPaymentProductTotal =
			roundMoney(
				baseProductTotal +
					combo
						.fullPaymentEligibleRegularTotal +
					orderBump
						.fullPaymentEligibleRegularTotal
			);

		const fullPaymentPayableBeforeAutomatic =
			roundMoney(
				baseProductTotal +
					combo.fullPaymentEligibleTotal +
					orderBump.fullPaymentEligibleTotal
			);

		const automaticFullPaymentOverlapBase =
			roundMoney(
				baseProductTotal +
					combo
						.automaticFullPaymentOverlapTotal +
					orderBump
						.automaticFullPaymentOverlapTotal
			);

		const fullPaymentAutomaticDiscount =
			allocateDiscountShare(
				automaticDiscount,
				automaticDiscountBase,
				automaticFullPaymentOverlapBase
			);

		const fullPaymentAfterAutomatic =
			roundMoney(
				Math.max(
					0,
					fullPaymentPayableBeforeAutomatic -
						fullPaymentAutomaticDiscount
				)
			);

		const couponPayableBeforeAutomatic =
			roundMoney(
				baseProductTotal +
					combo.couponEligibleTotal +
					orderBump.couponEligibleTotal
			);

		const automaticCouponOverlapBase =
			roundMoney(
				baseProductTotal +
					combo
						.automaticCouponOverlapTotal +
					orderBump
						.automaticCouponOverlapTotal
			);

		const couponAutomaticDiscount =
			allocateDiscountShare(
				automaticDiscount,
				automaticDiscountBase,
				automaticCouponOverlapBase
			);

		const couponPayableAfterAutomatic =
			roundMoney(
				Math.max(
					0,
					couponPayableBeforeAutomatic -
						couponAutomaticDiscount
				)
			);

		const fullPaymentCouponPayableBeforeAutomatic =
			roundMoney(
				baseProductTotal +
					combo
						.fullPaymentCouponOverlapTotal +
					orderBump
						.fullPaymentCouponOverlapTotal
			);

		const automaticFullPaymentCouponOverlapBase =
			roundMoney(
				baseProductTotal +
					combo
						.automaticFullPaymentCouponOverlapTotal +
					orderBump
						.automaticFullPaymentCouponOverlapTotal
			);

		const fullPaymentCouponAutomaticDiscount =
			allocateDiscountShare(
				automaticDiscount,
				automaticDiscountBase,
				automaticFullPaymentCouponOverlapBase
			);

		const fullPaymentCouponPayableAfterAutomatic =
			roundMoney(
				Math.max(
					0,
					fullPaymentCouponPayableBeforeAutomatic -
						fullPaymentCouponAutomaticDiscount
				)
			);

		const fullPaymentCouponDiscount =
			allocateDiscountShare(
				couponDiscount,
				couponPayableAfterAutomatic,
				fullPaymentCouponPayableAfterAutomatic
			);

		const fullPaymentDiscountedProductTotal =
			roundMoney(
				Math.max(
					0,
					fullPaymentAfterAutomatic -
						fullPaymentCouponDiscount
				)
			);

		const fullPaymentGrandTotal =
			roundMoney(
				fullPaymentDiscountedProductTotal +
					deliveryCharge
			);

		return {
			baseProductTotal:
				roundMoney(
					baseProductTotal
				),

			comboSelectedCount:
				combo.selectedCount,

			comboRegularTotal:
				roundMoney(
					combo.regularTotal
				),

			comboDiscount:
				comboDiscount,

			comboTotal:
				roundMoney(
					combo.comboTotal
				),

			orderBumpSelectedCount:
				orderBump.selectedCount,

			orderBumpRegularTotal:
				roundMoney(
					orderBump.regularTotal
				),

			orderBumpDiscount:
				orderBumpDiscount,

			orderBumpTotal:
				roundMoney(
					orderBump.bumpTotal
				),

			productTotal:
				productTotal,

			payableProductTotalBeforeAutomatic:
				payableProductTotalBeforeAutomatic,

			automaticDiscountBase:
				automaticDiscountBase,

			automaticDiscount:
				automaticDiscount,

			couponDiscount:
				couponDiscount,

			discountedProductTotal:
				discountedProductTotal,

			deliveryCharge:
				roundMoney(
					deliveryCharge
				),

			grandTotal:
				roundMoney(
					discountedProductTotal +
						deliveryCharge
				),

			fullPaymentProductTotal:
				fullPaymentProductTotal,

			fullPaymentPayableBeforeAutomatic:
				fullPaymentPayableBeforeAutomatic,

			fullPaymentAutomaticDiscount:
				fullPaymentAutomaticDiscount,

			fullPaymentCouponDiscount:
				fullPaymentCouponDiscount,

			fullPaymentDiscountedProductTotal:
				fullPaymentDiscountedProductTotal,

			fullPaymentGrandTotal:
				fullPaymentGrandTotal,
		};
	}

	function getBasisAmount(
		basis,
		totals
	) {
		switch (basis) {
			case 'product_total':
				return totals.productTotal;

			case 'discounted_product_total':
				return totals
					.discountedProductTotal;

			case 'grand_total':
			default:
				return totals.grandTotal;
		}
	}

	function getFullPaymentBasisAmount(
		basis,
		totals
	) {
		switch (basis) {
			case 'product_total':
				return totals
					.fullPaymentProductTotal;

			case 'grand_total':
				return totals
					.fullPaymentGrandTotal;

			case 'discounted_product_total':
			default:
				return totals
					.fullPaymentDiscountedProductTotal;
		}
	}

	function ruleMatches(
		rule,
		amount
	) {
		if (
			false === rule.enabled ||
			'no' === rule.enabled
		) {
			return false;
		}

		const minimum =
			toNumber(
				rule.minimum_amount
			);

		const maximum =
			toNumber(
				rule.maximum_amount
			);

		if (amount < minimum) {
			return false;
		}

		if (
			maximum > 0 &&
			amount >= maximum
		) {
			return false;
		}

		return true;
	}

	function findMatchingAdvanceRule(
		rules,
		amount
	) {
		if (
			!Array.isArray(rules) ||
			0 === rules.length
		) {
			return null;
		}

		let matchedRule = null;

		const enabledRules = [];

		for (
			let index = 0;
			index < rules.length;
			index += 1
		) {
			const rule =
				rules[index];

			if (
				!rule ||
				'object' !== typeof rule ||
				false === rule.enabled ||
				'no' === rule.enabled
			) {
				continue;
			}

			enabledRules.push(
				{
					rule:
						rule,

					index:
						index,

					minimum:
						toNumber(
							rule.minimum_amount
						),

					maximum:
						toNumber(
							rule.maximum_amount
						),
				}
			);

			if (
				!ruleMatches(
					rule,
					amount
				)
			) {
				continue;
			}

			matchedRule =
				rule;

			if (
				isEnabled(
					rule.stop_processing
				)
			) {
				break;
			}
		}

		if (matchedRule) {
			return matchedRule;
		}

		if (
			0 ===
				enabledRules.length
		) {
			return null;
		}

		enabledRules.sort(
			function (
				first,
				second
			) {
				if (
					first.minimum !==
						second.minimum
				) {
					return (
						second.minimum -
							first.minimum
					);
				}

				return (
					first.index -
						second.index
				);
			}
		);

		const finalTier =
			enabledRules[0];

		if (!finalTier) {
			return null;
		}

		if (
			finalTier.maximum <= 0 &&
			amount >=
				finalTier.minimum
		) {
			return finalTier.rule;
		}

		if (
			finalTier.maximum > 0 &&
			amount >=
				finalTier.maximum
		) {
			return finalTier.rule;
		}

		return null;
	}

	function calculateRule(
		rule,
		basisAmount,
		grandTotal
	) {
		const type =
			String(
				rule.type ||
					'fixed'
			);

		let advanceAmount = 0;
		let payNow = 0;

		switch (type) {
			case 'percentage':

				advanceAmount =
					basisAmount *
						(
							Math.min(
								100,
								toNumber(
									rule.value
								)
							) /
								100
						);

				payNow =
					advanceAmount;

				break;

			case 'full_payment':

				advanceAmount =
					grandTotal;

				payNow =
					grandTotal;

				break;

			case 'no_advance':

				advanceAmount =
					0;

				payNow =
					0;

				break;

			case 'fixed':
			default:

				advanceAmount =
					toNumber(
						rule.value
					);

				payNow =
					advanceAmount;

				break;
		}

		if (
			'fixed' === type ||
			'percentage' === type
		) {
			const minimumPay =
				toNumber(
					rule.minimum_pay_amount
				);

			const maximumPay =
				toNumber(
					rule.maximum_pay_amount
				);

			if (
				minimumPay > 0 &&
				payNow < minimumPay
			) {
				payNow =
					minimumPay;
			}

			if (
				maximumPay > 0 &&
				payNow > maximumPay
			) {
				payNow =
					maximumPay;
			}

			payNow =
				Math.min(
					payNow,
					grandTotal
				);

			advanceAmount =
				payNow;
		}

		payNow =
			roundMoney(
				Math.min(
					payNow,
					grandTotal
				)
			);

		advanceAmount =
			roundMoney(
				Math.min(
					advanceAmount,
					grandTotal
				)
			);

		return {
			ruleId:
				String(
					rule.id ||
						''
				),

			ruleType:
				type,

			advanceAmount:
				advanceAmount,

			payNow:
				payNow,

			remainingDue:
				roundMoney(
					Math.max(
						0,
						grandTotal -
							payNow
					)
				),
		};
	}

	function calculateAdvance(
		section,
		totals
	) {
		if (
			totals.productTotal <= 0
		) {
			return {
				ruleId:
					'',

				ruleType:
					'empty_order',

				advanceAmount:
					0,

				payNow:
					0,

				remainingDue:
					0,
			};
		}

		const calculationBasis =
			section.dataset
				.calculationBasis ||
					'grand_total';

		const basisAmount =
			getBasisAmount(
				calculationBasis,
				totals
			);

		const rules =
			parseJSON(
				section.getAttribute(
					'data-rules'
				),
				[]
			);

		const matchedRule =
			findMatchingAdvanceRule(
				rules,
				basisAmount
			);

		if (matchedRule) {
			return calculateRule(
				matchedRule,
				basisAmount,
				totals.grandTotal
			);
		}

		return {
			ruleId:
				'',

			ruleType:
				'fallback_full_payment',

			advanceAmount:
				totals.grandTotal,

			payNow:
				totals.grandTotal,

			remainingDue:
				0,
		};
	}

	function getConfiguredDiscountLabel(
		type,
		value
	) {
		if (
			'fixed' === type
		) {
			return formatPrice(
				value
			);
		}

		return formatPercentage(
			value
		);
	}

	function calculateFullPaymentDiscount(
		section,
		totals
	) {
		const settings =
			parseJSON(
				section.getAttribute(
					'data-full-payment-discount'
				),
				{}
			);

		const result = {
			enabled:
				false,

			offerAvailable:
				false,

			discountOfferAvailable:
				false,

			freeDelivery:
				false,

			freeDeliveryBadge:
				'',

			eligible:
				false,

			type:
				'percentage',

			value:
				0,

			basis:
				'discounted_product_total',

			basisAmount:
				0,

			minimumAmount:
				0,

			maximumDiscount:
				0,

			rawSaving:
				0,

			saving:
				0,

			discountedTotal:
				totals.grandTotal,

			discountLabel:
				'',

			eligibleProductTotal:
				roundMoney(
					totals
						.fullPaymentProductTotal ||
						0
				),

			eligibleDiscountedProductTotal:
				roundMoney(
					totals
						.fullPaymentDiscountedProductTotal ||
						0
				),

			eligibleGrandTotal:
				roundMoney(
					totals
						.fullPaymentGrandTotal ||
						0
				),
		};

		if (
			!settings ||
			'object' !==
				typeof settings
		) {
			return result;
		}

		const enabled =
			isEnabled(
				settings.enabled
			);

		const type =
			'fixed' ===
				settings.type
				? 'fixed'
				: 'percentage';

		let value =
			toNumber(
				settings.value
			);

		if (
			'percentage' === type
		) {
			value =
				Math.min(
					100,
					value
				);
		}

		const minimumAmount =
			toNumber(
				settings.minimum_amount
			);

		const maximumDiscount =
			toNumber(
				settings.maximum_discount
			);

		const freeDelivery =
			isEnabled(
				settings.free_delivery
			);

		const freeDeliveryBadge =
			String(
				settings.free_delivery_badge ||
					'Free Delivery'
			);

		const allowedBasis = [
			'product_total',
			'discounted_product_total',
			'grand_total',
		];

		const basis =
			-1 !==
				allowedBasis.indexOf(
					settings.basis
				)
				? settings.basis
				: 'discounted_product_total';

		const fullPaymentGrandTotal =
			roundMoney(
				Math.max(
					0,
					toNumber(totals.fullPaymentGrandTotal) -
						(freeDelivery ? toNumber(totals.deliveryCharge) : 0)
				)
			);

		const fullPaymentTotals = Object.assign({}, totals, {
			fullPaymentGrandTotal: fullPaymentGrandTotal,
		});

		const basisAmount =
			roundMoney(
				getFullPaymentBasisAmount(
					basis,
					fullPaymentTotals
				)
			);

		const discountOfferAvailable =
			enabled &&
			value > 0;

		const offerAvailable =
			discountOfferAvailable ||
			freeDelivery;

		result.enabled =
			enabled;

		result.offerAvailable =
			offerAvailable;

		result.discountOfferAvailable =
			discountOfferAvailable;

		result.freeDelivery =
			freeDelivery;

		result.freeDeliveryBadge =
			freeDeliveryBadge;

		result.type =
			type;

		result.value =
			value;

		result.basis =
			basis;

		result.basisAmount =
			basisAmount;

		result.minimumAmount =
			minimumAmount;

		result.maximumDiscount =
			maximumDiscount;

		result.discountedTotal =
			fullPaymentGrandTotal;

		if (discountOfferAvailable) {
			result.discountLabel =
				getConfiguredDiscountLabel(
					type,
					value
				);
		}

		if (!discountOfferAvailable) {
			return result;
		}

		if (
			totals.productTotal <= 0
		) {
			return result;
		}

		if (
			basisAmount <= 0 ||
			(
				minimumAmount > 0 &&
				basisAmount <
					minimumAmount
			)
		) {
			return result;
		}

		let rawSaving =
			'fixed' === type
				? value
				: (
					basisAmount *
						(
							value /
								100
						)
				);

		rawSaving =
			roundMoney(
				rawSaving
			);

		let saving =
			rawSaving;

		if (
			maximumDiscount > 0 &&
			saving > maximumDiscount
		) {
			saving =
				maximumDiscount;
		}

		saving =
			Math.min(
				saving,
				basisAmount,
				fullPaymentGrandTotal
			);

		saving =
			roundMoney(
				saving
			);

		if (saving <= 0) {
			return result;
		}

		const capped =
			rawSaving > 0 &&
			saving < rawSaving;

		if (
			capped ||
			'fixed' === type
		) {
			result.discountLabel =
				formatPrice(
					saving
				);
		} else {
			result.discountLabel =
				formatPercentage(
					value
				);
		}

		result.eligible =
			true;

		result.rawSaving =
			rawSaving;

		result.saving =
			saving;

		result.discountedTotal =
			roundMoney(
				Math.max(
					0,
					fullPaymentGrandTotal -
						saving
				)
			);

		return result;
	}

	function getTexts(section) {
		const texts =
			parseJSON(
				section.getAttribute(
					'data-texts'
				),
				{}
			);

		return (
			texts &&
			'object' === typeof texts
		)
			? texts
			: {};
	}

	function normalizePaymentType(value) {
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

	function getPaymentType(section) {
		const checked =
			section.querySelector(
				selectors.paymentType +
					':checked'
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

		const currentType =
			normalizePaymentType(
				section.dataset
					.paymentType ||
				section.dataset
					.defaultPaymentType ||
					''
			);

		if (currentType) {
			return currentType;
		}

		const firstAvailable =
			section.querySelector(
				selectors.paymentType +
					':not(:disabled)'
			);

		if (
			window.eilmoCfDom.isElement(firstAvailable, 'INPUT')
		) {
			const fallbackType =
				normalizePaymentType(
					firstAvailable.value
				);

			if (fallbackType) {
				return fallbackType;
			}
		}

		return 'advance';
	}

	function requiresPaymentMethod(
		paymentType
	) {
		return (
			'advance' ===
				paymentType ||
			'full' ===
				paymentType
		);
	}

	function updateOptionState(
		section,
		paymentType
	) {
		const normalizedType =
			normalizePaymentType(
				paymentType
			) ||
			'advance';

		section
			.querySelectorAll(
				selectors.option
			)
			.forEach(
				function (option) {
					if (
						!(
							window.eilmoCfDom.isElement(option)
						)
					) {
						return;
					}

					const optionType =
						normalizePaymentType(
							option.dataset
								.paymentType ||
								''
						);

					const selected =
						optionType ===
							normalizedType;

					const input =
						option.querySelector(
							selectors.paymentType
						);

					if (
						window.eilmoCfDom.isElement(input, 'INPUT')
					) {
						input.checked =
							selected;
					}

					option.classList.toggle(
						'eilmo-cf-advance-payment-option--selected',
						selected
					);

					option.dataset.selected =
						selected
							? 'yes'
							: 'no';

					option.setAttribute(
						'aria-selected',
						selected
							? 'true'
							: 'false'
					);
				}
			);

		section.dataset.paymentType =
			normalizedType;
	}

	function updatePaymentMethodsVisibility(
		checkout,
		paymentType
	) {
		const paymentMethods =
			checkout.querySelector(
				selectors.paymentMethods
			);

		const required =
			requiresPaymentMethod(
				paymentType
			);

		checkout.dataset
			.paymentMethodRequired =
				required
					? 'yes'
					: 'no';

		checkout.dataset
			.cashOnDeliverySelected =
				'cash_on_delivery' ===
					paymentType
						? 'yes'
						: 'no';

		if (
			!(
				window.eilmoCfDom.isElement(paymentMethods)
			)
		) {
			return;
		}

		const methodsWrapper = paymentMethods.closest(
			'.eilmo-cf-checkout__payment-methods'
		);

		paymentMethods.dataset
			.requiredByPaymentOption =
				required
					? 'yes'
					: 'no';

		if (!required) {
			paymentMethods.hidden =
				true;

			paymentMethods.setAttribute(
				'aria-hidden',
				'true'
			);

			paymentMethods.dataset
				.hiddenByPaymentOption =
					'yes';

			if (window.eilmoCfDom.isElement(methodsWrapper)) {
				methodsWrapper.hidden = true;
				methodsWrapper.setAttribute(
					'aria-hidden',
					'true'
				);
			}

			return;
		}

		if (window.eilmoCfDom.isElement(methodsWrapper)) {
			methodsWrapper.hidden = false;
			methodsWrapper.removeAttribute('aria-hidden');
		}

		if (
			'yes' ===
				paymentMethods.dataset
					.hiddenByPaymentOption
		) {
			paymentMethods.hidden =
				false;

			paymentMethods.removeAttribute(
				'aria-hidden'
			);

			delete paymentMethods.dataset
				.hiddenByPaymentOption;
		}

		if (
			window.eilmoCfPayment &&
			'function' ===
				typeof window
					.eilmoCfPayment
					.refreshNativeGateway
		) {
			window.eilmoCfPayment
				.refreshNativeGateway(
					checkout
				);
		}
	}

	function getEmptyAdvanceLabel(template) {
		return String(
			template || ''
		)
			.replace(
				/\s*\{pay_now\}\s*/g,
				' '
			)
			.replace(
				/\s+/g,
				' '
			)
			.trim();
	}

	function updateAdvanceLabel(
		label,
		text,
		result,
		totals
	) {
		if (!label) {
			return;
		}

		if (
			totals.productTotal <= 0
		) {
			label.textContent =
				getEmptyAdvanceLabel(
					text
				);

			return;
		}

		const placeholder =
			'__EILMO_PAY_NOW__';

		const rendered =
			replaceTokens(
				String(
					text || ''
				),
				{
					pay_now:
						placeholder,

					remaining_due:
						formatPrice(
							result
								.remainingDue
						),

					grand_total:
						formatPrice(
							totals
								.grandTotal
						),
				}
			);

		if (
			-1 ===
				rendered.indexOf(
					placeholder
				)
		) {
			label.textContent =
				rendered;

			return;
		}

		const parts =
			rendered.split(
				placeholder
			);

		label.textContent =
			'';

		label.appendChild(
			document.createTextNode(
				parts.shift() ||
					''
			)
		);

		const amount =
			document.createElement(
				'strong'
			);

		amount.className =
			'eilmo-cf-advance-payment-option__inline-amount';

		amount.setAttribute(
			'data-eilmo-advance-option-amount',
			''
		);

		amount.dataset.value =
			String(
				result.advanceAmount
			);

		amount.textContent =
			formatPrice(
				result.advanceAmount
			);

		label.appendChild(
			amount
		);

		label.appendChild(
			document.createTextNode(
				parts.join(
					placeholder
				)
			)
		);
	}

	function getAdvanceSubtitle(section) {
		let subtitle =
			section.querySelector(
				selectors.advanceSubtitle
			);

		if (
			window.eilmoCfDom.isElement(subtitle)
		) {
			return subtitle;
		}

		const option =
			section.querySelector(
				selectors.option +
					'[data-payment-type="advance"]'
			);

		if (
			!(
				window.eilmoCfDom.isElement(option)
			)
		) {
			return null;
		}

		const content =
			option.querySelector(
				'.eilmo-cf-advance-payment-option__content'
			);

		if (
			!(
				window.eilmoCfDom.isElement(content)
			)
		) {
			return null;
		}

		subtitle =
			document.createElement(
				'span'
			);

		subtitle.className =
			'eilmo-cf-advance-payment-option__description';

		subtitle.setAttribute(
			'data-eilmo-advance-option-subtitle',
			''
		);

		content.appendChild(
			subtitle
		);

		return subtitle;
	}

	function updateAdvanceBadge(section, totals, texts) {
		const badge = section.querySelector(
			selectors.advanceBadge
		);

		if (!window.eilmoCfDom.isElement(badge)) {
			return;
		}

		if (totals.productTotal <= 0) {
			badge.textContent = '';
			badge.hidden = true;
			return;
		}

		badge.textContent =
			texts.advance_badge ||
			'Less hassle';
		badge.hidden = '' === badge.textContent.trim();
	}

	function updateAdvanceOption(
		section,
		result,
		totals,
		texts
	) {
		const label =
			section.querySelector(
				selectors.advanceLabel
			);

		if (label) {
			updateAdvanceLabel(
				label,
				texts.advance_label ||
					'Advance Payment',
				result,
				totals
			);
		}

		updateAdvanceBadge(
			section,
			totals,
			texts
		);

		const subtitle =
			getAdvanceSubtitle(
				section
			);

		if (!subtitle) {
			return;
		}

		if (
			totals.productTotal <= 0
		) {
			subtitle.textContent =
				texts.empty_description ||
					'Select a product to see the advance amount.';

			subtitle.hidden =
				'' ===
					subtitle.textContent
						.trim();

			return;
		}

		subtitle.textContent =
			replaceTokens(
				texts.advance_card_subtitle ||
					'Pay {pay_now} in advance.',
				{
					pay_now:
						formatPrice(
							result.payNow
						),

					remaining_due:
						formatPrice(
							result.remainingDue
						),

					grand_total:
						formatPrice(
							totals.grandTotal
						),
				}
			);

		subtitle.hidden =
			'' ===
				subtitle.textContent
					.trim();
	}

	function updateFullPaymentOption(
		section,
		discount,
		totals,
		texts
	) {
		const option =
			section.querySelector(
				selectors.option +
					'[data-payment-type="full"]'
			);

		if (
			!(
				window.eilmoCfDom.isElement(option)
			)
		) {
			return;
		}

		const label =
			option.querySelector(
				selectors.fullLabel
			);

		const description =
			option.querySelector(
				selectors.fullDescription
			);

		const badge =
			option.querySelector(
				selectors.fullDiscountBadge
			);

		const discountedTotal =
			(
				discount.eligible ||
				discount.freeDelivery
			)
				? discount.discountedTotal
				: totals.grandTotal;

		const tokens = {
			discount:
				discount.discountLabel,

			grand_total:
				formatPrice(
					totals.grandTotal
				),

			discounted_total:
				formatPrice(
					discountedTotal
				),
		};

		option.dataset.discountOffer =
			discount.offerAvailable
				? 'yes'
				: 'no';

		option.dataset.discountEligible =
			discount.eligible
				? 'yes'
				: 'no';

		option.dataset.saving =
			String(
				discount.saving
			);

		option.dataset.discountedTotal =
			String(
				discountedTotal
			);

		option.dataset.discountBasisAmount =
			String(
				discount.basisAmount
			);

		option.classList.toggle(
			'eilmo-cf-advance-payment-option--discount',
			discount.offerAvailable
		);

		if (label) {
			label.textContent =
				replaceTokens(
					texts.full_payment_label ||
						'Full Payment',
					tokens
				);
		}

		if (description) {
			description.textContent =
				replaceTokens(
					texts.full_payment_description ||
						'',
					tokens
				);

			description.hidden =
				'' ===
					description.textContent
						.trim();
		}

		if (badge) {
			const badgeParts = [];

			if (
				discount.eligible &&
				discount.saving > 0 &&
				discount.discountLabel
			) {
				const discountBadge = replaceTokens(
					texts.discount_badge ||
						'Get {discount} OFF',
					tokens
				).trim();
				if (discountBadge) badgeParts.push(discountBadge);
			}

			if (discount.freeDelivery && toNumber(totals.deliveryCharge) > 0) {
				const freeBadge = String(
					texts.free_delivery_badge ||
						discount.freeDeliveryBadge ||
						'Free Delivery'
				).trim();
				if (freeBadge) badgeParts.push(freeBadge);
			}

			badge.textContent = badgeParts.filter(function (value, index, items) {
				return value && items.indexOf(value) === index;
			}).join(' + ');
			badge.hidden = badge.dataset.showBadge === 'no' || '' === badge.textContent.trim();
		}
	}

	function updatePaymentContext(
		section,
		paymentType,
		advanceResult,
		discount,
		totals,
		texts
	) {
		const completion = section.querySelector(
			'.eilmo-cf-advance-payment__completion'
		);
		const required = requiresPaymentMethod(paymentType);

		if (window.eilmoCfDom.isElement(completion)) {
			completion.hidden = !required;
		}

		const note = completion
			? completion.querySelector(selectors.paymentMethodsPayNote)
			: null;

		if (!window.eilmoCfDom.isElement(note)) {
			return;
		}

		if (!required || totals.productTotal <= 0) {
			note.textContent = '';
			note.hidden = true;
			return;
		}

		let payNow = advanceResult.payNow;

		if ('full' === paymentType) {
			payNow =
				discount.eligible ||
				discount.freeDelivery
					? discount.discountedTotal
					: totals.grandTotal;
		}

		note.textContent = replaceTokens(
			texts.payment_method_pay_now || 'Pay {amount} in advance.',
			{
				amount: formatPrice(payNow),
			}
		);
		note.hidden = '' === note.textContent.trim();
	}

	function clearError(section) {
		const error =
			section.querySelector(
				selectors.error
			);

		if (!error) {
			return;
		}

		error.textContent =
			'';

		error.hidden =
			true;
	}

	function storeResult(
		checkout,
		section,
		result
	) {
		checkout.dataset.advanceReady =
			'yes';

		checkout.dataset.paymentType =
			result.paymentType;

		checkout.dataset.cashOnDeliverySelected =
			result.isCashOnDelivery
				? 'yes'
				: 'no';

		checkout.dataset.paymentMethodRequired =
			result.requiresPaymentMethod
				? 'yes'
				: 'no';

		checkout.dataset.advanceRuleId =
			result.ruleId;

		checkout.dataset.advanceRuleType =
			result.ruleType;

		checkout.dataset.advanceAmount =
			String(
				result.advanceAmount
			);

		checkout.dataset.baseProductTotal =
			String(
				result.baseProductTotal
			);

		checkout.dataset.comboSelectedCount =
			String(
				result.comboSelectedCount
			);

		checkout.dataset.comboRegularTotal =
			String(
				result.comboRegularTotal
			);

		checkout.dataset.comboDiscount =
			String(
				result.comboDiscount
			);

		checkout.dataset.comboTotal =
			String(
				result.comboTotal
			);

		checkout.dataset.orderBumpSelectedCount =
			String(
				result.orderBumpSelectedCount
			);

		checkout.dataset.orderBumpRegularTotal =
			String(
				result.orderBumpRegularTotal
			);

		checkout.dataset.orderBumpDiscount =
			String(
				result.orderBumpDiscount
			);

		checkout.dataset.orderBumpTotal =
			String(
				result.orderBumpTotal
			);

		checkout.dataset.productTotal =
			String(
				result.productTotal
			);

		checkout.dataset.automaticDiscount =
			String(
				result.automaticDiscount
			);

		checkout.dataset.couponDiscount =
			String(
				result.couponDiscount
			);

		checkout.dataset.discountedProductTotal =
			String(
				result.discountedProductTotal
			);

		checkout.dataset.fullPaymentProductTotal =
			String(
				result.fullPaymentProductTotal
			);

		checkout.dataset.fullPaymentDiscountedProductTotal =
			String(
				result
					.fullPaymentDiscountedProductTotal
			);

		checkout.dataset.fullPaymentEligibleGrandTotal =
			String(
				result
					.fullPaymentEligibleGrandTotal
			);

		checkout.dataset.fullPaymentDiscount =
			String(
				result
					.appliedFullPaymentDiscount
			);

		checkout.dataset.fullPaymentDiscountPreview =
			String(
				result
					.fullPaymentDiscountPreview
			);

		checkout.dataset.fullPaymentDiscountOffer =
			result
				.fullPaymentDiscountOfferAvailable
				? 'yes'
				: 'no';

		checkout.dataset.baseGrandTotal =
			String(
				result.baseGrandTotal
			);

		checkout.dataset.payableGrandTotal =
			String(
				result.grandTotal
			);

		checkout.dataset.payNow =
			String(
				result.payNow
			);

		checkout.dataset.remainingDue =
			String(
				result.remainingDue
			);

		section.dataset.paymentType =
			result.paymentType;

		section.dataset.cashOnDeliverySelected =
			result.isCashOnDelivery
				? 'yes'
				: 'no';

		section.dataset.paymentMethodRequired =
			result.requiresPaymentMethod
				? 'yes'
				: 'no';

		section.dataset.advanceAmount =
			String(
				result.advanceAmount
			);

		section.dataset.comboDiscount =
			String(
				result.comboDiscount
			);

		section.dataset.productTotal =
			String(
				result.productTotal
			);

		/*
		 * Never overwrite:
		 *
		 * section.dataset.fullPaymentDiscount
		 *
		 * because it contains the JSON configuration.
		 */
		section.dataset.appliedFullPaymentDiscount =
			String(
				result
					.appliedFullPaymentDiscount
			);

		section.dataset.fullPaymentDiscountPreview =
			String(
				result
					.fullPaymentDiscountPreview
			);

		section.dataset.fullPaymentDiscountOffer =
			result
				.fullPaymentDiscountOfferAvailable
				? 'yes'
				: 'no';

		section.dataset.baseGrandTotal =
			String(
				result.baseGrandTotal
			);

		section.dataset.payableGrandTotal =
			String(
				result.grandTotal
			);

		section.dataset.payNow =
			String(
				result.payNow
			);

		section.dataset.remainingDue =
			String(
				result.remainingDue
			);

		section.dataset.hasSelectedProduct =
			result.productTotal > 0
				? 'yes'
				: 'no';

		results.set(
			checkout,
			result
		);
	}

	function dispatchChange(
		checkout,
		result
	) {
		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:advancePaymentChange',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						paymentType:
							result.paymentType,

						isCashOnDelivery:
							result.isCashOnDelivery,

						requiresPaymentMethod:
							result.requiresPaymentMethod,

						ruleId:
							result.ruleId,

						ruleType:
							result.ruleType,

						baseProductTotal:
							result.baseProductTotal,

						comboSelectedCount:
							result.comboSelectedCount,

						comboRegularTotal:
							result.comboRegularTotal,

						comboDiscount:
							result.comboDiscount,

						comboOfferDiscount:
							result.comboDiscount,

						comboTotal:
							result.comboTotal,

						orderBumpSelectedCount:
							result
								.orderBumpSelectedCount,

						orderBumpRegularTotal:
							result
								.orderBumpRegularTotal,

						orderBumpDiscount:
							result.orderBumpDiscount,

						orderBumpTotal:
							result.orderBumpTotal,

						productTotal:
							result.productTotal,

						automaticDiscount:
							result.automaticDiscount,

						couponDiscount:
							result.couponDiscount,

						discountedProductTotal:
							result
								.discountedProductTotal,

						fullPaymentProductTotal:
							result
								.fullPaymentProductTotal,

						fullPaymentDiscountedProductTotal:
							result
								.fullPaymentDiscountedProductTotal,

						fullPaymentEligibleGrandTotal:
							result
								.fullPaymentEligibleGrandTotal,

						deliveryCharge:
							result.deliveryCharge,

						baseGrandTotal:
							result.baseGrandTotal,

						advanceAmount:
							result.advanceAmount,

						fullPaymentDiscountOfferAvailable:
							result
								.fullPaymentDiscountOfferAvailable,

						fullPaymentDiscountEligible:
							result
								.fullPaymentDiscountEligible,

						fullPaymentDiscountPreview:
							result
								.fullPaymentDiscountPreview,

						appliedFullPaymentDiscount:
							result
								.appliedFullPaymentDiscount,

						grandTotal:
							result.grandTotal,

						payNow:
							result.payNow,

						remainingDue:
							result.remainingDue,
					},
				}
			)
		);
	}

	function refresh(checkout) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return null;
		}

		const section =
			checkout.querySelector(
				selectors.section
			);

		if (
			!(
				window.eilmoCfDom.isElement(section)
			)
		) {
			return null;
		}

		clearError(
			section
		);

		const totals =
			getTotals(
				checkout
			);

		const texts =
			getTexts(
				section
			);

		/*
		 * IMPORTANT:
		 *
		 * This Advance calculation is always calculated
		 * independently from the selected Payment Option.
		 *
		 * It is used both for:
		 * - Actual Advance Payment flow.
		 * - Advance card preview while COD / Full is selected.
		 */
		const advance =
			calculateAdvance(
				section,
				totals
			);

		const discount =
			calculateFullPaymentDiscount(
				section,
				totals
			);

		const paymentType =
			getPaymentType(
				section
			);

		const hasProduct =
			totals.productTotal > 0;

		/*
		 * These values represent the CURRENTLY SELECTED
		 * checkout Payment Option.
		 */
		let advanceAmount =
			advance.advanceAmount;

		let payNow =
			advance.payNow;

		let remainingDue =
			advance.remainingDue;

		let payableGrandTotal =
			totals.grandTotal;

		let appliedDiscount =
			0;

		if (!hasProduct) {
			advanceAmount =
				0;

			payNow =
				0;

			remainingDue =
				0;

			payableGrandTotal =
				0;
		} else if (
			'cash_on_delivery' ===
				paymentType
		) {
			/*
			 * COD actual checkout values.
			 *
			 * Advance preview remains separate below.
			 */
			advanceAmount =
				0;

			payNow =
				0;

			remainingDue =
				totals.grandTotal;

			payableGrandTotal =
				totals.grandTotal;

			appliedDiscount =
				0;
		} else if (
			'full' === paymentType
		) {
			advanceAmount =
				0;

			if (
				discount.eligible
			) {
				appliedDiscount =
					discount.saving;
			}

			if (
				discount.eligible ||
				discount.freeDelivery
			) {
				payableGrandTotal =
					discount
						.discountedTotal;
			}

			payNow =
				payableGrandTotal;

			remainingDue =
				0;
		}

		advanceAmount =
			roundMoney(
				advanceAmount
			);

		payNow =
			roundMoney(
				payNow
			);

		remainingDue =
			roundMoney(
				remainingDue
			);

		payableGrandTotal =
			roundMoney(
				payableGrandTotal
			);

		appliedDiscount =
			roundMoney(
				appliedDiscount
			);

		updateOptionState(
			section,
			paymentType
		);

		updatePaymentMethodsVisibility(
			checkout,
			paymentType
		);

		/*
		 * --------------------------------------------------
		 * Advance Card Preview
		 * --------------------------------------------------
		 *
		 * Never use the selected-flow advanceAmount,
		 * payNow or remainingDue here.
		 *
		 * Example:
		 *
		 * COD selected:
		 *
		 * Actual checkout:
		 * Pay Now       = 0
		 * Remaining Due = Grand Total
		 *
		 * Advance card:
		 * Pay 200 Advance
		 * Remaining 1,275 later
		 *
		 * This makes the Advance offer visible immediately
		 * when the popup opens, without requiring the
		 * customer to click Advance first.
		 */
		const advancePreviewResult = {
			ruleId:
				advance.ruleId,

			ruleType:
				advance.ruleType,

			advanceAmount:
				roundMoney(
					advance.advanceAmount
				),

			payNow:
				roundMoney(
					advance.payNow
				),

			remainingDue:
				roundMoney(
					advance.remainingDue
				),
		};

		updateAdvanceOption(
			section,
			advancePreviewResult,
			totals,
			texts
		);

		updateFullPaymentOption(
			section,
			discount,
			totals,
			texts
		);

		updatePaymentContext(
			section,
			paymentType,
			advancePreviewResult,
			discount,
			totals,
			texts
		);

		const result = {
			paymentType:
				paymentType,

			isCashOnDelivery:
				'cash_on_delivery' ===
					paymentType,

			requiresPaymentMethod:
				requiresPaymentMethod(
					paymentType
				),

			ruleId:
				'cash_on_delivery' ===
					paymentType
						? ''
						: advance.ruleId,

			ruleType:
				'cash_on_delivery' ===
					paymentType
						? 'cash_on_delivery'
						: advance.ruleType,

			baseProductTotal:
				totals.baseProductTotal,

			comboSelectedCount:
				totals.comboSelectedCount,

			comboRegularTotal:
				totals.comboRegularTotal,

			comboDiscount:
				totals.comboDiscount,

			comboOfferDiscount:
				totals.comboDiscount,

			comboTotal:
				totals.comboTotal,

			orderBumpSelectedCount:
				totals
					.orderBumpSelectedCount,

			orderBumpRegularTotal:
				totals
					.orderBumpRegularTotal,

			orderBumpDiscount:
				totals.orderBumpDiscount,

			orderBumpTotal:
				totals.orderBumpTotal,

			productTotal:
				totals.productTotal,

			automaticDiscount:
				totals.automaticDiscount,

			couponDiscount:
				totals.couponDiscount,

			discountedProductTotal:
				totals
					.discountedProductTotal,

			fullPaymentProductTotal:
				totals
					.fullPaymentProductTotal,

			fullPaymentDiscountedProductTotal:
				totals
					.fullPaymentDiscountedProductTotal,

			fullPaymentEligibleGrandTotal:
				totals
					.fullPaymentGrandTotal,

			deliveryCharge:
				totals.deliveryCharge,

			baseGrandTotal:
				hasProduct
					? totals.grandTotal
					: 0,

			/*
			 * IMPORTANT:
			 *
			 * This is the ACTUAL selected-flow advance
			 * amount, not the Advance card preview.
			 *
			 * COD = 0
			 * Advance = calculated advance amount
			 * Full = 0
			 */
			advanceAmount:
				advanceAmount,

			fullPaymentDiscountOfferAvailable:
				discount.offerAvailable,

			fullPaymentDiscountEligible:
				discount.eligible,

			fullPaymentDiscountPreview:
				discount.saving,

			appliedFullPaymentDiscount:
				appliedDiscount,

			grandTotal:
				hasProduct
					? payableGrandTotal
					: 0,

			payNow:
				payNow,

			remainingDue:
				remainingDue,
		};

		storeResult(
			checkout,
			section,
			result
		);

		dispatchChange(
			checkout,
			result
		);

		return result;
	}

	function scheduleRefresh(checkout) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			) ||
			scheduled.has(
				checkout
			)
		) {
			return;
		}

		scheduled.add(
			checkout
		);

		Promise.resolve().then(
			function () {
				scheduled.delete(
					checkout
				);

				refresh(
					checkout
				);
			}
		);
	}

	function validate(checkout) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return true;
		}

		const section =
			checkout.querySelector(
				selectors.section
			);

		if (!section) {
			return true;
		}

		const selected =
			section.querySelector(
				selectors.paymentType +
					':checked'
			);

		if (
			window.eilmoCfDom.isElement(selected, 'INPUT') &&
			normalizePaymentType(
				selected.value
			)
		) {
			clearError(
				section
			);

			return true;
		}

		const error =
			section.querySelector(
				selectors.error
			);

		if (error) {
			const config =
				window.eilmoCf ||
					{};

			error.textContent =
				config.paymentOptionRequired ||
					'Please select a payment option.';

			error.hidden =
				false;
		}

		return false;
	}

	function initialize(checkout) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			) ||
			initialized.has(
				checkout
			)
		) {
			return;
		}

		const section =
			checkout.querySelector(
				selectors.section
			);

		if (!section) {
			return;
		}

		prepareUnifiedPaymentExperience(
			checkout,
			section
		);

		initialized.add(
			checkout
		);

		refresh(
			checkout
		);
	}

	function initializeAll() {
		document
			.querySelectorAll(
				selectors.checkout
			)
			.forEach(
				function (checkout) {
					if (
						window.eilmoCfDom.isElement(checkout)
					) {
						initialize(
							checkout
						);
					}
				}
			);
	}

	/*
	 * Cash on Delivery / Advance / Full
	 * Payment Option changed.
	 */
	document.addEventListener(
		'change',
		function (event) {
			if (
				!(
					window.eilmoCfDom.isElement(event.target, 'INPUT')
				) ||
				!event.target.matches(
					selectors.paymentType
				)
			) {
				return;
			}

			const checkout =
				event.target.closest(
					selectors.checkout
				);

			if (
				!(
					window.eilmoCfDom.isElement(checkout)
				)
			) {
				return;
			}

			const section =
				event.target.closest(
					selectors.section
				);

			if (
				!(
					window.eilmoCfDom.isElement(section)
				)
			) {
				return;
			}

			const paymentType =
				normalizePaymentType(
					event.target.value
				);

			if (!paymentType) {
				return;
			}

			updateOptionState(
				section,
				paymentType
			);

			checkout.dataset.paymentType =
				paymentType;

			updatePaymentMethodsVisibility(
				checkout,
				paymentType
			);

			if (
				window.eilmoCfDelivery &&
				'function' === typeof window.eilmoCfDelivery.refresh
			) {
				window.eilmoCfDelivery.refresh(checkout);
			}

			refresh(
				checkout
			);
		}
	);

	[
		'eilmo:quantityChange',
		'eilmo:comboChange',
		'eilmo:orderBumpChange',
		'eilmo:deliveryChange',
		'eilmo:couponChange',
		'eilmo:discountChange',
	].forEach(
		function (eventName) {
			document.addEventListener(
				eventName,
				function (event) {
					const checkout =
						getCheckoutFromEvent(
							event
						);

					if (checkout) {
						scheduleRefresh(
							checkout
						);
					}
				}
			);
		}
	);

	window.eilmoCfAdvancePayment = {

		refresh:
			refresh,

		validate:
			validate,

		getResult:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return null;
				}

				return (
					results.get(
						checkout
					) ||
						null
				);
			},

		requiresPaymentMethod:
			requiresPaymentMethod,

		getPaymentType:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return 'advance';
				}

				const section =
					checkout.querySelector(
						selectors.section
					);

				return window.eilmoCfDom.isElement(section)
						? getPaymentType(
							section
						)
						: 'advance';
			},

		getComboTotals:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return {
						selectedCount:
							0,

						regularTotal:
							0,

						discount:
							0,

						comboTotal:
							0,
					};
				}

				return getComboTotals(
					checkout
				);
			},

		getOrderBumpTotals:
			function (checkout) {
				if (
					!(
						window.eilmoCfDom.isElement(checkout)
					)
				) {
					return {
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

				return getOrderBumpTotals(
					checkout
				);
			},
	};

	if (
		'loading' ===
			document.readyState
	) {
		document.addEventListener(
			'DOMContentLoaded',
			initializeAll
		);
	} else {
		initializeAll();
	}
})();
/* END src/js/frontend/advance.js */

/* BEGIN src/js/frontend/coupon.js */
/**
 * Eilmo Checkout Flow - Coupon.
 *
 * Handles custom coupon validation and live checkout synchronization.
 *
 * Normal Products, Combo Offers and Order Bumps remain independent
 * purchase contexts. Combo / Order Bump pricing and compatibility are
 * never trusted from the browser; only their selected IDs are submitted.
 *
 * Frontend values are previews only. Final coupon eligibility and totals
 * must always be recalculated and validated server-side.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		checkout: '[data-eilmo-checkout]',
		section: '[data-eilmo-coupon]',
		form: '[data-eilmo-coupon-form]',
		input: '[data-eilmo-coupon-input]',
		apply: '[data-eilmo-coupon-apply]',
		applied: '[data-eilmo-coupon-applied]',
		appliedCode: '[data-eilmo-coupon-applied-code]',
		appliedDiscount: '[data-eilmo-coupon-applied-discount]',
		remove: '[data-eilmo-coupon-remove]',
		message: '[data-eilmo-coupon-message]',
		item: '[data-eilmo-product-item], [data-eilmo-item]',
		quantityInput: '[data-eilmo-quantity-input]',

		comboSection: '[data-eilmo-combo-offers]',
		comboOffer: '[data-eilmo-combo-offer]',
		comboInput: '[data-eilmo-combo-input]',

		orderBumpOffer: '[data-eilmo-order-bump]',
		orderBumpInput: '[data-eilmo-order-bump-input]',

		customerEmail: [
			'[data-eilmo-customer-email]',
			'input[name="billing_email"]',
			'input[name*="[billing_email]"]',
			'input[type="email"]',
		].join(','),

		customerPhone: [
			'[data-eilmo-customer-phone]',
			'input[name="billing_phone"]',
			'input[name*="[billing_phone]"]',
			'input[type="tel"]',
		].join(','),

		billingCountry: [
			'[data-eilmo-billing-country]',
			'select[name="billing_country"]',
			'input[name="billing_country"]',
			'select[name*="[billing_country]"]',
			'input[name*="[billing_country]"]',
		].join(','),
	};

	const initialized = new WeakSet();

	const states = new WeakMap();

	const refreshTimers = new WeakMap();

	/**
	 * Convert value to non-negative number.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toNumber(value) {
		const number =
			Number.parseFloat(
				String(value)
			);

		if (!Number.isFinite(number)) {
			return 0;
		}

		return Math.max(
			0,
			number
		);
	}

	/**
	 * Convert value to non-negative integer.
	 *
	 * @param {*} value Value.
	 *
	 * @return {number}
	 */
	function toInteger(value) {
		const number =
			Number.parseInt(
				String(value),
				10
			);

		if (!Number.isFinite(number)) {
			return 0;
		}

		return Math.max(
			0,
			number
		);
	}

	/**
	 * Get checkout wrapper.
	 *
	 * @param {Element|null} element Element.
	 *
	 * @return {HTMLElement|null}
	 */
	function getCheckout(element) {
		if (!(window.eilmoCfDom.isElement(element))) {
			return null;
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
	 * Get Coupon section.
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
	 * Get localized string.
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
			config.i18n || {};

		if (
			typeof i18n[key] === 'string' &&
			i18n[key] !== ''
		) {
			return i18n[key];
		}

		return fallback;
	}

	/**
	 * Format price.
	 *
	 * Prefer Summary API so all modules use
	 * identical WooCommerce currency formatting.
	 *
	 * @param {number} amount Amount.
	 *
	 * @return {string}
	 */
	function formatPrice(amount) {
		if (
			window.eilmoCfSummary &&
			typeof window
				.eilmoCfSummary
				.formatPrice === 'function'
		) {
			return window
				.eilmoCfSummary
				.formatPrice(
					toNumber(amount)
				);
		}

		const config =
			window.eilmoCf || {};

		const currency =
			config.currency || {};

		const decimals =
			Number.isInteger(
				currency.decimals
			)
				? currency.decimals
				: 2;

		const symbol =
			typeof currency.symbol === 'string'
				? currency.symbol
				: '৳';

		const position =
			typeof currency.position === 'string'
				? currency.position
				: 'left';

		const formatted =
			toNumber(
				amount
			).toFixed(
				decimals
			);

		switch (position) {
			case 'left_space':
				return (
					symbol +
					'\u00A0' +
					formatted
				);

			case 'right':
				return (
					formatted +
					symbol
				);

			case 'right_space':
				return (
					formatted +
					'\u00A0' +
					symbol
				);

			case 'left':
			default:
				return (
					symbol +
					formatted
				);
		}
	}

	/**
	 * Get integer value from dataset.
	 *
	 * @param {HTMLElement|null} element Element.
	 * @param {Array<string>}    keys    Dataset keys.
	 *
	 * @return {number}
	 */
	function getDatasetInteger(
		element,
		keys
	) {
		if (!(window.eilmoCfDom.isElement(element))) {
			return 0;
		}

		for (
			let index = 0;
			index < keys.length;
			index += 1
		) {
			const key =
				keys[index];

			const value =
				element.dataset[key];

			const number =
				toInteger(
					value || 0
				);

			if (number > 0) {
				return number;
			}
		}

		return 0;
	}

	/**
	 * Read integer value from child field.
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

		let rawValue = '';

		if (
			window.eilmoCfDom.isElement(field, 'INPUT') ||
			window.eilmoCfDom.isElement(field, 'SELECT')
		) {
			rawValue =
				field.value;
		} else {
			rawValue =
				field.getAttribute(
					'data-value'
				) || '';
		}

		return toInteger(
			rawValue
		);
	}

	/**
	 * Get variation ID.
	 *
	 * @param {HTMLElement}      item  Product item.
	 * @param {HTMLInputElement} input Quantity input.
	 *
	 * @return {number}
	 */
	function getVariationId(
		item,
		input
	) {
		const itemId =
			getDatasetInteger(
				item,
				[
					'variationId',
				]
			);

		if (itemId > 0) {
			return itemId;
		}

		const inputId =
			getDatasetInteger(
				input,
				[
					'variationId',
				]
			);

		if (inputId > 0) {
			return inputId;
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
	 * @param {HTMLElement}      item  Product item.
	 * @param {HTMLInputElement} input Quantity input.
	 *
	 * @return {number}
	 */
	function getProductId(
		item,
		input
	) {
		const itemId =
			getDatasetInteger(
				item,
				[
					'productId',
					'parentProductId',
					'parentId',
				]
			);

		if (itemId > 0) {
			return itemId;
		}

		const inputId =
			getDatasetInteger(
				input,
				[
					'productId',
					'parentProductId',
					'parentId',
				]
			);

		if (inputId > 0) {
			return inputId;
		}

		const hiddenId =
			getChildInteger(
				item,
				[
					'[data-eilmo-product-id]',
					'input[name*="[product_id]"]',
					'input[name="product_id"]',
				].join(',')
			);

		if (hiddenId > 0) {
			return hiddenId;
		}

		return getVariationId(
			item,
			input
		);
	}

	/**
	 * Collect selected checkout items.
	 *
	 * Server recalculates prices from WooCommerce.
	 * Frontend never sends authoritative price values.
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
				function (element) {
					if (
						!(
							window.eilmoCfDom.isElement(element)
						)
					) {
						return;
					}

					/*
					 * Normal products only.
					 *
					 * Combo Offers and Order Bumps are separate
					 * purchase contexts and are submitted below
					 * only by their authoritative offer IDs.
					 */
					if (
						element.closest(
							SELECTORS.comboOffer
						) ||
						element.closest(
							SELECTORS.orderBumpOffer
						)
					) {
						return;
					}

					const quantityInput =
						element.querySelector(
							SELECTORS.quantityInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(quantityInput, 'INPUT')
						)
					) {
						return;
					}

					const quantity =
						toInteger(
							quantityInput.value
						);

					if (quantity <= 0) {
						return;
					}

					const variationId =
						getVariationId(
							element,
							quantityInput
						);

					const productId =
						getProductId(
							element,
							quantityInput
						);

					if (productId <= 0) {
						return;
					}

					/*
					 * Multiple Products can expose compatibility
					 * adapters for the same exact order item. Send
					 * one Coupon row per Simple product / variation;
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
	 * Collect selected Combo Offer request data.
	 *
	 * Only selected Combo IDs and the signed checkout
	 * context are sent. Product IDs, prices, quantities,
	 * compatibility flags and discount values remain
	 * server-authoritative.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getComboRequestData(checkout) {
		const selectedIds = [];

		checkout
			.querySelectorAll(
				SELECTORS.comboOffer
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
							SELECTORS.comboInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						) ||
						!input.checked ||
						input.disabled
					) {
						return;
					}

					const offerId =
						String(
							offer.dataset.comboId ||
							input.value ||
							''
						).trim();

					if (
						offerId &&
						!selectedIds.includes(
							offerId
						)
					) {
						selectedIds.push(
							offerId
						);
					}
				}
			);

		let context = {};

		const section =
			checkout.querySelector(
				SELECTORS.comboSection
			);

		if (
			window.eilmoCfDom.isElement(section)
		) {
			const rawContext =
				section.getAttribute(
					'data-eilmo-combo-context'
				) || '';

			if (rawContext) {
				try {
					const parsed =
						JSON.parse(
							rawContext
						);

					if (
						parsed &&
						'object' ===
							typeof parsed &&
						!Array.isArray(
							parsed
						)
					) {
						context = parsed;
					}
				} catch (error) {
					context = {};
				}
			}
		}

		return {
			selected_ids:
				selectedIds,

			context:
				context,
		};
	}

	/**
	 * Collect selected Order Bump request data.
	 *
	 * Only Order Bump IDs are submitted. The server
	 * reloads product, quantity, pricing and discount
	 * compatibility from saved settings.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getOrderBumpRequestData(checkout) {
		const selectedIds = [];
		let context = {};

		checkout
			.querySelectorAll(
				SELECTORS.orderBumpOffer
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
							SELECTORS.orderBumpInput
						);

					if (
						!(
							window.eilmoCfDom.isElement(input, 'INPUT')
						) ||
						!input.checked ||
						input.disabled
					) {
						return;
					}

					const offerId =
						String(
							offer.dataset.orderBumpId ||
							input.value ||
							''
						).trim();

					if (
						offerId &&
						!selectedIds.includes(
							offerId
						)
					) {
						selectedIds.push(
							offerId
						);
					}
				}
			);

		const rawContext = checkout.getAttribute('data-eilmo-special-discount-context') || '';
		if (rawContext) {
			try {
				const parsed = JSON.parse(rawContext);
				if (parsed && 'object' === typeof parsed && !Array.isArray(parsed)) {
					context = parsed;
				}
			} catch (error) {
				context = {};
			}
		}

		return {
			selected_ids:
				selectedIds,

			context:
				context,
		};
	}

	/**
	 * Get customer email when available.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getCustomerEmail(checkout) {
		const input =
			checkout.querySelector(
				SELECTORS.customerEmail
			);

		if (
			!(
				window.eilmoCfDom.isElement(input, 'INPUT')
			)
		) {
			return '';
		}

		return input.value.trim();
	}

	/**
	 * Get customer phone when available.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getCustomerPhone(checkout) {
		const input =
			checkout.querySelector(
				SELECTORS.customerPhone
			);

		if (
			!(
				window.eilmoCfDom.isElement(input, 'INPUT')
			)
		) {
			return '';
		}

		return input.value.trim();
	}

	/**
	 * Get billing country when available.
	 *
	 * Used by the server to normalize phone numbers.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getBillingCountry(checkout) {
		const field =
			checkout.querySelector(
				SELECTORS.billingCountry
			);

		if (
			window.eilmoCfDom.isElement(field, 'INPUT') ||
			window.eilmoCfDom.isElement(field, 'SELECT')
		) {
			return field.value
				.trim()
				.toUpperCase();
		}

		return '';
	}

	/**
	 * Get coupon state.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getState(checkout) {
		if (!states.has(checkout)) {
			states.set(
				checkout,
				{
					applied: false,
					pending: false,
					pendingReason: '',
					identificationMode: '',
					code: '',
					discount: 0,
					source: '',
					freeDelivery: false,
					requestId: 0,
					controller: null,
				}
			);
		}

		return states.get(
			checkout
		);
	}

	/**
	 * Set Coupon message.
	 *
	 * @param {HTMLElement} section Coupon section.
	 * @param {string}      message Message.
	 * @param {string}      type    success|error|info.
	 *
	 * @return {void}
	 */
	function setMessage(
		section,
		message,
		type
	) {
		const element =
			section.querySelector(
				SELECTORS.message
			);

		if (
			!(
				window.eilmoCfDom.isElement(element)
			)
		) {
			return;
		}

		element.classList.remove(
			'eilmo-cf-coupon__message--success',
			'eilmo-cf-coupon__message--error',
			'eilmo-cf-coupon__message--info'
		);

		if (!message) {
			element.textContent =
				'';

			element.hidden =
				true;

			delete element.dataset.type;

			return;
		}

		const safeType =
			[
				'success',
				'error',
				'info',
			].includes(type)
				? type
				: 'info';

		element.textContent =
			message;

		element.hidden =
			false;

		element.dataset.type =
			safeType;

		element.classList.add(
			'eilmo-cf-coupon__message--' +
				safeType
		);
	}

	/**
	 * Toggle loading state.
	 *
	 * @param {HTMLElement} section Coupon section.
	 * @param {boolean}     loading Loading.
	 *
	 * @return {void}
	 */
	function setLoading(
		section,
		loading
	) {
		const input =
			section.querySelector(
				SELECTORS.input
			);

		const button =
			section.querySelector(
				SELECTORS.apply
			);

		section.classList.toggle(
			'eilmo-cf-coupon--loading',
			loading
		);

		section.dataset.loading =
			loading
				? 'yes'
				: 'no';

		if (
			window.eilmoCfDom.isElement(input, 'INPUT')
		) {
			input.disabled =
				loading;
		}

		if (
			window.eilmoCfDom.isElement(button, 'BUTTON')
		) {
			if (
				!button.dataset.defaultText
			) {
				button.dataset.defaultText =
					button.textContent ||
					'Apply Coupon';
			}

			button.disabled =
				loading;

			button.textContent =
				loading
					? getText(
						'processing',
						'Processing...'
					)
					: button.dataset
						.defaultText;
		}
	}

	/**
	 * Format coupon benefit.
	 *
	 * @param {number}  discount     Discount.
	 * @param {boolean} freeDelivery Free delivery.
	 *
	 * @return {string}
	 */
	function formatBenefit(
		discount,
		freeDelivery
	) {
		if (
			freeDelivery &&
			toNumber(
				discount
			) <= 0
		) {
			return getText(
				'freeDelivery',
				'Free Delivery'
			);
		}

		if (
			toNumber(
				discount
			) <= 0
		) {
			return '';
		}

		return (
			'\u2212' +
			formatPrice(
				discount
			)
		);
	}

	/**
	 * Render applied Coupon state.
	 *
	 * Pending customer-identity checks keep the
	 * Coupon form visible and do not apply a discount.
	 *
	 * @param {HTMLElement} section Coupon section.
	 * @param {Object}      state   State.
	 *
	 * @return {void}
	 */
	function renderAppliedState(
		section,
		state
	) {
		const form =
			section.querySelector(
				SELECTORS.form
			);

		const applied =
			section.querySelector(
				SELECTORS.applied
			);

		const code =
			section.querySelector(
				SELECTORS.appliedCode
			);

		const discount =
			section.querySelector(
				SELECTORS.appliedDiscount
			);

		if (
			window.eilmoCfDom.isElement(form)
		) {
			form.hidden =
				state.applied === true;
		}

		if (
			window.eilmoCfDom.isElement(applied)
		) {
			applied.hidden =
				state.applied !== true;
		}

		if (
			window.eilmoCfDom.isElement(code)
		) {
			code.textContent =
				state.applied
					? state.code
					: '';
		}

		if (
			window.eilmoCfDom.isElement(discount)
		) {
			discount.textContent =
				state.applied
					? formatBenefit(
						state.discount,
						state.freeDelivery
					)
					: '';
		}

		section.dataset.applied =
			state.applied
				? 'yes'
				: 'no';

		section.dataset.pending =
			state.pending
				? 'yes'
				: 'no';

		section.dataset.pendingReason =
			state.pending
				? state.pendingReason
				: '';

		section.dataset.identificationMode =
			state.identificationMode ||
			'';

		section.dataset.couponCode =
			state.applied
				? state.code
				: '';

		section.dataset.pendingCouponCode =
			state.pending
				? state.code
				: '';

		section.dataset.couponDiscount =
			String(
				state.applied
					? toNumber(
						state.discount
					)
					: 0
			);

		section.dataset.freeDelivery =
			state.applied &&
			state.freeDelivery
				? 'yes'
				: 'no';
	}

	/**
	 * Synchronize Coupon with checkout state.
	 *
	 * Summary owns Grand Total calculation.
	 * Coupon only supplies coupon-discount.
	 *
	 * Pending Coupon state never affects totals.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {Object}      state    State.
	 *
	 * @return {void}
	 */
	function syncCheckout(
		checkout,
		state
	) {
		const discount =
			state.applied
				? toNumber(
					state.discount
				)
				: 0;

		checkout.dataset.couponApplied =
			state.applied
				? 'yes'
				: 'no';

		checkout.dataset.couponPending =
			state.pending
				? 'yes'
				: 'no';

		checkout.dataset.couponCode =
			state.applied
				? state.code
				: '';

		checkout.dataset.couponPendingCode =
			state.pending
				? state.code
				: '';

		checkout.dataset.couponSource =
			state.applied
				? state.source
				: '';

		checkout.dataset.couponDiscount =
			String(
				discount
			);

		checkout.dataset.couponFreeDelivery =
			state.applied &&
			state.freeDelivery
				? 'yes'
				: 'no';

		checkout.dataset.couponIdentificationMode =
			state.identificationMode ||
			'';

		if (
			window.eilmoCfSummary &&
			typeof window
				.eilmoCfSummary
				.setAmount === 'function'
		) {
			window
				.eilmoCfSummary
				.setAmount(
					checkout,
					'coupon-discount',
					discount
				);
		} else {
			checkout.dispatchEvent(
				new CustomEvent(
					'eilmo:summaryRefresh',
					{
						bubbles: true,

						detail: {
							checkout:
								checkout,
						},
					}
				)
			);
		}

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:couponChange',
				{
					bubbles: true,

					detail: {
						checkout:
							checkout,

						applied:
							state.applied ===
							true,

						pending:
							state.pending ===
							true,

						couponCode:
							state.applied
								? state.code
								: '',

						pendingCouponCode:
							state.pending
								? state.code
								: '',

						source:
							state.applied
								? state.source
								: '',

						couponDiscount:
							discount,

						freeDelivery:
							state.applied &&
							state.freeDelivery ===
								true,

						identificationMode:
							state.identificationMode ||
							'',
					},
				}
			)
		);
	}

	/**
	 * Clear pending revalidation timer.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function clearRefreshTimer(checkout) {
		const timer =
			refreshTimers.get(
				checkout
			);

		if (!timer) {
			return;
		}

		window.clearTimeout(
			timer
		);

		refreshTimers.delete(
			checkout
		);
	}

	/**
	 * Reset Coupon state.
	 *
	 * @param {Object}  state        State.
	 * @param {boolean} preserveCode Preserve current code as pending.
	 *
	 * @return {void}
	 */
	function resetState(
		state,
		preserveCode
	) {
		state.applied =
			false;

		state.pending =
			preserveCode &&
			Boolean(state.code);

		state.pendingReason =
			state.pending
				? state.pendingReason
				: '';

		state.discount =
			0;

		state.source =
			'';

		state.freeDelivery =
			false;

		if (!preserveCode) {
			state.code =
				'';

			state.identificationMode =
				'';
		}
	}

	/**
	 * Clear Coupon.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLElement} section  Coupon section.
	 * @param {boolean}     focus    Focus input.
	 *
	 * @return {void}
	 */
	function clearCoupon(
		checkout,
		section,
		focus
	) {
		const state =
			getState(
				checkout
			);

		clearRefreshTimer(
			checkout
		);

		if (
			state.controller instanceof
				AbortController
		) {
			state.controller.abort();
		}

		state.requestId += 1;

		state.controller =
			null;

		state.pendingReason =
			'';

		resetState(
			state,
			false
		);

		const input =
			section.querySelector(
				SELECTORS.input
			);

		if (
			window.eilmoCfDom.isElement(input, 'INPUT')
		) {
			input.value =
				'';
		}

		setLoading(
			section,
			false
		);

		setMessage(
			section,
			'',
			'info'
		);

		renderAppliedState(
			section,
			state
		);

		syncCheckout(
			checkout,
			state
		);

		if (
			focus &&
			window.eilmoCfDom.isElement(input, 'INPUT')
		) {
			input.focus();
		}
	}

	/**
	 * Extract AJAX response data.
	 *
	 * @param {*} payload Response payload.
	 *
	 * @return {Object}
	 */
	function getResponseData(payload) {
		if (
			payload &&
			payload.data &&
			typeof payload.data === 'object'
		) {
			return payload.data;
		}

		return {};
	}

	/**
	 * Extract AJAX response message.
	 *
	 * @param {*}      payload  Response.
	 * @param {string} fallback Fallback.
	 *
	 * @return {string}
	 */
	function getResponseMessage(
		payload,
		fallback
	) {
		const data =
			getResponseData(
				payload
			);

		if (
			typeof data.message ===
				'string' &&
			data.message !== ''
		) {
			return data.message;
		}

		if (
			payload &&
			typeof payload.message ===
				'string' &&
			payload.message !== ''
		) {
			return payload.message;
		}

		return fallback;
	}

	/**
	 * Determine whether response requests customer identity.
	 *
	 * @param {*} payload AJAX payload.
	 *
	 * @return {boolean}
	 */
	function isCustomerIdentityRequired(payload) {
		const data =
			getResponseData(
				payload
			);

		return (
			data.error_code ===
				'customer_identity_required' ||
			data.requires_customer_contact ===
				true
		);
	}

	/**
	 * Set Coupon to pending customer identity state.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLElement} section  Coupon section.
	 * @param {Object}      state    State.
	 * @param {string}      code     Coupon code.
	 * @param {*}           payload  AJAX payload.
	 *
	 * @return {void}
	 */
	function setIdentityPending(
		checkout,
		section,
		state,
		code,
		payload
	) {
		const data =
			getResponseData(
				payload
			);

		state.applied =
			false;

		state.pending =
			true;

		state.pendingReason =
			'customer_identity_required';

		state.identificationMode =
			typeof data.identification_mode ===
				'string'
				? data.identification_mode
				: '';

		state.code =
			code;

		state.discount =
			0;

		state.source =
			'eilmo';

		state.freeDelivery =
			false;

		renderAppliedState(
			section,
			state
		);

		syncCheckout(
			checkout,
			state
		);

		setMessage(
			section,
			getResponseMessage(
				payload,
				'Enter your email or phone number to check eligibility for this coupon.'
			),
			'info'
		);
	}

	/**
	 * Apply or revalidate Coupon.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {HTMLElement} section  Coupon section.
	 * @param {string}      code     Coupon code.
	 * @param {boolean}     silent   Silent revalidation.
	 *
	 * @return {Promise<void>}
	 */
	async function applyCoupon(
		checkout,
		section,
		code,
		silent
	) {
		const couponCode =
			String(
				code || ''
			).trim();

		if (!couponCode) {
			setMessage(
				section,
				'Please enter a coupon code.',
				'error'
			);

			return;
		}

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
			setMessage(
				section,
				getText(
					'error',
					'Something went wrong. Please try again.'
				),
				'error'
			);

			return;
		}

		const state =
			getState(
				checkout
			);

		const items =
			getItems(
				checkout
			);

		/*
		 * Existing Checkout Flow currently requires at
		 * least one normal selected product.
		 *
		 * Combo / Order Bump are additional purchase
		 * contexts and do not replace that requirement.
		 */
		if (items.length === 0) {
			if (
				state.applied ||
				state.pending
			) {
				state.code =
					couponCode;

				state.pendingReason =
					'empty_order';

				resetState(
					state,
					true
				);

				renderAppliedState(
					section,
					state
				);

				syncCheckout(
					checkout,
					state
				);
			}

			setMessage(
				section,
				getText(
					'selectProduct',
					'Please select at least one product.'
				),
				silent
					? 'info'
					: 'error'
			);

			return;
		}

		if (
			state.controller instanceof
				AbortController
		) {
			state.controller.abort();
		}

		const controller =
			new AbortController();

		const requestId =
			state.requestId + 1;

		state.requestId =
			requestId;

		state.controller =
			controller;

		if (!silent) {
			setMessage(
				section,
				'',
				'info'
			);
		}

		setLoading(
			section,
			true
		);

		const body =
			new URLSearchParams();

		body.set(
			'action',
			'eilmo_cf_apply_coupon'
		);

		body.set(
			'nonce',
			nonce
		);

		body.set(
			'coupon_code',
			couponCode
		);

		body.set(
			'items',
			JSON.stringify(
				items
			)
		);

		/*
		 * Combo and Order Bump purchase contexts are sent
		 * only as identifiers.
		 *
		 * CouponAjax must resolve and recalculate:
		 *
		 * - Products.
		 * - Quantities.
		 * - Own promotional pricing.
		 * - apply_coupon compatibility.
		 *
		 * Browser values are never authoritative.
		 */
		body.set(
			'combo_offers',
			JSON.stringify(
				getComboRequestData(
					checkout
				)
			)
		);

		body.set(
			'order_bumps',
			JSON.stringify(
				getOrderBumpRequestData(
					checkout
				)
			)
		);

		const customerEmail =
			getCustomerEmail(
				checkout
			);

		const customerPhone =
			getCustomerPhone(
				checkout
			);

		const billingCountry =
			getBillingCountry(
				checkout
			);

		if (customerEmail) {
			body.set(
				'customer_email',
				customerEmail
			);
		}

		if (customerPhone) {
			body.set(
				'customer_phone',
				customerPhone
			);
		}

		if (billingCountry) {
			body.set(
				'billing_country',
				billingCountry
			);
		}

		try {
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

						signal:
							controller.signal,

						credentials:
							'same-origin',
					}
				);

			const payload =
				await response.json();

			if (
				state.requestId !==
					requestId
			) {
				return;
			}

			if (
				!response.ok ||
				!payload ||
				payload.success !== true
			) {
				if (
					isCustomerIdentityRequired(
						payload
					)
				) {
					setIdentityPending(
						checkout,
						section,
						state,
						couponCode,
						payload
					);

					return;
				}

				throw new Error(
					getResponseMessage(
						payload,
						getText(
							'error',
							'Something went wrong. Please try again.'
						)
					)
				);
			}

			const data =
				getResponseData(
					payload
				);

			/*
			 * WooCommerce mode currently delegates
			 * authoritative application to native
			 * WooCommerce checkout integration.
			 *
			 * Final native WooCommerce Coupon allocation
			 * is still enforced by OrderCreator using
			 * exact source-aware line-item filtering.
			 */
			if (
				data.delegate_to_woocommerce ===
					true
			) {
				state.applied =
					false;

				state.pending =
					false;

				state.pendingReason =
					'';

				state.identificationMode =
					'';

				state.code =
					'';

				state.discount =
					0;

				state.source =
					'woocommerce';

				state.freeDelivery =
					false;

				renderAppliedState(
					section,
					state
				);

				syncCheckout(
					checkout,
					state
				);

				setMessage(
					section,
					typeof data.message ===
						'string' &&
					data.message !== ''
						? data.message
						: 'WooCommerce coupon recognized.',
					'info'
				);

				return;
			}

			if (
				data.valid !== true ||
				data.applied !== true
			) {
				throw new Error(
					typeof data.message ===
						'string' &&
					data.message !== ''
						? data.message
						: 'This coupon cannot be applied.'
				);
			}

			state.applied =
				true;

			state.pending =
				false;

			state.pendingReason =
				'';

			state.identificationMode =
				typeof data.identification_mode ===
					'string'
					? data.identification_mode
					: state.identificationMode;

			state.code =
				typeof data.coupon_code ===
					'string'
					? data.coupon_code
					: couponCode;

			state.discount =
				toNumber(
					data.coupon_discount ||
					0
				);

			state.source =
				typeof data.source ===
					'string'
					? data.source
					: 'eilmo';

			state.freeDelivery =
				data.free_delivery ===
					true;

			renderAppliedState(
				section,
				state
			);

			syncCheckout(
				checkout,
				state
			);

			if (!silent) {
				setMessage(
					section,
					typeof data.message ===
						'string' &&
					data.message !== ''
						? data.message
						: 'Coupon applied successfully.',
					'success'
				);
			} else {
				setMessage(
					section,
					'',
					'info'
				);
			}
		} catch (error) {
			if (
				error &&
				error.name ===
					'AbortError'
			) {
				return;
			}

			if (
				state.requestId !==
					requestId
			) {
				return;
			}

			const message =
				error instanceof Error &&
				error.message
					? error.message
					: getText(
						'error',
						'Something went wrong. Please try again.'
					);

			/*
			 * Applied/pending Coupon became invalid.
			 * Remove every checkout discount effect.
			 */
			if (
				state.applied ||
				state.pending
			) {
				state.pendingReason =
					'';

				resetState(
					state,
					false
				);

				renderAppliedState(
					section,
					state
				);

				syncCheckout(
					checkout,
					state
				);
			}

			setMessage(
				section,
				message,
				'error'
			);
		} finally {
			if (
				state.requestId ===
					requestId
			) {
				state.controller =
					null;

				setLoading(
					section,
					false
				);
			}
		}
	}

	/**
	 * Schedule Coupon revalidation.
	 *
	 * Used when:
	 * - Product quantity changes.
	 * - Combo Offer selection changes.
	 * - Order Bump selection changes.
	 * - Automatic Discount changes.
	 * - Customer identity changes.
	 *
	 * Applied and pending Coupons can both be
	 * revalidated automatically.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function scheduleRevalidation(
		checkout
	) {
		const state =
			getState(
				checkout
			);

		if (
			!(
				state.applied ||
				state.pending
			) ||
			!state.code
		) {
			return;
		}

		clearRefreshTimer(
			checkout
		);

		const timer =
			window.setTimeout(
				function () {
					refreshTimers.delete(
						checkout
					);

					const section =
						getSection(
							checkout
						);

					if (!section) {
						return;
					}

					applyCoupon(
						checkout,
						section,
						state.code,
						true
					);
				},
				350
			);

		refreshTimers.set(
			checkout,
			timer
		);
	}

	/**
	 * Determine whether element is a customer
	 * identity field relevant to Coupon eligibility.
	 *
	 * @param {Element} element Element.
	 *
	 * @return {boolean}
	 */
	function isCustomerIdentityField(element) {
		if (!(window.eilmoCfDom.isElement(element))) {
			return false;
		}

		return (
			element.matches(
				SELECTORS.customerEmail
			) ||
			element.matches(
				SELECTORS.customerPhone
			) ||
			element.matches(
				SELECTORS.billingCountry
			)
		);
	}

	/**
	 * Handle Coupon form.
	 *
	 * @param {SubmitEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleSubmit(event) {
		const form =
			event.target;

		if (
			!(
				window.eilmoCfDom.isElement(form, 'FORM')
			)
		) {
			return;
		}

		if (
			!form.matches(
				SELECTORS.form
			)
		) {
			return;
		}

		event.preventDefault();

		const checkout =
			getCheckout(
				form
			);

		if (!checkout) {
			return;
		}

		const section =
			getSection(
				checkout
			);

		if (!section) {
			return;
		}

		const input =
			section.querySelector(
				SELECTORS.input
			);

		if (
			!(
				window.eilmoCfDom.isElement(input, 'INPUT')
			)
		) {
			return;
		}

		applyCoupon(
			checkout,
			section,
			input.value,
			false
		);
	}

	/**
	 * Handle Coupon remove.
	 *
	 * @param {MouseEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleClick(event) {
		const target =
			event.target;

		if (
			!(
				window.eilmoCfDom.isElement(target)
			)
		) {
			return;
		}

		const remove =
			target.closest(
				SELECTORS.remove
			);

		if (
			!(
				window.eilmoCfDom.isElement(remove)
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				remove
			);

		if (!checkout) {
			return;
		}

		const section =
			getSection(
				checkout
			);

		if (!section) {
			return;
		}

		event.preventDefault();

		clearCoupon(
			checkout,
			section,
			true
		);
	}

	/**
	 * Handle module events that can affect
	 * Coupon eligibility.
	 *
	 * @param {CustomEvent} event Event.
	 *
	 * @return {void}
	 */
	function handleCheckoutChange(
		event
	) {
		let checkout =
			null;

		if (
			event.detail &&
			window.eilmoCfDom.isElement(event.detail.checkout)
		) {
			checkout =
				event.detail.checkout;
		} else if (
			window.eilmoCfDom.isElement(event.target)
		) {
			checkout =
				getCheckout(
					event.target
				);
		}

		if (!checkout) {
			return;
		}

		scheduleRevalidation(
			checkout
		);
	}

	/**
	 * Handle direct customer field input/change.
	 *
	 * This allows customer-limited Coupons to work
	 * before a dedicated customer-form module begins
	 * dispatching eilmo:customerChange.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleCustomerFieldChange(event) {
		const target =
			event.target;

		if (
			!(
				window.eilmoCfDom.isElement(target)
			) ||
			!isCustomerIdentityField(
				target
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				target
			);

		if (!checkout) {
			return;
		}

		scheduleRevalidation(
			checkout
		);
	}

	/**
	 * Initialize one checkout.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function initializeCheckout(
		checkout
	) {
		if (
			initialized.has(
				checkout
			)
		) {
			return;
		}

		const section =
			getSection(
				checkout
			);

		if (!section) {
			return;
		}

		initialized.add(
			checkout
		);

		const state =
			getState(
				checkout
			);

		renderAppliedState(
			section,
			state
		);

		syncCheckout(
			checkout,
			state
		);
	}

	/**
	 * Initialize all Coupon sections.
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
	}

	/**
	 * Public Coupon API.
	 */
	window.eilmoCfCoupon = {

		/**
		 * Apply Coupon.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 * @param {string}      code     Coupon code.
		 *
		 * @return {Promise<void>}
		 */
		apply: function (
			checkout,
			code
		) {
			if (
				!(
					window.eilmoCfDom.isElement(checkout)
				)
			) {
				return Promise.resolve();
			}

			const section =
				getSection(
					checkout
				);

			if (!section) {
				return Promise.resolve();
			}

			return applyCoupon(
				checkout,
				section,
				code,
				false
			);
		},

		/**
		 * Remove Coupon.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {void}
		 */
		remove: function (
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
				getSection(
					checkout
				);

			if (section) {
				clearCoupon(
					checkout,
					section,
					false
				);
			}
		},

		/**
		 * Revalidate Coupon.
		 *
		 * @param {HTMLElement|null} checkout Checkout.
		 *
		 * @return {void}
		 */
		refresh: function (
			checkout
		) {
			if (
				window.eilmoCfDom.isElement(checkout)
			) {
				scheduleRevalidation(
					checkout
				);

				return;
			}

			document
				.querySelectorAll(
					SELECTORS.checkout
				)
				.forEach(
					function (item) {
						if (
							window.eilmoCfDom.isElement(item)
						) {
							scheduleRevalidation(
								item
							);
						}
					}
				);
		},

		/**
		 * Get Coupon result.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {Object|null}
		 */
		getResult: function (
			checkout
		) {
			if (
				!(
					window.eilmoCfDom.isElement(checkout)
				)
			) {
				return null;
			}

			const state =
				getState(
					checkout
				);

			return {
				applied:
					state.applied ===
					true,

				pending:
					state.pending ===
					true,

				pendingReason:
					state.pendingReason,

				identificationMode:
					state.identificationMode,

				code:
					state.code,

				discount:
					toNumber(
						state.discount
					),

				source:
					state.source,

				freeDelivery:
					state.freeDelivery ===
					true,
			};
		},

		/**
		 * Get Coupon discount.
		 *
		 * @param {HTMLElement} checkout Checkout.
		 *
		 * @return {number}
		 */
		getDiscount: function (
			checkout
		) {
			if (
				!(
					window.eilmoCfDom.isElement(checkout)
				)
			) {
				return 0;
			}

			const state =
				getState(
					checkout
				);

			return state.applied
				? toNumber(
					state.discount
				)
				: 0;
		},
	};

	/*
	 * Coupon form.
	 */
	document.addEventListener(
		'submit',
		handleSubmit
	);

	/*
	 * Remove Coupon.
	 */
	document.addEventListener(
		'click',
		handleClick
	);

	/*
	 * Product changes may change:
	 * - Minimum spend.
	 * - Maximum spend.
	 * - Percentage amount.
	 * - Product restrictions.
	 */
	document.addEventListener(
		'eilmo:quantityChange',
		handleCheckoutChange
	);

	/*
	 * Multiple Products root selection sync can change Coupon
	 * minimum/maximum spend and product/variation restrictions.
	 * The revalidation scheduler coalesces this with quantity events.
	 */
	document.addEventListener(
		'eilmo:multipleProductsChange',
		handleCheckoutChange
	);

	/*
	 * Combo Offer selection can change the Coupon-
	 * eligible payable total when apply_coupon is ON.
	 */
	document.addEventListener(
		'eilmo:comboChange',
		handleCheckoutChange
	);

	/*
	 * Order Bump selection can change the Coupon-
	 * eligible payable total when apply_coupon is ON.
	 */
	document.addEventListener(
		'eilmo:orderBumpChange',
		handleCheckoutChange
	);

	/*
	 * Automatic Discount can change the
	 * Coupon calculation basis.
	 */
	document.addEventListener(
		'eilmo:discountChange',
		handleCheckoutChange
	);

	/*
	 * Dedicated customer-form module event.
	 */
	document.addEventListener(
		'eilmo:customerChange',
		handleCheckoutChange
	);

	/*
	 * Direct customer fields.
	 */
	document.addEventListener(
		'input',
		handleCustomerFieldChange
	);

	document.addEventListener(
		'change',
		handleCustomerFieldChange
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
/* END src/js/frontend/coupon.js */

/* BEGIN src/js/frontend/fraud-check.js */
/**
 * Eilmo Checkout Flow - one-time live phone risk check.
 *
 * The browser never calculates risk. It transports a server-signed result
 * and adjusts payment choices for clarity; final enforcement is server-side.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	/*
	 * A page can contain both a shortcode/widget checkout and the product-page
	 * Quick Checkout integration. Guard the controller globally so optimization
	 * plugins or a mixed bundle/fallback queue cannot create two independent
	 * verification state stores for the same checkout.
	 */
	const runtimeModules = window.eilmoCfRuntimeModules && 'object' === typeof window.eilmoCfRuntimeModules
		? window.eilmoCfRuntimeModules
		: {};
	window.eilmoCfRuntimeModules = runtimeModules;
	if (true === runtimeModules.liveFraud) {
		return;
	}
	runtimeModules.liveFraud = true;

	const states = new WeakMap();

	function getConfig() {
		return window.eilmoCf && window.eilmoCf.liveFraud
			? window.eilmoCf.liveFraud
			: {};
	}

	function isEnabled() {
		return 'yes' === getConfig().enabled;
	}

	function normalizePhone(value) {
		let digits = String(value || '').replace(/\D+/g, '');
		if (13 === digits.length && 0 === digits.indexOf('880')) {
			digits = `0${digits.slice(3)}`;
		}
		if (10 === digits.length && '1' === digits.charAt(0)) {
			digits = `0${digits}`;
		}
		return /^01[3-9][0-9]{8}$/.test(digits) ? digits : '';
	}

	function getState(checkout) {
		if (!states.has(checkout)) {
			states.set(checkout, {
				phone: '',
				token: '',
				decision: '',
				message: '',
				checking: false,
				timer: 0,
				requestId: 0,
				paymentAvailable: false,
				allowedPaymentTypes: [],
				paymentDisplayMode: 'always',
			});
		}
		return states.get(checkout);
	}

	function getStatus(checkout) {
		let status = checkout.querySelector('[data-eilmo-live-fraud-status]');
		if (!(window.eilmoCfDom.isElement(status))) {
			status = document.createElement('div');
			status.setAttribute('data-eilmo-live-fraud-status', '');
			status.setAttribute('role', 'status');
			status.className = 'eilmo-cf-live-fraud-status';
			const phone = checkout.querySelector('[data-eilmo-customer-phone]');
			/*
			 * The input itself owns data-eilmo-customer-field, so targeting
			 * that selector would attempt to append inside <input> and throw
			 * before the AJAX request starts. Always target the field wrapper.
			 */
			const target = window.eilmoCfDom.isElement(phone)
				? (phone.closest('[data-eilmo-customer-field-wrap]') || phone.parentElement)
				: null;
			if (target) {
				target.appendChild(status);
			}
		}
		return window.eilmoCfDom.isElement(status) ? status : null;
	}

	function render(checkout, message, kind) {
		const status = getStatus(checkout);
		if (!status) {
			return;
		}
		status.textContent = window.eilmoCfLanguage ? window.eilmoCfLanguage.copy(checkout, message || '') : message || '';
		status.hidden = !message;
		status.className = 'eilmo-cf-live-fraud-status';
		if (kind) {
			status.classList.add(`eilmo-cf-live-fraud-status--${kind}`);
		}
	}

	function rememberOption(option, input) {
		if (!Object.prototype.hasOwnProperty.call(option.dataset, 'eilmoFraudHidden')) {
			option.dataset.eilmoFraudHidden = option.hidden ? 'yes' : 'no';
		}
		if (!Object.prototype.hasOwnProperty.call(input.dataset, 'eilmoFraudDisabled')) {
			input.dataset.eilmoFraudDisabled = input.disabled ? 'yes' : 'no';
		}
	}

	function restorePaymentOptions(checkout) {
		checkout.querySelectorAll('[data-eilmo-payment-option]').forEach((option) => {
			const input = option.querySelector('[data-eilmo-payment-type]');
			if (!(window.eilmoCfDom.isElement(option)) || !(window.eilmoCfDom.isElement(input, 'INPUT'))) {
				return;
			}
			if (Object.prototype.hasOwnProperty.call(option.dataset, 'eilmoFraudHidden')) {
				option.hidden = 'yes' === option.dataset.eilmoFraudHidden;
				delete option.dataset.eilmoFraudHidden;
			}
			if (Object.prototype.hasOwnProperty.call(input.dataset, 'eilmoFraudDisabled')) {
				input.disabled = 'yes' === input.dataset.eilmoFraudDisabled;
				delete input.dataset.eilmoFraudDisabled;
			}
			option.classList.remove('eilmo-cf-advance-payment-option--fraud-disabled');
			option.removeAttribute('aria-disabled');
		});
	}

	function restorePaymentSection(checkout) {
		const section = checkout.querySelector('[data-eilmo-advance-payment]');
		if (!(window.eilmoCfDom.isElement(section))) {
			return;
		}
		if (Object.prototype.hasOwnProperty.call(section.dataset, 'eilmoFraudHidden')) {
			section.hidden = 'yes' === section.dataset.eilmoFraudHidden;
			delete section.dataset.eilmoFraudHidden;
		}
		section.classList.remove('eilmo-cf-advance-payment--fraud-pending');
	}

	function rememberPaymentSection(checkout) {
		const section = checkout.querySelector('[data-eilmo-advance-payment]');
		if (window.eilmoCfDom.isElement(section) && !Object.prototype.hasOwnProperty.call(section.dataset, 'eilmoFraudHidden')) {
			section.dataset.eilmoFraudHidden = section.hidden ? 'yes' : 'no';
		}
		return window.eilmoCfDom.isElement(section) ? section : null;
	}

	function normalizePaymentTypes(types) {
		return Array.isArray(types)
			? [...new Set(types.map((type) => String(type || '')).filter((type) => ['cash_on_delivery', 'advance', 'full'].includes(type)))]
			: [];
	}

	function setPendingPaymentState(checkout) {
		const mode = 'conditional' === getConfig().paymentDisplayMode ? 'conditional' : 'always';
		restorePaymentOptions(checkout);
		restorePaymentSection(checkout);
		const section = rememberPaymentSection(checkout);
		if (!(window.eilmoCfDom.isElement(section))) {
			return;
		}
		if ('conditional' === mode) {
			section.hidden = true;
			return;
		}

		section.classList.add('eilmo-cf-advance-payment--fraud-pending');
		checkout.querySelectorAll('[data-eilmo-payment-option]').forEach((option) => {
			const input = option.querySelector('[data-eilmo-payment-type]');
			if (window.eilmoCfDom.isElement(option) && window.eilmoCfDom.isElement(input, 'INPUT')) {
				rememberOption(option, input);
				input.disabled = true;
				option.classList.add('eilmo-cf-advance-payment-option--fraud-disabled');
				option.setAttribute('aria-disabled', 'true');
			}
		});
	}

	function applyPaymentRule(checkout, allowedTypes, displayMode) {
		const allowed = normalizePaymentTypes(allowedTypes);
		const mode = 'conditional' === displayMode ? 'conditional' : 'always';
		restorePaymentOptions(checkout);
		restorePaymentSection(checkout);
		const section = rememberPaymentSection(checkout);
		if (window.eilmoCfDom.isElement(section)) {
			section.hidden = false;
		}

		let availableInput = null;
		let optionCount = 0;
		checkout.querySelectorAll('[data-eilmo-payment-option]').forEach((option) => {
			const input = option.querySelector('[data-eilmo-payment-type]');
			if (!(window.eilmoCfDom.isElement(option)) || !(window.eilmoCfDom.isElement(input, 'INPUT'))) {
				return;
			}
			optionCount += 1;
			rememberOption(option, input);
			const accepted = allowed.includes(input.value) && !input.disabled;
			if (!accepted) {
				if ('conditional' === mode) {
					option.hidden = true;
				}
				input.disabled = true;
				option.classList.add('eilmo-cf-advance-payment-option--fraud-disabled');
				option.setAttribute('aria-disabled', 'true');
			} else if (!availableInput) {
				availableInput = input;
			}
		});

		if (0 === optionCount) {
			return allowed.includes('full');
		}

		const checked = checkout.querySelector('[data-eilmo-payment-type]:checked');
		if (!(window.eilmoCfDom.isElement(checked, 'INPUT')) || checked.disabled || !allowed.includes(checked.value)) {
			if (!(window.eilmoCfDom.isElement(availableInput, 'INPUT'))) {
				return false;
			}
			availableInput.checked = true;
			availableInput.dispatchEvent(new Event('change', { bubbles: true }));
		}
		if (window.eilmoCfAdvancePayment && 'function' === typeof window.eilmoCfAdvancePayment.refresh) {
			window.eilmoCfAdvancePayment.refresh(checkout);
		}
		return window.eilmoCfDom.isElement(availableInput, 'INPUT') || (window.eilmoCfDom.isElement(checked, 'INPUT') && !checked.disabled && allowed.includes(checked.value));
	}

	function setBlocked(checkout, blocked) {
		checkout.dataset.fraudBlocked = blocked ? 'yes' : 'no';
		checkout.querySelectorAll('[data-eilmo-order-submit]').forEach(function (button) {
			button.disabled = blocked || checkout.dataset.emptySelection === 'yes' || button.getAttribute('aria-busy') === 'true';
			button.setAttribute('aria-disabled', button.disabled ? 'true' : 'false');
		});
	}

	function reset(checkout, phone) {
		const state = getState(checkout);
		const mode = 'conditional' === getConfig().paymentDisplayMode ? 'conditional' : 'always';
		state.phone = phone || '';
		state.token = '';
		state.decision = '';
		state.message = '';
		state.checking = false;
		state.paymentAvailable = false;
		state.allowedPaymentTypes = [];
		state.paymentDisplayMode = mode;
		state.requestId += 1;
		setPendingPaymentState(checkout);
		setBlocked(checkout, true);
		render(checkout, '', '');
	}

	async function check(checkout, phone) {
		const state = getState(checkout);
		const config = getConfig();
		if (!isEnabled() || !phone || (state.phone === phone && state.token)) {
			return;
		}
		reset(checkout, phone);
		state.checking = true;
		const requestId = state.requestId;
		render(checkout, config.checkingMessage || 'Checking delivery history…', 'checking');

		const body = new URLSearchParams();
		body.set('action', config.action || 'eilmo_cf_live_fraud_check');
		body.set('nonce', window.eilmoCf && window.eilmoCf.ajaxNonce ? window.eilmoCf.ajaxNonce : '');
		body.set('phone', phone);

		try {
			const response = await fetch(window.eilmoCf.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString(),
			});
			const responseText = await response.text();
			let result;
			try {
				result = JSON.parse(responseText);
			} catch (parseError) {
				throw new Error(config.checkFailedMessage || 'Phone verification failed. Please try again.');
			}
			if (requestId !== state.requestId || phone !== state.phone) {
				return;
			}
			if (!result || !result.success || !result.data || !result.data.token) {
				throw new Error(result && result.data && result.data.message ? result.data.message : (config.checkFailedMessage || 'Phone verification failed. Please try again.'));
			}
			state.checking = false;
			state.token = String(result.data.token);
			state.decision = String(result.data.decision || 'allow');
			state.message = String(result.data.message || 'Phone verification completed.');
			state.allowedPaymentTypes = normalizePaymentTypes(result.data.allowedPaymentTypes);
			state.paymentDisplayMode = 'conditional' === result.data.paymentDisplayMode ? 'conditional' : 'always';
			state.paymentAvailable = applyPaymentRule(checkout, state.allowedPaymentTypes, state.paymentDisplayMode);
			setBlocked(checkout, 'block' === state.decision || !state.paymentAvailable);
			render(checkout, state.paymentAvailable ? state.message : (window.eilmoCfLanguage ? window.eilmoCfLanguage.copy(checkout, 'An advance payment is required for this order.') : 'The required payment option is unavailable.'), state.paymentAvailable ? state.decision : 'error');
		} catch (error) {
			if (requestId !== state.requestId) {
				return;
			}
			state.checking = false;
			state.token = '';
			state.decision = '';
			state.message = error instanceof Error ? error.message : 'Phone verification failed.';
			render(checkout, state.message, 'error');
		}
	}

	function schedule(checkout) {
		const state = getState(checkout);
		const config = getConfig();
		window.clearTimeout(state.timer);
		const input = checkout.querySelector('[data-eilmo-customer-phone]');
		const phone = window.eilmoCfDom.isElement(input, 'INPUT') ? normalizePhone(input.value) : '';
		if (!phone) {
			reset(checkout, '');
			render(checkout, config.phoneRequiredMessage || 'Enter a valid phone number to see available payment options.', 'pending');
			return;
		}
		if (state.phone === phone && (state.token || state.checking)) {
			return;
		}
		state.timer = window.setTimeout(() => check(checkout, phone), Math.max(300, Number(config.debounce || 700)));
	}

	function init(checkout) {
		if (!isEnabled() || !(window.eilmoCfDom.isElement(checkout))) {
			return;
		}
		const input = checkout.querySelector('[data-eilmo-customer-phone]');
		if (!(window.eilmoCfDom.isElement(input, 'INPUT')) || 'yes' === input.dataset.eilmoFraudBound) {
			return;
		}
		input.dataset.eilmoFraudBound = 'yes';
		input.addEventListener('input', () => schedule(checkout));
		input.addEventListener('change', () => schedule(checkout));
		schedule(checkout);
	}

	function initialize(scope = document) {
		if (!isEnabled()) {
			return;
		}

		if (window.eilmoCfDom.isElement(scope) && scope.matches('[data-eilmo-checkout]')) {
			init(scope);
		}

		if (scope && 'function' === typeof scope.querySelectorAll) {
			scope.querySelectorAll('[data-eilmo-checkout]').forEach(init);
		}
	}

	window.eilmoCfFraudCheck = {
		getData(checkout) {
			if (!isEnabled()) {
				return {};
			}
			const state = getState(checkout);
			return { token: state.token };
		},
		validate(checkout) {
			if (!isEnabled()) {
				return true;
			}
			const input = checkout.querySelector('[data-eilmo-customer-phone]');
			const phone = window.eilmoCfDom.isElement(input, 'INPUT') ? normalizePhone(input.value) : '';
			const state = getState(checkout);
			const config = getConfig();
			if (!phone || state.phone !== phone || !state.token || state.checking || 'block' === state.decision || !state.paymentAvailable) {
				render(checkout, state.message || config.checkRequiredMessage || 'Please complete phone verification before placing the order.', 'error');
				return false;
			}
			return true;
		},
		refresh: init,
	};

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', () => initialize(document));
	} else {
		initialize(document);
	}

	/* Rebind after Elementor editor/frontend widget renders. */
	let elementorHookRegistered = false;
	function registerElementorHook() {
		if (elementorHookRegistered || !window.elementorFrontend || !window.elementorFrontend.hooks || 'function' !== typeof window.elementorFrontend.hooks.addAction) {
			return;
		}
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/eilmo-checkout-flow.default',
			($scope) => initialize($scope && $scope[0] instanceof Element ? $scope[0] : document)
		);
		elementorHookRegistered = true;
	}

	registerElementorHook();
	if (window.jQuery && 'function' === typeof window.jQuery) {
		window.jQuery(window).on('elementor/frontend/init', registerElementorHook);
	}

	/* Cover popup, shortcode fragments and Elementor DOM replacements. */
	if (window.MutationObserver && document.documentElement) {
		new MutationObserver((mutations) => {
			mutations.forEach((mutation) => {
				mutation.addedNodes.forEach((node) => {
					if (window.eilmoCfDom.isElement(node)) {
						initialize(node);
					}
				});
			});
		}).observe(document.documentElement, { childList: true, subtree: true });
	}
}());
/* END src/js/frontend/fraud-check.js */

/* BEGIN src/js/frontend/payment.js */
/**
 * Eilmo Checkout Flow payment methods.
 *
 * Handles:
 * - Eilmo manual payment methods.
 * - WooCommerce native payment gateways.
 * - Payment selection.
 * - Manual transaction IDs.
 * - WooCommerce classic gateway events.
 * - Native payment-box visibility.
 * - Checkout state synchronization.
 * - Payment Option synchronization.
 * - Cash on Delivery virtual payment data.
 * - Frontend validation.
 *
 * @package EilmoCheckout
 */

(function () {
	'use strict';

	const SELECTORS = {
		checkout:
			'[data-eilmo-checkout]',

		section:
			'[data-eilmo-payment-methods]',

		paymentType:
			'[data-eilmo-payment-type]',

		method:
			'[data-eilmo-payment-method]',

		radio:
			'[data-eilmo-payment-radio]',

		details:
			'[data-eilmo-payment-details]',

		nativeBox:
			'[data-eilmo-native-payment-box]',

		gatewayFields:
			'[data-eilmo-gateway-fields]',

		transaction:
			'[data-eilmo-payment-transaction]',

		transactionWrap:
			'[data-eilmo-payment-transaction-wrap]',

		proof:
			'[data-eilmo-payment-proof]',

		proofToken:
			'[data-eilmo-payment-proof-token]',

		proofStatus:
			'[data-eilmo-payment-proof-status]',

		proofError:
			'[data-eilmo-payment-proof-error]',

		fieldError:
			'[data-eilmo-payment-field-error]',

		formError:
			'[data-eilmo-payment-form-error]',
	};

	/** Upload a selected manual-payment screenshot and store its temporary token. */
	async function uploadPaymentProof(input) {
		const method = input.closest(SELECTORS.method);
		if (!(window.eilmoCfDom.isElement(method)) || !(window.eilmoCfDom.isElement(input, 'INPUT'))) {
			return;
		}

		const file = input.files && input.files[0];
		const tokenInput = method.querySelector(SELECTORS.proofToken);
		const status = method.querySelector(SELECTORS.proofStatus);
		const errorBox = method.querySelector(SELECTORS.proofError);
		const config = window.eilmoCf && window.eilmoCf.paymentProof ? window.eilmoCf.paymentProof : {};
		const maxMb = Math.max(1, Number(method.dataset.proofMaxMb || 5));

		if (window.eilmoCfDom.isElement(tokenInput, 'INPUT')) {
			tokenInput.value = '';
		}
		setError(errorBox, '');

		if (!file) {
			method.dataset.proofUploading = 'no';
			return;
		}

		if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > maxMb * 1024 * 1024) {
			input.value = '';
			setError(errorBox, `Choose a JPG, PNG or WebP image up to ${maxMb} MB.`);
			return;
		}

		method.dataset.proofUploading = 'yes';
		input.disabled = true;
		if (status) {
			status.textContent = config.uploading || 'Uploading screenshot…';
		}

		try {
			const body = new FormData();
			body.append('action', config.action || 'eilmo_cf_upload_payment_proof');
			body.append('nonce', config.nonce || '');
			body.append('method_key', method.dataset.methodKey || '');
			body.append('proof', file, file.name);
			const response = await window.fetch((window.eilmoCf && window.eilmoCf.ajaxUrl) || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				credentials: 'same-origin',
				body: body,
			});
			const result = await response.json();
			if (!response.ok || !result.success || !result.data || !result.data.token) {
				throw new Error(result.data && result.data.message ? result.data.message : (config.failed || 'Screenshot upload failed.'));
			}
			if (window.eilmoCfDom.isElement(tokenInput, 'INPUT')) {
				tokenInput.value = String(result.data.token);
			}
			if (status) {
				status.textContent = `${result.data.name || file.name} — ${result.data.message || config.uploaded || 'Uploaded'}`;
			}
		} catch (error) {
			input.value = '';
			setError(errorBox, error instanceof Error ? error.message : (config.failed || 'Screenshot upload failed.'));
		} finally {
			method.dataset.proofUploading = 'no';
			input.disabled = false;
			const checkout = getCheckout(method);
			if (checkout) {
				syncCheckout(checkout, true);
			}
		}
	}

	/**
	 * Native gateway refresh timers.
	 *
	 * @type {WeakMap<HTMLElement, number>}
	 */
	const refreshTimers =
		new WeakMap();

	/**
	 * Initialized payment sections.
	 *
	 * @type {WeakSet<HTMLElement>}
	 */
	const initializedSections =
		new WeakSet();

	/**
	 * Get Eilmo checkout wrapper.
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

		const checkout =
			element.closest(
				SELECTORS.checkout
			);

		return window.eilmoCfDom.isElement(checkout)
				? checkout
				: null;
	}

	/**
	 * Get payment section.
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
	 * Get selected top-level Payment Option.
	 *
	 * Supported values:
	 *
	 * - cash_on_delivery
	 * - advance
	 * - full
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {string}
	 */
	function getPaymentType(checkout) {
		const checked =
			checkout.querySelector(
				`${SELECTORS.paymentType}:checked`
			);

		if (
			window.eilmoCfDom.isElement(checked, 'INPUT')
		) {
			const value =
				String(
					checked.value ||
					''
				);

			if (
				[
					'cash_on_delivery',
					'advance',
					'full',
				].includes(
					value
				)
			) {
				return value;
			}
		}

		const stored =
			String(
				checkout.dataset
					.paymentType ||
				''
			);

		if (
			[
				'cash_on_delivery',
				'advance',
				'full',
			].includes(
				stored
			)
		) {
			return stored;
		}

		return '';
	}

	/**
	 * Is Cash on Delivery the selected Payment Option?
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
	 * Determine whether a rendered Payment Method is COD.
	 *
	 * COD is no longer a Payment Method choice. It is now
	 * a top-level Payment Option. This helper also excludes
	 * WooCommerce's native "cod" gateway as a defensive
	 * fallback until the PHP renderer has removed it.
	 *
	 * @param {HTMLElement|null} method Method.
	 *
	 * @return {boolean}
	 */
	function isCashOnDeliveryMethod(
		method
	) {
		if (
			!(
				window.eilmoCfDom.isElement(method)
			)
		) {
			return false;
		}

		const radio =
			method.querySelector(
				SELECTORS.radio
			);

		const methodValue =
			String(
				method.dataset
					.methodValue ||
				''
			);

		const methodKey =
			String(
				method.dataset
					.methodKey ||
				''
			);

		const gatewayId =
			String(
				method.dataset
					.gatewayId ||
				''
			);

		const nativeValue =
			window.eilmoCfDom.isElement(radio, 'INPUT')
					? String(
						radio.value ||
							''
					)
					: String(
						method.dataset
							.nativePaymentValue ||
							''
					);

		return (
			'eilmo_cash_on_delivery' ===
				methodValue ||
			'cash_on_delivery' ===
				methodKey ||
			'wc__cod' ===
				methodValue ||
			'cod' ===
				gatewayId ||
			'cod' ===
				nativeValue
		);
	}

	/**
	 * Permanently exclude COD from the Payment Method list.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {void}
	 */
	function excludeCashOnDeliveryMethods(
		section
	) {
		section
			.querySelectorAll(
				SELECTORS.method
			)
			.forEach(
				function (method) {
					if (
						!(
							window.eilmoCfDom.isElement(method)
						) ||
						!isCashOnDeliveryMethod(
							method
						)
					) {
						return;
					}

					method.hidden =
						true;

					method.setAttribute(
						'aria-hidden',
						'true'
					);

					method.dataset
						.paymentOptionExcluded =
							'yes';

					const radio =
						method.querySelector(
							SELECTORS.radio
						);

					if (
						window.eilmoCfDom.isElement(radio, 'INPUT')
					) {
						radio.checked =
							false;

						radio.disabled =
							true;
					}

					const details =
						method.querySelector(
							SELECTORS.details
						);

					if (
						window.eilmoCfDom.isElement(details)
					) {
						hideDetails(
							details
						);
					}
				}
			);
	}

	/**
	 * Show / hide the Payment Method section according
	 * to the currently selected top-level Payment Option.
	 *
	 * Cash on Delivery:
	 * - Hide Payment Methods.
	 * - Payment validation is bypassed.
	 * - getData() exposes virtual Eilmo COD data.
	 *
	 * Advance / Full:
	 * - Show Payment Methods.
	 * - COD methods remain excluded.
	 * - A non-COD method is required according to section
	 *   configuration.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function syncPaymentOptionState(
		checkout
	) {
		const section =
			getSection(
				checkout
			);

		if (!section) {
			return;
		}

		excludeCashOnDeliveryMethods(
			section
		);

		if (
			isCashOnDelivery(
				checkout
			)
		) {
			clearErrors(
				section
			);

			section.hidden =
				true;

			section.setAttribute(
				'hidden',
				''
			);

			section.setAttribute(
				'aria-hidden',
				'true'
			);

			section.dataset
				.paymentOptionHidden =
					'yes';

			section
				.querySelectorAll(
					SELECTORS.details
				)
				.forEach(
					function (details) {
						if (
							window.eilmoCfDom.isElement(details)
						) {
							hideDetails(
								details
							);
						}
					}
				);

			const timer =
				refreshTimers.get(
					checkout
				);

			if (timer) {
				window.clearTimeout(
					timer
				);

				refreshTimers.delete(
					checkout
				);
			}

			return;
		}

		section.hidden =
			false;

		section.removeAttribute(
			'hidden'
		);

		section.removeAttribute(
			'aria-hidden'
		);

		delete section.dataset
			.paymentOptionHidden;

		ensureInitialSelection(
			section
		);

		updateMethodState(
			section
		);
	}

	/**
	 * Get selected payment radio.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {HTMLInputElement|null}
	 */
	function getSelectedRadio(section) {
		const radio =
			section.querySelector(
				`${SELECTORS.radio}:checked`
			);

		return window.eilmoCfDom.isElement(radio, 'INPUT')
				? radio
				: null;
	}

	/**
	 * Get selected payment method.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {HTMLElement|null}
	 */
	function getSelectedMethod(section) {
		const radio =
			getSelectedRadio(
				section
			);

		if (!radio) {
			return null;
		}

		const method =
			radio.closest(
				SELECTORS.method
			);

		return window.eilmoCfDom.isElement(method)
				? method
				: null;
	}

	/**
	 * Determine whether method is a native
	 * WooCommerce gateway.
	 *
	 * @param {HTMLElement|null} method Method.
	 *
	 * @return {boolean}
	 */
	function isWooCommerceMethod(
		method
	) {
		return (
			window.eilmoCfDom.isElement(method) &&
			method.dataset.paymentSource ===
				'woocommerce_gateway' &&
			Boolean(
				method.dataset.gatewayId
			)
		);
	}

	/**
	 * Determine whether section contains at least
	 * one native WooCommerce gateway.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {boolean}
	 */
	function hasWooCommerceGateway(
		section
	) {
		return Array.from(
			section.querySelectorAll(
				SELECTORS.method
			)
		).some(
			function (method) {
				return (
					window.eilmoCfDom.isElement(method) &&
					!isCashOnDeliveryMethod(
						method
					) &&
					method.dataset
						.paymentSource ===
							'woocommerce_gateway' &&
					Boolean(
						method.dataset
							.gatewayId
					)
				);
			}
		);
	}

	/**
	 * Get transaction input.
	 *
	 * @param {HTMLElement|null} method Method.
	 *
	 * @return {HTMLInputElement|null}
	 */
	function getTransactionInput(
		method
	) {
		if (
			!(
				window.eilmoCfDom.isElement(method)
			)
		) {
			return null;
		}

		const input =
			method.querySelector(
				SELECTORS.transaction
			);

		return window.eilmoCfDom.isElement(input, 'INPUT')
				? input
				: null;
	}

	/**
	 * Set error message.
	 *
	 * @param {HTMLElement|null} element Element.
	 * @param {string}           message Message.
	 *
	 * @return {void}
	 */
	function setError(
		element,
		message
	) {
		if (
			!(
				window.eilmoCfDom.isElement(element)
			)
		) {
			return;
		}

		element.textContent =
			String(
				message || ''
			);

		element.hidden =
			!message;
	}

	/**
	 * Clear payment errors.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {void}
	 */
	function clearErrors(section) {
		setError(
			section.querySelector(
				SELECTORS.formError
			),
			''
		);

		section
			.querySelectorAll(
				SELECTORS.fieldError
			)
			.forEach(
				function (error) {
					if (
						window.eilmoCfDom.isElement(error)
					) {
						setError(
							error,
							''
						);
					}
				}
			);

		section
			.querySelectorAll(
				[
					'input[aria-invalid="true"]',
					'select[aria-invalid="true"]',
					'textarea[aria-invalid="true"]',
				].join(',')
			)
			.forEach(
				function (field) {
					field.removeAttribute(
						'aria-invalid'
					);
				}
			);
	}

	/**
	 * Show one payment detail box.
	 *
	 * Native WooCommerce gateways require the
	 * payment_box element to be actually visible,
	 * not merely present in the DOM.
	 *
	 * @param {HTMLElement} details Details.
	 *
	 * @return {void}
	 */
	function showDetails(details) {
		details.hidden =
			false;

		details.removeAttribute(
			'hidden'
		);

		details.removeAttribute(
			'aria-hidden'
		);

		if (
			details.style.display ===
				'none'
		) {
			details.style.removeProperty(
				'display'
			);
		}
	}

	/**
	 * Hide one payment detail box.
	 *
	 * @param {HTMLElement} details Details.
	 *
	 * @return {void}
	 */
	function hideDetails(details) {
		details.hidden =
			true;

		details.setAttribute(
			'hidden',
			''
		);

		details.setAttribute(
			'aria-hidden',
			'true'
		);

		details.style.display =
			'none';
	}

	/**
	 * Update payment method visual state.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {void}
	 */
	function updateMethodState(section) {
		section
			.querySelectorAll(
				SELECTORS.method
			)
			.forEach(
				function (method) {
					if (
						!(
							window.eilmoCfDom.isElement(method)
						)
					) {
						return;
					}

					const radio =
						method.querySelector(
							SELECTORS.radio
						);

					const details =
						method.querySelector(
							SELECTORS.details
						);

					const selected =
						window.eilmoCfDom.isElement(radio, 'INPUT') &&
						radio.checked;

					method.classList.toggle(
						'is-selected',
						selected
					);

					method.setAttribute(
						'aria-selected',
						selected
							? 'true'
							: 'false'
					);

					if (
						!(
							window.eilmoCfDom.isElement(details)
						)
					) {
						return;
					}

					if (selected) {
						showDetails(
							details
						);

						return;
					}

					hideDetails(
						details
					);
				}
			);

		const selected =
			getSelectedMethod(
				section
			);

		section.dataset.selectedMethod =
			window.eilmoCfDom.isElement(selected)
					? (
						selected.dataset
							.methodValue ||
						''
					)
					: '';
	}

	/**
	 * Trigger a WooCommerce jQuery body event.
	 *
	 * @param {string} eventName Event.
	 * @param {Array<*>} args    Arguments.
	 *
	 * @return {void}
	 */
	function triggerWooEvent(
		eventName,
		args = []
	) {
		if (
			typeof window.jQuery !==
				'function'
		) {
			return;
		}

		try {
			window.jQuery(
				document.body
			).trigger(
				eventName,
				args
			);
		} catch (error) {
			/*
			 * Third-party gateway failures must not
			 * break Eilmo payment selection.
			 */
		}
	}

	/**
	 * Notify native WooCommerce payment scripts.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      reason   Reason.
	 *
	 * @return {void}
	 */
	function notifyPaymentMethodSelected(
		checkout,
		reason = 'selection'
	) {
		const section =
			getSection(
				checkout
			);

		if (
			isCashOnDelivery(
				checkout
			) ||
			!section ||
			!hasWooCommerceGateway(
				section
			)
		) {
			return;
		}

		triggerWooEvent(
			'payment_method_selected'
		);

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:nativePaymentMethodSelected',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						reason:
							reason,

						payment:
							getData(
								checkout
							),
					},
				}
			)
		);
	}

	/**
	 * Notify native gateway scripts that checkout
	 * payment state changed.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      reason   Reason.
	 *
	 * @return {void}
	 */
	function notifyUpdatedCheckout(
		checkout,
		reason = 'refresh'
	) {
		const section =
			getSection(
				checkout
			);

		if (
			isCashOnDelivery(
				checkout
			) ||
			!section ||
			!hasWooCommerceGateway(
				section
			)
		) {
			return;
		}

		triggerWooEvent(
			'updated_checkout',
			[
				{
					eilmoCf:
						true,

					reason:
						reason,
				},
			]
		);

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:nativePaymentUpdated',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						reason:
							reason,
					},
				}
			)
		);
	}

	/**
	 * Schedule native gateway refresh.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      reason   Reason.
	 * @param {number}      delay    Delay.
	 *
	 * @return {void}
	 */
	function scheduleNativeRefresh(
		checkout,
		reason = 'refresh',
		delay = 0
	) {
		if (
			isCashOnDelivery(
				checkout
			)
		) {
			const pending =
				refreshTimers.get(
					checkout
				);

			if (pending) {
				window.clearTimeout(
					pending
				);

				refreshTimers.delete(
					checkout
				);
			}

			return;
		}

		const existing =
			refreshTimers.get(
				checkout
			);

		if (existing) {
			window.clearTimeout(
				existing
			);
		}

		const timer =
			window.setTimeout(
				function () {
					refreshTimers.delete(
						checkout
					);

					notifyPaymentMethodSelected(
						checkout,
						reason
					);

					notifyUpdatedCheckout(
						checkout,
						reason
					);
				},
				Math.max(
					0,
					delay
				)
			);

		refreshTimers.set(
			checkout,
			timer
		);
	}

	/**
	 * Get selected payment data.
	 *
	 * COD returns a virtual Eilmo payment method so
	 * checkout.js can continue using the existing
	 * payment payload shape.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {Object}
	 */
	function getData(checkout) {
		const paymentType =
			getPaymentType(
				checkout
			);

		if (
			'cash_on_delivery' ===
				paymentType
		) {
			return {
				selected:
					true,

				method:
					'eilmo_cash_on_delivery',

				methodKey:
					'cash_on_delivery',

				source:
					'eilmo_manual',

				gatewayId:
					'',

				nativePaymentValue:
					'cod',

				transactionId:
					'',

				transactionRequired:
					false,

				proofToken:
					'',

				proofEnabled:
					false,

				isWooCommerceGateway:
					false,

				paymentType:
					'cash_on_delivery',

				isCashOnDelivery:
					true,
			};
		}

		const emptyData = {
			selected:
				false,

			method:
				'',

			methodKey:
				'',

			source:
				'',

			gatewayId:
				'',

			nativePaymentValue:
				'',

			transactionId:
				'',

			transactionRequired:
				false,

			proofToken:
				'',

			proofEnabled:
				false,

			isWooCommerceGateway:
				false,

			paymentType:
				paymentType,

			isCashOnDelivery:
				false,
		};

		const section =
			getSection(
				checkout
			);

		if (!section) {
			return emptyData;
		}

		excludeCashOnDeliveryMethods(
			section
		);

		const method =
			getSelectedMethod(
				section
			);

		if (
			!method ||
			isCashOnDeliveryMethod(
				method
			)
		) {
			return emptyData;
		}

		const radio =
			method.querySelector(
				SELECTORS.radio
			);

		const transactionInput =
			getTransactionInput(
				method
			);

		const proofTokenInput = method.querySelector(SELECTORS.proofToken);

		const source =
			method.dataset
				.paymentSource ||
			'';

		const gatewayId =
			method.dataset.gatewayId ||
			'';

		return {
			selected:
				true,

			method:
				method.dataset
					.methodValue ||
				'',

			methodKey:
				method.dataset
					.methodKey ||
				'',

			source:
				source,

			gatewayId:
				gatewayId,

			nativePaymentValue:
				window.eilmoCfDom.isElement(radio, 'INPUT')
						? radio.value
						: (
							method.dataset
								.nativePaymentValue ||
							''
						),

			transactionId:
				transactionInput
					? transactionInput
						.value
						.trim()
					: '',

			transactionRequired:
				method.dataset
					.transactionRequired ===
				'yes',

			proofToken:
				window.eilmoCfDom.isElement(proofTokenInput, 'INPUT') ? proofTokenInput.value.trim() : '',

			proofEnabled:
				method.dataset.proofEnabled === 'yes',

			isWooCommerceGateway:
				source ===
					'woocommerce_gateway' &&
				Boolean(
					gatewayId
				),

			paymentType:
				paymentType,

			isCashOnDelivery:
				false,
		};
	}

	/**
	 * Sync payment data to Eilmo checkout dataset.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {boolean}     dispatch Dispatch event.
	 *
	 * @return {void}
	 */
	function syncCheckout(
		checkout,
		dispatch
	) {
		const data =
			getData(
				checkout
			);

		checkout.dataset.paymentMethod =
			data.method;

		checkout.dataset.paymentMethodKey =
			data.methodKey;

		checkout.dataset.paymentSource =
			data.source;

		checkout.dataset.paymentGatewayId =
			data.gatewayId;

		checkout.dataset.paymentNativeValue =
			data.nativePaymentValue;

		checkout.dataset.paymentTransactionId =
			data.transactionId;

		/*
		 * Keep an order-submission fallback outside the payment card. Dynamic
		 * gateway refreshes can replace method markup after an upload, while the
		 * private upload token must survive until checkout.js builds its payload.
		 */
		checkout.dataset.paymentProofToken =
			data.proofToken;

		if (!dispatch) {
			return;
		}

		checkout.dispatchEvent(
			new CustomEvent(
				'eilmo:paymentChange',
				{
					bubbles:
						true,

					detail: {
						checkout:
							checkout,

						...data,
					},
				}
			)
		);
	}

	/**
	 * Get invalid required native HTML field.
	 *
	 * @param {HTMLElement} method Method.
	 *
	 * @return {HTMLElement|null}
	 */
	function getInvalidGatewayField(
		method
	) {
		const fields =
			method.querySelectorAll(
				[
					'input[required]',
					'select[required]',
					'textarea[required]',
				].join(',')
			);

		for (const field of fields) {
			if (
				!(
					window.eilmoCfDom.isElement(field, 'INPUT') ||
					window.eilmoCfDom.isElement(field, 'SELECT') ||
					window.eilmoCfDom.isElement(field, 'TEXTAREA')
				)
			) {
				continue;
			}

			if (
				field.matches(
					SELECTORS.transaction
				)
			) {
				continue;
			}

			if (
				field.matches(
					SELECTORS.radio
				)
			) {
				continue;
			}

			if (field.disabled) {
				continue;
			}

			if (
				!field.checkValidity()
			) {
				return field;
			}
		}

		return null;
	}

	/**
	 * Validate payment section.
	 *
	 * COD does not require a Payment Method.
	 *
	 * Advance / Full continue using normal Payment
	 * Method validation.
	 *
	 * @param {HTMLElement} checkout     Checkout.
	 * @param {boolean}     showMessages Show messages.
	 *
	 * @return {boolean}
	 */
	function validateCheckout(
		checkout,
		showMessages = true
	) {
		if (
			isCashOnDelivery(
				checkout
			)
		) {
			const section =
				getSection(
					checkout
				);

			if (section) {
				clearErrors(
					section
				);
			}

			return true;
		}

		const section =
			getSection(
				checkout
			);

		if (!section) {
			return true;
		}

		clearErrors(
			section
		);

		const required =
			section.dataset.required ===
				'yes';

		const method =
			getSelectedMethod(
				section
			);

		if (!method) {
			if (!required) {
				return true;
			}

			if (showMessages) {
				setError(
					section.querySelector(
						SELECTORS.formError
					),
					'Please select a payment method.'
				);
			}

			return false;
		}

		if (
			isCashOnDeliveryMethod(
				method
			)
		) {
			if (showMessages) {
				setError(
					section.querySelector(
						SELECTORS.formError
					),
					'Please select a payment method.'
				);
			}

			return false;
		}

		const transactionRequired =
			method.dataset
				.transactionRequired ===
					'yes';

		const transactionInput =
			getTransactionInput(
				method
			);

		const proofEnabled = method.dataset.proofEnabled === 'yes';
		const proofTokenInput = method.querySelector(SELECTORS.proofToken);
		const proofToken = window.eilmoCfDom.isElement(proofTokenInput, 'INPUT') ? proofTokenInput.value.trim() : '';
		const hasTransaction = Boolean(transactionInput && transactionInput.value.trim());

		if (method.dataset.proofUploading === 'yes') {
			if (showMessages) {
				setError(method.querySelector(SELECTORS.proofError), 'Please wait for the screenshot upload to finish.');
			}
			return false;
		}

		if (proofEnabled && !proofToken && !hasTransaction) {
			if (showMessages) {
				setError(method.querySelector(SELECTORS.proofError), transactionRequired ? 'Enter the Transaction ID or upload a payment screenshot.' : 'Please upload a payment screenshot.');
			}
			return false;
		}

		if (
			transactionRequired &&
			!proofEnabled &&
			(
				!transactionInput ||
				'' ===
					transactionInput
						.value
						.trim()
			)
		) {
			if (showMessages) {
				const fieldError =
					method.querySelector(
						SELECTORS.fieldError
					);

				setError(
					fieldError,
					'Please enter the transaction ID.'
				);

				if (transactionInput) {
					transactionInput
						.setAttribute(
							'aria-invalid',
							'true'
						);
				}
			}

			return false;
		}

		const invalidGatewayField =
			getInvalidGatewayField(
				method
			);

		if (invalidGatewayField) {
			if (showMessages) {
				setError(
					section.querySelector(
						SELECTORS.formError
					),
					'Please complete the required payment details.'
				);

				invalidGatewayField
					.setAttribute(
						'aria-invalid',
						'true'
					);
			}

			return false;
		}

		return true;
	}

	/**
	 * Focus first invalid payment field.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {boolean}
	 */
	function focusFirstInvalid(
		checkout
	) {
		if (
			isCashOnDelivery(
				checkout
			)
		) {
			return false;
		}

		const section =
			getSection(
				checkout
			);

		if (!section) {
			return false;
		}

		const method =
			getSelectedMethod(
				section
			);

		if (!method) {
			const radios =
				Array.from(
					section.querySelectorAll(
						SELECTORS.radio
					)
				);

			for (
				let index = 0;
				index < radios.length;
				index += 1
			) {
				const radio =
					radios[index];

				if (
					!(
						window.eilmoCfDom.isElement(radio, 'INPUT')
					) ||
					radio.disabled
				) {
					continue;
				}

				const paymentMethod =
					radio.closest(
						SELECTORS.method
					);

				if (
					!(
						window.eilmoCfDom.isElement(paymentMethod)
					) ||
					isCashOnDeliveryMethod(
						paymentMethod
					)
				) {
					continue;
				}

				radio.focus();

				return true;
			}

			return false;
		}

		if (
			method.dataset
				.transactionRequired ===
					'yes' &&
			method.dataset.proofEnabled !== 'yes'
		) {
			const transactionInput =
				getTransactionInput(
					method
				);

			if (
				transactionInput &&
				'' ===
					transactionInput
						.value
						.trim()
			) {
				transactionInput.focus();

				return true;
			}
		}

		if (method.dataset.proofEnabled === 'yes') {
			const proof = method.querySelector(SELECTORS.proof);
			const token = method.querySelector(SELECTORS.proofToken);
			const transaction = getTransactionInput(method);
			if (
				window.eilmoCfDom.isElement(proof, 'INPUT') &&
				(!(window.eilmoCfDom.isElement(token, 'INPUT')) || !token.value.trim()) &&
				(!transaction || !transaction.value.trim())
			) {
				proof.focus();
				return true;
			}
		}

		const invalidGatewayField =
			getInvalidGatewayField(
				method
			);

		if (
			window.eilmoCfDom.isElement(invalidGatewayField)
		) {
			invalidGatewayField.focus();

			return true;
		}

		if (
			isWooCommerceMethod(
				method
			)
		) {
			const iframe =
				method.querySelector(
					[
						SELECTORS.gatewayFields,
						' iframe',
					].join('')
				);

			if (
				window.eilmoCfDom.isElement(iframe, 'IFRAME')
			) {
				iframe.focus();

				return true;
			}
		}

		return false;
	}

	/**
	 * Ensure an initial non-COD payment method
	 * is selected.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {void}
	 */
	function ensureInitialSelection(
		section
	) {
		excludeCashOnDeliveryMethods(
			section
		);

		const selected =
			getSelectedRadio(
				section
			);

		if (
			window.eilmoCfDom.isElement(selected, 'INPUT') &&
			!selected.disabled
		) {
			const selectedMethod =
				selected.closest(
					SELECTORS.method
				);

			if (
				window.eilmoCfDom.isElement(selectedMethod) &&
				!isCashOnDeliveryMethod(
					selectedMethod
				)
			) {
				return;
			}
		}

		const radios =
			Array.from(
				section.querySelectorAll(
					SELECTORS.radio
				)
			);

		for (
			let index = 0;
			index < radios.length;
			index += 1
		) {
			const radio =
				radios[index];

			if (
				!(
					window.eilmoCfDom.isElement(radio, 'INPUT')
				) ||
				radio.disabled
			) {
				continue;
			}

			const method =
				radio.closest(
					SELECTORS.method
				);

			if (
				!(
					window.eilmoCfDom.isElement(method)
				) ||
				method.hidden ||
				isCashOnDeliveryMethod(
					method
				)
			) {
				continue;
			}

			radio.checked =
				true;

			return;
		}
	}

	/**
	 * Prepare Eilmo checkout DOM for classic
	 * WooCommerce gateway integrations.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function prepareNativeCheckoutDom(
		checkout
	) {
		checkout.classList.add(
			'woocommerce-checkout'
		);

		checkout.dataset
			.eilmoNativePaymentRuntime =
				'yes';
	}

	/**
	 * Initialize one payment section.
	 *
	 * @param {HTMLElement} section Section.
	 *
	 * @return {void}
	 */
	function initializeSection(
		section
	) {
		const checkout =
			getCheckout(
				section
			);

		if (!checkout) {
			return;
		}

		prepareNativeCheckoutDom(
			checkout
		);

		syncPaymentOptionState(
			checkout
		);

		syncCheckout(
			checkout,
			false
		);

		if (
			initializedSections.has(
				section
			)
		) {
			if (
				!isCashOnDelivery(
					checkout
				)
			) {
				scheduleNativeRefresh(
					checkout,
					'refresh',
					30
				);
			}

			return;
		}

		initializedSections.add(
			section
		);

		if (
			!isCashOnDelivery(
				checkout
			)
		) {
			scheduleNativeRefresh(
				checkout,
				'initial',
				0
			);
		}

		window.setTimeout(
			function () {
				if (
					document.contains(
						section
					) &&
					!isCashOnDelivery(
						checkout
					)
				) {
					notifyPaymentMethodSelected(
						checkout,
						'initial-late'
					);

					notifyUpdatedCheckout(
						checkout,
						'initial-late'
					);
				}
			},
			350
		);
	}

	/**
	 * Refresh payment section.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 *
	 * @return {void}
	 */
	function refresh(checkout) {
		const section =
			getSection(
				checkout
			);

		if (!section) {
			return;
		}

		syncPaymentOptionState(
			checkout
		);

		initializeSection(
			section
		);

		syncCheckout(
			checkout,
			true
		);
	}

	/**
	 * Refresh only native gateway runtime.
	 *
	 * @param {HTMLElement} checkout Checkout.
	 * @param {string}      reason   Reason.
	 *
	 * @return {void}
	 */
	function refreshNativeGateway(
		checkout,
		reason = 'manual-refresh'
	) {
		if (
			!(
				window.eilmoCfDom.isElement(checkout)
			)
		) {
			return;
		}

		scheduleNativeRefresh(
			checkout,
			reason,
			0
		);
	}

	/**
	 * Handle payment field change.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleChange(event) {
		if (
			!(
				window.eilmoCfDom.isElement(event.target)
			)
		) {
			return;
		}

		const section =
			event.target.closest(
				SELECTORS.section
			);

		if (
			!(
				window.eilmoCfDom.isElement(section)
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				section
			);

		if (!checkout) {
			return;
		}

		if (
			isCashOnDelivery(
				checkout
			)
		) {
			return;
		}

		if (
			event.target.matches(
				SELECTORS.radio
			)
		) {
			const method =
				event.target.closest(
					SELECTORS.method
				);

			if (
				window.eilmoCfDom.isElement(method) &&
				isCashOnDeliveryMethod(
					method
				)
			) {
				event.target.checked =
					false;

				return;
			}

			clearErrors(
				section
			);

			updateMethodState(
				section
			);

			syncCheckout(
				checkout,
				true
			);

			notifyPaymentMethodSelected(
				checkout,
				'payment-method-change'
			);

			scheduleNativeRefresh(
				checkout,
				'payment-method-change',
				50
			);

			return;
		}

		if (event.target.matches(SELECTORS.proof)) {
			uploadPaymentProof(event.target);
			return;
		}

		if (
			event.target.matches(
				[
					'input',
					'select',
					'textarea',
				].join(',')
			)
		) {
			syncCheckout(
				checkout,
				true
			);
		}
	}

	/**
	 * Handle payment field input.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleInput(event) {
		if (
			!(
				window.eilmoCfDom.isElement(event.target)
			)
		) {
			return;
		}

		const section =
			event.target.closest(
				SELECTORS.section
			);

		if (
			!(
				window.eilmoCfDom.isElement(section)
			)
		) {
			return;
		}

		const checkout =
			getCheckout(
				section
			);

		if (
			!checkout ||
			isCashOnDelivery(
				checkout
			)
		) {
			return;
		}

		if (
			event.target.matches(
				SELECTORS.transaction
			)
		) {
			const wrap =
				event.target.closest(
					SELECTORS.transactionWrap
				);

			if (
				window.eilmoCfDom.isElement(wrap)
			) {
				setError(
					wrap.querySelector(
						SELECTORS.fieldError
					),
					''
				);
			}

			event.target.removeAttribute(
				'aria-invalid'
			);
		}

		syncCheckout(
			checkout,
			true
		);
	}

	/**
	 * Customer information changed.
	 *
	 * @param {Event} event Event.
	 *
	 * @return {void}
	 */
	function handleCustomerChange(
		event
	) {
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

		if (
			!checkout ||
			isCashOnDelivery(
				checkout
			)
		) {
			return;
		}

		scheduleNativeRefresh(
			checkout,
			'customer-change',
			100
		);
	}

	/**
	 * Top-level Payment Option changed.
	 *
	 * @param {Event} event Payment option event.
	 *
	 * @return {void}
	 */
	function handlePaymentOptionChange(
		event
	) {
		const checkout =
			event.detail &&
			window.eilmoCfDom.isElement(event.detail.checkout)
					? event.detail.checkout
					: (
						window.eilmoCfDom.isElement(event.target)
								? getCheckout(
									event.target
								)
								: null
					);

		if (!checkout) {
			return;
		}

		syncPaymentOptionState(
			checkout
		);

		syncCheckout(
			checkout,
			true
		);

		if (
			!isCashOnDelivery(
				checkout
			)
		) {
			scheduleNativeRefresh(
				checkout,
				'payment-option-change',
				50
			);
		}
	}

	/**
	 * Initialize all payment sections.
	 *
	 * @return {void}
	 */
	function initialize() {
		document
			.querySelectorAll(
				SELECTORS.section
			)
			.forEach(
				function (section) {
					if (
						window.eilmoCfDom.isElement(section)
					) {
						initializeSection(
							section
						);
					}
				}
			);
	}

	document.addEventListener(
		'change',
		handleChange
	);

	document.addEventListener(
		'input',
		handleInput
	);

	document.addEventListener(
		'eilmo:customerChange',
		handleCustomerChange
	);

	document.addEventListener(
		'eilmo:advancePaymentChange',
		handlePaymentOptionChange
	);

	if (
		'loading' ===
			document.readyState
	) {
		document.addEventListener(
			'DOMContentLoaded',
			initialize
		);
	} else {
		initialize();
	}

	window.addEventListener(
		'load',
		function () {
			document
				.querySelectorAll(
					SELECTORS.section
				)
				.forEach(
					function (section) {
						if (
							!(
								window.eilmoCfDom.isElement(section)
							)
						) {
							return;
						}

						const checkout =
							getCheckout(
								section
							);

						if (!checkout) {
							return;
						}

						syncPaymentOptionState(
							checkout
						);

						syncCheckout(
							checkout,
							false
						);

						if (
							!isCashOnDelivery(
								checkout
							)
						) {
							scheduleNativeRefresh(
								checkout,
								'window-load',
								50
							);
						}
					}
				);
		}
	);

	/**
	 * Public Payment API.
	 */
	window.eilmoCfPayment = {

		getData:
			getData,

		validateCheckout:
			validateCheckout,

		focusFirstInvalid:
			focusFirstInvalid,

		refresh:
			refresh,

		refreshNativeGateway:
			refreshNativeGateway,

		isCashOnDelivery:
			isCashOnDelivery,

		syncPaymentOptionState:
			syncPaymentOptionState,

		getSelectedMethod:
			function (checkout) {
				const section =
					getSection(
						checkout
					);

				return section
					? getSelectedMethod(
						section
					)
					: null;
			},

		isWooCommerceGateway:
			function (checkout) {
				if (
					isCashOnDelivery(
						checkout
					)
				) {
					return false;
				}

				const section =
					getSection(
						checkout
					);

				if (!section) {
					return false;
				}

				return isWooCommerceMethod(
					getSelectedMethod(
						section
					)
				);
			},
	};
})();
/* END src/js/frontend/payment.js */

/* BEGIN src/js/frontend/order-bump.js */
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
/* END src/js/frontend/order-bump.js */

/* BEGIN src/js/frontend/checkout.js */
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
/* END src/js/frontend/checkout.js */

/* BEGIN src/js/frontend/tracking.js */
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
/* END src/js/frontend/tracking.js */

/* BEGIN src/js/frontend/abandoned.js */
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
/* END src/js/frontend/abandoned.js */

/* BEGIN src/js/frontend/checkout-presentation.js */
(function () {
 'use strict';
 if (window.eilmoCfPresentationReady) return;
 window.eilmoCfPresentationReady = true;
 function selectedItem(root) {
  const items = Array.from(root.querySelectorAll('[data-eilmo-item]'));
  if(root.matches('[data-eilmo-item]'))items.unshift(root);
  return items.find(item => {
   const input=item.querySelector('[data-eilmo-quantity-input]');
   return input && Number(input.value)>0 && item.dataset.purchasable!=='no';
  });
 }
 function update(checkout) {
  checkout.querySelectorAll('[data-eilmo-reference-quantity]').forEach(strip=>{
   const root=strip.closest('[data-eilmo-single-product]');
   if(!root)return;
   const item=selectedItem(root); const input=item && item.querySelector('[data-eilmo-quantity-input]');
   const qty=input?Number(input.value):0;
   strip.querySelector('output').textContent=window.eilmoCfLanguage.digits(qty,checkout.dataset.checkoutLanguage);
   strip.querySelector('[data-reference-delta="-1"]').disabled=!item || qty<=1;
   strip.querySelector('[data-reference-delta="1"]').disabled=!item || (Number(input.max)>0 && qty>=Number(input.max));
  });
 }
 function init(){document.querySelectorAll('[data-eilmo-checkout]').forEach(update);}
 document.addEventListener('click',event=>{
  const button=event.target.closest && event.target.closest('[data-reference-delta]');
  if(!button)return;
  const root=button.closest('[data-eilmo-single-product]'),item=root && selectedItem(root);
  if(!item)return;
  const input=item.querySelector('[data-eilmo-quantity-input]');
  root.dispatchEvent(new CustomEvent('eilmo:setQuantity',{bubbles:true,detail:{productId:Number(item.dataset.productId),variationId:Number(item.dataset.variationId||0),quantity:Math.max(1,Number(input.value)+Number(button.dataset.referenceDelta))}}));
  update(root.closest('[data-eilmo-checkout]'));
 });
 document.addEventListener('eilmo:quantityChange',event=>{const checkout=event.target.closest && event.target.closest('[data-eilmo-checkout]');if(checkout)update(checkout);});
 document.addEventListener('eilmo:summaryChange',init);
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
 new MutationObserver(records=>{if(records.some(record=>Array.from(record.addedNodes).some(node=>node.nodeType===1 && (node.matches('[data-eilmo-checkout]')||node.querySelector('[data-eilmo-checkout]')))))init();}).observe(document.documentElement,{childList:true,subtree:true});
})();
/* END src/js/frontend/checkout-presentation.js */

/* BEGIN src/js/frontend/product-actions.js */
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
		const regular = compact.querySelector('[data-eilmo-package-compact-regular]');
		const saving = compact.querySelector('[data-eilmo-package-compact-saving]');
		if (element(choice)) {
			const sourceLabel = choice.querySelector('.eilmo-cf-single-product__choice-label');
			const sourcePrice = choice.querySelector('.eilmo-cf-single-product__choice-price');
			const sourceRegular = choice.querySelector('.eilmo-cf-single-product__choice-regular-price');
			const sourceSaving = choice.querySelector('.eilmo-cf-single-product__choice-saving');
			if (element(label)) label.textContent = sourceLabel ? sourceLabel.textContent.trim() : '';
			[[price, sourcePrice], [regular, sourceRegular], [saving, sourceSaving]].forEach(function (pair) {
				if (!element(pair[0])) return;
				pair[0].innerHTML = element(pair[1]) ? pair[1].innerHTML : '';
				pair[0].hidden = !element(pair[1]) || pair[1].hidden || !pair[0].textContent.trim();
			});
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
		compact.innerHTML = '<div class="eilmo-cf-package-compact__info"><strong data-eilmo-package-compact-label></strong><span class="eilmo-cf-single-product__choice-price" data-eilmo-package-compact-price></span><del class="eilmo-cf-single-product__choice-regular-price" data-eilmo-package-compact-regular hidden></del><span class="eilmo-cf-single-product__choice-saving" data-eilmo-package-compact-saving hidden></span></div><button type="button" class="eilmo-cf-package-compact__change" data-eilmo-package-change></button>';
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
/* END src/js/frontend/product-actions.js */
