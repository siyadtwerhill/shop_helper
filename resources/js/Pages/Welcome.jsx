import MainLayout from '../Layouts/MainLayout';

const metrics = [
    { label: "Today's revenue", value: '450,000 MMK', change: '+18.2%', positive: true },
    { label: "Today's profit", value: '125,000 MMK', change: '+12.4%', positive: true },
    { label: 'Total orders', value: '82', change: '+15.1%', positive: true },
    { label: 'Low stock items', value: '7', change: '2 vs yesterday', positive: false },
];

const attentionItems = [
    'Coca-Cola, Water low',
    'Milk expires in 2 days',
    '3 unpaid customer orders',
];

const topProducts = [
    { name: 'Coca-Cola', sold: 48 },
    { name: 'Instant noodles', sold: 36 },
    { name: 'Mineral water', sold: 31 },
    { name: 'Coffee sachets', sold: 24 },
];

const recentActivity = [
    { label: 'New sale — 12,500 MMK', type: 'sale' },
    { label: 'Stock updated — Coca-Cola', type: 'stock' },
    { label: 'New customer added', type: 'customer' },
    { label: 'Expense recorded — 8,000 MMK', type: 'expense' },
];

export default function Welcome() {
    return (
        <MainLayout>
            <div className="space-y-6">
                {/* Hero banner */}
                <div className="rounded-2xl bg-gradient-to-r from-brand to-brand-dark p-8 text-white shadow-lg">
                    <h2 className="text-3xl font-bold">Good morning, Ye Myint</h2>
                    <p className="mt-4 inline-block rounded-xl bg-white/15 px-4 py-2 text-sm">
                        Small steps. Big growth.
                    </p>
                </div>

                {/* Metric cards */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {metrics.map((metric) => (
                        <div key={metric.label} className="rounded-2xl bg-white p-5 shadow-sm">
                            <p className="text-sm text-black/50">{metric.label}</p>
                            <p className="mt-2 text-2xl font-bold">{metric.value}</p>
                            <p className={`mt-1 text-sm font-medium ${metric.positive ? 'text-green-600' : 'text-red-500'}`}>
                                {metric.change}
                            </p>
                        </div>
                    ))}
                </div>

                {/* Charts row */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-2xl bg-white p-6 shadow-sm">
                        <h3 className="text-lg font-semibold">Sales Overview</h3>
                        <div className="mt-6 flex h-48 items-end gap-2">
                            {[40, 55, 45, 60, 70, 65, 85].map((height, i) => (
                                <div key={i} className="flex flex-1 flex-col items-center gap-2">
                                    <div
                                        className="w-full rounded-t-lg bg-brand/80"
                                        style={{ height: `${height}%` }}
                                    />
                                    <span className="text-xs text-black/40">
                                        {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][i]}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-2xl bg-white p-6 shadow-sm">
                        <h3 className="text-lg font-semibold">Sales by Category</h3>
                        <div className="mt-6 flex items-center gap-6">
                            <div className="relative h-36 w-36 shrink-0 rounded-full border-[14px] border-brand border-r-brand-dark border-b-amber-400 border-l-orange-300" />
                            <ul className="space-y-2 text-sm">
                                <li className="flex items-center gap-2"><span className="h-3 w-3 rounded-full bg-brand" /> Beverages 38%</li>
                                <li className="flex items-center gap-2"><span className="h-3 w-3 rounded-full bg-brand-dark" /> Snacks 22%</li>
                                <li className="flex items-center gap-2"><span className="h-3 w-3 rounded-full bg-amber-400" /> Daily needs 15%</li>
                                <li className="flex items-center gap-2"><span className="h-3 w-3 rounded-full bg-orange-300" /> Others 25%</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {/* Bottom cards */}
                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="rounded-2xl bg-white p-6 shadow-sm">
                        <h3 className="text-lg font-semibold">Needs Attention</h3>
                        <ul className="mt-4 space-y-3">
                            {attentionItems.map((item) => (
                                <li key={item} className="flex items-center gap-3 text-sm">
                                    <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-red-500" />
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="rounded-2xl bg-white p-6 shadow-sm">
                        <h3 className="text-lg font-semibold">Top Selling Products</h3>
                        <table className="mt-4 w-full text-sm">
                            <thead>
                                <tr className="text-left text-black/50">
                                    <th className="pb-2 font-medium">Product</th>
                                    <th className="pb-2 text-right font-medium">Sold</th>
                                </tr>
                            </thead>
                            <tbody>
                                {topProducts.map((product) => (
                                    <tr key={product.name} className="border-t border-black/5">
                                        <td className="py-2.5">{product.name}</td>
                                        <td className="py-2.5 text-right font-medium">{product.sold}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="rounded-2xl bg-white p-6 shadow-sm">
                        <h3 className="text-lg font-semibold">Recent Activity</h3>
                        <ul className="mt-4 space-y-4">
                            {recentActivity.map((item) => (
                                <li key={item.label} className="flex items-start gap-3 text-sm">
                                    <span className={`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${
                                        item.type === 'sale' ? 'bg-brand' :
                                        item.type === 'stock' ? 'bg-blue-500' :
                                        item.type === 'customer' ? 'bg-green-500' : 'bg-amber-500'
                                    }`} />
                                    {item.label}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                {/* Upgrade banner */}
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-brand-dark px-6 py-5 text-white">
                    <p className="text-lg font-semibold">Upgrade to Pro</p>
                    <button type="button" className="rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand/90">
                        View plans
                    </button>
                </div>
            </div>
        </MainLayout>
    );
}
