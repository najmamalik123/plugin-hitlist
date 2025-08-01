<?php

class Indie500_Frontend {
    
    public function __construct() {
        add_shortcode('indie500_vote_form', array($this, 'vote_form_shortcode'));
        add_shortcode('indie500_archive', array($this, 'archive_shortcode'));
        add_shortcode('indie500_results', array($this, 'results_shortcode'));
        add_shortcode('indie500_voting', array($this, 'voting_shortcode'));
        add_shortcode('indie500_voting_results', array($this, 'voting_results_shortcode'));
        add_shortcode('indie500_final_list', array($this, 'final_list_shortcode'));
        add_shortcode('indie500_voting_page', array($this, 'voting_page_shortcode'));
        add_shortcode('indie500_register_login', array($this, 'register_login_shortcode'));
    }
    
    public function vote_form_shortcode($atts) {
        $settings = get_option('indie500_settings', array(
            'voting_enabled' => true, 
            'max_votes_per_user' => 10,
            'require_exact_10' => true
        ));
        
        if (!$settings['voting_enabled']) {
            return '<div class="indie500-message indie500-error">' . __('Voting is currently disabled.', 'indie500-manager') . '</div>';
        }
        
        $user_ip = $_SERVER['REMOTE_ADDR'];
        if (Indie500_Database::user_has_voted($user_ip)) {
            return '<div class="indie500-message indie500-success">' . __('You have already voted. Thank you!', 'indie500-manager') . '</div>';
        }
        
        $songs = Indie500_Database::get_songs();
        $years = Indie500_Database::get_years();
        
        ob_start();
        ?>
        <div class="indie500-vote-container">
            <div class="indie500-vote-header">
                <h2><?php _e('Indie500 Voting', 'indie500-manager'); ?></h2>
                <p><?php printf(__('Select exactly %d of your favorite songs', 'indie500-manager'), $settings['max_votes_per_user']); ?></p>
            </div>
            
            <div class="indie500-vote-form">
                <div class="indie500-search-container">
                    <input type="text" id="indie500-search" placeholder="<?php _e('Search by title or artist...', 'indie500-manager'); ?>">
                </div>
                
                <div class="indie500-filter-container">
                    <select id="indie500-year-filter">
                        <option value=""><?php _e('All Years', 'indie500-manager'); ?></option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="indie500-vote-counter">
                    <span id="indie500-selected-count">0</span> / <?php echo $settings['max_votes_per_user']; ?> <?php _e('selected', 'indie500-manager'); ?>
                </div>
                
                <form id="indie500-vote-form" method="post">
                    <div class="indie500-songs-list" id="indie500-songs-list">
                        <?php foreach ($songs as $song): ?>
                            <div class="indie500-song-item" data-year="<?php echo esc_attr($song->year); ?>" data-search="<?php echo esc_attr(strtolower($song->title . ' ' . $song->artist)); ?>">
                                <label class="indie500-song-label">
                                    <input type="checkbox" name="votes[]" value="<?php echo $song->id; ?>" class="indie500-vote-checkbox">
                                    <div class="indie500-song-info">
                                        <div class="indie500-song-title"><?php echo esc_html($song->title); ?></div>
                                        <div class="indie500-song-artist"><?php echo esc_html($song->artist); ?> (<?php echo esc_html($song->year); ?>)</div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="indie500-submit-container">
                        <button type="submit" id="indie500-submit-btn" class="indie500-submit-btn" disabled>
                            <?php _e('Submit Votes', 'indie500-manager'); ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <div id="indie500-message" class="indie500-message" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function voting_shortcode($atts) {
        $settings = get_option('indie500_settings', array(
            'voting_enabled' => true, 
            'max_votes_per_user' => 10,
            'require_exact_10' => true
        ));
        
        if (!$settings['voting_enabled']) {
            return '<div class="indie500-message indie500-error">' . __('Voting is currently disabled.', 'indie500-manager') . '</div>';
        }
        
        $user_ip = $_SERVER['REMOTE_ADDR'];
        if (Indie500_Database::user_has_voted($user_ip)) {
            return '<div class="indie500-message indie500-success">' . __('You have already voted. Thank you!', 'indie500-manager') . '</div>';
        }
        
        $songs = Indie500_Database::get_songs();
        
        ob_start();
        ?>
        <div class="indie500-voting-container">
            <div class="indie500-voting-header">
                <h2><?php _e('Indie500 Stemformulier', 'indie500-manager'); ?></h2>
                <p><?php _e('Selecteer precies 10 van je favoriete titels', 'indie500-manager'); ?></p>
            </div>
            
            <div class="indie500-voting-form">
                <!-- Search and Filter Section -->
                <div class="indie500-controls">
                    <div class="indie500-search-wrapper">
                        <input type="text" id="indie500-search" placeholder="<?php _e('Zoek op titel of artiest...', 'indie500-manager'); ?>">
                    </div>
                    
                    <div class="indie500-selection-info">
                        <span id="indie500-selected-count">0</span> / 10 <?php _e('geselecteerd', 'indie500-manager'); ?>
                    </div>
                </div>
                
                <!-- Warning for incomplete selection -->
                <div id="indie500-warning" class="indie500-warning" style="display: none;">
                    <p><?php _e('Je moet precies 10 titels selecteren om te kunnen stemmen', 'indie500-manager'); ?></p>
                </div>
                
                <!-- Songs List -->
                <div class="indie500-songs-container">
                    <div class="indie500-songs-list" id="indie500-songs-list">
                        <?php foreach ($songs as $index => $song): ?>
                            <div class="indie500-song-item" data-search="<?php echo esc_attr(strtolower($song->title . ' ' . $song->artist)); ?>">
                                <label class="indie500-song-label">
                                    <input type="checkbox" name="votes[]" value="<?php echo $song->id; ?>" class="indie500-vote-checkbox">
                                    <div class="indie500-song-number">#<?php echo $index + 1; ?></div>
                                    <div class="indie500-song-info">
                                        <div class="indie500-song-title"><?php echo esc_html($song->title); ?></div>
                                        <div class="indie500-song-artist"><?php echo esc_html($song->artist); ?></div>
                                    </div>
                                    <div class="indie500-song-check">
                                        <span class="indie500-check-icon">✓</span>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Selected Songs Summary -->
                <div id="indie500-selected-summary" class="indie500-selected-summary" style="display: none;">
                    <h3><?php _e('Jouw geselecteerde titels:', 'indie500-manager'); ?></h3>
                    <div id="indie500-selected-list" class="indie500-selected-list"></div>
                </div>
                
                <!-- Submit Button -->
                <div class="indie500-submit-container">
                    <button type="button" id="indie500-submit-btn" class="indie500-submit-btn" disabled>
                        <?php _e('Stemmen Verzenden', 'indie500-manager'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Thank You Screen -->
            <div id="indie500-thank-you" class="indie500-thank-you" style="display: none;">
                <div class="indie500-thank-you-content">
                    <h2><?php _e('Hartelijk bedankt!', 'indie500-manager'); ?></h2>
                    <p><?php _e('Je stemmen zijn succesvol opgeslagen voor de Indie500.', 'indie500-manager'); ?></p>
                    <p><?php _e('Bedankt voor je deelname aan de grootste Nederlandse muzieklijst!', 'indie500-manager'); ?></p>
                    <div class="indie500-countdown">
                        <p><?php _e('Je wordt automatisch doorgestuurd over:', 'indie500-manager'); ?></p>
                        <div id="indie500-countdown-timer">40</div>
                        <p><?php _e('seconden', 'indie500-manager'); ?></p>
                    </div>
                </div>
            </div>
            
            <div id="indie500-message" class="indie500-message" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function archive_shortcode($atts) {
        $atts = shortcode_atts(array(
            'year' => ''
        ), $atts);
        
        $years = Indie500_Database::get_years();
        $selected_year = $atts['year'] ?: (isset($_GET['year']) ? $_GET['year'] : '');
        $songs = $selected_year ? Indie500_Database::get_songs($selected_year) : array();
        
        ob_start();
        ?>
        <div class="indie500-archive-container">
            <div class="indie500-archive-header">
                <h2><?php _e('Indie500 Archive', 'indie500-manager'); ?></h2>
                <p><?php _e('Browse songs by year', 'indie500-manager'); ?></p>
            </div>
            
            <div class="indie500-year-selector">
                <form method="get">
                    <select name="year" onchange="this.form.submit()">
                        <option value=""><?php _e('Select Year', 'indie500-manager'); ?></option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo esc_attr($year); ?>" <?php selected($selected_year, $year); ?>>
                                <?php echo esc_html($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            
            <?php if ($selected_year && !empty($songs)): ?>
                <div class="indie500-songs-archive">
                    <h3><?php printf(__('Songs from %s (%d songs)', 'indie500-manager'), $selected_year, count($songs)); ?></h3>
                    <div class="indie500-archive-list">
                        <?php foreach ($songs as $song): ?>
                            <div class="indie500-archive-item">
                                <div class="indie500-song-title"><?php echo esc_html($song->title); ?></div>
                                <div class="indie500-song-artist"><?php echo esc_html($song->artist); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($selected_year): ?>
                <p><?php _e('No songs found for the selected year.', 'indie500-manager'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function results_shortcode($atts) {
        $settings = get_option('indie500_settings', array('show_results' => true));
        
        if (!$settings['show_results']) {
            return '<div class="indie500-message">' . __('Results are not available at this time.', 'indie500-manager') . '</div>';
        }
        
        $results = Indie500_Database::get_vote_results();
        
        ob_start();
        ?>
        <div class="indie500-results-container">
            <div class="indie500-results-header">
                <h2><?php _e('Indie500 Results', 'indie500-manager'); ?></h2>
                <p><?php _e('Current voting standings', 'indie500-manager'); ?></p>
            </div>
            
            <?php if (empty($results)): ?>
                <div class="indie500-message">
                    <?php _e('No votes have been cast yet.', 'indie500-manager'); ?>
                </div>
            <?php else: ?>
                <div class="indie500-results-list">
                    <?php foreach ($results as $index => $result): ?>
                        <div class="indie500-result-item <?php echo $index < 3 ? 'indie500-top-3' : ''; ?>">
                            <div class="indie500-result-rank">
                                #<?php echo $index + 1; ?>
                            </div>
                            <div class="indie500-result-info">
                                <div class="indie500-result-title"><?php echo esc_html($result->title); ?></div>
                                <div class="indie500-result-artist"><?php echo esc_html($result->artist); ?> (<?php echo esc_html($result->year); ?>)</div>
                            </div>
                            <div class="indie500-result-votes">
                                <span class="indie500-vote-count"><?php echo $result->vote_count; ?></span>
                                <span class="indie500-vote-label"><?php echo $result->vote_count == 1 ? __('vote', 'indie500-manager') : __('votes', 'indie500-manager'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function voting_results_shortcode($atts) {
        $settings = get_option('indie500_settings', array('show_results' => true));
        if (!$settings['show_results']) {
            return '<div class="indie500-message">' . __('Results are not available at this time.', 'indie500-manager') . '</div>';
        }
        $results = Indie500_Database::get_vote_results();
        ob_start();
        ?>
        <section class="indie500-voting-results-section" aria-labelledby="indie500-voting-results-title">
            <div class="indie500-voting-results-header">
                <h2 id="indie500-voting-results-title"><?php _e('Indie500 Voting Results', 'indie500-manager'); ?></h2>
                <p><?php _e('Bekijk de huidige ranglijst van de Indie500 stemmen.', 'indie500-manager'); ?></p>
            </div>
            <?php if (empty($results)): ?>
                <div class="indie500-message">
                    <?php _e('No votes have been cast yet.', 'indie500-manager'); ?>
                </div>
            <?php else: ?>
                <ul class="indie500-voting-results-list" role="list">
                    <?php foreach ($results as $index => $result): ?>
                        <li class="indie500-voting-result-row<?php echo $index % 2 === 0 ? ' indie500-row-even' : ' indie500-row-odd'; ?><?php echo $index < 3 ? ' indie500-voting-top3' : ''; ?>" tabindex="0">
                            <div class="indie500-voting-rank">
                                <span class="indie500-voting-rank-number">#<?php echo $index + 1; ?></span>
                            </div>
                            <div class="indie500-voting-songinfo">
                                <span class="indie500-voting-title"><?php echo esc_html($result->title); ?></span>
                                <span class="indie500-voting-artist">&ndash; <?php echo esc_html($result->artist); ?></span>
                                <span class="indie500-voting-year">(<?php echo esc_html($result->year); ?>)</span>
                            </div>
                            <div class="indie500-voting-votes">
                                <span class="indie500-voting-vote-count"><?php echo $result->vote_count; ?></span>
                                <span class="indie500-voting-vote-label"><?php echo $result->vote_count == 1 ? __('vote', 'indie500-manager') : __('votes', 'indie500-manager'); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public function final_list_shortcode($atts) {
        // Fetch all songs, ordered by rank (assume get_songs returns in correct order)
        $songs = Indie500_Database::get_songs();
        $per_page = 50;
        $page = isset($_GET['indie500_page']) ? max(1, intval($_GET['indie500_page'])) : 1;
        $total = count($songs);
        $total_pages = ceil($total / $per_page);
        $start = ($page - 1) * $per_page;
        $songs_page = array_slice($songs, $start, $per_page);
        ob_start();
        ?>
        <section class="indie500-final-list-section" aria-labelledby="indie500-final-list-title">
            <div class="indie500-final-list-header">
                <h2 id="indie500-final-list-title"><?php _e('Indie500 Hitlist (Final List)', 'indie500-manager'); ?></h2>
                <p><?php _e('De complete lijst van de Indie500', 'indie500-manager'); ?></p>
            </div>
            <div class="indie500-final-list-table-wrapper">
                <div class="indie500-final-list-table">
                    <div class="indie500-final-list-header-row">
                        <div class="indie500-final-list-col indie500-final-list-rank-col">#</div>
                        <div class="indie500-final-list-col indie500-final-list-artist-col"><?php _e('Artist', 'indie500-manager'); ?></div>
                        <div class="indie500-final-list-col indie500-final-list-title-col"><?php _e('Song', 'indie500-manager'); ?></div>
                        <div class="indie500-final-list-col indie500-final-list-link-col"></div>
                    </div>
                    <?php foreach ($songs_page as $i => $song):
                        $rank = $start + $i + 1;
                        $highlight = $rank <= 10 ? ' indie500-final-list-top10' : ($rank <= 5 ? ' indie500-final-list-top5' : '');
                        $row_class = ($rank % 2 === 0 ? 'indie500-final-list-row-even' : 'indie500-final-list-row-odd') . $highlight;
                        $listen_url = isset($song->listen_url) ? esc_url($song->listen_url) : '';
                    ?>
                    <div class="indie500-final-list-row <?php echo $row_class; ?>">
                        <div class="indie500-final-list-col indie500-final-list-rank-col">
                            <span class="indie500-final-list-rank">#<?php echo $rank; ?></span>
                        </div>
                        <div class="indie500-final-list-col indie500-final-list-artist-col">
                            <span class="indie500-final-list-artist"><?php echo esc_html($song->artist); ?></span>
                        </div>
                        <div class="indie500-final-list-col indie500-final-list-title-col">
                            <span class="indie500-final-list-title"><?php echo esc_html($song->title); ?></span>
                        </div>
                        <div class="indie500-final-list-col indie500-final-list-link-col">
                            <?php if ($listen_url): ?>
                                <a href="<?php echo $listen_url; ?>" class="indie500-final-list-listen-btn" target="_blank" rel="noopener noreferrer"><?php _e('Where to Listen', 'indie500-manager'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="indie500-final-list-pagination">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="?indie500_page=<?php echo $p; ?>" class="indie500-final-list-page-btn<?php if ($p == $page) echo ' active'; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    public function voting_page_shortcode($atts) {
        $settings = get_option('indie500_settings', array(
            'voting_enabled' => true,
            'max_votes_per_user' => 10,
            'require_exact_10' => true
        ));
        if (!$settings['voting_enabled']) {
            return '<div class="indie500-message indie500-error">' . __('Voting is currently disabled.', 'indie500-manager') . '</div>';
        }
        $user_ip = $_SERVER['REMOTE_ADDR'];
        if (Indie500_Database::user_has_voted($user_ip)) {
            return '<div class="indie500-message indie500-success">' . __('Je hebt al gestemd. Bedankt!', 'indie500-manager') . '</div>';
        }
        $songs = Indie500_Database::get_songs();
        $max_votes = intval($settings['max_votes_per_user']);
        $per_page = 25;
        $total = count($songs);
        $total_pages = ceil($total / $per_page);
        ob_start();
        ?>
        <section class="indie500-voting-page-section" aria-labelledby="indie500-voting-page-title">
            <div class="indie500-voting-page-header">
                <h2 id="indie500-voting-page-title"><?php _e('Indie500 Stemformulier', 'indie500-manager'); ?></h2>
                <p><?php printf(__('Selecteer maximaal %d titels', 'indie500-manager'), $max_votes); ?></p>
            </div>
            <form id="indie500-voting-form" method="post" autocomplete="off">
                <div id="indie500-voting-list-wrapper">
                    <!-- Voting list will be rendered by JS -->
                </div>
                <div class="indie500-voting-controls">
                    <span id="indie500-voting-selected-count">0</span> / <?php echo $max_votes; ?> <?php _e('geselecteerd', 'indie500-manager'); ?>
                    <button type="submit" id="indie500-voting-submit-btn" class="indie500-voting-submit-btn" disabled><?php _e('Stemmen Verzenden', 'indie500-manager'); ?></button>
                </div>
                <div id="indie500-voting-message" class="indie500-message" style="display:none;"></div>
            </form>
            <div id="indie500-voting-thankyou" class="indie500-message indie500-success" style="display:none;">
                <?php _e('Bedankt voor je stem! Je bijdrage aan de Indie500 is succesvol ontvangen.', 'indie500-manager'); ?>
            </div>
            <script type="text/javascript">
            window.indie500VotingSongs = <?php echo json_encode(array_map(function($song, $i) {
                return array(
                    'id' => $song->id,
                    'artist' => $song->artist,
                    'title' => $song->title,
                    'rank' => $i + 1
                );
            }, $songs, array_keys($songs))); ?>;
            window.indie500VotingConfig = {
                maxVotes: <?php echo $max_votes; ?>,
                perPage: <?php echo $per_page; ?>,
                totalPages: <?php echo $total_pages; ?>
            };
            </script>
        </section>
        <?php
        return ob_get_clean();
    }

    public function register_login_shortcode($atts) {
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            ob_start();
            ?>
            <div class="indie500-auth-section indie500-auth-loggedin">
                <h2><?php _e('Welkom,', 'indie500-manager'); ?> <?php echo esc_html($current_user->display_name); ?>!</h2>
                <p><?php _e('Je bent ingelogd. Ga naar de', 'indie500-manager'); ?> <a href="/voting/">stem pagina</a>.</p>
                <form method="post"><button type="submit" name="indie500_logout" class="indie500-auth-btn">Uitloggen</button></form>
            </div>
            <?php
            if (isset($_POST['indie500_logout'])) {
                wp_logout();
                wp_redirect($_SERVER['REQUEST_URI']);
                exit;
            }
            return ob_get_clean();
        }
        $register_msg = $login_msg = '';
        // Handle registration
        if (isset($_POST['indie500_register'])) {
            $fname = sanitize_text_field($_POST['indie500_fname']);
            $lname = sanitize_text_field($_POST['indie500_lname']);
            $email = sanitize_email($_POST['indie500_email']);
            $pass1 = $_POST['indie500_pass1'];
            $pass2 = $_POST['indie500_pass2'];
            if (!$fname || !$lname || !$email || !$pass1 || !$pass2) {
                $register_msg = '<div class="indie500-message indie500-error">Alle velden zijn verplicht.</div>';
            } elseif (!is_email($email)) {
                $register_msg = '<div class="indie500-message indie500-error">Ongeldig e-mailadres.</div>';
            } elseif (email_exists($email)) {
                $register_msg = '<div class="indie500-message indie500-error">Dit e-mailadres is al geregistreerd.</div>';
            } elseif ($pass1 !== $pass2) {
                $register_msg = '<div class="indie500-message indie500-error">Wachtwoorden komen niet overeen.</div>';
            } else {
                $user_id = wp_create_user($email, $pass1, $email);
                if (is_wp_error($user_id)) {
                    $register_msg = '<div class="indie500-message indie500-error">Registratie mislukt: ' . esc_html($user_id->get_error_message()) . '</div>';
                } else {
                    wp_update_user(array('ID' => $user_id, 'first_name' => $fname, 'last_name' => $lname, 'display_name' => $fname . ' ' . $lname));
                    wp_set_current_user($user_id);
                    wp_set_auth_cookie($user_id);
                    // Send confirmation email
                    wp_mail($email, 'Bevestiging registratie Indie500', 'Je registratie is gelukt! Je kunt nu stemmen op de Indie500.');
                    $register_msg = '<div class="indie500-message indie500-success">Registratie gelukt! Je bent nu ingelogd.</div>';
                }
            }
        }
        // Handle login
        if (isset($_POST['indie500_login'])) {
            $login_user = sanitize_text_field($_POST['indie500_login_user']);
            $login_pass = $_POST['indie500_login_pass'];
            $creds = array(
                'user_login' => $login_user,
                'user_password' => $login_pass,
                'remember' => true
            );
            $user = wp_signon($creds, false);
            if (is_wp_error($user)) {
                $login_msg = '<div class="indie500-message indie500-error">Ongeldige inloggegevens.</div>';
            } else {
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID);
                $login_msg = '<div class="indie500-message indie500-success">Welkom terug! Je bent nu ingelogd.</div>';
                echo '<script>setTimeout(function(){window.location.href="/voting/";}, 1200);</script>';
            }
        }
        ob_start();
        ?>
        <section class="indie500-auth-section">
            <div class="indie500-auth-forms">
                <div class="indie500-auth-form indie500-register-form">
                    <h2><?php _e('Registreren', 'indie500-manager'); ?></h2>
                    <?php echo $register_msg; ?>
                    <form method="post" autocomplete="off">
                        <input type="text" name="indie500_fname" placeholder="Voornaam" required />
                        <input type="text" name="indie500_lname" placeholder="Achternaam" required />
                        <input type="email" name="indie500_email" placeholder="E-mailadres" required />
                        <input type="password" name="indie500_pass1" placeholder="Wachtwoord" required />
                        <input type="password" name="indie500_pass2" placeholder="Bevestig wachtwoord" required />
                        <button type="submit" name="indie500_register" class="indie500-auth-btn"><?php _e('Registreren', 'indie500-manager'); ?></button>
                    </form>
                </div>
                <div class="indie500-auth-form indie500-login-form">
                    <h2><?php _e('Inloggen', 'indie500-manager'); ?></h2>
                    <?php echo $login_msg; ?>
                    <form method="post" autocomplete="off">
                        <input type="text" name="indie500_login_user" placeholder="E-mail of gebruikersnaam" required />
                        <input type="password" name="indie500_login_pass" placeholder="Wachtwoord" required />
                        <button type="submit" name="indie500_login" class="indie500-auth-btn"><?php _e('Inloggen', 'indie500-manager'); ?></button>
                    </form>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
