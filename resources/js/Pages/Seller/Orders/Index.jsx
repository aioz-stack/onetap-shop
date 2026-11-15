import { Head, Link } from '@inertiajs/react';
import ActionButton from '../../../Components/admin/ActionButton';
import { FilterBar } from '../../../Components/admin/Filters';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { Card, DataTable, EmptyState, PageHeader, Pagination, SearchInput, StatusBadge } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';
import { formatDateTime, formatMoney } from '../../../lib/format';

export default function Index({ orders, tabs, filters, counts }) {
  return (
    <SellerLayout>
      <Head title="Orders" />
      <PageHeader title="Orders" description="Accept new orders, pack them, and mark them ready for the rider." />
      <Card bodyClassName="">
        <StatusTabs tabs={[{ key: null, label: 'All' }, ...tabs]} param="tab" active={filters.tab} counts={counts} />
        <FilterBar>
          <SearchInput value={filters.search} placeholder="Order number, product or customer" className="w-full sm:w-80" />
        </FilterBar>
        <DataTable
          rows={orders.data}
          empty={<EmptyState title="No orders here yet" />}
          columns={[
            { key: 'reference', label: 'Order', render: (row) => <Link href={`/seller/orders/${row.id}`} className="hover:text-brand-600"><span className="block font-medium text-gray-800 dark:text-white/90">{row.reference}</span><span className="text-xs text-gray-500">{formatDateTime(row.placed_at)}</span></Link> },
            { key: 'customer', label: 'Customer' },
            { key: 'items', label: 'Items', render: (row) => <ul className="max-w-xs text-xs text-gray-600 dark:text-gray-400">{row.items.map((item) => <li key={item} className="truncate">{item}</li>)}</ul> },
            { key: 'total', label: 'Total', className: 'whitespace-nowrap', render: (row) => <span>{formatMoney(row.total)}<span className="block text-xs text-gray-500">You earn {formatMoney(row.seller_earning)}</span></span> },
            { key: 'status', label: 'Status', render: (row) => <span><StatusBadge status={row.status} />{row.shipment && <span className="mt-1 block font-mono text-[10px] text-gray-500">{row.shipment.tracking_number}</span>}</span> },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (row) =>
                row.status === 'pending' ? (
                  <ActionButton url={`/seller/orders/${row.id}/accept`} label="Accept" variant="primary" message="Start preparing this order." />
                ) : row.status === 'processing' ? (
                  <ActionButton url={`/seller/orders/${row.id}/ready`} label="Ready for pickup" variant="primary" message="Confirm the parcel is packed. A shipment is created and a rider will collect it." />
                ) : (
                  <Link href={`/seller/orders/${row.id}`} className="text-sm text-brand-600">View</Link>
                ),
            },
          ]}
        />
        <Pagination paginator={orders} />
      </Card>
    </SellerLayout>
  );
}
