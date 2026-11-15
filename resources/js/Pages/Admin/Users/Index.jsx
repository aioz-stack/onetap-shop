import { Head, Link } from '@inertiajs/react';
import { FilterBar, FilterSelect } from '../../../Components/admin/Filters';
import StatusTabs from '../../../Components/admin/StatusTabs';
import { Avatar } from '../../../Components/UserMenu';
import { Badge, Button, Card, DataTable, PageHeader, Pagination, SearchInput, StatusBadge } from '../../../Components/ui';
import AdminLayout from '../../../Layouts/AdminLayout';
import { formatDate, headline } from '../../../lib/format';

export default function Index({ users, filters, roleCounts, roles, statuses }) {
  const tabs = [{ key: null, label: 'All' }, ...roles.map((role) => ({ key: role.value, label: `${role.label}s` }))];

  return (
    <AdminLayout>
      <Head title="Users" />
      <PageHeader
        title="Users"
        description="Every account on the marketplace."
        breadcrumbs={[{ label: 'Admin', href: '/admin' }, { label: 'Users' }]}
        actions={<Button href="/admin/users/create">Add account</Button>}
      />

      <Card bodyClassName="">
        <StatusTabs tabs={tabs} param="role" active={filters.role} counts={roleCounts} />
        <FilterBar>
          <SearchInput value={filters.search} placeholder="Search name, username, email, phone" className="w-full sm:w-80" />
          <FilterSelect param="status" value={filters.status} options={statuses} placeholder="Any status" />
        </FilterBar>
        <DataTable
          rows={users.data}
          columns={[
            {
              key: 'name',
              label: 'User',
              render: (user) => (
                <Link href={`/admin/users/${user.id}`} className="flex items-center gap-3">
                  <Avatar user={user} size="h-9 w-9" />
                  <span className="min-w-0">
                    <span className="block truncate font-medium text-gray-800 hover:text-brand-600 dark:text-white/90">{user.name}</span>
                    <span className="block truncate text-xs text-gray-500">@{user.username} · {user.email}</span>
                  </span>
                </Link>
              ),
            },
            {
              key: 'roles',
              label: 'Roles',
              render: (user) => (
                <div className="flex flex-wrap gap-1">
                  {user.roles.map((role) => (
                    <Badge key={role} tone={role === 'admin' ? 'brand' : 'gray'}>
                      {headline(role)}
                    </Badge>
                  ))}
                </div>
              ),
            },
            { key: 'status', label: 'Status', render: (user) => <StatusBadge status={user.status} /> },
            { key: 'last_login_at', label: 'Last login', className: 'whitespace-nowrap text-gray-500', render: (user) => formatDate(user.last_login_at) },
            { key: 'created_at', label: 'Joined', className: 'whitespace-nowrap text-gray-500', render: (user) => formatDate(user.created_at) },
          ]}
        />
        <Pagination paginator={users} />
      </Card>
    </AdminLayout>
  );
}
