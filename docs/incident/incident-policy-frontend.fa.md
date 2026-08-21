# 🚑 مدیریت رخداد (Incident) — راهنمای پیاده‌سازی فرانت‌اند

این راهنما هر چیزی که فرانت‌اند برای ساخت صفحات **Incident** لازم دارد را پوشش می‌دهد: فهرست و تاریخچه‌ی رخدادها، ساخت و ویرایش دستی، تأیید (acknowledge) به‌تفکیک تیم، رفع (resolve) و همچنین جریان کار **Incident Policy** با YAML.

---

## 🔹 چه چیزی الان موجود است و چه چیزی نیست

| بخش | وضعیت |
| --- | --- |
| فهرست و تاریخچه‌ی رخدادها، فیلترها، صفحه‌بندی | ✅ موجود |
| ساخت دستی، ویرایش، حذف | ✅ موجود |
| تأیید به‌تفکیک تیم، رفع | ✅ موجود |
| سیاست رخداد: اعمال از YAML، اعتبارسنجی، خروجی، فهرست، حذف | ✅ موجود |
| Postmortem / RCA | ⛔ هنوز نه — مقدار `postMortem` همیشه `null` است |
| تایم‌لاین رویدادها، پیوست‌ها، اقدامات پیگیری | ⛔ هنوز نه — اندپوینتی وجود ندارد |
| موتور سیاست (ساخت خودکار رخداد از هشدار) | ⛔ هنوز نه — سیاست‌ها ذخیره می‌شوند اما خودکار اعمال نمی‌شوند |

دو نتیجه برای رابط کاربری: فعلاً تب postmortem را روی `postMortem` نسازید، و همه‌ی رخدادها مقدار `source: "manual"` دارند، چون تا پیاده‌سازی موتور سیاست، هیچ چیزی رخداد با `source: "policy"` نمی‌سازد.

---

## 🔹 مفاهیم

یک **رخداد (incident)** یک اختلال پیگیری‌شده است با یک سطح شدت (`SEV1` تا `SEV4`)، یک وضعیت، یک یا چند **تیم** مسئول، و به‌صورت اختیاری ارجاع به **alert rule**‌هایی که آن را نمایان کرده‌اند.

تأیید (acknowledge) **به‌تفکیک تیم** است، نه برای کل رخداد. وقتی چند تیم مسئول‌اند، هر تیم جداگانه تأیید می‌کند و رخداد ثبت می‌کند که چه کسی به‌نمایندگی از چه تیمی و در چه زمانی تأیید کرده است. به همین دلیل شیء رخداد هم آرایه‌ی مسطح `acknowledgements` را دارد و هم نمای تیمی زیر `teams[]`.

یک **incident policy** شیء جداگانه و قابل استفاده‌ی مجدد است که توصیف می‌کند رخدادهای هر سطح شدت *چگونه* باید مدیریت شوند — مهلت تأیید و رفع، اطلاع‌رسانی به چه کسانی، کدام on-call plan ارجاع می‌دهد، آیا postmortem الزامی است و کدام runbook‌ها اعمال می‌شوند. سیاست‌ها با YAML نوشته می‌شوند.

### چرخه‌ی وضعیت

```
open ──(اولین تأیید)──► investigating ──(رفع)──► resolved
  └──────────────────(رفع)──────────────────────────┘
```

مقدار `status` **مستقیماً قابل نوشتن نیست** و فقط به‌عنوان یک اثر جانبی تغییر می‌کند:

- اولین تأیید، `open` را به `investigating` می‌برد
- رفع، هر وضعیتی را به `resolved` می‌برد
- ساخت یا ویرایش همراه با مقدار `resolvedAt` هم نتیجه‌اش `resolved` است

---

## 🔹 احراز هویت و دسترسی‌ها

همه‌ی اندپوینت‌ها زیر `/api/v1` هستند و همان توکن JWT برنامه را می‌خواهند:

```
Authorization: Bearer <token>
```

| عملیات | چه کسی |
| --- | --- |
| فهرست و مشاهده‌ی رخداد | ادمین، سازنده، یا عضو هر یک از تیم‌های مسئول |
| ساخت رخداد | ادمین، یا عضو **همه‌ی** تیم‌های موجود در `teamIds` |
| ویرایش و حذف رخداد | ادمین، سازنده، یا **مالک** یکی از تیم‌های مسئول |
| تأیید | عضو تیمی که هنوز تأیید نکرده، و رخداد رفع نشده باشد |
| رفع | هر کسی که دسترسی مشاهده دارد، و رخداد رفع نشده باشد |
| سیاست: فهرست، مشاهده، خروجی | ادمین، کاربر import‌کننده، یا عضو یکی از تیم‌های سیاست |
| سیاست: import، validate، delete | فقط نقش **owner** یا **manager** |

پاسخ‌ها: `401` بدون احراز هویت، `403` عدم دسترسی، `404` شناسه‌ی ناموجود، `422` خطای اعتبارسنجی.

