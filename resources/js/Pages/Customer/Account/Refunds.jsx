import { Head, Link } from '@inertiajs/react';
import { Card, DataTable, EmptyState, Pagination, StatusBadge } from '../../../Components/ui';
import AccountLayout from '../../../Layouts/AccountLayout';
import { formatDate, formatMoney } from '../../../lib/format';

export default function Refunds({ refunds }) {
  return (
    <AccountLayout title="Returns & refunds">
      <Head title="Returns & refunds" />
      <Card bodyClassName="">
        <DataTable
          rows={refunds.data}
          empty={<EmptyState title="No return requests" description="Request a return from a delivered order in My purchases." />}
          columns={[
            { key: 'order', label: 'Order', render: (row) => <Link className="hover:text-brand-600" href={`/account/orders/${row.order.id}`}>{row.order.reference}<span className="block text-xs text-gray-500">{row.order.shop}</span></Link> },
            { key: 'reason', label: 'Reason', render: (row) => <span>{row.reason}<span className="block max-w-xs truncate text-xs text-gray-500">{row.details}</span></span> },
            { key: 'amount', label: 'Amount', render: (row) => formatMoney(row.amount) },
            { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} /> },
            { key: 'created_at', label: 'Requested', className: 'text-gray-500 whitespace-nowrap', render: (row) => formatDate(row.created_at) },
          ]}
        />
        <Pagination paginator={refunds} />
      </Card>
    </AccountLayout>
  );
}
