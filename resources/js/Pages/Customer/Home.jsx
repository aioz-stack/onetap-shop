import { Head, Link, usePage } from '@inertiajs/react';
import AppIcon from '../../Components/AppIcon';
import { Card, EmptyState } from '../../Components/ui';
import CustomerLayout from '../../Layouts/CustomerLayout';

const categoryIcons = ['cart', 'store', 'home', 'tag', 'star', 'layers', 'settings', 'box'];

export default function Home({ categories }) {
  const { props, url } = usePage();
  const { auth } = props;
  const params = new URLSearchParams(url.split('?')[1] ?? '');
  const searching = params.get('q') || params.get('category');

  return (
    <CustomerLayout categories={categories}>
      <Head title="Shop local" />

      <section className="relative overflow-hidden rounded-3xl bg-brand-700 px-6 py-10 text-white sm:px-10 sm:py-14">
        <div className="absolute -right-16 -top-16 h-64 w-64 rounded-full border-[36px] border-brand-500/40" aria-hidden="true" />
        <div className="absolute bottom-6 right-40 hidden h-5 w-5 rounded-full bg-orange-400 sm:block" aria-hidden="true" />
        <div className="relative max-w-xl">
          <p className="text-sm font-medium uppercase tracking-[0.14em] text-brand-100">Onetap Marketplace</p>
          <h1 className="mt-3 text-3xl font-bold leading-tight sm:text-title-md">Everything your neighborhood sells, delivered.</h1>
          <p className="mt-4 text-brand-50/90">
            Groceries, bakeries, boutiques and hardware stores near you. Pay cash when it arrives.
          </p>
          {!auth?.user && (
            <div className="mt-6 flex flex-wrap gap-3">
              <Link href="/register" className="rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50">
                Create a free account
              </Link>
              <Link href="/login" className="rounded-lg px-5 py-2.5 text-sm font-semibold text-white ring-1 ring-inset ring-white/40 hover:bg-white/10">
                Log in
              </Link>
            </div>
          )}
        </div>
      </section>

      {searching && (
        <Card className="mt-6">
          <EmptyState
            icon="⌕"
            title="Product search opens soon"
            description="Shops are being onboarded. Search and category browsing go live with the storefront (Phase 4)."
          />
        </Card>
      )}

      <section className="mt-8">
        <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Shop by category</h2>
        {categories.length ? (
          <ul className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
            {categories.map((category, index) => (
              <li key={category.id}>
                <Link
                  href={`/?category=${category.slug}`}
                  className="flex h-full flex-col items-center gap-2 rounded-2xl border border-gray-200 bg-white p-4 text-center text-sm font-medium text-gray-700 transition hover:border-brand-300 hover:text-brand-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"
                >
                  <span className="flex h-11 w-11 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
                    <AppIcon name={categoryIcons[index % categoryIcons.length]} />
                  </span>
                  {category.name}
                </Link>
              </li>
            ))}
          </ul>
        ) : (
          <Card className="mt-4">
            <EmptyState title="No categories yet" description="An administrator can add categories from the Admin Panel." />
          </Card>
        )}
      </section>

      <section className="mt-10">
        <Card title="Featured products" description="Products from approved shops will appear here.">
          <EmptyState title="No products listed yet" description="The first local shops are getting ready. Check back soon." icon="◇" />
        </Card>
      </section>
    </CustomerLayout>
  );
}
