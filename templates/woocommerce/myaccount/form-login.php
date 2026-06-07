<?php
/**
 * WooCom SMS Auth replacement for WooCommerce login/register form.
 */
defined('ABSPATH') || exit;

echo (new \WCSA\Frontend\FormRenderer())->render();
