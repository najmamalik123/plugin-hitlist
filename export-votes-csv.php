<?php
/**
 * CSV Export Handler for Hit Lists Votes
 * Separate file to handle CSV exports without conflicts
 */

// Security check - prevent direct access
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php'); // Adjust path if needed
}

// Verify this is a POST request with proper nonce
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['export_year'], $_POST['export_votes_nonce']) &&
    wp_verify_nonce($_POST['export_votes_nonce'], 'export_votes_action')) {
    
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to export data.');
    }
    
    global $wpdb;
    $votes_table = $wpdb->prefix . 'hit_list_votes';
    $hitlists_table = $wpdb->prefix . 'hit_lists';
    
    $year = intval($_POST['export_year']);
    
    // Updated query for checkbox voting system (no points, just vote counts)
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT h.artist_name, h.song_title, h.year, COUNT(v.id) AS vote_count
             FROM $votes_table v
             JOIN $hitlists_table h ON v.song_id = h.id
             WHERE v.vote_year = %d
             GROUP BY v.song_id
             ORDER BY vote_count DESC",
            $year
        )
    );
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=stemresultaten_{$year}.csv");
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Add UTF-8 BOM for proper encoding
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // CSV Header - updated for checkbox voting
    fputcsv($output, ['Rank', 'Artiest', 'Nummer', 'Jaar', 'Aantal Stemmen']);
    
    $rank = 1;
    foreach ($results as $row) {
        fputcsv($output, [
            $rank++,
            $row->artist_name,
            $row->song_title,
            $row->year,
            $row->vote_count
        ]);
    }
    
    fclose($output);
    exit;
}

// If we get here, it's an invalid request
echo 'Ongeldige aanvraag';
exit;
