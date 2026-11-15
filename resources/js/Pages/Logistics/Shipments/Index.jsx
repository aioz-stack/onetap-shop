import { Head, Link } from '@inertiajs/react';
import { FilterBar, FilterSelect } from '../../../Components/admin/Filters';
import StatusTabs from '../../../Components/admin/StatusTabs';
import AssignRider from '../../../Components/logistics/AssignRider';
import { Card, DataTable, EmptyState, PageHeader, Pagination, SearchInput, StatusBadge } from '../../../Components/ui';
import LogisticsLayout from '../../../Layouts/LogisticsLayout';
import { formatDateTime, formatMoney } from '../../../lib/format';

export default function Index({ shipments, tabs, filters, counts, riders }) {
  const canAssign = (row) => ['pending_pickup', 'assigned', 'accepted', 'rescheduled'].includes(row.status);

  return (
    <LogisticsLayout>
      <Head title="Shipments" />
      <PageHeader title="Shipments" description="Pickup requests from shops, and every parcel your riders are carrying." />
      <Card bodyClassName="">
        <StatusTabs tabs={tabs} param="tab" active={filters.tab} counts={counts} />
        <FilterBar>
          <SearchInput value={filters.search} placeholder="Tracking number or order" className="w-full sm:w-72" />
          <FilterSelect param="rider" value={filters.rider} options={riders} placeholder="Any rider" className="w-full sm:w-64" />
        </FilterBar>
        <DataTable
          rows={shipments.data}
          empty={<EmptyState title="Nothing here" description={filters.tab === 'incoming' ? 'New pickup requests appear when shops pack their orders.' : 'No shipments in this stage.'} />}
          columns={[
            { key: 'tracking_number', label: 'Shipment', render: (row) => <Link href={`/logistics/shipments/${row.id}`} className="hover:text-brand-600"><span className="block font-mono text-xs font-medium text-gray-800 dark:text-white/90">{row.tracking_number}</span><span className="text-xs text-gray-500">{row.order}</span></Link> },
            { key: 'pickup', label: 'Pick up at' },
            { key: 'dropoff', label: 'Deliver to' },
            { key: 'cod_amount', label: 'COD', className: 'whitespace-nowrap', render: (row) => formatMoney(row.cod_amount) },
            { key: 'rider', label: 'Rider', render: (row) => row.rider ?? <span className="text-gray-400">—</span> },
            { key: 'status', label: 'Status', render: (row) => <span><StatusBadge status={row.status} />{row.attempts > 0 && <span className="block text-xs text-gray-500">{row.attempts} attempt{row.attempts > 1 ? 's' : ''}</span>}{row.scheduled_at && <span className="block text-xs text-gray-500">Retry {formatDateTime(row.scheduled_at)}</span>}</span> },
            { key: 'actions', label: '', className: 'text-right', render: (row) => (canAssign(row) ? <AssignRider shipment={row} riders={riders} /> : <Link href={`/logistics/shipments/${row.id}`} className="text-sm text-brand-600">Open</Link>) },
          ]}
        />
        <Pagination paginator={shipments} />
      </Card>
    </LogisticsLayout>
  );
}
