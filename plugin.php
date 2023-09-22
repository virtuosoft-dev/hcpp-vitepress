<?php
/**
 * Plugin Name: VitePress
 * Plugin URI: https://github.com/virtuosoft-dev/hcpp-vitepress
 * Description: VitePress is a plugin for HestiaCP that allows you to easily host VitePress websites.
 * Version: 1.0.0
 */

// Register the install and uninstall scripts
global $hcpp;
require_once( dirname(__FILE__) . '/vitepress.php' );

$hcpp->register_install_script( dirname(__FILE__) . '/install' );
$hcpp->register_uninstall_script( dirname(__FILE__) . '/uninstall' );
