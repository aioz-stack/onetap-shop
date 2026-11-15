import { Head } from '@inertiajs/react';
import { Card, DataTable, PageHeader } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatMoney } from '../../../lib/format';

const money = (key) => ({ key, className: 'text-right whitespace-nowrap', headerClassName: 'text-right', render: (row) => formatMoney(row[key]) });

export default function Reports({ monthly }) {
  const totals = monthly.reduce(
    (sum, row) => {
      ['gmv', 'commission', 'sellerEarnings', 'logisticsFees', 'refunds', 'paidOut'].forEach((key) => {
        sum[key] += Number(row[key]);
      });

      return sum;
    },
    { month: 'total', label: 'Last 12 months', gmv: 0, commission: 0, sellerEarnings: 0, logisticsFees: 0, refunds: 0, paidOut: 0 },
  );

  return (
    <AdminLayout>
      <Head title="Financial reports" />
      <PageHeader
        title="Financial reports"
        description="Monthly totals for the last 12 months."
        breadcrumbs={[{ label: 'Finance', href: '/admin/finance' }, { label: 'Reports' }]}
        actions={
          <a href="/admin/finance/reports/export" className="inline-flex h-9 items-center rounded-lg bg-brand-500 px-3 text-sm font-medium text-white hover:bg-brand-600">
            Download CSV
          </a>
        }
      />

      <Card bodyClassName="">
        <DataTable
          rowKey="month"
          rows={[...monthly, totals]}
          columns={[
            { key: 'label', label: 'Month', render: (row) => <span className={row.month === 'total' ? 'font-semibold' : ''}>{row.label}</span> },
            { ...money('gmv'), label: 'GMV' },
            { ...money('commission'), label: 'Commission' },
            { ...money('sellerEarnings'), label: 'Seller earnings' },
            { ...money('logisticsFees'), label: 'Logistics fees' },
            { ...money('refunds'), label: 'Refunds' },
            { ...money('paidOut'), label: 'Paid to sellers' },
          ]}
        />
      </Card>
    </AdminLayout>
  );
}
