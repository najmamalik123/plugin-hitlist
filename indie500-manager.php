<?php
/**
 * Plugin Name: Indie500 Manager
 * Plugin URI: https://yoursite.com
 * Description: Modern beheer systeem voor stemlijsten en stemmen voor de Indie500.
 * Version: 3.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: indie500-manager
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('INDIE500_VERSION', '3.0.0');
define('INDIE500_PATH', plugin_dir_path(__FILE__));
define('INDIE500_URL', plugin_dir_url(__FILE__));
define('INDIE500_BASENAME', plugin_basename(__FILE__));

// Main plugin class
class Indie500Manager {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load text domain for translations
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    public function init() {
        // Load includes
        $this->load_includes();
        
        // Initialize components
        new Indie500_Admin();
        new Indie500_Frontend();
        new Indie500_Ajax();
        new Indie500_Database();
    }
    
    private function load_includes() {
        require_once INDIE500_PATH . 'includes/class-admin.php';
        require_once INDIE500_PATH . 'includes/class-frontend.php';
        require_once INDIE500_PATH . 'includes/class-ajax.php';
        require_once INDIE500_PATH . 'includes/class-database.php';
        require_once INDIE500_PATH . 'includes/functions.php';
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('indie500-manager', false, dirname(INDIE500_BASENAME) . '/languages');
    }
    
    public function enqueue_scripts() {
        // Enqueue main styles
        wp_enqueue_style('indie500-style', INDIE500_URL . 'assets/style.css', array(), INDIE500_VERSION);
        
        // Enqueue main script
        wp_enqueue_script('indie500-script', INDIE500_URL . 'assets/script.js', array('jquery'), INDIE500_VERSION, true);
        
        // Localize script for AJAX
        wp_localize_script('indie500-script', 'indie500_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('indie500_nonce'),
            'messages' => array(
                'max_votes' => __('Je mag maximaal 10 titels selecteren.', 'indie500-manager'),
                'vote_success' => __('Bedankt voor het stemmen!', 'indie500-manager'),
                'vote_error' => __('Er ging iets mis bij het stemmen.', 'indie500-manager'),
                'no_selection' => __('Selecteer minimaal 1 titel.', 'indie500-manager'),
                'exact_10_required' => __('Je moet precies 10 titels selecteren.', 'indie500-manager'),
                'loading' => __('Bezig met laden...', 'indie500-manager'),
                'submitting' => __('Bezig met opslaan...', 'indie500-manager')
            )
        ));
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'indie500') !== false) {
            wp_enqueue_style('indie500-admin-style', INDIE500_URL . 'assets/admin-style.css', array(), INDIE500_VERSION);
            wp_enqueue_script('indie500-admin-script', INDIE500_URL . 'assets/admin-script.js', array('jquery'), INDIE500_VERSION, true);
            
            wp_localize_script('indie500-admin-script', 'indie500_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('indie500_admin_nonce')
            ));
        }
    }
    
    public function activate() {
        // Create database tables
        Indie500_Database::create_tables();
        
        // Set default options
        add_option('indie500_settings', array(
            'max_votes_per_user' => 10,
            'voting_enabled' => true,
            'show_results' => true,
            'require_exact_10' => true,
            'thank_you_message' => __('Bedankt voor je deelname aan de Indie500!', 'indie500-manager'),
            'voting_closed_message' => __('Het stemmen is momenteel gesloten.', 'indie500-manager')
        ));
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clean up if needed
        flush_rewrite_rules();
    }
}

// Initialize the plugin
new Indie500Manager();
