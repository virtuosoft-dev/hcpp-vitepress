<?php
/**
 * Plugin Name: VitePress
 * Plugin URI: https://github.com/virtuosoft-dev/hcpp-vitepress
 * Description: Host and maintain updated VitePress websites
 * Author: Virtuosoft/Stephen J. Carnam
 * License AGPL-3.0, for other licensing options contact support@virtuosoft.com
 */

// Register the install and uninstall scripts
global $hcpp;
require_once( dirname(__FILE__) . '/vitepress.php' );

$hcpp->register_install_script( dirname(__FILE__) . '/install' );
$hcpp->register_uninstall_script( dirname(__FILE__) . '/uninstall' );
