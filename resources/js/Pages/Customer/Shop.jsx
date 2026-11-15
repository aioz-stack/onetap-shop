import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { ProductGrid } from '../../Components/store/ProductCard';
import { ShopLogo } from '../../Components/store/ShopCard';
import Stars from '../../Components/store/Stars';
import { Button, EmptyState, Select, TextInput } from '../../Components/ui';
import CustomerLayout from '../../Layouts/CustomerLayout';
import { formatDate } from '../../lib/format';
import { withQuery } from '../../lib/query';

const sortLabels = { newest: 'Newest', price_asc: 'Price: low to high', price_desc: 'Price: high to low', best_selling: 'Best selling', top_rated: 'Top rated' };

export default function Shop({ shop, products, filters, sorts }) {
  const { auth } = usePage().props;
  const [term, setTerm] = useState(filters.q ?? '');
  const template = shop.template ?? 'classic';
  const accent = /^#[0-9a-fA-F]{6}$/.test(shop.decoration?.accent_color ?? '') ? shop.decoration.accent_color : '#0f9d76';
  const go = (updates) => router.get(withQuery(updates, `/shops/${shop.slug}`), {}, { preserveScroll: true });

  return (
    <CustomerLayout>
      <Head title={shop.name} />

      {shop.decoration?.announcement && (
        <p className="mb-4 rounded-xl px-4 py-2.5 text-center text-sm font-medium text-white" style={{ backgroundColor: accent }}>
          {shop.decoration.announcement}
        </p>
      )}
      <section className="overflow-hidden rounded-3xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        {template !== 'minimal' && (
          <div className={`relative ${template === 'spotlight' ? 'h-44 sm:h-60' : 'h-28 sm:h-36'}`} style={{ background: `linear-gradient(90deg, ${accent}, ${accent}aa)` }}>
            {shop.banner_url && <img src={shop.banner_url} alt="" className="h-full w-full object-cover" />}
            {template === 'spotlight' && (
              <div className="absolute inset-0 flex items-end bg-gradient-to-t from-black/60 to-transparent p-5">
                <p className="text-2xl font-bold text-white sm:text-3xl">{shop.name}</p>
              </div>
            )}
          </div>
        )}
        <div className={`flex flex-col gap-4 px-5 pb-5 sm:flex-row sm:items-end sm:justify-between ${template === 'minimal' ? 'pt-5' : ''}`}>
          <div className={`${template === 'minimal' ? '' : '-mt-8'} flex items-end gap-4`}>
            <div className="rounded-full border-4 border-white dark:border-gray-900">
              <ShopLogo shop={shop} size="h-20 w-20" />
            </div>
            <div className="pb-1">
              <h1 className="text-xl font-semibold text-gray-800 dark:text-white/90">{shop.name}</h1>
              <p className="flex flex-wrap items-center gap-1.5 text-sm text-gray-500">
                {shop.rating_avg > 0 && (
                  <>
                    <Stars value={shop.rating_avg} size="h-3.5 w-3.5" /> {shop.rating_avg.toFixed(1)} ·
                  </>
                )}
                {shop.products_count} products · {[shop.barangay, shop.city].filter(Boolean).join(', ')} · Since {formatDate(shop.joined_at, { year: 'numeric', month: 'short' })}
              </p>
            </div>
          </div>
          {auth?.user?.roles?.includes('customer') && (
            <Button variant="secondary" size="sm" onClick={() => router.post(`/account/chat/shops/${shop.id}`)}>
              Chat with shop
            </Button>
          )}
        </div>
        {shop.description && <p className="border-t border-gray-100 px-5 py-4 text-sm text-gray-600 dark:border-gray-800 dark:text-gray-400">{shop.description}</p>}
        {shop.vacation_mode && (
          <p className="border-t border-warning-200 bg-warning-50 px-5 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-orange-400">
            On vacation{shop.vacation_message ? ` — ${shop.vacation_message}` : ''}. Orders are paused.
          </p>
        )}
      </section>

      <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form
          className="flex gap-2 sm:w-80"
          onSubmit={(event) => {
            event.preventDefault();
            go({ q: term });
          }}
        >
          <TextInput type="search" aria-label="Search this shop" placeholder="Search this shop" className="h-10" value={term} onChange={(event) => setTerm(event.target.value)} />
          <Button type="submit" size="sm" variant="secondary" className="h-10">
            Search
          </Button>
        </form>
        <Select aria-label="Sort by" className="h-10 sm:w-48" value={filters.sort} options={sorts.filter((sort) => sort !== 'relevance').map((sort) => ({ value: sort, label: sortLabels[sort] }))} onChange={(event) => go({ sort: event.target.value })} />
      </div>

      <div className="mt-5">
        {products.data.length ? (
          <>
            <ProductGrid products={products.data} />
            {products.meta.last_page > 1 && (
              <div className="mt-8 flex items-center justify-center gap-3">
                {products.links.prev && <Button variant="secondary" size="sm" href={products.links.prev}>← Previous</Button>}
                <span className="text-sm text-gray-500">Page {products.meta.current_page} of {products.meta.last_page}</span>
                {products.links.next && <Button variant="secondary" size="sm" href={products.links.next}>Next →</Button>}
              </div>
            )}
          </>
        ) : (
          <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <EmptyState title={filters.q ? 'No products match' : 'No products yet'} description={filters.q ? 'Try another word.' : 'This shop has not listed any products yet.'} />
          </div>
        )}
      </div>
    </CustomerLayout>
  );
}
