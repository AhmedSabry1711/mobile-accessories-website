# ربط قاعدة بيانات MySQL على Hostinger — خطوة بخطوة

> دليل تشغيلي لموقع **Mobi Boost** (`https://mobiboost.store`) على استضافة **Hostinger**.
> الموقع بيشتغل بـ **PHP 8 + MySQL (PDO)** — **مش محتاج MongoDB** خالص.
>
> الوقت المتوقع: **10–15 دقيقة**. لو حصلت مشكلة في أي خطوة، شوف جدول حل المشاكل في آخر الدليل.

---

## الرحلة في سطر واحد

```
hPanel → أنشئ داتابيز  →  api/config.php  →  استورد api/schema.sql
        →  اتأكد بـ api/health.php  →  اعمل أدمن → /admin → نظّف
```

---

## 0) قبل ما تبدأ — تأكد إن ده جاهز

| # | الحاجة | إزاي تتأكد |
| :-: | --- | --- |
| 1 | ملفات الموقع مرفوعة في `public_html` | hPanel → **Files → File Manager** → جوّه `public_html` تلاقي `index.html` ومجلد `api` |
| 2 | إصدار PHP **8.1 أو أحدث** | hPanel → Advanced → **PHP Configuration** → اختار 8.2 |
| 3 | إضافة **`pdo_mysql`** مفعّلة | نفس الصفحة → قائمة الـ extensions → `pdo_mysql` ✔ (مفعّلة افتراضيًا على Hostinger) |
| 4 | مجلد `uploads` موجود | `public_html/uploads` (لو مش موجود هنعمله في خطوة 7) |
> لو ملفات الموقع لسه مش مرفوعة: من كمبيوترك شغّل `deploy.ps1` → هيطلعلك ملف **`mobiboost-deploy.zip`** → ارفعه في `public_html` وافكّه (Extract).

---

## 1) أنشئ قاعدة البيانات من hPanel

1. ادخل **hPanel**: <https://hpanel.hostinger.com> → اختار الموقع **`mobiboost.store`**.
2. من القائمة الجانبية: **Databases** → **MySQL Databases**.
3. تحت **Create a new MySQL database** اكتب:
   - **Database name:** `mobi`
     → Hostinger بيضيف بادئة الحساب لوحده، فالاسم النهائي بيبقى **`u126187_mobi`**
   - **Username:** `user`
     → بيبقى **`u126187_user`**
   - **Password:** اضغط **Generate** → وبعدين **انسخ الباسورد في مكان آمن دلوقتي** (مش هيتعرض تاني بنفس الشكل)
4. اضغط **Create**.
5. هتظهرلك بيانات الاتصال — دي اللي هنستخدمها في الخطوة الجاية:

   | الإعداد | القيمة (مثال من حسابك) |
   |---|---|
   | **Host** | `localhost` — أو `srv1566.hstgr.io` لو `localhost` مش نافع |
   | **Port** | `3306` |
   | **Database** | `u126187_mobi` |
   | **Username** | `u126187_user` |
   | **Password** | الباسورد اللي نسخته |

> 💡 **أستخدم أي Host؟**
> من داخل PHP على **نفس السيرفر** استخدم **`localhost`** — أسرع وأأمن ومش محتاج أي إذن.
> الاسم الطويل (زي `srv1566.hstgr.io`) بيتستخدم للاتصال **من برّه** السيرفر (Remote MySQL).
> لو `localhost` طلع خطأ في خطوة 4، ارجع للاسم الطويل اللي hPanel عارضه.

---

## 2) حطّ بيانات الاتصال في `api/config.php`

افتح الملف: **hPanel → Files → File Manager → `public_html/api/config.php`** → كليك يمين → **Edit**.

دور على البلوك `'mysql' => [ ... ]` واستبدله بالكود ده بالظبط (بالقيم بتاعتك):

```php
'mysql' => [
    'host'        => 'localhost',
    'port'        => 3306,
    'database'    => 'u126187_mobi',
    'username'    => 'u126187_user',
    'password'    => 'اكتب_هنا_الباسورد_اللي_نسخته',
    'tablePrefix' => 'mb_',
],
```

وانزل تحت خالص وظبّط الاتنين دول كمان:

```php
// سر طويل عشوائي لتوقيع توكن الأدمن — مينفعش يفضل القيمة الافتراضية
'jwtSecret'  => 'نص_طويل_عشوائي_64_حرف_على_الأقل',

// الدومين بتاعك بدل النجمة
'corsOrigin' => 'https://mobiboost.store',
```

- توليد نص عشوائي: `openssl rand -hex 32` في أي تيرمنال، أو أي مولّد نصوص عشوائية.
- اضغط **Save**.

