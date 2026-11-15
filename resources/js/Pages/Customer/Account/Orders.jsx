import { Head } from '@inertiajs/react';
import OrderActions from '../../../Components/store/OrderActions';
import OrderCard from '../../../Components/store/OrderCard';
import { Button, EmptyState, SearchInput, Tabs } from '../../../Components/ui';
import AccountLayout from '../../../Layouts/AccountLayout';
import { withQuery } from '../../../lib/query';

export default function Orders({ orders, tabs, filters, counts }) {
  return (
    <AccountLayout title="My purchases">
      <Head title="My purchases" />

      <div className="rounded-2xl border border-gray-200 bg-white px-2 dark:border-gray-800 dark:bg-white/[0.03]">
        <Tabs
          active={filters.tab ?? null}
          tabs={[{ key: null, label: 'All' }, ...tabs].map((tab) => ({ key: tab.key, label: tab.label, count: counts[tab.key ?? 'all'], href: withQuery({ tab: tab.key }) }))}
        />
      </div>

      <SearchInput value={filters.search} placeholder="Search by shop, product or order number" className="my-4" />

      {orders.data.length ? (
        <div className="space-y-4">
          {orders.data.map((order) => (
            <OrderCard key={order.id} order={order}>
              <OrderActions order={order} />
            </OrderCard>
          ))}
          {orders.meta.last_page > 1 && (
            <div className="flex items-center justify-center gap-3 pt-2">
              {orders.links.prev && <Button size="sm" variant="secondary" href={orders.links.prev}>← Newer</Button>}
              <span className="text-sm text-gray-500">Page {orders.meta.current_page} of {orders.meta.last_page}</span>
              {orders.links.next && <Button size="sm" variant="secondary" href={orders.links.next}>Older →</Button>}
            </div>
          )}
        </div>
      ) : (
        <div className="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
          <EmptyState title="No orders here" description="Orders you place appear here so you can track them." action={<Button href="/search">Start shopping</Button>} />
        </div>
      )}
    </AccountLayout>
  );
}
