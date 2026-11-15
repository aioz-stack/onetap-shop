import { sellerNav } from '../lib/navigation';
import PanelLayout from './PanelLayout';

export default function SellerLayout({ children }) {
  return (
    <PanelLayout nav={sellerNav} panelLabel="Seller Center" homeHref="/seller">
      {children}
    </PanelLayout>
  );
}