> 🔒 `api/config.php` جواه الباسورد والسر — متحطّهوش في Git ومتشاركوش مع حد.

---

## 3) أنشئ الجداول (12 جدول)

### الطريقة (أ) — phpMyAdmin ✅ الأحسن لو مش عندك تيرمنال

1. hPanel → **Databases** → **phpMyAdmin** (أو زر *Enter phpMyAdmin* جنب الداتابيز).
2. من القائمة الشمال اختار الداتابيز **`u126187_mobi`**.
3. تبويب **Import**.
4. **Choose File** → اختار ملف **`api/schema.sql`**
   (موجود في نسخة المشروع عندك على الكمبيوتر: `mobile-accessories-website\api\schema.sql`).
5. انزل تحت واضغط **Import**.
6. المفروض تظهر رسالة *"Import has been successfully finished"*، وتلاقي **12 جدول** في القائمة الشمال:

```
mb_products   mb_product_images   mb_product_variants   mb_product_variant_colors
mb_orders     mb_order_items      mb_reviews            mb_coupons
mb_ads        mb_settings         mb_categories         mb_users
```

### الطريقة (ب) — Terminal / SSH (لو خطتك بتسمح)

```bash
php /home/u126187/domains/mobiboost.store/public_html/api/migrate.php
```

- على داتابيز **فاضية** → بيعمل نفس الجداول الـ12.
- على داتابيز **فيها الداتا القديمة** (شكل `id` / `doc` / `created_at`) → بيعمل الترقية:
  بينسخ كل جدول قديم لاسم `mb_legacy_<الاسم>` (من غير ما يمسح حاجة)، وبيعمل الجداول الجديدة،
  وبيحوّل كل البيانات جواها. الملف آمن لو شغّلته أكتر من مرة.

---

## 4) اتأكد إن الاتصال شغال ✅

افتح في المتصفح:

```
https://mobiboost.store/api/health.php
```

المفروض يرجّعلك JSON زي ده:

```json
{
  "ok": true,
  "php_version": "8.2.x",
  "db": { "connected": true },
  "tables": {
    "mb_products": "ok",
    "mb_product_images": "ok",
    "mb_users": "ok"
  }
}
```

- `"connected": true` → الاتصال بقاعدة البيانات تمام.
- كل الجداول `"ok"` → الجداول اتعملت صح.
- أي جدول مكتوب عليه **`"MISSING"`** → معناها استيراد `schema.sql` ناقص → ارجع لخطوة 3.

لو ظهر خطأ جوه `db.error`:

| الرسالة | السبب | الحل |
|---|---|---|
| `Access denied for user` | يوزر أو باسورد غلط | راجع خطوة 2 وانسخ الباسورد تاني من hPanel |
| `Unknown database` | اسم الداتابيز غلط | لازم يكون بالبادئة: `u126187_mobi` |
| `No such host` / `getaddrinfo failed` | الـHost غلط | جرّب اسم السيرفر الطويل بدل `localhost` |
| `could not find driver` | `pdo_mysql` مقفولة | hPanel → Advanced → PHP Configuration → فعّلها |

> 🗑️ **مهم جدًا:** بعد ما كل حاجة تبقى تمام، **امسح الملف `api/health.php`** — ده ملف تشخيص مؤقت ومسموح له يعرض تفاصيل السيرفر.

---

## 5) اعمل حساب الأدمن

من غير مستخدم أدمن مش هتقدر تدخل `/admin`. اختار **واحدة** من الطرق دي:

### (أ) بيانات تجريبية كاملة (منتجات + كوبونات + بانرات + أدمن) — عبر Terminal

```bash
php /home/u126187/domains/mobiboost.store/public_html/api/seed.php
```

هيعمل 10 منتجات + كوبونين + 3 بانرات + أدمن **`admin` / `admin123`**.
> استخدمها لو عايز المتجر يبان مليان بسرعة للتجربة.
> لو المتجر حقيقي وناوي تحطّ منتجاتك بنفسك → استخدم الطريقة (ج).

### (ب) نفس الحاجة بدون Terminal — Cron Job تشتغل مرة واحدة

1. hPanel → **Advanced** → **Cron Jobs** → **Create**.
2. في خانة الأمر (Command) حط:

   ```bash
   php /home/u126187/domains/mobiboost.store/public_html/api/seed.php >> /home/u126187/seed.log 2>&1
   ```

3. اختار أي جدول زمني (مثلًا كل ساعة) واحفظ، واستنى دورة واحدة.
4. **بعد ما تخلص: ارجع امسح الـCron Job** (مهم جدًا).
5. للتأكد: افتح `https://mobiboost.store/api/products.php?limit=1` — لو رجّع منتجات يبقى تمام.

