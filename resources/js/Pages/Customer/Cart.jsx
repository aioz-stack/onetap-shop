import { Head, Link, router } from '@inertiajs/react';
import { ProductImage } from '../../Components/store/ProductCard';
import QuantityInput from '../../Components/store/QuantityInput';
import { Button, Card, EmptyState } from '../../Components/ui';
import CustomerLayout from '../../Layouts/CustomerLayout';
import { formatMoney } from '../../lib/format';

const options = { preserveScroll: true, preserveState: true };

function Checkbox({ checked, onChange, label }) {
  return <input type="checkbox" aria-label={label} checked={checked} onChange={(event) => onChange(event.target.checked)} className="h-4.5 w-4.5 shrink-0 cursor-pointer rounded accent-brand-500" />;
}

export default function Cart({ groups, summary, hasAddress }) {
  const allItems = groups.flatMap((group) => group.items);
  const allSelected = allItems.length > 0 && allItems.every((item) => item.is_selected);

  if (!allItems.length) {
    return (
      <CustomerLayout>
        <Head title="Cart" />
        <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <EmptyState title="Your cart is empty" description="Browse local shops and add something you like." icon="◌" action={<Button href="/search">Start shopping</Button>} />
        </div>
      </CustomerLayout>
    );
  }

  return (
    <CustomerLayout>
      <Head title="Cart" />
      <h1 className="mb-5 text-xl font-semibold text-gray-800 dark:text-white/90">Shopping cart</h1>

      <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
        <div className="min-w-0 space-y-4">
          <label className="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
            <Checkbox label="Select all items" checked={allSelected} onChange={(selected) => router.post('/cart/select', { selected }, options)} />
            Select all ({allItems.length})
          </label>

          {groups.map((group) => (
            <Card key={group.shop.id} bodyClassName="">
              <div className="flex items-center gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <Checkbox
                  label={`Select all from ${group.shop.name}`}
                  checked={group.items.every((item) => item.is_selected)}
                  onChange={(selected) => router.post('/cart/select', { selected, shop_id: group.shop.id }, options)}
                />
                <Link href={`/shops/${group.shop.slug}`} className="font-semibold text-gray-800 hover:text-brand-600 dark:text-white/90">
                  {group.shop.name}
                </Link>
                {group.shop.vacation_mode && <span className="text-xs text-warning-600">On vacation</span>}
              </div>

              <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                {group.items.map((item) => (
                  <li key={item.id} className="flex gap-3 px-4 py-4">
                    <Checkbox label={`Select ${item.product.name}`} checked={item.is_selected} onChange={(selected) => router.patch(`/cart/items/${item.id}`, { is_selected: selected }, options)} />
                    <Link href={`/products/${item.product.slug}`} className="h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-gray-50 dark:bg-white/5">
                      <ProductImage src={item.product.image ? `/storage/${item.product.image}` : null} name={item.product.name} />
                    </Link>
                    <div className="min-w-0 flex-1">
                      <Link href={`/products/${item.product.slug}`} className="line-clamp-2 text-sm text-gray-800 hover:text-brand-600 dark:text-white/90">
                        {item.product.name}
                      </Link>
                      {item.variation.label !== 'Default' && <p className="text-xs text-gray-500">{item.variation.label}</p>}
                      {item.problem && <p className="mt-1 text-xs font-medium text-error-500">{item.problem}</p>}
                      <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                        <QuantityInput value={item.quantity} max={Math.max(1, Math.min(99, item.available || item.quantity))} onChange={(quantity) => router.patch(`/cart/items/${item.id}`, { quantity }, options)} />
                        <div className="text-right">
                          <p className="font-semibold text-gray-800 dark:text-white/90">{formatMoney(item.line_total)}</p>
                          {item.quantity > 1 && <p className="text-xs text-gray-500">{formatMoney(item.unit_price)} each</p>}
                        </div>
                      </div>
                      <button type="button" onClick={() => router.delete(`/cart/items/${item.id}`, options)} className="mt-2 text-xs text-gray-500 hover:text-error-600">
                        Remove
                      </button>
                    </div>
                  </li>
                ))}
              </ul>

              {group.shipping && (
                <div className="flex justify-between border-t border-gray-100 px-4 py-3 text-sm text-gray-600 dark:border-gray-800 dark:text-gray-400">
                  <span>{group.shipping.free ? 'Free delivery' : 'Delivery fee'}</span>
                  <span>{formatMoney(group.shipping.fee)}</span>
                </div>
              )}
            </Card>
          ))}
        </div>

        <aside className="min-w-0">
          <Card title="Order summary" className="lg:sticky lg:top-36">
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between text-gray-600 dark:text-gray-400">
                <dt>Items ({summary.selected_quantity})</dt>
                <dd>{formatMoney(summary.items_subtotal)}</dd>
              </div>
              <div className="flex justify-between text-gray-600 dark:text-gray-400">
                <dt>Delivery</dt>
                <dd>{formatMoney(summary.shipping_total)}</dd>
              </div>
              <div className="flex justify-between border-t border-gray-100 pt-3 text-base font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90">
                <dt>Total</dt>
                <dd>{formatMoney(summary.grand_total)}</dd>
              </div>
            </dl>
            {!hasAddress && <p className="mt-3 text-xs text-gray-500">Delivery fees are estimates until you add an address at checkout.</p>}
            <Button href="/checkout" size="lg" className={`mt-5 w-full ${summary.selected_lines ? '' : 'pointer-events-none opacity-50'}`} aria-disabled={!summary.selected_lines}>
              Check out
            </Button>
          </Card>
        </aside>
      </div>
    </CustomerLayout>
  );
}
