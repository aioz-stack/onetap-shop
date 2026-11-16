import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Badge, Button, Card, Checkbox, ConfirmDialog, EmptyState, FormField, Modal, PageHeader, Select, Switch, Textarea, TextInput } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';

const typeInfo = {
  greeting: ['Greeting', 'Sent once when a customer starts a new chat during working hours.'],
  off_work: ['Off-work message', 'Sent when a customer messages outside your working hours.'],
  offline: ['Away message', 'Sent while your shop is in vacation mode.'],
  keyword: ['Keyword reply', 'Sent whenever a customer message contains the keyword.'],
};

// ISO weekdays (1 = Monday … 7 = Sunday), matching the server.
const DAYS = [
  [1, 'Mon'],
  [2, 'Tue'],
  [3, 'Wed'],
  [4, 'Thu'],
  [5, 'Fri'],
  [6, 'Sat'],
  [7, 'Sun'],
];

const DEFAULT_OFF_WORK = 'Thanks for your message! We are closed right now. We will reply as soon as we are back during working hours.';

function formatTime(value) {
  if (!value) {
    return '';
  }

  const [hours, minutes] = value.split(':').map(Number);
  const suffix = hours >= 12 ? 'PM' : 'AM';

  return `${hours % 12 || 12}:${String(minutes).padStart(2, '0')} ${suffix}`;
}

function describeDays(days) {
  const sorted = [...days].sort((a, b) => a - b);

  if (sorted.length === 7) {
    return 'Every day';
  }

  if (sorted.join() === '1,2,3,4,5') {
    return 'Mon–Fri';
  }

  if (sorted.join() === '1,2,3,4,5,6') {
    return 'Mon–Sat';
  }

  return sorted.map((day) => DAYS.find(([value]) => value === day)?.[1]).join(', ');
}

function ReplyForm({ reply, types, onClose }) {
  const editing = Boolean(reply?.id);
  const form = useForm({ type: reply?.type ?? types[0], keyword: reply?.keyword ?? '', message: reply?.message ?? '', is_active: reply?.is_active ?? true });
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
    <Modal open onClose={onClose} title={editing ? `Edit ${typeInfo[form.data.type][0].toLowerCase()}` : `New ${typeInfo[form.data.type][0].toLowerCase()}`} footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={form.processing} onClick={save}>Save</Button></>}>
      <div className="space-y-4">
        {types.length > 1 && !editing && (
          <FormField label="Type" error={form.errors.type} hint={typeInfo[form.data.type][1]}>
            {(id) => <Select id={id} value={form.data.type} options={types.map((value) => ({ value, label: typeInfo[value][0] }))} onChange={(event) => form.setData('type', event.target.value)} />}
          </FormField>
        )}
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

function ReplyRow({ reply, onEdit, onRemove, hideStatus = false }) {
  return (
    <li className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
      <div className={`min-w-0 text-sm ${hideStatus && !reply.is_active ? 'opacity-60' : ''}`}>
        <p className="flex flex-wrap items-center gap-2 font-medium text-gray-800 dark:text-white/90">
          {typeInfo[reply.type][0]}
          {reply.keyword && <Badge tone="brand">“{reply.keyword}”</Badge>}
          {!hideStatus && !reply.is_active && <Badge>Off</Badge>}
        </p>
        <p className="mt-1 whitespace-pre-line text-gray-600 dark:text-gray-400">{reply.message}</p>
      </div>
      <div className="flex shrink-0 gap-1">
        <Button size="sm" variant="ghost" onClick={() => onEdit(reply)}>Edit</Button>
        <Button size="sm" variant="ghost" className="text-error-600" onClick={() => onRemove(reply)}>Delete</Button>
      </div>
    </li>
  );
}

/**
 * Off-work message with the shop's working hours. Saved in place (no modal), right below the greeting.
 */
