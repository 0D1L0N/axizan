'use client';

import Link from 'next/link';

export default function OrderCard({ order }) {
  const getStatusColor = (status) => {
    const colors = {
      pending: 'bg-yellow-100 text-yellow-800',
      accepted: 'bg-blue-100 text-blue-800',
      preparing: 'bg-purple-100 text-purple-800',
      ready: 'bg-green-100 text-green-800',
      in_delivery: 'bg-indigo-100 text-indigo-800',
      delivered: 'bg-emerald-100 text-emerald-800',
      completed: 'bg-teal-100 text-teal-800',
      cancelled: 'bg-red-100 text-red-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
  };

  const getStatusEmoji = (status) => {
    const emojis = {
      pending: '⏳',
      accepted: '✅',
      preparing: '👨‍🍳',
      ready: '📦',
      in_delivery: '🚚',
      delivered: '🏠',
      completed: '✨',
      cancelled: '❌',
    };
    return emojis[status] || '❓';
  };

  return (
    <Link href={`/orders/${order.id}`}>
      <div className="bg-white rounded-lg shadow hover:shadow-lg transition-shadow p-4 cursor-pointer">
        <div className="flex items-start justify-between mb-2">
          <div>
            <p className="font-semibold text-lg">{order.order_number}</p>
            <p className="text-sm text-gray-500">
              {new Date(order.created_at).toLocaleDateString('fr-FR')}
            </p>
          </div>
          <span className={`px-3 py-1 rounded-full text-sm font-semibold ${getStatusColor(order.status)}`}>
            {getStatusEmoji(order.status)} {order.status.replace('_', ' ').toUpperCase()}
          </span>
        </div>

        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm text-gray-600">
              {order.items_count} article{order.items_count > 1 ? 's' : ''}
            </p>
            <p className="text-sm text-gray-600">
              Livraison: {new Date(order.delivery_date).toLocaleDateString('fr-FR')}
            </p>
          </div>
          <p className="text-2xl font-bold text-primary">{order.total.toFixed(0)} XOF</p>
        </div>
      </div>
    </Link>
  );
}
