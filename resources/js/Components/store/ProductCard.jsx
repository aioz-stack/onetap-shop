import { Link } from '@inertiajs/react';
import { formatMoney, formatNumber } from '../../lib/format';
import Stars from './Stars';

export function ProductImage({ src, name, className = '' }) {
  if (src) {
    return <img src={src} alt={name} loading="lazy" className={`h-full w-full object-cover ${className}`} />;
  }

  // Branded placeholder when a seller has not uploaded a photo yet.
  return (
    <div className={`flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-50 to-brand-100 dark:from-brand-500/10 dark:to-brand-500/20 ${className}`}>
      <span className="px-3 text-center text-sm font-semibold text-brand-700 dark:text-brand-400">{name}</span>
    </div>
  );
}

export default function ProductCard({ product }) {
  const onSale = product.compare_at_price && Number(product.compare_at_price) > Number(product.price);

  return (
    <Link
      href={`/products/${product.slug}`}
      className="group flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-theme-md dark:border-gray-800 dark:bg-white/[0.03]"
    >
      <div className="relative aspect-square overflow-hidden bg-gray-50 dark:bg-white/5">
        <ProductImage src={product.image_url} name={product.name} className="transition duration-300 group-hover:scale-105" />
        {!product.in_stock && <span className="absolute left-2 top-2 rounded-full bg-gray-900/80 px-2 py-0.5 text-[11px] font-medium text-white">Sold out</span>}
        {onSale && product.in_stock && (
          <span className="absolute left-2 top-2 rounded-full bg-orange-500 px-2 py-0.5 text-[11px] font-semibold text-white">
            {product.discount_percent ? `−${Math.round(product.discount_percent)}%` : 'Sale'}
          </span>
        )}
      </div>
      <div className="flex flex-1 flex-col gap-1.5 p-3">
        <p className="line-clamp-2 text-sm text-gray-800 dark:text-white/90">{product.name}</p>
        <div className="mt-auto">
          <p className="text-base font-bold text-brand-700 dark:text-brand-400">
            {formatMoney(product.price)}
            {product.max_price !== product.price && <span className="text-xs font-normal text-gray-500"> – {formatMoney(product.max_price)}</span>}
          </p>
          {onSale && <p className="text-xs text-gray-400 line-through">{formatMoney(product.compare_at_price)}</p>}
          <div className="mt-1 flex items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
            <span className="flex items-center gap-1">
              {product.rating_count > 0 ? (
                <>
                  <Stars value={product.rating_avg} size="h-3 w-3" />
                  {product.rating_avg.toFixed(1)}
                </>
              ) : (
                'New'
              )}
            </span>
            {product.sold_count > 0 && <span>{formatNumber(product.sold_count)} sold</span>}
          </div>
          {product.shop && <p className="mt-1 truncate text-xs text-gray-500">{product.shop.name}{product.shop.city ? ` · ${product.shop.city}` : ''}</p>}
        </div>
      </div>
    </Link>
  );
}

export function ProductGrid({ products, columns = 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5' }) {
  return (
    <ul className={`grid gap-3 sm:gap-4 ${columns}`}>
      {products.map((product) => (
        <li key={product.id}>
          <ProductCard product={product} />
        </li>
      ))}
    </ul>
  );
}
