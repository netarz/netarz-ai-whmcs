<?php
/**
 * NetArz AI for WHMCS — فارسی.
 * :name در زمان اجرا جایگزین می‌شود.
 * صفحه‌ها و برچسب‌ها نوشتاری‌اند؛ پیام‌هایی که در حباب چت می‌نشینند گفتاریِ مؤدب.
 */

$_ADDONLANG['_language'] = 'farsi';

/* module */
$_ADDONLANG['module_title'] = 'هوش مصنوعی نِت اَرز';
$_ADDONLANG['module_subtitle'] = 'چت آنلاین و پاسخ تیکت با هوش مصنوعی';
$_ADDONLANG['default_agent_name'] = 'دستیار پشتیبانی';
$_ADDONLANG['tab_dashboard'] = 'پیشخوان';
$_ADDONLANG['tab_inbox'] = 'چت آنلاین';
$_ADDONLANG['tab_tickets'] = 'تیکت‌ها';
$_ADDONLANG['tab_knowledge'] = 'منابع دانش';
$_ADDONLANG['tab_settings'] = 'تنظیمات';
$_ADDONLANG['tab_logs'] = 'گزارش';
$_ADDONLANG['foot_docs'] = 'مستندات API';
$_ADDONLANG['foot_panel'] = 'پنل نِت اَرز';

/* setup */
$_ADDONLANG['setup_title'] = 'کلید API نِت اَرز را وصل کنید';
$_ADDONLANG['setup_text'] = 'هزینهٔ هر پاسخ در چت و تیکت از اعتبار هوش مصنوعی شما در نِت اَرز کم می‌شود. کلید دسترسی (API Key) را در پنل نِت اَرز بسازید و در «تنظیمات» بگذارید؛ دستیار از همان لحظه در چت جواب می‌دهد.';
$_ADDONLANG['setup_get_key'] = 'ساخت کلید API';
$_ADDONLANG['setup_open_settings'] = 'رفتن به تنظیمات';

/* dashboard */
$_ADDONLANG['dash_balance'] = 'اعتبار هوش مصنوعی';
$_ADDONLANG['dash_toman'] = 'حدود :amount تومان';
$_ADDONLANG['dash_topup'] = 'افزایش اعتبار';
$_ADDONLANG['dash_no_key'] = 'هنوز کلید API وارد نشده است.';
$_ADDONLANG['dash_today'] = 'هزینهٔ امروز';
$_ADDONLANG['dash_calls'] = ':n درخواست';
$_ADDONLANG['dash_budget_of'] = 'سقف روزانه :cap';
$_ADDONLANG['dash_chats'] = 'چت‌های باز';
$_ADDONLANG['dash_waiting'] = ':n گفتگو منتظر همکار شما';
$_ADDONLANG['dash_open_inbox'] = 'باز کردن صندوق چت';
$_ADDONLANG['dash_tickets'] = 'تیکت‌های این ماه';
$_ADDONLANG['dash_ticket_mode'] = 'حالت تیکت';
$_ADDONLANG['dash_handoffs'] = ':n مورد به همکاران شما سپرده شد';
$_ADDONLANG['dash_chart'] = 'هزینهٔ ۱۴ روز گذشته';
$_ADDONLANG['dash_chart_note'] = 'این هزینه تخمینی است: تعداد توکن ضرب در قیمت عمومی هر مدل. رقم دقیق را در پنل نِت اَرز می‌بینید.';
$_ADDONLANG['dash_recent'] = 'آخرین کارهای تیکت';
$_ADDONLANG['dash_recent_empty'] = 'هنوز چیزی نیست. وقتی حالت تیکت روشن باشد، تیکت‌های تازه این‌جا می‌آیند.';
$_ADDONLANG['dash_project'] = 'پروژه';
$_ADDONLANG['dash_key'] = 'کلید';
$_ADDONLANG['dash_rpm'] = 'درخواست در دقیقه';
$_ADDONLANG['dash_model'] = 'مدل';
$_ADDONLANG['dash_month'] = 'این ماه';
$_ADDONLANG['see_all'] = 'همه';
$_ADDONLANG['refresh'] = 'به‌روزرسانی';

