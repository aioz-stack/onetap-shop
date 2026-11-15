import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { icon } from '../../../Components/AppIcon';
import { Badge, Button, Card, ConfirmDialog, DataTable, EmptyState, FormField, Modal, PageHeader, Select, StatCard, StatusBadge, TextInput } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';
import { formatDateTime, formatMoney } from '../../../lib/format';

const BANKS = ['BDO', 'BPI', 'Metrobank', 'Landbank', 'PNB', 'Security Bank', 'UnionBank', 'RCBC', 'China Bank', 'GCash', 'Maya'];

function AddAccount({ onClose }) {
  const form = useForm({ bank_name: 'BDO', account_name: '', account_number: '' });

  return (
    <Modal open onClose={onClose} title="Add payout account" footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={form.processing} onClick={() => form.post('/seller/finance/bank-accounts', { preserveScroll: true, onSuccess: onClose })}>Save account</Button></>}>
      <div className="space-y-4">
        <FormField label="Bank or e-wallet" required error={form.errors.bank_name}>
          {(id) => <Select id={id} value={form.data.bank_name} options={BANKS.map((bank) => ({ value: bank, label: bank }))} onChange={(event) => form.setData('bank_name', event.target.value)} />}
        </FormField>
        <FormField label="Account name" required error={form.errors.account_name} hint="Exactly as registered with the bank.">
          {(id) => <TextInput id={id} value={form.data.account_name} error={form.errors.account_name} onChange={(event) => form.setData('account_name', event.target.value)} />}
        </FormField>
        <FormField label="Account number" required error={form.errors.account_number} hint="Stored encrypted. Only the last 4 digits are shown afterwards.">
          {(id) => <TextInput id={id} inputMode="numeric" autoComplete="off" value={form.data.account_number} error={form.errors.account_number} onChange={(event) => form.setData('account_number', event.target.value)} />}
        </FormField>
      </div>
    </Modal>
  );
}

export default function Overview({ balance, income, holdDays, minWithdrawal, bankAccounts, recentWithdrawals }) {
  const [adding, setAdding] = useState(false);
  const [removing, setRemoving] = useState(null);
  const withdraw = useForm({ amount: '', bank_account_id: bankAccounts.find((account) => account.is_default)?.id ?? '' });
  const canWithdraw = bankAccounts.length > 0 && Number(balance.available) >= Number(minWithdrawal);

  return (
    <SellerLayout>
      <Head title="Balance & payouts" />
      <PageHeader title="Balance & payouts" description={`Earnings become available ${holdDays} day(s) after the customer confirms they received the order.`} />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Available to withdraw" value={formatMoney(balance.available)} icon={icon('wallet')} tone="brand" />
        <StatCard label="On hold" value={formatMoney(balance.pending)} icon={icon('cash')} tone="warning" hint={`${formatMoney(income.toRelease)} from completed orders`} />
        <StatCard label="Income this month" value={formatMoney(income.thisMonth)} icon={icon('chart')} tone="success" hint={`Last month ${formatMoney(income.lastMonth)}`} />
        <StatCard label="Orders in progress" value={formatMoney(income.inProgress)} icon={icon('truck')} tone="gray" hint="Your earning once delivered and confirmed" />
      </div>

      <div className="mt-6 grid gap-6 xl:grid-cols-2">
        <Card title="Request a withdrawal" description={`Minimum ${formatMoney(minWithdrawal)}. Onetap reviews and transfers within 1–3 business days.`}>
          <form className="grid gap-4 sm:grid-cols-2" noValidate onSubmit={(event) => { event.preventDefault(); withdraw.post('/seller/finance/withdrawals', { preserveScroll: true, onSuccess: () => withdraw.reset('amount') }); }}>
            <FormField label="Amount (₱)" required error={withdraw.errors.amount}>
              {(id) => <TextInput id={id} type="number" min="1" step="0.01" max={balance.available} value={withdraw.data.amount} error={withdraw.errors.amount} onChange={(event) => withdraw.setData('amount', event.target.value)} />}
            </FormField>
            <FormField label="Send to" required error={withdraw.errors.bank_account_id}>
              {(id) => <Select id={id} value={withdraw.data.bank_account_id} placeholder={bankAccounts.length ? 'Choose account' : 'Add an account first'} options={bankAccounts.map((account) => ({ value: account.id, label: `${account.bank_name} ••••${account.last4}` }))} onChange={(event) => withdraw.setData('bank_account_id', event.target.value)} />}
            </FormField>
            <div className="flex items-center gap-3 sm:col-span-2">
              <Button type="submit" loading={withdraw.processing} disabled={!canWithdraw}>Request withdrawal</Button>
              <button type="button" className="text-sm text-brand-600 hover:underline" onClick={() => withdraw.setData('amount', balance.available)}>Withdraw all</button>
            </div>
          </form>
        </Card>

        <Card title="Payout accounts" actions={<Button size="sm" variant="secondary" onClick={() => setAdding(true)} disabled={bankAccounts.length >= 5}>Add account</Button>}>
          {bankAccounts.length ? (
            <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
              {bankAccounts.map((account) => (
                <li key={account.id} className="flex items-center justify-between gap-3 py-3 first:pt-0">
                  <span>
                    <span className="font-medium text-gray-800 dark:text-white/90">{account.bank_name} ••••{account.last4}</span> {account.is_default && <Badge tone="brand">Default</Badge>}
                    <span className="block text-xs text-gray-500">{account.account_name}</span>
                  </span>
                  <span className="flex gap-1">
                    {!account.is_default && <Button size="sm" variant="ghost" onClick={() => router.post(`/seller/finance/bank-accounts/${account.id}/default`, {}, { preserveScroll: true })}>Make default</Button>}
                    <Button size="sm" variant="ghost" className="text-error-600" onClick={() => setRemoving(account)}>Remove</Button>
                  </span>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState title="No payout account yet" description="Add a bank or e-wallet account to receive your earnings." />
          )}
        </Card>
      </div>

      <Card className="mt-6" title="Recent withdrawals" bodyClassName="">
        <DataTable
          rows={recentWithdrawals}
          empty={<EmptyState title="No withdrawals yet" />}
          columns={[
            { key: 'created_at', label: 'Requested', className: 'whitespace-nowrap', render: (row) => formatDateTime(row.created_at) },
            { key: 'amount', label: 'Amount', render: (row) => formatMoney(row.amount) },
            { key: 'bank', label: 'To' },
            { key: 'status', label: 'Status', render: (row) => <span><StatusBadge status={row.status} /><span className="block text-xs text-gray-500">{row.reference ?? row.notes ?? ''}</span></span> },
          ]}
        />
      </Card>

      {adding && <AddAccount onClose={() => setAdding(false)} />}
      <ConfirmDialog open={Boolean(removing)} onClose={() => setRemoving(null)} onConfirm={() => router.delete(`/seller/finance/bank-accounts/${removing.id}`, { preserveScroll: true, onFinish: () => setRemoving(null) })} title="Remove this payout account?" message={removing ? `${removing.bank_name} ••••${removing.last4}` : ''} confirmLabel="Remove" />
    </SellerLayout>
  );
}
