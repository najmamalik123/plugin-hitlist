<?php
/*
Plugin Name: Hitlijstenbeheerder met stemsysteem (Verbeterd)
Description: Beheer hitlijsten met CSV-import, frontendweergave, gebruikersstemmen en export van stemmen. Verbeterde versie met zoekfunctie en checkbox voting.
Version: 2.1
Author: <a href="https://webdesign-studenten.nl" target="_blank">Gofke Marketing</a>
*/

if (!defined('ABSPATH')) exit;

class HitListsManager {
    private $hitlists_table;
    private $votes_table;
    private $items_per_page = 20;

    public function __construct() {
        global $wpdb;
        $this->hitlists_table = $wpdb->prefix . 'hit_lists';
        $this->votes_table = $wpdb->prefix . 'hit_list_votes';
        
        register_activation_hook(__FILE__, [$this, 'create_tables']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_shortcode('hit_lists', [$this, 'hit_lists_shortcode']);
        add_shortcode('hit_lists_voting', [$this, 'hit_lists_voting_shortcode']);
        add_shortcode('hit_lists_results', [$this, 'hit_lists_results_shortcode']);
        add_shortcode('hit_lists_user_votes', [$this, 'user_votes_shortcode']);
        add_shortcode('hit_lists_overall_results', [$this, 'overall_results_shortcode']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_ajax_submit_hit_list_votes', [$this, 'handle_vote_submission']);
        add_action('wp_ajax_nopriv_submit_hit_list_votes', [$this, 'handle_vote_submission']);
        add_action('wp_ajax_search_songs', [$this, 'handle_song_search']);
        add_action('wp_ajax_nopriv_search_songs', [$this, 'handle_song_search']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Add user registration and login shortcodes
        add_shortcode('indie_register', [$this, 'registration_shortcode']);
        add_shortcode('indie_login', [$this, 'login_shortcode']);
        add_shortcode('indie_user_dashboard', [$this, 'user_dashboard_shortcode']);
        add_shortcode('indie_homepage_stats', [$this, 'homepage_stats_shortcode']);
        add_action('wp_ajax_indie_register_user', [$this, 'handle_user_registration']);
        add_action('wp_ajax_nopriv_indie_register_user', [$this, 'handle_user_registration']);
        add_action('wp_ajax_indie_login_user', [$this, 'handle_user_login']);
        add_action('wp_ajax_nopriv_indie_login_user', [$this, 'handle_user_login']);
        add_action('wp_ajax_nopriv_indie_login_user', [$this, 'handle_user_login']);

// ✅ Register Top 500 shortcode
add_shortcode('indie500_top500', [$this, 'top500_shortcode']);

    }

    // Create DB tables (updated structure)
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql_hitlists = "CREATE TABLE {$this->hitlists_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            artist_name varchar(255) NOT NULL,
            song_title varchar(255) NOT NULL,
            year mediumint(4) NOT NULL,
            ranking mediumint(4) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY year (year),
            KEY artist_name (artist_name),
            KEY song_title (song_title)
        ) $charset_collate;";
        dbDelta($sql_hitlists);

        // Updated votes table for checkbox voting
        $sql_votes = "CREATE TABLE {$this->votes_table} (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) NOT NULL,
            song_id BIGINT(20) NOT NULL,
            vote_year YEAR NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_song_year (user_id, song_id, vote_year),
            KEY song_id (song_id),
            KEY vote_year (vote_year)
        ) $charset_collate;";
        dbDelta($sql_votes);
    }

    public function admin_menu() {
        $required_cap = 'manage_hitlists';
        $is_admin = current_user_can('administrator');
        $menu_cap = $is_admin ? 'read' : $required_cap;

        add_menu_page(
            'Hitlijsten',
            'Hitlijsten',
            $menu_cap,
            'hit-lists',
            [$this, 'admin_page'],
            'dashicons-playlist-audio',
            20
        );

        add_submenu_page(
            'hit-lists',
            'CSV Importeren',
            'CSV Importeren',
            $menu_cap,
            'hit-lists-csv-import',
            [$this, 'csv_import_page']
        );

        add_submenu_page(
            'hit-lists',
            'Stemmen exporteren',
            'Stemmen exporteren',
            $menu_cap,
            'hit-lists-export-votes',
            [$this, 'export_votes_page']
        );

        add_submenu_page(
            'hit-lists',
            'Nieuw Titel toevoegen',
            'Nieuw Titel toevoegen',
            $menu_cap,
            'hit-lists-add-song',
            [$this, 'render_add_songs_page']
        );

        add_submenu_page(
            'hit-lists',
            'Stemresultaten',
            'Stemresultaten',
            $menu_cap,
            'hit-lists-view-votes',
            [$this, 'render_view_votes_page']
        );
    }

    // Check if voting is allowed (November only)
    private function is_voting_allowed() {
        return true; // Always allow voting
    }

