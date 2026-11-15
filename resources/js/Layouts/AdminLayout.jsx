import { adminNav } from '../lib/navigation';
import PanelLayout from './PanelLayout';

export default function AdminLayout({ children }) {
  return (
    <PanelLayout nav={adminNav} panelLabel="Admin Panel" homeHref="/admin">
      {children}
    </PanelLayout>
  );
}
