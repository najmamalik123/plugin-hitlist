<?php

class Indie500_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_csv_upload'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Indie500 Manager', 'indie500-manager'),
            __('Indie500', 'indie500-manager'),
            'manage_options',
            'indie500-manager',
            array($this, 'admin_page'),
            'dashicons-playlist-audio',
            30
        );
        
        add_submenu_page(
            'indie500-manager',
            __('Upload Songs', 'indie500-manager'),
            __('Upload Songs', 'indie500-manager'),
            'manage_options',
            'indie500-upload',
            array($this, 'upload_page')
        );
        
        add_submenu_page(
            'indie500-manager',
            __('Results', 'indie500-manager'),
            __('Results', 'indie500-manager'),
            'manage_options',
            'indie500-results',
            array($this, 'results_page')
        );
        
        add_submenu_page(
            'indie500-manager',
            __('Settings', 'indie500-manager'),
            __('Settings', 'indie500-manager'),
            'manage_options',
            'indie500-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_page() {
        $songs_count = count(Indie500_Database::get_songs());
        $votes_count = count(Indie500_Database::get_vote_results());
        ?>
        <div class="wrap indie500-admin">
            <h1><?php _e('Indie500 Manager Dashboard', 'indie500-manager'); ?></h1>
            
            <div class="indie500-dashboard-cards">
                <div class="indie500-card">
                    <h3><?php _e('Total Songs', 'indie500-manager'); ?></h3>
                    <div class="indie500-stat"><?php echo $songs_count; ?></div>
                </div>
                
                <div class="indie500-card">
                    <h3><?php _e('Songs with Votes', 'indie500-manager'); ?></h3>
                    <div class="indie500-stat"><?php echo $votes_count; ?></div>
                </div>
                
                <div class="indie500-card">
                    <h3><?php _e('Available Years', 'indie500-manager'); ?></h3>
                    <div class="indie500-stat"><?php echo count(Indie500_Database::get_years()); ?></div>
                </div>
            </div>
            
            <div class="indie500-quick-actions">
                <h2><?php _e('Quick Actions', 'indie500-manager'); ?></h2>
                <a href="<?php echo admin_url('admin.php?page=indie500-upload'); ?>" class="button button-primary">
                    <?php _e('Upload New Songs', 'indie500-manager'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=indie500-results'); ?>" class="button">
                    <?php _e('View Results', 'indie500-manager'); ?>
                </a>
                <a href="<?php echo admin_url('admin-ajax.php?action=indie500_export_results&_wpnonce=' . wp_create_nonce('indie500_export')); ?>" class="button">
                    <?php _e('Export Results CSV', 'indie500-manager'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    public function upload_page() {
        ?>
        <div class="wrap indie500-admin">
            <h1><?php _e('Upload Songs', 'indie500-manager'); ?></h1>
            
            <?php if (isset($_GET['uploaded'])): ?>
                <div class="notice notice-success">
                    <p><?php printf(__('%d songs successfully uploaded!', 'indie500-manager'), intval($_GET['count'])); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($_GET['error']); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="indie500-upload-form">
                <h2><?php _e('CSV Upload', 'indie500-manager'); ?></h2>
                <p><?php _e('Upload a CSV file with columns: Title, Artist, Year', 'indie500-manager'); ?></p>
                
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('indie500_upload_csv', 'indie500_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="csv_file"><?php _e('CSV File', 'indie500-manager'); ?></label>
                            </th>
                            <td>
                                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                                <p class="description"><?php _e('Select a CSV file to upload', 'indie500-manager'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="upload_csv" class="button button-primary" value="<?php _e('Upload CSV', 'indie500-manager'); ?>">
                    </p>
                </form>
            </div>
            
            <div class="indie500-csv-format">
                <h3><?php _e('CSV Format Example', 'indie500-manager'); ?></h3>
                <pre>Title,Artist,Year
"Bohemian Rhapsody","Queen","1975"
"Hotel California","Eagles","1976"
"Stairway to Heaven","Led Zeppelin","1971"</pre>
            </div>
        </div>
        <?php
    }
    
    public function results_page() {
        $results = Indie500_Database::get_vote_results();
        ?>
        <div class="wrap indie500-admin">
            <h1><?php _e('Voting Results', 'indie500-manager'); ?></h1>
            
            <div class="indie500-results-actions">
                <a href="<?php echo admin_url('admin-ajax.php?action=indie500_export_results&_wpnonce=' . wp_create_nonce('indie500_export')); ?>" class="button button-primary">
                    <?php _e('Export Results CSV', 'indie500-manager'); ?>
                </a>
            </div>
            
            <?php if (empty($results)): ?>
                <p><?php _e('No votes have been cast yet.', 'indie500-manager'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Rank', 'indie500-manager'); ?></th>
                            <th><?php _e('Title', 'indie500-manager'); ?></th>
                            <th><?php _e('Artist', 'indie500-manager'); ?></th>
                            <th><?php _e('Year', 'indie500-manager'); ?></th>
                            <th><?php _e('Votes', 'indie500-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $index => $result): ?>
                            <tr class="<?php echo $index < 3 ? 'indie500-top-3' : ''; ?>">
                                <td><strong>#<?php echo $index + 1; ?></strong></td>
                                <td><?php echo esc_html($result->title); ?></td>
                                <td><?php echo esc_html($result->artist); ?></td>
                                <td><?php echo esc_html($result->year); ?></td>
                                <td><strong><?php echo $result->vote_count; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function settings_page() {
        if (isset($_POST['save_settings'])) {
            check_admin_referer('indie500_settings', 'indie500_nonce');
            
            $settings = array(
                'max_votes_per_user' => intval($_POST['max_votes_per_user']),
                'voting_enabled' => isset($_POST['voting_enabled']),
                'show_results' => isset($_POST['show_results'])
            );
            
            update_option('indie500_settings', $settings);
            echo '<div class="notice notice-success"><p>' . __('Settings saved!', 'indie500-manager') . '</p></div>';
        }
        
        $settings = get_option('indie500_settings', array(
            'max_votes_per_user' => 10,
            'voting_enabled' => true,
            'show_results' => true
        ));
        ?>
        <div class="wrap indie500-admin">
            <h1><?php _e('Indie500 Settings', 'indie500-manager'); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field('indie500_settings', 'indie500_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="max_votes_per_user"><?php _e('Max Votes Per User', 'indie500-manager'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="max_votes_per_user" id="max_votes_per_user" value="<?php echo $settings['max_votes_per_user']; ?>" min="1" max="50">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Voting Enabled', 'indie500-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="voting_enabled" <?php checked($settings['voting_enabled']); ?>>
                                <?php _e('Allow users to vote', 'indie500-manager'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Show Results', 'indie500-manager'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_results" <?php checked($settings['show_results']); ?>>
                                <?php _e('Show voting results publicly', 'indie500-manager'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_settings" class="button button-primary" value="<?php _e('Save Settings', 'indie500-manager'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
    
    public function handle_csv_upload() {
        if (isset($_POST['upload_csv']) && isset($_FILES['csv_file'])) {
            check_admin_referer('indie500_upload_csv', 'indie500_nonce');
            
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to upload files.', 'indie500-manager'));
            }
            
            $file = $_FILES['csv_file'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                wp_redirect(admin_url('admin.php?page=indie500-upload&error=' . urlencode(__('File upload error.', 'indie500-manager'))));
                exit;
            }
            
            $csv_data = array_map('str_getcsv', file($file['tmp_name']));
            $header = array_shift($csv_data); // Remove header row
            
            $songs_data = array();
            foreach ($csv_data as $row) {
                if (count($row) >= 3) {
                    $songs_data[] = array(
                        'title' => trim($row[0]),
                        'artist' => trim($row[1]),
                        'year' => trim($row[2])
                    );
                }
            }
            
            if (!empty($songs_data)) {
                $inserted = Indie500_Database::insert_songs($songs_data);
                wp_redirect(admin_url('admin.php?page=indie500-upload&uploaded=1&count=' . $inserted));
            } else {
                wp_redirect(admin_url('admin.php?page=indie500-upload&error=' . urlencode(__('No valid data found in CSV.', 'indie500-manager'))));
            }
            exit;
        }
    }
}
