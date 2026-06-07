<?php
namespace WCSA\Providers\Identity; defined('ABSPATH') || exit; interface IdentityProviderInterface{ public function id():string; public function title():string; public function verifyOwner(string $mobileE164,string $nationalCode):array; }
