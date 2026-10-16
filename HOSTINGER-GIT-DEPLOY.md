# 🚀 نشر Mobi Boost على Hostinger عبر Git — الدليل النهائي

> **الهدف:** إنهاء مشكلة اختفاء مجلد `api/` وملفات PHP و`.htaccess` من `public_html`
> بعد كل عملية تحديث أو Redeploy — نهائيًا.
>
> **اقرأ الصفحة كاملة قبل ما تنفّذ أي خطوة.** الخطوات مكتوبة بالترتيب، وفيها
> خطوات لا رجعة فيها (تفريغ `public_html`) محمية بنسخة احتياطية.

---

## ١. السبب الجذري — ليه الملفات بتختفي؟

المشكلة مش عشوائية، هي نتيجة ثلاث حقائق مجتمعة:

| # | الحقيقة | النتيجة |
|---|---------|---------|
| ١ | **Hostinger ما بينفّذش أمر بناء (build).** الموقع الرسمي: *"the files committed to the repo are the files served"* — يعني اللي في الفرع هو اللي بيتنسخ حرفيًا. | لازم الفرع اللي Hostinger بيسحب منه يكون **جاهز للنشر** (فيه `index.html` والـ`assets` المبنية). |
| ٢ | **مجلد البناء `dist/` مستثنى من Git** (`.gitignore`). | الفرع `main` ما فيهوش الفرونت إند المبني خالص. |
| ٣ | **كل عملية نشر بتستبدل محتوى المجلد الهدف بالكامل.** | أي حاجة مرفوعة بإيدك (زي `api/`) **بتتمسح** لأنها مش موجودة في الفرع. |

وكان في مشكلة رابعة منفصلة وأخطر:

> ⚠️ **ملف `api/config.php` كان متتبَّعًا في Git (tracked)!**
> ده كان معناه إن أي `git pull` أو استبدال للمجلد **بيرجّع الملف للقيم الوهمية**
> (`CHANGE_ME_*`) → وبعدها الاتصال بقاعدة البيانات يفشل وتظهر أخطاء 500.

**الخلاصة:** المشكلة كانت في **التصميم**، مش في الاستضافة. الحل هو إن نجعل
`public_html` **ناتجًا يُبنى تلقائيًا** (artifact) بدل ما يكون مجلدًا بنعدّله بإيدينا.

---

## ٢. المعمارية الجديدة — من «مجلد بنلمسه بإيدينا» إلى «فرع بيتولّد لوحده»

```
┌──────────────────┐   push    ┌────────────────────────┐   build    ┌─────────────────┐
│  فرع main        │──────────>│  GitHub Action         │──────────>│  فرع deploy     │
│  (الكود المصدري)  │           │  deploy.yml            │            │  (الموقع الجاهز)│
│  src/ api/ ...   │           │  npm ci && npm run     │            │  index.html     │
└──────────────────┘           │  build → تركيب artifact│            │  assets/        │
                               └────────────────────────┘            │  api/           │
                                                                     │  .htaccess      │
                                                                     │  uploads/       │
                                                                     └────────┬────────┘
                                                                              │ Hostinger يسحب
                                                                              ▼
                                                                    ┌──────────────────┐
                                                                    │  public_html/    │
                                                                    │  (استبدال كامل   │
                                                                    │   = آمن تمامًا)  │
                                                                    └──────────────────┘

ملفات لا تدخل Git أبدًا وتبقى خارج نطاق الاستبدال:
  🔐  /home/uXXXX/mobi-private/config.php   ← بيانات قاعدة البيانات والـ jwtSecret
  🖼️  public_html/uploads/*                 ← صور المنتجات اللي بترفعها من لوحة التحكم
```

**القاعدة الذهبية:** كل حاجة جوه `public_html` لازم تكون جاية من الفرع `deploy`.
الإستثناءان الوحيدان هما **الاعتمادات** (بره `public_html` خالص) و**الصور المرفوعة**
(مستثناة من Git فبتفضل مكانها).

---

## ٣. هيكل المستودع (Repository Structure)

