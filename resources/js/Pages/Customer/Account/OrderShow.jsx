import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { AddressText } from '../../../Components/store/AddressForm';
import OrderActions from '../../../Components/store/OrderActions';
import { ProductImage } from '../../../Components/store/ProductCard';
import { StarInput } from '../../../Components/store/Stars';
import { Button, Card, FormField, Modal, StatusBadge, Textarea } from '../../../Components/ui';
import AccountLayout from '../../../Layouts/AccountLayout';
import { formatDateTime, formatMoney } from '../../../lib/format';

const steps = [
  ['pending', 'Placed'],
  ['processing', 'Preparing'],
  ['ready_for_pickup', 'Packed'],
  ['shipping', 'On the way'],
  ['delivered', 'Delivered'],
  ['completed', 'Completed'],
];

function Progress({ status }) {
  const index = steps.findIndex(([key]) => key === status);

  if (index < 0) {
    return null;
  }

  return (
    <ol className="grid grid-cols-6 gap-1">
      {steps.map(([key, label], step) => (
        <li key={key} className="text-center">
          <span className={`mx-auto block h-1.5 rounded-full ${step <= index ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-800'}`} />
          <span className={`mt-2 block text-[11px] sm:text-xs ${step <= index ? 'font-medium text-brand-700 dark:text-brand-400' : 'text-gray-400'}`}>{label}</span>
        </li>
      ))}
    </ol>
  );
}

function ReviewForm({ item, onClose }) {
  const form = useForm({ rating: 5, comment: '' });

  return (
    <Modal
      open
      onClose={onClose}
      title="Rate this item"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button loading={form.processing} onClick={() => form.post(`/account/items/${item.id}/review`, { preserveScroll: true, onSuccess: onClose })}>
            Submit review
          </Button>
        </>
      }
    >
      <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">{item.product_name}</p>
      <StarInput value={form.data.rating} onChange={(rating) => form.setData('rating', rating)} />
      {form.errors.rating && <p className="mt-2 text-xs text-error-500">{form.errors.rating}</p>}
      <FormField label="Your review (optional)" className="mt-4" error={form.errors.comment}>
        {(id) => <Textarea id={id} rows={4} value={form.data.comment} placeholder="How was the quality, freshness, packaging?" onChange={(event) => form.setData('comment', event.target.value)} />}
      </FormField>
    </Modal>
  );
}

export default function OrderShow({ order }) {
  const [reviewing, setReviewing] = useState(null);

  return (
    <AccountLayout>
      <Head title={order.reference} />
      <Link href="/account/orders" className="text-sm text-gray-500 hover:text-brand-600">
        ← My purchases
      </Link>

      <div className="mt-3 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold text-gray-800 dark:text-white/90">{order.reference}</h1>
          <p className="text-sm text-gray-500">
            Placed {formatDateTime(order.placed_at)} · <StatusBadge status={order.status} />
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <OrderActions order={order} showDetails={false} />
        </div>
      </div>

      {order.cancelled_reason && <p className="mt-4 rounded-xl bg-error-50 px-4 py-3 text-sm text-error-700 dark:bg-error-500/10 dark:text-error-500">{order.cancelled_reason}</p>}

      <Card className="mt-5">
        <Progress status={order.status} />
        {order.shipment && (
          <p className="mt-4 text-sm text-gray-600 dark:text-gray-400">
            Tracking <span className="font-mono">{order.shipment.tracking_number}</span> · {order.shipment.status_label}
          </p>
        )}
      </Card>

      <div className="mt-5 grid gap-5 xl:grid-cols-3">
        <Card title={order.shop?.name} className="xl:col-span-2" bodyClassName="" actions={<Link href={`/shops/${order.shop?.slug}`} className="text-sm text-brand-600">Visit shop</Link>}>
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {order.items.map((item) => (
              <li key={item.id} className="flex items-center gap-3 px-5 py-3">
                <span className="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-gray-50 dark:bg-white/5">
                  <ProductImage src={item.image_url} name={item.product_name} />
                </span>
                <span className="min-w-0 flex-1 text-sm">
                  {item.product_slug ? (
                    <Link href={`/products/${item.product_slug}`} className="line-clamp-1 text-gray-800 hover:text-brand-600 dark:text-white/90">{item.product_name}</Link>
                  ) : (
                    <span className="line-clamp-1 text-gray-800 dark:text-white/90">{item.product_name}</span>
                  )}
                  <span className="text-xs text-gray-500">
                    {item.variation_label !== 'Default' ? `${item.variation_label} · ` : ''}
                    {item.quantity} × {formatMoney(item.unit_price)}
                  </span>
                </span>
                <span className="flex flex-col items-end gap-1 text-sm">
                  {formatMoney(item.line_total)}
                  {order.can.review && !item.reviewed && (
                    <Button size="sm" variant="ghost" onClick={() => setReviewing(item)}>
                      Rate
                    </Button>
                  )}
                  {item.reviewed && <span className="text-xs text-success-600">Reviewed</span>}
                </span>
              </li>
            ))}
          </ul>
          <dl className="space-y-1.5 border-t border-gray-100 px-5 py-4 text-sm dark:border-gray-800">
            <div className="flex justify-between text-gray-600 dark:text-gray-400"><dt>Items</dt><dd>{formatMoney(order.items_subtotal)}</dd></div>
            <div className="flex justify-between text-gray-600 dark:text-gray-400"><dt>Delivery</dt><dd>{formatMoney(order.shipping_fee)}</dd></div>
            <div className="flex justify-between font-semibold text-gray-800 dark:text-white/90"><dt>Total</dt><dd>{formatMoney(order.total)}</dd></div>
            <div className="flex justify-between text-xs text-gray-500"><dt>Payment</dt><dd className="uppercase">{order.payment_method} · {order.payment_status}</dd></div>
          </dl>
        </Card>

        <div className="space-y-5">
          <Card title="Delivery address">
            <p className="text-sm"><AddressText address={order.address} /></p>
          </Card>
          <Card title="History">
            <ol className="space-y-3 text-sm">
              {[...order.timeline].reverse().map((entry, index) => (
                <li key={`${entry.status}-${index}`} className="flex gap-3">
                  <span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${index === 0 ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-700'}`} />
                  <span>
                    <span className="font-medium text-gray-800 dark:text-white/90">{entry.label}</span>
                    {entry.note && <span className="block text-xs text-gray-500">{entry.note}</span>}
                    <span className="block text-xs text-gray-400">{formatDateTime(entry.at)}</span>
                  </span>
                </li>
              ))}
            </ol>
          </Card>
        </div>
      </div>

      {reviewing && <ReviewForm item={reviewing} onClose={() => setReviewing(null)} />}
    </AccountLayout>
  );
}
