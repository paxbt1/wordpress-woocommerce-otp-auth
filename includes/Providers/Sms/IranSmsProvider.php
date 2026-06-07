<?php
namespace WCSA\Providers\Sms;

defined('ABSPATH') || exit;

use WCSA\Options;
use WCSA\Support\Helpers;
use WCSA\Support\Logger;

/**
 * Universal Iranian SMS provider layer inspired by amyavari/iran-sms-laravel.
 * It intentionally avoids Laravel dependencies and uses WordPress HTTP API.
 */
final class IranSmsProvider implements SmsProviderInterface {
    private string $id;
    private array $meta;
    private array $activeContext = [];

    public function __construct(string $id, array $meta) {
        $this->id = $id;
        $this->meta = $meta;
    }

    public function id(): string { return $this->id; }
    public function title(): string { return (string)($this->meta['title'] ?? $this->id); }

    public static function catalog(): array {
        return [
            'kavenegar' => [
                'title' => 'کاوه‌نگار', 'site' => 'kavenegar.com', 'auth' => 'api_key', 'supports' => ['pattern','text'],
                'fields' => ['api_key','sender','pattern_code','token_field'],
                'defaults' => ['token_field' => 'token'],
                'note' => 'برای OTP بهتر است از Verify Lookup و Pattern استفاده شود.',
            ],
            'sms_ir' => [
                'title' => 'SMS.ir / اس‌ام‌اس دات آی‌آر', 'site' => 'sms.ir', 'auth' => 'api_key', 'supports' => ['pattern','text','credit'],
                'fields' => ['api_key','sender','pattern_code','token_field'],
                'defaults' => ['token_field' => 'code'],
                'note' => 'OTP اختصاصی ندارد؛ برای کد تایید از قالب/Pattern استفاده شود.',
            ],
            'meli_payamak' => [
                'title' => 'ملی پیامک', 'site' => 'melipayamak.com', 'auth' => 'username_password', 'supports' => ['pattern','text'],
                'fields' => ['username','password','sender','pattern_code','token_field'],
                'defaults' => ['token_field' => '0'],
                'note' => 'متغیرهای قالب در برخی حساب‌ها ترتیبی هستند؛ کد در مقدار اول ارسال می‌شود.',
            ],
            'payamito' => [
                'title' => 'پیامیتو', 'site' => 'payamito.com / payamak-panel.com', 'auth' => 'username_password', 'supports' => ['pattern','otp','rest_fallback'],
                'fields' => ['username','password','sender','pattern_code','pattern_params'],
                'defaults' => ['pattern_params' => '{code}'],
                'note' => 'اگر کد پترن / BodyId وارد شود، ارسال پیامیتو با الگو و متد SendByBaseNumber2 انجام می‌شود. متن/پارامترهای پترن را با ; جدا کنید و از {code} برای کد OTP استفاده کنید. اگر BodyId خالی باشد، ارسال با REST SendOtp انجام می‌شود.',
            ],
            'payam_resan' => [
                'title' => 'پیام‌رسان', 'site' => 'payam-resan.com', 'auth' => 'username_password', 'supports' => ['pattern','text'],
                'fields' => ['username','password','sender','pattern_code','token_field'],
                'defaults' => ['token_field' => 'code'],
                'note' => 'معمولاً برای Pattern به ۳ متغیر نیاز دارد؛ فیلدهای کمکی token2 و token3 قابل تنظیم هستند.',
            ],
            'faraz_sms' => [
                'title' => 'فراز اس‌ام‌اس / IPPanel', 'site' => 'farazsms.com', 'auth' => 'api_key', 'supports' => ['pattern','text'],
                'fields' => ['api_key','sender','pattern_code','token_field'],
                'defaults' => ['token_field' => 'code'],
                'note' => 'برای OTP معمولاً از پترن IPPanel استفاده می‌شود.',
            ],
            'raygan_sms' => [
                'title' => 'رایگان اس‌ام‌اس', 'site' => 'raygansms.com', 'auth' => 'username_password_token', 'supports' => ['otp','pattern','text'],
                'fields' => ['username','password','api_key','sender','pattern_code','token_field'],
                'defaults' => ['token_field' => 'code'],
                'note' => 'این سرویس OTP اختصاصی دارد؛ برای Pattern ممکن است token هم نیاز باشد.',
            ],
            'web_one' => [
                'title' => 'وب‌وان', 'site' => 'webone-sms.com', 'auth' => 'username_password', 'supports' => ['otp','text'],
                'fields' => ['username','password','sender'],
                'defaults' => [],
                'note' => 'Pattern اختصاصی ندارد؛ ارسال ساده/OTP استفاده می‌شود.',
            ],
            'amoot_sms' => [
                'title' => 'پیامک آموت', 'site' => 'amootsms.com', 'auth' => 'api_key', 'supports' => ['pattern','text'],
                'fields' => ['api_key','sender','pattern_code','token_field'],
                'defaults' => ['token_field' => '0'],
                'note' => 'متغیرهای قالب ممکن است ترتیبی باشند.',
            ],
            'fara_payamak' => [
                'title' => 'فراپیامک', 'site' => 'farapayamak.ir', 'auth' => 'username_password', 'supports' => ['pattern','text'],
                'fields' => ['username','password','sender','pattern_code','token_field'],
                'defaults' => ['token_field' => '0'],
                'note' => 'متغیرهای قالب در برخی حساب‌ها ترتیبی هستند.',
            ],
            'ghasedak' => [
                'title' => 'قاصدک', 'site' => 'ghasedak.me', 'auth' => 'api_key', 'supports' => ['pattern','text'],
                'fields' => ['api_key','sender','pattern_code','token_field'],
                'defaults' => ['token_field' => 'code'],
                'note' => 'برای OTP از قالب/Template استفاده شود.',
            ],
            'behin_payam' => [
                'title' => 'بهین پیام', 'site' => 'behinpayam.com', 'auth' => 'username_password', 'supports' => ['pattern','text'],
                'fields' => ['username','password','sender','pattern_code','token_field'],
                'defaults' => ['token_field' => 'code'],
                'note' => 'در برخی روش‌ها دقیقاً ۳ متغیر قالب لازم است.',
            ],
            'asanak' => [
                'title' => 'آسانک', 'site' => 'asanak.com', 'auth' => 'username_password', 'supports' => ['pattern','text'],
                'fields' => ['username','password','sender','pattern_code','token_field'],
                'defaults' => ['token_field' => 'code'],
                'note' => 'برای OTP از قالب/Pattern استفاده شود.',
            ],
            'mediana' => [
                'title' => 'مدیانا', 'site' => 'mediana.ir', 'auth' => 'api_key', 'supports' => ['pattern','text'],
                'fields' => ['api_key','sender','pattern_code','token_field'],
                'defaults' => ['token_field' => 'code'],
                'note' => 'برای OTP از قالب/Pattern استفاده شود.',
            ],
            'custom_rest' => [
                'title' => 'سرویس‌دهنده سفارشی REST', 'site' => '-', 'auth' => 'custom', 'supports' => ['text'],
                'fields' => ['custom_url','custom_method','custom_auth_header','custom_auth_value','custom_body','success_path','success_value'],
                'defaults' => ['custom_method'=>'POST','success_path'=>'','success_value'=>''],
                'note' => 'برای هر پنل ناشناخته، URL و Body را با متغیرهای {mobile}، {mobile_09}، {code}، {message}، {sender} تنظیم کنید.',
            ],
        ];
    }