```
mobile-accessories-website/            ← فرع main = المصدر (اللي بتشتغل عليه)
├── src/                               │  كود React (المصدر)
├── public/                            │  أيقونات المتصفح (Vite بينسخها لـ dist)
├── index.html                         │  نقطة دخول Vite (مش بتترفع كما هي)
├── api/                               │  كود PHP
│   ├── _config.php                    │  ⭐ بيحدّد مكان ملف الاعتمادات في وقت التشغيل
│   ├── config.example.php             │  ⭐ قالب عام (متتبَّع في Git - آمن)
│   ├── config.php                     │  🔒 محلي فقط — مستثنى من Git (machin)
│   └── ... باقي نقاط الـ API
├── api-mysql/                         │  نسخة مطابقة لـ api/ (تُحدَّث يدويًا)
├── .github/workflows/deploy.yml       │  ⭐ بيبني فرع deploy تلقائيًا
├── .htaccess                          │  قواعد Apache (SPA + Authorization)
├── deploy.ps1                         │  بناء ونشر يدوي (بديل للـ Action)
└── HOSTINGER-GIT-DEPLOY.md            │  هذا الملف

┌─ فرع deploy (بيتولّد تلقائيًا - لا تعدّله بإيدك) ─────────────────────┐
│  index.html · assets/ · api/ (بدون config.php) · .htaccess · uploads/  │
└────────────────────────────────────────────────────────────────────────┘
```

### الفرق المهم

| | فرع `main` | فرع `deploy` |
|---|---|---|
| الغرض | تشتغل عليه وتعمل commits | Hostinger بيسحب منه |
| المحتوى | `src/`, `api/`, أدوات، توثيق | الموقع الجاهز فقط |
| `dist/` | مش موجود (بيتبني) | محتواه هو جذر الفرع |
| `api/config.php` | ❌ مش موجود | ❌ مش موجود |
| تعدّله بإيدك؟ | ✅ أيوه | ❌ بيتولّد تلقائيًا وبيتكتب عليه |

---

## ٤. الملفات الحساسة (Secrets) — إزاي محميّة

### قبل (المشكلة)
```
public_html/api/config.php   ← متتبَّع في Git + جوه نطاق الاستبدال = بيتمسح/بيتبدّل
```

### بعد (الحل)
```
/home/u118872087/mobi-private/config.php   ← بره public_html تمامًا 🔒
/home/u118872087/public_html/api/_config.php ← بيدوّر عليه ويلاقيه لوحده
```

`api/_config.php` بيدوّر على ملف الاعتمادات بالترتيب ده (أول واحد يلاقيه هو اللي يكسب):

| # | المكان | الاستخدام |
|---|--------|-----------|
| ١ | متغير البيئة `MOBI_CONFIG` | لو الاستضافة بتوفّر لوحة "Environment variables" |
| ٢ | `~/mobi-private/config.php` | ⭐ **المكان الموصى به في الإنتاج** |
| ٣ | `api/config.php` | التطوير المحلي على XAMPP |

### مميزات التصميم ده

- ✅ **مستحيل يتمسح أو يتبدّل** — أصلًا بره المجلد اللي بيتستبدل.
- ✅ **مش موجود في المستودع** — مفيش أي خطر إن بياناتك تتكشف.
- ✅ **قابل للتجاوز بمتغيرات بيئة** — كل قيمة في الملف مكتوبة بصيغة
  `getenv('MYSQL_PASSWORD') ?: 'قيمة'`، يعني لو الاستضافة ضبطت المتغير هو اللي يكسب.
- ✅ **بيبلّغك بوضوح لو الملف ناقص** — بدل صفحة بيضاء 500، بيرجّع JSON فيه
  `"Configuration error: No configuration file found. Looked in: ..."`.
- ✅ **محمي من مشكلة BOM** — لو حرّرت الملف بـ File Manager وضاف
  علامة UTF-8 BOM، الكود بيتجاهلها بدل ما تفشّل كل ردود الـ JSON.

> 💡 **عن متغيرات البيئة على Hostinger:** المسار الموصى به هو الملف خارج
> `public_html` — أسهل وأضمن. لو حابب تستخدم متغيرات بيئة، ضيف في `.htaccess`:
> ```apache
> SetEnv MOBI_CONFIG /home/u118872087/mobi-private/config.php
> ```
> (موجودة كسطر معلَّق جاهز في `.htaccess` بتاعك).

---

## ٥. الخطوات العملية — بالترتيب