    // Enhanced admin page with search
    public function admin_page() {
        global $wpdb;

        // Handle delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['song_id'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_song_' . $_GET['song_id'])) {
                $song_id = intval($_GET['song_id']);
                
                // Delete votes first
                $wpdb->delete($this->votes_table, ['song_id' => $song_id]);
                
                // Delete song
                $deleted = $wpdb->delete($this->hitlists_table, ['id' => $song_id]);
                
                if ($deleted) {
                    echo '<div class="notice notice-success"><p>Nummer succesvol verwijderd!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Fout bij verwijderen.</p></div>';
                }
            }
        }
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $year_filter = isset($_GET['year']) ? intval($_GET['year']) : '';
        
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $this->items_per_page;

        // Build query with search and filters
        $where_conditions = [];
        $query_params = [];

        if ($search) {
            $where_conditions[] = "(artist_name LIKE %s OR song_title LIKE %s)";
            $query_params[] = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if ($year_filter) {
            $where_conditions[] = "year = %d";
            $query_params[] = $year_filter;
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $total_items = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->hitlists_table} $where_clause",
            $query_params
        ));

        $total_pages = ceil($total_items / $this->items_per_page);

        $query_params[] = $this->items_per_page;
        $query_params[] = $offset;

        $songs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->hitlists_table} $where_clause ORDER BY year DESC, artist_name ASC, song_title ASC LIMIT %d OFFSET %d",
            $query_params
        ));

        $years = $wpdb->get_col("SELECT DISTINCT year FROM {$this->hitlists_table} ORDER BY year DESC");
        ?>
        <div class="wrap">
            <h1>Hitlijsten - Titel</h1>
            
            <!-- Search and Filter Form -->
            <div class="hitlists-admin-filters">
                <form method="get">
                    <input type="hidden" name="page" value="hit-lists">
                    <p class="search-box">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Zoek op artiest of titel...">
                        <select name="year">
                            <option value="">Alle jaren</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php selected($year_filter, $year); ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="submit" class="button" value="Zoeken">
                        <?php if ($search || $year_filter): ?>
                            <a href="<?php echo admin_url('admin.php?page=hit-lists'); ?>" class="button">Opnieuw instellen</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>

            <div class="tablenav top">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo; Vorige'),
                        'next_text' => __('Volgende &raquo;'),
                        'total' => $total_pages,
                        'current' => $current_page,
                    ]);
                    ?>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Artiest</th>
                        <th>Titel</th>
                        <th>Jaar</th>
                        <th>Rangschikking</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($songs): foreach ($songs as $song): ?>
                        <tr>
                            <td><?php echo esc_html($song->artist_name); ?></td>
                            <td><?php echo esc_html($song->song_title); ?></td>
                            <td><strong><?php echo esc_html($song->year); ?></strong></td>
                            <td><?php echo esc_html($song->ranking); ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(
                                    admin_url('admin.php?page=hit-lists&action=delete&song_id=' . $song->id),
                                    'delete_song_' . $song->id
                                ); ?>" 
                                class="button button-small" 
                                onclick="return confirm('Weet je zeker dat je dit titel wilt verwijderen?')">
                                    Verwijderen
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5">Geen titels gevonden.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo; Vorige'),
                        'next_text' => __('Volgende &raquo;'),
                        'total' => $total_pages,
                        'current' => $current_page,
                    ]);
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    // Keep existing CSV import functionality
    public function csv_import_page() {
        global $wpdb;
        if (isset($_POST['upload_csv']) && !empty($_FILES['csv_file']['tmp_name'])) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            fgetcsv($handle, 0, ';'); // Skip header
            $imported = 0;
            $ranking = 1;

            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                if (count($data) < 3) continue;
                $artist = sanitize_text_field(trim($data[0]));
                $song = sanitize_text_field(trim($data[1]));
                $year = intval(trim($data[2]));

                if ($artist && $song && $year > 0) {
                    $wpdb->insert($this->hitlists_table, [
                        'artist_name' => $artist,
                        'song_title' => $song,
                        'year' => $year,
                        'ranking' => $ranking,
                    ]);
                    $ranking++;
                    $imported++;
                }
            }
            fclose($handle);
            echo '<div class="notice notice-success"><p>' . $imported . ' records succesvol geïmporteerd.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>CSV Importeren - Hitlijsten</h1>
            <form method="post" enctype="multipart/form-data">
                <p>
                    <label for="csv_file">Selecteer CSV-bestand (gescheiden door puntkomma):</label><br>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                </p>
                <p>
                    <input type="submit" name="upload_csv" class="button button-primary" value="Uploaden">
                </p>
            </form>
            <p>CSV-indeling: Artiest;Titel;Jaar</p>
        </div>
        <?php
    }

    // Keep existing export functionality
    public function export_votes_page() {
        global $wpdb;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $this->items_per_page;
        $total_items = $wpdb->get_var("SELECT COUNT(DISTINCT vote_year) FROM {$this->votes_table}");
        $total_pages = ceil($total_items / $this->items_per_page);

        $years = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT vote_year FROM {$this->votes_table} ORDER BY vote_year DESC LIMIT %d OFFSET %d",
                $this->items_per_page,
                $offset
            )
        );

        if (isset($_GET['export_year'])) {
            $year = intval($_GET['export_year']);
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT h.artist_name, h.song_title, h.year, COUNT(v.id) AS vote_count
                     FROM {$this->votes_table} v
                     JOIN {$this->hitlists_table} h ON v.song_id = h.id
                     WHERE v.vote_year = %d
                     GROUP BY v.song_id
                     ORDER BY vote_count DESC",
                    $year
                )
            );

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="stemresultaten_' . $year . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Rang', 'Artiest', 'Titel', 'Jaar', 'Aantal Stemmen']);
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
        ?>
        <div class="wrap">
            <h1>Stemresultaten exporteren</h1>
            <form method="post" action="<?php echo plugins_url('export-votes-csv.php', __FILE__); ?>">
                <?php wp_nonce_field('export_votes_action', 'export_votes_nonce'); ?>
                <label for="export_year">Selecteer jaar:</label>
                <select name="export_year" id="export_year" required>
                    <?php if ($years): foreach ($years as $year): ?>
                        <option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
                    <?php endforeach; else: ?>
                        <option value="">Geen stemgegevens gevonden</option>
                    <?php endif; ?>
                </select>
                <button type="submit" class="button button-primary">CSV exporteren</button>
            </form>
        </div>
        <?php
    }

    // Keep existing add song functionality
    public function render_add_songs_page() {
        global $wpdb;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hitlists_add_song_submit'])) {
            if (!check_admin_referer('hitlists_add_song', 'hitlists_nonce')) {
                wp_die('Nonce verification failed');
            }
            $artist = sanitize_text_field($_POST['artist_name']);
            $song = sanitize_text_field($_POST['song_title']);
            $year = intval($_POST['year']);

            if ($artist && $song && $year > 1900 && $year <= date('Y')) {
                $wpdb->insert($this->hitlists_table, [
                    'artist_name' => $artist,
                    'song_title' => $song,
                    'year' => $year,
                ]);
                echo '<div class="notice notice-success"><p>Nummer succesvol toegevoegd!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Vul geldige gegevens in.</p></div>';
            }
        }
        ?>
        <div class="wrap hitlist-form-container">
            <h1>Nieuw titel toevoegen</h1>
            <div class="hitlist-form-card">
                <form method="post">
                    <?php wp_nonce_field('hitlists_add_song', 'hitlists_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="artist_name">Artiest</label></th>
                            <td><input type="text" name="artist_name" id="artist_name" required class="regular-text hitlist-input-field"></td>
                        </tr>
                        <tr>
                            <th><label for="song_title">Titel</label></th>
                            <td><input type="text" name="song_title" id="song_title" required class="regular-text hitlist-input-field"></td>
                        </tr>
                        <tr>
                            <th><label for="year">Jaar</label></th>
                            <td><input type="number" name="year" id="year" min="1900" max="<?php echo date('Y'); ?>" required class="regular-text hitlist-input-field"></td>
                        </tr>
                    </table>
                    <?php submit_button('Toevoegen', 'primary', 'hitlists_add_song_submit'); ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_view_votes_page() {
    global $wpdb;
    
    // Handle search and filters
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $year_filter = isset($_GET['year']) ? intval($_GET['year']) : '';
    $category_filter = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
    
    // Pagination
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $items_per_page = 25; // Show 25 votes per page
    $offset = ($current_page - 1) * $items_per_page;
    
    // Build query conditions
    $where_conditions = ['1=1']; // Always true condition to start with
    $query_params = [];
    
    if ($search) {
        $where_conditions[] = "(h.artist_name LIKE %s OR h.song_title LIKE %s OR v.user_email LIKE %s)";
        $query_params[] = '%' . $wpdb->esc_like($search) . '%';
        $query_params[] = '%' . $wpdb->esc_like($search) . '%';
        $query_params[] = '%' . $wpdb->esc_like($search) . '%';
    }
    
    if ($year_filter) {
        $where_conditions[] = "v.vote_year = %d";
        $query_params[] = $year_filter;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get total count for pagination
    $total_votes = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) 
         FROM {$this->votes_table} v 
         JOIN {$this->hitlists_table} h ON v.song_id = h.id 
         $where_clause",
        $query_params
    ));
    
    $total_pages = ceil($total_votes / $items_per_page);
    
    // Get votes with details
    $votes_query_params = array_merge($query_params, [$items_per_page, $offset]);
    $votes = $wpdb->get_results($wpdb->prepare(
        "SELECT v.id as vote_id, v.user_id, v.vote_year, v.ip_address, v.user_email, v.created_at,
                h.artist_name, h.song_title, h.year as song_year,
                u.display_name, u.user_login
         FROM {$this->votes_table} v 
         JOIN {$this->hitlists_table} h ON v.song_id = h.id 
         LEFT JOIN {$wpdb->users} u ON v.user_id = u.ID
         $where_clause 
         ORDER BY v.created_at DESC 
         LIMIT %d OFFSET %d",
        $votes_query_params
    ));
    
    // Get vote statistics
    $vote_stats = $wpdb->get_results(
        "SELECT h.artist_name, h.song_title, h.year, COUNT(v.id) AS vote_count
         FROM {$this->votes_table} v
         JOIN {$this->hitlists_table} h ON v.song_id = h.id
         GROUP BY v.song_id
         ORDER BY vote_count DESC
         LIMIT 20"
    );
    
    // Get available years for filter
    $years = $wpdb->get_col("SELECT DISTINCT vote_year FROM {$this->votes_table} ORDER BY vote_year DESC");
    
    // Get total unique voters
    $unique_voters = $wpdb->get_var("SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_email END) FROM {$this->votes_table}");
    
    ?>
    <div class="wrap">
    <h1>Beheer Stemmen - Alle Stemmen (Admin)</h1>

    <!-- Statistiekkaarten -->
    <div class="hitlists-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div class="hitlists-stat-card">
            <h3>Totaal Aantal Stemmen</h3>
            <div><?php echo number_format($total_votes); ?></div>
        </div>
        <div class="hitlists-stat-card">
            <h3>Unieke Stemmers</h3>
            <div><?php echo number_format($unique_voters); ?></div>
        </div>
        <div class="hitlists-stat-card">
            <h3>Stemmen Dit Jaar</h3>
            <div>
                <?php 
                $current_year_votes = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->votes_table} WHERE vote_year = %d", 
                    date('Y')
                ));
                echo number_format($current_year_votes); 
                ?>
            </div>
        </div>
        <div class="hitlists-stat-card">
            <h3>Beschikbare Jaren</h3>
            <div><?php echo count($years); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="hitlists-admin-filters">
        <h3>Zoeken & Filteren</h3>
        <form method="get">
            <input type="hidden" name="page" value="hit-lists-view-votes">
            <div>
                <label for="search-votes"><strong>Zoek:</strong></label>
                <input type="search" id="search-votes" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Zoek op artiest, nummer of e-mail...">
            </div>
            <div>
                <label for="year-filter"><strong>Jaar:</strong></label>
                <select name="year" id="year-filter">
                    <option value="">Alle Jaren</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php selected($year_filter, $year); ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="items-per-page"><strong>Per Pagina:</strong></label>
                <select name="per_page" id="items-per-page">
                    <option value="25" <?php selected($items_per_page, 25); ?>>25</option>
                    <option value="50" <?php selected($items_per_page, 50); ?>>50</option>
                    <option value="100" <?php selected($items_per_page, 100); ?>>100</option>
                </select>
            </div>
            <div>
                <input type="submit" class="button button-primary" value="Filteren">
                <?php if ($search || $year_filter): ?>
                    <a href="<?php echo admin_url('admin.php?page=hit-lists-view-votes'); ?>" class="button">Resetten</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabs -->
    <div class="nav-tab-wrapper">
        <a href="#all-votes" class="nav-tab nav-tab-active" onclick="showTab('all-votes')">Alle Stemmen</a>
        <a href="#vote-stats" class="nav-tab" onclick="showTab('vote-stats')">Stemstatistieken</a>
    </div>

    <!-- Tab Alle Stemmen -->
    <div id="all-votes" class="tab-content">
        <div class="tablenav top">
            <div class="alignleft actions">
                <span class="displaying-num"><?php echo number_format($total_votes); ?> stemmen in totaal</span>
            </div>
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('‹ Vorige'),
                    'next_text' => __('Volgende ›'),
                    'total' => $total_pages,
                    'current' => $current_page,
                ]);
                ?>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Stem ID</th>
                    <th>Nummerdetails</th>
                    <th>Stemmer Informatie</th>
                    <th>Stemjaar</th>
                    <th>Inzenddatum</th>
                    <th>IP-adres</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($votes): foreach ($votes as $vote): ?>
                    <tr>
                        <td><strong>#<?php echo $vote->vote_id; ?></strong></td>
                        <td>
                            <strong><?php echo esc_html($vote->song_title); ?></strong><br>
                            <span>door <?php echo esc_html($vote->artist_name); ?></span><br>
                            <small>Jaar: <?php echo $vote->song_year; ?></small>
                        </td>
                        <td>
                            <?php if ($vote->user_id > 0): ?>
                                <strong><?php echo esc_html($vote->display_name ?: $vote->user_login); ?></strong><br>
                                <small>Geregistreerde gebruiker (ID: <?php echo $vote->user_id; ?>)</small>
                            <?php else: ?>
                                <small>Gastgebruiker</small>
                            <?php endif; ?>
                            <?php if ($vote->user_email): ?>
                                <br><small><?php echo esc_html($vote->user_email); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="vote-year-badge"><?php echo $vote->vote_year; ?></span>
                        </td>
                        <td>
                            <?php 
                            $date = new DateTime($vote->created_at);
                            echo $date->format('d M Y');
                            echo '<br><small>' . $date->format('H:i:s') . '</small>';
                            ?>
                        </td>
                        <td>
                            <code><?php echo esc_html($vote->ip_address); ?></code>
                        </td>
                        <td>
                            <button type="button" class="button button-small" onclick="viewVoteDetails(<?php echo $vote->vote_id; ?>)">
                                Details Bekijken
                            </button>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7">Geen stemmen gevonden die voldoen aan je zoekopdracht.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('‹ Vorige'),
                    'next_text' => __('Volgende ›'),
                    'total' => $total_pages,
                    'current' => $current_page,
                ]);
                ?>
            </div>
        </div>
    </div>

    <!-- Tab Stemstatistieken -->
    <div id="vote-stats" class="tab-content" style="display: none;">
        <h3>Meest Gestemde Nummers (All Time)</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Rang</th>
                    <th>Nummertitel</th>
                    <th>Artiest</th>
                    <th>Jaar</th>
                    <th>Totaal Stemmen</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                foreach ($vote_stats as $stat): 
                    $percentage = ($total_votes > 0) ? ($stat->vote_count / $total_votes) * 100 : 0;
                ?>
                    <tr class="<?php echo $rank <= 10 ? 'top-10-song' : ''; ?>">
                        <td><strong><?php echo $rank++; ?></strong></td>
                        <td><strong><?php echo esc_html($stat->song_title); ?></strong></td>
                        <td><?php echo esc_html($stat->artist_name); ?></td>
                        <td><?php echo $stat->year; ?></td>
                        <td><span><?php echo $stat->vote_count; ?></span></td>
                        <td><?php echo number_format($percentage, 2); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

    <style>
    .top-10-song {
        background-color: #fff3cd !important;
    }
    .tab-content {
        background: #fff;
        border: 1px solid #ddd;
        border-top: none;
        padding: 20px;
    }
    .nav-tab-active {
        background: #fff !important;
        border-bottom: 1px solid #fff !important;
    }
    .vote-year-badge {
        display: inline-block;
    }
    </style>

    <script>
    function showTab(tabId) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.style.display = 'none';
        });
        
        // Remove active class from all tabs
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.classList.remove('nav-tab-active');
        });
        
        // Show selected tab
        document.getElementById(tabId).style.display = 'block';
        
        // Add active class to clicked tab
        event.target.classList.add('nav-tab-active');
    }

    function viewVoteDetails(voteId) {
        // You can implement a modal or redirect to detailed view
        alert('Vote ID: ' + voteId + '\nDetailed view functionality can be implemented here.');
    }

    // Auto-submit form when per_page changes
    document.getElementById('items-per-page').addEventListener('change', function() {
        this.form.submit();
    });
    </script>
    <?php
}

    // REST API routes
    public function register_rest_routes() {
        register_rest_route('hit-lists/v1', '/data/(?P<year>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_hit_lists_data'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('hit-lists/v1', '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_search_songs'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function rest_get_hit_lists_data($request) {
        global $wpdb;
        $year = intval($request['year']);
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, artist_name, song_title, year, ranking
                 FROM {$this->hitlists_table}
                 WHERE year = %d
                 ORDER BY ranking ASC",
                $year
            ), ARRAY_A
        );
        return rest_ensure_response($results);
    }

    public function rest_search_songs($request) {
        global $wpdb;
        $search = sanitize_text_field($request['search']);
        $year = intval($request['year']);
        
        $where_conditions = [];
        $query_params = [];

        if ($search) {
            $where_conditions[] = "(artist_name LIKE %s OR song_title LIKE %s)";
            $query_params[] = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if ($year) {
            $where_conditions[] = "year = %d";
            $query_params[] = $year;
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, artist_name, song_title, year, ranking
             FROM {$this->hitlists_table} $where_clause
             ORDER BY artist_name, song_title
             LIMIT 100",
            $query_params
        ), ARRAY_A);

        return rest_ensure_response($results);
    }

    // AJAX search handler
    public function handle_song_search() {
        $search = sanitize_text_field($_GET['search'] ?? '');
        $year = intval($_GET['year'] ?? 0);
        
        global $wpdb;
        $where_conditions = [];
        $query_params = [];

        if ($search) {
            $where_conditions[] = "(artist_name LIKE %s OR song_title LIKE %s)";
            $query_params[] = '%' . $wpdb->esc_like($search) . '%';
            $query_params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if ($year) {
            $where_conditions[] = "year = %d";
            $query_params[] = $year;
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $songs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, artist_name, song_title, year
             FROM {$this->hitlists_table} $where_clause
             ORDER BY artist_name, song_title
             LIMIT 50",
            $query_params
        ));

        wp_send_json_success($songs);
    }

    // Enhanced frontend shortcode with search and year browsing
    public function hit_lists_shortcode($atts) {
        global $wpdb;
        $years = $wpdb->get_col("SELECT DISTINCT year FROM {$this->hitlists_table} ORDER BY year DESC");
        $rest_base = get_rest_url(null, 'hit-lists/v1/data/');
        $search_url = admin_url('admin-ajax.php?action=search_songs');
        
        ob_start();
        ?>
    
        <style>
        .hitlists-plugin {
          font-family: 'Segoe UI', sans-serif;
          max-width: 1200px;
          margin: auto;
          padding: 20px;
        }
        .hitlists-header-info h2 {
          font-size: 24px;
          margin-bottom: 5px;
        }
        .hitlists-header-info p {
          margin-bottom: 20px;
        }
        .hitlists-controls {
          display: flex;
          flex-wrap: wrap;
          gap: 15px;
          align-items: flex-end;
          margin-bottom: 25px;
        }
        .hitlists-controls .control-group {
          flex: 1;
          min-width: 200px;
        }
        .hitlists-reset-btn {
          background-color: #009fe3;
          color: white;
          padding: 10px 25px;
          font-weight: bold;
          border: none;
          border-radius: 6px;
          cursor: pointer;
          width: 100%;
        }
        .hitlists-reset-btn:hover {
          background-color: #009fe3;
        }
        .hitlists-results-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
          gap: 20px;
          margin-top: 20px;
        }
        .hitlists-result-card {
          background-color: #f8f9fa;
          border: 1px solid #e0e0e0;
          border-radius: 10px;
          padding: 20px;
          box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
          transition: transform 0.2s ease;
        }
        .hitlists-result-card:hover {
          transform: translateY(-4px);
        }
        .hitlists-card-row {
          display: flex;
          justify-content: space-between;
          align-items: center;
          gap: 10px;
        }
        .song-title {
          flex: 2;
          font-size: 16px;
          font-weight: 600;
          color: #222;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }
        .year-badge {
  flex: 1;
  font-size: 13px;
  background-color: #009fe3;
  color: white;
  padding: 4px 12px;
  border-radius: 20px;
  text-align: center;
  min-width: 55px;
  max-width: 70px;
  font-weight: 600;
}

        .artist-name {
          flex: 2;
          font-size: 15px;
          color: #555;
          text-align: right;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }
        .hitlists-pagination {
          display: flex;
          justify-content: center;
          align-items: center;
          gap: 10px;
          margin-top: 30px;
        }
        .pagination-btn {
          padding: 8px 20px;
          font-size: 14px;
          font-weight: 500;
          border: none;
          background-color: #009fe3;
          color: white;
          border-radius: 6px;
          cursor: pointer;
          transition: background-color 0.3s ease;
        }
        .pagination-btn:disabled {
          background-color: #ccc;
          cursor: not-allowed;
        }
        .pagination-btn:hover:not(:disabled) {
          background-color: #009fe3;
        }
        .hitlists-results-grid {
  display: grid;
  grid-template-columns: repeat(1, 1fr); /* always 2 columns */
  gap: 10px;
  margin-top: 20px;
}
.hitlists-top-card {
  background-color: #009fe3; /* Bright blue */
  color: white;
  padding: 10px;
  border-radius: 10px;
  text-align: center;
  margin-bottom: 10px;;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  transition: transform 0.2s ease;
}

.hitlists-top-card h2 {
  font-size: 28px;
  margin-bottom: 10px;
  font-weight: 700;
}

.hitlists-top-card p {
  font-size: 16px;
  margin: 0;
  opacity: 0.95;
}

        </style>
    
    <div class="hitlists-plugin">
    <div class="hitlists-top-card">
      <h2>Indie500 Archief</h2>
      <p>Bekijk de hitlijsten van voorgaande jaren</p>
    </div>

    <div class="hitlists-controls">
      <div class="control-group">
        <label for="search-input">Zoeken</label>
        <input type="text" id="search-input" placeholder="Zoek op artiest of titel...">
      </div>
      <div class="control-group">
        <label for="year-select">Jaar</label>
        <select id="year-select">
          <option value="">Alle jaren</option>
          <?php foreach ($years as $year): ?>
            <option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="control-group">
        <button id="reset-btn" class="hitlists-reset-btn">Resetten</button>
      </div>
    </div>

    <div id="results-container">
      <div class="hitlists-empty">
        <h3>Selecteer een jaar om de hitlijst te bekijken</h3>
        <p>Of gebruik de zoekfunctie om specifieke titels te vinden</p>
      </div>
    </div>

    <div id="pagination-container" class="hitlists-pagination" style="display:none;">
      <button id="prev-btn" class="pagination-btn">Vorige</button>
      <span id="page-info" class="pagination-info">Pagina 1</span>
      <button id="next-btn" class="pagination-btn">Volgende</button>
    </div>
    </div>

    <script>
    const restBaseURL = '<?php echo esc_url_raw($rest_base); ?>';
    const searchURL = '<?php echo esc_url_raw($search_url); ?>';
    const resultsContainer = document.getElementById('results-container');
    const searchInput = document.getElementById('search-input');
    const yearSelect = document.getElementById('year-select');
    const resetBtn = document.getElementById('reset-btn');
    const paginationContainer = document.getElementById('pagination-container');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    const pageInfo = document.getElementById('page-info');

    let currentData = [];
    let currentPage = 1;
    const itemsPerPage = 100;

    let searchTimeout;

    const escapeHtml = (text) => {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    };

    const renderResults = (data) => {
      if (!data.length) {
        resultsContainer.innerHTML = `
          <div class="hitlists-empty">
            <h3>Geen resultaten gevonden</h3>
            <p>Probeer andere zoektermen of selecteer een ander jaar</p>
          </div>`;
        paginationContainer.style.display = 'none';
        return;
      }

      const start = (currentPage - 1) * itemsPerPage;
      const paginatedData = data.slice(start, start + itemsPerPage);
      const year = yearSelect.value;
      const search = searchInput.value.trim();
      const headerText = year && search ? `Resultaten voor "${search}" in ${year}`
                        : year ? `Indie500 Hitlijst ${year}`
                        : search ? `Zoekresultaten voor "${search}"`
                        : 'Alle titels';

      let html = `
        <div class="hitlists-header-info">
          <h2>${headerText}</h2>
          <p>${data.length} titels gevonden</p>
        </div>
        <div class="hitlists-results-grid">
      `;

      paginatedData.forEach((item, i) => {
        html += `
          <div class="hitlists-result-card fade-in" style="animation-delay: ${i * 0.07}s">
            <div class="hitlists-card-row">
              <span class="song-title">${i + 1}. ${escapeHtml(item.artist_name)} - ${escapeHtml(item.song_title)}</span>
              <span class="year-badge">${item.year}</span>
            </div>
          </div>
        `;
      });

      html += '</div>';
      resultsContainer.innerHTML = html;

      paginationContainer.style.display = data.length > 100 ? 'flex' : 'none';

      updatePagination(data.length);
    };

    const updatePagination = (totalItems) => {
      const totalPages = Math.ceil(totalItems / itemsPerPage);
      pageInfo.textContent = `Pagina ${currentPage} van ${totalPages}`;
      prevBtn.disabled = currentPage === 1;
      nextBtn.disabled = currentPage === totalPages;
    };

    const sortData = (data) => {
      return [...data].sort((a, b) => {
        const artistCmp = a.artist_name.localeCompare(b.artist_name);
        if (artistCmp !== 0) return artistCmp;
        const titleCmp = a.song_title.localeCompare(b.song_title);
        return titleCmp !== 0 ? titleCmp : b.year - a.year;
      });
    };

    const loadData = (year) => {
      resultsContainer.innerHTML = `<div class="hitlists-loading"><p>Bezig met laden...</p></div>`;
      fetch(`${restBaseURL}${year}`)
        .then(res => res.json())
        .then(data => {
          currentData = data;
          currentPage = 1;
          renderResults(sortData(data));
        })
        .catch(() => {
          resultsContainer.innerHTML = `<div class="hitlists-error"><h3>Fout bij laden van gegevens</h3></div>`;
        });
    };

    const searchSongs = () => {
      const search = searchInput.value.trim();
      const year = yearSelect.value;

      if (!search && !year) {
        resetView();
        return;
      }

      resultsContainer.innerHTML = `<div class="hitlists-loading"><p>Zoeken...</p></div>`;
      const url = new URL(searchURL);
      if (search) url.searchParams.append('search', search);
      if (year) url.searchParams.append('year', year);

      fetch(url)
        .then(res => res.json())
        .then(data => {
          currentData = data.data || data;
          currentPage = 1;
          renderResults(sortData(currentData));
        })
        .catch(() => {
          resultsContainer.innerHTML = `<div class="hitlists-error"><h3>Fout bij zoeken</h3></div>`;
        });
    };

    const resetView = () => {
      searchInput.value = '';
      yearSelect.value = '';
      currentData = [];
      currentPage = 1;
      resultsContainer.innerHTML = `
        <div class="hitlists-empty">
          <h3>Selecteer een jaar om de hitlijst te bekijken</h3>
          <p>Of gebruik de zoekfunctie om specifieke titels te vinden</p>
        </div>`;
      paginationContainer.style.display = 'none';
    };

    const changePage = (dir) => {
      const maxPages = Math.ceil(currentData.length / itemsPerPage);
      if (dir === 'next' && currentPage < maxPages) currentPage++;
      else if (dir === 'prev' && currentPage > 1) currentPage--;
      renderResults(sortData(currentData));
    };

    yearSelect.addEventListener('change', () => yearSelect.value ? loadData(yearSelect.value) : searchSongs());
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => searchSongs(), 500);
    });
    resetBtn.addEventListener('click', resetView);
    prevBtn.addEventListener('click', () => changePage('prev'));
    nextBtn.addEventListener('click', () => changePage('next'));
    </script>

    <?php
    return ob_get_clean();
}
    //vote function 
    public function hit_lists_voting_shortcode() {
        global $wpdb;
    
        $current_year = date('Y');
        $previous_year = $current_year - 1;
        $excluded_titles = ["334vv", "88aaa", "aaaa", "aaabb", "777", "888", "cv"];
        $placeholders = implode(',', array_fill(0, count($excluded_titles), '%s'));
    
        $query = $wpdb->prepare("
            SELECT id, artist_name, song_title
            FROM {$this->hitlists_table}
            WHERE year = %d AND song_title NOT IN ($placeholders)
            ORDER BY artist_name, song_title
        ", array_merge([$previous_year], $excluded_titles));
    
        $songs = $wpdb->get_results($query);
    
        ob_start(); ?>
       <div class="indie500-voting-card">
    <h2 class="title">Stem op jouw Indie500 favorieten</h2>
    <p class="subtitle">Selecteer maximaal 10 nummers uit de lijst van <?php echo date('Y'); ?>.</p>

    <input type="text" id="search" placeholder="Zoek op artiest of titel..." />
</div>

    
            <form id="voting-form">
                <div id="song-list"></div>
                <div id="pagination"></div>
                <button type="button" id="next-btn" disabled>Stem</button>
            </form>
    
            <div id="confirmation-section" style="display:none;">
                <h3>Hier is de lijst van nummers die je hebt geselecteerd om op te stemmen</h3>
                <ul id="selected-songs-list"></ul>
                <button type="button" id="submit-vote-btn">Dien je keuzes in en ga verder.</button>
            </div>
    
            <div id="thank-you" style="display:none;">
    <h3>Thank you for voting!</h3>
    <p>Please wait a moment while we finalize the list of the highest-rated votes. The results will be updated shortly.</p>
    <ul id="final-vote-list"></ul>
</div>
    <!-- New Card Container for Social Links -->
    <div class="social-card">
        <h4>Deel je stemlijst:</h4>
        <div class="share-links">
            <a id="share-facebook" class="fb" href="#" target="_blank"><i class="fab fa-facebook-f"></i></a>
            <a id="share-twitter" class="tw" href="#" target="_blank"><i class="fab fa-twitter"></i></a>
            <a id="share-whatsapp" class="wa" href="#" target="_blank"><i class="fab fa-whatsapp"></i></a>
            <a id="share-instagram" class="ig" href="https://www.instagram.com" target="_blank"><i class="fab fa-instagram"></i></a>
        </div>
    </div>
</div>
            <div class="top-500-section">
                <h3>Top 500 uit <?php echo $previous_year; ?></h3>
                <div id="top-500-list"><?php echo do_shortcode('[indie500_top500]'); ?></div>
            </div>
    
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
    .indie500-voting-wrapper {
        font-family: "Segoe UI", sans-serif;
        max-width: 960px;
        margin: auto;
        padding: 20px;
    }
    .title {
        font-size: 28px;
        color:rgb(251, 254, 255);
        margin-bottom: 10px;
    }
    .subtitle {
        font-size: 16px;
        margin-bottom: 20px;
    }
    .indie500-voting-card {
    background-color: #009fe3; /* Bright blue background */
    color: white; /* White text */
    padding: 20px; /* Padding for spacing inside the card */
    border-radius: 10px; /* Rounded corners for a modern look */
    text-align: center; /* Center the text inside */
    margin-bottom: 20px; /* Space below the card */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Light shadow effect */
    transition: transform 0.2s ease; /* Smooth hover effect */
}
    #search {
        width: 100%;
        padding: 12px;
        margin-bottom: 20px;
        border: 1px solid #ccc;
        border-radius: 6px;
    }

    .song-item {
        display: flex;
        align-items: center;
        padding: 10px;
        border: 2px solid #eee;
        border-radius: 6px;
        margin-bottom: 10px;
        background-color:#ffffff;
        padding:20px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .song-item.selected {
        background-color: #009fe3;
        color: white;
    }

    .song-item input {
        margin-right: 10px;
    }

    #pagination-container {
    display: flex; /* Makes the pagination buttons align correctly in a row */
    gap: 20px; /* Adds space between all buttons */
    justify-content: center;
    margin: right 10px; /* Centers the pagination buttons */
}

#pagination-container {
    display: flex;
    gap: 80px; /* Increased space between buttons */
    justify-content: center;
    margin-bottom: 20px;
    flex-wrap: wrap; /* Allows the buttons to wrap if necessary */

}

