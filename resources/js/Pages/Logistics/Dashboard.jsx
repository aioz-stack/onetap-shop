import { Head, Link } from '@inertiajs/react';
import { icon } from '../../Components/AppIcon';
import { Button, Card, EmptyState, PageHeader, StatCard } from '../../Components/ui';
import LogisticsLayout from '../../Layouts/LogisticsLayout';
import { formatDateTime, formatMoney, formatNumber } from '../../lib/format';

export default function Dashboard({ partner, stats, waiting }) {
  return (
    <LogisticsLayout>
      <Head title="Logistics dashboard" />
      <PageHeader
        title={partner.name}
        description={`Partner code ${partner.code} · today's operations`}
        actions={<Button size="sm" href="/logistics/shipments">Dispatch shipments</Button>}
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Total shipments" value={formatNumber(stats.shipments)} icon={icon('truck')} tone="brand" />
        <StatCard label="Pending pickups" value={formatNumber(stats.pendingPickups)} icon={icon('box')} tone="warning" hint={`${formatNumber(stats.unassigned)} without a rider`} />
        <StatCard label="In transit" value={formatNumber(stats.inTransit)} icon={icon('map')} tone="orange" />
        <StatCard label="Out for delivery" value={formatNumber(stats.outForDelivery)} icon={icon('pin')} tone="brand" />
        <StatCard label="Delivered" value={formatNumber(stats.delivered)} icon={icon('clipboard')} tone="success" hint={`${formatNumber(stats.deliveredToday)} today`} />
        <StatCard label="Failed deliveries" value={formatNumber(stats.failed)} icon={icon('receipt')} tone={stats.failed ? 'error' : 'gray'} hint="Waiting to be rescheduled or returned" />
        <StatCard label="Active riders" value={`${formatNumber(stats.activeRiders)} / ${formatNumber(stats.riders)}`} icon={icon('bike')} tone="gray" hint={stats.pendingRiders ? `${stats.pendingRiders} waiting for approval` : 'Online now / total'} />
        <StatCard label="COD collected today" value={formatMoney(stats.codToday)} icon={icon('cash')} tone="orange" hint={`${formatMoney(stats.codUnremitted)} still with riders`} />
      </div>

      {stats.remittancesToReview > 0 && (
        <div className="mt-6 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-orange-400">
          {stats.remittancesToReview} rider remittance{stats.remittancesToReview > 1 ? 's' : ''} waiting for you to count and confirm. <Link href="/logistics/remittances" className="font-medium underline">Review</Link>
        </div>
      )}

      <Card className="mt-6" title="Waiting for a rider" actions={<Link href="/logistics/shipments?tab=incoming" className="text-sm font-medium text-brand-600">All pickup requests</Link>}>
        {waiting.length ? (
          <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
            {waiting.map((shipment) => (
              <li key={shipment.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0">
                <Link href={`/logistics/shipments/${shipment.id}`} className="min-w-0 hover:text-brand-600">
                  <span className="block font-mono text-xs text-gray-500">{shipment.tracking_number}</span>
                  <span className="block font-medium text-gray-800 dark:text-white/90">{shipment.pickup} → {shipment.dropoff}</span>
                  <span className="text-xs text-gray-500">Ready since {formatDateTime(shipment.created_at)}</span>
                </Link>
                <span className="flex items-center gap-3">
                  COD {formatMoney(shipment.cod_amount)}
                  <Button size="sm" href={`/logistics/shipments/${shipment.id}`}>Assign</Button>
                </span>
              </li>
            ))}
          </ul>
        ) : (
          <EmptyState title="Every pickup has a rider" description="New pickup requests appear here when shops pack their orders." />
        )}
      </Card>
    </LogisticsLayout>
  );
}
