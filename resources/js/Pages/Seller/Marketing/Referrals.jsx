import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Button, Card, DataTable, EmptyState, PageHeader, TextInput } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';
import { formatDate } from '../../../lib/format';

export default function Referrals({ code, link, referred }) {
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(link);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      setCopied(false);
    }
  };

  return (
    <SellerLayout>
      <Head title="Referrals" />
      <PageHeader title="Referral program" description="Invite customers to Onetap with your personal link. Sign-ups through it are credited to you." />
      <Card className="max-w-2xl" title="Your referral link">
        <p className="mb-3 text-sm text-gray-600 dark:text-gray-400">
          Code <span className="font-mono font-semibold text-gray-800 dark:text-white/90">{code}</span> — share it on your shop's social pages or receipts.
        </p>
        <div className="flex gap-2">
          <TextInput readOnly aria-label="Referral link" value={link} onFocus={(event) => event.target.select()} />
          <Button variant="secondary" onClick={copy}>{copied ? 'Copied!' : 'Copy'}</Button>
        </div>
      </Card>
      <Card className="mt-6" title={`People who joined (${referred.length})`} bodyClassName="">
        <DataTable
          rowKey={(row) => `${row.name}-${row.joined_at}`}
          rows={referred}
          empty={<EmptyState title="No sign-ups yet" description="When someone registers with your link, they appear here." />}
          columns={[
            { key: 'name', label: 'Customer' },
            { key: 'joined_at', label: 'Joined', render: (row) => formatDate(row.joined_at) },
          ]}
        />
      </Card>
    </SellerLayout>
  );
}
