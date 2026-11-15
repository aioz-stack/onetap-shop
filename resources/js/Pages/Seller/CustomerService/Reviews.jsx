import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { FilterBar, FilterSelect } from '../../../Components/admin/Filters';
import Stars from '../../../Components/store/Stars';
import { Button, Card, EmptyState, PageHeader, Pagination, StatCard, Textarea } from '../../../Components/ui';
import { icon } from '../../../Components/AppIcon';
import SellerLayout from '../../../Layouts/SellerLayout';
import { formatDate } from '../../../lib/format';

function ReplyBox({ review }) {
  const [open, setOpen] = useState(false);
  const form = useForm({ reply: review.seller_reply ?? '' });

  if (!open) {
    return <Button size="sm" variant="ghost" onClick={() => setOpen(true)}>{review.seller_reply ? 'Edit reply' : 'Reply'}</Button>;
  }

  return (
    <form className="mt-3 space-y-2" onSubmit={(event) => { event.preventDefault(); form.post(`/seller/reviews/${review.id}/reply`, { preserveScroll: true, onSuccess: () => setOpen(false) }); }}>
      <Textarea aria-label="Your reply" rows={3} maxLength={1000} value={form.data.reply} error={form.errors.reply} onChange={(event) => form.setData('reply', event.target.value)} placeholder="Thank the customer or address their concern. Replies are public." />
      {form.errors.reply && <p className="text-xs text-error-500">{form.errors.reply}</p>}
      <div className="flex gap-2">
        <Button type="submit" size="sm" loading={form.processing}>Post reply</Button>
        <Button size="sm" variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
      </div>
    </form>
  );
}

export default function Reviews({ reviews, filter, summary }) {
  return (
    <SellerLayout>
      <Head title="Reviews" />
      <PageHeader title="Reviews" description="What customers say about your products. Replies appear on the product page." />
      <div className="mb-6 grid gap-4 sm:grid-cols-3">
        <StatCard label="Shop rating" value={`${summary.average.toFixed(2)} ★`} icon={icon('star')} tone="warning" />
        <StatCard label="Reviews" value={summary.total} icon={icon('chat')} tone="brand" />
        <StatCard label="Waiting for a reply" value={summary.unanswered} icon={icon('bell')} tone={summary.unanswered ? 'orange' : 'gray'} />
      </div>
      <Card bodyClassName="">
        <FilterBar>
          <FilterSelect param="filter" value={filter} placeholder="All reviews" options={[{ value: 'unanswered', label: 'Not replied yet' }, ...[5, 4, 3, 2, 1].map((star) => ({ value: String(star), label: `${star} star${star > 1 ? 's' : ''}` }))]} />
        </FilterBar>
        {reviews.data.length ? (
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {reviews.data.map((review) => (
              <li key={review.id} className="px-5 py-4 text-sm">
                <div className="flex flex-wrap items-center gap-2">
                  <Stars value={review.rating} size="h-3.5 w-3.5" />
                  <span className="font-medium text-gray-800 dark:text-white/90">{review.customer}</span>
                  <span className="text-xs text-gray-400">{formatDate(review.created_at)}</span>
                  {review.product && <Link href={`/products/${review.product.slug}`} className="text-xs text-brand-600 hover:underline">{review.product.name}</Link>}
                </div>
                {review.comment && <p className="mt-1.5 text-gray-700 dark:text-gray-300">{review.comment}</p>}
                {review.seller_reply && <p className="mt-2 rounded-lg bg-gray-50 p-3 text-gray-600 dark:bg-white/5 dark:text-gray-400"><span className="font-medium">Your reply:</span> {review.seller_reply}</p>}
                <ReplyBox review={review} />
              </li>
            ))}
          </ul>
        ) : (
          <EmptyState title="No reviews yet" description="Customers can review items after they receive their order." />
        )}
        <Pagination paginator={reviews} />
      </Card>
    </SellerLayout>
  );
}
