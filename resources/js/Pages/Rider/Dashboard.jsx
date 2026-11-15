import { Head, usePage } from '@inertiajs/react';
import { icon } from '../../Components/AppIcon';
import { Card, EmptyState, StatCard, StatusBadge } from '../../Components/ui';
import RiderLayout from '../../Layouts/RiderLayout';
import { formatMoney, formatNumber } from '../../lib/format';

export default function Dashboard({ rider, stats }) {
  const { auth } = usePage().props;

  return (
    <RiderLayout title="Today">
      <Head title="Rider" />

      <div className="mb-5 flex items-center justify-between gap-3">
        <div>
          <p className="text-sm text-gray-500 dark:text-gray-400">Hello,</p>
          <h1 className="text-xl font-semibold text-gray-800 dark:text-white/90">{auth.user.name}</h1>
          {rider?.partner && <p className="text-xs text-gray-500 dark:text-gray-400">{rider.partner}</p>}
        </div>
        {rider && <StatusBadge status={rider.availability} />}
      </div>

      {rider ? (
        <div className="grid grid-cols-2 gap-3">
          <StatCard label="Active assignments" value={formatNumber(stats.assigned)} icon={icon('truck')} tone="brand" />
          <StatCard label="Delivered today" value={formatNumber(stats.completedToday)} icon={icon('clipboard')} tone="success" />
          <div className="col-span-2">
            <StatCard label="COD collected today" value={formatMoney(stats.codToday)} icon={icon('cash')} tone="orange" />
          </div>
        </div>
      ) : (
        <Card>
          <EmptyState title="Rider profile missing" description="Your logistics partner needs to finish setting up your rider profile." />
        </Card>
      )}

      <Card className="mt-5" bodyClassName="p-4">
        <p className="text-sm text-gray-600 dark:text-gray-400">
          Going online, delivery status updates, proof of delivery and COD remittance arrive in Phase 7.
        </p>
      </Card>
    </RiderLayout>
  );
}
