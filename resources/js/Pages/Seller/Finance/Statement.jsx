import { Head, router } from '@inertiajs/react';
import { Badge, Card, DataTable, EmptyState, PageHeader, Pagination, Select } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';
import { formatDate, formatMoney } from '../../../lib/format';

export default function Statement({ month, monthLabel, statement, details, months }) {
  const rows = [
    ['Gross sales (items)', statement.gross_sales, ''],
    ['Shop vouchers', statement.vouchers, '−'],
    ['Onetap commission', statement.commission, '−'],
  ];

  return (
    <SellerLayout>
      <Head title="Income statement" />
      <PageHeader
        title="Income statement"
        description="Income is counted when the customer confirms they received the order."
        breadcrumbs={[{ label: 'Finance', href: '/seller/finance' }, { label: 'Statement' }]}
        actions={<Select aria-label="Month" className="h-10 w-48" value={month} options={months} onChange={(event) => router.get('/seller/finance/statement', { month: event.target.value }, { preserveScroll: true })} />}
      />

      <Card title={monthLabel} description={`${statement.orders} completed order${statement.orders === 1 ? '' : 's'}`} className="max-w-xl">
        <dl className="space-y-2 text-sm">
          {rows.map(([label, value, sign]) => (
            <div key={label} className="flex justify-between text-gray-600 dark:text-gray-400">
              <dt>{label}</dt>
              <dd>{sign}{formatMoney(value)}</dd>
            </div>
          ))}
          <div className="flex justify-between border-t border-gray-100 pt-3 text-base font-semibold text-gray-800 dark:border-gray-800 dark:text-white/90">
            <dt>Net income</dt>
            <dd>{formatMoney(statement.net_income)}</dd>
          </div>
          {Number(statement.refunds) > 0 && <p className="text-xs text-error-500">{formatMoney(statement.refunds)} of earnings were reversed by refunds this month.</p>}
        </dl>
      </Card>

      <Card className="mt-6" title="Income details" bodyClassName="">
        <DataTable
          rows={details.data}
          empty={<EmptyState title="No completed orders this month" />}
          columns={[
            { key: 'reference', label: 'Order' },
            { key: 'completed_at', label: 'Completed', className: 'whitespace-nowrap', render: (row) => formatDate(row.completed_at) },
            { key: 'items_subtotal', label: 'Items', render: (row) => formatMoney(row.items_subtotal) },
            { key: 'discount', label: 'Voucher', render: (row) => (Number(row.discount) ? `−${formatMoney(row.discount)}` : '—') },
            { key: 'commission_amount', label: 'Commission', render: (row) => `−${formatMoney(row.commission_amount)}` },
            { key: 'seller_earning', label: 'Your income', className: 'font-semibold', render: (row) => formatMoney(row.seller_earning) },
            { key: 'released', label: '', render: (row) => <Badge tone={row.released ? 'success' : 'warning'}>{row.released ? 'Available' : 'On hold'}</Badge> },
          ]}
        />
        <Pagination paginator={details} />
      </Card>
    </SellerLayout>
  );
}
