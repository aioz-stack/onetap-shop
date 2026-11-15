import { Head } from '@inertiajs/react';
import VoucherManager from '../../../Components/marketing/VoucherManager';
import AdminLayout from '../../../Layouts/AdminLayout';

export default function Vouchers({ vouchers, types }) {
  return (
    <AdminLayout>
      <Head title="Platform vouchers" />
      <VoucherManager
        vouchers={vouchers}
        types={types}
        baseUrl="/admin/vouchers"
        title="Platform vouchers"
        description="Onetap-wide codes customers can use in any shop, once per order."
        emptyDescription="Create a code for a campaign, such as free delivery for first orders."
        fundingNote="Onetap funds the discount: sellers are paid as if there were no voucher, and the cost is booked in the ledger and subtracted from marketplace revenue."
      />
    </AdminLayout>
  );
}