### 🔵 المرحلة أ: تحضير المستودع والفرع (مرة واحدة)

#### أ-١) الـ Action بيشتغل لوحده
بمجرد ما ترفع التعديلات على `main`، GitHub هيبني فرع `deploy` تلقائيًا.

```powershell
cd C:\xampp\htdocs\mobile-accessories-website
git add -A
git commit -m "deploy: git-based deployment pipeline"
git push origin main
```

**تأكّد إن الفرع اتولّد:**
- افتح `https://github.com/AhmedSabry1711/mobile-accessories-website/actions`
- لازم تلاقي تشغيل أخضر ✅ اسمه *Publish deploy branch*
- من قائمة الفروع، لازم تلاقي فرع **`deploy`**

> 🔁 **بديل بدون GitHub Actions:** شغّل
> `powershell -ExecutionPolicy Bypass -File .\deploy.ps1 -Publish`
> وهو هيعمل نفس الحاجة (بناء + رفع فرع `deploy`).

#### أ-٢) تأكّد إن فرع `deploy` فيه الملفات الصح
افتح الفرع على GitHub واتأكد إن **جذره** فيه:

```
✅ index.html          ✅ api/products.php      ✅ .htaccess
✅ assets/             ✅ api/_config.php       ✅ uploads/.gitkeep
❌ api/config.php      ← ممنوع وجوده (لو موجود، ده خطأ)
```

---

### 🔵 المرحلة ب: تجهيز السيرفر (مرة واحدة — أهم مرحلة)

> 🛑 **قبل ما تبدأ:** المرحلة دي فيها **تفريغ `public_html`**. متخافش — بنعمل
> نسخة احتياطية كاملة الأول، والخطوات قابلة للرجوع.

#### ب-١) حدّد مسار حسابك
في hPanel → **ملف تعريف الحساب / Websites**، أو من File Manager: اضغط على
`public_html` وهتلاقي المسار `Home` — عادةً `/home/u118872087/`.
**اكتبه عندك**، هتحتاجه.

#### ب-٢) أنشئ ملف الاعتمادات **بره** `public_html` 🔐
1. hPanel → **File Manager**.
2. من الشريط العلوي، انزل من `public_html` لمجلد **Home** (الرئيسي).
3. **+ New folder** → سمّيه **`mobi-private`** (بالظبط).
4. ادخل عليه → **+ New file** → سمّيه **`config.php`**.
5. عليه كليك يمين → **Edit** → الصق المحتوى ده، مع تغيير القيم بقيمك الحقيقية:

```php
<?php
declare(strict_types=1);

return [
    'mongoUri' => 'mongodb+srv://mobiuser:ChangeMe123@cluster0.example.mongodb.net',
    'dbName'   => 'mobi_boost',

    'mysql' => [
        'host'        => 'localhost',
        'port'        => 3306,
        'database'    => 'u118872087_mobi',     // ← من hPanel → Databases
        'username'    => 'u118872087_user',     // ← اسم مستخدم قاعدة البيانات
        'password'    => 'كلمة-سر-قاعدة-البيانات',
        'tablePrefix' => 'mb_',
    ],

    'jwtSecret'     => 'ضع-هنا-نص-عشوائي-طويل-جدًا-64-حرف',
    'currencySymbol' => 'ج.م',      // ← عدّلها حسب عملتك
    'currencyCode'   => 'EGP',
    'corsOrigin'     => 'https://mobiboost.store',
    'pageSize'       => 24,
];
```

6. **Save**. ⚠️ لو الـ Editor سألك عن الـ Encoding، اختار **UTF-8 بدون BOM**.

> ℹ️ `uploadDir` و `uploadUrl` **مش موجودين في الملف عن قصد** — الكود ببيحسبهم
> تلقائيًا كـ `<جذر الموقع>/uploads`. كده لو نقلت ملف الاعتمادات في أي مكان
> تاني، صور المنتجات مش هتبع.

#### ب-٣) خُد نسخة احتياطية من الملفات المؤقتة
دي الحاجات اللي **مش** جاية من Git ولازم تفضل:

| الحاجة | المكان | إيه اللي تعمله |
|--------|--------|----------------|
| 🔐 الاعتمادات | `api/config.php` | ✅ خلصت — بقت في `~/mobi-private/` |
| 🖼️ صور المنتجات | `public_html/uploads/` | **نزّلها** أو انقلها لـ `~/uploads-backup/` |