#pagination-btn {
    padding: 12px 25px;
    font-size: 16px;
    font-weight: 500;
    border: none;
    background-color: #009fe3;
    color: white;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    margin-left: 10px;
    margin-right: 10px;
    display: inline-block;
}

#pagination-btn:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}


#pagination-btn:hover:not(:disabled) {
    background-color: #009fe3; /* Darker blue on hover */
}



button {
    appearance: auto;
    font-style: normal;
    font-variant-ligatures: normal;
    font-variant-caps: normal;
    font-variant-numeric: normal;
    font-variant-east-asian: normal;
    font-variant-alternates: normal;
    font-variant-position: normal;
    font-variant-emoji: normal;
    font-weight: 500;
    font-stretch: normal;
    font-size: 14px; /* Font size remains the same */
    font-family: 'Segoe UI', sans-serif; /* Custom font family */
    font-optical-sizing: auto;
    font-size-adjust: auto;
    font-kerning: normal;
    font-feature-settings: normal;
    font-variation-settings: normal;
    text-rendering: auto;
    color: white; /* White text */
    letter-spacing: normal;
    word-spacing: normal;
    line-height: normal;
    text-transform: none;
    text-indent: 0px;
    text-shadow: none; /* No shadow */
    display: inline-block;
    text-align: center;
    cursor: pointer;
    box-sizing: border-box;
    background-color: #009fe3; /* Blue background */
    margin: 8px 10px; /* Space between buttons */
    padding: 10px 30px; /* Larger padding */
    border-width: 2px;
    border-style: none;
    border-color: rgb(255, 255, 255); /* Border color */
    border-radius: 10px; /* Smooth rounded corners */
    transition: background-color 0.3s ease; /* Smooth background transition on hover */
}

