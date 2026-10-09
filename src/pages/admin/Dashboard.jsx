import { useEffect, useState } from 'react';
import { api, money } from '../../api/client.js';
import { useStore } from '../../context/StoreContext.jsx';
import { useUI } from '../../context/UIContext.jsx';
import { IconBox, IconTruck, IconChart, IconTag } from '../../components/icons.jsx';

export default function Dashboard() {
  const { toast } = useStore();
  const { t } = useUI();
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    api
      .get('/api/admin/dashboard.php')
      .then((res) => active && setStats(res))
      .catch((error) => active && toast(error.message, 'error'))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [toast]);

  if (loading) {
    return (
      <div className="admin-page">
        <div className="spinner" />
      </div>
    );
  }

  if (!stats) {
    return (
      <div className="admin-page">
        <p className="muted">{t('admin.dashboard.loadError')}</p>
      </div>
    );
  }

  const cards = [
    { label: t('admin.dashboard.revenue'), value: money(stats.revenue), icon: <IconChart size={20} />, tone: 'indigo' },
    { label: t('admin.dashboard.netProfit'), value: money(stats.profit), icon: <IconTag size={20} />, tone: 'green' },
    { label: t('admin.dashboard.orders'), value: stats.ordersCount, icon: <IconTruck size={20} />, tone: 'amber' },
    { label: t('admin.dashboard.products'), value: stats.productsCount, icon: <IconBox size={20} />, tone: 'slate' },
  ];

  return (
    <div className="admin-page">
      <div className="admin-page-head">
        <h1>{t('admin.dashboard.title')}</h1>
        <p>{t('admin.dashboard.subtitle')}</p>
      </div>

      <div className="stat-grid">
        {cards.map((card) => (
          <div key={card.label} className={`stat-card tone-${card.tone}`}>
            <div className="stat-icon">{card.icon}</div>
            <div>
              <span>{card.label}</span>
              <strong>{card.value}</strong>
            </div>
          </div>
        ))}
      </div>

      <div className="stat-grid secondary">
        <div className="mini-stat">
          <span>{t('admin.dashboard.completed')}</span>
          <strong>{stats.completedCount}</strong>
        </div>
        <div className="mini-stat">
          <span>{t('admin.dashboard.pending')}</span>
          <strong>{stats.pendingCount}</strong>
        </div>
        <div className="mini-stat">
          <span>{t('admin.dashboard.processing')}</span>
          <strong>{stats.processingCount}</strong>
        </div>
        <div className="mini-stat">
          <span>{t('admin.dashboard.totalCost')}</span>
          <strong>{money(stats.cost)}</strong>
        </div>
        <div className="mini-stat">
          <span>{t('admin.dashboard.lowStock')}</span>
          <strong>{stats.lowStockCount}</strong>
        </div>
        <div className="mini-stat">
          <span>{t('admin.dashboard.activeCoupons')}</span>
          <strong>{stats.couponsCount}</strong>
        </div>
        <div className="mini-stat">
          <span>{t('admin.dashboard.reviews')}</span>
          <strong>{stats.reviewsCount}</strong>
        </div>
      </div>

      <div className="admin-columns">
        <section className="panel">
          <h2>{t('admin.dashboard.topProducts')}</h2>
          {stats.topProducts.length ? (
            <table className="table">
              <thead>
                <tr>
                  <th>{t('admin.dashboard.thProduct')}</th>
                  <th>{t('admin.dashboard.thSold')}</th>
                  <th>{t('admin.dashboard.thRevenue')}</th>
                  <th>{t('admin.dashboard.thProfit')}</th>
                </tr>
              </thead>
              <tbody>
                {stats.topProducts.map((product) => (
                  <tr key={product.productId}>
                    <td>{product.name}</td>
                    <td>{product.sold}</td>
                    <td>{money(product.revenue)}</td>
                    <td className="profit">{money(product.profit)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <p className="muted">{t('admin.dashboard.noSales')}</p>
          )}
        </section>

        <section className="panel">
          <h2>{t('admin.dashboard.recentOrders')}</h2>
          {stats.recentOrders.length ? (
            <table className="table">
              <thead>
                <tr>
                  <th>{t('admin.dashboard.thOrder')}</th>
                  <th>{t('admin.dashboard.thCustomer')}</th>
                  <th>{t('admin.dashboard.thTotal')}</th>
                  <th>{t('admin.dashboard.thStatus')}</th>
                </tr>
              </thead>
              <tbody>
                {stats.recentOrders.map((order) => (
                  <tr key={order.id}>
                    <td>{order.orderNumber}</td>
                    <td>
                      {order.customer?.name}
                      <br />
                      <small className="muted">{order.customer?.phone}</small>
                    </td>
                    <td>{money(order.total)}</td>
                    <td>
                      <span className={`status status-${order.status}`}>
                        {t(`admin.status.${order.status}`)}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : (
            <p className="muted">{t('admin.dashboard.noOrders')}</p>
          )}
        </section>
      </div>
    </div>
  );
}