**دسترسی‌ها را در سمت کلاینت محاسبه نکنید.** هر شیء رخداد چهار مقدار `canEdit`، `canDelete`، `canAcknowledge` و `canResolve` را دارد که از قبل برای کاربر جاری ارزیابی شده‌اند. دکمه‌ها را مستقیم به همین‌ها ببندید. دقت کنید `canAcknowledge` هم وقتی کاربر دسترسی ندارد `false` است و هم وقتی همه‌ی تیم‌هایش قبلاً تأیید کرده‌اند؛ پس دکمه‌ی غیرفعال بهتر است tooltip داشته باشد که کدام حالت است — برای تفکیک از `teams[].acknowledgement` استفاده کنید.

---

# بخش ۱ — رخدادها

| متد | مسیر | کاربرد |
| --- | --- | --- |
| `GET` | `/api/v1/incident` | فهرست تاریخچه، صفحه‌بندی و فیلترشده |
| `GET` | `/api/v1/incident/{id}` | یک رخداد |
| `POST` | `/api/v1/incident` | ساخت دستی |
| `PUT` | `/api/v1/incident/{id}` | ویرایش |
| `DELETE` | `/api/v1/incident/{id}` | حذف |
| `POST` | `/api/v1/incident/{id}/acknowledge` | تأیید برای یک یا همه‌ی تیم‌های کاربر |
| `POST` | `/api/v1/incident/{id}/resolve` | رفع |

مقدار `{id}` همیشه یک شناسه‌ی MongoDB با ۲۴ کاراکتر hex است.

## 🔸 فهرست تاریخچه

`GET /api/v1/incident`

همه‌ی پارامترهای query اختیاری هستند:

| پارامتر | نوع | توضیح |
| --- | --- | --- |
| `page` | integer | پیش‌فرض ۱ |
| `perPage` | integer | پیش‌فرض ۲۵، حداکثر ۱۰۰ |
| `status` | string | `open`، `investigating`، `resolved` |
| `severity` | string | `SEV1` تا `SEV4` |
| `teamId` | string | رخدادهای مربوط به آن تیم |
| `tag` | string | تطابق دقیق تگ، نه جزئی |
| `search` | string | **تطابق جزئی روی عنوان**، بدون حساسیت به بزرگی و کوچکی حروف، حداکثر ۲۵۵ کاراکتر |

نتایج همیشه بر اساس **`startedAt` نزولی** مرتب می‌شوند، پس همین فهرست نقش تاریخچه را هم دارد — برای آرشیو بسته‌شده‌ها `status=resolved` بدهید و برای همه چیز، `status` را نفرستید. پارامتر مرتب‌سازی سمت سرور وجود ندارد، پس سرستون‌های قابل مرتب‌سازی پیشنهاد نکنید.

کاربران غیر‌ادمین به‌صورت خودکار فقط رخدادهایی را می‌بینند که خودشان ساخته‌اند یا به یکی از تیم‌هایشان مربوط است؛ فیلتر سمت کلاینت لازم نیست.

```json
{
  "current_page": 1,
  "data": [ { "id": "...", "title": "Checkout latency spike", "...": "شیء رخداد در ادامه" } ],
  "last_page": 4,
  "per_page": 25,
  "total": 87
}
```

کلیدهای صفحه‌بندی (`current_page`, `last_page`, `per_page`, `total`) مثل بقیه‌ی فهرست‌ها در ریشه هستند و snake_case‌اند. فیلدهای خود رخداد camelCase هستند.

## 🔸 مشاهده

`GET /api/v1/incident/{id}` مقدار `{ "data": { ...incident } }` را برمی‌گرداند.

## 🔸 ساخت دستی

`POST /api/v1/incident` → کد `201` و بدنه‌ی `{ "data": { ...incident } }`.

| فیلد | نوع | الزامی | قواعد و رفتار |
| --- | --- | --- | --- |
| `title` | string | ✅ | حداکثر ۲۵۵ کاراکتر |
| `severity` | string | ✅ | `SEV1` تا `SEV4` |
| `teamIds` | string[] | ✅ | حداقل یکی. کاربر باید عضو **همه‌ی** آن‌ها باشد، وگرنه `403` |
| `description` | string | — | پیش‌فرض `""` |
| `tags` | string[] | — | trim می‌شود، تکراری‌ها و خالی‌ها حذف می‌شوند |
| `startedAt` | date-time | — | زمان شروع. **پیش‌فرض: اکنون** |
| `detectedAt` | date-time | — | زمان تشخیص. **پیش‌فرض: اکنون** |
| `resolvedAt` | date-time | — | اگر `startedAt` هم فرستاده شود، باید ≥ آن باشد |
| `alertRuleIds` | string[] | — | اتصال رخداد به alert rule‌های موجود |

سرور مقادیر `source: "manual"`، `createdBy` (کاربر جاری)، `acknowledgements: []` و `status: "open"` را خودش تنظیم می‌کند.

**ساخت رخدادِ از قبل رفع‌شده** پشتیبانی می‌شود و راه ثبت تاریخچه‌ی گذشته است: `resolvedAt` را بفرستید و رخداد با `status: "resolved"` و `resolvedBy` برابر کاربر جاری ساخته می‌شود. پس فرم «ثبت رخداد گذشته» همان فرم ساخت است با زمان رفع پرشده.

