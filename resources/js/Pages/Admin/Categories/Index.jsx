import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Badge, Button, Card, Checkbox, ConfirmDialog, EmptyState, FormField, Modal, PageHeader, Select, TextInput } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';

function CategoryForm({ category, parents, onClose }) {
  const editing = Boolean(category?.id);
  const { data, setData, post, put, processing, errors } = useForm({
    name: category?.name ?? '',
    parent_id: category?.parent_id ?? '',
    sort_order: category?.sort_order ?? 0,
    is_active: category?.is_active ?? true,
  });

  const submit = (event) => {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: onClose };
    editing ? put(`/admin/categories/${category.id}`, options) : post('/admin/categories', options);
  };

  return (
    <Modal
      open
      onClose={onClose}
      title={editing ? `Edit ${category.name}` : 'Add category'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form="category-form" loading={processing}>
            Save
          </Button>
        </>
      }
    >
      <form id="category-form" onSubmit={submit} className="space-y-4" noValidate>
        <FormField label="Name" required error={errors.name}>
          {(id) => <TextInput id={id} autoFocus value={data.name} error={errors.name} onChange={(event) => setData('name', event.target.value)} />}
        </FormField>
        <FormField label="Parent category" error={errors.parent_id} hint="Leave empty for a top-level category.">
          {(id) => (
            <Select
              id={id}
              value={data.parent_id ?? ''}
              placeholder="None (top level)"
              options={parents.filter((parent) => parent.id !== category?.id).map((parent) => ({ value: parent.id, label: parent.name }))}
              onChange={(event) => setData('parent_id', event.target.value || null)}
            />
          )}
        </FormField>
        <FormField label="Sort order" error={errors.sort_order}>
          {(id) => <TextInput id={id} type="number" min="0" value={data.sort_order} onChange={(event) => setData('sort_order', event.target.value)} />}
        </FormField>
        <Checkbox label="Visible on the storefront" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} />
      </form>
    </Modal>
  );
}

function Row({ category, child, onEdit, onDelete }) {
  return (
    <li className={`flex items-center justify-between gap-3 px-5 py-3 ${child ? 'pl-12' : ''}`}>
      <div className="min-w-0">
        <p className={`truncate text-sm ${child ? 'text-gray-700 dark:text-gray-300' : 'font-semibold text-gray-800 dark:text-white/90'}`}>
          {child && <span className="mr-2 text-gray-300">└</span>}
          {category.name}
        </p>
        <p className="text-xs text-gray-500">
          {category.products_count} products · order {category.sort_order}
        </p>
      </div>
      <div className="flex shrink-0 items-center gap-2">
        {!category.is_active && <Badge>Hidden</Badge>}
        <Button size="sm" variant="ghost" onClick={() => onEdit(category)}>
          Edit
        </Button>
        <Button size="sm" variant="ghost" className="text-error-600" onClick={() => onDelete(category)}>
          Delete
        </Button>
      </div>
    </li>
  );
}

export default function Index({ categories, parents }) {
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const [processing, setProcessing] = useState(false);

  const destroy = () => {
    router.delete(`/admin/categories/${deleting.id}`, {
      preserveScroll: true,
      onStart: () => setProcessing(true),
      onFinish: () => {
        setProcessing(false);
        setDeleting(null);
      },
    });
  };

  return (
    <AdminLayout>
      <Head title="Categories" />
      <PageHeader
        title="Product categories"
        description="Two levels: top-level categories and their sub-categories."
        breadcrumbs={[{ label: 'Admin', href: '/admin' }, { label: 'Categories' }]}
        actions={<Button onClick={() => setEditing({})}>Add category</Button>}
      />

      <Card bodyClassName="">
        {categories.length ? (
          <ul className="divide-y divide-gray-100 dark:divide-gray-800">
            {categories.map((parent) => (
              <li key={parent.id}>
                <ul className="divide-y divide-gray-50 dark:divide-gray-800/60">
                  <Row category={parent} onEdit={setEditing} onDelete={setDeleting} />
                  {parent.children.map((child) => (
                    <Row key={child.id} category={child} child onEdit={setEditing} onDelete={setDeleting} />
                  ))}
                </ul>
              </li>
            ))}
          </ul>
        ) : (
          <EmptyState title="No categories" action={<Button onClick={() => setEditing({})}>Add the first category</Button>} />
        )}
      </Card>

      {editing && <CategoryForm category={editing} parents={parents} onClose={() => setEditing(null)} />}

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={destroy}
        processing={processing}
        title={`Delete ${deleting?.name ?? ''}?`}
        message="Only empty categories can be deleted. To hide a category that has products, edit it and untick “Visible on the storefront”."
        confirmLabel="Delete"
      />
    </AdminLayout>
  );
}
