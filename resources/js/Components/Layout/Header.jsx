export default function Header({ onToggleSidebar }) {
    return (
        <header className="border-b border-black/5 bg-white px-6 py-4">
            <div className="flex items-center justify-between gap-4">
                <button
                    type="button"
                    onClick={onToggleSidebar}
                    className="rounded-xl p-2 hover:bg-brand-cream"
                    aria-label="Toggle sidebar"
                >
                    <svg className="h-6 w-6 text-brand-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div className="mx-4 max-w-xl flex-1">
                    <div className="relative">
                        <input
                            type="text"
                            placeholder="Search products, sales, customers, or ask Pilot..."
                            className="w-full rounded-xl border border-black/10 bg-brand-cream/50 py-2.5 pr-4 pl-10 text-sm focus:border-brand focus:ring-2 focus:ring-brand/20 focus:outline-none"
                        />
                        <svg
                            className="absolute top-3 left-3 h-4 w-4 text-black/40"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <div className="flex items-center gap-4">
                    <button type="button" className="relative rounded-xl p-2 hover:bg-brand-cream">
                        <svg className="h-6 w-6 text-brand-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span className="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-red-500" />
                    </button>

                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">
                            YM
                        </div>
                        <div className="hidden md:block">
                            <p className="text-sm font-semibold text-brand-dark">Ye Myint Swe</p>
                            <p className="text-xs text-black/50">Shop owner</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>
    );
}