/* inbox */
$_ADDONLANG['inbox_open'] = 'باز';
$_ADDONLANG['inbox_waiting'] = 'منتظر';
$_ADDONLANG['inbox_closed'] = 'بسته';
$_ADDONLANG['inbox_all'] = 'همه';
$_ADDONLANG['inbox_empty'] = 'گفتگویی در این بخش نیست.';
$_ADDONLANG['inbox_pick'] = 'یک گفتگو را از فهرست انتخاب کنید.';
$_ADDONLANG['inbox_takeover'] = 'پاسخ با من';
$_ADDONLANG['inbox_takeover_hint'] = 'هوش مصنوعی دیگر به این بازدیدکننده جواب نمی‌دهد. نوشتن پاسخ هم همین کار را می‌کند.';
$_ADDONLANG['inbox_handback'] = 'برگرداندن به هوش مصنوعی';
$_ADDONLANG['inbox_handback_hint'] = 'هوش مصنوعی دوباره به این بازدیدکننده جواب می‌دهد.';
$_ADDONLANG['inbox_to_ticket'] = 'ساخت تیکت';
$_ADDONLANG['inbox_close'] = 'بستن';
$_ADDONLANG['inbox_delete'] = 'حذف گفتگو';
$_ADDONLANG['inbox_confirm_delete'] = 'این گفتگو برای همیشه حذف شود؟';
$_ADDONLANG['inbox_placeholder'] = 'پاسخ را بنویسید…';
$_ADDONLANG['inbox_composer_hint'] = 'Enter برای فرستادن، Shift+Enter برای خط تازه. وقتی پاسخ بفرستید، گفتگو از هوش مصنوعی به شما می‌رسد.';
$_ADDONLANG['inbox_ticket_done'] = 'تیکت باز شد';
$_ADDONLANG['inbox_handoff_reason'] = 'دلیل ارجاع';
$_ADDONLANG['inbox_online'] = 'الان در صفحه است';
$_ADDONLANG['mode_ai_short'] = 'هوش مصنوعی';
$_ADDONLANG['mode_human_short'] = 'همکار';
$_ADDONLANG['mode_waiting_short'] = 'منتظر';
$_ADDONLANG['visitor'] = 'بازدیدکننده';
$_ADDONLANG['client'] = 'مشتری';
$_ADDONLANG['guest'] = 'مهمان';
$_ADDONLANG['seen'] = 'دیده شد';
$_ADDONLANG['send'] = 'ارسال';
$_ADDONLANG['back'] = 'بازگشت';

/* tickets */
$_ADDONLANG['tickets_title'] = 'هوش مصنوعی در تیکت‌ها';
$_ADDONLANG['tickets_intro'] = 'هوش مصنوعی هر تیکت تازه و هر پاسخ مشتری را در دپارتمان‌های انتخاب‌شده می‌خواند. نتیجهٔ هر کدام را این‌جا می‌بینید.';
$_ADDONLANG['tickets_empty'] = 'هنوز کاری روی تیکت‌ها انجام نشده است.';
$_ADDONLANG['filter_all'] = 'همه';
$_ADDONLANG['col_ticket'] = 'تیکت';
$_ADDONLANG['col_event'] = 'رویداد';
$_ADDONLANG['col_outcome'] = 'نتیجه';
$_ADDONLANG['col_reason'] = 'دلیل';
$_ADDONLANG['col_time'] = 'زمان';
$_ADDONLANG['col_channel'] = 'کانال';
$_ADDONLANG['col_ref'] = 'شناسه';
$_ADDONLANG['col_tokens'] = 'توکن ورودی / خروجی';
$_ADDONLANG['col_cost'] = 'هزینه';
$_ADDONLANG['col_latency'] = 'مدت پاسخ';
$_ADDONLANG['col_result'] = 'نتیجه';
$_ADDONLANG['confidence'] = 'اطمینان';
$_ADDONLANG['view_draft'] = 'پیش‌نویس';
$_ADDONLANG['retry'] = 'دوباره';
$_ADDONLANG['event_open'] = 'تیکت تازه';
$_ADDONLANG['event_reply'] = 'پاسخ مشتری';
$_ADDONLANG['event_manual'] = 'درخواست همکار';
$_ADDONLANG['mode_off'] = 'خاموش';
$_ADDONLANG['mode_off_hint'] = 'هوش مصنوعی تیکت‌ها را نمی‌خواند.';
$_ADDONLANG['mode_draft'] = 'پیش‌نویس برای همکاران';
$_ADDONLANG['mode_draft_hint'] = 'هوش مصنوعی پاسخ را در صفحهٔ تیکت می‌نویسد؛ یکی از همکاران آن را می‌خواند و می‌فرستد.';
$_ADDONLANG['mode_auto'] = 'پاسخ خودکار';
$_ADDONLANG['mode_auto_hint'] = 'پاسخی که اطمینانش به حد تعیین‌شده برسد برای مشتری فرستاده می‌شود. بقیه پیش‌نویس می‌مانند، با یادداشتی برای همکاران.';
$_ADDONLANG['outcome_replied'] = 'پاسخ داده شد';
$_ADDONLANG['outcome_drafted'] = 'پیش‌نویس آماده';
$_ADDONLANG['outcome_handoff'] = 'نیاز به همکار';
$_ADDONLANG['outcome_pending'] = 'در صف';
$_ADDONLANG['outcome_processing'] = 'در حال کار';
$_ADDONLANG['outcome_retry'] = 'دوباره امتحان می‌شود';
$_ADDONLANG['outcome_superseded'] = 'پیام تازه‌تری رسید';
$_ADDONLANG['outcome_staff_replied'] = 'همکار پاسخ داد';
$_ADDONLANG['outcome_paused'] = 'روی این تیکت متوقف است';
$_ADDONLANG['outcome_closed'] = 'تیکت بسته است';
$_ADDONLANG['outcome_already_answered'] = 'پاسخ داده شده بود';
$_ADDONLANG['outcome_empty'] = 'تیکت خالی';
$_ADDONLANG['outcome_ticket_not_found'] = 'تیکت پیدا نشد';
$_ADDONLANG['outcome_low_balance'] = 'اعتبار کم';
$_ADDONLANG['outcome_budget'] = 'سقف روزانه پر شد';
$_ADDONLANG['outcome_missing_api_key'] = 'کلید API وارد نشده';
$_ADDONLANG['outcome_invalid_api_key'] = 'کلید API پذیرفته نشد';
$_ADDONLANG['outcome_insufficient_credit'] = 'اعتبار تمام شده';
$_ADDONLANG['outcome_exception'] = 'خطا';
$_ADDONLANG['outcome_skipped'] = 'کنار گذاشته شد';
$_ADDONLANG['outcome_failed'] = 'ناموفق';
$_ADDONLANG['outcome_done'] = 'انجام شد';
$_ADDONLANG['outcome_network_error'] = 'خطای اتصال';
$_ADDONLANG['outcome_upstream_error'] = 'خطای سرویس‌دهندهٔ مدل';
$_ADDONLANG['outcome_rate_limit_exceeded'] = 'محدودیت تعداد درخواست';

