/** Checkout Flow admin interactions. */
(function () {
	'use strict';

	function closeModal(modal) {
		if (!(modal instanceof HTMLElement)) {
			return;
		}
		modal.hidden = true;
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('eilmo-cf-proof-modal-open');
		const image = modal.querySelector('[data-eilmo-payment-proof-image]');
		if (image instanceof HTMLImageElement) {
			image.src = '';
		}
	}

	document.addEventListener('click', function (event) {
		const opener = event.target instanceof Element ? event.target.closest('[data-eilmo-payment-proof-open]') : null;
		if (opener instanceof HTMLElement) {
			event.preventDefault();
			const modal = document.querySelector('[data-eilmo-payment-proof-modal]');
			const image = modal ? modal.querySelector('[data-eilmo-payment-proof-image]') : null;
			const title = modal ? modal.querySelector('[data-eilmo-payment-proof-title]') : null;
			if (modal instanceof HTMLElement && image instanceof HTMLImageElement) {
				image.src = opener.dataset.proofUrl || '';
				if (title) {
					title.textContent = opener.dataset.proofName || 'Payment screenshot';
				}
				modal.hidden = false;
				modal.setAttribute('aria-hidden', 'false');
				document.body.classList.add('eilmo-cf-proof-modal-open');
				const close = modal.querySelector('.eilmo-cf-payment-proof-modal__close');
				if (close instanceof HTMLElement) {
					close.focus();
				}
			}
			return;
		}

		const closer = event.target instanceof Element ? event.target.closest('[data-eilmo-payment-proof-close]') : null;
		if (closer) {
			closeModal(closer.closest('[data-eilmo-payment-proof-modal]'));
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeModal(document.querySelector('[data-eilmo-payment-proof-modal]:not([hidden])'));
		}
	});
}());

