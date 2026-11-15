import ActionButton from '../admin/ActionButton';
import { Button } from '../ui';
import { formatMoney } from '../../lib/format';

/**
 * Buttons a customer can use on an order, based on what the server says is allowed (`order.can`).
 */
export default function OrderActions({ order, showDetails = true }) {
  const base = `/account/orders/${order.id}`;

  return (
    <>
      {order.can.confirm_received && (
        <ActionButton url={`${base}/confirm`} label="Order received" variant="primary" message="Confirm you received everything in good condition. This completes the order and pays the seller." confirmLabel="Yes, I received it" />
      )}
      {order.can.request_refund && (
        <ActionButton
          url={`${base}/refund`}
          label="Return / refund"
          message={`Tell us what went wrong. You can ask for up to ${formatMoney(order.items_subtotal)}.`}
          confirmLabel="Send request"
          fields={[
            { name: 'reason', label: 'Reason', type: 'text', required: true, placeholder: 'e.g. Item arrived damaged' },
            { name: 'details', label: 'Details', type: 'textarea' },
          ]}
        />
      )}
      {order.can.cancel && (
        <ActionButton
          url={`${base}/cancel`}
          label="Cancel order"
          tone="danger"
          message="The shop has not started preparing this order yet, so you can still cancel it."
          confirmLabel="Cancel order"
          fields={[{ name: 'reason', label: 'Reason', type: 'text', required: true, placeholder: 'e.g. Ordered by mistake' }]}
        />
      )}
      {order.refund && <span className="self-center text-xs text-gray-500">Return request {order.refund.status}</span>}
      {showDetails && (
        <Button size="sm" variant="ghost" href={base}>
          View details
        </Button>
      )}
    </>
  );
}
