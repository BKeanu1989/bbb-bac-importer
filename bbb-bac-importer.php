<?php
/*
Plugin Name: BigBoxBerlin Überweisungsimporter
Plugin URI: https://
Description: BigBoxBerlin Überweisungsimporter
Version: 0.1.0
Author: Kevin Fechner
Author URI: https://complete-webolutions.de
Text Domain: bbb-bac-importer
Domain Path: /languages
 */

if (! defined('ABSPATH')) exit;

define('BBB_BAC_IMPORTER_PATH', WP_PLUGIN_DIR . '/bbb-bac-importer/');

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    if (is_admin()) {

        // register admin page and add menu
        add_action('admin_menu', 'bbb_bac_importer_menu');

        function bbb_bac_importer_menu()
        {
            add_submenu_page('woocommerce', 'Überweisungsimporter', 'Überweisungsimporter', 'manage_options', 'bbb-bac-importer', 'bbb_bac_importer_include_admin');
        }

        function bbb_bac_importer_include_admin()
        {
            include 'admin-menu.php';
        }
    }
}

add_action( 'admin_post_import_bacs', 'bbb_import_bacs');
function bbb_import_bacs() 
{
    global $wpdb;
    require_once(BBB_BAC_IMPORTER_PATH . 'includes/class-csv-importer.php');

    $file_data = $_FILES['csv'];
    $path_info = pathinfo($file_data['name']);

    if ($path_info['extension'] !== 'csv') {
        return;
    }

    $importer = new CSV_Importer($file_data['tmp_name']);
    $lines = $importer->init();

    $redirect_to = add_query_arg( array( 'page' => 'bbb-bac-importer' ), admin_url( 'admin.php' ) );
    wp_safe_redirect($redirect_to);
}