    public static function providerIds(): array { return array_keys(self::catalog()); }
    public static function meta(string $id): array { $c = self::catalog(); return $c[$id] ?? $c['kavenegar']; }

    public function sendOtp(string $mobileE164, string $code, array $context=[]): bool {
        $message = $this->renderMessage($code);
        $this->activeContext = $context;
        $result = false;
        $forceLog = !empty($context['manual_test']) || !empty($context['request_id']);
        $revealSensitive = !empty($context['debug_sensitive']);
        Logger::add('info', 'SMS send attempt', ['provider'=>$this->id, 'mobile'=>$mobileE164, 'code'=>$code, 'sender'=>$this->sender(), 'username'=>$this->username(), 'api_key'=>$this->apiKey(), 'context'=>$context], $forceLog, $revealSensitive);
        try {
            switch ($this->id) {
                case 'kavenegar': $result = $this->sendKavenegar($mobileE164, $code, $message); break;
                case 'sms_ir': $result = $this->sendSmsIr($mobileE164, $code, $message); break;
                case 'payamito': $result = $this->sendPayamito($mobileE164, $code, $message); break;
                case 'faraz_sms': $result = $this->sendFaraz($mobileE164, $code, $message); break;
                case 'ghasedak': $result = $this->sendGhasedak($mobileE164, $code, $message); break;
                case 'amoot_sms': $result = $this->sendAmoot($mobileE164, $code, $message); break;
                case 'mediana': $result = $this->sendMediana($mobileE164, $code, $message); break;
                case 'custom_rest': $result = $this->sendCustom($mobileE164, $code, $message); break;
                default: $result = $this->sendGeneric($mobileE164, $code, $message); break;
            }
        } catch (\Throwable $e) {
            Logger::add('error', 'SMS provider exception', ['provider'=>$this->id, 'error'=>$e->getMessage(), 'context'=>$context], $forceLog, $revealSensitive);
            $result = false;
        }
        Logger::add($result ? 'info' : 'error', 'SMS send result', ['provider'=>$this->id, 'mobile'=>$mobileE164, 'code'=>$code, 'sender'=>$this->sender(), 'username'=>$this->username(), 'api_key'=>$this->apiKey(), 'ok'=>$result, 'context'=>$context], $forceLog, $revealSensitive);
        $this->activeContext = [];
        return $result;
    }