/* ticket page panel */
$_ADDONLANG['tp_title'] = 'پاسخ هوش مصنوعی';
$_ADDONLANG['tp_generate'] = 'نوشتن پیش‌نویس';
$_ADDONLANG['tp_regenerate'] = 'نوشتن دوباره';
$_ADDONLANG['tp_use'] = 'گذاشتن در کادر پاسخ';
$_ADDONLANG['tp_dismiss'] = 'کنار گذاشتن';
$_ADDONLANG['tp_pause'] = 'توقف روی این تیکت';
$_ADDONLANG['tp_resume'] = 'ادامهٔ کار هوش مصنوعی';
$_ADDONLANG['tp_paused_note'] = 'هوش مصنوعی روی این تیکت متوقف است و به آن پاسخ نمی‌دهد.';
$_ADDONLANG['tp_none'] = 'هنوز پیش‌نویسی برای این تیکت نیست.';
$_ADDONLANG['tp_nothing'] = 'هوش مصنوعی پیشنهادی نداشت.';
$_ADDONLANG['tp_working'] = 'تیکت را می‌خواند و پیش‌نویس را می‌نویسد…';
$_ADDONLANG['tp_inserted'] = 'پیش‌نویس به کادر پاسخ رفت. پیش از فرستادن، یک بار آن را بخوانید.';

/* knowledge */
$_ADDONLANG['kn_title'] = 'دستیار از کجا جواب می‌دهد';
$_ADDONLANG['kn_intro'] = 'دستیار برای هر پرسش، مرتبط‌ترین بخش‌های همین منابع را برمی‌دارد و دستور دارد فقط از روی همین‌ها جواب بدهد.';
$_ADDONLANG['kn_src_kb'] = 'پایگاه دانش — :n مقالهٔ عمومی';
$_ADDONLANG['kn_src_ann'] = 'اطلاعیه‌ها — :n اطلاعیهٔ منتشرشده';
$_ADDONLANG['kn_src_products'] = 'محصولات و قیمت‌ها از فرم سفارش';
$_ADDONLANG['kn_src_domains'] = 'قیمت دامنه‌ها';
$_ADDONLANG['kn_src_network'] = 'اختلال‌های باز شبکه';
$_ADDONLANG['kn_src_client'] = 'سرویس‌ها، دامنه‌ها و فاکتورهای پرداخت‌نشدهٔ همان مشتری که وارد حسابش شده';
$_ADDONLANG['kn_custom'] = 'یادداشت شما برای دستیار';
$_ADDONLANG['kn_custom_hint'] = 'قوانین، ساعت کاری، شرایط بازگشت وجه، مشخصات سرورها و جواب پرسش‌های پرتکرار. اگر این متن با منبع دیگری فرق داشت، دستیار حرف این متن را می‌گیرد.';
$_ADDONLANG['kn_custom_placeholder'] = "ساعت پاسخگویی: شنبه تا چهارشنبه، ۹ تا ۱۷.\nبازگشت وجه: هاست اشتراکی تا ۷ روز؛ دامنه بازگشت وجه ندارد.\nنیم‌سرورها (Nameserver): ns1.example.com و ns2.example.com";
$_ADDONLANG['kn_console'] = 'آزمایش دستیار';
$_ADDONLANG['kn_console_intro'] = 'همان چیزی را بپرسید که مشتری‌هایتان می‌پرسند. پاسخ، تصمیم دستیار و هزینه را قبل از مشتری‌ها می‌بینید.';
$_ADDONLANG['kn_console_placeholder'] = 'هزینهٔ یک سال ارزان‌ترین پلن هاست چقدر است؟';
$_ADDONLANG['kn_console_as'] = 'در نقش';
$_ADDONLANG['kn_console_client'] = 'شناسهٔ مشتری';
$_ADDONLANG['kn_channel_chat'] = 'چت آنلاین';
$_ADDONLANG['kn_channel_ticket'] = 'تیکت';
$_ADDONLANG['kn_preview'] = 'دیدن منابع پیداشده';
$_ADDONLANG['kn_ask'] = 'پرسیدن';
$_ADDONLANG['save'] = 'ذخیره';

