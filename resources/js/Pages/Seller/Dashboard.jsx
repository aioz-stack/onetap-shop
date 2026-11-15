import { Head } from '@inertiajs/react';
import { icon } from '../../Components/AppIcon';
import { Card, EmptyState, PageHeader, StatCard, StatusBadge } from '../../Components/ui';
import SellerLayout from '../../Layouts/SellerLayout';
import { formatMoney, formatNumber } from '../../lib/format';

export default function Dashboard({ shop, stats }) {
  return (
    <SellerLayout>
      <Head title="Seller Center" />
      <PageHeader
        title={shop ? shop.name : 'Seller Center'}
        description="Your shop at a glance."
        actions={shop && <StatusBadge status={shop.status} />}
      />

      {shop ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard label="Available balance" value={formatMoney(stats.availableBalance)} icon={icon('wallet')} tone="brand" />
          <StatCard label="Pending balance" value={formatMoney(stats.pendingBalance)} icon={icon('cash')} tone="warning" hint="Released after the hold period" />
          <StatCard label="Orders" value={formatNumber(stats.orders)} icon={icon('receipt')} tone="orange" />
          <StatCard label="Products" value={formatNumber(stats.products)} icon={icon('box')} tone="gray" />
        </div>
      ) : (
        <Card>
          <EmptyState title="No shop yet" description="Your shop is created when an administrator approves your seller application." />
        </Card>
      )}
    </SellerLayout>
  );
}
