export default function Welcome({ test }) {
    return (
        <div className="min-h-screen bg-gray-100 flex items-center justify-center">
            <div className="bg-white p-8 rounded-lg shadow-lg">
                <h1 className="text-3xl font-bold text-gray-800">Welcome to ShopPilot</h1>
                <p className="mt-4 text-gray-600">{test}</p>
                <p className="mt-2 text-green-600">React + Inertia is working!</p>
            </div>
        </div>
    );
}