/* settings */
$_ADDONLANG['s_connection'] = 'اتصال';
$_ADDONLANG['s_voice'] = 'دستیار';
$_ADDONLANG['s_chat'] = 'چت آنلاین';
$_ADDONLANG['s_tickets'] = 'تیکت‌ها';
$_ADDONLANG['s_knowledge'] = 'منابع دانش';
$_ADDONLANG['s_money'] = 'بودجه و داده‌ها';
$_ADDONLANG['save_settings'] = 'ذخیرهٔ تنظیمات';
$_ADDONLANG['settings_saved'] = 'تنظیمات ذخیره شد.';
$_ADDONLANG['f_api_key'] = 'کلید API نِت اَرز';
$_ADDONLANG['f_api_key_hint'] = 'با sk-ntz- شروع می‌شود و رمزنگاری‌شده ذخیره می‌شود. کلید را در پنل نِت اَرز بسازید:';
$_ADDONLANG['f_test'] = 'آزمایش اتصال';
$_ADDONLANG['f_model'] = 'مدل';
$_ADDONLANG['f_model_hint'] = 'هر مدل گفتگوی کاتالوگ نِت اَرز را می‌توانید انتخاب کنید. پیش‌فرض gpt-4o-mini است که برای پشتیبانی کافی است و هزینهٔ کمی دارد.';
$_ADDONLANG['f_base_url'] = 'نشانی API';
$_ADDONLANG['f_base_url_hint'] = 'این نشانی را تغییر ندهید، مگر پشتیبانی نِت اَرز بخواهد.';
$_ADDONLANG['f_temperature'] = 'میزان خلاقیت';
$_ADDONLANG['f_temperature_hint'] = 'همان temperature مدل. عدد کمتر یعنی پاسخ یکدست‌تر؛ برای پشتیبانی ۰٫۲ تا ۰٫۴ مناسب است.';
$_ADDONLANG['f_max_tokens'] = 'بلندترین پاسخ (توکن)';
$_ADDONLANG['f_max_tokens_hint'] = 'بلندی هر پاسخ و در نتیجه سقف هزینه‌اش را تعیین می‌کند.';
$_ADDONLANG['f_agent_name'] = 'نام دستیار';
$_ADDONLANG['f_agent_name_hint'] = 'بالای پنجرهٔ چت و کنار پاسخ‌های هوش مصنوعی دیده می‌شود.';
$_ADDONLANG['f_brand_name'] = 'نام شرکت';
$_ADDONLANG['f_brand_name_hint'] = 'دستیار از طرف این شرکت حرف می‌زند. اگر خالی بماند، نام شرکت در WHMCS به کار می‌رود.';
$_ADDONLANG['f_language'] = 'زبان پاسخ';
$_ADDONLANG['f_language_hint'] = 'در حالت خودکار، دستیار به زبانِ خودِ مشتری جواب می‌دهد.';
$_ADDONLANG['lang_auto'] = 'خودکار';
$_ADDONLANG['lang_fa'] = 'فارسی';
$_ADDONLANG['lang_en'] = 'انگلیسی';
$_ADDONLANG['f_tone'] = 'لحن';
$_ADDONLANG['tone_friendly'] = 'دوستانه';
$_ADDONLANG['tone_formal'] = 'رسمی';
$_ADDONLANG['f_instructions'] = 'دستورهای اضافه';
$_ADDONLANG['f_instructions_hint'] = 'رفتار و سبک دستیار را این‌جا بنویسید. اطلاعات و قیمت‌ها جایشان در بخش «منابع دانش» است.';
$_ADDONLANG['f_instructions_placeholder'] = 'اول پلن سالانه را پیشنهاد کنید. دربارهٔ رقبا حرف نزنید. آخر هر پاسخ شمارهٔ پشتیبانی ما را بنویسید.';
$_ADDONLANG['f_chat_enabled'] = 'نمایش چت';
$_ADDONLANG['f_chat_enabled_hint'] = 'دکمهٔ چت در همهٔ صفحه‌های ناحیهٔ کاربری دیده می‌شود.';
$_ADDONLANG['f_chat_ai'] = 'پاسخ هوش مصنوعی در چت';
$_ADDONLANG['f_chat_ai_hint'] = 'اگر خاموش باشد، چت کار می‌کند و همکاران شما به همهٔ پیام‌ها جواب می‌دهند.';
$_ADDONLANG['f_chat_guests'] = 'چت برای مهمان‌ها';
$_ADDONLANG['f_chat_guests_hint'] = 'مهمان یعنی کسی که وارد حساب نشده. اگر خاموش باشد، فقط مشتری‌های واردشده چت می‌کنند.';
$_ADDONLANG['f_chat_guest_email'] = 'پرسیدن نام و ایمیل از بازدیدکننده';
$_ADDONLANG['f_chat_guest_email_hint'] = 'برای تبدیل چتِ مهمان به تیکت لازم است.';
$_ADDONLANG['f_chat_alerts'] = 'خبر دادن به همکاران در پنل مدیریت';
$_ADDONLANG['f_chat_alerts_hint'] = 'وقتی بازدیدکننده‌ای منتظر همکار است، پیام کوچکی در همهٔ صفحه‌های مدیریت دیده می‌شود.';
$_ADDONLANG['f_chat_credit'] = 'نمایش «هوش مصنوعی از نِت اَرز»';
$_ADDONLANG['f_chat_credit_hint'] = 'یک خط کوچک زیر کادر چت.';
$_ADDONLANG['f_chat_position'] = 'جای دکمه';
$_ADDONLANG['pos_right'] = 'پایین، سمت راست';
$_ADDONLANG['pos_left'] = 'پایین، سمت چپ';
$_ADDONLANG['f_chat_color'] = 'رنگ اصلی';
$_ADDONLANG['f_chat_text_color'] = 'رنگ نوشته روی رنگ اصلی';
$_ADDONLANG['f_chat_burst'] = 'مکث پیش از پاسخ (میلی‌ثانیه)';
$_ADDONLANG['f_chat_burst_hint'] = 'بیشتر مشتری‌ها پیامشان را چندتکه می‌فرستند. هوش مصنوعی این مدت بعد از آخرین پیام صبر می‌کند و به همه با هم جواب می‌دهد.';
$_ADDONLANG['f_chat_wait'] = 'پیشنهاد تیکت بعد از (دقیقه)';
$_ADDONLANG['f_chat_wait_hint'] = 'اگر در این مدت کسی از همکاران به گفتگوی ارجاع‌شده جواب ندهد، به بازدیدکننده پیشنهاد تیکت می‌شود.';
$_ADDONLANG['f_chat_rate'] = 'پیام هر بازدیدکننده در ساعت';
$_ADDONLANG['f_chat_rate_hint'] = 'جلوی پیام‌های رگباری و هزینهٔ بی‌حساب را می‌گیرد.';
$_ADDONLANG['f_chat_len'] = 'بلندترین پیام (نویسه)';
$_ADDONLANG['f_chat_history'] = 'پیام‌هایی که دستیار به خاطر دارد';
$_ADDONLANG['f_chat_history_hint'] = 'چند نوبت آخر گفتگو همراه هر پرسش فرستاده شود. عدد بیشتر، هزینهٔ بیشتر.';
$_ADDONLANG['f_chat_greeting'] = 'پیام خوشامد';
$_ADDONLANG['f_chat_greeting_hint'] = 'اولین پیامی که بازدیدکننده می‌بیند. اگر خالی بماند، متن پیش‌فرض نشان داده می‌شود.';
$_ADDONLANG['f_chat_hide'] = 'پنهان کردن چت در این صفحه‌ها';
$_ADDONLANG['f_chat_hide_hint'] = 'هر خط، بخشی از نشانی یک صفحه؛ مثلاً cart.php?a=checkout';
$_ADDONLANG['f_ticket_admin'] = 'پاسخ به نام';
$_ADDONLANG['f_ticket_admin_hint'] = 'نام این مدیر پای پاسخ‌های هوش مصنوعی می‌آید. پیشنهاد: مدیری با نام «دستیار پشتیبانی» بسازید.';
$_ADDONLANG['f_ticket_status'] = 'وضعیت تیکت بعد از پاسخ هوش مصنوعی';
$_ADDONLANG['status_default'] = 'پیش‌فرض WHMCS';
$_ADDONLANG['f_ticket_conf'] = 'کمترین اطمینان برای ارسال (درصد)';
$_ADDONLANG['f_ticket_conf_hint'] = 'پاسخی که اطمینانش کمتر از این باشد پیش‌نویس می‌ماند.';
$_ADDONLANG['f_ticket_max'] = 'سقف پاسخ هوش مصنوعی در هر تیکت';
$_ADDONLANG['f_ticket_max_hint'] = 'بعد از این تعداد، تیکت به همکاران شما می‌رسد.';
$_ADDONLANG['f_ticket_delay'] = 'مکث پیش از پاسخ (دقیقه)';
$_ADDONLANG['f_ticket_delay_hint'] = 'صفر یعنی همان لحظه. با مکث، پاسخ را cron (کار زمان‌بندی‌شدهٔ WHMCS) می‌فرستد.';
$_ADDONLANG['f_ticket_depts'] = 'دپارتمان‌ها';
$_ADDONLANG['f_ticket_depts_hint'] = 'اگر هیچ‌کدام را انتخاب نکنید، همهٔ دپارتمان‌ها حساب می‌شوند.';
$_ADDONLANG['no_departments'] = 'دپارتمانی پیدا نشد.';
$_ADDONLANG['f_ticket_instant'] = 'پاسخ فوری';
$_ADDONLANG['f_ticket_instant_hint'] = 'پاسخ همان وقتی نوشته می‌شود که مشتری تیکت را می‌فرستد، نه در نوبت بعدی cron.';
$_ADDONLANG['f_ticket_note'] = 'یادداشت وقتی همکار لازم است';
$_ADDONLANG['f_ticket_note_hint'] = 'یک یادداشت داخلی با دلیل ارجاع و پیش‌نویس پاسخ.';
$_ADDONLANG['f_ticket_skip_human'] = 'کنار کشیدن بعد از پاسخ همکار';
$_ADDONLANG['f_ticket_skip_human_hint'] = 'وقتی یکی از همکاران جواب داد، هوش مصنوعی روی آن تیکت فقط پیش‌نویس می‌نویسد.';
$_ADDONLANG['f_ticket_signature'] = 'امضای پاسخ‌های هوش مصنوعی';
$_ADDONLANG['f_ticket_signature_hint'] = 'مثلاً: «این پاسخ را دستیار هوش مصنوعی ما نوشته است. اگر مشکل حل نشد، در همین تیکت بنویسید.»';
$_ADDONLANG['f_kn_kb'] = 'مقاله‌های پایگاه دانش';
$_ADDONLANG['f_kn_ann'] = 'اطلاعیه‌ها';
$_ADDONLANG['f_kn_products'] = 'محصولات و قیمت‌ها';
$_ADDONLANG['f_kn_domains'] = 'قیمت دامنه‌ها';
$_ADDONLANG['f_kn_network'] = 'اختلال‌های شبکه';
$_ADDONLANG['f_kn_client'] = 'حساب خودِ مشتری';
$_ADDONLANG['f_kn_client_hint'] = 'سرویس‌ها، دامنه‌ها و فاکتورهای پرداخت‌نشدهٔ مشتریِ واردشده. رمز عبور، نام کاربری سرویس و IP سرور به هوش مصنوعی فرستاده نمی‌شود.';
$_ADDONLANG['f_kn_custom_where'] = 'یادداشت‌های خودتان را این‌جا ویرایش کنید:';
$_ADDONLANG['f_budget'] = 'سقف هزینهٔ روزانه (دلار)';
$_ADDONLANG['f_budget_hint'] = 'صفر یعنی بدون سقف. وقتی پر شود، هوش مصنوعی تا فردا کنار می‌کشد.';
$_ADDONLANG['f_min_balance'] = 'حداقل اعتبار (دلار)';
$_ADDONLANG['f_min_balance_hint'] = 'اگر اعتبار از این کمتر شود، هوش مصنوعی کنار می‌کشد و همکاران شما جواب می‌دهند.';
$_ADDONLANG['f_retention'] = 'نگه‌داری گفتگوها (روز)';
$_ADDONLANG['f_retention_hint'] = 'cron روزانه چت‌ها و گزارش درخواست‌های قدیمی‌تر از این را پاک می‌کند.';
$_ADDONLANG['f_alerts'] = 'ایمیل هشدار به مدیران';
$_ADDONLANG['f_alerts_hint'] = 'وقتی اعتبار کم شد یا کلید کار نکرد. حداکثر روزی یک بار، برای مدیران WHMCS.';
$_ADDONLANG['danger_title'] = 'پاک کردن همهٔ داده‌های افزونه';
$_ADDONLANG['danger_text'] = 'همهٔ گفتگوها، پیش‌نویس‌ها، گزارش‌ها و تنظیمات این افزونه پاک می‌شود. برای تأیید، DELETE را بنویسید.';
$_ADDONLANG['danger_button'] = 'پاک کردن همه';

