import { Head } from '@inertiajs/react';
import ActionButton from '../../../Components/admin/ActionButton';
import DetailList from '../../../Components/admin/DetailList';
import AssignRider from '../../../Components/logistics/AssignRider';
import { Card, DataTable, PageHeader, StatusBadge } from '../../../Components/ui';
import LogisticsLayout from '../../../Layouts/LogisticsLayout';
import { formatDateTime, formatMoney, headline } from '../../../lib/format';

const address = (value) => [value?.street, value?.barangay, value?.city, value?.province].filter(Boolean).join(', ');

export default function Show({ shipment, riders }) {
  return (
    <LogisticsLayout>
      <Head title={shipment.tracking_number} />
      <PageHeader
        title={shipment.tracking_number}
        description={<span className="inline-flex items-center gap-2"><StatusBadge status={shipment.status} /> Order {shipment.order} · {shipment.shop}</span>}
        breadcrumbs={[{ label: 'Shipments', href: '/logistics/shipments' }, { label: shipment.tracking_number }]}
        actions={
          <>
            {shipment.can.assign && <AssignRider shipment={shipment} riders={riders} />}
            {shipment.can.unassign && <ActionButton url={`/logistics/shipments/${shipment.id}/unassign`} label="Remove rider" message="The shipment goes back to the pickup queue." fields={[{ name: 'reason', label: 'Reason', type: 'text', required: true }]} />}
            {shipment.can.reschedule && (
              <ActionButton
                url={`/logistics/shipments/${shipment.id}/reschedule`}
                label="Reschedule"
                variant="primary"
                message="Plan another delivery attempt, then assign a rider."
                fields={[{ name: 'note', label: 'Note (e.g. customer asked for tomorrow afternoon)', type: 'text', required: true }]}
              />
            )}
            {shipment.can.return && (
              <ActionButton
                url={`/logistics/shipments/${shipment.id}/return`}
                label="Return to seller"
                variant="danger"
                tone="danger"
                message="Confirm the parcel was brought back to the shop. The order is closed as returned and the items go back into the seller's stock."
                fields={[{ name: 'note', label: 'Reason', type: 'textarea', required: true }]}
              />
            )}
          </>
        }
      />

      <div className="grid gap-6 xl:grid-cols-3">
        <div className="space-y-6 xl:col-span-2">
          <div className="grid gap-6 md:grid-cols-2">
            <Card title="Pick up">
              <DetailList columns={1} items={[{ label: 'Shop', value: shipment.pickup_address?.name }, { label: 'Phone', value: shipment.pickup_address?.phone }, { label: 'Address', value: address(shipment.pickup_address) }]} />
            </Card>
            <Card title="Deliver to">
              <DetailList columns={1} items={[{ label: 'Recipient', value: shipment.dropoff_address?.recipient_name }, { label: 'Phone', value: shipment.dropoff_address?.phone }, { label: 'Address', value: address(shipment.dropoff_address) }]} />
            </Card>
          </div>
          <Card title="Parcel" bodyClassName="">
            <DataTable rowKey={(row) => `${row.name}-${row.variation}`} rows={shipment.items} columns={[{ key: 'name', label: 'Item', render: (row) => <span>{row.name}{row.variation !== 'Default' && <span className="block text-xs text-gray-500">{row.variation}</span>}</span> }, { key: 'quantity', label: 'Qty' }]} />
            <dl className="space-y-1.5 border-t border-gray-100 px-5 py-4 text-sm dark:border-gray-800">
              <div className="flex justify-between font-semibold text-gray-800 dark:text-white/90"><dt>Collect on delivery</dt><dd>{formatMoney(shipment.cod_amount)}</dd></div>
              <div className="flex justify-between text-gray-600 dark:text-gray-400"><dt>Your delivery fee</dt><dd>{formatMoney(shipment.delivery_fee)}</dd></div>
            </dl>
          </Card>
          {(shipment.proof || shipment.collection) && (
            <Card title="Proof of delivery & COD">
              <DetailList
                items={[
                  shipment.proof && { label: 'Received by', value: `${shipment.proof.recipient_name}${shipment.proof.recipient_relation ? ` (${shipment.proof.recipient_relation})` : ''}` },
                  shipment.proof && { label: 'Delivered', value: formatDateTime(shipment.proof.delivered_at) },
                  shipment.proof && {
                    label: 'GPS',
                    value: shipment.proof.has_location ? (
                      <a href={`https://www.google.com/maps?q=${shipment.proof.lat},${shipment.proof.lng}`} target="_blank" rel="noreferrer" className="text-brand-600 hover:underline dark:text-brand-400">
                        {Number(shipment.proof.lat).toFixed(5)}, {Number(shipment.proof.lng).toFixed(5)}
                      </a>
                    ) : 'Not shared',
                  },
                  shipment.proof && {
                    label: 'Photo',
                    value: (
                      <a href={shipment.proof.photo_url} target="_blank" rel="noreferrer">
                        <img src={shipment.proof.photo_url} alt="Proof of delivery" className="h-32 w-auto rounded-lg border border-gray-200 object-cover dark:border-gray-800" />
                      </a>
                    ),
                  },
                  shipment.collection && { label: 'Cash collected', value: `${formatMoney(shipment.collection.amount_collected)} of ${formatMoney(shipment.collection.amount_due)}` },
                  shipment.collection && { label: 'Remittance', value: <StatusBadge status={shipment.collection.status} /> },
                ]}
              />
            </Card>
          )}
        </div>

        <div className="space-y-6">
          <Card title="Rider jobs">
            {shipment.assignments.length ? (
              <ul className="space-y-3 text-sm">
                {shipment.assignments.map((job) => (
                  <li key={job.id} className="flex items-start justify-between gap-3">
                    <span>
                      <span className="font-medium text-gray-800 dark:text-white/90">{job.rider}</span>
                      <span className="block text-xs text-gray-500">{headline(job.type)} · {job.phone} · {formatDateTime(job.created_at)}</span>
                    </span>
                    <StatusBadge status={job.status} />
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-gray-500">No rider yet.</p>
            )}
          </Card>
          <Card title="Tracking history">
            <ol className="space-y-3 text-sm">
              {shipment.history.map((entry) => (
                <li key={entry.id}>
                  <span className="font-medium text-gray-800 dark:text-white/90">{headline(entry.to)}</span>
                  <span className="block text-xs text-gray-500">
                    {formatDateTime(entry.created_at)}
                    {entry.user && ` · ${entry.user}`}
                    {entry.has_location && ' · 📍'}
                  </span>
                  {entry.note && <span className="block text-xs text-gray-500">{entry.note}</span>}
                </li>
              ))}
            </ol>
          </Card>
        </div>
      </div>
    </LogisticsLayout>
  );
}
