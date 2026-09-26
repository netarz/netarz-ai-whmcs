<?php

namespace NetArz\WhmcsAi;

/** Unicode-safe text helpers. Never trims or cuts Persian text byte-wise. */
final class Text
{
    private const STOPWORDS = [
        // fa
        'و', 'در', 'به', 'از', 'که', 'این', 'را', 'با', 'است', 'برای', 'آن', 'یک', 'تا', 'می', 'هم', 'یا', 'اما', 'اگر',
        'من', 'ما', 'شما', 'چه', 'چی', 'چطور', 'چگونه', 'کنم', 'کنید', 'دارم', 'دارید', 'هست', 'هستم', 'بود', 'شد', 'شده',
        'سلام', 'لطفا', 'ممنون', 'مرسی', 'خیلی', 'باید', 'نمی', 'روی', 'بر', 'هر', 'ها', 'های', 'ای', 'کن', 'رو',
        'ام', 'ات', 'اش', 'مان', 'تان', 'شان', 'تون', 'شون', 'مون', 'چیه', 'خوام', 'میخوام', 'کنه', 'چنده', 'هستن', 'بشه',
        // en
        'the', 'a', 'an', 'and', 'or', 'to', 'of', 'in', 'on', 'for', 'is', 'are', 'was', 'be', 'it', 'i', 'my', 'me', 'you',
        'your', 'we', 'our', 'do', 'does', 'how', 'what', 'can', 'with', 'this', 'that', 'please', 'hi', 'hello', 'thanks',
    ];

    /** Plain text from HTML/markdown-ish input, with whitespace collapsed. */
    public static function plain(string $html): string
    {
        $text = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr)\b[^>]*>#iu', "\n", $html);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', (string) $text);
        $text = preg_replace("/\n\s*\n\s*\n+/u", "\n\n", (string) $text);

        return trim((string) $text);
    }

    /** Strip control characters (keeps newlines, tabs and the ZWNJ Persian needs). */
    public static function clean(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        $text = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text);

        return trim((string) $text);
    }

    public static function limit(string $text, int $chars): string
    {
        if (mb_strlen($text, 'UTF-8') <= $chars) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $chars - 1, 'UTF-8'), " \n\t").'…';
    }

    /** Normalised form for matching: Arabic → Persian letters, no diacritics, Latin digits, lower case. */
    public static function normalize(string $text): string
    {
        $text = str_replace(
            ['ي', 'ك', 'ة', 'ۀ', 'أ', 'إ', 'ٱ', "\u{200C}", "\u{200F}", "\u{200E}"],
            ['ی', 'ک', 'ه', 'ه', 'ا', 'ا', 'ا', ' ', '', ''],
            $text
        );
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $text);
        $text = strtr((string) $text, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return mb_strtolower((string) $text, 'UTF-8');
    }

    /** @return string[] distinct search terms (2+ chars, no stop words). */
    public static function terms(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}\.\-]+/u', self::normalize($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part, '.-');
            if (mb_strlen($part, 'UTF-8') < 2 || in_array($part, self::STOPWORDS, true)) {
                continue;
            }
            $out[$part] = true;
        }

        return array_keys($out);
    }

    /**
     * Hosting words customers type in Persian, with the words the knowledgebase
     * is usually written in. A Persian question must find an English article.
     */
    private const SYNONYMS = [
        'نیم سرور' => ['nameserver', 'nameservers', 'dns'], 'نیمسرور' => ['nameserver', 'nameservers', 'dns'],
        'دی ان اس' => ['dns'], 'دامنه' => ['domain'], 'دامین' => ['domain'], 'دومین' => ['domain'],
        'هاست' => ['hosting', 'host'], 'میزبانی' => ['hosting'], 'سرور مجازی' => ['vps'], 'سرور اختصاصی' => ['dedicated'],
        'ایمیل' => ['email', 'mail'], 'رمز' => ['password'], 'پسورد' => ['password'], 'گواهی' => ['ssl', 'certificate'],
        'اس اس ال' => ['ssl'], 'سی پنل' => ['cpanel'], 'سیپنل' => ['cpanel'], 'دایرکت ادمین' => ['directadmin'], 'پلسک' => ['plesk'],
        'فاکتور' => ['invoice'], 'صورتحساب' => ['invoice'], 'پرداخت' => ['payment', 'pay'], 'تمدید' => ['renew', 'renewal'],
        'انتقال' => ['transfer', 'migration'], 'بکاپ' => ['backup'], 'پشتیبان' => ['backup'], 'وردپرس' => ['wordpress'],
        'ثبت' => ['register', 'registration'], 'کنسل' => ['cancel', 'cancellation'], 'لغو' => ['cancel', 'cancellation'],
        'بازگشت وجه' => ['refund'], 'قیمت' => ['price', 'pricing'], 'هزینه' => ['price', 'cost'], 'پهنای باند' => ['bandwidth'],
        'فضا' => ['disk', 'storage'], 'دیتابیس' => ['database', 'mysql'], 'پایگاه داده' => ['database', 'mysql'],
        'ساب دامین' => ['subdomain'], 'زیردامنه' => ['subdomain'], 'اف تی پی' => ['ftp'], 'قطع' => ['down', 'outage'],
        'کند' => ['slow', 'speed'], 'سرعت' => ['speed'], 'خطا' => ['error'], 'ارور' => ['error'], 'ریدایرکت' => ['redirect'],
    ];

    /** Persian endings that stick to a word: سرورهاتون → سرور, دامنه‌ام → دامنه. */
    private const SUFFIXES = ['هایتان', 'هایشان', 'هاتون', 'هاشون', 'هامون', 'هایی', 'های', 'تون', 'شون', 'مون', 'ها', 'ام', 'ات', 'اش'];

    /**
     * Search terms plus their English equivalents and Persian stems.
     *
     * @return string[]
     */
    public static function searchTerms(string $text): array
    {
        $terms = self::terms($text);
        $norm = ' '.preg_replace('/\s+/u', ' ', self::normalize($text)).' ';

        foreach (self::SYNONYMS as $fa => $en) {
            if (mb_strpos($norm, $fa) !== false) {
                foreach ($en as $word) {
                    $terms[] = $word;
                }
            }
        }

        foreach ($terms as $term) {
            if (! preg_match('/^[\x{0600}-\x{06FF}]+$/u', $term)) {
                continue;
            }
            foreach (self::SUFFIXES as $suffix) {
                $len = mb_strlen($suffix);
                if (mb_strlen($term) > $len + 2 && mb_substr($term, -$len) === $suffix) {
                    $terms[] = mb_substr($term, 0, -$len);
                    break;
                }
            }
        }

        return array_values(array_unique($terms));
    }

    /** Is most of this text written in Persian/Arabic script? */
    public static function isRtl(string $text): bool
    {
        $rtl = preg_match_all('/[\x{0600}-\x{06FF}]/u', $text);
        $ltr = preg_match_all('/[A-Za-z]/', $text);

        return $rtl > 0 && $rtl >= $ltr;
    }

    public static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** A random hex token. */
    public static function token(int $bytes = 24): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