/* logs */
$_ADDONLANG['logs_title'] = 'گزارش درخواست‌ها';
$_ADDONLANG['logs_intro'] = '۲۰۰ درخواست آخر به API نِت اَرز. هر درخواست را با همین شناسه در پنل نِت اَرز هم پیدا می‌کنید.';
$_ADDONLANG['logs_empty'] = 'هنوز درخواستی نیست.';
$_ADDONLANG['channel_chat'] = 'چت';
$_ADDONLANG['channel_ticket'] = 'تیکت';
$_ADDONLANG['channel_test'] = 'آزمایش';

/* widget */
$_ADDONLANG['widget_no_key'] = 'کلید API نِت اَرز را وصل کنید تا اعتبار هوش مصنوعی این‌جا دیده شود.';
$_ADDONLANG['widget_calls'] = 'درخواست امروز';
$_ADDONLANG['widget_waiting'] = 'چت منتظر';
$_ADDONLANG['widget_open'] = 'باز کردن هوش مصنوعی نِت اَرز';

/* errors */
$_ADDONLANG['err_no_key'] = 'کلید API نِت اَرز وارد نشده است.';
$_ADDONLANG['err_api_key_format'] = 'این کلید API نِت اَرز نیست. کلید با sk-ntz- شروع می‌شود.';
$_ADDONLANG['err_base_url'] = 'نشانی API باید با https:// شروع شود.';
$_ADDONLANG['err_color'] = 'رنگ را به شکل #ffc700 بنویسید.';
$_ADDONLANG['err_network'] = 'اتصال به API نِت اَرز برقرار نشد. اینترنت و فایروال سرور را بررسی کنید.';
$_ADDONLANG['err_http'] = 'API نِت اَرز این وضعیت را برگرداند:';
$_ADDONLANG['err_budget'] = 'سقف هزینهٔ امروز پر شده است. هوش مصنوعی فردا دوباره شروع می‌کند؛ اگر زودتر لازم دارید، سقف را در «تنظیمات» بالا ببرید.';
$_ADDONLANG['err_low_balance'] = 'اعتبار هوش مصنوعی شما از حداقلی که تعیین کرده‌اید کمتر است. اعتبار را افزایش دهید یا حداقل را کمتر کنید.';
$_ADDONLANG['err_csrf'] = 'نشست شما تمام شده است. صفحه را از نو باز کنید و دوباره امتحان کنید.';
$_ADDONLANG['err_empty_question'] = 'اول یک پرسش بنویسید.';
$_ADDONLANG['err_generic'] = 'مشکلی پیش آمد. دوباره امتحان کنید.';

