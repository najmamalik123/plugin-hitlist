<?php
// WordPress-compatible CSV export for votes
// Place this file in admin/export-vote-csv.php and ensure it is included by your main plugin file if needed

// Register the export action
add_action('admin_post_indie500_export_votes', 'indie500_export_votes_csv');

function indie500_export_votes_csv() {
    // Example: fetch votes from DB or file
    // Replace this with your actual vote-fetching logic
    $votes = [
        ['title' => 'Song 1', 'artist' => 'Artist 1', 'year' => 2023, 'count' => 10],
        ['title' => 'Song 2', 'artist' => 'Artist 2', 'year' => 2022, 'count' => 5],
    ];

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stemresultaten.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Titel', 'Artiest', 'Jaar', 'Stemmen']);

    foreach ($votes as $vote) {
        fputcsv($output, [$vote['title'], $vote['artist'], $vote['year'], $vote['count']]);
    }
    fclose($output);
    exit;
}

// Example HTML button for admin area (add to your admin page template):
// <a href="<?php echo admin_url('admin-post.php?action=indie500_export_votes'); ?>" class="indie500-export-btn">Export Votes as CSV</a> 