button:hover {
    background-color: #007bb5; /* Darker shade for hover effect */
}


button:hover {
    background-color: #007bb5; /* Darker blue on hover */
}

button:disabled {
    background-color: #ccc; /* Disabled button background color */
    color: #888; /* Disabled button text color */
    cursor: not-allowed; /* Disable cursor on hover when disabled */
}


button:hover {
    background-color: #009fe3; /* Darker blue on hover */
}

button:disabled {
    background-color: #ccc; /* Disabled button background color */
    color: #888; /* Disabled button text color */
    cursor: not-allowed; /* Disable cursor on hover when disabled */
}




button:hover {
    background-color: #009fe3; /* Darker blue when hovered */
    color: white; /* Maintain white text on hover */
}

button:disabled {
    background-color: #ccc; /* Disabled button background color */
    color: #888; /* Light grey text for disabled state */
    cursor: not-allowed; /* Show "not-allowed" cursor for disabled state */
}

#thank-you {
    background-color: #009fe3; /* Bright blue background */
    color: white; /* White text */
    padding: 20px; /* Padding for spacing inside the card */
    border-radius: 10px; /* Rounded corners for a modern look */
    text-align: center; /* Center the text inside */
    margin-bottom: 20px; /* Space below the card */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Light shadow effect */
    transition: transform 0.2s ease; /* Smooth hover effect */
    display: none; /* Initially hidden */
}

