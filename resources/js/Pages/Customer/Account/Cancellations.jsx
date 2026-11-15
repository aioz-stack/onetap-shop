import { Head } from '@inertiajs/react';
import OrderCard from '../../../Components/store/OrderCard';
import { EmptyState } from '../../../Components/ui';
import AccountLayout from '../../../Layouts/AccountLayout';
import { formatDateTime } from '../../../lib/format';

export default function Cancellations({ orders }) {
  return (
    <AccountLayout title="Cancellations">
      <Head title="Cancellations" />
      {orders.data.length ? (
        <div className="space-y-4">
          {orders.data.map((order) => (
            <OrderCard key={order.id} order={order}>
              <p className="mr-auto text-xs text-gray-500">
                Cancelled {formatDateTime(order.cancelled_at)}
                {order.cancelled_reason && ` — ${order.cancelled_reason}`}
              </p>
            </OrderCard>
          ))}
        </div>
      ) : (
        <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <EmptyState title="No cancelled orders" />
        </div>
      )}
    </AccountLayout>
  );
}
