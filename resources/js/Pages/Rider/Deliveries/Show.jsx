import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppIcon from '../../../Components/AppIcon';
import { Button, Card, Checkbox, FormField, Modal, Select, StatusBadge, Textarea, TextInput } from '../../../Components/ui';
import RiderLayout from '../../../Layouts/RiderLayout';
import { formatDateTime, formatMoney, headline } from '../../../lib/format';
import { FLOW, NOTE_REQUIRED, STEP_LABELS, addressLine, currentPosition, mapsUrl } from '../../../lib/rider';

const RELATIONS = ['Self', 'Family member', 'Housemate', 'Guard / receptionist', 'Neighbor', 'Other'].map((value) => ({ value, label: value }));

function Progress({ status }) {
  const index = FLOW.indexOf(status);

  return (
    <ol className="flex items-center gap-1" aria-label="Delivery progress">
      {FLOW.map((step, position) => (
        <li key={step} title={headline(step)} className={`h-1.5 flex-1 rounded-full ${index >= position ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-800'}`} />
      ))}
    </ol>
  );
}

function Place({ title, icon, name, phone, address, highlight }) {
  return (
    <Card bodyClassName="p-4" className={highlight ? 'ring-2 ring-brand-500/40' : ''}>
      <div className="flex items-start gap-3">
        <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
          <AppIcon name={icon} className="h-5 w-5" />
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{title}</p>
          <p className="font-medium text-gray-800 dark:text-white/90">{name}</p>
          <p className="text-sm text-gray-600 dark:text-gray-400">{addressLine(address)}</p>
          <div className="mt-3 flex flex-wrap gap-2">
            {phone && (
              <a href={`tel:${phone}`} className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-gray-100 px-3 text-sm font-medium text-gray-700 dark:bg-white/5 dark:text-gray-300">
                Call {phone}
              </a>
            )}
            <a href={mapsUrl(address)} target="_blank" rel="noreferrer" className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-gray-100 px-3 text-sm font-medium text-gray-700 dark:bg-white/5 dark:text-gray-300">
              <AppIcon name="map" className="h-4 w-4" /> Directions
            </a>
          </div>
        </div>
      </div>
    </Card>
  );
}

function NoteDialog({ job, step, onClose }) {
  const form = useForm({ status: step, note: '', lat: '', lng: '' });
  const required = NOTE_REQUIRED.includes(step);
  const title = step === 'declined' ? 'Decline this job?' : STEP_LABELS[step];
  const placeholders = {
    declined: 'e.g. motorcycle broke down, too far from my area',
    failed_delivery: 'e.g. wrong address, refused the parcel',
    customer_unavailable: 'e.g. nobody home, phone unanswered after 3 calls',
    returned_to_seller: 'e.g. customer refused after two attempts',
    rescheduled: 'e.g. customer asked for tomorrow afternoon',
  };
  const submit = async () => {
    const position = await currentPosition(5000);
    form.transform((data) => ({ ...data, ...position }));
    form.post(`/rider/deliveries/${job.id}/status`, { preserveScroll: true, onSuccess: onClose });
  };

  return (
    <Modal
      open
      onClose={onClose}
      title={title}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button variant={['declined', 'failed_delivery', 'customer_unavailable', 'returned_to_seller'].includes(step) ? 'danger' : 'primary'} loading={form.processing} disabled={required && !form.data.note.trim()} onClick={submit}>
            Confirm
          </Button>
        </>
      }
    >
      <FormField label={required ? 'What happened?' : 'Note (optional)'} required={required} error={form.errors.note || form.errors.status}>
        {(id) => <Textarea id={id} rows={3} value={form.data.note} placeholder={placeholders[step]} onChange={(event) => form.setData('note', event.target.value)} />}
      </FormField>
      {step === 'returned_to_seller' && <p className="mt-3 text-sm text-gray-600 dark:text-gray-400">Only confirm once you have handed the parcel back to the shop.</p>}
    </Modal>
  );
}