نمونه:

```json
{
  "title": "Checkout latency spike",
  "description": "p99 above 4s for 20 minutes",
  "severity": "SEV2",
  "teamIds": ["66a1c0de5f1a2b3c4d5e6f70"],
  "tags": ["payments", "latency"],
  "startedAt": "2026-08-20T04:10:00Z",
  "detectedAt": "2026-08-20T04:14:00Z",
  "alertRuleIds": ["66a1c0de5f1a2b3c4d5e6f71"]
}
```

خطاهای اعتبارسنجی با قالب استاندارد Laravel برمی‌گردند:

```json
{
  "message": "The title field is required.",
  "errors": { "title": ["The title field is required."], "severity": ["The severity field is required."] }
}
```

## 🔸 ویرایش

`PUT /api/v1/incident/{id}` → کد `200` و بدنه‌ی `{ "data": { ...incident } }`.

بدنه دقیقاً مثل ساخت است. **این یک جایگزینی کامل است، نه patch** — هر فیلدی را که می‌خواهید حفظ شود، بفرستید:

- نفرستادن `description` آن را به `""` برمی‌گرداند
- نفرستادن `tags` یا `alertRuleIds` آن‌ها را پاک می‌کند
- نفرستادن `startedAt` یا `detectedAt` مقدار ذخیره‌شده را نگه می‌دارد
- نفرستادن کامل `resolvedAt` مقدار قبلی را نگه می‌دارد؛ فرستادن آن با مقدار `null` پاکش می‌کند

پس فرم ویرایش باید از شیء فعلی پر شود و کامل ارسال شود. ارسال بدنه‌ی ناقص، بی‌صدا فیلدها را پاک می‌کند.

دو قاعده‌ی دیگر: دادن `resolvedAt` به رخدادی که هنوز رفع نشده، آن را `resolved` می‌کند و کاربر جاری را `resolvedBy` ثبت می‌کند؛ و چون تیم‌های مسئول می‌توانند تغییر کنند، کاربر باید عضو همه‌ی تیم‌های **جدید** در `teamIds` باشد، وگرنه `403`.

## 🔸 تأیید (Acknowledge)

`POST /api/v1/incident/{id}/acknowledge` → کد `200` و بدنه‌ی `{ "data": { ...incident } }`.

| فیلد | نوع | رفتار |
| --- | --- | --- |
| `teamId` | string \| null | اختیاری. تأیید به‌نمایندگی از همان یک تیم |

بدنه را کامل حذف کنید تا برای **همه‌ی** تیم‌های کاربر که هنوز تأیید نکرده‌اند تأیید ثبت شود. با فرستادن `teamId` فقط برای همان یک تیم ثبت می‌شود — این حالت وقتی مفید است که کاربر عضو دو تیم مسئول است و باید فقط از طرف یکی پاسخ دهد.

اولین تأیید روی رخدادِ `open` آن را به `investigating` می‌برد. تأییدهای بعدی تیم‌های دیگر، وضعیت را دوباره تغییر نمی‌دهند.

کد `403` برمی‌گردد وقتی رخداد قبلاً رفع شده، کاربر عضو تیم درخواستی نیست، یا آن تیم قبلاً تأیید کرده است. قبل از نمایش دکمه، `canAcknowledge` را بررسی کنید.

## 🔸 رفع (Resolve)

`POST /api/v1/incident/{id}/resolve` → کد `200` و بدنه‌ی `{ "data": { ...incident } }`.

| فیلد | نوع | رفتار |
| --- | --- | --- |
| `resolvedAt` | date-time \| null | اختیاری، **پیش‌فرض: اکنون** |

مقدار `status: "resolved"` و `resolvedBy` (کاربر جاری) تنظیم می‌شود. یک رخداد می‌تواند مستقیم از `open` رفع شود، بدون آن‌که هرگز تأیید شده باشد. رفع کردن رخدادی که قبلاً رفع شده `403` می‌دهد، پس دکمه را به `canResolve` مشروط کنید.

فیلد زمان اختیاری را در رابط کاربری ارائه دهید — اپراتورها معمولاً مدتی بعد از بازگشت واقعی سرویس رخداد را رفع می‌کنند و مقدار درست `resolvedAt` همان چیزی است که گزارش‌های مدت‌زمان را معنادار می‌کند.

## 🔸 حذف

`DELETE /api/v1/incident/{id}` → مقدار `{ "status": true }`. توجه کنید این پاسخ داخل `data` **پیچیده نشده** است.

## 🔸 شیء رخداد

