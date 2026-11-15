import { Head, Link } from '@inertiajs/react';
import ApexChart from '../../Components/ApexChart';
import { icon } from '../../Components/AppIcon';
import { Card, DataTable, EmptyState, PageHeader, StatCard } from '../../Components/ui';
import AdminLayout from '../../Layouts/AdminLayout';
import { formatDateTime, formatMoney, formatNumber, headline } from '../../lib/format';
import { useTheme } from '../../lib/ThemeContext';

function SalesChart({ chart }) {
  const { isDark } = useTheme();
  const muted = isDark ? '#98a2b3' : '#667085';

  const options = {
    chart: { type: 'area', height: 300, fontFamily: 'Outfit, sans-serif', toolbar: { show: false }, background: 'transparent' },
    series: [
      { name: 'Sales', type: 'area', data: chart.sales },
      { name: 'Orders', type: 'line', data: chart.orders },
    ],
    colors: ['#0f9d76', '#fb6514'],
    stroke: { curve: 'smooth', width: [2, 2] },
    fill: { type: ['gradient', 'solid'], gradient: { opacityFrom: 0.35, opacityTo: 0 } },
    dataLabels: { enabled: false },
    legend: { position: 'top', horizontalAlign: 'left', labels: { colors: muted } },
    grid: { borderColor: isDark ? '#1d2939' : '#e4e7ec', strokeDashArray: 4 },
    xaxis: { categories: chart.labels, labels: { style: { colors: muted }, rotate: 0, hideOverlappingLabels: true }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: [
      { title: { text: 'Sales (₱)', style: { color: muted } }, labels: { style: { colors: muted }, formatter: (value) => formatNumber(Math.round(value)) } },
      { opposite: true, title: { text: 'Orders', style: { color: muted } }, labels: { style: { colors: muted }, formatter: (value) => Math.round(value) } },
    ],
    tooltip: { theme: isDark ? 'dark' : 'light', y: { formatter: (value, { seriesIndex }) => (seriesIndex === 0 ? formatMoney(value) : `${value} orders`) } },
  };

  return <ApexChart options={options} />;
}

export default function Dashboard({ stats, salesChart, recentTransactions, recentActivities }) {
  const cards = [
    { label: 'Marketplace revenue', value: formatMoney(stats.revenue), icon: icon('wallet'), tone: 'brand', hint: `Commission on ${formatMoney(stats.gmv)} completed sales` },
    { label: 'Total orders', value: formatNumber(stats.orders), icon: icon('receipt'), tone: 'orange' },
    { label: 'Pending orders', value: formatNumber(stats.pendingOrders), icon: icon('clipboard'), tone: 'warning', hint: 'Waiting to ship' },
    { label: 'Completed orders', value: formatNumber(stats.completedOrders), icon: icon('star'), tone: 'success' },
    { label: 'Cancelled orders', value: formatNumber(stats.cancelledOrders), icon: icon('receipt'), tone: 'error' },
    { label: 'Customers', value: formatNumber(stats.customers), icon: icon('users'), tone: 'success' },
    { label: 'Sellers', value: formatNumber(stats.sellers), icon: icon('store'), tone: 'warning' },
    { label: 'Products', value: formatNumber(stats.products), icon: icon('box'), tone: 'gray' },
    { label: 'Logistics partners', value: formatNumber(stats.logisticsPartners), icon: icon('truck'), tone: 'brand' },
    { label: 'Riders', value: formatNumber(stats.riders), icon: icon('bike'), tone: 'orange' },
  ];

  const hasSales = salesChart.orders.some((count) => count > 0);

  return (
    <AdminLayout>
      <Head title="Admin dashboard" />
      <PageHeader title="Dashboard" description="Live marketplace figures." />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        {cards.map((card) => (
          <StatCard key={card.label} {...card} />
        ))}
      </div>

      <Card className="mt-6" title="Sales analytics" description="Last 30 days, excluding cancelled orders">
        {hasSales ? <SalesChart chart={salesChart} /> : <EmptyState title="No sales yet" description="The chart fills in as customers place orders." />}
      </Card>

      <div className="mt-6 grid gap-6 xl:grid-cols-2">
        <Card title="Recent transactions" actions={<Link href="/admin/finance/transactions" className="text-sm font-medium text-brand-600 hover:text-brand-700">View all</Link>} bodyClassName="">
          <DataTable
            rows={recentTransactions}
            empty={<EmptyState title="No transactions yet" description="Earnings, payouts and refunds are recorded here." />}
            columns={[
              { key: 'type', label: 'Type', render: (row) => headline(row.type) },
              { key: 'shop', label: 'Shop', render: (row) => row.shop ?? '—' },
              {
                key: 'amount',
                label: 'Amount',
                className: 'text-right whitespace-nowrap',
                headerClassName: 'text-right',
                render: (row) => (
                  <span className={row.direction === 'credit' ? 'text-success-600' : 'text-gray-800 dark:text-white/90'}>
                    {row.direction === 'credit' ? '+' : '−'}
                    {formatMoney(row.amount)}
                  </span>
                ),
              },
              { key: 'created_at', label: 'When', className: 'whitespace-nowrap text-gray-500', render: (row) => formatDateTime(row.created_at) },
            ]}
          />
        </Card>

        <Card title="Recent activity" actions={<Link href="/admin/audit-logs" className="text-sm font-medium text-brand-600 hover:text-brand-700">Audit log</Link>}>
          {recentActivities.length ? (
            <ol className="space-y-4">
              {recentActivities.map((activity) => (
                <li key={activity.id} className="flex items-start gap-3">
                  <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500" aria-hidden="true" />
                  <div className="min-w-0 text-sm">
                    <p className="text-gray-800 dark:text-white/90">
                      <span className="font-medium">{activity.user}</span> · {headline(activity.action.replace('.', ' '))}
                      {activity.subject && <span className="text-gray-500"> · {activity.subject}</span>}
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">{formatDateTime(activity.created_at)}</p>
                  </div>
                </li>
              ))}
            </ol>
          ) : (
            <EmptyState title="No activity yet" />
          )}
        </Card>
      </div>
    </AdminLayout>
  );
}
