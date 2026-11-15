import { Head } from '@inertiajs/react';
import VoucherManager from '../../../Components/marketing/VoucherManager';
import SellerLayout from '../../../Layouts/SellerLayout';

export default function Vouchers({ vouchers, types }) {
  return (
    <SellerLayout>
      <Head title="Vouchers" />
      <VoucherManager
        vouchers={vouchers}
        types={types}
        baseUrl="/seller/vouchers"
        title="Vouchers"
        description="Discount codes customers enter at checkout for your shop."
        emptyDescription="Create a code to reward regulars or attract new customers."
        fundingNote="Your shop funds the discount: it is taken from your earning on the order, and commission is charged on the discounted item total."
      />
    </SellerLayout>
  );
}
