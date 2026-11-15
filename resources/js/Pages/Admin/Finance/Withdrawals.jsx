import { Head } from '@inertiajs/react';
import ActionButton from '../../../Components/admin/ActionButton';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { Card, DataTable, EmptyState, PageHeader, Pagination, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDateTime, formatMoney } from '../../../lib/format';

export default function Withdrawals({ withdrawals, status, counts }) {
  return (
    <AdminLayout>
      <Head title="Seller settlements" />
      <PageHeader
        title="Seller settlements"
        description="Withdrawal requests from sellers. The amount is held from their available balance when they request it."
        breadcrumbs={[{ label: 'Finance', href: '/admin/finance' }, { label: 'Settlements' }]}
      />

      <Card bodyClassName="">
        <StatusTabs
          tabs={[
            { key: 'pending', label: 'Pending' },
            { key: 'approved', label: 'Approved — to pay' },
            { key: 'paid', label: 'Paid' },
            { key: 'rejected', label: 'Rejected' },
          ]}
          active={status}
          counts={counts}
        />
        <DataTable
          rows={withdrawals.data}
          empty={<EmptyState title={`No ${status} withdrawals`} />}
          columns={[
            { key: 'shop', label: 'Shop', render: (row) => <span className="font-medium text-gray-800 dark:text-white/90">{row.shop?.name}</span> },
            { key: 'amount', label: 'Amount', className: 'whitespace-nowrap font-semibold', render: (row) => formatMoney(row.amount) },
            { key: 'bank', label: 'Pay to', render: (row) => (row.bank ? <span>{row.bank.bank_name} ••••{row.bank.last4}<span className="block text-xs text-gray-500">{row.bank.account_name}</span></span> : '—') },
            { key: 'available_balance', label: 'Seller available now', className: 'whitespace-nowrap text-gray-500', render: (row) => formatMoney(row.available_balance) },
            { key: 'status', label: 'Status', render: (row) => <span><StatusBadge status={row.status} /><span className="block text-xs text-gray-500">{row.reference ?? row.notes ?? ''}</span></span> },
            { key: 'created_at', label: 'Requested', className: 'whitespace-nowrap text-gray-500', render: (row) => formatDateTime(row.created_at) },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (row) => (
                <div className="flex justify-end gap-2">
                  {row.status === 'pending' && (
                    <ActionButton url={`/admin/finance/withdrawals/${row.id}/approve`} label="Approve" variant="primary" message={`Approve paying ${formatMoney(row.amount)} to ${row.shop?.name}. You will record the transfer reference next.`} />
                  )}
                  {row.status === 'approved' && (
                    <ActionButton
                      url={`/admin/finance/withdrawals/${row.id}/pay`}
                      label="Record payment"
                      variant="primary"
                      message={`Confirm ${formatMoney(row.amount)} was sent to ${row.bank?.bank_name} ••••${row.bank?.last4}. This cannot be undone.`}
                      fields={[{ name: 'reference', label: 'Bank transfer reference', type: 'text', required: true }]}
                    />
                  )}
                  {['pending', 'approved'].includes(row.status) && (
                    <ActionButton url={`/admin/finance/withdrawals/${row.id}/reject`} label="Reject" tone="danger" message="The amount goes back to the seller's available balance." fields={[{ name: 'reason', label: 'Reason', type: 'textarea', required: true }]} />
                  )}
                  {row.processor && <span className="self-center text-xs text-gray-500">by {row.processor}</span>}
                </div>
              ),
            },
          ]}
        />
        <Pagination paginator={withdrawals} />
      </Card>
    </AdminLayout>
  );
}
