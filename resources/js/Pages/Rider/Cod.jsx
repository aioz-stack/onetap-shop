import { Head, Link, useForm } from '@inertiajs/react';
import { icon } from '../../Components/AppIcon';
import { Button, Card, EmptyState, FormField, Pagination, StatCard, StatusBadge, TextInput } from '../../Components/ui';
import RiderLayout from '../../Layouts/RiderLayout';
import { formatDate, formatDateTime, formatMoney, formatNumber } from '../../lib/format';

function RemitForm({ summary }) {
  const form = useForm({ amount: summary.inHand, notes: '' });
  const differs = form.data.amount !== '' && Number(form.data.amount) !== Number(summary.inHand);

  return (
    <Card title="Remit cash" description="Hand the cash to your logistics office, then record it here. They confirm after counting.">
      <form
        className="space-y-4"
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/rider/cod/remit', { preserveScroll: true, onSuccess: () => form.reset('notes') });
        }}
      >
        <FormField label="Amount handed over (₱)" required error={form.errors.amount} hint={differs ? `Expected ${formatMoney(summary.inHand)}. The difference will be flagged for review.` : `${formatNumber(summary.inHandCount)} delivery collection(s) included.`}>
          {(id) => <TextInput id={id} type="number" inputMode="decimal" min="0" step="0.01" value={form.data.amount} error={form.errors.amount || differs} onChange={(event) => form.setData('amount', event.target.value)} />}
        </FormField>
        <FormField label="Note (optional)" error={form.errors.notes}>
          {(id) => <TextInput id={id} value={form.data.notes} placeholder="e.g. handed to Ana at the hub" onChange={(event) => form.setData('notes', event.target.value)} />}
        </FormField>
        <Button type="submit" size="lg" className="w-full" loading={form.processing}>Submit remittance</Button>
      </form>
    </Card>
  );
}

export default function Cod({ summary, collections, earnings, remittances }) {
  const today = earnings[0];

  return (
    <RiderLayout title="Cash on delivery">
      <Head title="COD" />

      <div className="grid grid-cols-2 gap-3">
        <StatCard compact label="Collected today" value={formatMoney(summary.collectedToday)} icon={icon('cash')} tone="orange" />
        <StatCard compact label="Cash in hand" value={formatMoney(summary.inHand)} hint="Not yet remitted" icon={icon('wallet')} tone="brand" />
        <StatCard compact label="Awaiting confirmation" value={formatMoney(summary.pendingConfirmation)} icon={icon('clipboard')} tone="warning" />
        <StatCard compact label="Discrepancies" value={formatNumber(summary.discrepancies)} icon={icon('shield')} tone={summary.discrepancies ? 'error' : 'gray'} />
      </div>

      <div className="mt-5">
        {Number(summary.inHand) > 0 ? (
          <RemitForm key={summary.inHand} summary={summary} />
        ) : (
          <Card bodyClassName="p-4"><p className="text-sm text-gray-600 dark:text-gray-400">No cash waiting to be remitted. Collections appear here after each COD delivery.</p></Card>
        )}
      </div>

      <Card title="Collections" description="Cash still with you, plus everything collected today." className="mt-5" bodyClassName="p-4">
        {collections.length ? (
          <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
            {collections.map((collection) => (
              <li key={collection.id} className="flex items-center justify-between gap-3 py-2.5">
                <Link href={`/rider/deliveries/${collection.shipment_id}`} className="min-w-0">
                  <span className="block truncate font-mono text-gray-800 dark:text-white/90">{collection.tracking_number}</span>
                  <span className="block text-xs text-gray-500">{formatDateTime(collection.collected_at)}{collection.remitted && ' · remitted'}</span>
                </Link>
                <span className="text-right">
                  <span className="block font-medium text-gray-800 dark:text-white/90">{formatMoney(collection.amount_collected)}</span>
                  {collection.status === 'discrepancy' ? <span className="text-xs text-error-600">due {formatMoney(collection.amount_due)}</span> : <StatusBadge status={collection.status} />}
                </span>
              </li>
            ))}
          </ul>
        ) : (
          <EmptyState title="No collections yet" />
        )}
      </Card>

      <Card title="Earnings" description={`${formatMoney(today.rate)} per delivered parcel, set by your logistics partner.`} className="mt-5" bodyClassName="p-4">
        <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
          {earnings.map((day) => (
            <li key={day.date} className="flex justify-between py-2">
              <span className="text-gray-700 dark:text-gray-300">{day.date === today.date ? 'Today' : formatDate(day.date, { weekday: 'short', month: 'short', day: 'numeric' })}<span className="ml-2 text-xs text-gray-500">{day.delivered} delivered</span></span>
              <span className="font-medium text-gray-800 dark:text-white/90">{formatMoney(day.earnings)}</span>
            </li>
          ))}
        </ul>
      </Card>

      <Card title="Remittance history" className="mt-5" bodyClassName="p-4">
        {remittances.data.length ? (
          <>
            <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
              {remittances.data.map((remittance) => (
                <li key={remittance.id} className="py-2.5">
                  <div className="flex items-center justify-between gap-3">
                    <span className="text-gray-800 dark:text-white/90">{formatDateTime(remittance.created_at)}</span>
                    <StatusBadge status={remittance.status} />
                  </div>
                  <p className="mt-0.5 text-xs text-gray-500">
                    Remitted {formatMoney(remittance.remitted_amount)} of {formatMoney(remittance.expected_amount)}
                    {Number(remittance.discrepancy) !== 0 && <span className="text-error-600"> · difference {formatMoney(remittance.discrepancy)}</span>}
                    {remittance.notes && ` · ${remittance.notes}`}
                  </p>
                </li>
              ))}
            </ul>
            <Pagination paginator={remittances} />
          </>
        ) : (
          <EmptyState title="No remittances yet" />
        )}
      </Card>
    </RiderLayout>
  );
}