**لنقل الصور:** File Manager → `public_html` → `uploads` → كليك يمين → **Move** →
اكتب `~/uploads-backup`. (أو نزّلها ZIP عندك على الجهاز.)

#### ب-٤) فصّل ارتباط Git الحالي
hPanel → ابحث عن **Git** (عادة `Advanced → GIT`، أحيانًا `Files → Git`).

- لو في **repository مرتبط** → من قائمة الإجراءات → **Disconnect**
  (ملفاته هتفضل مكانها، مش بتتمسح).
- لو في **GitHub auto-deploy** مفعّل → اقفله كمان.

> ⚠️ ده مهم: لو سيبت الارتباط القديم، أول نشر هيفشل أو هيعمل صراع.

#### ب-٥) افرغ `public_html` بأمان
1. في **Home**، اعمل مجلد جديد اسمه `backup-2026-10-16` (أو أي تاريخ).
2. ادخل `public_html` → **Select all** → **Move** → اختار `~/backup-2026-10-16`.
3. المفروض `public_html` تبقى **فاضية تمامًا**.

> 💡 الخطوة دي إلزامية: Hostinger بيرفض النشر الأول في مجلد فيه ملفات من
> مستودع أو فرع مختلف. والطريقة دي reversable — كل حاجة في الباك اب.

#### ب-٦) اربط المستودع بالفرع `deploy`
في نفس صفحة **Git**:

| الإعداد | القيمة |
|---------|--------|
| Repository URL | `https://github.com/AhmedSabry1711/mobile-accessories-website.git` |
| Branch | **`deploy`** ⭐ (مش `main`!) |
| Root directory / Install path | **اتركه فاضي** ← معناه `public_html` |
| Auto-deployment | مفعّل (لو متاح) |

اضغط **Deploy**.

> 🔒 لو المستودع **private**: اضغط **Generate SSH Key** وانسخ المفتاح وضيفه
> كـ Deploy Key في GitHub → Settings → Deploy keys.

#### ب-٧) رجّع صور المنتجات
File Manager → `~/uploads-backup` → **Select all** → **Move** →
`~/public_html/uploads`.

---

### 🔵 المرحلة ج: التحقق ✅

| # | الاختبار | النتيجة المتوقعة |
|---|----------|------------------|
| ١ | افتح `https://mobiboost.store/api/health.php` | JSON فيه `"ok": true` و `"db": {"connected": true}` و `"uploads_writable": true` و **`"loaded_from"`** = مسار `~/mobi-private/config.php` |
| ٢ | افتح `https://mobiboost.store/api/products.php?filter=all` | JSON فيه `"success": true` والمنتجات |
| ٣ | افتح `https://mobiboost.store/` | الصفحة الرئيسية بالمنتجات |
| ٤ | افتح `https://mobiboost.store/admin` وسجّل دخول | لوحة التحكم تشتغل (مفيش "Session expired") |
| ٥ | ارفع صورة منتج جديدة | الصورة تظهر |
| ٦ | دخل `https://mobiboost.store/api/health.php` → **احذف `api/health.php`** بعد ما تطمن | — |

**الاختبار الأهم — الاختبار اللي كان بيفشل قبل كده:**

7. اعمل أي تعديل بسيط في الفرونت إند → `git push origin main`
8. استنى الـ Action يخلّص (⏱️ دقيقة)
9. افتح **File Manager** واتفرّج على `public_html`:
   ```
   ✅ api/ موجود
   ✅ .htaccess موجود
   ✅ uploads/ فيه صورك
   ✅ الملف الجديد ظهر على الموقع
   ```
10. افتح `https://mobiboost.store/admin` — **لازم تكون لسه مسجّل دخول** (الـ jwtSecret ما اتغيرش).

لو كل ده تمام، **المشكلة انتهت نهائيًا**. 🎉

---

## ٦. بعد كده — إزاي بتعمل تحديث؟

```powershell
# 1) عدّل الكود عادي
# 2) ارفع على main بس - مفيش أي حاجة تانية
git add -A
git commit -m "وصف التعديل"
git push origin main
```

