import { Head, Link } from '@inertiajs/react';
import { icon } from '../../../Components/AppIcon';
import ActionButton from '../../../Components/admin/ActionButton';
import DetailList from '../../../Components/admin/DetailList';
import { Button, Card, DataTable, EmptyState, PageHeader, StatCard, StatusBadge } from '../../../Components/ui';
import LogisticsLayout from '../../../Layouts/LogisticsLayout';
import { formatDate, formatDateTime, formatMoney } from '../../../lib/format';

export default function Show({ rider, performance, jobs }) {
  return (
    <LogisticsLayout>
      <Head title={rider.name} />
      <PageHeader
        title={rider.name}
        description={<span className="inline-flex items-center gap-2"><StatusBadge status={rider.status} /><StatusBadge status={rider.availability} /></span>}
        breadcrumbs={[{ label: 'Riders', href: '/logistics/riders' }, { label: rider.name }]}
        actions={
          <>
            <Button size="sm" variant="secondary" href={`/logistics/riders/${rider.id}/edit`}>Edit</Button>
            {rider.status === 'active' ? (
              <ActionButton url={`/logistics/riders/${rider.id}/suspend`} label="Suspend" variant="danger" tone="danger" message="The rider goes offline. Pickups they have not collected go back to the queue." />
            ) : (
              <ActionButton url={`/logistics/riders/${rider.id}/approve`} label={rider.status === 'pending' ? 'Approve' : 'Reactivate'} variant="primary" message="The rider can receive deliveries." />
            )}
          </>
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Delivered" value={performance.delivered} icon={icon('clipboard')} tone="success" hint={performance.success_rate !== null ? `${performance.success_rate}% success rate` : 'No attempts yet'} />
        <StatCard label="Failed attempts" value={performance.failed_attempts} icon={icon('receipt')} tone={performance.failed_attempts ? 'error' : 'gray'} />
        <StatCard label="Avg. pickup to delivery" value={performance.avg_delivery_minutes !== null ? `${performance.avg_delivery_minutes} min` : '—'} icon={icon('truck')} tone="brand" hint={`${performance.open_jobs} open job(s)`} />
        <StatCard label="Cash on hand" value={formatMoney(performance.cod_unremitted)} icon={icon('cash')} tone={Number(performance.cod_unremitted) > 0 ? 'warning' : 'gray'} hint={`${formatMoney(performance.cod_collected)} collected all time`} />
      </div>

      <div className="mt-6 grid gap-6 xl:grid-cols-3">
        <Card title="Details">
          <DetailList
            columns={1}
            items={[
              { label: 'Phone', value: rider.phone },
              { label: 'Email', value: rider.email },
              { label: 'Username', value: rider.username },
              { label: 'Vehicle', value: `${rider.vehicle_type ?? '—'} · ${rider.plate_number ?? ''}` },
              { label: "Driver's license", value: rider.license_number },
              { label: 'Last seen', value: formatDateTime(rider.last_seen_at) },
              { label: 'Joined', value: formatDate(rider.joined_at) },
            ]}
          />
        </Card>
        <Card title="Recent jobs" className="xl:col-span-2" bodyClassName="">
          <DataTable
            rows={jobs}
            empty={<EmptyState title="No jobs yet" />}
            columns={[
              { key: 'tracking_number', label: 'Shipment', render: (row) => <Link className="font-mono text-xs hover:text-brand-600" href={`/logistics/shipments/${row.shipment_id}`}>{row.tracking_number}</Link> },
              { key: 'dropoff', label: 'Barangay' },
              { key: 'cod_amount', label: 'COD', render: (row) => formatMoney(row.cod_amount) },
              { key: 'shipment_status', label: 'Parcel', render: (row) => <StatusBadge status={row.shipment_status} /> },
              { key: 'status', label: 'Job', render: (row) => <StatusBadge status={row.status} /> },
              { key: 'created_at', label: 'Assigned', className: 'whitespace-nowrap text-gray-500', render: (row) => formatDateTime(row.created_at) },
            ]}
          />
        </Card>
      </div>
    </LogisticsLayout>
  );
}
