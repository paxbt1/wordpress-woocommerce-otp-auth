<?php
namespace WCSA; defined('ABSPATH') || exit;
final class Autoloader{ public static function register():void{ spl_autoload_register([__CLASS__,'autoload']); } public static function autoload(string $class):void{ if(strpos($class,'WCSA\\')!==0)return; $relative=str_replace('\\',DIRECTORY_SEPARATOR,substr($class,5)); $file=WCSA_DIR.'includes'.DIRECTORY_SEPARATOR.$relative.'.php'; if(is_readable($file)) require_once $file; }}
