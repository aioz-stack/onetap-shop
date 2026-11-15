import { Head, router } from '@inertiajs/react';
import { icon } from '../../../Components/AppIcon';
import { Card, DataTable, EmptyState, PageHeader, StatCard, StatusBadge, TextInput } from '../../../Components/ui';
import LogisticsLayout from '../../../Layouts/LogisticsLayout';
import { formatDate, formatDateTime, formatMoney } from '../../../lib/format';

export default function Cod({ date, summary, byRider, collections }) {
  return (
    <LogisticsLayout>
      <Head title="Daily COD" />
      <PageHeader
        title="Daily COD collection"
        description={`Cash riders collected on ${formatDate(date)}.`}
        actions={<TextInput type="date" aria-label="Day" className="h-10 w-44" value={date} onChange={(event) => router.get('/logistics/cod', { date: event.target.value }, { preserveScroll: true })} />}
      />
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Collected" value={formatMoney(summary.collected)} icon={icon('cash')} tone="brand" />
        <StatCard label="Expected" value={formatMoney(summary.due)} icon={icon('receipt')} tone="gray" />
        <StatCard label="COD deliveries" value={summary.deliveries} icon={icon('truck')} tone="success" />
        <StatCard label="Still with riders" value={formatMoney(summary.outstanding)} icon={icon('wallet')} tone={Number(summary.outstanding) > 0 ? 'warning' : 'gray'} />
      </div>
      <Card className="mt-6" title="By rider" bodyClassName="">
        <DataTable rowKey="rider" rows={byRider} empty={<EmptyState title="No COD collected this day" />} columns={[
          { key: 'rider', label: 'Rider' },
          { key: 'deliveries', label: 'Deliveries' },
          { key: 'collected', label: 'Collected', render: (row) => formatMoney(row.collected) },
          { key: 'remitted', label: 'Handed over', render: (row) => formatMoney(row.remitted) },
          { key: 'outstanding', label: 'Outstanding', render: (row) => <span className={Number(row.outstanding) > 0 ? 'font-semibold text-warning-600' : ''}>{formatMoney(row.outstanding)}</span> },
        ]} />
      </Card>
      <Card className="mt-6" title="Collections" bodyClassName="">
        <DataTable rows={collections} empty={<EmptyState title="Nothing collected" />} columns={[
          { key: 'collected_at', label: 'Time', className: 'whitespace-nowrap', render: (row) => formatDateTime(row.collected_at) },
          { key: 'tracking_number', label: 'Shipment', className: 'font-mono text-xs' },
          { key: 'rider', label: 'Rider' },
          { key: 'amount_due', label: 'Due', render: (row) => formatMoney(row.amount_due) },
          { key: 'amount_collected', label: 'Collected', render: (row) => <span className={row.amount_collected !== row.amount_due ? 'font-semibold text-error-600' : ''}>{formatMoney(row.amount_collected)}</span> },
          { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} /> },
        ]} />
      </Card>
    </LogisticsLayout>
  );
}