#thank-you h3 {
    font-size: 24px;
    margin-bottom: 10px;
    font-weight: 700;
}

#thank-you p {
    font-size: 16px;
    margin-bottom: 20px;
}

#thank-you ul {
    list-style-type: none;
    padding-left: 0;
}

#thank-you:hover {
    transform: translateY(-4px); /* Hover effect: lift the card */
}


    /* Social Icons Container */
    .social-card {
        margin-top: 20px;
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        text-align: center;
    }

    .social-card h4 {
        font-size: 20px;
        color: #009fe3;
        margin-bottom: 15px;
    }

    .share-links {
        display: flex;
        justify-content: center;
        gap: 30px;
    }

    .share-links a {
        font-size: 18px;
        margin-right: 15px;
        text-decoration: none;
        color: white;
        display: inline-block;
        width: 40px;
        height: 40px;
        text-align: center;
        line-height: 40px;
        border-radius: 50%;
    }

    .fb {
        background-color: #3b5998;
    }

    .tw {
        background-color: #55acee;
    }

    .wa {
        background-color: #25d366;
    }

    .ig {
        background-color: #e4405f;
    }

    .share-links a:hover {
        opacity: 0.8;
    }

    #submit-vote-btn {
        background-color: #009fe3; /* Blue button color */
        color: white;
        padding: 12px 20px;
        font-size: 18px;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.3s;
        width: 100%; /* Full-width button */
        margin-top: 20px; /* Space above the button */
    }

    #submit-vote-btn:hover {
        background-color: #007bb5; /* Darker blue on hover */
    }

    .pagination-btn {
        margin-bottom: 20px;
    }

    .top500-container, .indie500-voting-wrapper {
        margin-bottom: 40px;
    }

    @media (max-width: 600px) {
        .song-item {
            flex-direction: column;
            align-items: flex-start;
        }

        .share-links a {
            margin-bottom: 10px;
        }
    }

    h1, h2, h3, h4, h5, h6 {
    color: #333;
    padding-bottom: 20px;
    padding-top: 40px;
    line-height: 1em;
    font-weight: 500;
}
</style>

    
            <script>
            const songs = <?php echo json_encode($songs); ?>;
            const perPage = 100;
            let currentPage = 1;
            let selectedIds = [];
    
            function filterSongs(term) {
                return songs.filter(s =>
                    s.song_title.toLowerCase().includes(term) ||
                    s.artist_name.toLowerCase().includes(term)
                );
            }
    
            function renderSongs(songList = songs) {
                const list = document.getElementById("song-list");
                list.innerHTML = '';
                const start = (currentPage - 1) * perPage;
                const end = start + perPage;
    
                songList.slice(start, end).forEach(song => {
                    const item = document.createElement('div');
                    item.className = 'song-item';
                    item.dataset.id = song.id;
                    item.innerHTML = `<input type="checkbox" ${selectedIds.includes(song.id) ? 'checked' : ''}>
                        <span>${song.artist_name} - ${song.song_title}</span>`;
                    if (selectedIds.includes(song.id)) item.classList.add('selected');
    
                    item.addEventListener('click', e => {
                        if (e.target.tagName !== 'INPUT') item.querySelector('input').click();
                    });
    
                    item.querySelector('input').addEventListener('change', e => {
                        if (e.target.checked) {
                            if (selectedIds.length >= 10) {
                                e.target.checked = false;
                                alert("Maximaal 10 nummers toegestaan.");
                            } else {
                                selectedIds.push(song.id);
                                item.classList.add('selected');
                            }
                        } else {
                            selectedIds = selectedIds.filter(id => id !== song.id);
                            item.classList.remove('selected');
                        }
                        document.getElementById("next-btn").disabled = selectedIds.length === 0;
                    });
    
                    list.appendChild(item);
                });
    
                renderPagination(songList);
            }
    
            function renderPagination(songList) {
                const pageCount = Math.ceil(songList.length / perPage);
                const container = document.getElementById("pagination");
                container.innerHTML = '';
                for (let i = 1; i <= pageCount; i++) {
                    const btn = document.createElement("button");
                    btn.textContent = i;
                    btn.disabled = i === currentPage;
                    btn.addEventListener("click", () => {
                        currentPage = i;
                        renderSongs(songList);
                    });
                    container.appendChild(btn);
                }
            }
    
            document.addEventListener("DOMContentLoaded", () => {
                renderSongs();
    
                document.getElementById("search").addEventListener("input", e => {
                    const value = e.target.value.toLowerCase();
                    const filtered = filterSongs(value);
                    currentPage = 1;
                    renderSongs(filtered);
                });
    
                document.getElementById("next-btn").addEventListener("click", () => {
                    const list = document.getElementById("selected-songs-list");
                    list.innerHTML = '';
                    songs.filter(s => selectedIds.includes(s.id)).forEach(song => {
                        const li = document.createElement("li");
                        li.textContent = `${song.artist_name} - ${song.song_title}`;
                        list.appendChild(li);
                    });
                    document.getElementById("confirmation-section").style.display = "block";
                    document.getElementById("voting-form").style.display = "none";
                });
    
                document.getElementById("submit-vote-btn").addEventListener("click", () => {
                    document.getElementById("confirmation-section").style.display = "none";
                    document.getElementById("thank-you").style.display = "block";
    
                    const finalList = document.getElementById("final-vote-list");
                    finalList.innerHTML = '';
                    songs.filter(s => selectedIds.includes(s.id)).forEach(song => {
                        const li = document.createElement("li");
                        li.textContent = `${song.artist_name} - ${song.song_title}`;
                        finalList.appendChild(li);
                    });
    
                    const url = encodeURIComponent(window.location.href);
                    const text = encodeURIComponent("Dit is mijn Indie500 stemlijst!");
                    document.getElementById("share-facebook").href = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                    document.getElementById("share-twitter").href = `https://twitter.com/intent/tweet?text=${text}&url=${url}`;
                    document.getElementById("share-whatsapp").href = `https://api.whatsapp.com/send?text=${text}%20${url}`;
                });
            });
            </script>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Modern overall results shortcode (all years combined)
    public function overall_results_shortcode() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT h.artist_name, h.song_title, h.year, COUNT(v.id) AS vote_count
             FROM {$this->votes_table} v
             JOIN {$this->hitlists_table} h ON v.song_id = h.id
             GROUP BY v.song_id
             ORDER BY vote_count DESC, h.artist_name ASC, h.song_title ASC
             LIMIT 100"
        );

        ob_start();
        ?>
        <div class="hitlists-modern-container">
            <!-- Modern Header -->
            <div class="hitlists-header">
                <h1>Indie500 - Alle Tijden Top</h1>
                <p>De meest gestemde titels door alle jaren heen</p>
                </div>

            <!-- Results Grid -->
                    <?php if (empty($results)): ?>
                <div class="hitlists-empty">
                    <h3>Nog geen stemresultaten beschikbaar</h3>
                    <p>Er zijn nog geen stemmen uitgebracht.</p>
                </div>
                    <?php else: ?>
                <div class="hitlists-results-grid">
                        <?php foreach ($results as $index => $result): ?>
                        <?php 
                        $isTop10 = $index < 10;
                        $isTop5 = $index < 5;
                        $cardClass = 'hitlists-result-card fade-in';
                        if ($isTop5) $cardClass .= ' top-5';
                        elseif ($isTop10) $cardClass .= ' top-10';
                        ?>
                        <div class="<?php echo $cardClass; ?>" style="animation-delay: <?php echo $index * 0.1; ?>s">
                            <div class="rank"><?php echo $index + 1; ?></div>
                            <div class="song-title"><?php echo esc_html($result->song_title); ?></div>
                            <div class="artist-name"><?php echo esc_html($result->artist_name); ?></div>
                            <div class="year-badge"><?php echo $result->year; ?></div>
                            <div class="vote-count"><?php echo $result->vote_count; ?> stemmen</div>
                            </div>
                        <?php endforeach; ?>
                </div>
                    <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    // User registration shortcode
    public function registration_shortcode() {
        if (is_user_logged_in()) {
            return '<div class="hlm-notice hlm-success">Je bent al ingelogd!</div>';
        }

        ob_start();
        ?>
        <div class="hitlists-plugin">
            <div class="hlm-registration-container">
                <div class="hlm-registration-header">
                    <h2>Account Aanmaken</h2>
                    <p>Maak een account aan om te kunnen stemmen op de Indie500</p>
                </div>

                <form id="indie-registration-form" class="hlm-registration-form">
                    <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('indie-register-security')); ?>">
                    
                    <div class="form-group">
                        <label for="reg-username">Gebruikersnaam:</label>
                        <input type="text" id="reg-username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg-email">Email:</label>
                        <input type="email" id="reg-email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg-password">Wachtwoord:</label>
                        <input type="password" id="reg-password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg-password-confirm">Bevestig Wachtwoord:</label>
                        <input type="password" id="reg-password-confirm" name="password_confirm" required>
                    </div>

                    <button type="submit" id="reg-submit-btn" class="hlm-vote-button">Account Aanmaken</button>
                    <div id="reg-message"></div>
                </form>

                <div class="hlm-login-link">
                    <p>Heb je al een account? <a href="<?php echo get_permalink(get_page_by_path('inloggen')); ?>">Inloggen</a></p>
                </div>
            </div>
        </div>

        <script>
        document.getElementById('indie-registration-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('reg-password').value;
            const passwordConfirm = document.getElementById('reg-password-confirm').value;
            
            if (password !== passwordConfirm) {
                showRegMessage('Wachtwoorden komen niet overeen.', 'error');
                return;
            }

            // Disable form during submission
            document.getElementById('reg-submit-btn').disabled = true;
            document.getElementById('reg-submit-btn').textContent = 'Bezig met aanmaken...';

            const formData = new FormData(this);
            formData.append('action', 'indie_register_user');

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showRegMessage(data.data.message, 'success');
                    setTimeout(() => {
                        if (data.data.redirect) {
                            window.location.href = data.data.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 2000);
                } else {
                    showRegMessage(data.data, 'error');
                    document.getElementById('reg-submit-btn').disabled = false;
                    document.getElementById('reg-submit-btn').textContent = 'Account Aanmaken';
                }
            })
            .catch(error => {
                showRegMessage('Er ging iets mis. Probeer het opnieuw.', 'error');
                document.getElementById('reg-submit-btn').disabled = false;
                document.getElementById('reg-submit-btn').textContent = 'Account Aanmaken';
            });
        });

        function showRegMessage(message, type) {
            const messageDiv = document.getElementById('reg-message');
            messageDiv.className = 'hlm-message hlm-' + type;
            messageDiv.textContent = message;
            messageDiv.style.display = 'block';
        }
        </script>
        <?php
        return ob_get_clean();
    }

    // NEW: Login shortcode
    public function login_shortcode() {
        if (is_user_logged_in()) {
            return '<div class="hlm-notice hlm-success">Je bent al ingelogd! <a href="' . get_permalink(get_page_by_path('mijn-account')) . '">Ga naar je account</a></div>';
        }

        ob_start();
        ?>
        <div class="hitlists-plugin">
            <div class="hlm-registration-container">
                <div class="hlm-registration-header">
                    <h2>Inloggen</h2>
                    <p>Log in op je Indie500 account</p>
                </div>

                <form id="indie-login-form" class="hlm-registration-form">
                    <input type="hidden" name="security" value="<?php echo esc_attr(wp_create_nonce('indie-login-security')); ?>">
                    
                    <div class="form-group">
                        <label for="login-username">Gebruikersnaam of Email:</label>
                        <input type="text" id="login-username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="login-password">Wachtwoord:</label>
                        <input type="password" id="login-password" name="password" required>
                    </div>

                    <button type="submit" id="login-submit-btn" class="hlm-vote-button">Inloggen</button>
                    <div id="login-message"></div>
                </form>

                <div class="hlm-login-link">
                    <p>Nog geen account? <a href="<?php echo get_permalink(get_page_by_path('account-aanmaken')); ?>">Account aanmaken</a></p>
                </div>
            </div>
        </div>

        <script>
        document.getElementById('indie-login-form').addEventListener('submit', function(e) {
            e.preventDefault();

            // Disable form during submission
            document.getElementById('login-submit-btn').disabled = true;
            document.getElementById('login-submit-btn').textContent = 'Bezig met inloggen...';

            const formData = new FormData(this);
            formData.append('action', 'indie_login_user');

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showLoginMessage(data.data.message, 'success');
                    setTimeout(() => {
                        if (data.data.redirect) {
                            window.location.href = data.data.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 1000);
                } else {
                    showLoginMessage(data.data, 'error');
                    document.getElementById('login-submit-btn').disabled = false;
                    document.getElementById('login-submit-btn').textContent = 'Inloggen';
                }
            })
            .catch(error => {
                showLoginMessage('Er ging iets mis. Probeer het opnieuw.', 'error');
                document.getElementById('login-submit-btn').disabled = false;
                document.getElementById('login-submit-btn').textContent = 'Inloggen';
            });
        });

        function showLoginMessage(message, type) {
            const messageDiv = document.getElementById('login-message');
            messageDiv.className = 'hlm-message hlm-' + type;
            messageDiv.textContent = message;
            messageDiv.style.display = 'block';
        }
        </script>
        <?php
        return ob_get_clean();
    }

    // NEW: User dashboard shortcode
    public function user_dashboard_shortcode() {
        if (!is_user_logged_in()) {
            return '<div class="hlm-notice">Log alstublieft <a href="' . get_permalink(get_page_by_path('inloggen')) . '">in</a> om je account te bekijken.</div>';
        }
        
        $current_user = wp_get_current_user();
        global $wpdb;
        
        // User's vote count
        $vote_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE user_id = %d AND vote_year = %d",
            $current_user->ID,
            date('Y')
        ));
        
        ob_start();
        ?>
        <div class="hitlists-plugin">
            <div class="hlm-account-dashboard">
                <div class="hlm-account-header">
                    <h2>Welkom, <?php echo esc_html($current_user->display_name); ?>!</h2>
                    <p>Je Indie500 Account Dashboard</p>
                </div>
                
                <div class="hlm-account-stats">
                    <div class="hlm-stat-card">
                        <h3>Je Stemmen Dit Jaar</h3>
                        <div class="hlm-stat-number"><?php echo $vote_count; ?></div>
                    </div>
                </div>
                
                <div class="hlm-account-actions">
                    <h3>Snelle Acties</h3>
                    <div class="hlm-action-buttons">
                        <a href="<?php echo get_permalink(get_page_by_path('stemmen')); ?>" class="hlm-vote-button">
                            🗳️ Ga Stemmen
                        </a>
                        
                        <a href="<?php echo get_permalink(get_page_by_path('mijn-stemmen')); ?>" class="hlm-vote-button">
                            📝 Bekijk Mijn Stemmen
                        </a>
                        
                        <a href="<?php echo get_permalink(get_page_by_path('indie500-resultaten')); ?>" class="hlm-vote-button">
                            📊 Bekijk Resultaten
                        </a>
                        
                        <a href="<?php echo wp_logout_url(home_url()); ?>" class="hlm-vote-button hlm-logout">
                            🚪 Uitloggen
                        </a>
                    </div>
                </div>
                
                <div class="hlm-account-info">
                    <h3>Account Informatie</h3>
                    <p><strong>Gebruikersnaam:</strong> <?php echo esc_html($current_user->user_login); ?></p>
                    <p><strong>Email:</strong> <?php echo esc_html($current_user->user_email); ?></p>
                    <p><strong>Lid sinds:</strong> <?php echo date('F Y', strtotime($current_user->user_registered)); ?></p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // NEW: Homepage stats shortcode
    public function homepage_stats_shortcode() {
        global $wpdb;
        
        $total_songs = $wpdb->get_var("SELECT COUNT(*) FROM {$this->hitlists_table}");
        $total_votes = $wpdb->get_var("SELECT COUNT(*) FROM {$this->votes_table}");
        $current_year_votes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE vote_year = %d",
            date('Y')
        ));
        
        ob_start();
        ?>
        <div class="hitlists-plugin">
            <div class="hlm-homepage-stats">
                <div class="hlm-stats-header">
                    <h2>Indie500 Statistieken</h2>
                </div>
                
                <div class="hlm-stats-grid">
                    <div class="hlm-stat-card">
                        <div class="hlm-stat-number"><?php echo number_format($total_songs); ?></div>
                        <div class="hlm-stat-label">Nummers in Database</div>
                    </div>
                    
                    <div class="hlm-stat-card">
                        <div class="hlm-stat-number"><?php echo number_format($total_votes); ?></div>
                        <div class="hlm-stat-label">Totaal Stemmen</div>
                    </div>
                    
                    <div class="hlm-stat-card">
                        <div class="hlm-stat-number"><?php echo number_format($current_year_votes); ?></div>
                        <div class="hlm-stat-label">Stemmen <?php echo date('Y'); ?></div>
                    </div>
                </div>
                
                <?php if (!is_user_logged_in()): ?>
                <div class="hlm-cta-section">
                    <h3>Doe mee met de Indie500!</h3>
                    <div class="hlm-cta-buttons">
                        <a href="<?php echo get_permalink(get_page_by_path('account-aanmaken')); ?>" class="hlm-vote-button">
                            Account Aanmaken
                        </a>
                        <a href="<?php echo get_permalink(get_page_by_path('inloggen')); ?>" class="hlm-vote-button">
                            Inloggen
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="hlm-cta-section">
                    <h3>Welkom terug!</h3>
                    <div class="hlm-cta-buttons">
                        <a href="<?php echo get_permalink(get_page_by_path('stemmen')); ?>" class="hlm-vote-button">
                            🗳️ Ga Stemmen
                        </a>
                        <a href="<?php echo get_permalink(get_page_by_path('mijn-account')); ?>" class="hlm-vote-button">
                            👤 Mijn Account
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // Handle user registration - UPDATED TO REDIRECT TO LOGIN
    public function handle_user_registration() {
        check_ajax_referer('indie-register-security', 'security');
        
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error('Alle velden zijn verplicht.');
        }
        
        if (username_exists($username)) {
            wp_send_json_error('Gebruikersnaam bestaat al.');
        }
        
        if (email_exists($email)) {
            wp_send_json_error('Email adres is al in gebruik.');
        }
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }
        
        // Don't auto-login, redirect to login page instead
        $login_page = get_permalink(get_page_by_path('inloggen'));
        
        wp_send_json_success([
            'message' => 'Account succesvol aangemaakt! Je wordt doorgestuurd naar de inlogpagina...',
            'redirect' => $login_page
        ]);
    }

    // NEW: Handle user login
    public function handle_user_login() {
        check_ajax_referer('indie-login-security', 'security');
        
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            wp_send_json_error('Alle velden zijn verplicht.');
        }
        
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            wp_send_json_error('Ongeldige gebruikersnaam of wachtwoord.');
        }
        
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        
        $dashboard_page = get_permalink(get_page_by_path('mijn-account'));
        
        wp_send_json_success([
            'message' => 'Succesvol ingelogd! Je wordt doorgestuurd...',
            'redirect' => $dashboard_page ?: home_url()
        ]);
    }

    // Updated AJAX vote handler for checkbox voting - ALLOW ALL USERS
    public function handle_vote_submission() {
        check_ajax_referer('vote-security', 'security');
        
        global $wpdb;
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $ip = $_SERVER['REMOTE_ADDR'];
        $year = date('Y');
        $votes = $_POST['votes'] ?? [];
        $voter_email = sanitize_email($_POST['voter_email'] ?? '');

        // Validate votes
        if (empty($votes) || count($votes) > 10) {
            wp_send_json_error('Selecteer tussen 1 en 10 titels.');
        }

        // Check for duplicates
        if (count($votes) !== count(array_unique($votes))) {
            wp_send_json_error('Selecteer verschillende titels.');
        }

        // Save votes
        foreach ($votes as $song_id) {
            $song_id = intval($song_id);
            if ($song_id > 0) {
                $wpdb->insert($this->votes_table, [
                    'user_id' => $user_id,
                    'song_id' => $song_id,
                    'vote_year' => $year,
                    'ip_address' => $ip,
                    'user_email' => $voter_email,
                ]);
            }
        }

        wp_send_json_success('Bedankt voor je stem! Je nieuwe stemmen zijn opgeslagen.');
    }

    public function user_votes_shortcode() {
        if (!is_user_logged_in()) {
            return '<div class="hlm-notice">Log alstublieft <a href="' . esc_url(wp_login_url(get_permalink())) . '">in</a> om je stemmen te bekijken.</div>';
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $year = date('Y');
        
        $user_votes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT h.id, h.artist_name, h.song_title, h.year, v.created_at
             FROM {$this->votes_table} v
             JOIN {$this->hitlists_table} h ON v.song_id = h.id
             WHERE v.user_id = %d AND v.vote_year = %d
             ORDER BY v.created_at DESC",
            $user_id,
            $year
        )
    );

    ob_start();
    ?>
    <div class="hitlists-plugin">
        <div class="hlm-results-container">
            <div class="hlm-results-header">
                <h2>Mijn Stemmen</h2>
                <p>Jouw stemmen voor <?php echo $year; ?></p>
            </div>

            <?php if (empty($user_votes)): ?>
                <div class="hlm-message">
                    Je hebt nog niet gestemd dit jaar. <a href="<?php echo get_permalink(get_page_by_path('stemmen')); ?>">Ga stemmen!</a>
                </div>
            <?php else: ?>
                <div class="hlm-results-list">
                    <?php foreach ($user_votes as $index => $vote): ?>
                        <div class="hlm-result-item hlm-user-vote-item">
                            <div class="hlm-result-position">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="hlm-result-info">
                                <div class="hlm-result-title"><?php echo esc_html($vote->song_title); ?></div>
                                <div class="hlm-result-artist"><?php echo esc_html($vote->artist_name); ?> <span class="hlm-year-badge"><?php echo $vote->year; ?></span></div>
                            </div>
                            <div class="hlm-result-votes">
                                <span class="hlm-vote-label">Gestemd op</span>
                                <span class="hlm-vote-count"><?php echo date('d-m-Y', strtotime($vote->created_at)); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
