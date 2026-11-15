import { Head, Link } from '@inertiajs/react';
import ApexChart from '../../Components/ApexChart';
import { icon } from '../../Components/AppIcon';
import { Button, Card, EmptyState, PageHeader, StatCard, StatusBadge } from '../../Components/ui';
import SellerLayout from '../../Layouts/SellerLayout';
import { formatDateTime, formatMoney, formatNumber } from '../../lib/format';
import { useTheme } from '../../lib/ThemeContext';

function SalesChart({ chart }) {
  const { isDark } = useTheme();
  const muted = isDark ? '#98a2b3' : '#667085';

  return (
    <ApexChart
      options={{
        chart: { type: 'area', height: 260, fontFamily: 'Outfit, sans-serif', toolbar: { show: false }, background: 'transparent' },
        series: [{ name: 'Sales', data: chart.sales }],
        colors: ['#0f9d76'],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
        dataLabels: { enabled: false },
        grid: { borderColor: isDark ? '#1d2939' : '#e4e7ec', strokeDashArray: 4 },
        xaxis: { categories: chart.labels, labels: { style: { colors: muted }, hideOverlappingLabels: true, rotate: 0 }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { style: { colors: muted }, formatter: (value) => formatNumber(Math.round(value)) } },
        tooltip: { theme: isDark ? 'dark' : 'light', y: { formatter: (value) => formatMoney(value) } },
      }}
    />
  );
}

export default function Dashboard({ shop, stats, chart, toShip, lowStock }) {
  const hasSales = chart.orders.some((count) => count > 0);

  return (
    <SellerLayout>
      <Head title="Seller Center" />
      <PageHeader
        title={shop.name}
        description={<span className="inline-flex items-center gap-2"><StatusBadge status={shop.status} />{shop.vacation_mode && <StatusBadge status="offline" />} Your shop at a glance</span>}
        actions={
          <>
            <Button variant="secondary" size="sm" href={`/shops/${shop.slug}`}>View shop</Button>
            <Button size="sm" href="/seller/products/create">Add product</Button>
          </>
        }
      />

      {shop.vacation_mode && (
        <div className="mb-6 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-orange-400">
          Vacation mode is on, so customers cannot place orders. <Link href="/seller/shop" className="font-medium underline">Turn it off</Link>
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Total sales" value={formatMoney(stats.totalSales)} icon={icon('chart')} tone="brand" hint="Completed orders, before commission" />
        <StatCard label="Orders" value={formatNumber(stats.orders)} icon={icon('receipt')} tone="orange" hint={`${formatNumber(stats.pendingOrders)} waiting to ship`} />
        <StatCard label="Products sold" value={formatNumber(stats.productsSold)} icon={icon('box')} tone="success" hint={`${stats.liveProducts} of ${stats.products} products live`} />
        <StatCard label="Seller balance" value={formatMoney(stats.balance)} icon={icon('wallet')} tone="warning" hint={`${formatMoney(stats.availableBalance)} available · ${formatMoney(stats.pendingBalance)} on hold`} />
      </div>

      <Card className="mt-6" title="Sales analytics" description="Item sales over the last 30 days, excluding cancelled orders">
        {hasSales ? <SalesChart chart={chart} /> : <EmptyState title="No sales in the last 30 days" description="Share your shop link to get your first orders." />}
      </Card>

      <div className="mt-6 grid gap-6 xl:grid-cols-2">
        <Card title="To ship next" actions={<Link href="/seller/orders?tab=to_ship" className="text-sm font-medium text-brand-600">All orders</Link>}>
          {toShip.length ? (
            <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
              {toShip.map((order) => (
                <li key={order.id} className="flex items-center justify-between gap-3 py-3 first:pt-0">
                  <Link href={`/seller/orders/${order.id}`} className="min-w-0 hover:text-brand-600">
                    <span className="block font-medium text-gray-800 dark:text-white/90">{order.reference}</span>
                    <span className="text-xs text-gray-500">{order.items_count} item(s) · {formatDateTime(order.placed_at)}</span>
                  </Link>
                  <span className="flex items-center gap-2">{formatMoney(order.total)} <StatusBadge status={order.status} /></span>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState title="Nothing to ship" description="New orders show up here." />
          )}
        </Card>
        <Card title="Low stock" actions={<Link href="/seller/inventory?low=1" className="text-sm font-medium text-brand-600">Inventory</Link>}>
          {lowStock.length ? (
            <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
              {lowStock.map((item, index) => (
                <li key={index} className="flex items-center justify-between gap-3 py-3 first:pt-0">
                  <Link href={`/seller/products/${item.product_id}/edit`} className="min-w-0 truncate hover:text-brand-600">
                    {item.name}
                    {item.label !== 'Default' && <span className="text-gray-500"> · {item.label}</span>}
                  </Link>
                  <span className={item.available === 0 ? 'font-semibold text-error-600' : 'text-warning-600'}>{item.available} left</span>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState title="Stock looks healthy" />
          )}
        </Card>
      </div>
    </SellerLayout>
  );
}
