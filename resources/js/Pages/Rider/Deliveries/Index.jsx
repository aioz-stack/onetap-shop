import { Head } from '@inertiajs/react';
import JobCard from '../../../Components/rider/JobCard';
import { Card, EmptyState, Pagination, SearchInput, Tabs } from '../../../Components/ui';
import RiderLayout from '../../../Layouts/RiderLayout';
import { withQuery } from '../../../lib/query';

const EMPTY = {
  new: 'New jobs from your dispatcher appear here. Accept them to start the pickup.',
  pickup: 'Accepted jobs you still need to collect from the shop.',
  delivery: 'Parcels you are carrying to customers.',
  issues: 'Failed attempts waiting for a redelivery or a return to the shop.',
  history: 'Finished, declined and reassigned jobs.',
};

export default function Index({ jobs, tabs, filters, counts }) {
  return (
    <RiderLayout title="Deliveries">
      <Head title="Deliveries" />

      <div className="-mx-4 mb-4 bg-white px-1 dark:bg-gray-900">
        <Tabs active={filters.tab} tabs={tabs.map((tab) => ({ ...tab, count: counts[tab.key], href: withQuery({ tab: tab.key, search: null }, '/rider/deliveries') }))} />
      </div>

      <div className="mb-4">
        <SearchInput value={filters.search} placeholder="Search tracking number" />
      </div>

      {jobs.data.length ? (
        <div className="space-y-3">
          {jobs.data.map((job) => <JobCard key={job.assignment_id} job={job} />)}
          <Pagination paginator={jobs} />
        </div>
      ) : (
        <Card>
          <EmptyState title={filters.search ? 'No matching jobs' : 'Nothing here'} description={EMPTY[filters.tab]} />
        </Card>
      )}
    </RiderLayout>
  );
}
