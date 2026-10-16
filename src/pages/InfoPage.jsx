import { Link } from 'react-router-dom';
import { useUI } from '../context/UIContext.jsx';
import {
  IconHeadphones,
  IconTruck,
  IconShield,
  IconPhone,
  IconWhatsApp,
} from '../components/icons.jsx';

/** The store's support line — shown on every info page. */
const PHONE_DISPLAY = '01023538775';
const PHONE_TEL = '+201023538775';
const WHATSAPP = 'https://wa.me/201023538775';

/**
 * The four static support pages share one layout:
 *   /help      المساعدة والدعم
 *   /shipping  الشحن والتوصيل
 *   /returns   سياسة الإرجاع
 *   /contact   اتصل بنا
 * `kind` picks the copy out of `info.<kind>.*` in the translations.
 */
const PAGES = {
  help: { paragraphs: ['p1', 'p2', 'p3'], Icon: IconHeadphones },
  shipping: { paragraphs: ['p1', 'p2', 'p3'], Icon: IconTruck },
  returns: { paragraphs: ['p1', 'p2', 'p3'], Icon: IconShield },
  contact: { paragraphs: ['p1', 'p2'], Icon: IconPhone },
};

export default function InfoPage({ kind }) {
  const { t } = useUI();
  const page = PAGES[kind] || PAGES.help;
  const { paragraphs, Icon } = page;

  return (
    <div className="container info-page">
      <nav className="breadcrumbs">
        <Link to="/">{t('product.breadcrumbs.home')}</Link>
        <span>/</span>
        <span>{t(`info.${kind}.title`)}</span>
      </nav>

      <div className="info-card">
        <div className="info-icon">
          <Icon size={22} />
        </div>
        <h1>{t(`info.${kind}.title`)}</h1>
        <p className="info-intro">{t(`info.${kind}.intro`)}</p>

        {paragraphs.map((paragraph) => (
          <p key={paragraph} className="info-text">
            {t(`info.${kind}.${paragraph}`)}
          </p>
        ))}

        <div className="info-actions">
          <a
            className="btn btn-primary"
            href={WHATSAPP}
            target="_blank"
            rel="noreferrer"
          >
            <IconWhatsApp size={16} />
            {t(`info.${kind}.cta`)}
          </a>
          <a className="btn btn-outline" href={`tel:${PHONE_TEL}`}>
            <IconPhone size={16} />
            {t('info.call')} — {PHONE_DISPLAY}
          </a>
        </div>

        <Link className="info-back" to="/">
          {t('info.backHome')}
        </Link>
      </div>
    </div>
  );
}
