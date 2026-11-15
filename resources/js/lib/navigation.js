/**
 * Sidebar structure for each back-office panel.
 *
 * Items without an `href` belong to a later phase. They render as disabled entries
 * labeled "Soon" so the full panel structure is visible without dead links.
 * `badge` names a key in the shared `adminCounts` prop; its number shows next to the item.
 */

export const adminNav = [
  { heading: 'Overview', items: [{ label: 'Dashboard', href: '/admin', icon: 'grid', exact: true }] },
  {
    heading: 'People',
    items: [
      { label: 'Users', href: '/admin/users', icon: 'users' },
      { label: 'Verification', href: '/admin/verifications', icon: 'shield', badge: 'verifications' },
      { label: 'Sellers', href: '/admin/sellers', icon: 'store', badge: 'sellers' },
    ],
  },
  {
    heading: 'Catalog',
    items: [
      { label: 'Products', href: '/admin/products', icon: 'box', badge: 'products' },
      { label: 'Categories', href: '/admin/categories', icon: 'layers' },
    ],
  },
  {
    heading: 'Orders',
    items: [
      { label: 'Orders', href: '/admin/orders', icon: 'receipt' },
      { label: 'Returns & Refunds', href: '/admin/refunds', icon: 'cart', badge: 'refunds' },
    ],
  },
  {
    heading: 'Logistics',
    items: [
      { label: 'Partners & Coverage', href: '/admin/logistics/partners', icon: 'truck' },
      { label: 'Riders', href: '/admin/logistics/riders', icon: 'bike' },
      { label: 'Shipments', href: '/admin/logistics/shipments', icon: 'map' },
      { label: 'COD Monitoring', href: '/admin/logistics/cod', icon: 'cash' },
    ],
  },
  {
    heading: 'Finance',
    items: [
      { label: 'Overview', href: '/admin/finance', icon: 'wallet', exact: true },
      { label: 'Transactions', href: '/admin/finance/transactions', icon: 'clipboard' },
      { label: 'Seller Settlements', href: '/admin/finance/withdrawals', icon: 'cash', badge: 'withdrawals' },
      { label: 'Reports', href: '/admin/finance/reports', icon: 'chart' },
    ],
  },
  {
    heading: 'System',
    items: [
      { label: 'Settings', href: '/admin/settings', icon: 'settings' },
      { label: 'Audit Logs', href: '/admin/audit-logs', icon: 'shield' },
    ],
  },
];

export const sellerNav = [
  { heading: 'Overview', items: [{ label: 'Dashboard', href: '/seller', icon: 'grid', exact: true }] },
  {
    heading: 'Selling',
    items: [
      { label: 'Orders', href: '/seller/orders', icon: 'receipt', badge: 'toShip' },
      { label: 'Products', href: '/seller/products', icon: 'box' },
      { label: 'Inventory', href: '/seller/inventory', icon: 'layers' },
      { label: 'Shop Settings', href: '/seller/shop', icon: 'store' },
    ],
  },
  {
    heading: 'Customer Service',
    items: [
      { label: 'Chat', href: '/seller/chat', icon: 'chat', badge: 'messages' },
      { label: 'Auto-replies', href: '/seller/auto-replies', icon: 'bell' },
      { label: 'Reviews', href: '/seller/reviews', icon: 'star' },
    ],
  },
  {
    heading: 'Marketing',
    items: [
      { label: 'Vouchers', href: '/seller/vouchers', icon: 'tag' },
      { label: 'Promotions', href: '/seller/promotions', icon: 'megaphone' },
      { label: 'Referrals', href: '/seller/referrals', icon: 'users' },
    ],
  },
  {
    heading: 'Finance',
    items: [
      { label: 'Balance & Payouts', href: '/seller/finance', icon: 'wallet', exact: true },
      { label: 'Income Statement', href: '/seller/finance/statement', icon: 'chart' },
      { label: 'Transactions', href: '/seller/finance/transactions', icon: 'clipboard' },
    ],
  },
];

export const logisticsNav = [
  { heading: 'Overview', items: [{ label: 'Dashboard', href: '/logistics', icon: 'grid', exact: true }] },
  {
    heading: 'Operations',
    items: [
      { label: 'Shipments', href: '/logistics/shipments', icon: 'truck', badge: 'unassigned' },
      { label: 'Riders', href: '/logistics/riders', icon: 'bike', badge: 'pendingRiders' },
      { label: 'Service Coverage', href: '/logistics/coverage', icon: 'map' },
    ],
  },
  {
    heading: 'Money',
    items: [
      { label: 'Daily COD', href: '/logistics/cod', icon: 'cash' },
      { label: 'Rider Remittances', href: '/logistics/remittances', icon: 'wallet', badge: 'remittances' },
      { label: 'Earnings & Reports', href: '/logistics/earnings', icon: 'chart' },
    ],
  },
];

export const riderTabs = [
  { label: 'Home', href: '/rider', icon: 'home', exact: true },
  { label: 'Deliveries', href: '/rider/deliveries', icon: 'truck', badge: 'newJobs' },
  { label: 'COD', href: '/rider/cod', icon: 'cash', badge: 'cashInHand' },
  { label: 'Profile', href: '/rider/profile', icon: 'user' },
];

export function isActivePath(currentUrl, item) {
  if (!item.href) {
    return false;
  }

  const path = currentUrl.split('?')[0];

  return item.exact ? path === item.href : path === item.href || path.startsWith(`${item.href}/`);
}
