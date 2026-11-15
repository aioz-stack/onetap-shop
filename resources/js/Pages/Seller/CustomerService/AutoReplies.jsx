import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Badge, Button, Card, Checkbox, ConfirmDialog, EmptyState, FormField, Modal, PageHeader, Select, Textarea, TextInput } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';

const typeInfo = {
  greeting: ['Greeting', 'Sent once when a customer starts a new chat.'],
  offline: ['Away message', 'Sent while your shop is in vacation mode.'],
  keyword: ['Keyword reply', 'Sent whenever a customer message contains the keyword.'],
};

function ReplyForm({ reply, onClose }) {
  const editing = Boolean(reply?.id);
  const form = useForm({ type: reply?.type ?? 'keyword', keyword: reply?.keyword ?? '', message: reply?.message ?? '', is_active: reply?.is_active ?? true });
  const save = () => {
    const options = { preserveScroll: true, onSuccess: onClose };
    if (editing) {
      // The type is fixed once created, so it is not sent on update.
      form.transform(({ type, ...rest }) => rest);
      form.put(`/seller/auto-replies/${reply.id}`, options);
    } else {
      form.post('/seller/auto-replies', options);
    }
  };

  return (
    <Modal open onClose={onClose} title={editing ? 'Edit automated reply' : 'New automated reply'} footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={form.processing} onClick={save}>Save</Button></>}>
      <div className="space-y-4">
        <FormField label="Type" error={form.errors.type} hint={typeInfo[form.data.type][1]}>
          {(id) => <Select id={id} disabled={editing} value={form.data.type} options={Object.entries(typeInfo).map(([value, [label]]) => ({ value, label }))} onChange={(event) => form.setData('type', event.target.value)} />}
        </FormField>
        {form.data.type === 'keyword' && (
          <FormField label="Keyword" required error={form.errors.keyword} hint="Not case-sensitive, e.g. “delivery” or “price”.">
            {(id) => <TextInput id={id} value={form.data.keyword} maxLength={50} onChange={(event) => form.setData('keyword', event.target.value)} />}
          </FormField>
        )}
        <FormField label="Message" required error={form.errors.message}>
          {(id) => <Textarea id={id} rows={4} maxLength={500} value={form.data.message} onChange={(event) => form.setData('message', event.target.value)} />}
        </FormField>
        <Checkbox label="Active" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} />
      </div>
    </Modal>
  );
}

export default function AutoReplies({ replies }) {
  const [editing, setEditing] = useState(null);
  const [removing, setRemoving] = useState(null);

  return (
    <SellerLayout>
      <Head title="Automated replies" />
      <PageHeader title="Automated replies" description="Answer common questions instantly, even when you are busy." actions={<Button onClick={() => setEditing({})}>Add reply</Button>} />
      <Card bodyClassName="">
        {replies.length ? (
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {replies.map((reply) => (
              <li key={reply.id} className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 text-sm">
                  <p className="flex flex-wrap items-center gap-2 font-medium text-gray-800 dark:text-white/90">
                    {typeInfo[reply.type][0]}
                    {reply.keyword && <Badge tone="brand">“{reply.keyword}”</Badge>}
                    {!reply.is_active && <Badge>Off</Badge>}
                  </p>
                  <p className="mt-1 whitespace-pre-line text-gray-600 dark:text-gray-400">{reply.message}</p>
                </div>
                <div className="flex shrink-0 gap-1">
                  <Button size="sm" variant="ghost" onClick={() => setEditing(reply)}>Edit</Button>
                  <Button size="sm" variant="ghost" className="text-error-600" onClick={() => setRemoving(reply)}>Delete</Button>
                </div>
              </li>
            ))}
          </ul>
        ) : (
          <EmptyState title="No automated replies" description="Start with a greeting so customers know you got their message." action={<Button onClick={() => setEditing({ type: 'greeting' })}>Add greeting</Button>} />
        )}
      </Card>
      {editing && <ReplyForm reply={editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog open={Boolean(removing)} onClose={() => setRemoving(null)} onConfirm={() => router.delete(`/seller/auto-replies/${removing.id}`, { preserveScroll: true, onFinish: () => setRemoving(null) })} title="Delete this reply?" confirmLabel="Delete" />
    </SellerLayout>
  );
}
