/**
 * Sidebar structure for each back-office panel.
 *
 * Items without an `href` belong to a later phase. They render as disabled entries
 * labeled "Soon" so the full panel structure is visible without dead links.
 * When a module ships, give its item an `href`.
 */

export const adminNav = [
  { heading: 'Overview', items: [{ label: 'Dashboard', href: '/admin', icon: 'grid', exact: true }] },
  {
    heading: 'Marketplace',
    items: [
      { label: 'Users', href: '/crud', icon: 'users' },
      { label: 'Sellers', icon: 'store' },
      { label: 'Products', icon: 'box' },
      { label: 'Categories', icon: 'layers' },
      { label: 'Orders', icon: 'receipt' },
    ],
  },
  {
    heading: 'Operations',
    items: [
      { label: 'Logistics', icon: 'truck' },
      { label: 'Finance', icon: 'wallet' },
      { label: 'Reports', icon: 'chart' },
    ],
  },
  {
    heading: 'System',
    items: [
      { label: 'Settings', icon: 'settings' },
      { label: 'Audit Logs', icon: 'shield' },
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