| فیلد | نوع | توضیح |
| --- | --- | --- |
| `id` | string | شناسه ۲۴ کاراکتری hex |
| `title` | string | |
| `description` | string | ممکن است خالی باشد |
| `severity` | string | `SEV1` تا `SEV4` |
| `status` | string | `open`، `investigating`، `resolved` — فقط‌خواندنی |
| `source` | string | `manual` یا `policy`؛ فعلاً همیشه `manual` |
| `startedAt` | date-time | زمان شروع رخداد |
| `detectedAt` | date-time | زمان تشخیص |
| `resolvedAt` | date-time \| null | |
| `teamIds` | string[] | |
| `tags` | string[] | |
| `alertRuleIds` | string[] | |
| `createdBy` | string | شناسه کاربر |
| `createdByUser` | `{id, name}` | در فهرست و مشاهده موجود است |
| `resolvedBy` | string \| null | شناسه کاربر |
| `acknowledgements` | `{teamId, acknowledgedBy, acknowledgedAt}[]` | ردپای مسطح، به ترتیب تأیید |
| `teams` | در ادامه | جزئیات تیم‌ها از قبل resolve شده، برای نمایش همین را استفاده کنید |
| `alertRules` | `{id, name}[]` | نام‌ها از قبل resolve شده، درخواست اضافه لازم نیست |
| `postMortem` | `null` | جای‌نگهدار، در این فاز همیشه null |
| `canEdit` / `canDelete` / `canAcknowledge` / `canResolve` | boolean | پرچم‌های دسترسی برای کاربر جاری |
| `createdAt` / `updatedAt` | date-time | زمان‌های رکورد، متفاوت از `startedAt` |

مقدار `teams` هر چیزی که برای پنل تأیید تیمی لازم است را دارد:

```json
[
  {
    "id": "66a1c0de5f1a2b3c4d5e6f70",
    "name": "payments",
    "onCallPlan": { "id": "66a1...", "name": "payments-primary" },
    "acknowledgement": { "acknowledgedBy": "66a2...", "acknowledgedAt": "2026-08-20T04:20:11Z" }
  },
  {
    "id": "66a1c0de5f1a2b3c4d5e6f80",
    "name": "platform",
    "onCallPlan": null,
    "acknowledgement": null
  }
]
```

مقدار `acknowledgement: null` یعنی آن تیم هنوز پاسخ نداده، و `onCallPlan` وقتی `null` است که تیم plan تنظیم‌شده ندارد. فهرست تیم‌ها را با نشان «تأییدشده / در انتظار» در هر سطر نمایش دهید؛ این واضح‌ترین نشانه‌ی این است که آیا واقعاً کسی روی رخداد کار می‌کند یا نه.

مقادیر `acknowledgedBy` و `resolvedBy` **شناسه‌ی کاربر بدون نام** هستند، برخلاف `createdByUser`. اگر نام لازم دارید، از اعضای تیم یا اندپوینت‌های کاربر که همین حالا استفاده می‌کنید آن را resolve کنید.

---

# بخش ۲ — سیاست رخداد (Incident Policy)

سیاست‌ها **فقط با اعمال یک تعریف YAML ساخته و به‌روزرسانی می‌شوند**. اندپوینت JSON برای ساخت یا ویرایش وجود ندارد، پس رابط کاربری به ویرایشگر YAML و آپلود فایل نیاز دارد، نه فرم فیلد‌به‌فیلد. فهرست، مشاهده، خروجی و حذف، اندپوینت‌های معمول JSON هستند.

هر سیاست با `metadata.name` شناسایی می‌شود که یک **slug** و یکتاست. اعمال دوباره‌ی همان نام، همان سیاست را به‌روزرسانی می‌کند و نسخه‌ی دومی نمی‌سازد؛ پس import **idempotent** است. هر تغییر، `version` را یک واحد بالا می‌برد.

| متد | مسیر | کاربرد |
| --- | --- | --- |
| `GET` | `/api/v1/incident-policy` | فهرست صفحه‌بندی‌شده |
| `GET` | `/api/v1/incident-policy/{id}` | یک سیاست |
| `GET` | `/api/v1/incident-policy/{id}/export` | دانلود به‌صورت YAML |
| `POST` | `/api/v1/incident-policy/import` | اعمال تعریف (فایل یا رشته) |
| `POST` | `/api/v1/incident-policy/validate` | بررسی تعریف، بدون نوشتن |
| `DELETE` | `/api/v1/incident-policy/{id}` | حذف |

## 🔸 فهرست و مشاهده

`GET /api/v1/incident-policy` پارامترهای `page`، `perPage` (پیش‌فرض ۲۵، حداکثر ۱۰۰)، `enabled` (بولی)، `teamId` و `search` (روی نام سیاست) را می‌پذیرد. قالب صفحه‌بندی مثل رخدادها است. `GET /api/v1/incident-policy/{id}` مقدار `{ "data": { ...policy } }` را برمی‌گرداند.

## 🔸 Import

`POST /api/v1/incident-policy/import`

دو قالب درخواست پذیرفته می‌شود؛ هرکدام که با رابط کاربری فعلی هم‌خوان است.

**آپلود فایل** (`multipart/form-data`):

| فیلد | نوع | قواعد |
| --- | --- | --- |
| `file` | file | پسوند `.yaml` یا `.yml`، حداکثر **۵۱۲ کیلوبایت** |
| `dryRun` | boolean | اختیاری، پیش‌فرض `false` |

