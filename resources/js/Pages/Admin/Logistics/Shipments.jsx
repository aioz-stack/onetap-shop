import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { FilterBar, FilterSelect } from '../../../Components/admin/Filters';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { Button, Card, Checkbox, DataTable, EmptyState, FormField, Modal, PageHeader, Pagination, SearchInput, Select, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDateTime, formatMoney } from '../../../lib/format';
import { applyFilters, withQuery } from '../../../lib/query';

function RouteButton({ shipment, partners }) {
  const [open, setOpen] = useState(false);
  const form = useForm({ partner_id: partners[0]?.id ?? '' });
  const close = () => setOpen(false);

  return (
    <>
      <Button size="sm" variant={shipment.partner ? 'ghost' : 'primary'} onClick={() => setOpen(true)}>
        {shipment.partner ? 'Reroute' : 'Route to partner'}
      </Button>
      <Modal open={open} onClose={close} title={`Route ${shipment.tracking_number}`} footer={<><Button variant="secondary" onClick={close}>Cancel</Button><Button loading={form.processing} onClick={() => form.post(`/admin/logistics/shipments/${shipment.id}/route`, { preserveScroll: true, onSuccess: close })}>Route</Button></>}>
        <p className="mb-3 text-sm text-gray-600 dark:text-gray-400">Deliver to {shipment.dropoff || 'unknown area'}. Consider adding this area to the partner's coverage so future orders route automatically.</p>
        <FormField label="Logistics partner" required error={form.errors.partner_id}>
          {(id) => <Select id={id} value={form.data.partner_id} options={partners.map((partner) => ({ value: partner.id, label: partner.name }))} onChange={(event) => form.setData('partner_id', event.target.value)} />}
        </FormField>
      </Modal>
    </>
  );
}

export default function Shipments({ shipments, tabs, filters, counts, partners, unassignedCount }) {
  return (
    <AdminLayout>
      <Head title="Shipments" />
      <PageHeader
        title="Pickup & shipment monitoring"
        description="Every shipment across partners. Partners dispatch riders; route shipments that no partner covers."
        breadcrumbs={[{ label: 'Logistics', href: '/admin/logistics/partners' }, { label: 'Shipments' }]}
      />

      {unassignedCount > 0 && !filters.unassigned && (
        <div className="mb-5 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-orange-400">
          {unassignedCount} shipment{unassignedCount > 1 ? 's have' : ' has'} no logistics partner. <Link href={withQuery({ unassigned: 1, tab: 'pickup' })} className="font-medium underline">Route them</Link>
        </div>
      )}

      <Card bodyClassName="">
        <StatusTabs tabs={tabs} param="tab" active={filters.tab} counts={counts} />
        <FilterBar>
          <SearchInput value={filters.search} placeholder="Tracking number or order reference" className="w-full sm:w-80" />
          <FilterSelect param="partner" value={filters.partner} options={partners.map((partner) => ({ value: partner.id, label: partner.name }))} placeholder="All partners" />
          <Checkbox label="No partner yet" checked={Boolean(filters.unassigned)} onChange={(event) => applyFilters({ unassigned: event.target.checked ? 1 : null })} />
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
            { key: 'actions', label: '', className: 'text-right', render: (row) => row.can_route && <RouteButton shipment={row} partners={partners} /> },
          ]}
        />
        <Pagination paginator={shipments} />
      </Card>
    </AdminLayout>
  );
}
