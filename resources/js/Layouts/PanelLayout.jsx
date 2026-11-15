import { useState } from 'react';
import Header from '../Components/Header';
import Overlay from '../Components/Overlay';
import Preloader from '../Components/Preloader';
import Sidebar from '../Components/Sidebar';
import Toast from '../Components/ui/Toast';

/**
 * Shared shell for the sidebar-based back-office panels.
 */
export default function PanelLayout({ nav, panelLabel, homeHref, children }) {
  const [sidebarToggle, setSidebarToggle] = useState(false);

  return (
    <>
      <Preloader />
      <Toast />

      <div className="flex h-screen overflow-hidden">
        <Sidebar sidebarToggle={sidebarToggle} nav={nav} panelLabel={panelLabel} homeHref={homeHref} />

        <div className="relative flex flex-1 flex-col overflow-x-hidden overflow-y-auto">
          <Overlay sidebarToggle={sidebarToggle} onClose={() => setSidebarToggle(false)} />

          <Header
            sidebarToggle={sidebarToggle}
            onToggleSidebar={() => setSidebarToggle((value) => !value)}
            homeHref={homeHref}
            title={panelLabel}
          />

          <main className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">{children}</main>
        </div>
      </div>
    </>
  );
}