/** Abandoned Checkout courier breakdown popover. */
(function () {
	'use strict';

	var popover = null;
	var hideTimer = null;
	var activeTarget = null;

	function parsePayload(target) {
		try {
			return JSON.parse(target.getAttribute('data-eilmo-abandoned-courier-breakdown') || '{}');
		} catch (error) {
			return {};
		}
	}

	function formatRatio(value) {
		var number = Number(value || 0);
		return Number.isInteger(number) ? String(number) : number.toFixed(1);
	}

	function steadfastRateOnly(entry) {
		if (!entry || entry.rate_only !== true || !entry.parcel_range || !Number.isFinite(Number(entry.ratio))) {
			return '';
		}
		var bands = { low: 'Low', medium: 'Medium', high: 'High', very_high: 'Very High' };
		var band = bands[String(entry.volume_band || '')];
		return band ? String(entry.parcel_range) + ' (' + band + ')' : '';
	}

	function ensurePopover() {
		if (popover) {
			return popover;
		}

		popover = document.createElement('div');
		popover.className = 'eilmo-cf-courier-breakdown eilmo-cf-abandoned-courier-breakdown';
		popover.setAttribute('role', 'dialog');
		popover.setAttribute('aria-label', 'Courier breakdown');
		popover.hidden = true;

		popover.addEventListener('mouseenter', function () {
			if (hideTimer) {
				window.clearTimeout(hideTimer);
				hideTimer = null;
			}
		});

		popover.addEventListener('mouseleave', function () {
			hidePopover(100);
		});

		document.body.appendChild(popover);
		return popover;
	}

	function appendRow(container, entry) {
		var rateOnly = entry && entry.key === 'steadfast' ? steadfastRateOnly(entry) : '';
		var available = Boolean(rateOnly || (entry && entry.available && Number(entry.total || 0) > 0));
		var row = document.createElement('div');
		row.className = 'eilmo-cf-courier-breakdown__row' + (available ? '' : ' is-empty');

		var name = document.createElement('span');
		name.className = 'eilmo-cf-courier-breakdown__name';
		name.textContent = String((entry && entry.label) || 'Courier');

		var detail = document.createElement('span');
		detail.className = 'eilmo-cf-courier-breakdown__detail';

		var count = document.createElement('strong');
		count.textContent = rateOnly || (available
			? String(Number(entry.success || 0)) + '/' + String(Number(entry.total || 0))
			: 'No history');
		detail.appendChild(count);

		if (available) {
			var badge = document.createElement('span');
			var band = String(entry.band || 'unknown').replace(/[^a-z_-]/gi, '').toLowerCase();
			badge.className = 'eilmo-cf-courier-breakdown__badge is-' + (band || 'unknown');
			badge.textContent = formatRatio(entry.ratio) + '%';
			detail.appendChild(badge);
		}

		row.appendChild(name);
		row.appendChild(detail);
		container.appendChild(row);
	}

	function appendStoreHistory(box, store) {
		var section = document.createElement('div');
		section.className = 'eilmo-cf-courier-breakdown__store';

		var header = document.createElement('div');
		header.className = 'eilmo-cf-courier-breakdown__store-header';
		var titleWrap = document.createElement('div');
		var eyebrow = document.createElement('span');
		eyebrow.className = 'eilmo-cf-courier-breakdown__eyebrow';
		eyebrow.textContent = 'Customer on This Store';
		var title = document.createElement('strong');
		title.className = 'eilmo-cf-courier-breakdown__store-title';
		title.textContent = 'Store order history';
		titleWrap.appendChild(eyebrow);
		titleWrap.appendChild(title);
		var count = document.createElement('span');
		count.className = 'eilmo-cf-courier-breakdown__store-count';
		count.textContent = String(Number(store.total || 0)) + ' orders';
		header.appendChild(titleWrap);
		header.appendChild(count);
		section.appendChild(header);

		if (Number(store.total || 0) > 0) {
			var stats = document.createElement('div');
			stats.className = 'eilmo-cf-courier-breakdown__store-stats';
			[
				['Completed', Number(store.success || 0)],
				['Unsuccessful', Number(store.cancel || 0)],
				['Open', Number(store.open || 0)],
				['Success', Number(store.resolved_total || 0) > 0 ? formatRatio(store.ratio) + '%' : '—']
			].forEach(function (item) {
				var stat = document.createElement('span');
				var label = document.createElement('small');
				var value = document.createElement('strong');
				label.textContent = item[0];
				value.textContent = String(item[1]);
				stat.appendChild(label);
				stat.appendChild(value);
				stats.appendChild(stat);
			});
			section.appendChild(stats);
		} else {
			var empty = document.createElement('div');
			empty.className = 'eilmo-cf-courier-breakdown__store-empty';
			empty.textContent = 'No orders found on this store.';
			section.appendChild(empty);
		}

		box.appendChild(section);
	}

	function renderPopover(target) {
		var data = parsePayload(target);
		var box = ensurePopover();
		box.classList.remove('is-steadfast');
		if (data.provider === 'steadfast') {
			renderSteadfastPopover(box, data);
			box.hidden = false;
			activeTarget = target;
			positionPopover(target, box);
			return;
		}
		var entries = Array.isArray(data.entries) ? data.entries : [];
		box.innerHTML = '';

		var header = document.createElement('div');
		header.className = 'eilmo-cf-courier-breakdown__header';

		var heading = document.createElement('div');
		var eyebrow = document.createElement('span');
		eyebrow.className = 'eilmo-cf-courier-breakdown__eyebrow';
		eyebrow.textContent = 'All Couriers';
		var title = document.createElement('strong');
		title.className = 'eilmo-cf-courier-breakdown__title';
		title.textContent = 'Courier breakdown';
		heading.appendChild(eyebrow);
		heading.appendChild(title);

		var total = document.createElement('div');
		total.className = 'eilmo-cf-courier-breakdown__total';
		var rateOnlyEntry = Number(data.total || 0) <= 0
			? entries.find(function (entry) { return entry && entry.key === 'steadfast' && steadfastRateOnly(entry); })
			: null;
		if (rateOnlyEntry) {
			total.classList.add('is-' + String(rateOnlyEntry.band || 'unknown').replace(/[^a-z_-]/gi, '').toLowerCase());
		}
		total.textContent = Number(data.total || 0) > 0
			? String(Number(data.success || 0)) + '/' + String(Number(data.total || 0)) + ' (' + formatRatio(data.ratio) + '%)'
			: (rateOnlyEntry ? steadfastRateOnly(rateOnlyEntry) + ' ' + formatRatio(rateOnlyEntry.ratio) + '%' : 'No history');

		header.appendChild(heading);
		header.appendChild(total);
		box.appendChild(header);
		if (data.store && typeof data.store === 'object') {
			appendStoreHistory(box, data.store);
		}

		var body = document.createElement('div');
		body.className = 'eilmo-cf-courier-breakdown__body';
		if (entries.length) {
			entries.forEach(function (entry) {
				appendRow(body, entry || {});
			});
		} else {
			var empty = document.createElement('div');
			empty.className = 'eilmo-cf-courier-breakdown__empty';
			empty.textContent = 'No history';
			body.appendChild(empty);
		}
		box.appendChild(body);

		if (Number(data.total || 0) > 0) {
			var footer = document.createElement('div');
			footer.className = 'eilmo-cf-courier-breakdown__footer';
			[
				['Delivered', Number(data.success || 0)],
				['Cancelled', Number(data.cancel || 0)],
				['Total', Number(data.total || 0)]
			].forEach(function (item) {
				var cell = document.createElement('span');
				var label = document.createElement('small');
				label.textContent = item[0];
				var value = document.createElement('strong');
				value.textContent = String(item[1]);
				cell.appendChild(label);
				cell.appendChild(value);
				footer.appendChild(cell);
			});
			box.appendChild(footer);
		}

		box.hidden = false;
		activeTarget = target;
		positionPopover(target, box);
	}

	function renderSteadfastPopover(box, data) {
		box.innerHTML = '';
		box.classList.add('is-steadfast');
		var profile = data.profile || {};
		var header = document.createElement('div');
		header.className = 'eilmo-cf-courier-breakdown__header is-' + String(data.band || 'unknown');
		var title = document.createElement('strong'); title.textContent = 'STEADFAST';
		var badge = document.createElement('span');
		badge.className = 'eilmo-cf-courier-breakdown__badge is-' + String(data.band || 'unknown');
		badge.textContent = ({trusted: 'Trusted', review: 'Review', high: 'High Risk', critical: 'Critical'})[data.band] || 'No History';
		header.appendChild(title); header.appendChild(badge); box.appendChild(header);
		var body = document.createElement('div'); body.className = 'eilmo-cf-courier-breakdown__body';
		function row(label, value) {
			var item = document.createElement('div'); item.className = 'eilmo-cf-courier-breakdown__row';
			var name = document.createElement('span'); name.textContent = label;
			var detail = document.createElement('strong'); detail.textContent = String(value);
			item.appendChild(name); item.appendChild(detail); body.appendChild(item);
		}
		row('Delivery Ratio', profile.available ? formatRatio(profile.delivery_ratio) + '%' : 'No history');
		row('Cancellation Ratio', profile.available ? formatRatio(profile.cancellation_ratio) + '%' : 'No history');
		var volumes = {none: 'None', low: 'Low', medium: 'Medium', high: 'High', very_high: 'Very high'};
		var volume = volumes[profile.volume_band] || 'None';
		row('Customer Volume', profile.volume_range ? volume + ' (' + profile.volume_range + ')' : volume);
		if (profile.delivered_count !== null && profile.delivered_count !== undefined) { row('Delivered Parcels', profile.delivered_count); }
		if (profile.cancelled_count !== null && profile.cancelled_count !== undefined) { row('Cancelled Parcels', profile.cancelled_count); }
		var reports = profile.fraud_reports !== undefined ? profile.fraud_reports : profile.total_reports;
		if (reports !== null && reports !== undefined) { row('Fraud Reports (all merchants)', Number(reports)); }
		if (Number(reports) > 0 && profile.fraud_categories && typeof profile.fraud_categories === 'object') {
			var details = Object.keys(profile.fraud_categories).map(function (key) { return key.replace(/_/g, ' ') + ': ' + String(profile.fraud_categories[key]); }).join(', ');
			if (details) { row('Report details', details); }
		}
		if (Array.isArray(profile.fraud_keywords) && profile.fraud_keywords.length) { row('Fraud Keywords', profile.fraud_keywords.join(', ')); }
		if (Array.isArray(profile.fraud_details) && profile.fraud_details.length) { row('Fraud Details', profile.fraud_details.join('; ')); }
		if (typeof profile.reported_by_you === 'boolean') { row('Reported by Your Store', profile.reported_by_you ? 'Yes' : 'No'); }
		if (Number(data.checked_at || 0) > 0) { row('Checked', new Date(Number(data.checked_at) * 1000).toLocaleString()); }
		box.appendChild(body);
		var store = data.store || {};
		var section = document.createElement('div'); section.className = 'eilmo-cf-courier-breakdown__store';
		var heading = document.createElement('strong'); heading.textContent = 'Customer on This Store · ' + Number(store.total || 0) + ' orders'; section.appendChild(heading);
		var stats = document.createElement('div'); stats.className = 'eilmo-cf-courier-breakdown__store-stats';
		[['Completed', store.success], ['Unsuccessful', store.cancel], ['Open', store.open], ['Success', Number(store.resolved_total || 0) > 0 ? formatRatio(store.ratio) + '%' : '—']].forEach(function (pair) {
			var item = document.createElement('span'); var name = document.createElement('small'); name.textContent = pair[0];
			var value = document.createElement('strong'); value.textContent = String(pair[1] || 0);
			item.appendChild(name); item.appendChild(value); stats.appendChild(item);
		});
		section.appendChild(stats); box.appendChild(section);
	}

	function positionPopover(target, box) {
		if (!target || !box || box.hidden) {
			return;
		}

		var rect = target.getBoundingClientRect();
		var margin = 12;
		var width = box.offsetWidth || 340;
		var height = box.offsetHeight || 260;
		var left = rect.left + (rect.width / 2) - (width / 2);
		left = Math.max(margin, Math.min(left, window.innerWidth - width - margin));

		var below = rect.bottom + 10;
		var above = rect.top - height - 10;
		var placeBelow = below + height <= window.innerHeight - margin || above < margin;
		var top = placeBelow ? below : above;

		box.style.left = Math.round(left) + 'px';
		box.style.top = Math.max(margin, Math.round(top)) + 'px';
		box.setAttribute('data-placement', placeBelow ? 'bottom' : 'top');
	}

	function hidePopover(delay) {
		if (hideTimer) {
			window.clearTimeout(hideTimer);
		}
		hideTimer = window.setTimeout(function () {
			if (popover) {
				popover.hidden = true;
			}
			activeTarget = null;
		}, Number(delay || 0));
	}

	document.addEventListener('mouseover', function (event) {
		var target = event.target instanceof Element
			? event.target.closest('[data-eilmo-abandoned-courier-breakdown]')
			: null;
		if (target instanceof HTMLElement) {
			if (hideTimer) {
				window.clearTimeout(hideTimer);
				hideTimer = null;
			}
			renderPopover(target);
		}
	});

	document.addEventListener('mouseout', function (event) {
		var target = event.target instanceof Element
			? event.target.closest('[data-eilmo-abandoned-courier-breakdown]')
			: null;
		if (target instanceof HTMLElement && !target.contains(event.relatedTarget)) {
			hidePopover(120);
		}
	});

	document.addEventListener('focusin', function (event) {
		var target = event.target instanceof Element
			? event.target.closest('[data-eilmo-abandoned-courier-breakdown]')
			: null;
		if (target instanceof HTMLElement) {
			renderPopover(target);
		}
	});

	document.addEventListener('focusout', function (event) {
		var target = event.target instanceof Element
			? event.target.closest('[data-eilmo-abandoned-courier-breakdown]')
			: null;
		if (target instanceof HTMLElement) {
			hidePopover(120);
		}
	});

	window.addEventListener('resize', function () {
		if (activeTarget && popover && !popover.hidden) {
			positionPopover(activeTarget, popover);
		}
	});

	window.addEventListener('scroll', function () {
		if (activeTarget && popover && !popover.hidden) {
			positionPopover(activeTarget, popover);
		}
	}, true);

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && popover && !popover.hidden) {
			popover.hidden = true;
			activeTarget = null;
		}
	});
}());
