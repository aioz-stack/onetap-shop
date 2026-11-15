import { Head, Link } from '@inertiajs/react';
import ActionButton from '../../../Components/admin/ActionButton';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { Card, DataTable, EmptyState, PageHeader, Pagination, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDateTime, formatMoney } from '../../../lib/format';

export default function Index({ refunds, status, counts }) {
  return (
    <AdminLayout>
      <Head title="Returns & refunds" />
      <PageHeader
        title="Returns & refunds"
        description="Decide on customer return and refund requests."
        breadcrumbs={[{ label: 'Orders', href: '/admin/orders' }, { label: 'Returns & refunds' }]}
      />

      <Card bodyClassName="">
        <StatusTabs
          tabs={[
            { key: 'requested', label: 'Requested' },
            { key: 'refunded', label: 'Refunded' },
            { key: 'rejected', label: 'Rejected' },
          ]}
          active={status}
          counts={counts}
        />
        <DataTable
          rows={refunds.data}
          empty={<EmptyState title={status === 'requested' ? 'No requests waiting' : `No ${status} refunds`} />}
          columns={[
            { key: 'order', label: 'Order', render: (row) => <Link className="font-medium hover:text-brand-600" href={`/admin/orders/${row.order?.id}`}>{row.order?.reference}<span className="block text-xs font-normal text-gray-500">{row.shop}</span></Link> },
            { key: 'customer', label: 'Customer' },
            { key: 'reason', label: 'Reason', render: (row) => <span>{row.reason}<span className="block max-w-xs truncate text-xs text-gray-500">{row.details}</span></span> },
            { key: 'amount', label: 'Amount', className: 'whitespace-nowrap', render: (row) => formatMoney(row.amount) },
            { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} /> },
            { key: 'created_at', label: 'Requested', className: 'whitespace-nowrap text-gray-500', render: (row) => formatDateTime(row.created_at) },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (row) =>
                row.status === 'requested' ? (
                  <div className="flex justify-end gap-2">
                    <ActionButton
                      url={`/admin/refunds/${row.id}/approve`}
                      label="Approve"
                      variant="primary"
                      message={`Refund ${formatMoney(row.amount)} to ${row.customer}. If the seller was already paid for this order, the earning is taken back from their balance.`}
                      fields={[
                        { name: 'restock', label: 'Items were returned — put them back in stock', type: 'checkbox' },
                        { name: 'note', label: 'Note (optional)', type: 'text' },
                      ]}
                    />
                    <ActionButton url={`/admin/refunds/${row.id}/reject`} label="Reject" tone="danger" message="The order returns to its previous status." fields={[{ name: 'reason', label: 'Reason', type: 'textarea', required: true }]} />
                  </div>
                ) : (
                  <span className="text-xs text-gray-500">{row.resolver ? `by ${row.resolver}` : ''}</span>
                ),
            },
          ]}
        />
        <Pagination paginator={refunds} />
      </Card>
    </AdminLayout>
  );
}
