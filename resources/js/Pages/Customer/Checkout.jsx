import { useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AddressForm, { AddressText } from '../../Components/store/AddressForm';
import { ProductImage } from '../../Components/store/ProductCard';
import { Badge, Button, Card, Textarea, TextInput } from '../../Components/ui';
import CustomerLayout from '../../Layouts/CustomerLayout';
import { formatMoney } from '../../lib/format';

export default function Checkout({ groups, summary, addresses, paymentMethods, codLimit }) {
  const { errors: pageErrors } = usePage().props;
  const [addingAddress, setAddingAddress] = useState(false);
  const initialVouchers = Object.fromEntries(groups.filter((group) => group.voucher_code).map((group) => [group.shop.id, group.voucher_code]));
  const [codes, setCodes] = useState(initialVouchers);
  const form = useForm({ address_id: summary.address_id ?? '', payment_method: 'cod', notes: '', vouchers: initialVouchers });
  const overCodLimit = Number(codLimit) > 0 && Number(summary.grand_total) > Number(codLimit);

  const reload = (addressId, vouchers) => router.get('/checkout', { address: addressId || undefined, vouchers }, { preserveState: true, preserveScroll: true, only: ['groups', 'summary'] });

  const chooseAddress = (id) => {
    form.setData('address_id', id);
    reload(id, form.data.vouchers);
  };

  const applyVouchers = (next) => {
    const cleaned = Object.fromEntries(Object.entries(next).filter(([, code]) => code && code.trim()));
    form.setData('vouchers', cleaned);
    reload(form.data.address_id, cleaned);
  };

  const placeOrder = () => form.post('/checkout');
  const voucherError = Object.entries(form.errors).find(([key]) => key.startsWith('vouchers'))?.[1];
  const error = form.errors.cart ?? form.errors.payment_method ?? form.errors.address_id ?? voucherError ?? pageErrors?.cart;

  return (
    <CustomerLayout>
      <Head title="Checkout" />
      <h1 className="mb-5 text-xl font-semibold text-gray-800 dark:text-white/90">Checkout</h1>

      <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div className="min-w-0 space-y-5">
          <Card title="Delivery address" actions={<Button size="sm" variant="secondary" onClick={() => setAddingAddress(true)}>Add new</Button>}>
            {addresses.length ? (
              <ul className="space-y-2">
                {addresses.map((address) => (
                  <li key={address.id}>
                    <label className={`flex cursor-pointer gap-3 rounded-xl border p-3 text-sm ${Number(form.data.address_id) === address.id ? 'border-brand-500 bg-brand-50/50 dark:bg-brand-500/10' : 'border-gray-200 dark:border-gray-800'}`}>
                      <input type="radio" name="address" className="mt-1 accent-brand-500" checked={Number(form.data.address_id) === address.id} onChange={() => chooseAddress(address.id)} />
                      <span className="min-w-0">
                        <AddressText address={address} />
                        {address.is_default && <Badge tone="brand" className="mt-1">Default</Badge>}
                      </span>
                    </label>
                  </li>
                ))}
              </ul>
            ) : (
              <div className="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700">
                Add a delivery address to continue.
                <div className="mt-3">
                  <Button size="sm" onClick={() => setAddingAddress(true)}>Add address</Button>
                </div>
              </div>
            )}
          </Card>

          {groups.map((group) => (
            <Card key={group.shop.id} title={group.shop.name} bodyClassName="">
              <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                {group.items.map((item) => (
                  <li key={item.id} className="flex items-center gap-3 px-5 py-3">
                    <span className="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-gray-50 dark:bg-white/5">
                      <ProductImage src={item.product.image ? `/storage/${item.product.image}` : null} name={item.product.name} />
                    </span>
                    <span className="min-w-0 flex-1 text-sm">
                      <span className="line-clamp-1 text-gray-800 dark:text-white/90">{item.product.name}</span>
                      <span className="text-xs text-gray-500">
                        {item.variation.label !== 'Default' ? `${item.variation.label} · ` : ''}
                        {item.quantity} × {formatMoney(item.unit_price)}
                      </span>
                      {item.problem && <span className="block text-xs font-medium text-error-500">{item.problem}</span>}
                    </span>
                    <span className="text-sm font-medium text-gray-800 dark:text-white/90">{formatMoney(item.line_total)}</span>
                  </li>
                ))}
              </ul>
              <div className="space-y-1 border-t border-gray-100 px-5 py-3 text-sm dark:border-gray-800">
                <form
                  className="mb-2 flex gap-2"
                  onSubmit={(event) => {
                    event.preventDefault();
                    applyVouchers({ ...form.data.vouchers, [group.shop.id]: codes[group.shop.id] ?? '' });
                  }}
                >
                  <TextInput
                    aria-label={`Voucher code for ${group.shop.name}`}
                    placeholder="Voucher code (shop or Onetap)"
                    className="h-9 uppercase"
                    value={codes[group.shop.id] ?? ''}
                    onChange={(event) => setCodes({ ...codes, [group.shop.id]: event.target.value.toUpperCase() })}
                  />
                  <Button type="submit" size="sm" variant="secondary" className="h-9">
                    Apply
                  </Button>
                  {group.voucher && (
                    <Button
                      size="sm"
                      variant="ghost"
                      className="h-9"
                      onClick={() => {
                        setCodes({ ...codes, [group.shop.id]: '' });
                        applyVouchers({ ...form.data.vouchers, [group.shop.id]: '' });
                      }}
                    >
                      Remove
                    </Button>
                  )}
                </form>
                {group.voucher_error && <p className="text-xs text-error-500">{group.voucher_error}</p>}
                {group.voucher && (
                  <div className="flex justify-between text-success-600">
                    <span>
                      Voucher {group.voucher.code} · {group.voucher.label}
                    </span>
                    <span>−{formatMoney(group.voucher.discount)}</span>
                  </div>
                )}
                <div className="flex justify-between text-gray-600 dark:text-gray-400">
                  <span>Standard delivery {group.shipping && !group.shipping.covered && <span className="text-xs">(estimated)</span>}</span>
                  <span>{group.shipping?.free ? 'Free' : formatMoney(group.shipping?.fee ?? 0)}</span>
                </div>
                <div className="flex justify-between font-medium text-gray-800 dark:text-white/90">
                  <span>Shop total</span>
                  <span>{formatMoney(group.total)}</span>
                </div>
              </div>
            </Card>
          ))}

          <Card title="Note to sellers">
            <Textarea rows={2} aria-label="Note to sellers" placeholder="Landmarks, delivery instructions (optional)" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
          </Card>
        </div>

        <aside className="min-w-0 space-y-5">
          <Card title="Payment method">
            <ul className="space-y-2">
              {paymentMethods.map((method) => (
                <li key={method.value}>
                  <label className={`flex items-start gap-3 rounded-xl border p-3 text-sm ${!method.available ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'} ${form.data.payment_method === method.value ? 'border-brand-500 bg-brand-50/50 dark:bg-brand-500/10' : 'border-gray-200 dark:border-gray-800'}`}>
                    <input type="radio" name="payment" className="mt-1 accent-brand-500" disabled={!method.available} checked={form.data.payment_method === method.value} onChange={() => form.setData('payment_method', method.value)} />
                    <span>
                      <span className="block font-medium text-gray-800 dark:text-white/90">{method.label}</span>
                      <span className="text-xs text-gray-500">{method.note}</span>
                    </span>
                  </label>
                </li>
              ))}
            </ul>
          </Card>

          <Card title="Summary" className="lg:sticky lg:top-36">
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between text-gray-600 dark:text-gray-400">
                <dt>Items ({summary.selected_quantity})</dt>
                <dd>{formatMoney(summary.items_subtotal)}</dd>
              </div>
              <div className="flex justify-between text-gray-600 dark:text-gray-400">
                <dt>Delivery ({groups.length} shop{groups.length > 1 ? 's' : ''})</dt>
                <dd>{formatMoney(summary.shipping_total)}</dd>
              </div>
              {Number(summary.discount_total) > 0 && (
                <div className="flex justify-between text-success-600">
                  <dt>Vouchers</dt>
                  <dd>−{formatMoney(summary.discount_total)}</dd>
                </div>
              )}
              <div className="flex justify-between border-t border-gray-100 pt-3 text-base font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90">
                <dt>Total to pay on delivery</dt>
                <dd>{formatMoney(summary.grand_total)}</dd>
              </div>
            </dl>
            {groups.length > 1 && <p className="mt-3 text-xs text-gray-500">Each shop ships separately, so you may receive {groups.length} parcels.</p>}
            {overCodLimit && <p className="mt-3 text-xs text-error-500">Cash on Delivery is limited to {formatMoney(codLimit)} per order.</p>}
            {error && <p className="mt-3 rounded-lg bg-error-50 px-3 py-2 text-sm text-error-700 dark:bg-error-500/10 dark:text-error-500">{error}</p>}
            <Button size="lg" className="mt-5 w-full" loading={form.processing} disabled={!form.data.address_id || overCodLimit} onClick={placeOrder}>
              Place order
            </Button>
          </Card>
        </aside>
      </div>

      {addingAddress && <AddressForm onClose={() => setAddingAddress(false)} returnTo="checkout" />}
    </CustomerLayout>
  );
}
