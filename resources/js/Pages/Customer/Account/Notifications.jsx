import { Head, router } from '@inertiajs/react';
import { Button, Card, EmptyState, Pagination } from '../../../Components/ui';
import AccountLayout from '../../../Layouts/AccountLayout';
import { formatDateTime } from '../../../lib/format';

export default function Notifications({ notifications }) {
  const hasUnread = notifications.data.some((notification) => !notification.read);

  return (
    <AccountLayout
      title="Notifications"
      actions={hasUnread && <Button size="sm" variant="secondary" onClick={() => router.post('/account/notifications/read-all', {}, { preserveScroll: true })}>Mark all as read</Button>}
    >
      <Head title="Notifications" />
      <Card bodyClassName="">
        {notifications.data.length ? (
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {notifications.data.map((notification) => (
              <li key={notification.id}>
                <a href={`/account/notifications/${notification.id}`} className={`flex gap-3 px-5 py-4 hover:bg-gray-50 dark:hover:bg-white/[0.02] ${notification.read ? '' : 'bg-brand-50/40 dark:bg-brand-500/5'}`}>
                  <span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${notification.read ? 'bg-transparent' : 'bg-brand-500'}`} aria-hidden="true" />
                  <span className="min-w-0 text-sm">
                    <span className={`block ${notification.read ? 'text-gray-700 dark:text-gray-300' : 'font-semibold text-gray-800 dark:text-white/90'}`}>{notification.title}</span>
                    <span className="block text-gray-600 dark:text-gray-400">{notification.body}</span>
                    <span className="mt-1 block text-xs text-gray-400">{formatDateTime(notification.created_at)}</span>
                  </span>
                </a>
              </li>
            ))}
          </ul>
        ) : (
          <EmptyState title="No notifications yet" description="Order updates and messages appear here." />
        )}
        <Pagination paginator={notifications} />
      </Card>
    </AccountLayout>
  );
}