function OffWorkCard({ reply, timezone, onRemove }) {
  const form = useForm({
    type: 'off_work',
    message: reply?.message ?? DEFAULT_OFF_WORK,
    days: reply?.schedule?.days ?? [1, 2, 3, 4, 5, 6],
    start: reply?.schedule?.start ?? '08:00',
    end: reply?.schedule?.end ?? '17:00',
    is_active: reply?.is_active ?? true,
  });
  const toggleDay = (day) => form.setData('days', form.data.days.includes(day) ? form.data.days.filter((value) => value !== day) : [...form.data.days, day]);
  const overnight = form.data.start && form.data.end && form.data.end < form.data.start;
  const [switching, setSwitching] = useState(false);
  // Saving hours or text never changes on/off: the switch in the header owns that.
  const save = (activate = false) => {
    const options = { preserveScroll: true };
    if (reply?.id) {
      form.transform(({ type, is_active, ...rest }) => rest);
      form.put(`/seller/auto-replies/${reply.id}`, options);
    } else {
      form.transform((data) => ({ ...data, is_active: activate }));
      form.post('/seller/auto-replies', options);
    }
  };
  const toggle = (on) => {
    if (!reply) {
      // Nothing saved yet: switching on saves the form as it is, turned on.
      if (on) {
        save(true);
      }

      return;
    }

    router.patch(`/seller/auto-replies/${reply.id}/active`, { is_active: on }, { preserveScroll: true, onStart: () => setSwitching(true), onFinish: () => setSwitching(false) });
  };

  return (
    <Card
      title="Off-work message"
      description="Sent automatically when a customer messages your shop outside your working hours, so they know when to expect a reply."
      className="mt-6"
      pinActions
      actions={<Switch label="Off-work message" checked={Boolean(reply?.is_active)} busy={switching || (!reply && form.processing)} onChange={toggle} />}
    >
      <div className="space-y-5">
        <fieldset>
          <legend className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-400">Working days</legend>
          <div className="flex flex-wrap gap-2">
            {DAYS.map(([day, label]) => {
              const on = form.data.days.includes(day);

              return (
                <button
                  key={day}
                  type="button"
                  aria-pressed={on}
                  onClick={() => toggleDay(day)}
                  className={`h-9 min-w-12 rounded-lg border px-3 text-sm font-medium ${on ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400' : 'border-gray-300 text-gray-500 dark:border-gray-700 dark:text-gray-400'}`}
                >
                  {label}
                </button>
              );
            })}
          </div>
          {(form.errors.days || form.errors['days.0']) && <p className="mt-1.5 text-xs text-error-500">{form.errors.days ?? form.errors['days.0']}</p>}
        </fieldset>

        <div className="grid gap-4 sm:grid-cols-2">
          <FormField label="Opens at" required error={form.errors.start}>
            {(id) => <TextInput id={id} type="time" value={form.data.start} error={form.errors.start} onChange={(event) => form.setData('start', event.target.value)} />}
          </FormField>
          <FormField label="Closes at" required error={form.errors.end} hint={overnight ? 'Closes after midnight, the next day.' : null}>
            {(id) => <TextInput id={id} type="time" value={form.data.end} error={form.errors.end} onChange={(event) => form.setData('end', event.target.value)} />}
          </FormField>
        </div>

        <FormField label="Message" required error={form.errors.message} hint={`${form.data.message.length}/500`}>
          {(id) => <Textarea id={id} rows={3} maxLength={500} value={form.data.message} onChange={(event) => form.setData('message', event.target.value)} />}
        </FormField>

        <p className="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-400">
          {form.data.days.length
            ? `Open ${describeDays(form.data.days)}, ${formatTime(form.data.start)} – ${formatTime(form.data.end)}${overnight ? ' (next day)' : ''}. Messages at any other time get this reply, once every 12 hours per chat.`
            : 'Choose your working days.'}{' '}
          Times are in {timezone.replace('_', ' ')} time. Vacation mode's away message takes priority.
        </p>

        <div className="flex flex-wrap items-center justify-end gap-2">
          {reply && (
            <Button variant="ghost" className="text-error-600" onClick={() => onRemove(reply)}>
              Delete
            </Button>
          )}
          <Button loading={form.processing} onClick={() => save(true)}>
            {reply ? 'Save changes' : 'Save and turn on'}
          </Button>
        </div>
        {reply && !reply.is_active && <p className="text-right text-xs text-gray-500">Saved, but switched off: customers won't get this message until you turn it on.</p>}
      </div>
    </Card>
  );
}

export default function AutoReplies({ replies, timezone = 'Asia/Manila' }) {
  const [editing, setEditing] = useState(null);
  const [removing, setRemoving] = useState(null);
  const [greetingSwitching, setGreetingSwitching] = useState(false);
  const greeting = replies.find((reply) => reply.type === 'greeting');
  const offWork = replies.find((reply) => reply.type === 'off_work');
  const others = replies.filter((reply) => !['greeting', 'off_work'].includes(reply.type));
  const hasAway = others.some((reply) => reply.type === 'offline');
  const otherTypes = hasAway ? ['keyword'] : ['keyword', 'offline'];

  return (
    <SellerLayout>
      <Head title="Automated replies" />
      <PageHeader title="Automated replies" description="Answer customers instantly, even when you are busy or closed." />

      <Card
        title="Greeting"
        description={typeInfo.greeting[1]}
        bodyClassName=""
        pinActions
        actions={
          <Switch
            label="Greeting"
            checked={Boolean(greeting?.is_active)}
            busy={greetingSwitching}
            onChange={(on) => {
              if (!greeting) {
                // Nothing to switch on yet: write the greeting first.
                if (on) {
                  setEditing({ reply: { type: 'greeting' }, types: ['greeting'] });
                }

                return;
              }

              router.patch(`/seller/auto-replies/${greeting.id}/active`, { is_active: on }, { preserveScroll: true, onStart: () => setGreetingSwitching(true), onFinish: () => setGreetingSwitching(false) });
            }}
          />
        }
      >
        {greeting ? (
          <ul>
            <ReplyRow reply={greeting} hideStatus onEdit={(reply) => setEditing({ reply, types: ['greeting'] })} onRemove={setRemoving} />
          </ul>
        ) : (
          <EmptyState title="No greeting yet" description="Start with a greeting so customers know you got their message." action={<Button onClick={() => setEditing({ reply: { type: 'greeting' }, types: ['greeting'] })}>Add greeting</Button>} />
        )}
      </Card>

      <OffWorkCard key={offWork?.id ?? 'new'} reply={offWork} timezone={timezone} onRemove={setRemoving} />

      <Card
        title="Other automated replies"
        description="An away message for vacation mode, and replies to keywords such as “delivery” or “price”."
        className="mt-6"
        bodyClassName=""
        actions={<Button size="sm" onClick={() => setEditing({ reply: { type: otherTypes[0] }, types: otherTypes })}>Add reply</Button>}
      >
        {others.length ? (
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {others.map((reply) => (
              <ReplyRow key={reply.id} reply={reply} onEdit={(item) => setEditing({ reply: item, types: [item.type] })} onRemove={setRemoving} />
            ))}
          </ul>
        ) : (
          <EmptyState title="No other replies" description="Add keyword replies to answer common questions automatically." />
        )}
      </Card>

      {editing && <ReplyForm reply={editing.reply} types={editing.types} onClose={() => setEditing(null)} />}
      <ConfirmDialog open={Boolean(removing)} onClose={() => setRemoving(null)} onConfirm={() => router.delete(`/seller/auto-replies/${removing.id}`, { preserveScroll: true, onFinish: () => setRemoving(null) })} title="Delete this reply?" confirmLabel="Delete" />
    </SellerLayout>
  );
}
