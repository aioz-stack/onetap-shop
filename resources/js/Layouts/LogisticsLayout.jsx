import { logisticsNav } from '../lib/navigation';
import PanelLayout from './PanelLayout';

export default function LogisticsLayout({ children }) {
  return (
    <PanelLayout nav={logisticsNav} panelLabel="Logistics Panel" homeHref="/logistics">
      {children}
    </PanelLayout>
  );
}