**رشته‌ی خام** (`application/json`):

| فیلد | نوع | قواعد |
| --- | --- | --- |
| `yaml` | string | حداکثر ۲۶۲۱۴۴ کاراکتر |
| `dryRun` | boolean | اختیاری، پیش‌فرض `false` |

دقیقاً یکی از `file` یا `yaml` الزامی است. یک درخواست می‌تواند **چند سیاست** جدا‌شده با `---` داشته باشد.

پاسخ موفق (`200`):

```json
{
  "valid": true,
  "dryRun": false,
  "created":   [ { "name": "payments-critical", "id": "66c3...", "version": 1 } ],
  "updated":   [ { "name": "search-tier2",      "id": "66c4...", "version": 4 } ],
  "unchanged": [ { "name": "billing-default",   "id": "66c5...", "version": 2 } ],
  "errors": []
}
```

این را به‌صورت خلاصه‌ی تغییرات نشان دهید. `unchanged` یعنی تعریف با آنچه ذخیره شده یکسان است — این موفقیت است نه هشدار، و نسخه به‌صورت عمدی بالا نرفته.

**تا وقتی همه‌ی سندهای ورودی معتبر نباشند، هیچ چیزی نوشته نمی‌شود.** فایلی با سه سیاست که سومی ارجاع نامعتبر دارد، هیچ‌یک را اعمال نمی‌کند؛ پس می‌توان import را یک عملیات «همه یا هیچ» نشان داد.

## 🔸 Validate

`POST /api/v1/incident-policy/validate` همان بدنه را می‌گیرد و همان بررسی‌ها را بدون نوشتن انجام می‌دهد؛ `dryRun` همیشه `true` برمی‌گردد. این اندپوینت پشت دکمه‌ی «بررسی تعریف» است. فراخوانی `import` با `dryRun: true` هم معادل آن است؛ تفاوت فقط در خوانایی کد شماست.

## 🔸 Export

`GET /api/v1/incident-policy/{id}/export` خروجی **YAML خام است، نه JSON**:

```
Content-Type: application/x-yaml
Content-Disposition: attachment; filename="payments-critical.yaml"
```

شناسه‌ها دوباره به نام تبدیل می‌شوند تا خروجی قابل مرور و قابل کامیت در ریپازیتوری باشد. اگر خروجی export را مستقیم به import بدهید، نتیجه `unchanged` است.

```ts
const response = await fetch(`/api/v1/incident-policy/${id}/export`, {
  headers: { Authorization: `Bearer ${token}` },
});
const blob = await response.blob();
const url = URL.createObjectURL(blob);
const link = Object.assign(document.createElement('a'), { href: url, download: `${policy.name}.yaml` });
link.click();
URL.revokeObjectURL(url);
```

## 🔸 حذف

`DELETE /api/v1/incident-policy/{id}` مقدار `{ "status": true }` را برمی‌گرداند.

## 🔸 دو قالب متفاوت برای خطای 422 — قبل از نوشتن error handler بخوانید

هر دو کد HTTP `422` دارند و **یکی نیستند**.

**۱. خطای سطح درخواست** (فایل ارسال نشده، پسوند اشتباه، حجم زیاد) — قالب استاندارد Laravel:

```json
{
  "message": "Upload a YAML file or send the definition in the yaml field.",
  "errors": {
    "file": ["Upload a YAML file or send the definition in the yaml field."],
    "yaml": ["Upload a YAML file or send the definition in the yaml field."]
  }
}
```

**۲. خطای سطح DSL** (فایل درست رسیده، اما محتوایش اشکال دارد) — قالب نتیجه‌ی import:

```json
{
  "valid": false,
  "dryRun": false,
  "created": [],
  "updated": [],
  "unchanged": [],
  "errors": [
    { "path": "spec.rules[0].notify.channels[1]", "message": "Endpoint 'oncall-sms' not found." },
    { "path": "spec.rules[1].resolve.withinMinutes", "message": "Must be greater than or equal to ack.withinMinutes." }
  ]
}
```

برای تفکیک، وجود کلید `valid` را بررسی کنید:

```ts
if (response.status === 422) {
  const body = await response.json();
  if ('valid' in body) {
    showDefinitionErrors(body.errors);   // آرایه‌ای از { path, message }
  } else {
    showFormErrors(body.errors);         // نقشه‌ی فیلد به پیام‌های Laravel
  }
}
```

مقدار `path` یک **مسیر در سند است، نه شماره‌ی خط** — `spec.rules[0].notify.channels[1]` یعنی کانال دوم از قاعده‌ی اول. این‌ها را به‌صورت فهرست قابل کلیک کنار ویرایشگر نشان دهید. برای پرش به خط، مسیر را در سمت کلاینت روی YAML پارس‌شده حل کنید (مثلاً با AST کتابخانه‌ی `yaml` و مقادیر `range` آن)؛ API شماره‌ی خط برنمی‌گرداند. پیام‌ها انگلیسی و آماده‌ی نمایش هستند.

## 🔸 شیء سیاست

