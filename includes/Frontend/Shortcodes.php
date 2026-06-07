<?php
namespace WCSA\Frontend; defined('ABSPATH') || exit;
final class Shortcodes{private static $i; public static function instance():self{return self::$i?:self::$i=new self();} public function init():void{add_shortcode('smsauth_form',[$this,'form']);} public function form():string{return (new FormRenderer())->render();}}