/* alerts */
$_ADDONLANG['alert_subject'] = 'هوش مصنوعی نِت اَرز: اعتبار شما را بررسی کنید';
$_ADDONLANG['alert_low_body'] = "اعتبار هوش مصنوعی شما در نِت اَرز :balance است. اگر از :min کمتر شود، دستیار دیگر جواب نمی‌دهد و پیام‌ها به همکاران شما می‌رسد.\n\nافزایش اعتبار: :url";
$_ADDONLANG['alert_broken_body'] = "API نِت اَرز کلید را نپذیرفت: :error\n\nکلید را در پنل نِت اَرز بررسی کنید: :url";
$_ADDONLANG['alert_toast_title'] = 'بازدیدکننده‌ای منتظر است';
$_ADDONLANG['alert_toast_open'] = 'باز کردن چت';

/* notes written into WHMCS */
$_ADDONLANG['note_handoff'] = "هوش مصنوعی نِت اَرز: این تیکت را یکی از همکاران جواب دهد.\nدلیل: :reason (اطمینان :confidence٪)";
$_ADDONLANG['note_draft_follows'] = 'پاسخ پیشنهادی:';
$_ADDONLANG['attachment_note'] = 'پیوست';

/* chat, server side — sits in a chat bubble, so polite spoken Persian */
$_ADDONLANG['chat_greeting_default'] = 'سلام، به :brand خوش اومدید. سؤالتون رو بپرسید.';
$_ADDONLANG['chat_handoff_default'] = 'پیامتون رو به همکارم دادم؛ همین‌جا جوابتون رو می‌ده. لطفاً این پنجره رو باز نگه دارید.';
$_ADDONLANG['chat_redirect_default'] = 'من دربارهٔ سرویس‌ها، سفارش‌ها و حساب کاربری شما در :brand کمک می‌کنم. در همین موارد سؤالی دارید؟';
$_ADDONLANG['secret_warning'] = 'رمزی که فرستادید امن نیست. لطفاً همین حالا عوضش کنید و هیچ رمزی در چت یا تیکت نفرستید.';
$_ADDONLANG['chat_you'] = 'بازدیدکننده';
$_ADDONLANG['chat_staff'] = 'پشتیبانی';
$_ADDONLANG['chat_ticket_subject'] = 'چت آنلاین — :name';
$_ADDONLANG['chat_ticket_intro'] = 'این تیکت از چت آنلاین باز شده است. متن گفتگو تا این لحظه:';
$_ADDONLANG['chat_ticket_created'] = 'تیکت #:tid باز شد. جوابتون رو همون‌جا می‌دیم و با ایمیل هم خبرتون می‌کنیم.';
$_ADDONLANG['chat_closed_note'] = 'این گفتگو بسته شد. هر وقت خواستید، دوباره پیام بدید.';

