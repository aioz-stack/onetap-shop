import { Head } from '@inertiajs/react';
import { Card, DataTable, EmptyState, PageHeader, Pagination } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';
import { formatDateTime, formatMoney, headline } from '../../../lib/format';

export default function Transactions({ transactions }) {
  return (
    <SellerLayout>
      <Head title="Transactions" />
      <PageHeader title="Transaction history" description="Every earning, refund and payout recorded for your shop." breadcrumbs={[{ label: 'Finance', href: '/seller/finance' }, { label: 'Transactions' }]} />
      <Card bodyClassName="">
        <DataTable
          rows={transactions.data}
          empty={<EmptyState title="No transactions yet" description="Earnings are recorded when customers confirm their orders." />}
          columns={[
            { key: 'created_at', label: 'Date', className: 'whitespace-nowrap', render: (row) => formatDateTime(row.created_at) },
            { key: 'type', label: 'Type', render: (row) => headline(row.type) },
            { key: 'description', label: 'Description', render: (row) => row.description ?? '—' },
            { key: 'amount', label: 'Amount', className: 'text-right whitespace-nowrap', headerClassName: 'text-right', render: (row) => <span className={row.direction === 'credit' ? 'text-success-600' : ''}>{row.direction === 'credit' ? '+' : '−'}{formatMoney(row.amount)}</span> },
          ]}
        />
        <Pagination paginator={transactions} />
      </Card>
    </SellerLayout>
  );
}
