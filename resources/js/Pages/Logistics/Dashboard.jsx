import { Head } from '@inertiajs/react';
import { icon } from '../../Components/AppIcon';
import { Card, EmptyState, PageHeader, StatCard } from '../../Components/ui';
import LogisticsLayout from '../../Layouts/LogisticsLayout';
import { formatNumber } from '../../lib/format';

export default function Dashboard({ partner, stats }) {
  return (
    <LogisticsLayout>
      <Head title="Logistics dashboard" />
      <PageHeader title={partner ? partner.name : 'Logistics'} description={partner ? `Partner code ${partner.code}` : undefined} />

      {partner ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <StatCard label="Total shipments" value={formatNumber(stats.shipments)} icon={icon('truck')} tone="brand" />
          <StatCard label="Pending pickups" value={formatNumber(stats.pendingPickups)} icon={icon('box')} tone="warning" />
          <StatCard label="In transit" value={formatNumber(stats.inTransit)} icon={icon('map')} tone="orange" />
          <StatCard label="Delivered" value={formatNumber(stats.delivered)} icon={icon('clipboard')} tone="success" />
          <StatCard label="Riders" value={formatNumber(stats.riders)} icon={icon('bike')} tone="gray" />
          <StatCard label="Riders available now" value={formatNumber(stats.activeRiders)} icon={icon('pin')} tone="brand" />
        </div>
      ) : (
        <Card>
          <EmptyState title="No logistics partner linked" description="Ask a marketplace administrator to link your account to a logistics partner." />
        </Card>
      )}
    </LogisticsLayout>
  );
}
