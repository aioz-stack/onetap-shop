import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ProductImage } from '../../../Components/store/ProductCard';
import Stars, { StarInput } from '../../../Components/store/Stars';
import { Button, Card, EmptyState, FormField, Modal, Textarea } from '../../../Components/ui';
import AccountLayout from '../../../Layouts/AccountLayout';
import { formatDate } from '../../../lib/format';

function ReviewModal({ item, onClose }) {
  const form = useForm({ rating: 5, comment: '' });

  return (
    <Modal
      open
      onClose={onClose}
      title="Rate this item"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button loading={form.processing} onClick={() => form.post(`/account/items/${item.id}/review`, { preserveScroll: true, onSuccess: onClose })}>Submit review</Button>
        </>
      }
    >
      <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">{item.product_name}</p>
      <StarInput value={form.data.rating} onChange={(rating) => form.setData('rating', rating)} />
      {form.errors.rating && <p className="mt-2 text-xs text-error-500">{form.errors.rating}</p>}
      <FormField label="Your review (optional)" className="mt-4">
        {(id) => <Textarea id={id} rows={4} value={form.data.comment} onChange={(event) => form.setData('comment', event.target.value)} />}
      </FormField>
    </Modal>
  );
}

export default function Reviews({ pending, written }) {
  const [reviewing, setReviewing] = useState(null);

  return (
    <AccountLayout title="My reviews">
      <Head title="My reviews" />

      <Card title={`Waiting for your review (${pending.length})`} bodyClassName="">
        {pending.length ? (
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {pending.map((item) => (
              <li key={item.id} className="flex items-center gap-3 px-5 py-3">
                <span className="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-gray-50 dark:bg-white/5"><ProductImage src={item.image_url} name={item.product_name} /></span>
                <span className="min-w-0 flex-1 text-sm">
                  <span className="line-clamp-1 text-gray-800 dark:text-white/90">{item.product_name}</span>
                  <span className="text-xs text-gray-500">{item.order_reference}</span>
                </span>
                <Button size="sm" onClick={() => setReviewing(item)}>Rate</Button>
              </li>
            ))}
          </ul>
        ) : (
          <EmptyState title="All caught up" description="Items from completed orders show up here to review." />
        )}
      </Card>

      <Card title="Reviews you wrote" className="mt-5" bodyClassName="">
        {written.length ? (
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {written.map((review) => (
              <li key={review.id} className="px-5 py-4">
                <div className="flex flex-wrap items-center gap-2 text-sm">
                  <Stars value={review.rating} size="h-3.5 w-3.5" />
                  {review.product && <Link className="font-medium text-gray-800 hover:text-brand-600 dark:text-white/90" href={`/products/${review.product.slug}`}>{review.product.name}</Link>}
                  <span className="text-xs text-gray-400">{formatDate(review.created_at)}</span>
                </div>
                {review.comment && <p className="mt-1.5 text-sm text-gray-600 dark:text-gray-400">{review.comment}</p>}
                {review.seller_reply && <p className="mt-2 rounded-lg bg-gray-50 p-3 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-400"><span className="font-medium">Seller:</span> {review.seller_reply}</p>}
              </li>
            ))}
          </ul>
        ) : (
          <EmptyState title="No reviews yet" />
        )}
      </Card>

      {reviewing && <ReviewModal item={reviewing} onClose={() => setReviewing(null)} />}
    </AccountLayout>
  );
}
