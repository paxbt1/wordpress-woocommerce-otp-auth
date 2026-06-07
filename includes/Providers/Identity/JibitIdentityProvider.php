<?php
namespace WCSA\Providers\Identity;

defined('ABSPATH') || exit;

use WCSA\Options;
use WCSA\Support\Helpers;
use WCSA\Support\Logger;

final class JibitIdentityProvider implements IdentityProviderInterface {
    public function id(): string { return 'jibit'; }
    public function title(): string { return 'جیبیت'; }

    public function verifyOwner(string $m, string $nc): array {
        $base = trim((string)Options::get('jibit_base_url','https://napi.jibit.ir/ide')) ?: 'https://napi.jibit.ir/ide';
        $apiKey = trim((string)Options::get('jibit_api_key',''));
        $secretKey = trim((string)Options::get('jibit_secret_key',''));
        if (!$base || !$apiKey || !$secretKey) return ['ok'=>false,'matched'=>null,'error'=>'تنظیمات جیبیت کامل نیست. API Key و Secret Key را وارد کنید.','error_code'=>'config_missing'];
        $token = $this->accessToken($base, $apiKey, $secretKey);
        if (!$token) return ['ok'=>false,'matched'=>null,'error'=>'دریافت توکن جیبیت ناموفق بود. API Key و Secret Key را بررسی کنید.','error_code'=>'token_failed'];

        $mobile = Helpers::mobileNational($m);
        $ck = 'wcsa_identity_'.md5($mobile.'|'.$nc);
        $cm = max(0,(int)Options::get('cache_identity_minutes',10));
        if ($cm && is_array($c=get_transient($ck)) && array_key_exists('matched',$c)) return ['ok'=>true,'matched'=>(bool)$c['matched'],'error'=>null,'error_code'=>null];

        $url = rtrim($base,'/').'/v1/services/matching?nationalCode='.rawurlencode($nc).'&mobileNumber='.rawurlencode($mobile);
        $r = wp_remote_get($url, ['timeout'=>12,'redirection'=>0,'headers'=>['Authorization'=>'Bearer '.$token,'Accept'=>'application/json']]);
        if (is_wp_error($r)) return ['ok'=>false,'matched'=>null,'error'=>'ارتباط با سرویس تطبیق برقرار نشد.','error_code'=>'network'];
        $http = (int)wp_remote_retrieve_response_code($r); $j = json_decode(wp_remote_retrieve_body($r), true);
        if ($http>=200 && $http<300 && is_array($j) && array_key_exists('matched',$j)) {
            $matched = (bool)$j['matched'];
            if ($cm) set_transient($ck, ['matched'=>$matched], $cm*MINUTE_IN_SECONDS);
            Logger::add('info','Jibit matching completed',['mobile'=>$m,'national_code'=>$nc]);
            return ['ok'=>true,'matched'=>$matched,'error'=>null,'error_code'=>null];
        }
        $err = is_array($j) ? ($j['error'] ?? $j['code'] ?? null) : null;
        $msg = 'خطای سرویس تطبیق.';
        if ($http===403 || $err==='forbidden') $msg='توکن یا سطح دسترسی جیبیت نامعتبر است.';
        if ($http===429 || $err==='daily_limit.reached') $msg='سقف استعلام روزانه جیبیت تکمیل شده است.';
        if ($err==='nationalCode.not_valid') $msg='کد ملی معتبر نیست.';
        if ($err==='mobileNumber.not_valid') $msg='شماره موبایل معتبر نیست.';
        if ($err==='providers.not_available') $msg='سرویس تطبیق موقتاً در دسترس نیست.';
        Logger::add('error','Jibit matching failed',['http'=>$http,'error'=>(string)$err]);
        return ['ok'=>false,'matched'=>null,'error'=>$msg,'error_code'=>$err ?: 'http_'.$http];
    }

    private function accessToken(string $base, string $apiKey, string $secretKey): string {
        $cacheKey = 'wcsa_jibit_access_token_'.md5($base.'|'.$apiKey);
        $cached = get_transient($cacheKey);
        if (is_string($cached) && $cached !== '') return $cached;
        $candidates = [rtrim($base,'/').'/v1/tokens/generate'];
        if (strpos($base, '/ide') !== false) $candidates[] = rtrim(str_replace('/ide','',$base),'/').'/ide/v1/tokens/generate';
        foreach (array_unique($candidates) as $url) {
            $r = wp_remote_post($url, ['timeout'=>12,'headers'=>['Content-Type'=>'application/json','Accept'=>'application/json'], 'body'=>wp_json_encode(['apiKey'=>$apiKey,'secretKey'=>$secretKey])]);
            if (is_wp_error($r)) continue;
            $http = (int)wp_remote_retrieve_response_code($r);
            $j = json_decode(wp_remote_retrieve_body($r), true);
            if ($http>=200 && $http<300 && is_array($j)) {
                $token = (string)($j['accessToken'] ?? $j['access_token'] ?? ($j['data']['accessToken'] ?? ''));
                if ($token !== '') { set_transient($cacheKey, $token, 20 * MINUTE_IN_SECONDS); return $token; }
            }
        }
        Logger::add('error','Jibit token generation failed', ['base'=>$base]);
        return '';
    }
}
