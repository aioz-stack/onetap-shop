import { Head } from '@inertiajs/react';
import { icon } from '../../../Components/AppIcon';
import { Card, DataTable, PageHeader, StatCard } from '../../../Components/ui';
import LogisticsLayout from '../../../Layouts/LogisticsLayout';
import { formatMoney } from '../../../lib/format';

export default function Earnings({ summary, monthly }) {
  return (
    <LogisticsLayout>
      <Head title="Earnings & reports" />
      <PageHeader
        title="Earnings & reports"
        description="You earn the delivery fee of every parcel you deliver."
        actions={<a href="/logistics/earnings/export" className="inline-flex h-9 items-center rounded-lg bg-brand-500 px-3 text-sm font-medium text-white hover:bg-brand-600">Download CSV</a>}
      />
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Delivery fees earned" value={formatMoney(summary.allTimeFees)} icon={icon('wallet')} tone="brand" />
        <StatCard label="This month" value={formatMoney(summary.thisMonthFees)} icon={icon('chart')} tone="success" />
        <StatCard label="Fees on parcels in progress" value={formatMoney(summary.pendingFees)} icon={icon('truck')} tone="warning" />
        <StatCard label="Parcels delivered" value={summary.delivered} icon={icon('clipboard')} tone="gray" />
      </div>
      <Card className="mt-6" title="Monthly report" bodyClassName="">
        <DataTable
          rowKey="month"
          rows={monthly}
          columns={[
            { key: 'label', label: 'Month' },
            { key: 'delivered', label: 'Delivered' },
            { key: 'fees', label: 'Delivery fees', className: 'font-semibold', render: (row) => formatMoney(row.fees) },
            { key: 'cod', label: 'COD collected', render: (row) => formatMoney(row.cod) },
            { key: 'failed', label: 'Failed attempts' },
            { key: 'returned', label: 'Returned' },
          ]}
        />
      </Card>
    </LogisticsLayout>
  );
}
