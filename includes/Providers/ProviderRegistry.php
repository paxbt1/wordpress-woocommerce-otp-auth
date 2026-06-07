<?php
namespace WCSA\Providers;

defined('ABSPATH') || exit;

use WCSA\Providers\Sms\IranSmsProvider;
use WCSA\Providers\Sms\SmsProviderInterface;
use WCSA\Providers\Identity\JibitIdentityProvider;
use WCSA\Providers\Identity\IdentityProviderInterface;

final class ProviderRegistry {
    private static $i;
    private array $sms = [];
    private array $identity = [];

    public static function instance(): self { return self::$i ?: self::$i = new self(); }

    public function registerDefaults(): void {
        foreach (IranSmsProvider::catalog() as $id => $meta) {
            $this->registerSms(new IranSmsProvider($id, $meta));
        }
        // Backward compatibility: old Kavenegar class may still be hooked by external extensions.
        $this->registerIdentity(new JibitIdentityProvider());
    }
    public function registerSms(SmsProviderInterface $p): void { $this->sms[$p->id()] = $p; }
    public function registerIdentity(IdentityProviderInterface $p): void { $this->identity[$p->id()] = $p; }
    public function sms(string $id): ?SmsProviderInterface { return $this->sms[$id] ?? null; }
    public function identity(string $id): ?IdentityProviderInterface { return $this->identity[$id] ?? null; }
    public function smsProviders(): array { return $this->sms; }
    public function identityProviders(): array { return $this->identity; }
}
