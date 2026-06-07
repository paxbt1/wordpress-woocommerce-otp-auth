<?php
namespace WCSA\Providers\Sms; defined('ABSPATH') || exit; interface SmsProviderInterface{ public function id():string; public function title():string; public function sendOtp(string $mobileE164,string $code,array $context=[]):bool; }
