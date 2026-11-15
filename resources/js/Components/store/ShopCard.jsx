import { Link } from '@inertiajs/react';
import { initials } from '../../lib/format';
import Stars from './Stars';

export function ShopLogo({ shop, size = 'h-12 w-12' }) {
  return shop.logo_url ? (
    <img src={shop.logo_url} alt="" className={`${size} shrink-0 rounded-full object-cover`} />
  ) : (
    <span className={`${size} flex shrink-0 items-center justify-center rounded-full bg-brand-500 text-sm font-bold text-white`}>{initials(shop.name)}</span>
  );
}

export default function ShopCard({ shop }) {
  return (
    <Link
      href={`/shops/${shop.slug}`}
      className="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-brand-300 dark:border-gray-800 dark:bg-white/[0.03]"
    >
      <ShopLogo shop={shop} />
      <div className="min-w-0">
        <p className="truncate font-semibold text-gray-800 dark:text-white/90">{shop.name}</p>
        <p className="truncate text-xs text-gray-500">{[shop.category, shop.city].filter(Boolean).join(' · ')}</p>
        <p className="mt-1 flex items-center gap-1.5 text-xs text-gray-500">
          {shop.rating_avg > 0 && (
            <>
              <Stars value={shop.rating_avg} size="h-3 w-3" /> {shop.rating_avg.toFixed(1)} ·
            </>
          )}
          {shop.products_count ?? 0} products
        </p>
      </div>
    </Link>
  );
}