public function top500_shortcode() {
    global $wpdb;

    $current_year = date('Y');
    $previous_year = $current_year - 1;
    $per_page = 50;  // Number of results per page
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;

    // Query to fetch results with pagination
    $results = $wpdb->get_results(
        $wpdb->prepare("
            SELECT artist_name, song_title
            FROM {$this->hitlists_table}
            WHERE year = %d
            ORDER BY id ASC
            LIMIT %d OFFSET %d
        ", $previous_year, $per_page, $offset)
    );

    // Count total results for pagination
    $total_results = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$this->hitlists_table}
        WHERE year = %d
    ", $previous_year));

    if (!$results) {
        return '<p>Er zijn nog geen top 500 nummers beschikbaar voor dit jaar.</p>';
    }

    $total_pages = ceil($total_results / $per_page);

    ob_start();
    ?>

    <div class="top500-container">
        <h3 class="top500-title">Top 500 uit <?php echo esc_html($previous_year); ?></h3>
        <div class="top500-grid">
            <?php foreach ($results as $song): ?>
                <div class="top500-card">
                    <span class="song-title"><?php echo esc_html($song->song_title); ?></span><br>
                    <span class="artist-name"><?php echo esc_html($song->artist_name); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" class="pagination-btn">Vorige</a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="pagination-btn">Volgende</a>
            <?php endif; ?>
        </div>
    </div>

    <style>
    .top500-container {
        font-family: 'Segoe UI', sans-serif;
        max-width: 1200px;
        margin: auto;
        padding: 20px;
    }

    .top500-title {
        font-size: 24px;
        color: #009fe3;
        margin-bottom: 20px;
    }

    .top500-grid {
        display: grid;
        grid-template-columns: repeat(1, 1fr); /* 1 card per row */
        gap: 20px;
        margin-top: 20px;
    }

    .top500-card {
        background-color: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 10px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s ease;
        text-align: center;
    }

    .top500-card:hover {
        transform: translateY(-4px);
    }

    .song-title {
        font-size: 16px;
        font-weight: 600;
        color: #222;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .artist-name {
        font-size: 15px;
        color: #555;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 30px;
    }

    .pagination-btn {
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 500;
        border: none;
        background-color: #009fe3;
        color: white;
        border-radius: 6px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .pagination-btn:disabled {
        background-color: #ccc;
        cursor: not-allowed;
    }

    .pagination-btn:hover:not(:disabled) {
        background-color: #007bb5;
    }

    @media screen and (max-width: 600px) {
        .top500-grid {
            grid-template-columns: 1fr; /* 1 card per row on mobile */
        }

        .song-title, .artist-name {
            font-size: 14px;
        }
    }
</style>




    <?php
    return ob_get_clean();
}



    // Enqueue frontend assets
    public function enqueue_frontend_assets() {
        wp_enqueue_style('hitlists-frontend', plugin_dir_url(__FILE__) . 'css/hitlists-divi-compatible.css', [], '2.1');
        wp_enqueue_script('hitlists-voting', plugin_dir_url(__FILE__) . 'js/hitlists-voting.js', ['jquery'], '2.1', true);
        wp_localize_script('hitlists-voting', 'hitlists_vars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vote-security'),
        ]);
    }

    // Enqueue admin assets
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'hit-lists') !== false) {
            wp_enqueue_style('hitlists-admin', plugin_dir_url(__FILE__) . 'css/hitlists-admin.css', [], '2.1');
        }
    }
}

new HitListsManager();
