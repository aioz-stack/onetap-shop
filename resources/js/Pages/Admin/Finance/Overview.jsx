import { Head, Link } from '@inertiajs/react';
import ApexChart from '../../../Components/ApexChart';
import { icon } from '../../../Components/AppIcon';
import { Card, PageHeader, StatCard } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatMoney, formatNumber } from '../../../lib/format';
import { useTheme } from '../../../lib/ThemeContext';

function MonthlyChart({ monthly }) {
  const { isDark } = useTheme();
  const muted = isDark ? '#98a2b3' : '#667085';

  return (
    <ApexChart
      options={{
        chart: { type: 'bar', height: 280, fontFamily: 'Outfit, sans-serif', toolbar: { show: false }, background: 'transparent' },
        series: [
          { name: 'Commission', data: monthly.map((row) => Number(row.commission)) },
          { name: 'Seller earnings', data: monthly.map((row) => Number(row.sellerEarnings)) },
          { name: 'Logistics fees', data: monthly.map((row) => Number(row.logisticsFees)) },
        ],
        colors: ['#0f9d76', '#5ac8a5', '#fb6514'],
        plotOptions: { bar: { columnWidth: '45%', borderRadius: 4 } },
        dataLabels: { enabled: false },
        legend: { position: 'top', horizontalAlign: 'left', labels: { colors: muted } },
        grid: { borderColor: isDark ? '#1d2939' : '#e4e7ec', strokeDashArray: 4 },
        xaxis: { categories: monthly.map((row) => row.label), labels: { style: { colors: muted } }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { style: { colors: muted }, formatter: (value) => formatNumber(Math.round(value)) } },
        tooltip: { theme: isDark ? 'dark' : 'light', y: { formatter: (value) => formatMoney(value) } },
      }}
    />
  );
}

export default function Overview({ allTime, thisMonth, monthly }) {
  const cards = [
    { label: 'Marketplace revenue', value: formatMoney(allTime.netRevenue), icon: icon('wallet'), tone: 'brand', hint: `Commission ${formatMoney(allTime.commission)} − platform vouchers ${formatMoney(allTime.platformVouchers)} · ${formatMoney(thisMonth.netRevenue)} this month` },
    { label: 'Completed sales (GMV)', value: formatMoney(allTime.gmv), icon: icon('chart'), tone: 'success', hint: `${formatMoney(thisMonth.gmv)} this month` },
    { label: 'Seller earnings', value: formatMoney(allTime.sellerEarnings), icon: icon('store'), tone: 'warning' },
    { label: 'Logistics fees', value: formatMoney(allTime.logisticsFees), icon: icon('truck'), tone: 'orange' },
    { label: 'Refunds', value: formatMoney(allTime.refunds), icon: icon('receipt'), tone: 'error' },
    { label: 'Paid out to sellers', value: formatMoney(allTime.paidOut), icon: icon('cash'), tone: 'gray' },
    { label: 'Payouts awaiting action', value: formatMoney(allTime.pendingPayouts), icon: icon('clipboard'), tone: 'warning' },
  ];

  return (
    <AdminLayout>
      <Head title="Finance" />
      <PageHeader
        title="Finance overview"
        description="Revenue is recognised when a seller order is completed."
        breadcrumbs={[{ label: 'Admin', href: '/admin' }, { label: 'Finance' }]}
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {cards.map((card) => (
          <StatCard key={card.label} {...card} />
        ))}
      </div>

      <Card className="mt-6" title="Last 6 months" actions={<Link href="/admin/finance/reports" className="text-sm font-medium text-brand-600">Full report</Link>}>
        <MonthlyChart monthly={monthly} />
      </Card>

      <div className="mt-6 grid gap-4 sm:grid-cols-3">
        {[
          ['Transactions', 'Every ledger entry, with CSV export.', '/admin/finance/transactions'],
          ['Seller settlements', 'Approve and record seller payouts.', '/admin/finance/withdrawals'],
          ['Rider remittances', 'COD cash handed back by riders.', '/admin/logistics/cod?view=remittances'],
        ].map(([title, text, href]) => (
          <Link key={href} href={href} className="rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 dark:border-gray-800 dark:bg-white/[0.03]">
            <p className="font-semibold text-gray-800 dark:text-white/90">{title}</p>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{text}</p>
          </Link>
        ))}
      </div>
    </AdminLayout>
  );
}
