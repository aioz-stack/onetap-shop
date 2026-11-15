import { Link } from '@inertiajs/react';
import { StatusBadge } from '../ui';
import { formatDate, formatMoney } from '../../lib/format';
import { ProductImage } from './ProductCard';

/**
 * One seller order in My Purchases.
 */
export default function OrderCard({ order, children }) {
  const first = order.items?.[0];
  const more = (order.items?.length ?? 1) - 1;

  return (
    <article className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
      <header className="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
        <Link href={`/shops/${order.shop?.slug}`} className="text-sm font-semibold text-gray-800 hover:text-brand-600 dark:text-white/90">
          {order.shop?.name}
        </Link>
        <span className="flex items-center gap-2 text-xs text-gray-500">
          {formatDate(order.placed_at)} · <StatusBadge status={order.status} />
        </span>
      </header>
      <Link href={`/account/orders/${order.id}`} className="flex gap-3 px-4 py-3 hover:bg-gray-50/60 dark:hover:bg-white/[0.02]">
        <span className="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-gray-50 dark:bg-white/5">
          <ProductImage src={first?.image_url} name={first?.product_name ?? ''} />
        </span>
        <span className="min-w-0 flex-1 text-sm">
          <span className="line-clamp-1 text-gray-800 dark:text-white/90">{first?.product_name}</span>
          <span className="text-xs text-gray-500">
            {first?.variation_label !== 'Default' ? `${first?.variation_label} · ` : ''}×{first?.quantity}
            {more > 0 && ` · +${more} more item${more > 1 ? 's' : ''}`}
          </span>
          <span className="mt-1 block text-xs text-gray-400">{order.reference}</span>
        </span>
        <span className="text-right text-sm">
          <span className="block text-xs text-gray-500">Order total</span>
          <span className="font-semibold text-brand-700 dark:text-brand-400">{formatMoney(order.total)}</span>
        </span>
      </Link>
      {children && <footer className="flex flex-wrap justify-end gap-2 border-t border-gray-100 px-4 py-3 dark:border-gray-800">{children}</footer>}
    </article>
  );
}