| فیلد | نوع | توضیح |
| --- | --- | --- |
| `id` | string | شناسه ۲۴ کاراکتری hex |
| `name` | string | slug، یکتا، کلید شناسایی در import |
| `description` | string | ممکن است خالی باشد |
| `enabled` | boolean | سیاست غیرفعال ذخیره می‌شود ولی اثری ندارد |
| `version` | integer | از ۱ شروع و با هر import تغییردهنده +۱ |
| `source` | string | `yaml` یا `api`؛ فعلاً همیشه `yaml` |
| `ownerId` | string \| null | شناسه کاربر |
| `teamIds` | string[] | |
| `teams` | `{id, name}[]` | از قبل resolve شده |
| `match` | object | این‌که سیاست کدام هشدارها را پوشش می‌دهد |
| `grouping` | object | `{ key: string[], windowMinutes: number }` |
| `incident` | object | نحوه‌ی باز شدن رخداد |
| `rules` | object | کلید‌گذاری‌شده با شدت: `{ "SEV1": {...}, "SEV3": {...} }` |
| `createdBy` | string | شناسه کاربر |
| `createdByUser` | `{id, name}` | در فهرست و مشاهده موجود است |
| `updatedBy` | string \| null | آخرین کاربر import‌کننده |
| `canEdit` / `canDelete` | boolean | برای وضعیت دکمه‌ها |
| `createdAt` / `updatedAt` | date-time | |

```json
{
  "match": {
    "alertRuleIds": ["66a1..."],
    "tags": ["payments", "tier-1"],
    "serviceIds": ["66a2..."],
    "dataSourceTypes": ["prometheus", "grafana"]
  },
  "incident": {
    "autoCreate": true,
    "autoResolveOnAlertClear": false,
    "titleTemplate": "{{ alert.name }} on {{ service.name }}",
    "defaultSeverity": "SEV3",
    "severityMap": { "critical": "SEV1", "warning": "SEV3" }
  }
}
```

یک ورودی از `rules` — تعهدهای پاسخ‌گویی برای یک سطح شدت:

```json
{
  "ackWithinMinutes": 5,
  "resolveWithinMinutes": 60,
  "requireCommander": true,
  "notifyEndpointIds": ["66b1..."],
  "escalation": { "onCallPlanId": "66b2...", "useLayers": true },
  "communication": { "stakeholderUpdateEveryMinutes": 30, "statusPageUpdateRequired": true },
  "postmortem": { "required": true, "dueDays": 5, "reviewRequired": true },
  "runbookNames": ["payments-api-5xx-triage"]
}
```

دو نکته برای نمایش یک سیاست:

- مقدار `rules` یک **map با کلید شدت** است، نه آرایه، و ممکن است سیاست فقط بعضی سطوح را پوشش دهد. از `SEV1` تا `SEV4` پیمایش کنید و کلیدهای ناموجود را رد کنید؛ فرض «همیشه چهار ورودی» درست نیست.
- مقدار `runbookNames` فقط نام است، نه شناسه. runbook هنوز منبع ذخیره‌شده نیست، پس به‌صورت متن نمایش دهید و لینک نسازید.

## 🔸 زبان توصیف (DSL) در YAML

این را به‌عنوان الگوی شروع ویرایشگر قرار دهید. فقط `apiVersion`، `kind`، `metadata.name`، `metadata.teams`، `spec.match` و `spec.rules` الزامی هستند و بقیه پیش‌فرض دارند.

```yaml
apiVersion: skylogs.io/v1
kind: IncidentPolicy
metadata:
  name: payments-critical          # slug، یکتا، کلید upsert
  description: Response policy for payment-path incidents
  owner: user:jsmith               # اختیاری
  teams: [payments, platform]      # نام یا شناسه ۲۴ کاراکتری
spec:
  enabled: true

  match:                           # حداقل یک matcher الزامی است
    alertRules: [payments-api-5xx, payments-latency-p99]
    tags: [payments, tier-1]
    services: [checkout-api]
    dataSourceTypes: [prometheus, grafana]

  grouping:                        # طوفان هشدار به یک رخداد تبدیل می‌شود
    key: [serviceId, alertRuleId]
    windowMinutes: 15

  incident:
    autoCreate: true
    autoResolveOnAlertClear: false
    titleTemplate: "{{ alert.name }} on {{ service.name }}"
    defaultSeverity: SEV3
    severityMap:
      critical: SEV1
      warning: SEV3

  rules:
    - severity: SEV1
      ack:      { withinMinutes: 5 }
      resolve:  { withinMinutes: 60 }
      requireCommander: true
      notify:
        channels: [endpoint:oncall-sms, endpoint:payments-telegram]
      escalation:
        onCallPlan: payments-primary
        useLayers: true
      communication:
        stakeholderUpdateEveryMinutes: 30
        statusPageUpdateRequired: true
      postmortem:
        required: true
        dueDays: 5
        reviewRequired: true
      runbooks: [payments-api-5xx-triage, database-failover]

    - severity: SEV3
      ack:      { withinMinutes: 30 }
      resolve:  { withinMinutes: 480 }
      postmortem: { required: false }
```

