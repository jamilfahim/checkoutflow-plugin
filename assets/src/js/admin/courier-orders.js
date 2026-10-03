(function () {
	'use strict';

	var config = window.eilmoCfCourierOrders || null;

	if (!config) {
		return;
	}

	var cells = Array.prototype.slice.call(
		document.querySelectorAll('[data-eilmo-courier]')
	);

	var modal = document.querySelector('[data-eilmo-courier-modal]');
	var form = modal ? modal.querySelector('[data-eilmo-courier-form]') : null;
	var loading = modal ? modal.querySelector('[data-eilmo-courier-loading]') : null;
	var modalError = modal ? modal.querySelector('[data-eilmo-courier-modal-error]') : null;
	var subtitle = modal ? modal.querySelector('[data-eilmo-courier-modal-subtitle]') : null;
	var submitButton = modal ? modal.querySelector('[data-eilmo-courier-submit]') : null;
	var referenceLabel = modal ? modal.querySelector('[data-eilmo-reference-label]') : null;
	var noteLabel = modal ? modal.querySelector('[data-eilmo-note-label]') : null;
	var breakdownPopover = null;
	var breakdownHideTimer = null;

	var standaloneWidthTimer = null;

	/**
	 * Standalone Orders layout (Order Table Management OFF).
	 * ONLY Courier Performance stays fixed at 220px. All other columns are
	 * returned to auto sizing so WooCommerce can distribute the full width.
	 */
	function lockStandaloneOrderTableWidths() {
		var tables = document.querySelectorAll(
			'.wp-list-table.orders, table.wc-orders-list-table, .wp-list-table.table-view-list.orders'
		);
		var standalone = false;

		tables.forEach(function (table) {
			// Quick Order Management arranges enabled cells into responsive order rows.
			if (
				document.body.classList.contains('eilmo-cf-order-workflow') ||
				table.querySelector('.column-eilmo_cf_verify_customer') ||
				table.querySelector('.column-eilmo_cf_verify_products') ||
				table.querySelector('.column-eilmo_cf_verify_status')
			) {
				return;
			}

			standalone = true;
			table.style.setProperty('table-layout', 'auto');
			table.style.setProperty('width', '100%');
			table.style.setProperty('min-width', '100%');
			table.style.setProperty('max-width', '100%');

			// Clear managed-mode/stale inline locks from every native cell first.
			table.querySelectorAll('th, td').forEach(function (cell) {
				cell.style.setProperty('width', 'auto');
				cell.style.setProperty('min-width', '0');
				cell.style.setProperty('max-width', 'none');
			});

			// The only fixed-width column in standalone/native mode.
			table.querySelectorAll('th.column-eilmo_cf_courier_success, td.column-eilmo_cf_courier_success').forEach(function (cell) {
				cell.style.setProperty('width', '220px');
				cell.style.setProperty('min-width', '220px');
				cell.style.setProperty('max-width', '220px');
			});
		});

		document.body.classList.toggle('eilmo-cf-qom-disabled', standalone);
	}

	function scheduleStandaloneWidthLock() {
		if (standaloneWidthTimer) {
			window.clearTimeout(standaloneWidthTimer);
		}
		standaloneWidthTimer = window.setTimeout(function () {
			standaloneWidthTimer = null;
			lockStandaloneOrderTableWidths();
		}, 40);
	}

	var state = {
		cell: null,
		button: null,
		provider: '',
		orderId: 0,
		defaults: null
	};

	function post(data) {
		var body = new URLSearchParams();

		Object.keys(data).forEach(function (key) {
			var value = data[key];

			if (Array.isArray(value)) {
				value.forEach(function (item) {
					body.append(
						key + '[]',
						String(item)
					);
				});

				return;
			}

			body.append(
				key,
				String(value)
			);
		});

		return window.fetch(
			config.ajaxUrl,
			{
				method: 'POST',
				credentials: 'same-origin',

				headers: {
					'Content-Type':
						'application/x-www-form-urlencoded; charset=UTF-8'
				},

				body:
					body.toString()
			}
		).then(function (response) {
			return response.text().then(function (responseText) {
				try {
					return JSON.parse(responseText);
				} catch (error) {
					throw new Error(config.i18n.apiError || 'API error');
				}
			});
		});
	}

	function getErrorMessage(response) {
		return response &&
			response.data &&
			response.data.message
				? String(
					response.data.message
				)
				: config.i18n.unavailable;
	}

	function formatStat(stat) {
		if (!stat) {
			return config.i18n.unavailable;
		}
		if (stat.provider === 'steadfast' && Object.prototype.hasOwnProperty.call(stat, 'delivery_ratio')) {
			return stat.volume_band === 'none' ? (config.i18n.noHistory || 'No history') :
				String(Number(stat.delivery_ratio || 0)) + '% Delivered';
		}

		if (!stat.available) {
			if (stat.state === 'not_selected') {
				return config.i18n.noData || 'No data';
			}

			return config.i18n.noHistory ||
				config.i18n.unavailable;
		}

		var success =
			Number(
				stat.success || 0
			);

		var total =
			Number(
				stat.total || 0
			);

		var ratio =
			Number(
				stat.ratio || 0
			);

		var roundedRatio =
			Number.isInteger(
				ratio
			)
				? String(
					ratio
				)
				: ratio.toFixed(
					1
				);

		return (
			success +
			'/' +
			total +
			' (' +
			roundedRatio +
			'%)'
		);
	}

	function riskConfig() {
		var risk = config.risk || {};
		var trustedRate = Number(risk.trustedRate);
		var advanceRate = Number(risk.advanceRate);
		var blockRate = Number(risk.blockRate);
		var minimumOrders = Number(risk.minimumOrders);

		return {
			trustedRate: Number.isFinite(trustedRate) ? trustedRate : 95,
			advanceRate: Number.isFinite(advanceRate) ? advanceRate : 90,
			blockRate: Number.isFinite(blockRate) ? blockRate : 40,
			minimumOrders: Math.max(1, Number.isFinite(minimumOrders) ? minimumOrders : 5)
		};
	}

	function getRiskBand(total, ratio) {
		var risk = riskConfig();

		if (total <= 0) {
			return 'unknown';
		}

		if (total >= risk.minimumOrders && ratio < risk.blockRate) {
			return 'critical';
		}

		if (ratio < risk.advanceRate) {
			return 'high';
		}

		if (ratio < risk.trustedRate) {
			return 'review';
		}

		return 'trusted';
	}

	function getSteadfastBand(stat) {
		if (!stat || stat.volume_band === 'none' || !stat.available) { return 'unknown'; }
		var ratio = Number(stat.delivery_ratio || 0);
		var risk = riskConfig();
		var bandMinimum = {medium: 6, high: 21, very_high: 201};
		var enoughHistory = Number(bandMinimum[stat.volume_band] || 0) >= risk.minimumOrders;
		if (enoughHistory && ratio < risk.blockRate) { return 'critical'; }
		if (ratio < risk.advanceRate) { return 'high'; }
		if (ratio < risk.trustedRate) { return 'review'; }
		return 'trusted';
	}

	/**
	 * Apply success ratio state class.
	 *
	 * @param {HTMLElement} target Stat element.
	 * @param {Object|null} stat   Courier statistics.
	 *
	 * @return {void}
	 */
	function applyRatioClass(
		target,
		stat
	) {
		if (!target) {
			return;
		}

		target.classList.remove(
			'is-success-ratio-trusted',
			'is-success-ratio-review',
			'is-success-ratio-high',
			'is-success-ratio-critical',
			'is-success-ratio-neutral'
		);

		if (
			!stat ||
			!stat.available
		) {
			target.classList.add(
				'is-success-ratio-neutral'
			);

			return;
		}

		target.classList.add(
			'is-success-ratio-' +
				(stat.provider === 'steadfast' ? getSteadfastBand(stat) : getRiskBand(
					Number(stat.total || 0),
					Number(stat.ratio || 0)
				))
		);
	}

	function riskLabel(band) {
		var labels = {
			trusted: config.i18n.trusted || 'Trusted',
			review: config.i18n.review || 'Review',
			high: config.i18n.highRisk || 'High Risk',
			critical: config.i18n.critical || 'Critical',
			unknown: config.i18n.noHistory || 'No History',
			unavailable: config.i18n.unavailable || 'Unavailable'
		};

		return labels[band] || labels.unavailable;
	}

	function getProviderEntries(result) {
		if (!result || result.error || typeof result !== 'object') {
			return [];
		}

		return Object.keys(result)
			.filter(function (key) {
				if (!key || key.charAt(0) === '_') {
					return false;
				}
				var stat = result[key];
				return stat && typeof stat === 'object' && (
					Object.prototype.hasOwnProperty.call(stat, 'total') ||
					Object.prototype.hasOwnProperty.call(stat, 'success') ||
					Object.prototype.hasOwnProperty.call(stat, 'cancel') ||
					Object.prototype.hasOwnProperty.call(stat, 'ratio') ||
					Object.prototype.hasOwnProperty.call(stat, 'available')
				);
			})
			.map(function (key) {
				return { key: key, stat: result[key] };
			});
	}

	function combineProviderStats(result) {
		var total = 0;
		var success = 0;
		var cancel = 0;
		var entries = getProviderEntries(result);

		entries.forEach(function (entry) {
			var stat = entry.stat || {};
			total += Math.max(0, Number(stat.total || 0));
			success += Math.max(0, Number(stat.success || 0));
			cancel += Math.max(0, Number(stat.cancel || 0));
		});

		if (total <= 0 && (success > 0 || cancel > 0)) {
			total = success + cancel;
		}

		return {
			available: total > 0,
			total: total,
			success: success,
			cancel: cancel,
			ratio: total > 0 ? Math.max(0, Math.min(100, (success / total) * 100)) : 0,
			state: total > 0 ? 'available' : 'no_history'
		};
	}

	function bdSteadfastRateOnly(stat) {
		if (!stat || stat.rate_only !== true || !stat.parcel_range ||
			!Number.isFinite(Number(stat.ratio))) {
			return '';
		}
		var bands = { low: 'Low', medium: 'Medium', high: 'High', very_high: 'Very High' };
		var band = bands[String(stat.volume_band || '')];
		return band ? String(stat.parcel_range) + ' (' + band + ')' : '';
	}

	function bdSteadfastRateOnlyEntry(result) {
		return result && result.steadfast && bdSteadfastRateOnly(result.steadfast)
			? result.steadfast : null;
	}

	function formatBdSteadfastRateOnly(stat) {
		var ratio = Number(stat.ratio || 0);
		return bdSteadfastRateOnly(stat) + ' ' + (Number.isInteger(ratio) ? String(ratio) : ratio.toFixed(1)) + '%';
	}

	function bdSteadfastRateOnlyBand(stat) {
		return getSteadfastBand({
			available: true,
			volume_band: stat.volume_band,
			delivery_ratio: stat.ratio
		});
	}

	function providerLabel(key, stat) {
		if (stat && stat.label) {
			return String(stat.label);
		}

		var known = {
			steadfast: 'Steadfast',
			pathao: 'Pathao',
			redx: 'REDX',
			paperfly: 'Paperfly',
			ecourier: 'eCourier'
		};
		if (known[key]) {
			return known[key];
		}

		return String(key || '')
			.replace(/[_-]+/g, ' ')
			.replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
	}

	function ensureBreakdownPopover() {
		if (breakdownPopover) {
			return breakdownPopover;
		}

		breakdownPopover = document.createElement('div');
		breakdownPopover.className = 'eilmo-cf-courier-breakdown';
		breakdownPopover.setAttribute('data-eilmo-courier-breakdown', '');
		breakdownPopover.setAttribute('role', 'dialog');
		breakdownPopover.setAttribute('aria-label', config.i18n.courierBreakdown || 'Courier breakdown');
		breakdownPopover.hidden = true;

		breakdownPopover.addEventListener('mouseenter', function () {
			if (breakdownHideTimer) {
				window.clearTimeout(breakdownHideTimer);
				breakdownHideTimer = null;
			}
		});

		breakdownPopover.addEventListener('mouseleave', function () {
			hideBreakdownPopover(90);
		});

		document.body.appendChild(breakdownPopover);
		return breakdownPopover;
	}

	function appendBreakdownStatRow(container, label, value, ratio, available, bandOverride) {
		var row = document.createElement('div');
		row.className = 'eilmo-cf-courier-breakdown__row' + (available ? '' : ' is-empty');

		var name = document.createElement('span');
		name.className = 'eilmo-cf-courier-breakdown__name';
		name.textContent = label;

		var detail = document.createElement('span');
		detail.className = 'eilmo-cf-courier-breakdown__detail';

		var count = document.createElement('strong');
		count.textContent = value;
		detail.appendChild(count);

		if (available) {
			var badge = document.createElement('span');
			badge.className = 'eilmo-cf-courier-breakdown__badge is-' + (bandOverride || getRiskBand(
				Number((ratio && ratio.total) || 0),
				Number((ratio && ratio.ratio) || 0)
			));
			badge.textContent = Number.isInteger(Number(ratio.ratio || 0))
				? String(Number(ratio.ratio || 0)) + '%'
				: Number(ratio.ratio || 0).toFixed(1) + '%';
			detail.appendChild(badge);
		}

		row.appendChild(name);
		row.appendChild(detail);
		container.appendChild(row);
	}

	function renderSteadfastPopover(popover, stat, result) {
		popover.innerHTML = '';
		popover.classList.add('is-steadfast');
		var band = getSteadfastBand(stat);
		popover.dataset.riskBand = band;
		var header = document.createElement('div');
		header.className = 'eilmo-cf-courier-breakdown__header is-' + band;
		var title = document.createElement('strong');
		title.textContent = 'STEADFAST';
		var badge = document.createElement('span');
		badge.className = 'eilmo-cf-courier-breakdown__badge is-' + band;
		badge.textContent = riskLabel(band);
		header.appendChild(title);
		header.appendChild(badge);
		popover.appendChild(header);
		var body = document.createElement('div');
		body.className = 'eilmo-cf-courier-breakdown__body';
		function row(label, value) {
			var item = document.createElement('div');
			item.className = 'eilmo-cf-courier-breakdown__row';
			var name = document.createElement('span');
			name.textContent = label;
			var detail = document.createElement('strong');
			detail.textContent = String(value);
			item.appendChild(name);
			item.appendChild(detail);
			body.appendChild(item);
		}
		row('Delivery Ratio', stat.available ? String(Number(stat.delivery_ratio || 0)) + '%' : 'No history');
		row('Cancellation Ratio', stat.available ? String(Number(stat.cancellation_ratio || 0)) + '%' : 'No history');
		var volumes = {none: 'None', low: 'Low (1–5)', medium: 'Medium (6–20)', high: 'High (21–200)', very_high: 'Very high (200+)'};
		row('Customer Volume', volumes[stat.volume_band] || 'None');
		row('Fraud Reports (all merchants)', Number(stat.total_reports || 0));
		if (Number(stat.total_reports || 0) > 0 && stat.fraud_categories && typeof stat.fraud_categories === 'object') {
			var details = Object.keys(stat.fraud_categories).map(function (key) {
				return key.replace(/_/g, ' ') + ': ' + String(stat.fraud_categories[key]);
			}).join(', ');
			if (details) { row('Report details', details); }
		}
		if (result && result._meta && result._meta.checkedAt) {
			row('Checked', new Date(Number(result._meta.checkedAt) * 1000).toLocaleString());
		}
		popover.appendChild(body);
		var store = result && result._storeHistory;
		if (config.showStoreHistory && store) {
			var section = document.createElement('div');
			section.className = 'eilmo-cf-courier-breakdown__store';
			var heading = document.createElement('strong');
			heading.textContent = 'Customer on This Store · ' + Number(store.total || 0) + ' orders';
			section.appendChild(heading);
			var stats = document.createElement('div');
			stats.className = 'eilmo-cf-courier-breakdown__store-stats';
			[['Completed', store.success], ['Unsuccessful', store.cancel], ['Open', store.open], ['Success', Number(store.resolved_total || 0) > 0 ? String(Number(store.ratio || 0)) + '%' : '—']].forEach(function (pair) {
				var item = document.createElement('span');
				var name = document.createElement('small'); name.textContent = pair[0];
				var value = document.createElement('strong'); value.textContent = String(pair[1] || 0);
				item.appendChild(name); item.appendChild(value); stats.appendChild(item);
			});
			section.appendChild(stats);
			popover.appendChild(section);
		}
	}

	function renderBreakdownPopover(target, result) {
		var popover = ensureBreakdownPopover();
		popover.classList.remove('is-steadfast');
		delete popover.dataset.riskBand;
		var combined = combineProviderStats(result || {});
		var entries = getProviderEntries(result || {});
		var isAllCouriers = config.successProvider === 'bdcourier';
		var selectedProvider = String(config.successProvider || 'steadfast');
		var selectedEntry = null;

		if (!isAllCouriers) {
			selectedEntry = entries.find(function (entry) {
				return String(entry.key || '') === selectedProvider;
			}) || (entries.length === 1 ? entries[0] : null);

			if (selectedEntry) {
				entries = [selectedEntry];
			}
		}

		var displayStat = !isAllCouriers && selectedEntry
			? (selectedEntry.stat || {})
			: combined;
		if (!isAllCouriers && selectedEntry && selectedEntry.stat && selectedEntry.stat.provider === 'steadfast') {
			renderSteadfastPopover(popover, selectedEntry.stat, result);
			popover.hidden = false;
			positionBreakdownPopover(target, popover);
			return;
		}
		popover.innerHTML = '';

		var header = document.createElement('div');
		header.className = 'eilmo-cf-courier-breakdown__header';

		var titleWrap = document.createElement('div');
		var eyebrow = document.createElement('span');
		eyebrow.className = 'eilmo-cf-courier-breakdown__eyebrow';
		eyebrow.textContent = isAllCouriers
			? (config.i18n.allCouriers || 'All Couriers')
			: providerLabel(selectedEntry ? selectedEntry.key : selectedProvider, selectedEntry ? selectedEntry.stat : null);

		var title = document.createElement('strong');
		title.className = 'eilmo-cf-courier-breakdown__title';
		title.textContent = isAllCouriers
			? (config.i18n.courierBreakdown || 'Courier breakdown')
			: (config.i18n.courierHistory || 'Courier history');

		titleWrap.appendChild(eyebrow);
		titleWrap.appendChild(title);

		var total = document.createElement('div');
		total.className = 'eilmo-cf-courier-breakdown__total';
		var rateOnlyEntry = isAllCouriers && Number(combined.total || 0) <= 0
			? bdSteadfastRateOnlyEntry(result) : null;
		if (rateOnlyEntry) {
			total.classList.add('is-' + bdSteadfastRateOnlyBand(rateOnlyEntry));
		}
		total.textContent = rateOnlyEntry ? formatBdSteadfastRateOnly(rateOnlyEntry) : formatStat(displayStat);

		header.appendChild(titleWrap);
		header.appendChild(total);
		popover.appendChild(header);

		var storeHistory = result && result._storeHistory && typeof result._storeHistory === 'object'
			? result._storeHistory
			: null;

		if (config.showStoreHistory && storeHistory) {
			var storeSection = document.createElement('div');
			storeSection.className = 'eilmo-cf-courier-breakdown__store';

			var storeHeader = document.createElement('div');
			storeHeader.className = 'eilmo-cf-courier-breakdown__store-header';

			var storeTitleWrap = document.createElement('div');
			var storeEyebrow = document.createElement('span');
			storeEyebrow.className = 'eilmo-cf-courier-breakdown__eyebrow';
			storeEyebrow.textContent = config.i18n.customerOnStore || 'Customer on This Store';
			var storeTitle = document.createElement('strong');
			storeTitle.className = 'eilmo-cf-courier-breakdown__store-title';
			storeTitle.textContent = config.i18n.storeOrderHistory || 'Store order history';
			storeTitleWrap.appendChild(storeEyebrow);
			storeTitleWrap.appendChild(storeTitle);

			var storeCount = document.createElement('span');
			storeCount.className = 'eilmo-cf-courier-breakdown__store-count';
			storeCount.textContent = String(Number(storeHistory.total || 0)) + ' ' + (config.i18n.ordersLabel || 'orders');

			storeHeader.appendChild(storeTitleWrap);
			storeHeader.appendChild(storeCount);
			storeSection.appendChild(storeHeader);

			if (Number(storeHistory.total || 0) > 0) {
				var storeStats = document.createElement('div');
				storeStats.className = 'eilmo-cf-courier-breakdown__store-stats';

				[
					[config.i18n.completedLabel || 'Completed', Number(storeHistory.success || 0)],
					[config.i18n.unsuccessfulLabel || 'Unsuccessful', Number(storeHistory.cancel || 0)],
					[config.i18n.openLabel || 'Open', Number(storeHistory.open || 0)],
					[config.i18n.successLabel || 'Success', (Number(storeHistory.resolved_total || 0) > 0 ? (Math.round(Number(storeHistory.ratio || 0) * 10) / 10) + '%' : '—')]
				].forEach(function (item) {
					var stat = document.createElement('span');
					var label = document.createElement('small');
					var value = document.createElement('strong');
					label.textContent = item[0];
					value.textContent = String(item[1]);
					stat.appendChild(label);
					stat.appendChild(value);
					storeStats.appendChild(stat);
				});

				storeSection.appendChild(storeStats);
			} else {
				var storeEmpty = document.createElement('div');
				storeEmpty.className = 'eilmo-cf-courier-breakdown__store-empty';
				storeEmpty.textContent = config.i18n.noStoreHistory || 'No orders found on this store.';
				storeSection.appendChild(storeEmpty);
			}

			popover.appendChild(storeSection);
		}

		var body = document.createElement('div');
		body.className = 'eilmo-cf-courier-breakdown__body';

		if (!entries.length) {
			var empty = document.createElement('div');
			empty.className = 'eilmo-cf-courier-breakdown__empty';
			empty.textContent = config.i18n.noHistory || 'No history';
			body.appendChild(empty);
		} else {
			entries.forEach(function (entry) {
				var stat = entry.stat || {};
				var rateOnly = entry.key === 'steadfast' ? bdSteadfastRateOnly(stat) : '';
				var available = Boolean(rateOnly || (stat.available && Number(stat.total || 0) > 0));
				appendBreakdownStatRow(
					body,
					providerLabel(entry.key, stat),
					rateOnly || (available ? String(Number(stat.success || 0)) + '/' + String(Number(stat.total || 0)) : (config.i18n.noHistory || 'No history')),
					stat,
					available,
					rateOnly ? bdSteadfastRateOnlyBand(stat) : ''
				);
			});
		}

		popover.appendChild(body);

		if (displayStat.available) {
			var footer = document.createElement('div');
			footer.className = 'eilmo-cf-courier-breakdown__footer';

			var delivered = document.createElement('span');
			delivered.innerHTML = '<small>Delivered</small><strong></strong>';
			delivered.querySelector('strong').textContent = String(Number(displayStat.success || 0));

			var cancelled = document.createElement('span');
			cancelled.innerHTML = '<small>Cancelled</small><strong></strong>';
			cancelled.querySelector('strong').textContent = String(Number(displayStat.cancel || 0));

			var totalOrders = document.createElement('span');
			totalOrders.innerHTML = '<small>Total</small><strong></strong>';
			totalOrders.querySelector('strong').textContent = String(Number(displayStat.total || 0));

			footer.appendChild(delivered);
			footer.appendChild(cancelled);
			footer.appendChild(totalOrders);
			popover.appendChild(footer);
		}

		popover.hidden = false;
		positionBreakdownPopover(target, popover);
	}

	function positionBreakdownPopover(target, popover) {
		if (!target || !popover || popover.hidden) {
			return;
		}

		var rect = target.getBoundingClientRect();
		var width = Math.min(390, Math.max(300, popover.offsetWidth || 340));
		var viewportPadding = 12;
		var left = rect.left + (rect.width / 2) - (width / 2);
		left = Math.max(viewportPadding, Math.min(left, window.innerWidth - width - viewportPadding));

		popover.style.width = width + 'px';
		popover.style.left = left + 'px';

		var height = popover.offsetHeight || 260;
		var belowTop = rect.bottom + 10;
		var aboveTop = rect.top - height - 10;
		var top = belowTop;
		var placement = 'bottom';

		if (belowTop + height > window.innerHeight - viewportPadding && aboveTop >= viewportPadding) {
			top = aboveTop;
			placement = 'top';
		}

		popover.style.top = Math.max(viewportPadding, top) + 'px';
		popover.setAttribute('data-placement', placement);
	}

	function showBreakdownPopover(target) {
		if (!target || !target._eilmoCourierBreakdownResult) {
			return;
		}
		if (breakdownHideTimer) {
			window.clearTimeout(breakdownHideTimer);
			breakdownHideTimer = null;
		}
		renderBreakdownPopover(target, target._eilmoCourierBreakdownResult);
	}

	function hideBreakdownPopover(delay) {
		if (!breakdownPopover) {
			return;
		}
		if (breakdownHideTimer) {
			window.clearTimeout(breakdownHideTimer);
		}
		breakdownHideTimer = window.setTimeout(function () {
			breakdownPopover.hidden = true;
			breakdownHideTimer = null;
		}, Number(delay || 0));
	}

	function bindBreakdownTarget(target) {
		if (!target || target.dataset.eilmoBreakdownBound === 'yes') {
			return;
		}
		target.dataset.eilmoBreakdownBound = 'yes';
		target.addEventListener('mouseenter', function () { showBreakdownPopover(target); });
		target.addEventListener('mouseleave', function () { hideBreakdownPopover(120); });
		target.addEventListener('focus', function () { showBreakdownPopover(target); });
		target.addEventListener('blur', function () { hideBreakdownPopover(120); });
		target.addEventListener('click', function (event) {
			event.preventDefault();
			showBreakdownPopover(target);
		});
	}

	function setPerformance(cell, result) {
		var wrapper = cell.querySelector('[data-eilmo-courier-risk]');
		var bar = cell.querySelector('[data-eilmo-courier-risk-bar]');
		var status = cell.querySelector('[data-eilmo-courier-risk-status]');

		if (!wrapper || !bar || !status) {
			return;
		}

		var combined = result && !result.error
			? combineProviderStats(result)
			: { available: false, total: 0, success: 0, ratio: 0 };
		var steadfast = config.successProvider === 'steadfast' && result && result.steadfast && result.steadfast.provider === 'steadfast' ? result.steadfast : null;
		var total = Number(combined.total || 0);
		var ratio = steadfast ? Number(steadfast.delivery_ratio || 0) : Number(combined.ratio || 0);
		var band = steadfast ? getSteadfastBand(steadfast) : (total > 0 ? getRiskBand(total, ratio) : (result && result.error ? 'unavailable' : 'unknown'));

		wrapper.className = 'eilmo-cf-courier__risk is-' + band + (steadfast ? ' is-steadfast' : '');
		status.textContent = riskLabel(band);
		bar.setAttribute('aria-valuenow', String(Math.round(ratio * 10) / 10));
		bar.setAttribute('aria-valuetext', (Math.round(ratio * 10) / 10) + '% · ' + riskLabel(band));

		var filled = Math.max(0, Math.min(10, Math.round(ratio / 10)));

		Array.prototype.forEach.call(
			bar.querySelectorAll('.eilmo-cf-courier__risk-segment'),
			function (segment, index) {
				var neutral = 'unknown' === band || 'unavailable' === band;
				segment.classList.toggle('is-success', !neutral && index < filled);
				segment.classList.toggle('is-failure', !neutral && index >= filled);
				segment.classList.toggle('is-neutral', neutral);
			}
		);
	}

	function getStatsErrorLabel(result) {
		if (
			result &&
			result.code ===
				'eilmo_cf_courier_api_key_missing'
		) {
			return config.i18n.notConfigured ||
				'Not configured';
		}

		return config.i18n.apiError ||
			'API error';
	}

	function resetRatioClasses(target) {
		if (!target) {
			return;
		}
		target.classList.remove(
			'is-success-ratio-trusted',
			'is-success-ratio-review',
			'is-success-ratio-high',
			'is-success-ratio-critical',
			'is-success-ratio-neutral'
		);
	}

	function setStats(cell, result) {
		var isBdCourier = config.successProvider === 'bdcourier';
		var target = cell.querySelector(
			isBdCourier
				? '[data-eilmo-courier-stat="combined"]'
				: '[data-eilmo-courier-stat="steadfast"]'
		);

		if (target) {
			resetRatioClasses(target);
			target.removeAttribute('title');

			if (result && result.error) {
				target.textContent = getStatsErrorLabel(result);
				target._eilmoCourierBreakdownResult = null;
				target.setAttribute('aria-label', String(result.error || getStatsErrorLabel(result)));
				target.classList.add('is-success-ratio-neutral');
			} else if (isBdCourier) {
				var combined = combineProviderStats(result || {});
				var rateOnlyEntry = Number(combined.total || 0) <= 0 ? bdSteadfastRateOnlyEntry(result) : null;
				var displayText = rateOnlyEntry ? formatBdSteadfastRateOnly(rateOnlyEntry) : formatStat(combined);
				target.textContent = displayText;
				target._eilmoCourierBreakdownResult = result || {};
				target.setAttribute('aria-label', (config.i18n.courierBreakdown || 'Courier breakdown') + ': ' + displayText);
				bindBreakdownTarget(target);
				applyRatioClass(target, rateOnlyEntry ? {
					available: true,
					provider: 'steadfast',
					volume_band: rateOnlyEntry.volume_band,
					delivery_ratio: rateOnlyEntry.ratio
				} : combined);
			} else {
				var stat = result ? result.steadfast : null;
				target.textContent = formatStat(stat);
				target._eilmoCourierBreakdownResult = result || {};
				target.setAttribute('aria-label', (config.i18n.courierHistory || 'Courier history') + ': ' + formatStat(stat));
				bindBreakdownTarget(target);
				applyRatioClass(target, stat);
			}
		}

		setPerformance(cell, result);
		updateCheckControl(cell, result);
	}

	function checkedAgeLabel(timestamp) {
		var seconds = Math.max(0, Math.floor(Date.now() / 1000) - Number(timestamp || 0));
		if (seconds < 60) {
			return config.i18n.checkedNow || 'Checked just now';
		}
		if (seconds < 3600) {
			return 'Checked ' + Math.floor(seconds / 60) + 'm ago';
		}
		if (seconds < 86400) {
			return 'Checked ' + Math.floor(seconds / 3600) + 'h ago';
		}
		return 'Checked ' + Math.floor(seconds / 86400) + 'd ago';
	}

	function updateCheckControl(cell, result) {
		var button = cell.querySelector('[data-eilmo-courier-check]');
		var age = cell.querySelector('[data-eilmo-courier-cache-age]');
		if (!(button instanceof HTMLButtonElement)) {
			return;
		}

		var meta = result && result._meta ? result._meta : {};
		var hasData = Boolean(meta.hasCourierData);
		var stale = Boolean(meta.stale);
		button.hidden = false;
		button.textContent = hasData
			? (config.i18n.refreshHistory || 'Refresh Courier Data')
			: (config.i18n.checkHistory || 'Check Courier History');

		if (age instanceof HTMLElement) {
			age.hidden = !hasData || !Number(meta.checkedAt || 0);
			if (!age.hidden) {
				age.textContent = checkedAgeLabel(meta.checkedAt);
			}
		}
	}

	function setCellMessage(cell, message, isError) {
		var target = cell.querySelector('[data-eilmo-courier-message]');
		if (!(target instanceof HTMLElement)) {
			return;
		}
		target.textContent = message || '';
		target.className = 'eilmo-cf-courier__message' + (message ? (isError ? ' is-error' : ' is-success') : '');
	}

	function manualCheck(cell, button) {
		if (!(button instanceof HTMLButtonElement) || button.disabled) {
			return;
		}
		button.disabled = true;
		button.textContent = config.i18n.checkingHistory || 'Checking…';
		setCellMessage(cell, '', false);

		post({
			action: config.actions.manualStats,
			nonce: config.nonce,
			order_id: Number(cell.dataset.orderId || 0)
		})
			.then(function (response) {
				if (!response || !response.success || !response.data || !response.data.result) {
					throw new Error(getErrorMessage(response));
				}
				setStats(cell, response.data.result);
				setCellMessage(cell, config.i18n.checkedNow || 'Checked just now', false);
			})
			.catch(function (error) {
				button.hidden = false;
				button.textContent = config.i18n.checkHistory || 'Check Courier History';
				setCellMessage(cell, error && error.message ? error.message : config.i18n.apiError, true);
			})
			.finally(function () {
				button.disabled = false;
			});
	}

	function loadStats() {
		var orderIds =
			cells
				.filter(function (cell) {
					return (
						cell.dataset.hasPhone ===
							'yes' &&
						cell.querySelector(
							'[data-eilmo-courier-stat]'
						)
					);
				})
				.map(function (cell) {
					return Number(
						cell.dataset.orderId ||
							0
					);
				})
				.filter(Boolean);

		if (!orderIds.length) {
			return;
		}

		post({
			action:
				config.actions.stats,

			nonce:
				config.nonce,

			order_ids:
				orderIds
		})
			.then(function (response) {
				var orders =
					response &&
					response.success &&
					response.data &&
					response.data.orders
						? response.data.orders
						: {};

				cells.forEach(function (cell) {
					var id =
						String(
							cell.dataset.orderId ||
								''
						);

					if (id) {
						setStats(
							cell,
							orders[id] ||
								null
						);
					}
				});
			})
			.catch(function () {
				cells.forEach(function (cell) {
					setStats(
						cell,
						null
					);
				});
			});
	}

	function formatDeliveryStatus(status) {
		status =
			String(
				status || ''
			)
				.trim()
				.toLowerCase();

		if (!status) {
			return '';
		}

		var labels = {
			in_review:
				'In Review',

			pending:
				'Pending',

			delivered_approval_pending:
				'Delivered Approval Pending',

			partial_delivered_approval_pending:
				'Partial Delivered Approval Pending',

			cancelled_approval_pending:
				'Cancelled Approval Pending',

			unknown_approval_pending:
				'Unknown Approval Pending',

			delivered:
				'Delivered',

			partial_delivered:
				'Partial Delivered',

			cancelled:
				'Cancelled',

			hold:
				'On Hold',

			unknown:
				'Unknown'
		};

		if (
			labels[
				status
			]
		) {
			return labels[
				status
			];
		}

		return status
			.split(
				'_'
			)
			.filter(
				Boolean
			)
			.map(function (part) {
				return (
					part
						.charAt(0)
						.toUpperCase() +
					part.slice(1)
				);
			})
			.join(
				' '
			);
	}

	function setSteadfastStatus(
		cell,
		booking
	) {
		if (
			!cell ||
			!booking ||
			!booking.sent
		) {
			return;
		}

		var button =
			cell.querySelector(
				'[data-eilmo-courier-send="steadfast"]'
			);

		if (!button) {
			return;
		}

		var status =
			String(
				booking.delivery_status ||
					''
			);

		var label =
			formatDeliveryStatus(
				status
			);

		if (label) {
			button.textContent =
				label;

			cell.dataset.steadfastStatus =
				status;
		}
	}

	function loadDeliveryStatuses() {
		if (
			!config.actions ||
			!config.actions.status
		) {
			return;
		}

		var orderIds =
			cells
				.filter(function (cell) {
					return (
						cell.dataset
							.steadfastSent ===
						'yes'
					);
				})
				.map(function (cell) {
					return Number(
						cell.dataset.orderId ||
							0
					);
				})
				.filter(
					Boolean
				);

		if (!orderIds.length) {
			return;
		}

		post({
			action:
				config.actions.status,

			nonce:
				config.nonce,

			order_ids:
				orderIds
		})
			.then(function (response) {
				var orders =
					response &&
					response.success &&
					response.data &&
					response.data.orders
						? response.data.orders
						: {};

				cells.forEach(function (cell) {
					var id =
						String(
							cell.dataset.orderId ||
								''
						);

					var result =
						id
							? orders[id]
							: null;

					if (
						result &&
						result.steadfast
					) {
						setSteadfastStatus(
							cell,
							result.steadfast
						);
					}
				});
			})
			.catch(function () {
				/*
				 * Keep last saved status.
				 */
			});
	}

	function getOtherProvider(provider) {
		return provider ===
			'steadfast'
				? 'pathao'
				: 'steadfast';
	}

	function sentLabel(provider) {
		return provider ===
			'steadfast'
				? config.i18n.sentSteadfast
				: config.i18n.sentPathao;
	}

	function sendLabel(provider) {
		return provider ===
			'steadfast'
				? config.i18n.sendSteadfast
				: config.i18n.sendPathao;
	}

	function reviewLabel(provider) {
		return provider ===
			'steadfast'
				? config.i18n.editSteadfast
				: config.i18n.editPathao;
	}

	function appendReference(
		button,
		booking
	) {
		if (
			!button ||
			!booking
		) {
			return;
		}

		var value =
			booking.tracking_code ||
			booking.reference ||
			'';

		if (!value) {
			return;
		}

		var block = button.closest('[data-eilmo-courier-action-block]');
		var oldReference = block
			? block.querySelector('.eilmo-cf-courier__reference')
			: null;

		var reference = oldReference || document.createElement('small');
		reference.className = 'eilmo-cf-courier__reference';
		reference.textContent = String(value);

		if (!oldReference) {
			if (block) {
				block.appendChild(reference);
			} else {
				button.insertAdjacentElement('afterend', reference);
			}
		}
	}

	function getEditButton(cell, provider) {
		return cell
			? cell.querySelector('[data-eilmo-courier-edit="' + provider + '"]')
			: null;
	}

	function applyBookingSuccess(cell, button, provider, booking) {
		if (!cell || !button || !booking) {
			return;
		}

		cell.dataset[provider + 'Sent'] = 'yes';

		if (provider === 'steadfast' && booking.delivery_status) {
			button.textContent = formatDeliveryStatus(booking.delivery_status);
			cell.dataset.steadfastStatus = String(booking.delivery_status);
		} else {
			button.textContent = sentLabel(provider);
		}

		button.disabled = true;
		var editButton = getEditButton(cell, provider);
		if (editButton) {
			editButton.disabled = true;
		}

		appendReference(button, booking);
		setCellMessage(cell, config.i18n.sentSuccessfully || 'Sent successfully.', false);
	}

	function directSend(cell, button, provider) {
		if (!cell || !button || button.disabled) {
			return;
		}

		var orderId = Number(cell.dataset.orderId || 0);
		if (!orderId) {
			return;
		}

		var originalLabel = button.textContent;
		var editButton = getEditButton(cell, provider);

		button.disabled = true;
		button.textContent = config.i18n.sending || 'Sending…';
		if (editButton) {
			editButton.disabled = true;
		}
		setCellMessage(cell, '', false);

		post({
			action: config.actions.send,
			nonce: config.nonce,
			order_id: orderId,
			provider: provider,
			fields: '{}'
		})
			.then(function (response) {
				if (!response || !response.success || !response.data || !response.data.booking) {
					throw new Error(getErrorMessage(response));
				}

				applyBookingSuccess(cell, button, provider, response.data.booking);
			})
			.catch(function (error) {
				button.disabled = false;
				button.textContent = originalLabel || sendLabel(provider);
				if (editButton) {
					editButton.disabled = false;
				}
				setCellMessage(
					cell,
					(error && error.message ? error.message : (config.i18n.apiError || 'API error')) +
						' ' + (config.i18n.useEdit || 'Use the edit icon to review courier details.'),
					true
				);
			});
	}

	function showModalError(message) {
		if (!modalError) {
			return;
		}

		modalError.textContent =
			message || '';

		modalError.hidden =
			!message;
	}

	function setModalLoading(isLoading) {
		if (loading) {
			loading.hidden =
				!isLoading;
		}

		if (form) {
			form.hidden =
				isLoading;
		}
	}

	function openModalShell() {
		if (!modal) {
			return;
		}

		modal.hidden =
			false;

		document.documentElement.classList.add(
			'eilmo-cf-courier-modal-open'
		);

		showModalError(
			''
		);

		setModalLoading(
			true
		);
	}

	function closeModal() {
		if (!modal) {
			return;
		}

		modal.hidden =
			true;

		document.documentElement.classList.remove(
			'eilmo-cf-courier-modal-open'
		);

		showModalError(
			''
		);

		state.cell =
			null;

		state.button =
			null;

		state.provider =
			'';

		state.orderId =
			0;

		state.defaults =
			null;
	}

	function field(name) {
		return form
			? form.elements.namedItem(
				name
			)
			: null;
	}

	function setValue(
		name,
		value
	) {
		var input =
			field(
				name
			);

		if (input) {
			input.value =
				value == null
					? ''
					: String(
						value
					);
		}
	}

	function togglePathaoFields(isPathao) {
		if (!modal) {
			return;
		}

		Array.prototype.slice.call(
			modal.querySelectorAll(
				'[data-eilmo-pathao-only]'
			)
		).forEach(function (element) {
			element.hidden =
				!isPathao;
		});
	}

	function selectOptions(
		select,
		items,
		placeholder,
		selectedId,
		useDefault
	) {
		if (!select) {
			return;
		}

		select.innerHTML =
			'';

		var empty =
			document.createElement(
				'option'
			);

		empty.value =
			'';

		empty.textContent =
			placeholder;

		select.appendChild(
			empty
		);

		var resolvedSelected =
			Number(
				selectedId ||
					0
			);

		if (
			!resolvedSelected &&
			useDefault
		) {
			var defaultItem =
				items.find(function (item) {
					return !!item.is_default;
				});

			resolvedSelected =
				defaultItem
					? Number(
						defaultItem.id ||
							0
					)
					: Number(
						items[0] &&
							items[0].id
							? items[0].id
							: 0
					);
		}

		items.forEach(function (item) {
			var option =
				document.createElement(
					'option'
				);

			option.value =
				String(
					item.id ||
						''
				);

			option.textContent =
				String(
					item.name ||
						''
				);

			option.selected =
				Number(
					item.id ||
						0
				) ===
				resolvedSelected;

			select.appendChild(
				option
			);
		});
	}

	function getPathaoOptions(
		type,
		parentId
	) {
		return post({
			action:
				config.actions
					.pathaoOptions,

			nonce:
				config.nonce,

			type:
				type,

			parent_id:
				parentId ||
				0
		}).then(function (response) {
			if (
				!response ||
				!response.success
			) {
				throw new Error(
					getErrorMessage(
						response
					)
				);
			}

			return (
				response.data &&
				Array.isArray(
					response.data.items
				)
			)
				? response.data.items
				: [];
		});
	}

	function loadZones(
		cityId,
		selectedZoneId,
		selectedAreaId
	) {
		var zoneSelect =
			field(
				'recipient_zone'
			);

		var areaSelect =
			field(
				'recipient_area'
			);

		selectOptions(
			zoneSelect,
			[],
			config.i18n.selectZone,
			0,
			false
		);

		selectOptions(
			areaSelect,
			[],
			config.i18n.selectArea,
			0,
			false
		);

		if (!cityId) {
			return Promise.resolve();
		}

		return getPathaoOptions(
			'zones',
			cityId
		).then(function (items) {
			selectOptions(
				zoneSelect,
				items,
				config.i18n.selectZone,
				selectedZoneId,
				false
			);

			var zoneId =
				Number(
					zoneSelect.value ||
						selectedZoneId ||
						0
				);

			if (zoneId) {
				return loadAreas(
					zoneId,
					selectedAreaId
				);
			}
		});
	}

	function loadAreas(
		zoneId,
		selectedAreaId
	) {
		var areaSelect =
			field(
				'recipient_area'
			);

		selectOptions(
			areaSelect,
			[],
			config.i18n.selectArea,
			0,
			false
		);

		if (!zoneId) {
			return Promise.resolve();
		}

		return getPathaoOptions(
			'areas',
			zoneId
		).then(function (items) {
			selectOptions(
				areaSelect,
				items,
				config.i18n.selectArea,
				selectedAreaId,
				false
			);
		});
	}

	function populatePathaoOptions(data) {
		var storeSelect =
			field(
				'store_id'
			);

		var citySelect =
			field(
				'recipient_city'
			);

		return Promise.all([
			getPathaoOptions(
				'stores',
				0
			),

			getPathaoOptions(
				'cities',
				0
			)
		]).then(function (results) {
			selectOptions(
				storeSelect,
				results[0],
				config.i18n.selectStore,
				data.store_id,
				true
			);

			selectOptions(
				citySelect,
				results[1],
				config.i18n.selectCity,
				data.recipient_city,
				false
			);

			var cityId =
				Number(
					citySelect.value ||
						data.recipient_city ||
						0
				);

			return loadZones(
				cityId,
				data.recipient_zone,
				data.recipient_area
			);
		});
	}

	function populateForm(data) {
		var isPathao =
			state.provider ===
			'pathao';

		togglePathaoFields(
			isPathao
		);

		if (subtitle) {
			subtitle.textContent =
				reviewLabel(
					state.provider
				);
		}

		if (submitButton) {
			submitButton.textContent =
				sendLabel(
					state.provider
				);
		}

		if (referenceLabel) {
			referenceLabel.textContent =
				isPathao
					? 'Order ID'
					: 'Invoice';
		}

		if (noteLabel) {
			noteLabel.textContent =
				isPathao
					? 'Special Instruction'
					: 'Note';
		}

		setValue(
			'reference',
			isPathao
				? data.merchant_order_id
				: data.invoice
		);

		var referenceInput =
			field(
				'reference'
			);

		if (referenceInput) {
			referenceInput.readOnly =
				isPathao;
		}

		setValue(
			'recipient_name',
			data.recipient_name
		);

		setValue(
			'recipient_phone',
			data.recipient_phone
		);

		setValue(
			'recipient_address',
			data.recipient_address
		);

		setValue(
			'collect_amount',
			isPathao
				? data.amount_to_collect
				: data.cod_amount
		);

		setValue(
			'note',
			isPathao
				? data.special_instruction
				: data.note
		);

		setValue(
			'item_description',
			data.item_description
		);

		if (!isPathao) {
			return Promise.resolve();
		}

		setValue(
			'recipient_secondary_phone',
			data.recipient_secondary_phone
		);

		setValue(
			'delivery_type',
			data.delivery_type
		);

		setValue(
			'item_type',
			data.item_type
		);

		setValue(
			'item_quantity',
			data.item_quantity
		);

		setValue(
			'item_weight',
			data.item_weight
		);

		return populatePathaoOptions(
			data
		);
	}

	function openCourierEditor(
		cell,
		button,
		provider
	) {
		if (
			!modal ||
			!form
		) {
			return;
		}

		state.cell =
			cell;

		state.button =
			button;

		state.provider =
			provider;

		state.orderId =
			Number(
				cell.dataset.orderId ||
					0
			);

		openModalShell();

		post({
			action:
				config.actions.form,

			nonce:
				config.nonce,

			order_id:
				state.orderId,

			provider:
				provider
		})
			.then(function (response) {
				if (
					!response ||
					!response.success ||
					!response.data ||
					!response.data.form
				) {
					throw new Error(
						getErrorMessage(
							response
						)
					);
				}

				state.defaults =
					response.data.form;

				return populateForm(
					response.data.form
				);
			})
			.catch(function (error) {
				showModalError(
					error &&
						error.message
						? error.message
						: config.i18n.unavailable
				);
			})
			.finally(function () {
				setModalLoading(
					false
				);
			});
	}

	function collectFields() {
		var isPathao =
			state.provider ===
			'pathao';

		var values = {
			recipient_name:
				field(
					'recipient_name'
				).value,

			recipient_phone:
				field(
					'recipient_phone'
				).value,

			recipient_address:
				field(
					'recipient_address'
				).value
		};

		if (isPathao) {
			values.merchant_order_id =
				field(
					'reference'
				).value;

			values.recipient_secondary_phone =
				field(
					'recipient_secondary_phone'
				).value;

			values.amount_to_collect =
				field(
					'collect_amount'
				).value;

			values.store_id =
				field(
					'store_id'
				).value;

			values.delivery_type =
				field(
					'delivery_type'
				).value;

			values.item_type =
				field(
					'item_type'
				).value;

			values.item_quantity =
				field(
					'item_quantity'
				).value;

			values.item_weight =
				field(
					'item_weight'
				).value;

			values.recipient_city =
				field(
					'recipient_city'
				).value;

			values.recipient_zone =
				field(
					'recipient_zone'
				).value;

			values.recipient_area =
				field(
					'recipient_area'
				).value;

			values.item_description =
				field(
					'item_description'
				).value;

			values.special_instruction =
				field(
					'note'
				).value;
		} else {
			values.invoice =
				field(
					'reference'
				).value;

			values.cod_amount =
				field(
					'collect_amount'
				).value;

			values.item_description =
				field(
					'item_description'
				).value;

			values.note =
				field(
					'note'
				).value;
		}

		return values;
	}

	function submitCourierForm(event) {
		event.preventDefault();

		if (
			!state.cell ||
			!state.button ||
			!state.provider ||
			!state.orderId
		) {
			return;
		}

		showModalError(
			''
		);

		submitButton.disabled =
			true;

		submitButton.textContent =
			config.i18n.sending;

		var noteField =
			field(
				'note'
			);

		var courierNote =
			noteField
				? noteField.value
				: '';

		var fields =
			collectFields();

		/*
		 * Force the popup Note into the submitted
		 * Steadfast payload as well as sending it
		 * separately as courier_note.
		 */
		if (
			state.provider ===
			'steadfast'
		) {
			fields.note =
				courierNote;
		}

		post({
			action:
				config.actions.send,

			nonce:
				config.nonce,

			order_id:
				state.orderId,

			provider:
				state.provider,

			courier_note:
				courierNote,

			fields:
				JSON.stringify(
					fields
				)
		})
			.then(function (response) {
				if (
					!response ||
					!response.success ||
					!response.data ||
					!response.data.booking
				) {
					throw new Error(
						getErrorMessage(
							response
						)
					);
				}

				var booking = response.data.booking;
				var provider = state.provider;
				var button = state.button;
				var cell = state.cell;

				applyBookingSuccess(cell, button, provider, booking);
				closeModal();
			})
			.catch(function (error) {
				showModalError(
					error &&
						error.message
						? error.message
						: config.i18n.unavailable
				);
			})
			.finally(function () {
				if (submitButton) {
					submitButton.disabled =
						false;

					submitButton.textContent =
						sendLabel(
							state.provider ||
								'steadfast'
						);
				}
			});
	}

	cells.forEach(function (cell) {
		cell.addEventListener(
			'click',
			function (event) {
				var editButton = event.target.closest('[data-eilmo-courier-edit]');
				if (editButton) {
					if (editButton.disabled) {
						return;
					}

					event.preventDefault();
					event.stopPropagation();

					var editProvider = editButton.getAttribute('data-eilmo-courier-edit');
					if (editProvider !== 'steadfast' && editProvider !== 'pathao') {
						return;
					}

					var sendButton = cell.querySelector('[data-eilmo-courier-send="' + editProvider + '"]');
					if (!sendButton || sendButton.disabled) {
						return;
					}

					openCourierEditor(cell, sendButton, editProvider);
					return;
				}

				var button = event.target.closest('[data-eilmo-courier-send]');
				if (!button || button.disabled) {
					return;
				}

				event.preventDefault();
				event.stopPropagation();

				var provider = button.getAttribute('data-eilmo-courier-send');
				if (provider !== 'steadfast' && provider !== 'pathao') {
					return;
				}

				directSend(cell, button, provider);
			}
		);
	});

	if (modal) {
		modal.addEventListener(
			'click',
			function (event) {
				if (
					event.target.closest(
						'[data-eilmo-courier-close]'
					)
				) {
					event.preventDefault();

					closeModal();
				}
			}
		);
	}

	if (form) {
		form.addEventListener(
			'submit',
			submitCourierForm
		);

		var citySelect =
			field(
				'recipient_city'
			);

		var zoneSelect =
			field(
				'recipient_zone'
			);

		if (citySelect) {
			citySelect.addEventListener(
				'change',
				function () {
					showModalError(
						''
					);

					loadZones(
						Number(
							citySelect.value ||
								0
						),
						0,
						0
					).catch(function (error) {
						showModalError(
							error.message ||
								config.i18n.unavailable
						);
					});
				}
			);
		}

		if (zoneSelect) {
			zoneSelect.addEventListener(
				'change',
				function () {
					showModalError(
						''
					);

					loadAreas(
						Number(
							zoneSelect.value ||
								0
						),
						0
					).catch(function (error) {
						showModalError(
							error.message ||
								config.i18n.unavailable
						);
					});
				}
			);
		}
	}

	document.addEventListener(
		'keydown',
		function (event) {
			if (event.key !== 'Escape') {
				return;
			}

			if (modal && !modal.hidden) {
				closeModal();
			}

			if (breakdownPopover && !breakdownPopover.hidden) {
				breakdownPopover.hidden = true;
			}
		}
	);

	window.addEventListener('resize', function () {
		hideBreakdownPopover(0);
	});

	document.addEventListener('scroll', function () {
		hideBreakdownPopover(0);
	}, true);

	cells.forEach(function (cell) {
		var checkButton = cell.querySelector('[data-eilmo-courier-check]');
		if (checkButton instanceof HTMLButtonElement) {
			checkButton.addEventListener('click', function () {
				manualCheck(cell, checkButton);
			});
		}
	});


	scheduleStandaloneWidthLock();
	if (document.body && window.MutationObserver) {
		var standaloneWidthObserver = new MutationObserver(function (mutations) {
			var shouldLock = mutations.some(function (mutation) {
				return mutation.type === 'childList' && mutation.addedNodes.length > 0;
			});
			if (shouldLock) {
				scheduleStandaloneWidthLock();
			}
		});
		standaloneWidthObserver.observe(document.body, { childList: true, subtree: true });
	}

	loadStats();
	loadDeliveryStatuses();
})();
