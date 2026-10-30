/**
 * Store identity printed on shipping labels.
 *
 * Keep the sender details in one place so an address or phone change only has
 * to be made once. The label component reads these fields and falls back to
 * the WhatsApp number saved in the admin settings when `phone` is empty.
 */
export const COMPANY = {
  name: 'Mobi Boost',
  tagline: { ar: 'إكسسوارات الموبايل', en: 'Mobile accessories' },
  phone: '0102 353 8775',
  whatsapp: '+201023538775',
  email: 'sales@mobiboost.com',
  website: 'mobiboost.com',
  city: { ar: 'الفيوم، مصر', en: 'Fayoum, Egypt' },
};