### ارجاع‌ها (References)

تیم‌ها، alert rule‌ها، سرویس‌ها، کانال‌های اطلاع‌رسانی، on-call plan‌ها و owner با **نام قابل خواندن** نوشته می‌شوند، نه شناسه، تا تعریف در یک pull request قابل مرور باشد. سه شکل پذیرفته می‌شود:

- نام ساده — `payments`
- جفت `kind:name` — `endpoint:oncall-sms`، `onCallPlan:payments-primary`، `user:jsmith`
- شناسه ۲۴ کاراکتری hex، مفید برای وقتی نام مبهم است

اگر دو رکورد نام یکسان داشته باشند، API خطای «ambiguous» می‌دهد و شناسه می‌خواهد. این پیام ارزش نمایش عیناً دارد.

### محدودیت‌هایی که در ویرایشگر هم باید اعمال شود

| فیلد | قاعده |
| --- | --- |
| `apiVersion` | باید دقیقاً `skylogs.io/v1` باشد |
| `kind` | باید دقیقاً `IncidentPolicy` باشد |
| `metadata.name` | الگوی `^[a-z0-9][a-z0-9-]*$`، حداکثر ۱۲۰ کاراکتر |
| `metadata.description` | حداکثر ۱۰۰۰ کاراکتر |
| `metadata.teams` | حداقل یک مورد |
| `spec.match` | حداقل یکی از `alertRules`، `tags`، `services`، `dataSourceTypes` |
| `spec.grouping.key` | از میان `serviceId`، `alertRuleId`، `tag`، `dataSourceType` |
| `spec.grouping.windowMinutes` | بین ۱ تا ۱۴۴۰، پیش‌فرض ۱۵ |
| `spec.match.dataSourceTypes` | `prometheus`، `sentry`، `grafana`، `pmm`، `zabbix`، `elastic` |
| `spec.incident.defaultSeverity` | `SEV1` تا `SEV4`، پیش‌فرض `SEV3` |
| `spec.incident.titleTemplate` | حداکثر ۲۵۵ کاراکتر |
| `spec.rules` | حداقل یک قاعده، یکی برای هر شدت، بدون تکرار |
| `ack.withinMinutes` و `resolve.withinMinutes` | بین ۱ تا ۱۰۰۸۰؛ resolve باید ≥ ack باشد |
| `communication.stakeholderUpdateEveryMinutes` | بین ۱ تا ۱۴۴۰ |
| `postmortem.dueDays` | بین ۱ تا ۳۶۵، با `required: true` پیش‌فرض ۵ |

فیلدهای ناشناخته **رد می‌شوند**، نه نادیده گرفته. غلط تایپی `requred: true` به‌جای `required: true` با پیام `Unknown field 'requred'.` خطا می‌دهد و postmortem را بی‌صدا غیرفعال نمی‌کند؛ پس این خطاها را نویز حساب نکنید، مفیدترین خطاها همین‌ها هستند.

---

## 🔹 رابط کاربری پیشنهادی

### صفحه‌ی تاریخچه‌ی رخدادها

جدولی مرتب‌شده از جدید به قدیم: نشان شدت، عنوان، نشان وضعیت، تیم‌ها، `startedAt` و برای سطرهای بسته‌شده، مدت زمان تا رفع. نوار فیلتر برای وضعیت، شدت، تیم، تگ و جست‌وجوی عنوان؛ همه یک‌به‌یک به پارامترهای query نگاشت می‌شوند. صفحه‌بندی از `meta`. اقدام اصلی: **ثبت رخداد**.

چون پارامتر مرتب‌سازی وجود ندارد، ترتیب را ثابت نگه دارید و آن را اطلاع دهید («جدیدترین اول») به‌جای سرستون‌های قابل مرتب‌سازی که کار نمی‌کنند.

### فرم ثبت و ویرایش رخداد

یک فرم برای هر دو. فیلدها: عنوان، انتخاب شدت، انتخاب چند‌تایی تیم، توضیح، ورودی تگ‌ها، `startedAt`، `detectedAt`، `resolvedAt` اختیاری و انتخاب‌گر alert rule.

سه رفتار که بهتر است صریح مدیریت شوند:

- خالی گذاشتن `startedAt` و `detectedAt` مشکلی ندارد — سرور زمان جاری را ثبت می‌کند. این را به‌صورت placeholder نشان دهید، نه با پرکردن پیش‌فرض که کاربر مجبور به اصلاحش شود.
- پرکردن `resolvedAt` در فرم ساخت، یک رخداد بسته‌شده ثبت می‌کند. این راه مورد نظر برای ثبت تاریخچه‌ی گذشته است، پس یک کلید «این رخداد تمام شده» بگذارید که این فیلد را نمایان کند.
- فرم ویرایش باید از شیء موجود پر شود و کامل ارسال شود، چون `PUT` جایگزین می‌کند نه patch.

### صفحه‌ی جزئیات رخداد

