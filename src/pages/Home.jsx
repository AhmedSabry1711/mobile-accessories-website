import { useEffect, useState } from 'react';
import { api } from '../api/client.js';
import AdsCarousel from '../components/AdsCarousel.jsx';
import ProductShelf from '../components/ProductShelf.jsx';
import { IconTruck, IconShield, IconPhone, IconTag } from '../components/icons.jsx';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';

export default function Home() {
  const { toast } = useStore();
  const { t } = useUI();
  const [settings, setSettings] = useState(null);
  const [sale, setSale] = useState([]);
  const [fresh, setFresh] = useState([]);
  const [all, setAll] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    Promise.all([
      api.get('/api/settings.php'),
      api.get('/api/products.php?filter=sale&limit=8'),
      api.get('/api/products.php?filter=new&limit=8'),
      api.get('/api/products.php?filter=all&limit=12'),
    ])
      .then(([settingsRes, saleRes, freshRes, allRes]) => {
        if (!active) return;
        setSettings(settingsRes);
        setSale(saleRes.items || []);
        setFresh(freshRes.items || []);
        setAll(allRes.items || []);
      })
      .catch((error) => toast(error.message, 'error'))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [toast]);

  const currency = settings?.currency?.symbol || '$';

  return (
    <div className="home">
      <AdsCarousel ads={settings?.ads || []} />

      <section className="hero">
        <div className="container hero-inner">
          <div className="hero-copy">
            <span className="hero-eyebrow">{t('home.eyebrow')}</span>
            <h1>
              {t('home.heroTitle1')} <span>{t('home.heroTitleAccent')}</span>
            </h1>
            <p>{t('home.heroText')}</p>
            <div className="hero-actions">
              <a href="#shop" className="btn btn-primary">
                {t('home.cta')}
              </a>
              <a href="#deals" className="btn btn-outline">
                {t('home.todayDeals')}
              </a>
            </div>
          </div>
          <div className="hero-perks">
            <div className="perk">
              <IconTruck size={22} />
              <div>
                <strong>{t('home.perks.fast.title')}</strong>
                <span>{t('home.perks.fast.text')}</span>
              </div>
            </div>
            <div className="perk">
              <IconShield size={22} />
              <div>
                <strong>{t('home.perks.quality.title')}</strong>
                <span>{t('home.perks.quality.text')}</span>
              </div>
            </div>
            <div className="perk">
              <IconPhone size={22} />
              <div>
                <strong>{t('home.perks.cod.title')}</strong>
                <span>{t('home.perks.cod.text')}</span>
              </div>
            </div>
            <div className="perk">
              <IconTag size={22} />
              <div>
                <strong>{t('home.perks.price.title')}</strong>
                <span>{t('home.perks.price.text')}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <div id="deals" />

      <ProductShelf
        title={t('home.deals.title')}
        subtitle={t('home.deals.subtitle')}
        viewAllTo="/shop?filter=sale"
        products={sale}
        currency={currency}
      />

      <ProductShelf
        title={t('home.fresh.title')}
        subtitle={t('home.fresh.subtitle')}
        viewAllTo="/shop?filter=new"
        products={fresh}
        currency={currency}
      />

      <div id="shop" />

      <ProductShelf
        title={t('home.all.title')}
        subtitle={t('home.all.subtitle')}
        viewAllTo="/shop"
        products={all}
        currency={currency}
      />

      {!loading && !all.length && (
        <div className="container empty-state">
          <h3>{t('home.empty.title')}</h3>
          <p dangerouslySetInnerHTML={{ __html: t('home.empty.text') }} />
        </div>
      )}
    </div>
  );
}