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
      { label: 'Products', icon: 'box' },
      { label: 'Orders', icon: 'receipt' },
      { label: 'Shop', icon: 'store' },
      { label: 'Marketing', icon: 'megaphone' },
    ],
  },
  {
    heading: 'Support & Money',
    items: [
      { label: 'Customer Service', icon: 'chat' },
      { label: 'Reviews', icon: 'star' },
      { label: 'Finance', icon: 'wallet' },
    ],
  },
];

export const logisticsNav = [
  { heading: 'Overview', items: [{ label: 'Dashboard', href: '/logistics', icon: 'grid', exact: true }] },
  {
    heading: 'Operations',
    items: [
      { label: 'Shipments', icon: 'truck' },
      { label: 'Riders', icon: 'bike' },
      { label: 'Service Coverage', icon: 'map' },
    ],
  },
  {
    heading: 'Money',
    items: [
      { label: 'COD & Remittances', icon: 'cash' },
      { label: 'Reports', icon: 'chart' },
    ],
  },
];

export const riderTabs = [
  { label: 'Home', href: '/rider', icon: 'home', exact: true },
  { label: 'Deliveries', icon: 'truck' },
  { label: 'COD', icon: 'cash' },
  { label: 'Profile', icon: 'user' },
];

export function isActivePath(currentUrl, item) {
  if (!item.href) {
    return false;
  }

  const path = currentUrl.split('?')[0];

  return item.exact ? path === item.href : path === item.href || path.startsWith(`${item.href}/`);
}
