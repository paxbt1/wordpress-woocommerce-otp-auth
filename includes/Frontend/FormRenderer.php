<?php
namespace WCSA\Frontend;

defined('ABSPATH') || exit;

use WCSA\Options;

final class FormRenderer {
    public function render(): string {
        if (is_user_logged_in()) {
            return '<div class="kt-card wcsa-already"><div class="kt-card-body">شما وارد حساب کاربری شده‌اید.</div></div>';
        }

        $otpLength = max(4, min(6, (int) Options::get('otp_length', 5)));

        ob_start();
        ?>
        <div class="wcsa-shell kt-auth-shell" dir="rtl" data-wcsa-otp-length="<?php echo esc_attr((string)$otpLength); ?>">
            <section class="kt-card kt-auth-card wcsa-card" aria-label="فرم ورود و عضویت پیامکی">
                <div class="kt-auth-brand">
                    <?php echo $this->logo(); ?>
                </div>

                <div class="kt-auth-heading">
                    <div class="kt-auth-chip">ورود امن پیامکی</div>
                    <h1 class="kt-auth-title wcsa-title"><?php echo esc_html((string) Options::get('ui_title', 'ورود / عضویت')); ?></h1>
                    <p class="kt-auth-subtitle wcsa-subtitle"><?php echo esc_html((string) Options::get('ui_subtitle', 'برای ادامه، شماره موبایل خود را وارد کنید.')); ?></p>
                </div>

                <div class="kt-stepper wcsa-stepper" aria-hidden="true">
                    <span class="is-active" data-wcsa-step-dot="mobile"></span>
                    <span data-wcsa-step-dot="otp"></span>
                    <span data-wcsa-step-dot="register"></span>
                </div>

                <div class="kt-alert wcsa-alert wcsa-hidden" data-wcsa-alert role="alert"></div>

                <div class="wcsa-step" data-wcsa-step="mobile">
                    <label class="kt-form-label wcsa-label">شماره موبایل</label>
                    <div class="kt-input-group wcsa-phone wcsa-phone-clean">
                        <input type="tel" inputmode="numeric" maxlength="11" placeholder="09121234567" data-wcsa-mobile autocomplete="tel">
                    </div>
                    <button class="kt-btn kt-btn-primary wcsa-btn wcsa-btn-primary wcsa-send-btn" type="button" data-wcsa-send>ارسال کد تایید</button>
                </div>

                <div class="wcsa-step wcsa-hidden" data-wcsa-step="otp">
                    <label class="kt-form-label wcsa-label wcsa-center-label">کد تایید</label>
                    <div class="wcsa-otp-stage" data-wcsa-otp-stage>
                        <div class="wcsa-otp-boxes" data-wcsa-otp-boxes aria-label="کد تایید"></div>
                        <div class="wcsa-otp-check" data-wcsa-otp-check aria-hidden="true">
                            <svg viewBox="0 0 52 52" focusable="false"><path d="M14 27.5L23 36L39 17"></path></svg>
                        </div>
                    </div>
                    <input type="hidden" data-wcsa-otp autocomplete="one-time-code">

                    <div class="wcsa-otp-meta">
                        <span class="wcsa-otp-mobile" data-wcsa-otp-mobile></span>
                        <a href="#" class="wcsa-link wcsa-inline-link" data-wcsa-back>تغییر شماره</a>
                    </div>
                    <div class="wcsa-otp-timer" data-wcsa-timer></div>
                </div>

                <div class="wcsa-step wcsa-hidden" data-wcsa-step="register">
                    <div class="kt-alert wcsa-alert is-ok wcsa-register-note">کد تایید شد. اطلاعات حساب را تکمیل کنید.</div>
                    <div class="kt-grid wcsa-grid">
                        <div>
                            <label class="kt-form-label wcsa-label">نام</label>
                            <input class="kt-input wcsa-input" type="text" data-wcsa-first autocomplete="given-name">
                        </div>
                        <div>
                            <label class="kt-form-label wcsa-label">نام خانوادگی</label>
                            <input class="kt-input wcsa-input" type="text" data-wcsa-last autocomplete="family-name">
                        </div>
                    </div>
                    <label class="kt-form-label wcsa-label">کد ملی</label>
                    <input class="kt-input wcsa-input wcsa-ltr" type="text" inputmode="numeric" maxlength="10" data-wcsa-national autocomplete="off">
                    <button class="kt-btn kt-btn-primary wcsa-btn wcsa-btn-primary" type="button" data-wcsa-register>ثبت‌نام و ورود</button>
                    <p class="kt-form-hint wcsa-note">برای امنیت حساب، مالکیت شماره همراه با کد ملی بررسی می‌شود.</p>
                </div>
            </section>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function logo(): string {
        $id = absint(Options::get('logo_id', 0));
        $url = $id ? (wp_get_attachment_image_url($id, 'medium') ?: '') : '';
        if (!$url) {
            $url = esc_url((string) Options::get('logo_url', ''));
        }

        if ($url) {
            return '<div class="wcsa-logo kt-auth-logo"><img src="' . esc_url($url) . '" alt="' . esc_attr(get_bloginfo('name')) . '"></div>';
        }

        return '<div class="wcsa-logo wcsa-logo-text kt-auth-logo-text">' . esc_html(get_bloginfo('name')) . '</div>';
    }
}
