import { useEffect, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AppIcon from '../../Components/AppIcon';
import { ProductGrid } from '../../Components/store/ProductCard';
import ShopCard from '../../Components/store/ShopCard';
import { EmptyState } from '../../Components/ui';
import CustomerLayout from '../../Layouts/CustomerLayout';

const categoryIcons = ['cart', 'store', 'home', 'tag', 'star', 'layers', 'settings', 'box'];

function Section({ title, href, children }) {
  return (
    <section className="mt-10">
      <div className="mb-4 flex items-end justify-between gap-3">
        <h2 className="text-lg font-semibold text-gray-800 sm:text-xl dark:text-white/90">{title}</h2>
        {href && (
          <Link href={href} className="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
            See all →
          </Link>
        )}
      </div>
      {children}
    </section>
  );
}

function Hero({ banners, signedIn }) {
  const [index, setIndex] = useState(0);

  useEffect(() => {
    if (banners.length < 2) {
      return undefined;
    }

    const timer = setInterval(() => setIndex((value) => (value + 1) % banners.length), 6000);

    return () => clearInterval(timer);
  }, [banners.length]);

  const banner = banners[index];

  return (
    <section className="relative overflow-hidden rounded-3xl bg-brand-700 text-white">
      {banner?.image_url && <img src={banner.image_url} alt="" className="absolute inset-0 h-full w-full object-cover opacity-40" />}
      <div className="absolute -right-16 -top-16 h-64 w-64 rounded-full border-[36px] border-brand-500/40" aria-hidden="true" />
      <div className="absolute bottom-6 right-40 hidden h-5 w-5 rounded-full bg-orange-400 sm:block" aria-hidden="true" />
      <div className="relative max-w-xl px-6 py-10 sm:px-10 sm:py-14">
        <p className="text-sm font-medium uppercase tracking-[0.14em] text-brand-100">Onetap Marketplace</p>
        <h1 className="mt-3 text-3xl font-bold leading-tight sm:text-title-md">{banner?.title ?? 'Everything your neighborhood sells, delivered.'}</h1>
        <p className="mt-4 text-brand-50/90">{banner?.subtitle ?? 'Groceries, bakeries, boutiques and hardware stores near you. Pay cash when it arrives.'}</p>
        <div className="mt-6 flex flex-wrap gap-3">
          <Link href={banner?.link_url ?? '/search'} className="rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-brand-700 hover:bg-brand-50">
            Start shopping
          </Link>
          {!signedIn && (
            <Link href="/register" className="rounded-lg px-5 py-2.5 text-sm font-semibold text-white ring-1 ring-inset ring-white/40 hover:bg-white/10">
              Create a free account
            </Link>
          )}
        </div>
      </div>
      {banners.length > 1 && (
        <div className="absolute bottom-4 left-6 flex gap-1.5 sm:left-10">
          {banners.map((item, dot) => (
            <button key={item.id} type="button" aria-label={`Show banner ${dot + 1}`} onClick={() => setIndex(dot)} className={`h-2 rounded-full transition-all ${dot === index ? 'w-6 bg-white' : 'w-2 bg-white/50'}`} />
          ))}
        </div>
      )}
    </section>
  );
}

export default function Home({ categories, banners, featured, recommended, bestSellers, newArrivals, popularShops }) {
  const { auth } = usePage().props;
  const empty = !featured.length && !bestSellers.length && !newArrivals.length;

  return (
    <CustomerLayout>
      <Head title="Shop local" />
      <Hero banners={banners} signedIn={Boolean(auth?.user)} />

      <Section title="Shop by category">
        <ul className="grid grid-cols-4 gap-2 sm:gap-3 lg:grid-cols-8">
          {categories.map((category, index) => (
            <li key={category.id}>
              <Link
                href={`/search?category=${category.slug}`}
                className="flex h-full flex-col items-center gap-2 rounded-2xl border border-gray-200 bg-white p-3 text-center text-xs font-medium text-gray-700 transition hover:border-brand-300 hover:text-brand-600 sm:p-4 sm:text-sm dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"
              >
                <span className="flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 text-brand-600 sm:h-11 sm:w-11 dark:bg-brand-500/15 dark:text-brand-400">
                  <AppIcon name={categoryIcons[index % categoryIcons.length]} />
                </span>
                {category.name}
              </Link>
            </li>
          ))}
        </ul>
      </Section>

      {empty ? (
        <section className="mt-10 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <EmptyState title="No products listed yet" description="The first local shops are getting ready. Check back soon." icon="◇" />
        </section>
      ) : (
        <>
          {featured.length > 0 && (
            <Section title="Featured" href="/search?sort=top_rated">
              <ProductGrid products={featured} />
            </Section>
          )}
          {recommended.length > 0 && (
            <Section title={auth?.user ? 'Recommended for you' : 'You might like'}>
              <ProductGrid products={recommended} />
            </Section>
          )}
          {popularShops.length > 0 && (
            <Section title="Popular shops">
              <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {popularShops.map((shop) => (
                  <li key={shop.id}>
                    <ShopCard shop={shop} />
                  </li>
                ))}
              </ul>
            </Section>
          )}
          {bestSellers.length > 0 && (
            <Section title="Best sellers" href="/search?sort=best_selling">
              <ProductGrid products={bestSellers} />
            </Section>
          )}
          {newArrivals.length > 0 && (
            <Section title="Recently added" href="/search?sort=newest">
              <ProductGrid products={newArrivals} />
            </Section>
          )}
        </>
      )}
    </CustomerLayout>
  );
}
