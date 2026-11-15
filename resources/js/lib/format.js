/**
 * Formatting helpers shared by every panel. Money values arrive from Laravel as decimal strings.
 */

export function formatMoney(value, currency = 'PHP') {
  const amount = Number(value ?? 0);

  return new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(Number.isFinite(amount) ? amount : 0);
}

export function formatNumber(value) {
  return new Intl.NumberFormat('en-PH').format(Number(value ?? 0));
}

export function formatDate(value, options = { dateStyle: 'medium' }) {
  if (!value) {
    return '—';
  }

  return new Intl.DateTimeFormat('en-PH', options).format(new Date(value));
}

export function formatDateTime(value) {
  return formatDate(value, { dateStyle: 'medium', timeStyle: 'short' });
}

export function headline(value) {
  return String(value ?? '')
    .replace(/[_-]+/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

export function initials(name) {
  return String(name ?? '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('');
}
