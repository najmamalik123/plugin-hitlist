<?php

class Indie500_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_indie500_submit_vote', array($this, 'handle_vote_submission'));
        add_action('wp_ajax_nopriv_indie500_submit_vote', array($this, 'handle_vote_submission'));
        add_action('wp_ajax_indie500_export_results', array($this, 'export_results'));
    }
    
    public function handle_vote_submission() {
        check_ajax_referer('indie500_nonce', 'nonce');
        
        $settings = get_option('indie500_settings', array('voting_enabled' => true, 'max_votes_per_user' => 10));
        
        if (!$settings['voting_enabled']) {
            wp_send_json_error(__('Voting is currently disabled.', 'indie500-manager'));
        }
        
        $user_ip = $_SERVER['REMOTE_ADDR'];
        if (Indie500_Database::user_has_voted($user_ip)) {
            wp_send_json_error(__('You have already voted.', 'indie500-manager'));
        }
        
        $votes = isset($_POST['votes']) ? array_map('intval', $_POST['votes']) : array();
        
        if (empty($votes)) {
            wp_send_json_error(__('Please select at least one song.', 'indie500-manager'));
        }
        
        if (count($votes) > $settings['max_votes_per_user']) {
            wp_send_json_error(sprintf(__('You can select maximum %d songs.', 'indie500-manager'), $settings['max_votes_per_user']));
        }
        
        $saved = Indie500_Database::save_votes($votes, $user_ip);
        
        if ($saved > 0) {
            wp_send_json_success(__('Thank you for voting!', 'indie500-manager'));
        } else {
            wp_send_json_error(__('Failed to save votes. Please try again.', 'indie500-manager'));
        }
    }
    
    public function export_results() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export results.', 'indie500-manager'));
        }
        
        check_admin_referer('indie500_export', '_wpnonce');
        
        $results = Indie500_Database::get_vote_results();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="indie500-results-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV Header
        fputcsv($output, array('Rank', 'Title', 'Artist', 'Year', 'Votes'));
        
        // CSV Data
        foreach ($results as $index => $result) {
            fputcsv($output, array(
                $index + 1,
                $result->title,
                $result->artist,
                $result->year,
                $result->vote_count
            ));
        }
        
        fclose($output);
        exit;
    }
}
