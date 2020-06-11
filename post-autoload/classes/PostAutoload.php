<?php
declare(strict_types=1);

/**
 * Plugin Posts Autoload class.
 *
 * @package   PostAutoload
 * @author    Stoil Dobreff
 * @copyright Copyright © 2020, Stoil Dobreff
 * @license   GNU General Public License v3.0 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace PostAutoload;

class PostAutoload {
    /**
     * Sets all the necessary hooks and creates the tables if needed
     */
    public static function init() {
        self::init_hooks();
        self::create_table();
    }

    /**
     * Inits all the hooks the plugin would use
     */
    private static function init_hooks() {
        // Removes comments from posts on frontend
        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);
        add_filter('comments_array', '__return_empty_array', 10, 2);
        add_action( 'wp_enqueue_scripts', [ 'PostAutoload\\PostAutoload', 'post_autoload_enqueue_scripts' ] );

        // Ajax methods for showing the next article
        add_action('wp_ajax_load_next_post', [ 'PostAutoload\\PostAutoload', 'load_next_post' ]);
        add_action('wp_ajax_nopriv_load_next_post', [ 'PostAutoload\\PostAutoload', 'load_next_post' ]);

        // Template guessing methods
        add_filter( 'get_template_part', [ 'PostAutoload\\PostAutoload', 'filter_template_part' ], PHP_INT_MAX, 3 );

        // Track user when everything is done
        add_action( 'shutdown', [ 'PostAutoload\\PostAutoload', 'track_user' ], PHP_INT_MAX );

        // Theme switch - we need to remove the template part as it will probably be different
        add_action( 'after_switch_theme', [ 'PostAutoload\\PostAutoload', 'theme_changed' ], PHP_INT_MAX);

        // Settings Related actions
        add_action( 'admin_menu', [ 'PostAutoload\\PostAutoload', 'settings_page' ] );
        add_action( 'admin_init', [ 'PostAutoload\\PostAutoload', 'register_settings' ] );
        add_action( 'update_option_post_autoload_options', [ 'PostAutoload\\PostAutoload', 'options_updated' ], PHP_INT_MAX, 2 );
    }

    /**
     * AJAX call for loading the next post
     */
    public static function load_next_post() {

        // Must not be called directly
        if ( isset( $_SERVER['HTTP_REFERER'] ) && isset( $_SERVER['HTTP_X_REQUESTED_WITH'] )) {
            if ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            } else {
                wp_die();
            }

            if ( strtolower(parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST)) != strtolower(parse_url(get_site_url(), PHP_URL_HOST)) ) {
                wp_die();
            }
        } else {
            wp_die();
        }

        global $post;

        $currentPost = absint($_GET['post']);

        $post = $currentPost;

        $post = get_next_post(); //Gets the next post

        if (empty($post)) {

            // No posts to show - set last flag
            $data = [
                'last' => true,
            ];

            echo json_encode($data);

            wp_die();
        }

        // Extracts the template slug
        $templateSlug = get_transient( 'autoload_template_part' );

        if (!$templateSlug) {

            // None is set - set the error flag
            $data = [
                'error' => 'Unable to guess the template slug! Please provide it in the settings',
            ];

            echo json_encode($data);

            wp_die();
        }

        $postUrl = '';
        $postTitle = '';
        $postId = '';

        $args = [
            'post__in' => [$post->ID],
        ];

        // Execute the query for post extraction and show
        $query = new \WP_Query( $args );
         
        // Check that we have query results.
        if ( $query->have_posts() ) {
            $query->the_post();

            ob_start();
                get_template_part( $templateSlug );
                $postUrl = get_permalink();
                $postTitle = get_the_title();
                $postId = get_the_ID();

            $output = ob_get_contents();
            ob_end_clean();

            if ( empty($output) ) {
                // The template slug is probably wrong - will try again after removing the part after last "-", then give up and notify the user
                $templateSlug = substr( $templateSlug, 0, strrpos( $templateSlug, '-') );

                ob_start();
                    get_template_part( $templateSlug );
                    $postUrl = get_permalink();
                    $postTitle = get_the_title();
                    $postId = get_the_ID();

                $output = ob_get_contents();
                ob_end_clean();

            }
        }

        wp_reset_postdata(); // Return the initial state

        if ( empty($output) ) {
            /** 
             * Unfortunately something went wrong with the content ...
             * the get_template_part for instance fails silently - notify the user
             */
            $data = [
                'error' => 'Unable to auto load next article - check your settings',
            ];

            echo json_encode($data);

            wp_die();
        }

        $data = [
            'content' => $output,
            'url' => $postUrl,
            'title' => $postTitle,
            'id' => $postId,
            'last' => false,
        ];

        // We good - create and output the json data
        echo json_encode($data);

        wp_die();
    }

    /**
     * Collects the template part responsible for post rendering and
     * trying to get the template responsible for the article showing.
     * If there is one set in the admin settings - the transient will be set as well.
     * Usually themes calling that one 'content'
     * Sets the value in the 'autoload_template_part' transient for future use
     */
    public static function filter_template_part( $slug, $name, $templates ) {

        // Make sure we are on the single page
        // TODO: add support for CPT
        if ( is_singular() && 'post' === get_post_type() ) {

            $templateSlug = get_transient( 'autoload_template_part' );

            if ( empty( $templateSlug ) ) {
                $data = compact( 'slug', 'name', 'templates' );

                // Most probably it has 'content' in the slug name
                if ( strpos($data['slug'], 'content') !== false ) {
                    set_transient( 'autoload_template_part', $slug.((!empty($name))?'-'.$name:''), DAY_IN_SECONDS );
                }
            }
        }
    }

    /**
     * The theme was changed - remove the transient holding the template part for the article
     */
    public static function theme_changed() {
        delete_transient( 'autoload_template_part' );

        $options = get_option( 'post_autoload_options' );
        if ( isset($options['template_slug']) && !empty($options['template_slug']) ) {
            // If template slug is set in admin, set transient with the value
            set_transient( 'autoload_template_part', $options['template_slug'], DAY_IN_SECONDS );
        }
    }

    /**
     * Registers and enqueues javascripts for the frontend part of site.
     */
    public static function post_autoload_enqueue_scripts() {

        // No need to overloading the browser if we are not on the single page
        if ( is_singular() && 'post' === get_post_type() ) {

            $autoLoadScript = 'post-autoload-script'; // Script slug

            $options = get_option( 'post_autoload_options' );

            // Get the main HTML element if one is set, otherwise use 'main'
            $mainElement = ( isset( $options['main_entry'] ) && !empty( $options['main_entry'] ) )?$options['main_entry']:'main';

            if ( !wp_script_is( $autoLoadScript, 'registered' ) ) {
                wp_register_script( 
                    $autoLoadScript,
                    POSTAUTOLOAD__ASSETS_URL_DIR.'js/post-autoload.js',
                    [
                        'jquery'
                    ],
                    POSTAUTOLOAD__PLUGIN_VERSION,
                    true
                );
                wp_enqueue_script( $autoLoadScript );
                wp_localize_script( 
                    $autoLoadScript,
                    'autoload',
                    [
                        'ajax_url' => admin_url( 'admin-ajax.php' ),
                        'post_id' => get_the_ID(),
                        'main_element' => $mainElement,
                    ]
                );
            }
        }
    }

    /**
     * Creates the table if necessary
     */
    private static function create_table() {
        global $wpdb;

        $wpTrackUsers = $wpdb->prefix.'track_users';

        // Check for the WP create function, and if one is not present, load the script with it
        if (!function_exists('maybe_create_table')) {
            require_once ABSPATH . 'wp-admin/install-helper.php';
        }

        $wpTrackUsersSQL = '
            CREATE TABLE `'.$wpdb->prefix.'track_users` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `site_url` varchar(255) DEFAULT NULL,
              `user_ip` varbinary(16) DEFAULT NULL,
              `browser_info` json DEFAULT NULL,
              `date_visited` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
        ';

        maybe_create_table($wpTrackUsers, $wpTrackUsersSQL);
    }

    /**
     * Checks for the minimum requirements and bails if they are not met
     */
    public static function plugin_activation() {
        global $wpdb;

        // Minimum PHP version check
        if ( version_compare( phpversion(), REQUIRED_PHP_VERSION, '<') ) {

            // Plugin not activated info message.
            ?>
                <div class="update-nag">
                    <?php _e( 'You need to update your PHP version to '.REQUIRED_PHP_VERSION.'.', 'text-domain' ); ?> <br />
                    <?php _e( 'Actual version is:', 'text-domain' ) ?> <strong><?php echo phpversion(); ?></strong>
                </div>
            <?php

            exit;
        }

        // Minumum WP version check
        if ( version_compare( $GLOBALS['wp_version'], REQUIRED_WP_VERSION, '<' ) ) {

            // Plugin not activated info message.
            ?>
                <div class="update-nag">
                    <?php _e( 'You need to update your WP version to '.REQUIRED_WP_VERSION.'.', 'text-domain' ); ?> <br />
                    <?php _e( 'Actual version is:', 'text-domain' ) ?> <strong><?php echo $GLOBALS['wp_version']; ?></strong>
                </div>
            <?php

            exit;
        }

        // Minimum MySQL version check
        if ( version_compare( $wpdb->db_version(), REQUIRED_MYSQL_VERSION, '<' ) ) {

            // Plugin not activated info message.
            ?>
                <div class="update-nag">
                    <?php _e( 'You need to update your MySQL version to '.REQUIRED_MYSQL_VERSION.'.', 'text-domain' ); ?> <br />
                    <?php _e( 'Actual version is:', 'text-domain' ) ?> <strong><?php echo $wpdb->db_version(); ?></strong>
                </div>
            <?php

            exit;
        }
    }

    /**
     * Adds user tracking data to the DB
     */
    public static function track_user() {

        // Don't store AJAX calls and admin actions
        if ( !is_admin() && !wp_doing_ajax() ) {
            global $wp, $wpdb;

            // Check for core WP method and include the class if necessary
            if (!function_exists('get_unsafe_client_ip')) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-community-events.php';
            }

            // Use the WP core function wich extracts info for the current request
            if (!function_exists('wp_check_browser_version')) {
                require_once ABSPATH . 'wp-admin/includes/dashboard.php';
            }

            $currentURL = home_url( $wp->request );
            $ip = \WP_Community_Events::get_unsafe_client_ip();
            $browserInfo = wp_check_browser_version();

            // Add user agent info from browser
            if ( !empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
                $browserInfo['browser_UA'] = $_SERVER['HTTP_USER_AGENT'];
            }

            // Store the collected data
            $wpdb->insert( 
                $wpdb->prefix.'track_users', [
                    'site_url' => $currentURL,
                    'user_ip' => $ip,
                    'browser_info' => json_encode($browserInfo),
                ]
            );
        }
    }

    /**
     * Adds settings page to the admin menu
     */
    public static function settings_page() {
        add_options_page( POSTAUTOLOAD__PLUGIN_NAME.' settings', POSTAUTOLOAD__PLUGIN_NAME, 'manage_options', POSTAUTOLOAD__PLUGIN_SLUG, [ 'PostAutoload\\PostAutoload', 'render_plugin_settings_page'] );
    }

    /**
     * Responsible for rendering the admin settings page
     */
    public static function render_plugin_settings_page() {
        ?>
        <h2>Post Autoload Settings</h2>
        <form action="options.php" method="post">
            <?php 
            settings_fields( 'post_autoload_options' );
            do_settings_sections( 'post_autoload' ); ?>
            <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
        </form>
        <?php
    }

    /**
     * Responsible for rendering the settings page
     */
    public static function register_settings() {
        register_setting( 'post_autoload_options', 'post_autoload_options' );
        add_settings_section( 'main_entry_data', 'Main page entry CSS selector or tag', [ 'PostAutoload\\PostAutoload', 'post_autoload_main_entry_section_text' ], 'post_autoload' );

        add_settings_field( 'main_entry', 'Main article wrapper', [ 'PostAutoload\\PostAutoload', 'post_autoload_setting_main_entry' ], 'post_autoload', 'main_entry_data' );

        add_settings_section( 'template_slug_data', 'The template slug for your current theme', [ 'PostAutoload\\PostAutoload', 'post_autoload_template_slug_entry_section_text' ], 'post_autoload' );

        add_settings_field( 'template_slug', 'Template slug for single article', [ 'PostAutoload\\PostAutoload', 'post_autoload_setting_template_slug' ], 'post_autoload', 'template_slug_data' );
    }

    /**
     * Renders main_entry field for the settings page
     */
    public static function post_autoload_setting_main_entry() {
        $options = get_option( 'post_autoload_options' );

        echo '<input id="post_autoload_setting_main_entry" name="post_autoload_options[main_entry]" type="text" value="'.esc_attr( $options['main_entry'] ).'" />';
    }

    /**
     * Add info message for the main_entry field in the admin settings page
     */
    public static function post_autoload_main_entry_section_text() {
        echo 'The css selector (or html tag), for your template article wrapper. Usually its "main" html tag - if you leave that one empty, plugin will try to use that.
        <p><strong>Example:</strong><br />
            If theme is using main HTML tag fill that one with "main".<br />
            If theme is using div with specific class or id - provide it with CSS selector syntax: "#main"
        </p>';
    }

    /**
     * Renders template_slug field for the settings page
     */
    public static function post_autoload_setting_template_slug() {
        $options = get_option( 'post_autoload_options' );
        echo '<input id="post_autoload_setting_template_slug" name="post_autoload_options[template_slug]" type="text" value="'.esc_attr( $options['template_slug'] ).'" />';
    }

    /**
     * Add info message for the template_slug field in the admin settings page
     */
    public static function post_autoload_template_slug_entry_section_text() {
        echo 'The template slug responsible for rendering single posts in your theme. <br />Its usually the one called from within the <strong>single.php</strong> or <strong>singular.php</strong> files located in the root directory of your theme.
        <p><i>Warning:</i> If you leave that one empty, plugin will try to guess the slug of the template part used, but that is not reliable - you will be provided with warning message from your article page to disable the plugin in that case!</p>';
    }

    /**
     * When options are updated - store the new value (if any) in the transient
     */
    public static function options_updated( $oldValue, $newValue ) {

        if (isset($newValue['template_slug']) && !empty($newValue['template_slug'])) {
            // If template slug is set in admin it takes precedence over auto guessing system
            set_transient( 'autoload_template_part', $newValue['template_slug'], DAY_IN_SECONDS );
        } else {
            // The template slug is not set - remove transient so code could fall back to auto guess
            delete_transient( 'autoload_template_part' );
        }
    }

    /**
     * Adds settings link
     */
    public static function add_action_links( $links ) {
        $new_links = [
            '<a href="'.admin_url('options-general.php?page='.POSTAUTOLOAD__PLUGIN_SLUG).'">'.__('Settings', POSTAUTOLOAD__PLUGIN_SLUG).'</a>',
        ];
        return array_merge( $links, $new_links );
    }
}