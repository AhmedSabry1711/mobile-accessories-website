import { Link } from 'react-router-dom';
import { useUI } from '../context/UIContext.jsx';
import BrandLogo from './BrandLogo.jsx';
import {
  IconTruck,
  IconShield,
  IconPhone,
  IconLock,
  IconTikTok,
  IconInstagram,
  IconWhatsApp,
  IconFacebook,
} from './icons.jsx';

/**
 * Social channels. A channel left empty renders a disabled "soon" badge
 * instead of a link — fill it in and the icon becomes a working link.
 */
const SOCIALS = {
  tiktok: 'https://www.tiktok.com/@mobi_boost_fayoum?_r=1&_t=ZS-9AFCVZcaomA',
  instagram: 'https://instagram.com/mobi_boost_fayoum?exln=NXZvZG02Z3dnZjVy&utm_source=qr',
  whatsapp: 'https://wa.me/201023538775',
  facebook: 'https://www.facebook.com/p/MobiBoost-61595036596685/',
};

export default function Footer() {
  const year = new Date().getFullYear();
  const { t } = useUI();

  return (
    <footer className="footer">
      <div className="container footer-grid">
        <div className="footer-brand">
          <div className="brand brand-footer">
            <BrandLogo />
          </div>
          <p>{t('footer.tagline')}</p>
          <ul className="footer-perks">
            <li>
              <IconTruck size={16} /> {t('footer.perks.freeShipping')}
            </li>
            <li>
              <IconShield size={16} /> {t('footer.perks.returns')}
            </li>
            <li>
              <IconPhone size={16} /> {t('footer.perks.cod')}
            </li>
          </ul>

          <div className="footer-social">
            <h4>{t('footer.contact')}</h4>
            <div className="social-links">
              <a
                className="social-link social-tiktok"
                href={SOCIALS.tiktok}
                target="_blank"
                rel="noreferrer"
                aria-label="TikTok"
                title="TikTok"
              >
                <IconTikTok />
              </a>
              <a
                className="social-link social-instagram"
                href={SOCIALS.instagram}
                target="_blank"
                rel="noreferrer"
                aria-label="Instagram"
                title="Instagram"
              >
                <IconInstagram />
              </a>
              <a
                className="social-link social-whatsapp"
                href={SOCIALS.whatsapp}
                target="_blank"
                rel="noreferrer"
                aria-label="WhatsApp"
                title="WhatsApp"
              >
                <IconWhatsApp />
              </a>
              {SOCIALS.facebook ? (
                <a
                  className="social-link social-facebook"
                  href={SOCIALS.facebook}
                  target="_blank"
                  rel="noreferrer"
                  aria-label="Facebook"
                  title="Facebook"
                >
                  <IconFacebook />
                </a>
              ) : (
                <span
                  className="social-link social-facebook is-soon"
                  aria-label="Facebook"
                  title={`Facebook — ${t('footer.soon')}`}
                >
                  <IconFacebook />
                </span>
              )}
            </div>
          </div>
        </div>

        <div className="footer-col">
          <h4>{t('footer.shop')}</h4>
          <Link to="/shop">{t('footer.links.allProducts')}</Link>
          <Link to="/shop?filter=sale">{t('footer.links.deals')}</Link>
          <Link to="/shop?filter=new">{t('footer.links.newArrivals')}</Link>
          <Link to="/shop?category=Phone%20Cases">{t('footer.links.phoneCases')}</Link>
          <Link to="/shop?category=Chargers">{t('footer.links.chargers')}</Link>
        </div>

        <div className="footer-col">
          <h4>{t('footer.help')}</h4>
          <Link to="/cart">{t('footer.links.yourCart')}</Link>
          <Link to="/checkout">{t('footer.links.checkout')}</Link>
          <Link to="/shop">{t('footer.links.shipping')}</Link>
          <Link to="/shop">{t('footer.links.returns')}</Link>
          <Link to="/shop">{t('footer.links.contact')}</Link>
        </div>

        <div className="footer-col footer-newsletter">
          <h4>{t('footer.newsletter')}</h4>
          <p>{t('footer.newsletterText')}</p>
          <form
            className="newsletter"
            onSubmit={(e) => {
              e.preventDefault();
              const email = e.target.email.value;
              if (email) {
                window.location.assign(
                  `mailto:sales@mobiboost.com?subject=Newsletter%20signup&body=Please%20add%20${encodeURIComponent(email)}%20to%20the%20Mobi%20Boost%20list.`
                );
              }
            }}
          >
            <input type="email" name="email" placeholder={t('footer.newsletterPlaceholder')} required />
            <button type="submit" className="btn btn-primary btn-sm">
              {t('footer.join')}
            </button>
          </form>
        </div>
      </div>

      <div className="footer-bottom">
        <div className="container footer-bottom-inner">
          <span>{t('footer.rights', { year })}</span>
          <span className="footer-bottom-right">
            <span className="footer-meta">{t('footer.meta')}</span>
            <Link to="/admin" className="footer-admin" title={t('footer.admin')}>
              <IconLock size={12} />
              {t('footer.admin')}
            </Link>
          </span>
        </div>
      </div>
    </footer>
  );
}