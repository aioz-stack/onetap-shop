import { Head, Link, usePage } from '@inertiajs/react';
import { icon } from '../../Components/AppIcon';
import JobCard from '../../Components/rider/JobCard';
import ShiftControls from '../../Components/rider/ShiftControls';
import { Card, EmptyState, StatCard } from '../../Components/ui';
import RiderLayout from '../../Layouts/RiderLayout';
import { formatMoney, formatNumber } from '../../lib/format';

export default function Dashboard({ rider, stats, jobs }) {
  const { auth } = usePage().props;

  return (
    <RiderLayout title="Today">
      <Head title="Rider" />

      <div className="mb-4">
        <p className="text-sm text-gray-500 dark:text-gray-400">Hello,</p>
        <h1 className="text-xl font-semibold text-gray-800 dark:text-white/90">{auth.user.name}</h1>
        {rider?.partner && <p className="text-xs text-gray-500 dark:text-gray-400">{rider.partner}{rider.vehicle && ` · ${rider.vehicle}`}</p>}
      </div>

      {rider ? (
        <div className="space-y-5">
          <ShiftControls rider={rider} />

          <div className="grid grid-cols-2 gap-3">
            <StatCard compact label="Assigned to me" value={formatNumber(stats.assigned)} icon={icon('clipboard')} tone="brand" />
            <StatCard compact label="Pending pickups" value={formatNumber(stats.pendingPickups)} icon={icon('store')} tone="warning" />
            <StatCard compact label="To deliver" value={formatNumber(stats.todayDeliveries)} icon={icon('truck')} tone="brand" />
            <StatCard compact label="Delivered today" value={formatNumber(stats.completedToday)} icon={icon('box')} tone="success" />
            <StatCard compact label="COD collected today" value={formatMoney(stats.codToday)} hint={`${formatMoney(stats.codUnremitted)} still to remit`} icon={icon('cash')} tone="orange" />
            <StatCard compact label="Earned today" value={formatMoney(stats.earningsToday)} hint={`${formatMoney(stats.ratePerDelivery)} per delivery`} icon={icon('wallet')} tone="success" />
          </div>

          {stats.failedToday > 0 && (
            <Link href="/rider/deliveries?tab=issues" className="block rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
              {stats.failedToday} parcel{stats.failedToday > 1 ? 's need' : ' needs'} a decision after a failed attempt.
            </Link>
          )}

          <section>
            <div className="mb-3 flex items-center justify-between">
              <h2 className="text-base font-semibold text-gray-800 dark:text-white/90">Up next</h2>
              <Link href="/rider/deliveries" className="text-sm font-medium text-brand-600 dark:text-brand-400">All jobs</Link>
            </div>
            {jobs.length ? (
              <div className="space-y-3">{jobs.map((job) => <JobCard key={job.assignment_id} job={job} />)}</div>
            ) : (
              <Card><EmptyState title="No jobs right now" description={rider.on_shift ? 'New assignments from your dispatcher appear here.' : 'Sign in to your shift to receive jobs.'} /></Card>
            )}
          </section>
        </div>
      ) : (
        <Card>
          <EmptyState title="Rider profile missing" description="Your logistics partner needs to finish setting up your rider profile." />
        </Card>
      )}
    </RiderLayout>
  );
}
