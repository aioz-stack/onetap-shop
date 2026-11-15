import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button, Card, StatusBadge } from '../ui';
import { formatDateTime } from '../../lib/format';

/**
 * Sign in / sign out of a shift and toggle availability while on shift.
 */
export default function ShiftControls({ rider }) {
  const [busy, setBusy] = useState(false);
  const post = (url, data = {}) => router.post(url, data, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });

  if (rider.status !== 'active') {
    return (
      <Card bodyClassName="p-4">
        <div className="flex items-center justify-between gap-3">
          <p className="text-sm text-gray-600 dark:text-gray-400">Your rider account is {rider.status}. Your logistics partner needs to approve it before you can take jobs.</p>
          <StatusBadge status={rider.status} />
        </div>
      </Card>
    );
  }

  return (
    <Card bodyClassName="p-4">
      <div className="flex items-center justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-medium text-gray-800 dark:text-white/90">{rider.on_shift ? 'On shift' : 'Off shift'}</p>
          <p className="text-xs text-gray-500 dark:text-gray-400">
            {rider.on_shift ? `Since ${formatDateTime(rider.shift_started_at)}` : 'Sign in to start receiving and updating jobs.'}
          </p>
        </div>
        <StatusBadge status={rider.availability} />
      </div>
      <div className="mt-4 grid grid-cols-2 gap-2">
        {rider.on_shift ? (
          <>
            {rider.availability === 'available' ? (
              <Button variant="secondary" disabled={busy} onClick={() => post('/rider/shift/availability', { available: false })}>Go unavailable</Button>
            ) : (
              <Button disabled={busy} onClick={() => post('/rider/shift/availability', { available: true })}>Go available</Button>
            )}
            <Button variant="ghost" className="ring-1 ring-inset ring-gray-300 dark:ring-gray-700" disabled={busy} onClick={() => post('/rider/shift/sign-out')}>Sign out</Button>
          </>
        ) : (
          <Button className="col-span-2" size="lg" loading={busy} onClick={() => post('/rider/shift/sign-in')}>Sign in to shift</Button>
        )}
      </div>
    </Card>
  );
}
