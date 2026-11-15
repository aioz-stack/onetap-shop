import { Tabs } from '../ui';
import { withQuery } from '../../lib/query';

/**
 * Tabs driven by a query-string parameter, with record counts.
 *
 * tabs: [{ key, label }] — use key null for an "All" tab.
 */
export default function StatusTabs({ tabs, param = 'status', active, counts = {} }) {
  return (
    <div className="px-4">
      <Tabs
        active={active ?? null}
        tabs={tabs.map((tab) => ({
          key: tab.key,
          label: tab.label,
          count: counts[tab.key ?? 'all'],
          href: withQuery({ [param]: tab.key }),
        }))}
      />
    </div>
  );
}
