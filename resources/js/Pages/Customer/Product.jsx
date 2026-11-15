import { useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ProductGrid, ProductImage } from '../../Components/store/ProductCard';
import QuantityInput from '../../Components/store/QuantityInput';
import { ShopLogo } from '../../Components/store/ShopCard';
import Stars from '../../Components/store/Stars';
import { Button, Card, EmptyState } from '../../Components/ui';
import CustomerLayout from '../../Layouts/CustomerLayout';
import { formatDate, formatMoney, formatNumber } from '../../lib/format';

function Gallery({ images, name }) {
  const [active, setActive] = useState(0);

  return (
    <div>
      <div className="aspect-square overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <ProductImage src={images[active]?.url} name={name} />
      </div>
      {images.length > 1 && (
        <ul className="mt-3 grid grid-cols-5 gap-2">
          {images.map((image, index) => (
            <li key={image.id}>
              <button type="button" onClick={() => setActive(index)} aria-label={`Show image ${index + 1}`} className={`aspect-square w-full overflow-hidden rounded-lg border-2 ${index === active ? 'border-brand-500' : 'border-transparent'}`}>
                <img src={image.url} alt="" className="h-full w-full object-cover" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function Reviews({ product, reviews }) {
  const total = Object.values(reviews.distribution).reduce((sum, count) => sum + count, 0);

  return (
    <Card title={`Ratings & reviews (${reviews.meta.total})`} className="mt-8">
      {reviews.meta.total === 0 ? (
        <EmptyState title="No reviews yet" description="Reviews come from customers who bought and received this item." icon="★" />
      ) : (
        <div className="grid gap-8 lg:grid-cols-[240px_1fr]">
          <div>
            <p className="text-4xl font-bold text-gray-800 dark:text-white/90">{product.rating_avg.toFixed(1)}</p>
            <Stars value={product.rating_avg} />
            <ul className="mt-4 space-y-1.5">
              {Object.entries(reviews.distribution)
                .sort(([a], [b]) => b - a)
                .map(([star, count]) => (
                  <li key={star} className="flex items-center gap-2 text-xs text-gray-500">
                    <span className="w-3">{star}</span>
                    <span className="h-2 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                      <span className="block h-full rounded-full bg-warning-500" style={{ width: `${total ? (count / total) * 100 : 0}%` }} />
                    </span>
                    <span className="w-6 text-right">{count}</span>
                  </li>
                ))}
            </ul>
          </div>
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {reviews.data.map((review) => (
              <li key={review.id} className="py-4 first:pt-0">
                <div className="flex items-center gap-2">
                  <Stars value={review.rating} size="h-3.5 w-3.5" />
                  <span className="text-sm font-medium text-gray-800 dark:text-white/90">{review.author}</span>
                  <span className="text-xs text-gray-400">{formatDate(review.created_at)}</span>
                </div>
                {review.variation && review.variation !== 'Default' && <p className="mt-1 text-xs text-gray-500">Variation: {review.variation}</p>}
                {review.comment && <p className="mt-2 text-sm text-gray-700 dark:text-gray-300">{review.comment}</p>}
                {review.seller_reply && (
                  <p className="mt-2 rounded-lg bg-gray-50 p-3 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-400">
                    <span className="font-medium">Seller:</span> {review.seller_reply}
                  </p>
                )}
              </li>
            ))}
            {reviews.meta.last_page > 1 && (
              <li className="flex justify-center gap-2 pt-4">
                {reviews.links
                  .filter((link) => link.url && !Number.isNaN(Number(link.label)))
                  .map((link) => (
                    <Link key={link.label} href={link.url} preserveScroll only={['reviews']} className={`flex h-8 w-8 items-center justify-center rounded-lg text-sm ${link.active ? 'bg-brand-500 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400'}`}>
                      {link.label}
                    </Link>
                  ))}
              </li>
            )}
          </ul>
        </div>
      )}
    </Card>
  );
}

export default function Product({ product, reviews, related, shopStats }) {
  const { auth } = usePage().props;
  const defaultVariation = product.variations.find((variation) => variation.is_default && variation.available > 0) ?? product.variations.find((variation) => variation.available > 0) ?? product.variations[0];
  const [variationId, setVariationId] = useState(defaultVariation?.id);
  const variation = useMemo(() => product.variations.find((item) => item.id === variationId), [product.variations, variationId]);
  const form = useForm({ variation_id: variationId, quantity: 1, buy_now: false });
  const soldOut = !variation || variation.available < 1;
  const unavailable = product.shop.vacation_mode || soldOut;
  const isCustomer = auth?.user?.roles?.includes('customer');

  const submit = (buyNow) => {
    if (!auth?.user) {
      router.visit('/login');

      return;
    }

    form.transform((data) => ({ ...data, variation_id: variationId, buy_now: buyNow }));
    form.post('/cart/items', { preserveScroll: true });
  };

  const showVariations = product.variations.length > 1 || (product.variations[0] && product.variations[0].label !== 'Default');

  return (
    <CustomerLayout>
      <Head title={product.name} />

      <nav aria-label="Breadcrumb" className="mb-4 text-xs text-gray-500">
        <Link href="/" className="hover:text-brand-600">Home</Link>
        {product.category && (
          <>
            {' / '}
            <Link href={`/search?category=${product.category.slug}`} className="hover:text-brand-600">{product.category.name}</Link>
          </>
        )}
        {' / '}
        <span className="text-gray-700 dark:text-gray-300">{product.name}</span>
      </nav>

      <div className="grid gap-8 lg:grid-cols-2">
        <Gallery images={product.images} name={product.name} />

        <div className="min-w-0">
          <h1 className="text-2xl font-semibold text-gray-800 dark:text-white/90">{product.name}</h1>
          <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-gray-500">
            {product.rating_count > 0 ? (
              <a href="#reviews" className="flex items-center gap-1.5 hover:text-brand-600">
                <Stars value={product.rating_avg} size="h-4 w-4" /> {product.rating_avg.toFixed(1)} ({product.rating_count})
              </a>
            ) : (
              <span>No ratings yet</span>
            )}
            <span aria-hidden="true">·</span>
            <span>{formatNumber(product.sold_count)} sold</span>
            {product.brand && (
              <>
                <span aria-hidden="true">·</span>
                <span>{product.brand}</span>
              </>
            )}
          </div>

          <div className="mt-5 rounded-2xl bg-gray-100/70 p-4 dark:bg-white/5">
            <p className="text-3xl font-bold text-brand-700 dark:text-brand-400">{formatMoney(variation?.price ?? product.price_range.min)}</p>
            {variation?.compare_at_price && Number(variation.compare_at_price) > Number(variation.price) && <p className="text-sm text-gray-400 line-through">{formatMoney(variation.compare_at_price)}</p>}
            {product.discount_percent && (
              <p className="mt-1 text-sm font-medium text-orange-600">
                {Math.round(product.discount_percent)}% off · {product.promotion?.name}
                {product.promotion?.ends_at && ` · until ${formatDate(product.promotion.ends_at)}`}
              </p>
            )}
          </div>

          {showVariations && (
            <fieldset className="mt-6">
              <legend className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Variation</legend>
              <div className="flex flex-wrap gap-2">
                {product.variations.map((item) => (
                  <button
                    key={item.id}
                    type="button"
                    aria-pressed={item.id === variationId}
                    onClick={() => {
                      setVariationId(item.id);
                      form.setData('quantity', 1);
                    }}
                    className={`rounded-lg border px-3 py-2 text-sm ${item.id === variationId ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400' : 'border-gray-300 text-gray-700 hover:border-brand-300 dark:border-gray-700 dark:text-gray-300'} ${item.available < 1 ? 'opacity-50 line-through' : ''}`}
                  >
                    {item.label}
                  </button>
                ))}
              </div>
            </fieldset>
          )}

          <div className="mt-6 flex flex-wrap items-center gap-4">
            <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</span>
            <QuantityInput value={form.data.quantity} max={Math.max(1, Math.min(99, variation?.available ?? 1))} disabled={unavailable} onChange={(value) => form.setData('quantity', value)} />
            <span className={`text-sm ${soldOut ? 'text-error-500' : 'text-gray-500'}`}>{soldOut ? 'Out of stock' : `${formatNumber(variation.available)} available`}</span>
          </div>
          {(form.errors.quantity || form.errors.variation_id) && <p className="mt-2 text-sm text-error-500">{form.errors.quantity ?? form.errors.variation_id}</p>}

          {product.shop.vacation_mode && (
            <p className="mt-4 rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-orange-400">
              This shop is on vacation{product.shop.vacation_message ? `: ${product.shop.vacation_message}` : '.'}
            </p>
          )}

          {auth?.user && !isCustomer ? (
            <p className="mt-6 text-sm text-gray-500">Staff accounts cannot place orders. Sign in with a customer account to buy.</p>
          ) : (
            <div className="mt-6 flex flex-col gap-3 sm:flex-row">
              <Button variant="secondary" size="lg" className="flex-1" disabled={unavailable} loading={form.processing && !form.data.buy_now} onClick={() => submit(false)}>
                Add to cart
              </Button>
              <Button size="lg" className="flex-1" disabled={unavailable} loading={form.processing && form.data.buy_now} onClick={() => submit(true)}>
                Buy now
              </Button>
            </div>
          )}

          <p className="mt-4 text-xs text-gray-500">Cash on Delivery · Delivered by local Onetap riders</p>

          <div className="mt-6 flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <ShopLogo shop={product.shop} />
            <div className="min-w-0 flex-1">
              <Link href={`/shops/${product.shop.slug}`} className="font-semibold text-gray-800 hover:text-brand-600 dark:text-white/90">
                {product.shop.name}
              </Link>
              <p className="text-xs text-gray-500">
                {[product.shop.city, `${shopStats.products} products`, product.shop.rating_avg > 0 ? `${product.shop.rating_avg.toFixed(1)}★` : null].filter(Boolean).join(' · ')}
              </p>
            </div>
            {isCustomer && (
              <Button variant="secondary" size="sm" onClick={() => router.post(`/account/chat/shops/${product.shop.id}`)}>
                Chat
              </Button>
            )}
          </div>
        </div>
      </div>

      {product.description && (
        <Card title="Product details" className="mt-8">
          <p className="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">{product.description}</p>
          {product.weight_grams > 0 && <p className="mt-4 text-xs text-gray-500">Weight: {formatNumber(product.weight_grams)} g</p>}
        </Card>
      )}

      <div id="reviews">
        <Reviews product={product} reviews={reviews} />
      </div>

      {related.length > 0 && (
        <section className="mt-10">
          <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">You may also like</h2>
          <ProductGrid products={related} columns="grid-cols-2 sm:grid-cols-3 lg:grid-cols-6" />
        </section>
      )}
    </CustomerLayout>
  );
}