    private function cfg(string $key, $default='') { return Options::get('sms_'.$this->id.'_'.$key, Options::get($this->legacyKey($key), $default)); }
    private function legacyKey(string $key): string { return $key === 'api_key' ? 'kavenegar_api_key' : ($key === 'sender' ? 'kavenegar_sender' : ($key === 'pattern_code' ? 'kavenegar_pattern' : $key)); }
    private function tokenField(): string { return (string)$this->cfg('token_field', (string)($this->meta['defaults']['token_field'] ?? 'code')); }
    private function sender(): string { return (string)$this->cfg('sender',''); }
    private function apiKey(): string { return (string)$this->cfg('api_key',''); }
    private function username(): string { return (string)$this->cfg('username',''); }
    private function password(): string { return (string)$this->cfg('password',''); }
    private function pattern(): string { return (string)$this->cfg('pattern_code',''); }
    private function patternParams(): string { return (string)$this->cfg('pattern_params',''); }

    private function renderMessage(string $code): string {
        $tpl = (string)Options::get('sms_message_template','کد ورود شما: {code}');
        return strtr($tpl, ['{code}'=>$code, '{site}'=>wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)]);
    }

    private function phone09(string $e164): string { return Helpers::mobileNational($e164); }
    private function phone98(string $e164): string { return preg_replace('/^\+/', '', $e164); }
    private function phoneNoZero(string $e164): string { return preg_replace('/^\+98/', '', $e164); }

    private function post(string $url, array $body, array $headers=[], string $format='json'): array {
        $args = ['timeout'=>12, 'headers'=>$headers, 'body'=>$format==='json' ? wp_json_encode($body, JSON_UNESCAPED_UNICODE) : $body];
        if ($format === 'json') $args['headers']['Content-Type'] = 'application/json; charset=utf-8';
        $res = wp_remote_post($url, $args);
        $parsed = $this->response($res);
        $this->logHttp('POST', $url, $headers, $body, $format, $parsed);
        return $parsed;
    }
    private function get(string $url, array $headers=[]): array {
        $res = wp_remote_get($url, ['timeout'=>12, 'headers'=>$headers]);
        $parsed = $this->response($res);
        $this->logHttp('GET', $url, $headers, [], 'query', $parsed);
        return $parsed;
    }
    private function response($res): array {
        if (is_wp_error($res)) return ['ok'=>false, 'http'=>0, 'body'=>null, 'raw'=>$res->get_error_message(), 'error'=>$res->get_error_message(), 'headers'=>[]];
        $http = (int)wp_remote_retrieve_response_code($res);
        $raw = (string)wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);
        return [
            'ok'=>$http>=200 && $http<300,
            'http'=>$http,
            'body'=>is_array($json)?$json:$raw,
            'raw'=>$raw,
            'headers'=>wp_remote_retrieve_headers($res) ? wp_remote_retrieve_headers($res)->getAll() : [],
        ];
    }
    private function logHttp(string $method, string $url, array $headers, array $body, string $format, array $response): void {
        $ok = $this->success($response);
        $forceLog = !empty($this->activeContext['manual_test']) || !empty($this->activeContext['request_id']);
        $revealSensitive = !empty($this->activeContext['debug_sensitive']);
        Logger::http($ok ? 'info' : 'error', 'SMS webservice response', [
            'provider'=>$this->id,
            'context'=>$this->activeContext,
            'method'=>$method,
            'url'=>$url,
            'format'=>$format,
            'headers'=>$headers,
            'body'=>$body,
        ], [
            'ok'=>$ok,
            'http'=>$response['http'] ?? 0,
            'headers'=>$response['headers'] ?? [],
            'body'=>$response['body'] ?? null,
            'raw'=>$response['raw'] ?? '',
            'error'=>$response['error'] ?? '',
        ], $forceLog, $revealSensitive);
    }
    private function success(array $r): bool {
        if (!$r['ok']) return false;
        $b = $r['body'];
        if (is_array($b)) {
            foreach (['return.status','status','statusCode','code','result','IsSuccessful','success'] as $p) {
                $v = $this->path($b, $p);
                if ($v === true || $v === 1 || $v === '1' || $v === 200 || $v === '200' || $v === 'OK' || $v === 'success') return true;
            }
            if (isset($b['messageid']) || isset($b['messageId']) || isset($b['data'])) return true;
        }
        return true;
    }
    private function path(array $arr, string $path) {
        foreach (explode('.', $path) as $seg) { if (!is_array($arr) || !array_key_exists($seg,$arr)) return null; $arr = $arr[$seg]; }
        return $arr;
    }

    private function sendKavenegar(string $m, string $code, string $msg): bool {
        $key = $this->apiKey(); if (!$key) return false;
        $pattern = $this->pattern();
        if ($pattern) {
            $url = 'https://api.kavenegar.com/v1/'.rawurlencode($key).'/verify/lookup.json';
            $body = ['receptor'=>$this->phone09($m), 'template'=>$pattern, $this->tokenField()=>$code, 'token'=>$code];
            return $this->success($this->post($url, $body, ['Content-Type'=>'application/x-www-form-urlencoded'], 'form'));
        }
        $body = ['receptor'=>$this->phone09($m), 'message'=>$msg]; if ($this->sender()) $body['sender']=$this->sender();
        return $this->success($this->post('https://api.kavenegar.com/v1/'.rawurlencode($key).'/sms/send.json', $body, ['Content-Type'=>'application/x-www-form-urlencoded'], 'form'));
    }

    private function sendSmsIr(string $m, string $code, string $msg): bool {
        $key = $this->apiKey(); if (!$key) return false;
        $headers = ['x-api-key'=>$key, 'Accept'=>'application/json'];
        if ($this->pattern()) {
            $body = ['mobile'=>$this->phone09($m), 'templateId'=>(int)$this->pattern(), 'parameters'=>[['name'=>$this->tokenField(), 'value'=>$code]]];
            return $this->success($this->post('https://api.sms.ir/v1/send/verify', $body, $headers));
        }
        $body = ['lineNumber'=>$this->sender(), 'messageText'=>$msg, 'mobiles'=>[$this->phone09($m)]];
        return $this->success($this->post('https://api.sms.ir/v1/send/bulk', $body, $headers));
    }

    private function sendPayamito(string $m, string $code, string $msg): bool {
        $username = $this->username();
        $password = $this->password();
        $sender   = $this->sender();

        if (!$username || !$password || !$sender) {
            Logger::add('error', 'Payamito configuration is incomplete', [
                'provider' => $this->id,
                'username' => $username,
                'password' => $password,
                'sender' => $sender,
                'has_username' => $username !== '',
                'has_password' => $password !== '',
                'has_sender' => $sender !== '',
                'context' => $this->activeContext,
            ], !empty($this->activeContext['manual_test']) || !empty($this->activeContext['request_id']), !empty($this->activeContext['debug_sensitive']));
            return false;
        }

        // Pattern / base-number mode. Payamito sample code uses SendByBaseNumber2 with bodyId and semicolon-separated text.
        if ($this->pattern()) {
            return $this->sendPayamitoBaseNumber2($m, $code, $username, $password);
        }

        /*
         * Payamito OTP documentation provides two official paths:
         * 1) SOAP: http://api.payamak-panel.com/post/Send.asmx?wsdl  method SendOtp/SendOtp2
         * 2) REST: https://rest.payamak-panel.com/api/SendSMS/SendOtp
         *
         * In shared/Cloudflare/Parspack-hosted WordPress installations, SoapClient may fetch the WSDL
         * synchronously and can crash/timeout PHP-FPM, which returns an HTML upstream 500 page instead of JSON.
         * Therefore the WordPress plugin uses REST first and keeps SOAP only as an optional fallback.
         */
        $restOk = $this->sendPayamitoRestOtp($m, $code, $username, $password, $sender);
        if ($restOk) {
            return true;
        }

        // Optional SOAP fallback is disabled by default. Enable only with a constant on servers known to support it.
        if (!defined('WCSA_PAYAMITO_ENABLE_SOAP_FALLBACK') || !WCSA_PAYAMITO_ENABLE_SOAP_FALLBACK) {
            Logger::add('error', 'Payamito REST failed; SOAP fallback is disabled to prevent upstream 500/timeout issues', [
                'provider' => $this->id,
                'context' => $this->activeContext,
                'hint' => 'Define WCSA_PAYAMITO_ENABLE_SOAP_FALLBACK as true only if SoapClient/WSDL works reliably on this host.',
            ], !empty($this->activeContext['manual_test']) || !empty($this->activeContext['request_id']), !empty($this->activeContext['debug_sensitive']));
            return false;
        }

        return $this->sendPayamitoSoapOtp($m, $code, $username, $password, $sender);
    }

    private function sendPayamitoBaseNumber2(string $m, string $code, string $username, string $password): bool {
        $bodyId = trim($this->pattern());
        $text = $this->payamitoPatternText($m, $code);
        if ($bodyId === '' || $text === '') {
            Logger::add('error', 'Payamito pattern configuration is incomplete', [
                'provider' => $this->id,
                'bodyId' => $bodyId,
                'text' => $text,
                'context' => $this->activeContext,
            ], !empty($this->activeContext['manual_test']) || !empty($this->activeContext['request_id']), !empty($this->activeContext['debug_sensitive']));
            return false;
        }

        if (!preg_match('/^\d+$/', $bodyId)) {
            Logger::add('error', 'Payamito BodyId must be numeric', [
                'provider' => $this->id,
                'bodyId' => $bodyId,
                'hint' => 'کد پترن / BodyId پیامیتو باید شناسه عددی الگو باشد، نه نام متنی الگو.',
                'context' => $this->activeContext,
            ], !empty($this->activeContext['manual_test']) || !empty($this->activeContext['request_id']), !empty($this->activeContext['debug_sensitive']));
            return false;
        }

        $url = add_query_arg([
            'username' => $username,
            'password' => $password,
            'text'     => $text,
            'to'       => $this->phoneNoZero($m),
            'bodyId'   => $bodyId,
        ], 'https://api.payamak-panel.com/post/Send.asmx/SendByBaseNumber2');

        $response = $this->get($url, ['Accept' => 'text/plain, application/xml, */*']);
        $parsed = $this->parsePayamitoBaseNumberReturn($response);

        $forceLog = !empty($this->activeContext['manual_test']) || !empty($this->activeContext['request_id']);
        $revealSensitive = !empty($this->activeContext['debug_sensitive']);
        Logger::http($parsed['ok'] ? 'info' : 'error', 'Payamito pattern SendByBaseNumber2 response', [
            'provider' => $this->id,
            'method' => 'SendByBaseNumber2',
            'url' => 'https://api.payamak-panel.com/post/Send.asmx/SendByBaseNumber2',
            'format' => 'query',
            'body' => [
                'username' => $username,
                'password' => $password,
                'text' => $text,
                'to' => $this->phoneNoZero($m),
                'bodyId' => $bodyId,
            ],
            'context' => $this->activeContext,
        ], [
            'ok' => $parsed['ok'],
            'parsed' => $parsed,
            'http' => $response['http'] ?? 0,
            'headers' => $response['headers'] ?? [],
            'body' => $response['body'] ?? null,
            'raw' => $response['raw'] ?? '',
            'error' => $response['error'] ?? '',
        ], $forceLog, $revealSensitive);

        return (bool) $parsed['ok'];
    }

    private function payamitoPatternText(string $m, string $code): string {
        $params = trim($this->patternParams());
        if ($params === '') {
            return $code;
        }
        $replaced = $this->replaceVars($params, $m, $code, $this->renderMessage($code));
        // If admin entered extra parameters without {code}, keep backward-friendly behavior: code first, then extras.
        if (strpos($params, '{code}') === false) {
            $replaced = $code . ';' . $replaced;
        }
        return trim($replaced, " \t\n\r\0\x0B;");
    }

    private function parsePayamitoBaseNumberReturn(array $response): array {
        if (empty($response['ok'])) {
            return [
                'ok' => false,
                'value' => null,
                'message' => $response['error'] ?? 'HTTP request failed',
            ];
        }

        $raw = (string)($response['raw'] ?? '');
        $value = trim(wp_strip_all_tags($raw));
        if ($value === '' && is_scalar($response['body'] ?? null)) {
            $value = trim((string)$response['body']);
        }
        if ($value === '' && is_array($response['body'] ?? null)) {
            $value = (string)($response['body']['Value'] ?? $response['body']['SendByBaseNumber2Result'] ?? '');
        }

        $parsed = $this->parsePayamitoOtpReturn($value);
        return [
            'ok' => (bool)$parsed['ok'],
            'value' => $value,
            'message' => $parsed['message'] ?? '',
        ];
    }

    private function sendPayamitoSoapOtp(string $m, string $code, string $username, string $password, string $sender): bool {
        if (!class_exists('SoapClient')) {
            Logger::add('error', 'Payamito SOAP extension is not available', ['provider' => $this->id]);
            return false;
        }

        try {
            $wsdl = 'http://api.payamak-panel.com/post/Send.asmx?wsdl';
            $client = new \SoapClient($wsdl, [
                'encoding' => 'UTF-8',
                'exceptions' => true,
                'connection_timeout' => 8,
                'cache_wsdl' => WSDL_CACHE_MEMORY,
                'trace' => true,
            ]);

            $soapArgs = [
                'username' => $username,
                'password' => $password,
                'code'     => (string) $code,
                'to'       => $this->phone09($m),
                'from'     => $sender,
            ];

            $result = $client->SendOtp2($soapArgs);
            $value  = $result->SendOtp2Result ?? null;
            $parsed = $this->parsePayamitoOtpReturn($value);

            $forceLog = !empty($this->activeContext['manual_test']) || !empty($this->activeContext['request_id']);
            $revealSensitive = !empty($this->activeContext['debug_sensitive']);
            Logger::add($parsed['ok'] ? 'info' : 'error', 'Payamito OTP SOAP response', [
                'provider' => $this->id,
                'method' => 'SendOtp2',
                'wsdl' => $wsdl,
                'request' => $soapArgs,
                'result_value' => $value,
                'parsed' => $parsed,
                'soap_last_request' => method_exists($client, '__getLastRequest') ? $client->__getLastRequest() : '',
                'soap_last_response' => method_exists($client, '__getLastResponse') ? $client->__getLastResponse() : '',
                'context' => $this->activeContext,
            ], $forceLog, $revealSensitive);

            return (bool) $parsed['ok'];
        } catch (\Throwable $e) {
            Logger::add('error', 'Payamito SOAP SendOtp2 error', [
                'provider' => $this->id,
                'context' => $this->activeContext,
                'method' => 'SendOtp2',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], !empty($this->activeContext['manual_test']) || !empty($this->activeContext['request_id']), !empty($this->activeContext['debug_sensitive']));
            return false;
        }
    }

    private function sendPayamitoRestOtp(string $m, string $code, string $username, string $password, string $sender): bool {
        $url = 'https://rest.payamak-panel.com/api/SendSMS/SendOtp';
        $body = [
            'username' => $username,
            'password' => $password,
            'From'     => $sender,
            'to'       => $this->phone09($m),
            'code'     => (string) $code,
        ];

        // Primary REST call. The endpoint accepts JSON and returns {Value, RetStatus, StrRetStatus}.
        $response = $this->post($url, $body, [
            'Accept' => 'application/json',
        ], 'json');

        $parsed = $this->parsePayamitoRestReturn($response);

        // Some older Payamak Panel deployments are stricter with form-encoded requests.
        // If JSON transport returns HTML/invalid body, try form transport once before failing.
        if (!$parsed['ok'] && in_array((string)($response['http'] ?? ''), ['400','415','500'], true)) {
            Logger::add('error', 'Payamito REST JSON transport failed; form fallback will be tried', [
                'provider' => $this->id,
                'http' => $response['http'] ?? 0,
                'message' => $parsed['message'] ?? '',
                'context' => $this->activeContext,
            ], !empty($this->activeContext['manual_test']) || !empty($this->activeContext['request_id']), !empty($this->activeContext['debug_sensitive']));
            $response = $this->post($url, $body, [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            ], 'form');
            $parsed = $this->parsePayamitoRestReturn($response);
        }
        $forceLog = !empty($this->activeContext['manual_test']) || !empty($this->activeContext['request_id']);
        $revealSensitive = !empty($this->activeContext['debug_sensitive']);
        Logger::http($parsed['ok'] ? 'info' : 'error', 'Payamito OTP REST response', [
            'provider' => $this->id,
            'method' => 'REST SendOtp',
            'url' => $url,
            'format' => 'json',
            'body' => $body,
            'context' => $this->activeContext,
        ], [
            'ok' => $parsed['ok'],
            'parsed' => $parsed,
            'http' => $response['http'] ?? 0,
            'headers' => $response['headers'] ?? [],
            'body' => $response['body'] ?? null,
            'raw' => $response['raw'] ?? '',
            'error' => $response['error'] ?? '',
        ], $forceLog, $revealSensitive);

        return (bool) $parsed['ok'];
    }

    private function parsePayamitoRestReturn(array $response): array {
        if (empty($response['ok'])) {
            return [
                'ok' => false,
                'value' => $response['body']['Value'] ?? null,
                'ret_status' => $response['body']['RetStatus'] ?? null,
                'str_ret_status' => $response['body']['StrRetStatus'] ?? null,
                'message' => $response['error'] ?? 'HTTP request failed',
            ];
        }

        $body = $response['body'] ?? null;
        if (!is_array($body)) {
            return [
                'ok' => false,
                'value' => null,
                'ret_status' => null,
                'str_ret_status' => null,
                'message' => 'REST response is not JSON',
            ];
        }

        $value = $body['Value'] ?? null;
        $retStatus = $body['RetStatus'] ?? null;
        $str = $body['StrRetStatus'] ?? null;

        $ok = ((string)$retStatus === '1') && (strtoupper((string)$str) === 'OK' || $str === null);
        if ($ok && is_numeric($value)) {
            // Value may be a long recId. If Value is a known error code, fail.
            $otp = $this->parsePayamitoOtpReturn($value);
            if (!$otp['ok'] && (int)$value !== 1) $ok = false;
        }

        return [
            'ok' => $ok,
            'value' => $value,
            'ret_status' => $retStatus,
            'str_ret_status' => $str,
            'message' => $ok ? 'OK' : $this->payamitoReturnMessage($value),
        ];
    }

    private function parsePayamitoOtpReturn($value): array {
        $raw = is_scalar($value) ? trim((string)$value) : '';
        if ($raw === '') {
            return ['ok' => false, 'value' => $value, 'message' => 'Empty response'];
        }

        if (is_numeric($raw)) {
            $num = (int) $raw;
            // Documentation: a returned recId means sent. Error codes are -111..18.
            // Code 1 is also documented as successful request.
            $knownErrors = [-111, -110, -109, -108, 0, 2, 3, 4, 5, 6, 7, 9, 10, 11, 12, 14, 15, 16, 17, 18];
            $ok = ($num === 1) || !in_array($num, $knownErrors, true);
            return [
                'ok' => $ok,
                'value' => $raw,
                'message' => $ok ? 'OK / recId' : $this->payamitoReturnMessage($raw),
            ];
        }

        $lower = strtolower($raw);
        $ok = ($lower === 'ok' || strpos($lower, 'success') !== false);
        return ['ok' => $ok, 'value' => $raw, 'message' => $ok ? 'OK' : $raw];
    }

    private function payamitoReturnMessage($value): string {
        $key = is_numeric($value) ? (string)((int)$value) : (string)$value;
        $messages = [
            '-111' => 'IP درخواست‌کننده نامعتبر است.',
            '-110' => 'الزام استفاده از ApiKey به جای رمز عبور.',
            '-109' => 'الزام تنظیم IP مجاز برای استفاده از API.',
            '-108' => 'مسدود شدن IP به دلیل تلاش ناموفق استفاده از API.',
            '0' => 'نام کاربری یا رمز عبور اشتباه است.',
            '1' => 'درخواست با موفقیت انجام شد.',
            '2' => 'اعتبار کافی نیست.',
            '3' => 'محدودیت در ارسال روزانه.',
            '4' => 'محدودیت در حجم ارسال.',
            '5' => 'شماره فرستنده معتبر نیست.',
            '6' => 'سامانه در حال بروزرسانی است یا لینک ارسال اپراتور قطع است.',
            '7' => 'متن حاوی کلمه فیلتر شده است.',
            '9' => 'ارسال از خطوط عمومی از طریق وب‌سرویس امکان‌پذیر نیست.',
            '10' => 'کاربر فعال نیست.',
            '11' => 'ارسال نشده.',
            '12' => 'مدارک کاربر کامل نیست.',
            '14' => 'متن حاوی لینک است.',
            '15' => 'عدم وجود لغو 11 در انتهای متن پیامک.',
            '16' => 'شماره گیرنده‌ای یافت نشد.',
            '17' => 'متن پیامک خالی است.',
            '18' => 'شماره موبایل معتبر نیست.',
        ];
        return $messages[$key] ?? ('پاسخ ناشناخته پیامیتو: ' . (string)$value);
    }

    private function sendFaraz(string $m, string $code, string $msg): bool {
        $key = $this->apiKey(); if (!$key) return false;
        if ($this->pattern()) {
            $body = ['code'=>$this->pattern(), 'sender'=>$this->sender(), 'recipient'=>$this->phone09($m), 'variable'=>[$this->tokenField()=>$code]];
            return $this->success($this->post('https://api2.ippanel.com/api/v1/sms/pattern/normal/send', $body, ['apikey'=>$key]));
        }
        $body = ['sender'=>$this->sender(), 'recipient'=>[$this->phone09($m)], 'message'=>$msg];
        return $this->success($this->post('https://api2.ippanel.com/api/v1/sms/send/webservice/single', $body, ['apikey'=>$key]));
    }

    private function sendGhasedak(string $m, string $code, string $msg): bool {
        $key = $this->apiKey(); if (!$key) return false;
        if ($this->pattern()) {
            $url = add_query_arg(['receptor'=>$this->phone09($m),'template'=>$this->pattern(),'param1'=>$code], 'https://api.ghasedak.me/v2/verification/send/simple');
            return $this->success($this->get($url, ['apikey'=>$key]));
        }
        $body = ['message'=>$msg, 'receptor'=>$this->phone09($m), 'linenumber'=>$this->sender()];
        return $this->success($this->post('https://api.ghasedak.me/v2/sms/send/simple', $body, ['apikey'=>$key], 'form'));
    }

    private function sendAmoot(string $m, string $code, string $msg): bool {
        $key = $this->apiKey(); if (!$key) return false;
        if ($this->pattern()) {
            $url = add_query_arg(['Mobile'=>$this->phone09($m),'TemplateCode'=>$this->pattern(),'Token'=>$code], 'https://portal.amootsms.com/rest/SendQuickOTP');
            return $this->success($this->get($url, ['Authorization'=>$key]));
        }
        $body = ['SendDateTime'=>'','SMSMessageText'=>$msg,'LineNumber'=>$this->sender(),'Mobiles'=>$this->phone09($m)];
        return $this->success($this->post('https://portal.amootsms.com/rest/SendSimple', $body, ['Authorization'=>$key], 'form'));
    }

    private function sendMediana(string $m, string $code, string $msg): bool {
        $key = $this->apiKey(); if (!$key) return false;
        if ($this->pattern()) {
            $body = ['recipient'=>$this->phone09($m), 'code'=>$this->pattern(), 'parameters'=>[$this->tokenField()=>$code]];
            return $this->success($this->post('https://api.mediana.ir/sms/v1/send/pattern', $body, ['Authorization'=>'AccessKey '.$key]));
        }
        $body = ['recipients'=>[$this->phone09($m)], 'message'=>$msg, 'sender'=>$this->sender()];
        return $this->success($this->post('https://api.mediana.ir/sms/v1/send', $body, ['Authorization'=>'AccessKey '.$key]));
    }

    private function sendGeneric(string $m, string $code, string $msg): bool {
        // Conservative generic endpoint support: if the admin filled a custom URL for this provider, use it.
        $url = (string)$this->cfg('custom_url','');
        if ($url) return $this->sendCustom($m,$code,$msg);
        Logger::add('error','Provider needs custom endpoint or credentials', ['provider'=>$this->id]);
        return false;
    }

    private function sendCustom(string $m, string $code, string $msg): bool {
        $url = (string)$this->cfg('custom_url',''); if (!$url) return false;
        $method = strtoupper((string)$this->cfg('custom_method','POST'));
        $authHeader = (string)$this->cfg('custom_auth_header','');
        $authValue = (string)$this->cfg('custom_auth_value','');
        $headers = [];
        if ($authHeader && $authValue) $headers[$authHeader] = $this->replaceVars($authValue, $m, $code, $msg);
        $bodyTpl = (string)$this->cfg('custom_body','');
        $bodyText = $this->replaceVars($bodyTpl, $m, $code, $msg);
        $body = json_decode($bodyText, true);
        if (!is_array($body)) parse_str($bodyText, $body);
        $r = $method === 'GET' ? $this->get($this->replaceVars($url, $m, $code, $msg), $headers) : $this->post($url, $body, $headers);
        $path = (string)$this->cfg('success_path',''); $val = (string)$this->cfg('success_value','');
        if ($path && is_array($r['body'])) return (string)$this->path($r['body'], $path) === $val;
        return $this->success($r);
    }

    private function replaceVars(string $s, string $m, string $code, string $msg): string {
        return strtr($s, ['{mobile}'=>$m, '{mobile_09}'=>$this->phone09($m), '{mobile_98}'=>$this->phone98($m), '{mobile_no_zero}'=>$this->phoneNoZero($m), '{code}'=>$code, '{message}'=>$msg, '{sender}'=>$this->sender(), '{api_key}'=>$this->apiKey(), '{username}'=>$this->username(), '{password}'=>$this->password()]);
    }
}