هدر با شدت، وضعیت، عنوان و دکمه‌های عملیات که به `canAcknowledge`، `canResolve`، `canEdit` و `canDelete` بسته شده‌اند. زیر آن، یک خلاصه‌ی شبیه تایم‌لاین از داده‌های موجود امروز: `startedAt`، `detectedAt`، هر ورودی `acknowledgements` و `resolvedAt`. یک پنل **تیم‌ها** با فهرست هر تیم مسئول، on-call plan آن و نشان «تأییدشده / در انتظار»، به‌همراه اقدام تأیید برای تیم‌هایی که کاربر جاری عضوشان است. یک پنل **alert rule‌های متصل** از `alertRules`. برای تب‌های postmortem و تایم‌لاین کامل جا بگذارید، اما فعلاً نسازید.

برای اقدام تأیید: اگر کاربر عضو دقیقاً یک تیم مسئول است، بدنه نفرستید. اگر عضو چند تیم است، انتخاب‌گر تیم بگذارید و `teamId` بفرستید.

### فهرست سیاست‌ها و import

جدول سیاست: نام، تیم‌ها، سطوح شدت پوشش‌داده‌شده، `enabled`، `version` و آخرین به‌روزرسانی. اقدام اصلی «Import from YAML» برای owner/manager، و در هر سطر خروجی و حذف.

کشوی import دو تب دارد — *آپلود فایل* (کشیدن‌و‌رها کردن `.yaml`/`.yml` و بررسی محدودیت ۵۱۲ کیلوبایت سمت کلاینت) و *چسباندن یا ویرایش YAML* (ویرایشگر Monaco یا CodeMirror در حالت YAML، از قبل با قالب بالا پرشده). هر دو یک فوتر مشترک دارند: **بررسی تعریف** به `/validate` و **اعمال** به `/import`:

۱. با **بررسی**، در صورت خطا فهرست `{ path, message }` را نشان دهید و در صورت موفقیت، پیش‌نمایش تغییرات created/updated/unchanged را.
۲. با **اعمال**، همچنان `422` را مدیریت کنید — ممکن است بین دو فراخوانی یک تیم یا endpoint حذف شده باشد.
۳. در موفقیت، خلاصه را نشان دهید («۱ ساخته، ۱ به‌روزرسانی، ۱ بدون تغییر») و فهرست را تازه‌سازی کنید.

### جزئیات سیاست

نمایش فقط‌خواندنی: هدر با نام، `enabled`، `version` و تیم‌ها؛ سپس بخش‌های match و grouping؛ سپس یک کارت برای هر سطح شدت شامل مهلت ack و resolve، کانال‌های اطلاع‌رسانی، on-call plan، الزام postmortem و نام runbook‌ها. یک دکمه‌ی **دانلود YAML** به اندپوینت export و یک اقدام **ویرایش** که YAML خروجی را در ویرایشگر بار می‌کند — مسیر ویرایش مورد نظر در این فاز همین است.

---

## 🔹 نکات ریز مهم

**رخدادها**

- متد `PUT` جایگزینی کامل است. بدنه‌ی ناقص، بی‌صدا `description`، `tags` و `alertRuleIds` را پاک می‌کند.
- مقدار `status` مستقیم قابل نوشتن نیست. از acknowledge و resolve استفاده کنید، یا `resolvedAt` را تنظیم کنید.
- فیلتر `tag` تطابق دقیق است، در حالی که `search` تطابق جزئی و فقط روی عنوان است — توضیحات و تگ‌ها را جست‌وجو نمی‌کند.
- تأیید به‌تفکیک تیم است و تأیید دوم توسط همان تیم `403` می‌دهد.
- پاسخ `DELETE` رخداد و `DELETE` سیاست یک `{ "status": true }` ساده است، بدون پوشش `data`، برخلاف بقیه‌ی پاسخ‌های رخداد.
- مقادیر `createdAt`/`updatedAt` زمان‌های رکورد هستند و با `startedAt`/`detectedAt` یکی نیستند. زمان‌های رخداد را نمایش دهید، نه زمان رکورد را.
- مقادیر `acknowledgedBy` و `resolvedBy` شناسه‌ی خام کاربر هستند؛ فقط `createdByUser` با نام می‌آید.

**سیاست‌ها**

- نام‌گذاری فیلدها همه‌جا camelCase است؛ فقط کلیدهای صفحه‌بندی (`current_page`, `last_page`, `per_page`, `total`) snake_case هستند و در ریشه قرار دارند، نه داخل `meta`.
- مقدار `unchanged` موفقیت است، نه شکست بی‌اثر.
- در dry run مقدار `created[].id` برابر `null` است، پس از نتیجه‌ی dry run به صفحه‌ی جزئیات لینک ندهید.
- export متن برمی‌گرداند نه JSON — کلاینتی که پاسخ را JSON پارس می‌کند خطا می‌دهد.
- فایل چند‌سندی، مسیرهای خطا را با اندیس سند پیشوند می‌دهد، مثل `documents[1].metadata.name`. با یک سیاست، پیشوندی نیست.
- پوشش ناقص سطوح شدت عمدی است؛ سیاستی با فقط قاعده‌ی SEV1 معتبر است.