function DeliverForm({ job, onClose }) {
  const hasCod = Number(job.cod_amount) > 0;
  const form = useForm({ photo: null, recipient_name: job.recipient ?? '', recipient_relation: 'Self', amount_collected: hasCod ? job.cod_amount : '', note: '', lat: '', lng: '' });
  const [preview, setPreview] = useState(null);
  const [shareLocation, setShareLocation] = useState(true);
  const mismatch = hasCod && form.data.amount_collected !== '' && Number(form.data.amount_collected) !== Number(job.cod_amount);

  useEffect(() => () => preview && URL.revokeObjectURL(preview), [preview]);

  const choosePhoto = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.setData('photo', file);
    setPreview(file ? URL.createObjectURL(file) : null);
  };
  const submit = async (event) => {
    event.preventDefault();
    const position = shareLocation ? await currentPosition() : {};
    form.transform((data) => ({ ...data, ...position }));
    form.post(`/rider/deliveries/${job.id}/deliver`, { forceFormData: true, preserveScroll: true, onSuccess: onClose });
  };

  return (
    <Card title="Proof of delivery" description="Hand over the parcel, collect the cash, then take a photo.">
      <form onSubmit={submit} className="space-y-4">
        <FormField label="Photo of the parcel with the recipient" required error={form.errors.photo}>
          {(id) => (
            <label htmlFor={id} className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-300 p-4 text-center text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400">
              {preview ? <img src={preview} alt="Proof preview" className="max-h-56 rounded-lg object-contain" /> : <AppIcon name="camera" className="h-8 w-8" />}
              <span className="font-medium text-brand-600 dark:text-brand-400">{preview ? 'Retake photo' : 'Take photo'}</span>
              <input id={id} type="file" accept="image/*" capture="environment" className="sr-only" onChange={choosePhoto} />
            </label>
          )}
        </FormField>
        <div className="grid gap-4 sm:grid-cols-2">
          <FormField label="Received by" required error={form.errors.recipient_name}>
            {(id) => <TextInput id={id} value={form.data.recipient_name} error={form.errors.recipient_name} onChange={(event) => form.setData('recipient_name', event.target.value)} />}
          </FormField>
          <FormField label="Relation to customer" error={form.errors.recipient_relation}>
            {(id) => <Select id={id} value={form.data.recipient_relation} options={RELATIONS} onChange={(event) => form.setData('recipient_relation', event.target.value)} />}
          </FormField>
        </div>
        {hasCod && (
          <FormField label="Cash collected (₱)" required error={form.errors.amount_collected} hint={mismatch ? `This differs from the ${formatMoney(job.cod_amount)} due and will be flagged as a discrepancy.` : `Amount due: ${formatMoney(job.cod_amount)}`}>
            {(id) => <TextInput id={id} type="number" inputMode="decimal" min="0" step="0.01" value={form.data.amount_collected} error={form.errors.amount_collected || mismatch} onChange={(event) => form.setData('amount_collected', event.target.value)} />}
          </FormField>
        )}
        <FormField label="Note (optional)" error={form.errors.note}>
          {(id) => <TextInput id={id} value={form.data.note} placeholder="e.g. left with the guard at the lobby" onChange={(event) => form.setData('note', event.target.value)} />}
        </FormField>
        <Checkbox label="Attach my GPS location (your browser will ask first)" checked={shareLocation} onChange={(event) => setShareLocation(event.target.checked)} />
        {form.errors.status && <p className="text-sm text-error-500">{form.errors.status}</p>}
        <div className="grid grid-cols-2 gap-2">
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" loading={form.processing} disabled={!form.data.photo}>Confirm delivery</Button>
        </div>
      </form>
    </Card>
  );
}