### (ج) أرجل واحد أدمن بس (بدون منتجات تجريبية) — أسرع طريقة بدون Terminal

في **phpMyAdmin** → الداتابيز `u126187_mobi` → تبويب **SQL** → الصق الكود ده واضغط **Go**:

```sql
INSERT INTO `mb_users` (`username`, `password_hash`, `created_at`)
VALUES ('admin', '$2y$10$WCCUL8aq3TzMrdDo0P7ciu1gux.djggZDxL7L20sQohuvgSAKrnSq', UTC_TIMESTAMP());
```

- بيعمل مستخدم **`admin`** بباسورد **`admin123`** (هاش bcrypt جاهز وصالح).
- بعدها ادخل `/admin` **وغيّر الباسورد فورًا**، وضيف منتجاتك من لوحة التحكم.
- كل الإعدادات (واتساب / الشحن / العملة / الأقسام) بتتعمل من صفحة **Settings** جوه `/admin` — مش محتاجة أي SQL.

### (د) من كمبيوترك مباشرة (لو SSH مش متاح)

من hPanel → Databases → **Remote MySQL** → ضيف الـIP بتاعك (Hostinger بيوريك الـIP الحالي)، وبعدها من الريبو على جهازك:

```bash
php api/seed.php
```

> ⚠️ الطريقة دي بتخلي `api/config.php` بيشاور على داتابيز الإنتاج — ارجع للقيم المحلية بعد ما تخلص.

---

## 6) ادخل لوحة التحكم وظبّط كل حاجة

1. افتح `https://mobiboost.store/admin`
2. سجّل دخول بـ **`admin`** / **`admin123`**
3. **أول حاجة: غيّر الباسورد.**
4. من **Settings**:
   - **رقم الواتساب** بصيغة دولية: `+201023538775`
   - **العملة**: `EGP` / `ج.م` أو اللي تحبه
   - **الشحن**: فعّله + السعر + حد الشحن المجاني
   - **الأقسام (Categories)**: ضيف أقسام متجرك
5. ضيف منتج تجريبي وشوفه ظاهر على الموقع.
6. اعمل طلب تجريبي وشوفه في صفحة Orders.

---

## 7) مجلد الرفع `uploads`

1. لو مش موجود: hPanel File Manager → `public_html` → **New Folder** → اسمها `uploads`.
2. الصلاحيات: كليك يمين → **Permissions** → **755**.
3. جرّب ترفع صورة منتج من `/admin` — لو نجحت يبقى الملف قابل للكتابة.

---

## 8) التنظيف بعد ما تخلص (مهم)

- [ ] امسح `public_html/api/health.php`
- [ ] امسح الـCron Job بتاع الـseed لو استخدمت الطريقة (ب)
- [ ] غيّر باسورد الأدمن
- [ ] اتأكد إن جداول `mb_legacy_*` مش محتاجها → امسحها
- [ ] اتأكد إن `api/config.php` مش متسرّب في أي مكان عام
- [ ] `jwtSecret` مش القيمة الافتراضية

---

## حل المشاكل — نظرة سريعة

| العرض | السبب المحتمل | الحل |
|---|---|---|
| الموقع يفتح لكن المنتجات فاضية | الجداول فاضية (مفيش seed) أو endpoint بيرجّع خطأ | افتح `/api/products.php` وشوف الرسالة؛ راجع خطوة 5 |
| `db.connected: false` | بيانات الاتصال غلط في `config.php` | راجع خطوة 2، وجرّب الـHostين (`localhost` والاسم الطويل) |
| كل الجداول `MISSING` | `schema.sql` ماتستوردش | كرّر خطوة 3 (تأكد إنك مستورد على نفس الداتابيز) |
| `/admin` بيرجّع 500 | مفيش مستخدم أدمن أو `mb_users` فاضل | نفّذ خطوة 5 (ج) |
| رفع الصور بيفشل | `uploads` مش موجود أو صلاحيته غلط | خطوة 7 (صلاحية 755) |
| أخطاء CORS في المتصفح | `corsOrigin` لسه `*` أو دومين غلط | خطوة 2 → خليها `https://mobiboost.store` |

---

## نقل بياناتك من اللوكال (XAMPP) للإنتاج

1. **عندك محليًا** في phpMyAdmin: اختار الداتابيز → تبويب **Export** → **Go** → هيطلع ملف `.sql`.
2. **على Hostinger** في phpMyAdmin: اختار `u126187_mobi` → تبويب **Import** → ارفع الملف.
3. لو نفس الأسماء موجودة، امسح الجداول الأول من الإنتاج أو خلّي ملف التصدير من غير `DROP TABLE`/`CREATE TABLE` (اختار Data only).
