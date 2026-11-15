import { Head } from '@inertiajs/react';
import ActionButton from '../../../Components/admin/ActionButton';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { Badge, Card, DataTable, EmptyState, PageHeader, Pagination, StatusBadge } from '../../../Components/ui';
import LogisticsLayout from '../../../Layouts/LogisticsLayout';
import { formatDate, formatMoney } from '../../../lib/format';

export default function Remittances({ remittances, status, counts }) {
  return (
    <LogisticsLayout>
      <Head title="Rider remittances" />
      <PageHeader title="Rider remittances" description="Riders declare the COD cash they hand over. Count it and confirm; mismatches are kept as disputed." />
      <Card bodyClassName="">
        <StatusTabs tabs={[{ key: 'submitted', label: 'To review' }, { key: 'disputed', label: 'Disputed' }, { key: 'confirmed', label: 'Confirmed' }]} active={status} counts={counts} />
        <DataTable
          rows={remittances.data}
          empty={<EmptyState title={status === 'submitted' ? 'Nothing to review' : `No ${status} remittances`} description="Riders submit remittances from the Rider app at the end of their shift." />}
          columns={[
            { key: 'remittance_date', label: 'Day', className: 'whitespace-nowrap', render: (row) => formatDate(row.remittance_date) },
            { key: 'rider', label: 'Rider' },
            { key: 'collections_count', label: 'Deliveries' },
            { key: 'expected_amount', label: 'Expected', render: (row) => formatMoney(row.expected_amount) },
            { key: 'remitted_amount', label: status === 'submitted' ? 'Rider declared' : 'Counted', render: (row) => formatMoney(row.remitted_amount) },
            { key: 'discrepancy', label: 'Difference', render: (row) => (Number(row.discrepancy) !== 0 ? <Badge tone="error">{formatMoney(row.discrepancy)}</Badge> : <span className="text-gray-400">—</span>) },
            { key: 'status', label: 'Status', render: (row) => <span><StatusBadge status={row.status} />{row.confirmer && <span className="block text-xs text-gray-500">by {row.confirmer}</span>}{row.notes && <span className="block max-w-[200px] truncate text-xs text-gray-500">{row.notes}</span>}</span> },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (row) =>
                row.status !== 'confirmed' && (
                  <ActionButton
                    url={`/logistics/remittances/${row.id}/confirm`}
                    label={row.status === 'disputed' ? 'Recount' : 'Count & confirm'}
                    variant="primary"
                    message={`Expected ${formatMoney(row.expected_amount)} from ${row.rider}. Enter the cash you actually received.`}
                    fields={[
                      { name: 'received_amount', label: 'Cash received (₱)', type: 'text', required: true, placeholder: row.expected_amount },
                      { name: 'notes', label: 'Notes', type: 'text' },
                    ]}
                  />
                ),
            },
          ]}
        />
        <Pagination paginator={remittances} />
      </Card>
    </LogisticsLayout>
  );
}
