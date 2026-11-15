import { useEffect, useRef } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, EmptyState, PageHeader } from '../../../Components/ui';
import SellerLayout from '../../../Layouts/SellerLayout';
import { formatDateTime } from '../../../lib/format';

function Thread({ active }) {
  const form = useForm({ body: '' });
  const bottom = useRef(null);

  useEffect(() => {
    const timer = setInterval(() => router.reload({ only: ['active', 'conversations', 'sellerCounts'] }), 5000);

    return () => clearInterval(timer);
  }, [active.id]);

  useEffect(() => {
    bottom.current?.scrollIntoView({ block: 'end' });
  }, [active.messages.length]);

  return (
    <div className="flex h-[65vh] min-h-[420px] flex-col">
      <header className="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
        <span className="font-semibold text-gray-800 dark:text-white/90">{active.customer}</span>
        <Link href="/seller/chat" className="text-sm text-gray-500 lg:hidden">All chats</Link>
      </header>
      <ol className="flex-1 space-y-3 overflow-y-auto px-4 py-4">
        {active.messages.map((message) => (
          <li key={message.id} className={`flex ${message.mine ? 'justify-end' : 'justify-start'}`}>
            <div className={`max-w-[80%] rounded-2xl px-4 py-2 text-sm ${message.mine ? 'rounded-br-sm bg-brand-500 text-white' : 'rounded-bl-sm bg-gray-100 text-gray-800 dark:bg-white/10 dark:text-white/90'}`}>
              <p className="whitespace-pre-line break-words">{message.body}</p>
              <p className={`mt-1 text-[10px] ${message.mine ? 'text-brand-100' : 'text-gray-500'}`}>{message.auto && 'Auto-reply · '}{formatDateTime(message.created_at)}</p>
            </div>
          </li>
        ))}
        <li ref={bottom} />
      </ol>
      <form
        className="flex gap-2 border-t border-gray-100 p-3 dark:border-gray-800"
        onSubmit={(event) => {
          event.preventDefault();
          if (form.data.body.trim()) {
            form.post(`/seller/chat/${active.id}`, { preserveScroll: true, onSuccess: () => form.reset('body') });
          }
        }}
      >
        <input aria-label="Reply" placeholder="Write a reply" maxLength={2000} value={form.data.body} onChange={(event) => form.setData('body', event.target.value)} className="h-11 flex-1 rounded-full border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90" />
        <Button type="submit" loading={form.processing} disabled={!form.data.body.trim()}>Send</Button>
      </form>
    </div>
  );
}

export default function Inbox({ conversations, active }) {
  return (
    <SellerLayout>
      <Head title="Chat" />
      <PageHeader title="Customer chat" description="Questions from customers about your products and orders." />
      <div className="grid overflow-hidden rounded-2xl border border-gray-200 bg-white lg:grid-cols-[300px_1fr] dark:border-gray-800 dark:bg-white/[0.03]">
        <ul className={`divide-y divide-gray-100 lg:border-r dark:divide-gray-800 dark:border-gray-800 ${active ? 'hidden lg:block' : ''}`}>
          {conversations.length ? (
            conversations.map((conversation) => (
              <li key={conversation.id}>
                <Link href={`/seller/chat/${conversation.id}`} className={`block px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/[0.02] ${active?.id === conversation.id ? 'bg-brand-50/60 dark:bg-brand-500/10' : ''}`}>
                  <span className="flex items-center justify-between gap-2">
                    <span className={`truncate text-sm ${conversation.unread ? 'font-semibold text-gray-800 dark:text-white/90' : 'text-gray-700 dark:text-gray-300'}`}>{conversation.customer}</span>
                    {conversation.unread && <span className="h-2 w-2 shrink-0 rounded-full bg-orange-500" aria-label="Unread" />}
                  </span>
                  <span className="block truncate text-xs text-gray-500">{conversation.last_message}</span>
                  <span className="text-[10px] text-gray-400">{formatDateTime(conversation.last_message_at)}</span>
                </Link>
              </li>
            ))
          ) : (
            <li><EmptyState title="No conversations yet" description="Customers can message you from your product and shop pages." /></li>
          )}
        </ul>
        <div className={`min-w-0 ${active ? '' : 'hidden lg:block'}`}>{active ? <Thread active={active} /> : <EmptyState title="Select a conversation" />}</div>
      </div>
    </SellerLayout>
  );
}
