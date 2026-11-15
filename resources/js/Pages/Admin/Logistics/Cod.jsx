import { Head } from '@inertiajs/react';
import { icon } from '../../../Components/AppIcon';
import { FilterBar, FilterSelect } from '../../../Components/admin/Filters';
import { Badge, Card, DataTable, EmptyState, PageHeader, Pagination, StatCard, StatusBadge, Tabs } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDate, formatDateTime, formatMoney, formatNumber } from '../../../lib/format';

const collectionColumns = [
  { key: 'tracking_number', label: 'Shipment', className: 'font-mono text-xs' },
  { key: 'rider', label: 'Rider', render: (row) => <span>{row.rider}<span className="block text-xs text-gray-500">{row.partner}</span></span> },
  { key: 'amount_due', label: 'Due', render: (row) => formatMoney(row.amount_due) },
  { key: 'amount_collected', label: 'Collected', render: (row) => <span className={row.mismatch ? 'font-semibold text-error-600' : ''}>{formatMoney(row.amount_collected)}</span> },
  { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} /> },
  { key: 'collected_at', label: 'Collected at', className: 'whitespace-nowrap text-gray-500', render: (row) => formatDateTime(row.collected_at) },
];

const remittanceColumns = [
  { key: 'remittance_date', label: 'Date', className: 'whitespace-nowrap', render: (row) => formatDate(row.remittance_date) },
  { key: 'rider', label: 'Rider', render: (row) => <span>{row.rider}<span className="block text-xs text-gray-500">{row.partner}</span></span> },
  { key: 'collections_count', label: 'Deliveries' },
  { key: 'expected_amount', label: 'Expected', render: (row) => formatMoney(row.expected_amount) },
  { key: 'remitted_amount', label: 'Remitted', render: (row) => formatMoney(row.remitted_amount) },
  { key: 'discrepancy', label: 'Discrepancy', render: (row) => (Number(row.discrepancy) !== 0 ? <Badge tone="error">{formatMoney(row.discrepancy)}</Badge> : <span className="text-gray-400">—</span>) },
  { key: 'status', label: 'Status', render: (row) => <span><StatusBadge status={row.status} /><span className="block text-xs text-gray-500">{row.confirmer ? `by ${row.confirmer}` : ''}</span></span> },
];

export default function Cod({ view, rows, summary, filters, statuses, partners }) {
  return (
    <AdminLayout>
      <Head title="COD monitoring" />
      <PageHeader
        title="COD monitoring"
        description="Cash collected by riders on delivery and remitted back to their partner."
        breadcrumbs={[{ label: 'Logistics', href: '/admin/logistics/partners' }, { label: 'COD' }]}
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Collected today" value={formatMoney(summary.collectedToday)} icon={icon('cash')} tone="brand" />
        <StatCard label="Not yet remitted" value={formatMoney(summary.unremitted)} icon={icon('wallet')} tone="warning" hint={`${formatNumber(summary.unremittedCount)} deliveries`} />
        <StatCard label="Remitted today" value={formatMoney(summary.remittedToday)} icon={icon('clipboard')} tone="success" />
        <StatCard label="Discrepancies" value={formatNumber(summary.discrepancies)} icon={icon('shield')} tone={summary.discrepancies ? 'error' : 'gray'} />
      </div>

      <Card className="mt-6" bodyClassName="">
        <div className="px-4">
          <Tabs
            active={view}
            tabs={[
              { key: 'collections', label: 'Collections', href: '/admin/logistics/cod?view=collections' },
              { key: 'remittances', label: 'Remittances', href: '/admin/logistics/cod?view=remittances' },
            ]}
          />
        </div>
        <FilterBar>
          <FilterSelect param="partner" value={filters.partner} options={partners.map((partner) => ({ value: partner.id, label: partner.name }))} placeholder="All partners" />
          <FilterSelect param="status" value={filters.status} options={statuses} placeholder="Any status" />
        </FilterBar>
        <DataTable rows={rows.data} columns={view === 'collections' ? collectionColumns : remittanceColumns} empty={<EmptyState title={`No ${view} yet`} description="These fill in as riders complete COD deliveries." />} />
        <Pagination paginator={rows} />
      </Card>
    </AdminLayout>
  );
}
