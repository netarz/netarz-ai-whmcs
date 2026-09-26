# راهنمای مشارکت

ممنون که می‌خواهید افزونهٔ هوش مصنوعی WHMCS نِت اَرز را بهتر کنید. این‌ها را خوشحال می‌پذیریم:

- رفع اشکال، با یک آزمون که اشکال را نشان می‌دهد
- سازگاری با نسخه یا قالب تازه‌ای از WHMCS
- بهبود ترجمهٔ فارسی یا انگلیسی، یا زبان تازه
- منبع دانش تازه یا امکانی که به کار پشتیبانِ یک شرکت میزبانی می‌آید

پیش از تغییر بزرگ، اول یک [Issue](https://github.com/netarz/netarz-ai-whmcs/issues/new/choose) باز کنید تا دربارهٔ راه‌حل هم‌نظر شویم.

## قاعده‌ها

1. کلید واقعی (`sk-ntz-v1-…`) در کد، README یا تاریخچهٔ گیت نگذارید. اگر اشتباهی کلیدی را کامیت کردید، پیش از هر کاری آن را از
   پنل نِت اَرز باطل کنید؛ پاک کردن کامیت کافی نیست.
2. افزونه باید روی **PHP 7.4** اجرا شود: بدون `match`، ویژگی‌های سازنده (promoted properties)، `readonly`، enum و نوع‌های اجتماع.
   بررسی: `php7.4 -l` روی هر فایلی که عوض کرده‌اید.
3. هر چه به WHMCS مربوط است از `Whmcs` بگذرد و هر چه به API نِت اَرز از `Gateway`. این دو نقطه همان جایی‌اند که آزمون‌ها
   WHMCS و API را شبیه‌سازی می‌کنند.
4. داده‌ای که به مدل می‌رسد را گسترش ندهید مگر آگاهانه: `ClientContext` فهرست بسته‌ای از فیلدها را می‌خواند و رمز، نام کاربری
   سرویس، IP سرور و یادداشت‌ها هرگز به مدل نمی‌رسند. هر فیلد تازه یک آزمون در `KnowledgeTest` لازم دارد.
5. هر متنی که کاربر می‌بیند در `lang/english.php` و `lang/farsi.php` باشد، با کلیدهای یکسان. `LangTest` این را بررسی می‌کند.
   متن فارسی «شما» است، بدون شکلک (emoji)، و نام برند همیشه «نِت اَرز».
6. هر خروجی HTML با `$e()` یا `Text::e()` و در جاوااسکریپت با `textContent` ساخته شود، هرگز با `innerHTML` از متن کاربر یا مدل.
7. کد و توضیح‌های داخل کد انگلیسی باشد.
8. پیش از Pull Request:

   ```bash
   composer install
   composer test                 # آزمون‌های واحد و یکپارچگی
   node tests/e2e/run.cjs        # اگر ویجت یا صفحه‌های مدیریت را عوض کرده‌اید
   ```

   اگر رفتار دستیار را عوض کرده‌اید (پرامپت، دانش، محافظ‌ها)، `tests/eval/run.php` را هم با یک کلید اجرا کنید و نتیجه را در
   توضیح Pull Request بگذارید.
9. اگر رفتار افزونه عوض شده، `Schema::VERSION` و `CHANGELOG.md` را به‌روز کنید. تغییر جدول‌ها فقط افزودنی است و از
   `Schema::upgrade()` می‌گذرد.

## مجوز

با فرستادن Pull Request می‌پذیرید که کد شما با مجوز همین مخزن (MIT) منتشر شود.

---

## Contributing (English)

Thanks for improving NetArz AI for WHMCS. Open an issue before a large change. Never commit a real key (`sk-ntz-v1-…`);
if you did, revoke it in the NetArz panel first.

- Keep **PHP 7.4** compatibility (no `match`, promoted properties, `readonly`, enums or union types) and lint with `php7.4 -l`.
- Go through `Whmcs` for anything WHMCS and `Gateway` for the NetArz API — those are the seams the tests replace.
- Do not widen what reaches the model: `ClientContext` reads a closed list of client fields. A new field needs a test.
- Every user-facing string lives in both `lang/english.php` and `lang/farsi.php` with the same keys (`LangTest` checks).
- Escape all HTML output (`$e()`, `Text::e()`); in JavaScript use `textContent`, never `innerHTML` with user or model text.
- Run `composer test`, and `node tests/e2e/run.cjs` when you touch the widget or the admin screens. If you change the
  assistant's behaviour, run `tests/eval/run.php` with a key and paste the result into the pull request.
- Bump `Schema::VERSION` and `CHANGELOG.md` when behaviour changes; schema changes are additive and go through `Schema::upgrade()`.

By contributing you agree your work is released under the MIT licence.