وخلاص. GitHub بيبني → Hostinger بيسحب → الموقع بيتحدّث.
**مفيش رفع ZIP، ومفيش استخراج، ومفيش خوف على `api/`.**

> override يدوي طارئ (لو GitHub Actions وقع):
> `.\deploy.ps1 -Publish`

---

## ٧. استكشاف الأخطاء

| الأعراض | السبب المحتمل | الحل |
|---------|----------------|------|
| `"Configuration error: No configuration file found"` | ملف الاعتمادات مش موجود | اتأكد إن `~/mobi-private/config.php` موجود وبالاسم الصح بالظبط |
| `api/` بيختفي بعد كل نشر | Hostinger لسه مربوط بـ `main` مش `deploy` | صفحة Git → اتأكد إن Branch = `deploy` |
| الموقع بيطلع 404 على `/api/...` | الفرع `deploy` ناقص أو فيه غلط | افتح فرع `deploy` على GitHub واتأكد من الملفات |
| الموقع بيفضل على النسخة القديمة | الـ Action فشل | `github.com/.../actions` → شوف اللوج الأحمر |
| "The install directory must contain no files or folders" | `public_html` مش فاضية | نفّذ الخطوة ب-٥ تاني (انقل المحتوى لبره) |
| "Session expired" بعد كل نشر | `jwtSecret` بيتغير | المفروض ميحصلش دلوقتي؛ اتأكد إن `config.php` بره `public_html` |
| الصور مكسورة (404) بالرغم إنها موجودة | `uploadDir` بيشاور على مكان غلط | المفروض ميحصلش؛ شوف `api/_config.php` — بيعتمد على `dirname(__DIR__)` |
| ردود JSON فيها رموز غريبة `﻿` في الأول | BOM في ملف الاعتمادات | أعِد حفظ `config.php` كـ **UTF-8 بدون BOM** |

### أوامر تشخيص سريعة
```
https://mobiboost.store/api/health.php          ← كل حاجة عن السيرفر والاتصال
https://mobiboost.store/api/products.php        ← الـ API شغال؟
https://mobiboost.store/api/settings.php        ← إعدادات المتجر
```

---

## ٨. خطة الطوارئ (لو حصلت مشكلة)

### رجوع سريع من الباك اب
File Manager → `~/backup-2026-10-16` → **Select all** → **Move** → `~/public_html`.

> ⚠️ لو رجّعت الباك اب، فيه `api/config.php` القديم (بالقيم الوهمية). **بعد**
> الرجوع، انسخ القيم الصح من `~/mobi-private/config.php` أو امسح
> `public_html/api/config.php` خالص — الكود هيلاقي اللي في `mobi-private` لوحده.

### إيقاف النشر مؤقتًا
صفحة **Git** → **Disconnect**. الملفات هتفضل زي ما هي على السيرفر.

### الملفات المهمة للمراجعة
| الملف | وظيفته |
|-------|--------|
| `.github/workflows/deploy.yml` | بيبني فرع `deploy` من `main` |
| `api/_config.php` | بيدوّر على الاعتمادات (بره الموقع الأول) |
| `api/config.example.php` | القالب اللي تنسخ منه |
| `deploy.ps1 -Publish` | نشر يدوي بدون GitHub Actions |
| `.gitignore` | اللي مستثنى: `dist/`, `api/config.php`, `uploads/*` |

---

## ٩. قائمة تحقق نهائية

- [ ] فرع `deploy` اتولّد على GitHub وفيه `index.html` + `api/` + `.htaccess`
- [ ] `api/config.php` **مش موجود** في فرع `deploy`
- [ ] `~/mobi-private/config.php` موجود بالقيم الحقيقية
- [ ] Git القديم اتفصل، و`public_html` اتفضّت (بعد الباك اب)
- [ ] Hostinger مربوط بـ **Branch = `deploy`** و Directory = فاضي
- [ ] `uploads/` رجعت مكانها
- [ ] `health.php` بيرجّع `ok: true` و `db.connected: true`
- [ ] Admin login شغال
- [ ] **اختبار النشر الكامل نجح** (خطوة ج-٧)
- [ ] `api/health.php` اتمسح بعد التحقق
- [ ] كلمة سر الأدمن اتغيرت من `admin123`
- [ ] كلمة سر قاعدة البيانات اتغيرت (ظهرت في المحادثة سابقًا)
