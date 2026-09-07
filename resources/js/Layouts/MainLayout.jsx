import { useState } from 'react';
import Header from '../Components/Layout/Header';
import Sidebar from '../Components/Layout/Sidebar';

export default function MainLayout({ children }) {
    const [sidebarOpen, setSidebarOpen] = useState(true);

    return (
        <div className="min-h-screen bg-brand-cream font-sans text-brand-dark antialiased">
            <div className="flex min-h-screen">
                <Sidebar open={sidebarOpen} />

                <div className="flex min-w-0 flex-1 flex-col">
                    <Header onToggleSidebar={() => setSidebarOpen((prev) => !prev)} />

                    <main className="flex-1 overflow-y-auto p-6">{children}</main>
                </div>
            </div>
        </div>
    );
}
