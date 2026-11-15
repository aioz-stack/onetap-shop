import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ProductGrid } from '../../Components/store/ProductCard';
import ShopCard from '../../Components/store/ShopCard';
import { Button, Checkbox, EmptyState, Select, TextInput } from '../../Components/ui';
import CustomerLayout from '../../Layouts/CustomerLayout';
import { withQuery } from '../../lib/query';

const sortLabels = { relevance: 'Best match', newest: 'Newest', price_asc: 'Price: low to high', price_desc: 'Price: high to low', best_selling: 'Best selling', top_rated: 'Top rated' };

function go(updates) {
  router.get(withQuery(updates, '/search'), {}, { preserveScroll: true });
}

function Pager({ meta, links }) {
  if (!meta || meta.last_page <= 1) {
    return null;
  }

  return (
    <nav aria-label="Pagination" className="mt-8 flex items-center justify-center gap-3">
      {links.prev ? <Button variant="secondary" size="sm" href={links.prev}>← Previous</Button> : <span />}
      <span className="text-sm text-gray-500">
        Page {meta.current_page} of {meta.last_page}
      </span>
      {links.next ? <Button variant="secondary" size="sm" href={links.next}>Next →</Button> : <span />}
    </nav>
  );
}

export default function Search({ products, shops, filters, categories, sorts }) {
  const [price, setPrice] = useState({ min: filters.min ?? '', max: filters.max ?? '' });
  const activeCategory = categories.flatMap((category) => [category, ...category.children]).find((category) => category.slug === filters.category);
  const heading = filters.q ? `Results for “${filters.q}”` : activeCategory ? activeCategory.name : 'All products';

  return (
    <CustomerLayout>
      <Head title={heading} />

      <div className="grid gap-6 lg:grid-cols-[240px_1fr]">
        <aside className="min-w-0 space-y-6">
          <div>
            <h2 className="mb-2 text-sm font-semibold text-gray-800 dark:text-white/90">Categories</h2>
            <ul className="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 text-sm lg:mx-0 lg:block lg:space-y-1 lg:px-0">
              <li className="shrink-0">
                <button type="button" onClick={() => go({ category: null })} className={`rounded-lg px-2.5 py-1.5 ${!filters.category ? 'bg-brand-50 font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400' : 'text-gray-600 hover:text-brand-600 dark:text-gray-400'}`}>
                  All
                </button>
              </li>
              {categories.map((category) => (
                <li key={category.id} className="shrink-0">
                  <button type="button" onClick={() => go({ category: category.slug })} className={`whitespace-nowrap rounded-lg px-2.5 py-1.5 text-left ${filters.category === category.slug ? 'bg-brand-50 font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400' : 'text-gray-600 hover:text-brand-600 dark:text-gray-400'}`}>
                    {category.name}
                  </button>
                  {(filters.category === category.slug || category.children.some((child) => child.slug === filters.category)) && (
                    <ul className="ml-3 mt-1 hidden space-y-1 border-l border-gray-200 pl-2 lg:block dark:border-gray-800">
                      {category.children.map((child) => (
                        <li key={child.id}>
                          <button type="button" onClick={() => go({ category: child.slug })} className={`text-left text-sm ${filters.category === child.slug ? 'font-medium text-brand-700 dark:text-brand-400' : 'text-gray-500 hover:text-brand-600'}`}>
                            {child.name}
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </li>
              ))}
            </ul>
          </div>

          <form
            className="hidden space-y-3 lg:block"
            onSubmit={(event) => {
              event.preventDefault();
              go({ min: price.min, max: price.max });
            }}
          >
            <h2 className="text-sm font-semibold text-gray-800 dark:text-white/90">Price (₱)</h2>
            <div className="flex items-center gap-2">
              <TextInput type="number" min="0" aria-label="Minimum price" placeholder="Min" className="h-9" value={price.min} onChange={(event) => setPrice({ ...price, min: event.target.value })} />
              <span className="text-gray-400">–</span>
              <TextInput type="number" min="0" aria-label="Maximum price" placeholder="Max" className="h-9" value={price.max} onChange={(event) => setPrice({ ...price, max: event.target.value })} />
            </div>
            <Button type="submit" size="sm" variant="secondary" className="w-full">
              Apply
            </Button>
          </form>

          <div className="hidden lg:block">
            <Checkbox label="In stock only" checked={Boolean(filters.in_stock)} onChange={(event) => go({ in_stock: event.target.checked ? 1 : null })} />
          </div>
        </aside>

        <section className="min-w-0">
          <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 className="text-xl font-semibold text-gray-800 dark:text-white/90">{heading}</h1>
              <p className="text-sm text-gray-500">{products.meta.total} product{products.meta.total === 1 ? '' : 's'}</p>
            </div>
            <label className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
              Sort
              <Select aria-label="Sort by" className="h-10 w-48" value={filters.sort ?? (filters.q ? 'relevance' : 'newest')} options={sorts.filter((sort) => filters.q || sort !== 'relevance').map((sort) => ({ value: sort, label: sortLabels[sort] }))} onChange={(event) => go({ sort: event.target.value })} />
            </label>
          </div>

          {shops.length > 0 && (
            <div className="mb-6">
              <h2 className="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Shops</h2>
              <ul className="grid gap-3 sm:grid-cols-2">
                {shops.map((shop) => (
                  <li key={shop.id}>
                    <ShopCard shop={shop} />
                  </li>
                ))}
              </ul>
            </div>
          )}

          {products.data.length ? (
            <>
              <ProductGrid products={products.data} columns="grid-cols-2 sm:grid-cols-3 xl:grid-cols-4" />
              <Pager meta={products.meta} links={products.links} />
            </>
          ) : (
            <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
              <EmptyState
                title="No products match"
                description="Try a different search word or remove a filter."
                icon="⌕"
                action={
                  <Link href="/search" className="text-sm font-medium text-brand-600">
                    Clear all filters
                  </Link>
                }
              />
            </div>
          )}
        </section>
      </div>
    </CustomerLayout>
  );
}
