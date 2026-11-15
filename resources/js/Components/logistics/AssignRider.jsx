import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button, EmptyState, FormField, Modal, Select } from '../ui';

/**
 * Button + dialog to give a shipment to one of the partner's active riders.
 */
export default function AssignRider({ shipment, riders, label = 'Assign rider', variant = 'primary' }) {
  const [open, setOpen] = useState(false);
  const form = useForm({ rider_id: shipment.rider_id ?? riders[0]?.value ?? '' });
  const close = () => {
    setOpen(false);
    form.clearErrors();
  };

  return (
    <>
      <Button size="sm" variant={variant} onClick={() => setOpen(true)}>
        {shipment.rider ? 'Reassign' : label}
      </Button>
      <Modal
        open={open}
        onClose={close}
        title={`${shipment.rider ? 'Reassign' : 'Assign'} ${shipment.tracking_number}`}
        footer={
          riders.length > 0 && (
            <>
              <Button variant="secondary" onClick={close}>Cancel</Button>
              <Button loading={form.processing} onClick={() => form.post(`/logistics/shipments/${shipment.id}/assign`, { preserveScroll: true, onSuccess: close })}>
                Assign
              </Button>
            </>
          )
        }
      >
        {riders.length ? (
          <div className="space-y-3">
            <p className="text-sm text-gray-600 dark:text-gray-400">
              {shipment.pickup} → {shipment.dropoff}
            </p>
            <FormField label="Rider" required error={form.errors.rider_id} hint="Online riders with the fewest open jobs are listed first.">
              {(id) => <Select id={id} value={form.data.rider_id} options={riders} onChange={(event) => form.setData('rider_id', event.target.value)} />}
            </FormField>
            {shipment.rider && <p className="text-xs text-warning-600">{shipment.rider}'s job will be cancelled.</p>}
          </div>
        ) : (
          <EmptyState title="No active riders" description="Add or approve a rider before dispatching." />
        )}
      </Modal>
    </>
  );
}
