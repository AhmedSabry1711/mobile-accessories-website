import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client.js';
import AdsCarousel from '../components/AdsCarousel.jsx';
import ProductShelf from '../components/ProductShelf.jsx';
import {
  IconTruck,
  IconShield,
  IconPhone,
  IconTag,
  IconBolt,
  IconCable,
  IconHeadphones,
} from '../components/icons.jsx';
import { localizeCategory } from '../i18n/catalog.js';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';

/** Canonical category → glyph, so admin-added labels still get a sensible icon. */
const CATEGORY_ICONS = {
  'Phone Cases': IconShield,
  Chargers: IconBolt,
  Cables: IconCable,
  Earbuds: IconHeadphones,
};

export default function Home() {
  const { toast } = useStore();
  const { t, lang } = useUI();
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

  const categories = settings?.categories || [];

  return (
    <div className="home">
      <AdsCarousel ads={settings?.ads || []} />

      <section className="hero">
        <div className="container hero-inner">
          <div className="hero-copy">
            <span className="hero-eyebrow">{t('home.eyebrow')}</span>
            <h1>
              {t('home.heroTitle1')}
              <br />
              <span>{t('home.heroTitle2')}</span>
            </h1>
            <p>{t('home.heroText')}</p>

            <div className="hero-delivery">
              <span className="hero-delivery-icon">
                <IconTruck size={17} />
              </span>
              <span>{t('home.delivery')}</span>
            </div>

            <div className="hero-actions">
              <a href="#shop" className="btn btn-primary btn-lg">
                {t('home.cta')}
              </a>
              <a href="#deals" className="btn btn-outline btn-lg">
                {t('home.todayDeals')}
              </a>
            </div>
          </div>

          <div className="hero-perks">
            <div className="perk">
              <span className="perk-icon">
                <IconTruck size={20} />
              </span>
              <div>
                <strong>{t('home.perks.fast.title')}</strong>
                <span>{t('home.perks.fast.text')}</span>
              </div>
            </div>
            <div className="perk">
              <span className="perk-icon">
                <IconShield size={20} />
              </span>
              <div>
                <strong>{t('home.perks.quality.title')}</strong>
                <span>{t('home.perks.quality.text')}</span>
              </div>
            </div>
            <div className="perk">
              <span className="perk-icon">
                <IconPhone size={20} />
              </span>
              <div>
                <strong>{t('home.perks.cod.title')}</strong>
                <span>{t('home.perks.cod.text')}</span>
              </div>
            </div>
            <div className="perk">
              <span className="perk-icon">
                <IconTag size={20} />
              </span>
              <div>
                <strong>{t('home.perks.price.title')}</strong>
                <span>{t('home.perks.price.text')}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      {categories.length > 0 && (
        <section className="cats">
          <div className="container">
            <div className="cats-head">
              <h2>{t('home.categories')}</h2>
              <p>{t('home.categoriesSub')}</p>
            </div>
            <div className="cats-grid">
              {categories.map((category) => {
                const Icon = CATEGORY_ICONS[category] || IconTag;
                return (
                  <Link
                    key={category}
                    to={`/shop?category=${encodeURIComponent(category)}`}
                    className="cat-card"
                  >
                    <span className="cat-card-icon">
                      <Icon size={22} />
                    </span>
                    <strong>{localizeCategory(category, lang)}</strong>
                  </Link>
                );
              })}
            </div>
          </div>
        </section>
      )}

      <div id="deals" />

      <ProductShelf
        title={t('home.deals.title')}
        subtitle={t('home.deals.subtitle')}
        viewAllTo="/shop?filter=sale"
        products={sale}
      />

      <ProductShelf
        title={t('home.fresh.title')}
        subtitle={t('home.fresh.subtitle')}
        viewAllTo="/shop?filter=new"
        products={fresh}
      />

      <div id="shop" />

      <ProductShelf
        title={t('home.all.title')}
        subtitle={t('home.all.subtitle')}
        viewAllTo="/shop"
        products={all}
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
