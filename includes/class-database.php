<?php

class Indie500_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Songs table
        $songs_table = $wpdb->prefix . 'indie500_songs';
        $songs_sql = "CREATE TABLE $songs_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            artist varchar(255) NOT NULL,
            year varchar(4) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY year_idx (year),
            KEY artist_idx (artist)
        ) $charset_collate;";
        
        // Votes table
        $votes_table = $wpdb->prefix . 'indie500_votes';
        $votes_sql = "CREATE TABLE $votes_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            song_id mediumint(9) NOT NULL,
            user_ip varchar(45) NOT NULL,
            user_agent text,
            voted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY song_id_idx (song_id),
            KEY user_ip_idx (user_ip),
            KEY voted_at_idx (voted_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($songs_sql);
        dbDelta($votes_sql);
    }
    
    public static function get_songs($year = null) {
        global $wpdb;
        
        $songs_table = $wpdb->prefix . 'indie500_songs';
        
        if ($year) {
            $songs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $songs_table WHERE year = %s ORDER BY artist, title",
                $year
            ));
        } else {
            $songs = $wpdb->get_results("SELECT * FROM $songs_table ORDER BY year DESC, artist, title");
        }
        
        return $songs;
    }
    
    public static function get_years() {
        global $wpdb;
        
        $songs_table = $wpdb->prefix . 'indie500_songs';
        $years = $wpdb->get_col("SELECT DISTINCT year FROM $songs_table ORDER BY year DESC");
        
        return $years;
    }
    
    public static function insert_songs($songs_data) {
        global $wpdb;
        
        $songs_table = $wpdb->prefix . 'indie500_songs';
        
        // Clear existing songs
        $wpdb->query("TRUNCATE TABLE $songs_table");
        
        $inserted = 0;
        foreach ($songs_data as $song) {
            $result = $wpdb->insert(
                $songs_table,
                array(
                    'title' => sanitize_text_field($song['title']),
                    'artist' => sanitize_text_field($song['artist']),
                    'year' => sanitize_text_field($song['year'])
                ),
                array('%s', '%s', '%s')
            );
            
            if ($result) {
                $inserted++;
            }
        }
        
        return $inserted;
    }
    
    public static function save_votes($song_ids, $user_ip) {
        global $wpdb;
        
        $votes_table = $wpdb->prefix . 'indie500_votes';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $inserted = 0;
        foreach ($song_ids as $song_id) {
            $result = $wpdb->insert(
                $votes_table,
                array(
                    'song_id' => intval($song_id),
                    'user_ip' => sanitize_text_field($user_ip),
                    'user_agent' => sanitize_text_field($user_agent)
                ),
                array('%d', '%s', '%s')
            );
            
            if ($result) {
                $inserted++;
            }
        }
        
        return $inserted;
    }
    
    public static function get_vote_results() {
        global $wpdb;
        
        $songs_table = $wpdb->prefix . 'indie500_songs';
        $votes_table = $wpdb->prefix . 'indie500_votes';
        
        $results = $wpdb->get_results("
            SELECT s.id, s.title, s.artist, s.year, COUNT(v.id) as vote_count
            FROM $songs_table s
            LEFT JOIN $votes_table v ON s.id = v.song_id
            GROUP BY s.id, s.title, s.artist, s.year
            HAVING vote_count > 0
            ORDER BY vote_count DESC, s.title
        ");
        
        return $results;
    }
    
    public static function user_has_voted($user_ip) {
        global $wpdb;
        
        $votes_table = $wpdb->prefix . 'indie500_votes';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $votes_table WHERE user_ip = %s",
            $user_ip
        ));
        
        return $count > 0;
    }
}
