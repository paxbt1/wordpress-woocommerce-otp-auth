=== Woocom SMS Auth ===
Contributors: woocom
Tags: sms, otp, login, register, woocommerce, iran, payamito
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.5.3
License: GPLv2 or later

ورود و عضویت پیامکی وردپرس/ووکامرس با هسته چندسرویس‌دهنده پیامک ایرانی، جیبیت، صفحه ورود اختصاصی، رابط کاربری پیشرفته و ارسال تست.

== Description ==
- صفحه ورود اختصاصی بدون هدر و فوتر قالب
- شورتکد [smsauth_form]
- جایگزینی فرم ورود ووکامرس در صورت فعال بودن WooCommerce
- فیلد نام، نام خانوادگی، موبایل و کد ملی
- تطبیق کد ملی و موبایل با جیبیت
- Provider چندگانه پیامک با فرم تنظیمات پویا
- ارسال تست پیامک از پنل تنظیمات
- لاگ پیشرفته پاسخ وب‌سرویس‌های پیامک
- پشتیبانی از پیامیتو در دو حالت OTP و الگو/پترن SendByBaseNumber2

== Changelog ==
= 1.5.3 =
* Added aurora OTP loading border, smooth success collapse-to-check animation, and red error feedback.

= 1.4.2 =
* Rebuilt advanced auth UI: split OTP inputs, auto verify, countdown/resend, clearer notifications.
* Limited OTP length to 4-6 and added Payamito numeric BodyId validation.
* Simplified admin logs summary.

= 1.4.1 =
* Added Payamito pattern/BaseNumber2 mode with BodyId and semicolon-separated pattern params.
* Payamito pattern mode uses SendByBaseNumber2 URL endpoint to avoid SoapClient/WSDL upstream issues.

= 1.4.0 =
* Diagnostic logs and request-id filtered test output.

= 1.3.8 =
* Admin Ajax test SMS always returns JSON with HTTP 200 to avoid hosting/proxy HTML error pages on 4xx/5xx.

= 1.3.6 =
* Reimplemented Payamito based on official OTP documentation.

= 1.3.3 =
* Added Payamito provider.

= 1.4.3 =
* Auth UX refinements: link-style change/resend, centered loading timer, registration session retry flow, admin notice suppression.

= 1.5.4 =
* Fix logged-in WooCommerce My Account dashboard being replaced by login message.
* Keep Aurora OTP UI.
