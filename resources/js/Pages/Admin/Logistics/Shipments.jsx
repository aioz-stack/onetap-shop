import { Head, Link } from '@inertiajs/react';
import { FilterBar, FilterSelect } from '../../../Components/admin/Filters';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { Card, DataTable, EmptyState, PageHeader, Pagination, SearchInput, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDateTime, formatMoney } from '../../../lib/format';

export default function Shipments({ shipments, tabs, filters, counts, partners }) {
  return (
    <AdminLayout>
      <Head title="Shipments" />
      <PageHeader
        title="Pickup & shipment monitoring"
        description="Read-only view of every shipment. Partners and riders update them."
        breadcrumbs={[{ label: 'Logistics', href: '/admin/logistics/partners' }, { label: 'Shipments' }]}
      />

      <Card bodyClassName="">
        <StatusTabs tabs={tabs} param="tab" active={filters.tab} counts={counts} />
        <FilterBar>
          <SearchInput value={filters.search} placeholder="Tracking number or order reference" className="w-full sm:w-80" />
          <FilterSelect param="partner" value={filters.partner} options={partners.map((partner) => ({ value: partner.id, label: partner.name }))} placeholder="All partners" />
        </FilterBar>
        <DataTable
          rows={shipments.data}
          empty={<EmptyState title="No shipments here" description="Shipments are created when sellers mark orders ready for pickup." />}
          columns={[
            { key: 'tracking_number', label: 'Tracking', className: 'font-mono text-xs' },
            { key: 'order', label: 'Order', render: (row) => <Link className="hover:text-brand-600" href={`/admin/orders/${row.order?.id}`}>{row.order?.reference}<span className="block text-xs text-gray-500">{row.shop}</span></Link> },
            { key: 'partner', label: 'Partner / rider', render: (row) => <span>{row.partner ?? '—'}<span className="block text-xs text-gray-500">{row.rider ?? 'Unassigned'}</span></span> },
            { key: 'dropoff', label: 'Deliver to' },
            { key: 'cod_amount', label: 'COD', className: 'whitespace-nowrap', render: (row) => formatMoney(row.cod_amount) },
            { key: 'attempts', label: 'Attempts' },
            { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} /> },
            { key: 'updated_at', label: 'Updated', className: 'whitespace-nowrap text-gray-500', render: (row) => formatDateTime(row.updated_at) },
          ]}
        />
        <Pagination paginator={shipments} />
      </Card>
    </AdminLayout>
  );
}
