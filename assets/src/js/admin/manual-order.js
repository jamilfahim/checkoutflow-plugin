(function () {
    'use strict';

    var root = document.getElementById('eilmo-cf-admin-order');
    var config = window.eilmoCfAdminOrder;
    if (!root || !config) return;

    var button = document.getElementById('eilmo-cf-admin-preview-button');
    var output = document.getElementById('eilmo-cf-admin-preview');
    var file = document.getElementById('eilmo-cf-admin-proof');
    var token = document.getElementById('eilmo-cf-admin-proof-token');
    var status = document.getElementById('eilmo-cf-admin-proof-status');
    var type = document.getElementById('eilmo-cf-admin-payment-type');
    var gateway = document.getElementById('eilmo-cf-admin-gateway');
    var advanceRow = document.getElementById('eilmo-cf-admin-advance-row');
    var advanceInput = document.getElementById('eilmo-cf-admin-advance-amount');
    var advanceOverride = document.getElementById('eilmo-cf-admin-advance-override');
    var advanceDefault = document.getElementById('eilmo-cf-admin-advance-default');
    var advanceStatus = document.getElementById('eilmo-cf-admin-advance-status');
    var advanceRequestId = 0;
    var advanceTimer;

    function formValue(name) {
        var element = root.querySelector('[name="' + name + '"]');
        return element ? element.value : '';
    }

    function showLines(lines) {
        output.replaceChildren();
        (lines || []).forEach(function (line) {
            var paragraph = document.createElement('p');
            paragraph.textContent = line;
            output.appendChild(paragraph);
        });
    }

    function updateVisibility() {
        var isCod = type.value === 'cash_on_delivery';
        advanceRow.hidden = type.value !== 'advance';
        gateway.disabled = isCod;
        file.disabled = isCod || (gateway.value !== 'eilmo_bkash' && gateway.value !== 'eilmo_nagad');
        if (file.disabled) {
            file.value = '';
            token.value = '';
        }
    }

    function calculationRequest(forceDefault) {
        var request = new FormData();
        request.append('action', 'eilmo_cf_admin_order_preview');
        request.append('nonce', config.previewNonce);
        request.append('order_id', root.dataset.orderId || '0');
        ['eilmo_cf_admin_payment_type', 'eilmo_cf_admin_delivery_amount', 'eilmo_cf_admin_discount_amount', 'eilmo_cf_admin_gateway_id', 'eilmo_cf_admin_advance_amount'].forEach(function (name) {
            request.append(name, formValue(name));
        });
        request.append('eilmo_cf_admin_advance_override', forceDefault ? 'no' : advanceOverride.value);
        return fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: request }).then(function (response) { return response.json(); });
    }

    function applyDefault(response) {
        if (type.value !== 'advance' || advanceOverride.value !== 'no') return;
        if (response.success && response.data.defaultAdvance !== null) {
            advanceInput.value = String(response.data.defaultAdvance);
            advanceStatus.textContent = '';
        } else {
            advanceInput.value = '';
            advanceStatus.textContent = response.data && response.data.message || 'No matching advance rule.';
        }
    }

    function refreshDefault() {
        if (type.value !== 'advance' || advanceOverride.value !== 'no') return;
        var requestId = ++advanceRequestId;
        advanceStatus.textContent = 'Calculating…';
        calculationRequest(true).then(function (response) {
            if (requestId === advanceRequestId) applyDefault(response);
        }).catch(function () {
            if (requestId === advanceRequestId) advanceStatus.textContent = 'Could not calculate the plugin default.';
        });
    }

    type.addEventListener('change', function () {
        updateVisibility();
        refreshDefault();
    });
    gateway.addEventListener('change', updateVisibility);
    advanceInput.addEventListener('input', function () {
        advanceOverride.value = 'yes';
        advanceStatus.textContent = '';
        advanceRequestId++;
    });
    advanceDefault.addEventListener('click', function () {
        advanceOverride.value = 'no';
        advanceInput.value = '';
        refreshDefault();
    });
    ['eilmo_cf_admin_delivery_amount', 'eilmo_cf_admin_discount_amount'].forEach(function (name) {
        root.querySelector('[name="' + name + '"]').addEventListener('input', function () {
            clearTimeout(advanceTimer);
            advanceTimer = setTimeout(refreshDefault, 350);
        });
    });
    updateVisibility();
    refreshDefault();

    button.addEventListener('click', function () {
        button.disabled = true;
        showLines(['Calculating…']);
        calculationRequest(false)
            .then(function (response) {
                showLines(response.success ? response.data.lines : [response.data.message || 'Calculation failed.']);
                if (advanceOverride.value === 'no') applyDefault(response);
            })
            .catch(function () { showLines(['Calculation failed.']); })
            .finally(function () { button.disabled = false; });
    });

    file.addEventListener('change', function () {
        token.value = '';
        if (!file.files.length) return;
        var methodKey = gateway.value === 'eilmo_bkash' ? 'bkash' : (gateway.value === 'eilmo_nagad' ? 'nagad' : '');
        if (!methodKey) return;
        status.textContent = config.uploading;
        var request = new FormData();
        request.append('action', 'eilmo_cf_upload_payment_proof');
        request.append('nonce', config.proofNonce);
        request.append('method_key', methodKey);
        request.append('proof', file.files[0]);
        fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: request })
            .then(function (response) { return response.json(); })
            .then(function (response) {
                if (!response.success || !response.data.token) throw new Error(response.data && response.data.message || config.uploadFailed);
                token.value = response.data.token;
                status.textContent = response.data.name || 'Uploaded';
            })
            .catch(function (error) { status.textContent = error.message || config.uploadFailed; });
    });
}());