/* chat widget (sent to the browser) — labels are written Persian */
$_ADDONLANG['w_title'] = 'چت پشتیبانی';
$_ADDONLANG['w_open'] = 'باز کردن چت پشتیبانی';
$_ADDONLANG['w_minimize'] = 'کوچک کردن';
$_ADDONLANG['w_placeholder'] = 'پیامتان را بنویسید…';
$_ADDONLANG['w_send'] = 'ارسال';
$_ADDONLANG['w_sent'] = 'ارسال شد';
$_ADDONLANG['w_seen'] = 'دیده شد';
$_ADDONLANG['w_status_ai'] = 'دستیار هوش مصنوعی';
$_ADDONLANG['w_status_team'] = 'همکاران ما همین‌جا جواب می‌دهند';
$_ADDONLANG['w_status_waiting'] = 'منتظر یکی از همکاران';
$_ADDONLANG['w_status_human'] = 'آنلاین';
$_ADDONLANG['w_identity_lead'] = 'تا اگر صفحه را بستید هم بتوانیم جوابتان را بدهیم:';
$_ADDONLANG['w_name'] = 'نام شما';
$_ADDONLANG['w_email'] = 'ایمیل شما';
$_ADDONLANG['w_start'] = 'شروع گفتگو';
$_ADDONLANG['w_identity_invalid'] = 'نام و ایمیلتان را درست وارد کنید.';
$_ADDONLANG['w_login_required'] = 'برای گفتگو با ما، اول وارد حساب کاربری‌تان شوید.';
$_ADDONLANG['w_login'] = 'ورود به حساب';
$_ADDONLANG['w_offer_ticket'] = 'الان همکاران ما آزاد نیستند. گفتگو را به تیکت تبدیل کنید تا جوابتان را در تیکت بدهیم و با ایمیل خبرتان کنیم.';
$_ADDONLANG['w_make_ticket'] = 'ساخت تیکت';
$_ADDONLANG['w_view_ticket'] = 'دیدن تیکت';
$_ADDONLANG['w_credit'] = 'هوش مصنوعی از نِت اَرز';
$_ADDONLANG['w_err_generic'] = 'مشکلی پیش آمد. دوباره امتحان کنید.';
$_ADDONLANG['w_err_too_long'] = 'این پیام بلندتر از حد مجاز است. کوتاه‌ترش کنید یا در چند پیام بفرستید.';
$_ADDONLANG['w_err_empty'] = 'اول چیزی بنویسید.';
$_ADDONLANG['w_err_rate_limited'] = 'پیام‌های زیادی فرستاده‌اید. چند دقیقه صبر کنید.';
$_ADDONLANG['w_err_duplicate'] = 'این پیام قبلاً فرستاده شده است.';
$_ADDONLANG['w_err_no_thread'] = 'این گفتگو تمام شده است. برای شروع دوباره پیام تازه‌ای بفرستید.';
$_ADDONLANG['w_err_identity_required'] = 'نام و ایمیلتان را وارد کنید.';
$_ADDONLANG['w_err_invalid_email'] = 'این ایمیل درست به نظر نمی‌رسد.';
$_ADDONLANG['w_err_login_required'] = 'برای گفتگو با ما وارد حساب کاربری‌تان شوید.';
$_ADDONLANG['w_err_disabled'] = 'چت فعلاً در دسترس نیست.';
$_ADDONLANG['w_err_forbidden'] = 'این درخواست پذیرفته نشد. صفحه را دوباره باز کنید.';
$_ADDONLANG['w_err_unknown_action'] = 'مشکلی پیش آمد. دوباره امتحان کنید.';
$_ADDONLANG['w_err_no_department'] = 'فعلاً نمی‌شود از این‌جا تیکت ساخت.';
$_ADDONLANG['w_err_email_required'] = 'برای ساخت تیکت، ایمیلتان لازم است.';
$_ADDONLANG['w_err_ticket_failed'] = 'تیکت ساخته نشد. دوباره امتحان کنید.';

/* why a reply stayed a draft */
$_ADDONLANG['reason_draft_mode'] = 'حالت پیش‌نویس: همکاران پاسخ را می‌فرستند';
$_ADDONLANG['reason_low_confidence'] = 'اطمینان برای ارسال کافی نبود';
$_ADDONLANG['reason_staff_on_ticket'] = 'یکی از همکاران روی این تیکت است';
$_ADDONLANG['reason_auto_reply_cap'] = 'سقف پاسخ هوش مصنوعی در این تیکت پر شد';
$_ADDONLANG['reason_no_admin_user'] = 'مدیری برای ارسال پاسخ انتخاب نشده است';
$_ADDONLANG['reason_post_failed'] = 'WHMCS پاسخ را نپذیرفت';
$_ADDONLANG['reason_handoff'] = 'نیاز به همکار';
$_ADDONLANG['reason_malformed_output'] = 'مدل در قالب درست جواب نداد';
$_ADDONLANG['reason_empty_reply'] = 'مدل جواب خالی داد';
$_ADDONLANG['reason_model_chose_silence'] = 'مدل حرفی برای گفتن نداشت';
