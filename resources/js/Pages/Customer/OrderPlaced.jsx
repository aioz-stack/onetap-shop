import { Head, Link } from '@inertiajs/react';
import { AddressText } from '../../Components/store/AddressForm';
import { Button, Card } from '../../Components/ui';
import CustomerLayout from '../../Layouts/CustomerLayout';
import { formatMoney } from '../../lib/format';

export default function OrderPlaced({ order }) {
  return (
    <CustomerLayout>
      <Head title="Order placed" />
      <div className="mx-auto max-w-xl text-center">
        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-success-50 text-3xl text-success-600 dark:bg-success-500/15">✓</div>
        <h1 className="mt-5 text-2xl font-semibold text-gray-800 dark:text-white/90">Thank you! Your order is placed.</h1>
        <p className="mt-2 text-sm text-gray-500">
          Order <span className="font-medium text-gray-700 dark:text-gray-300">{order.order_number}</span> · Pay <strong>{formatMoney(order.grand_total)}</strong> in cash when it arrives.
        </p>

        <Card className="mt-8 text-left" title={`${order.seller_orders.length} shop order${order.seller_orders.length > 1 ? 's' : ''}`}>
          <ul className="divide-y divide-gray-100 text-sm dark:divide-gray-800">
            {order.seller_orders.map((sellerOrder) => (
              <li key={sellerOrder.id} className="flex items-center justify-between gap-3 py-3 first:pt-0">
                <span>
                  <span className="block font-medium text-gray-800 dark:text-white/90">{sellerOrder.shop}</span>
                  <span className="text-xs text-gray-500">{sellerOrder.reference}</span>
                </span>
                <span className="flex items-center gap-3">
                  {formatMoney(sellerOrder.total)}
                  <Link href={`/account/orders/${sellerOrder.id}`} className="text-brand-600 hover:underline">
                    Track
                  </Link>
                </span>
              </li>
            ))}
          </ul>
          <div className="mt-4 border-t border-gray-100 pt-4 text-sm dark:border-gray-800">
            <p className="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">Delivering to</p>
            <AddressText address={order.address} />
          </div>
        </Card>

        <div className="mt-6 flex justify-center gap-3">
          <Button variant="secondary" href="/search">
            Continue shopping
          </Button>
          <Button href="/account/orders">View my orders</Button>
        </div>
      </div>
    </CustomerLayout>
  );
}
