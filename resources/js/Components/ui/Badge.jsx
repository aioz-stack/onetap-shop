import { headline } from '../../lib/format';

const tones = {
  gray: 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300',
  brand: 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
  success: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500',
  warning: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-orange-400',
  error: 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-500',
  info: 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-500',
};

/**
 * Maps every status value used across orders, shipments, products and accounts to a color.
 */
const statusTones = {
  active: 'success',
  approved: 'success',
  live: 'success',
  completed: 'success',
  delivered: 'success',
  paid: 'success',
  confirmed: 'success',
  available: 'success',
  pending: 'warning',
  under_review: 'warning',
  unpaid: 'warning',
  requested: 'warning',
  submitted: 'warning',
  pending_pickup: 'warning',
  processing: 'info',
  ready_for_pickup: 'info',
  shipping: 'info',
  assigned: 'info',
  accepted: 'info',
  arrived_at_pickup: 'info',
  picked_up: 'info',
  in_transit: 'info',
  out_for_delivery: 'brand',
  busy: 'brand',
  draft: 'gray',
  unpublished: 'gray',
  offline: 'gray',
  inactive: 'gray',
  closed: 'gray',
  rescheduled: 'warning',
  cancelled: 'error',
  rejected: 'error',
  suspended: 'error',
  violation: 'error',
  failed: 'error',
  failed_delivery: 'error',
  customer_unavailable: 'error',
  returned_to_seller: 'error',
  discrepancy: 'error',
  disputed: 'error',
  collected: 'info',
  remitted: 'success',
  declined: 'gray',
  return_requested: 'warning',
  returned: 'gray',
  refunded: 'gray',
};

export default function Badge({ tone = 'gray', className = '', children }) {
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${tones[tone]} ${className}`}>
      {children}
    </span>
  );
}

export function StatusBadge({ status }) {
  return <Badge tone={statusTones[status] ?? 'gray'}>{headline(status)}</Badge>;
}
