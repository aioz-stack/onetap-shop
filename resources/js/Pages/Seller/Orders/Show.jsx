import { Head, Link } from '@inertiajs/react';
import ActionButton from '../../../Components/admin/ActionButton';
import DetailList from '../../../Components/admin/DetailList';
import { Card, DataTable, PageHeader, StatusBadge } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';
import { formatDateTime, formatMoney, headline } from '../../../lib/format';

export default function Show({ order }) {
  return (
    <SellerLayout>
      <Head title={order.reference} />
      <PageHeader
        title={order.reference}
        description={<span className="inline-flex items-center gap-2"><StatusBadge status={order.status} /> Placed {formatDateTime(order.placed_at)} · {order.payment_method.toUpperCase()}</span>}
        breadcrumbs={[{ label: 'Orders', href: '/seller/orders' }, { label: order.reference }]}
        actions={
          <>
            {order.can.accept && <ActionButton url={`/seller/orders/${order.id}/accept`} label="Accept order" variant="primary" message="Start preparing this order." />}
            {order.can.ready && <ActionButton url={`/seller/orders/${order.id}/ready`} label="Ready for pickup" variant="primary" message="Confirm the parcel is packed. A shipment is created and a rider will collect it." />}
            {order.can.cancel && (
              <ActionButton url={`/seller/orders/${order.id}/cancel`} label="Cancel" variant="danger" tone="danger" message="The customer is notified and the stock goes back to your inventory." fields={[{ name: 'reason', label: 'Reason for the customer', type: 'textarea', required: true }]} />
            )}
          </>
        }
      />
      {order.cancelled_reason && <p className="mb-5 rounded-xl bg-error-50 px-4 py-3 text-sm text-error-700 dark:bg-error-500/10 dark:text-error-500">{order.cancelled_reason}</p>}

      <div className="grid gap-6 xl:grid-cols-3">
        <Card title="Items to pack" className="xl:col-span-2" bodyClassName="">
          <DataTable
            rows={order.items}
            columns={[
              { key: 'product_name', label: 'Product', render: (row) => <span>{row.product_name}{row.variation_label !== 'Default' && <span className="block text-xs text-gray-500">{row.variation_label}</span>}</span> },
              { key: 'quantity', label: 'Qty', className: 'font-semibold' },
              { key: 'unit_price', label: 'Price', render: (row) => formatMoney(row.unit_price) },
              { key: 'line_total', label: 'Total', className: 'text-right', headerClassName: 'text-right', render: (row) => formatMoney(row.line_total) },
            ]}
          />
          <dl className="space-y-1.5 border-t border-gray-100 px-5 py-4 text-sm dark:border-gray-800">
            <div className="flex justify-between text-gray-600 dark:text-gray-400"><dt>Items</dt><dd>{formatMoney(order.items_subtotal)}</dd></div>
            {Number(order.discount) > 0 && <div className="flex justify-between text-success-600"><dt>Voucher {order.voucher}</dt><dd>−{formatMoney(order.discount)}</dd></div>}
            <div className="flex justify-between text-gray-600 dark:text-gray-400"><dt>Delivery (paid to logistics)</dt><dd>{formatMoney(order.shipping_fee)}</dd></div>
            <div className="flex justify-between font-semibold text-gray-800 dark:text-white/90"><dt>Customer pays</dt><dd>{formatMoney(order.total)}</dd></div>
            <div className="flex justify-between text-gray-600 dark:text-gray-400"><dt>Onetap commission ({order.commission_rate}%)</dt><dd>−{formatMoney(order.commission_amount)}</dd></div>
            <div className="flex justify-between font-semibold text-brand-700 dark:text-brand-400"><dt>You earn</dt><dd>{formatMoney(order.seller_earning)}</dd></div>
          </dl>
        </Card>

        <div className="space-y-6">
          <Card title="Deliver to">
            <DetailList
              columns={1}
              items={[
                { label: 'Recipient', value: order.address ? `${order.address.recipient_name} · ${order.address.phone}` : '—' },
                { label: 'Address', value: order.address ? [order.address.street, order.address.barangay, order.address.city, order.address.province].filter(Boolean).join(', ') : '—' },
                order.notes && { label: 'Customer note', value: order.notes },
              ]}
            />
          </Card>
          <Card title="Shipment">
            {order.shipment ? (
              <DetailList
                columns={1}
                items={[
                  { label: 'Tracking', value: <span className="font-mono">{order.shipment.tracking_number}</span> },
                  { label: 'Status', value: <StatusBadge status={order.shipment.status} /> },
                  { label: 'Logistics partner', value: order.shipment.partner ?? 'Waiting for assignment' },
                ]}
              />
            ) : (
              <p className="text-sm text-gray-500">Created when you mark the order ready for pickup.</p>
            )}
          </Card>
          {order.refunds.length > 0 && (
            <Card title="Return requests">
              <ul className="space-y-3 text-sm">
                {order.refunds.map((refund) => (
                  <li key={refund.id}>
                    <p className="font-medium text-gray-800 dark:text-white/90">{refund.reason} · {formatMoney(refund.amount)} <StatusBadge status={refund.status} /></p>
                    {refund.details && <p className="text-gray-500">{refund.details}</p>}
                  </li>
                ))}
              </ul>
              <p className="mt-3 text-xs text-gray-500">Onetap reviews return requests. Message the customer from <Link href="/seller/chat" className="text-brand-600">Chat</Link> if you need details.</p>
            </Card>
          )}
          <Card title="History">
            <ol className="space-y-3 text-sm">
              {[...order.history].reverse().map((entry) => (
                <li key={entry.id}>
                  <span className="font-medium text-gray-800 dark:text-white/90">{headline(entry.to)}</span>
                  <span className="block text-xs text-gray-500">{formatDateTime(entry.created_at)}{entry.user && ` · ${entry.user}`}{entry.note && ` · ${entry.note}`}</span>
                </li>
              ))}
            </ol>
          </Card>
        </div>
      </div>
    </SellerLayout>
  );
}
