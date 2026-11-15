import { Head } from '@inertiajs/react';
import { icon } from '../../Components/AppIcon';
import { Card, PageHeader, StatCard } from '../../Components/ui';
import AdminLayout from '../../Layouts/AdminLayout';
import { formatMoney, formatNumber } from '../../lib/format';

export default function Dashboard({ stats }) {
  const cards = [
    { label: 'Marketplace revenue', value: formatMoney(stats.revenue), icon: icon('wallet'), tone: 'brand', hint: 'Commission from completed orders' },
    { label: 'Orders', value: formatNumber(stats.orders), icon: icon('receipt'), tone: 'orange', hint: `${formatNumber(stats.pendingOrders)} pending` },
    { label: 'Customers', value: formatNumber(stats.customers), icon: icon('users'), tone: 'success' },
    { label: 'Sellers', value: formatNumber(stats.sellers), icon: icon('store'), tone: 'warning' },
    { label: 'Products', value: formatNumber(stats.products), icon: icon('box'), tone: 'gray' },
    { label: 'Logistics partners', value: formatNumber(stats.logisticsPartners), icon: icon('truck'), tone: 'brand' },
    { label: 'Riders', value: formatNumber(stats.riders), icon: icon('bike'), tone: 'orange' },
  ];

  return (
    <AdminLayout>
      <Head title="Admin dashboard" />
      <PageHeader title="Dashboard" description="Marketplace totals, read live from the database." />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {cards.map((card) => (
          <StatCard key={card.label} {...card} />
        ))}
      </div>

      <Card className="mt-6" title="Coming in Phase 3">
        <p className="text-sm text-gray-600 dark:text-gray-400">
          Sales analytics, recent transactions, seller approvals, product moderation, order management, logistics and finance
          tools are built in the next phase. The sidebar shows where each module will live.
        </p>
      </Card>
    </AdminLayout>
  );
}
