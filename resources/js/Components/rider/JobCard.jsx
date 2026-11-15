import { Link } from '@inertiajs/react';
import AppIcon from '../AppIcon';
import { StatusBadge } from '../ui';
import { formatDateTime, formatMoney } from '../../lib/format';

/**
 * One delivery job in the rider's lists.
 */
export default function JobCard({ job }) {
  const pickupStage = ['assigned', 'accepted', 'arrived_at_pickup'].includes(job.status);

  return (
    <Link
      href={`/rider/deliveries/${job.id}`}
      className="block rounded-2xl border border-gray-200 bg-white p-4 transition hover:border-brand-300 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-800"
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="truncate font-mono text-sm font-semibold text-gray-800 dark:text-white/90">{job.tracking_number}</p>
          <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
            {job.job_type === 'delivery' ? 'Redelivery' : 'Pickup & delivery'} · {formatDateTime(job.assigned_at)}
          </p>
        </div>
        <StatusBadge status={job.open ? job.status : job.job_status === 'completed' ? job.status : job.job_status} />
      </div>

      <div className="mt-3 space-y-1.5 text-sm">
        <p className={`flex items-center gap-2 ${pickupStage ? 'text-gray-800 dark:text-white/90' : 'text-gray-500 dark:text-gray-400'}`}>
          <AppIcon name="store" className="h-4 w-4 shrink-0" />
          <span className="truncate">{job.shop}{job.pickup_area && ` · ${job.pickup_area}`}</span>
        </p>
        <p className={`flex items-center gap-2 ${pickupStage ? 'text-gray-500 dark:text-gray-400' : 'text-gray-800 dark:text-white/90'}`}>
          <AppIcon name="pin" className="h-4 w-4 shrink-0" />
          <span className="truncate">{job.recipient}{job.dropoff_area && ` · ${job.dropoff_area}`}</span>
        </p>
      </div>

      <div className="mt-3 flex items-center justify-between border-t border-gray-100 pt-3 text-sm dark:border-gray-800">
        <span className="text-gray-500 dark:text-gray-400">{Number(job.cod_amount) > 0 ? 'Collect' : 'Prepaid'}</span>
        <span className="font-semibold text-gray-800 dark:text-white/90">{Number(job.cod_amount) > 0 ? formatMoney(job.cod_amount) : '—'}</span>
      </div>
      {job.attempts > 0 && <p className="mt-2 text-xs text-warning-600 dark:text-warning-500">Attempt {job.attempts + 1}</p>}
    </Link>
  );
}
