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