export default function Show({ job, onShift }) {
  const [step, setStep] = useState(null);
  const [delivering, setDelivering] = useState(false);
  const [busy, setBusy] = useState(false);
  const pickupStage = ['assigned', 'accepted', 'arrived_at_pickup'].includes(job.status);
  const [primary, ...others] = job.next_steps;

  const run = async (next) => {
    if (next === 'delivered') {
      setDelivering(true);
      return;
    }

    if (NOTE_REQUIRED.includes(next) || next === 'rescheduled') {
      setStep(next);
      return;
    }

    setBusy(true);
    const position = await currentPosition(5000);
    router.post(`/rider/deliveries/${job.id}/status`, { status: next, ...position }, { preserveScroll: true, onFinish: () => setBusy(false) });
  };

  return (
    <RiderLayout title="Delivery">
      <Head title={job.tracking_number} />

      <Link href="/rider/deliveries" className="mb-3 inline-flex text-sm font-medium text-brand-600 dark:text-brand-400">← All jobs</Link>

      <Card bodyClassName="p-4">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="truncate font-mono text-base font-semibold text-gray-800 dark:text-white/90">{job.tracking_number}</p>
            <p className="text-xs text-gray-500 dark:text-gray-400">{job.shop} · assigned {formatDateTime(job.assigned_at)}</p>
          </div>
          <StatusBadge status={job.status} />
        </div>
        <div className="mt-4"><Progress status={job.status} /></div>
        <div className="mt-4 flex items-end justify-between">
          <span className="text-sm text-gray-500 dark:text-gray-400">{Number(job.cod_amount) > 0 ? 'Cash to collect' : 'Prepaid order'}</span>
          <span className="text-2xl font-semibold text-gray-800 dark:text-white/90">{Number(job.cod_amount) > 0 ? formatMoney(job.cod_amount) : '₱0.00'}</span>
        </div>
        {job.attempts > 0 && <p className="mt-2 text-xs text-warning-600 dark:text-warning-500">{job.attempts} failed attempt{job.attempts > 1 ? 's' : ''} so far</p>}
      </Card>

      {job.open && !onShift && (
        <div className="mt-4 flex items-center justify-between gap-3 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
          <span>Sign in to your shift to update this job.</span>
          <Button size="sm" onClick={() => router.post('/rider/shift/sign-in', {}, { preserveScroll: true })}>Sign in</Button>
        </div>
      )}

      {job.open && onShift && (primary || job.can_decline) && !delivering && (
        <div className="mt-4 space-y-2">
          {primary && <Button size="lg" className="w-full" loading={busy} onClick={() => run(primary)}>{STEP_LABELS[primary]}</Button>}
          {(others.length > 0 || job.can_decline) && (
            <div className="grid grid-cols-2 gap-2">
              {others.map((next) => (
                <Button key={next} variant="secondary" className={['failed_delivery', 'customer_unavailable', 'returned_to_seller'].includes(next) ? '!text-error-600' : ''} disabled={busy} onClick={() => run(next)}>
                  {STEP_LABELS[next]}
                </Button>
              ))}
              {job.can_decline && <Button variant="secondary" className="!text-error-600" disabled={busy} onClick={() => setStep('declined')}>Decline job</Button>}
            </div>
          )}
        </div>
      )}

      {delivering && <div className="mt-4"><DeliverForm job={job} onClose={() => setDelivering(false)} /></div>}

      <div className="mt-4 space-y-3">
        <Place title="Pick up from" icon="store" name={job.pickup_address?.name ?? job.shop} phone={job.pickup_address?.phone} address={job.pickup_address} highlight={job.open && pickupStage} />
        <Place title="Deliver to" icon="pin" name={job.dropoff_address?.recipient_name} phone={job.dropoff_address?.phone} address={job.dropoff_address} highlight={job.open && !pickupStage} />
      </div>

      <Card title="Parcel" className="mt-4" bodyClassName="p-4">
        <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
          {job.items.map((item, index) => (
            <li key={index} className="flex justify-between gap-3 py-2">
              <span className="min-w-0 text-gray-800 dark:text-white/90">{item.name}{item.variation && item.variation !== 'Default' && <span className="block text-xs text-gray-500">{item.variation}</span>}</span>
              <span className="text-gray-600 dark:text-gray-400">×{item.quantity}</span>
            </li>
          ))}
        </ul>
      </Card>

      {job.proof && (
        <Card title="Proof of delivery" className="mt-4" bodyClassName="p-4">
          <img src={job.proof.photo_url} alt="Proof of delivery" className="mb-3 max-h-72 w-full rounded-lg object-cover" />
          <p className="text-sm text-gray-700 dark:text-gray-300">Received by {job.proof.recipient_name}{job.proof.recipient_relation && ` (${job.proof.recipient_relation})`}</p>
          <p className="text-xs text-gray-500">{formatDateTime(job.proof.delivered_at)} · {job.proof.has_location ? 'GPS recorded' : 'No GPS'}</p>
          {job.collection && (
            <p className="mt-2 text-sm text-gray-700 dark:text-gray-300">
              Collected {formatMoney(job.collection.amount_collected)} of {formatMoney(job.collection.amount_due)} <StatusBadge status={job.collection.status} />
            </p>
          )}
        </Card>
      )}

      <Card title="Tracking history" className="mt-4" bodyClassName="p-4">
        <ol className="space-y-3 text-sm">
          {job.history.map((entry) => (
            <li key={entry.id}>
              <span className="font-medium text-gray-800 dark:text-white/90">{headline(entry.to)}</span>
              <span className="block text-xs text-gray-500">{formatDateTime(entry.created_at)}</span>
              {entry.note && <span className="block text-xs text-gray-500">{entry.note}</span>}
            </li>
          ))}
        </ol>
      </Card>

      {step && <NoteDialog job={job} step={step} onClose={() => setStep(null)} />}
    </RiderLayout>
  );
}
