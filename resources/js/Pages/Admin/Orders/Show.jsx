import { Head, Link } from '@inertiajs/react';
import ActionButton from '../../../Components/admin/ActionButton';
import DetailList from '../../../Components/admin/DetailList';
import { Card, DataTable, PageHeader, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDateTime, formatMoney, headline } from '../../../lib/format';

export default function Show({ order }) {
  return (
    <AdminLayout>
      <Head title={order.reference} />
      <PageHeader
        title={order.reference}
        description={<span className="inline-flex items-center gap-2"><StatusBadge status={order.status} /> Part of checkout {order.order.order_number}</span>}
        breadcrumbs={[{ label: 'Orders', href: '/admin/orders' }, { label: order.reference }]}
        actions={
          order.can_cancel && (
            <ActionButton
              url={`/admin/orders/${order.id}/cancel`}
              label="Cancel order"
              variant="danger"
              tone="danger"
              message="Cancelling releases the reserved stock back to the seller. This cannot be undone."
              fields={[{ name: 'reason', label: 'Reason', type: 'textarea', required: true }]}
            />
          )
        }
      />

      {order.cancelled_reason && (
        <div className="mb-6 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-500">
          <strong>Cancelled:</strong> {order.cancelled_reason}
        </div>
      )}

      <div className="grid gap-6 xl:grid-cols-3">
        <Card title="Items" className="xl:col-span-2" bodyClassName="">
          <DataTable
            rows={order.items}
            columns={[
              { key: 'product_name', label: 'Product', render: (row) => <Link className="hover:text-brand-600" href={`/admin/products/${row.product_id}`}>{row.product_name}<span className="block text-xs text-gray-500">{row.variation_label}</span></Link> },
              { key: 'unit_price', label: 'Price', render: (row) => formatMoney(row.unit_price) },
              { key: 'quantity', label: 'Qty' },
              { key: 'line_total', label: 'Total', className: 'text-right', headerClassName: 'text-right', render: (row) => formatMoney(row.line_total) },
            ]}
          />
          <dl className="space-y-2 border-t border-gray-100 px-5 py-4 text-sm dark:border-gray-800">
            {[
              ['Items subtotal', order.items_subtotal],
              ['Shipping fee', order.shipping_fee],
              ['Discount', `-${order.discount}`],
            ].map(([label, value]) => (
              <div key={label} className="flex justify-between text-gray-600 dark:text-gray-400">
                <dt>{label}</dt>
                <dd>{formatMoney(value)}</dd>
              </div>
            ))}
            <div className="flex justify-between font-semibold text-gray-800 dark:text-white/90">
              <dt>Order total</dt>
              <dd>{formatMoney(order.total)}</dd>
            </div>
            <div className="flex justify-between text-xs text-gray-500">
              <dt>Commission ({order.commission_rate}%) · seller earns</dt>
              <dd>
                {formatMoney(order.commission_amount)} · {formatMoney(order.seller_earning)}
              </dd>
            </div>
          </dl>
        </Card>

        <div className="space-y-6">
          <Card title="Customer">
            <DetailList
              columns={1}
              items={[
                { label: 'Name', value: order.customer ? <Link className="text-brand-600 hover:underline" href={`/admin/users/${order.customer.id}`}>{order.customer.name}</Link> : '—' },
                { label: 'Contact', value: [order.customer?.email, order.customer?.phone].filter(Boolean).join(' · ') },
                { label: 'Deliver to', value: order.address ? `${order.address.recipient_name}, ${order.address.phone} — ${[order.address.street, order.address.barangay, order.address.city, order.address.province].filter(Boolean).join(', ')}` : '—' },
              ]}
            />
          </Card>
          <Card title="Payment & shop">
            <DetailList
              columns={1}
              items={[
                { label: 'Payment', value: <span className="uppercase">{order.order.payment_method} · <StatusBadge status={order.order.payment_status} /></span> },
                { label: 'Shop', value: order.shop?.name },
                { label: 'Placed', value: formatDateTime(order.order.placed_at) },
                order.order.notes && { label: 'Customer note', value: order.order.notes },
              ]}
            />
          </Card>
          <Card title="Shipment">
            {order.shipment ? (
              <DetailList
                columns={1}
                items={[
                  { label: 'Tracking', value: order.shipment.tracking_number },
                  { label: 'Status', value: <StatusBadge status={order.shipment.status} /> },
                  { label: 'Partner / rider', value: `${order.shipment.partner ?? '—'} / ${order.shipment.rider ?? 'unassigned'}` },
                  { label: 'COD to collect', value: formatMoney(order.shipment.cod_amount) },
                ]}
              />
            ) : (
              <p className="text-sm text-gray-500">No shipment yet. One is created when the seller marks the order ready for pickup.</p>
            )}
          </Card>
        </div>
      </div>

      <Card className="mt-6" title="Status history">
        <ol className="relative space-y-5 border-l border-gray-200 pl-6 dark:border-gray-800">
          {order.history.map((entry) => (
            <li key={entry.id}>
              <span className="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full border-2 border-white bg-brand-500 dark:border-gray-900" aria-hidden="true" />
              <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                {entry.from ? `${headline(entry.from)} → ` : ''}
                {headline(entry.to)}
              </p>
              <p className="text-xs text-gray-500">
                {formatDateTime(entry.created_at)}
                {entry.user && ` · ${entry.user}`}
                {entry.note && ` · ${entry.note}`}
              </p>
            </li>
          ))}
        </ol>
      </Card>
    </AdminLayout>
  );
}
