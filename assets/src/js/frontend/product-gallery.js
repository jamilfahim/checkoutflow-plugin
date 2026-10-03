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
