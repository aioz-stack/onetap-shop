import { Head } from '@inertiajs/react';
import { DateRangeFilter, FilterBar, FilterSelect } from '../../../Components/admin/Filters';
import { Card, DataTable, EmptyState, PageHeader, Pagination, SearchInput } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDateTime, formatMoney, headline } from '../../../lib/format';
import { withQuery } from '../../../lib/query';

export default function Transactions({ transactions, filters, types }) {
  return (
    <AdminLayout>
      <Head title="Transactions" />
      <PageHeader
        title="Transactions"
        description="The append-only money ledger: earnings, commissions, fees, payouts, refunds and COD."
        breadcrumbs={[{ label: 'Finance', href: '/admin/finance' }, { label: 'Transactions' }]}
        actions={
          <a
            href={withQuery({}, '/admin/finance/transactions/export')}
            className="inline-flex h-9 items-center rounded-lg px-3 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-white/[0.03]"
          >
            Export CSV
          </a>
        }
      />

      <Card bodyClassName="">
        <FilterBar>
          <SearchInput value={filters.search} placeholder="Description or shop" className="w-full sm:w-64" />
          <FilterSelect param="type" value={filters.type} options={types} placeholder="All types" />
          <DateRangeFilter from={filters.from} to={filters.to} />
        </FilterBar>
        <DataTable
          rows={transactions.data}
          empty={<EmptyState title="No transactions" description="Ledger entries appear as orders complete, payouts are made and refunds are issued." />}
          columns={[
            { key: 'id', label: '#', className: 'text-gray-500' },
            { key: 'created_at', label: 'Date', className: 'whitespace-nowrap', render: (row) => formatDateTime(row.created_at) },
            { key: 'type', label: 'Type', render: (row) => headline(row.type) },
            { key: 'party', label: 'Party', render: (row) => row.shop ?? row.partner ?? row.user ?? '—' },
            { key: 'description', label: 'Description', render: (row) => row.description ?? '—' },
            {
              key: 'amount',
              label: 'Amount',
              className: 'text-right whitespace-nowrap',
              headerClassName: 'text-right',
              render: (row) => (
                <span className={row.direction === 'credit' ? 'text-success-600' : ''}>
                  {row.direction === 'credit' ? '+' : '−'}
                  {formatMoney(row.amount)}
                </span>
              ),
            },
            { key: 'balance_after', label: 'Balance after', className: 'text-right whitespace-nowrap text-gray-500', headerClassName: 'text-right', render: (row) => (row.balance_after !== null ? formatMoney(row.balance_after) : '—') },
          ]}
        />
        <Pagination paginator={transactions} />
      </Card>
    </AdminLayout>
  );
}
