<?php
/**
 * Plugin Name: Etch Custom Fonts
 * Plugin URI: https://github.com/harry1079/etch-custom-fonts
 * Description: A lightweight custom font manager designed for EtchWP + Automatic.css workflows. Upload local font files and manage @font-face declarations without conflicts.
 * Version: 1.4.1
 * Author: Harry Brown
 * License: GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ECF_VERSION', '1.4.1' );
define( 'ECF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ECF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ECF_FONTS_DIR', WP_CONTENT_DIR . '/fonts/' );
define( 'ECF_FONTS_URL', content_url( '/fonts/' ) );

/**
 * Main plugin class.
 */
final class Etch_Custom_Fonts {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
        add_action( 'wp_ajax_ecf_upload_font', [ $this, 'handle_font_upload' ] );
        add_action( 'wp_ajax_ecf_delete_font_file', [ $this, 'handle_font_delete' ] );
        add_action( 'wp_ajax_ecf_search_google_fonts', [ $this, 'handle_google_fonts_search' ] );
        add_action( 'wp_ajax_ecf_install_google_font', [ $this, 'handle_google_font_install' ] );

        // Enqueue @font-face CSS on frontend (inline in wp_head as early fallback)
        add_action( 'wp_head', [ $this, 'render_fontface_css' ], 1 );

        // Enqueue @font-face CSS inside Etch builder canvas via its asset pipeline.
        // We use only the filter (not etch/canvas/enqueue_assets) to avoid adding
        // a duplicate entry. Etch's normalize_assets_queue() uses array_unique()
        // without array_values(), so duplicate entries can cause non-sequential
        // array keys that break json_encode() serialization (object vs array).
        add_filter( 'etch/canvas/additional_stylesheets', [ $this, 'add_etch_canvas_stylesheet' ] );

        // Also hook into wp_enqueue_scripts as a fallback
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_fontface_stylesheet' ], 1 );

        // Register fonts with Gutenberg's theme.json system so they appear in the
        // Site Editor Typography panel and block-level font pickers.
        // We only register font-family names (no src) to avoid Gutenberg generating
        // duplicate @font-face rules — our own CSS handles the actual font loading.
        // We use the 'theme' layer (not 'default') so our fonts aren't overridden by
        // the active theme's own theme.json fontFamilies definition.
        // To revert this feature, remove or comment out the two lines below.
        add_filter( 'wp_theme_json_data_theme', [ $this, 'register_fonts_in_theme_json' ] );

        // Enqueue the @font-face CSS inside the Gutenberg block editor iframe so
        // fonts actually render when editing posts/pages (not just on the frontend).
        add_action( 'enqueue_block_assets', [ $this, 'enqueue_fontface_stylesheet' ] );

        // Ensure fonts dir exists
        $this->maybe_create_fonts_dir();
    }

    /**
     * Verify that a file path resolves to a location inside ECF_FONTS_DIR.
     *
     * Prevents path-traversal attacks by resolving symlinks and relative
     * segments, then confirming the canonical path starts with the fonts dir.
     *
     * @param string $path Absolute path to validate.
     * @return bool
     */
    private function is_path_within_fonts_dir( $path ) {
        $fonts_dir = realpath( ECF_FONTS_DIR );
        if ( false === $fonts_dir ) {
            return false;
        }

        // For files that don't exist yet, validate the parent directory.
        if ( ! file_exists( $path ) ) {
            $parent = realpath( dirname( $path ) );
            return false !== $parent && 0 === strpos( $parent . DIRECTORY_SEPARATOR, $fonts_dir . DIRECTORY_SEPARATOR );
        }

        $real = realpath( $path );
        return false !== $real && 0 === strpos( $real, $fonts_dir . DIRECTORY_SEPARATOR );
    }

    /**
     * Create the /wp-content/fonts/ directory if it doesn't exist.
     */
    private function maybe_create_fonts_dir() {
        if ( ! file_exists( ECF_FONTS_DIR ) ) {
            wp_mkdir_p( ECF_FONTS_DIR );

            // Add an index.php for security
            $this->write_file( ECF_FONTS_DIR . 'index.php', '<?php // Silence is golden.' );
        }
    }

    /**
     * Write a file using WP_Filesystem for compatibility with various hosting setups.
     *
     * @param string $path    Absolute file path.
     * @param string $content File content.
     * @return bool
     */
    private function write_file( $path, $content ) {
        global $wp_filesystem;

        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( $wp_filesystem ) {
            return $wp_filesystem->put_contents( $path, $content, FS_CHMOD_FILE );
        }

        // Fallback if WP_Filesystem is unavailable (e.g., during plugin init)
        return (bool) file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }

    /**
     * Get stored font families.
     *
     * @return array
     */
    public function get_font_families() {
        $fonts = get_option( 'ecf_font_families', [] );
        return is_array( $fonts ) ? $fonts : [];
    }

    /**
     * Save font families.
     *
     * @param array $fonts
     */
    public function save_font_families( $fonts ) {
        update_option( 'ecf_font_families', $fonts );
        // Regenerate the static CSS file
        $this->generate_css_file();
    }

    /**
     * Get ACSS mapping settings.
     *
     * @return array
     */
    public function get_acss_settings() {
        $defaults = [
            'heading_font' => '',
            'text_font'    => '',
        ];
        $settings = get_option( 'ecf_acss_settings', $defaults );
        return wp_parse_args( $settings, $defaults );
    }

    // -------------------------------------------------------------------------
    // Admin Page
    // -------------------------------------------------------------------------

    public function add_admin_page() {
        add_options_page(
            'Etch Custom Fonts',
            'Etch Custom Fonts',
            'manage_options',
            'etch-custom-fonts',
            [ $this, 'render_admin_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'ecf_settings_group', 'ecf_font_families', [
            'sanitize_callback' => [ $this, 'sanitize_font_families' ],
        ] );
        register_setting( 'ecf_settings_group', 'ecf_acss_settings', [
            'sanitize_callback' => [ $this, 'sanitize_acss_settings' ],
        ] );

        // Regenerate CSS on plugin update to convert absolute URLs to relative URLs (WP 7.0 compatibility)
        if ( get_option( 'ecf_version' ) !== ECF_VERSION ) {
            $this->generate_css_file();
            update_option( 'ecf_version', ECF_VERSION );
        }
    }

    /**
     * Sanitize a font family name for safe use in CSS.
     * Strips characters that could enable CSS injection.
     *
     * @param string $name
     * @return string
     */
    private function sanitize_font_name( $name ) {
        $name = sanitize_text_field( $name );
        // Strip characters meaningful in CSS: " ' { } ; \ / ( ) < >
        $name = preg_replace( '/["\'\{\};\\\\\/\(\)<>]/', '', $name );
        return trim( $name );
    }

    public function sanitize_font_families( $input ) {
        if ( ! is_array( $input ) ) {
            return [];
        }

        $allowed_weights = [
            '100', '200', '300', '400', '500',
            '600', '700', '800', '900', '100 900',
        ];
        $allowed_styles = [ 'normal', 'italic' ];

        $clean = [];
        foreach ( $input as $family ) {
            if ( empty( $family['name'] ) ) {
                continue;
            }

            $clean_family = [
                'name'     => $this->sanitize_font_name( $family['name'] ),
                'variants' => [],
            ];

            if ( ! empty( $family['variants'] ) && is_array( $family['variants'] ) ) {
                foreach ( $family['variants'] as $variant ) {
                    $weight = sanitize_text_field( $variant['weight'] ?? '400' );
                    $style  = sanitize_text_field( $variant['style'] ?? 'normal' );

                    $clean_family['variants'][] = [
                        'file'   => sanitize_file_name( $variant['file'] ?? '' ),
                        'weight' => in_array( $weight, $allowed_weights, true ) ? $weight : '400',
                        'style'  => in_array( $style, $allowed_styles, true ) ? $style : 'normal',
                    ];
                }
            }

            $clean[] = $clean_family;
        }

        // Regenerate CSS after save
        $this->generate_css_file( $clean );

        return $clean;
    }

    public function sanitize_acss_settings( $input ) {
        return [
            'heading_font' => $this->sanitize_font_name( $input['heading_font'] ?? '' ),
            'text_font'    => $this->sanitize_font_name( $input['text_font'] ?? '' ),
        ];
    }

    public function admin_assets( $hook ) {
        if ( 'settings_page_etch-custom-fonts' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'ecf-admin', ECF_PLUGIN_URL . 'assets/admin.css', [], ECF_VERSION );
        wp_enqueue_script( 'ecf-admin', ECF_PLUGIN_URL . 'assets/admin.js', [ 'jquery' ], ECF_VERSION, true );

        // Load the generated font stylesheet so the preview section renders correctly.
        $this->enqueue_fontface_stylesheet();
        wp_localize_script( 'ecf-admin', 'ecfAdmin', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ecf_upload_nonce' ),
            'fontsUrl' => ECF_FONTS_URL,
        ] );
    }

    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $fonts         = $this->get_font_families();
        $acss_settings = $this->get_acss_settings();

        // Get list of uploaded font files
        $uploaded_files = $this->get_uploaded_font_files();

        include ECF_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * Get all font files in the fonts directory.
     *
     * @return array
     */
    public function get_uploaded_font_files() {
        $files      = [];
        $extensions = [ 'woff2', 'woff', 'ttf', 'otf' ];

        if ( ! is_dir( ECF_FONTS_DIR ) ) {
            return $files;
        }

        $dir = new DirectoryIterator( ECF_FONTS_DIR );
        foreach ( $dir as $file ) {
            if ( $file->isDot() || $file->isDir() ) {
                continue;
            }
            $ext = strtolower( $file->getExtension() );
            if ( in_array( $ext, $extensions, true ) ) {
                $files[] = $file->getFilename();
            }
        }

        sort( $files );
        return $files;
    }

    // -------------------------------------------------------------------------
    // AJAX: Upload Font File
    // -------------------------------------------------------------------------

    /**
     * Maximum allowed font file size in bytes (10 MB).
     */
    const MAX_FONT_FILE_SIZE = 10 * 1024 * 1024;

    public function handle_font_upload() {
        check_ajax_referer( 'ecf_upload_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        if ( empty( $_FILES['font_file'] ) ) {
            wp_send_json_error( 'No file uploaded.' );
        }

        $file = $_FILES['font_file'];

        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( 'Upload error (code ' . (int) $file['error'] . ').' );
        }

        // Enforce file size limit
        if ( $file['size'] > self::MAX_FONT_FILE_SIZE ) {
            wp_send_json_error( 'File too large. Maximum size is 10 MB.' );
        }

        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        $allowed = [ 'woff2', 'woff', 'ttf', 'otf' ];
        if ( ! in_array( $ext, $allowed, true ) ) {
            wp_send_json_error( 'Invalid file type. Allowed: woff2, woff, ttf, otf.' );
        }

        // Validate magic bytes match the claimed extension
        if ( ! $this->validate_font_magic_bytes( $file['tmp_name'], $ext ) ) {
            wp_send_json_error( 'File contents do not match the font file type.' );
        }

        // Sanitize filename
        $filename = sanitize_file_name( $file['name'] );

        $this->maybe_create_fonts_dir();

        $destination = ECF_FONTS_DIR . $filename;

        // Prevent silent overwrite of existing files
        if ( file_exists( $destination ) ) {
            $name_without_ext = pathinfo( $filename, PATHINFO_FILENAME );
            $filename         = $name_without_ext . '-' . wp_generate_uuid4() . '.' . $ext;
            $destination      = ECF_FONTS_DIR . $filename;
        }

        if ( ! $this->is_path_within_fonts_dir( $destination ) ) {
            wp_send_json_error( 'Invalid file path.' );
        }

        if ( ! move_uploaded_file( $file['tmp_name'], $destination ) ) {
            wp_send_json_error( 'Failed to save file.' );
        }

        wp_send_json_success( [
            'filename' => $filename,
            'url'      => ECF_FONTS_URL . $filename,
        ] );
    }

    /**
     * Validate that a file's magic bytes match the expected font type.
     *
     * @param string $filepath Path to the temporary uploaded file.
     * @param string $ext      Expected extension (woff2, woff, ttf, otf).
     * @return bool
     */
    private function validate_font_magic_bytes( $filepath, $ext ) {
        $handle = fopen( $filepath, 'rb' );
        if ( ! $handle ) {
            return false;
        }
        $header = fread( $handle, 8 );
        fclose( $handle );

        if ( strlen( $header ) < 4 ) {
            return false;
        }

        switch ( $ext ) {
            case 'woff2':
                // wOF2
                return substr( $header, 0, 4 ) === 'wOF2';
            case 'woff':
                // wOFF
                return substr( $header, 0, 4 ) === 'wOFF';
            case 'ttf':
                // TrueType: 0x00010000 or 'true'
                $sig = substr( $header, 0, 4 );
                return $sig === "\x00\x01\x00\x00" || $sig === 'true';
            case 'otf':
                // OpenType: 'OTTO'
                return substr( $header, 0, 4 ) === 'OTTO';
            default:
                return false;
        }
    }

    // -------------------------------------------------------------------------
    // AJAX: Delete Font File
    // -------------------------------------------------------------------------

    public function handle_font_delete() {
        check_ajax_referer( 'ecf_upload_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $filename = sanitize_file_name( wp_unslash( $_POST['filename'] ?? '' ) );
        if ( empty( $filename ) ) {
            wp_send_json_error( 'No filename provided.' );
        }

        $filepath = ECF_FONTS_DIR . $filename;

        if ( ! $this->is_path_within_fonts_dir( $filepath ) ) {
            wp_send_json_error( 'Invalid file path.' );
        }

        if ( file_exists( $filepath ) ) {
            unlink( $filepath );
            wp_send_json_success( [ 'deleted' => $filename ] );
        }

        wp_send_json_error( 'File not found.' );
    }

    // -------------------------------------------------------------------------
    // Google Fonts Integration
    // -------------------------------------------------------------------------

    /**
     * Transient key for cached Google Fonts metadata.
     */
    const GF_TRANSIENT_KEY = 'ecf_google_fonts_v2';

    /**
     * Fetch and cache the Google Fonts metadata list.
     *
     * Uses Google's public metadata endpoint. Cached for 7 days.
     *
     * @param bool $force_refresh Force a fresh fetch.
     * @return array|WP_Error Array of font objects or WP_Error on failure.
     */
    private function get_google_fonts_list( $force_refresh = false ) {
        if ( ! $force_refresh ) {
            $cached = get_transient( self::GF_TRANSIENT_KEY );
            if ( is_array( $cached ) && ! empty( $cached ) ) {
                return $cached;
            }
        }

        $response = wp_remote_get( 'https://fonts.google.com/metadata/fonts', [
            'timeout' => 15,
            'headers' => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );

        // Google prepends )]}\' to the JSON response — strip it.
        $body = preg_replace( '/^\)\]\}\'\s*\n?/', '', $body );

        $data = json_decode( $body, true );
        if ( empty( $data['familyMetadataList'] ) ) {
            return new \WP_Error( 'ecf_gf_parse', 'Could not parse Google Fonts metadata.' );
        }

        // Slim down to what we need: family name, category, and weight/style variants.
        // The 'fonts' key contains entries like "100", "200", "300i" (italic), etc.
        $fonts = [];
        foreach ( $data['familyMetadataList'] as $meta ) {
            $variant_keys = array_keys( $meta['fonts'] ?? [] );
            $variants     = [];
            foreach ( $variant_keys as $key ) {
                $is_italic = 'i' === substr( (string) $key, -1 );
                $weight    = rtrim( (string) $key, 'i' );
                $variants[] = [
                    'weight' => $weight,
                    'style'  => $is_italic ? 'italic' : 'normal',
                ];
            }

            $fonts[] = [
                'family'   => $meta['family'] ?? '',
                'category' => $meta['category'] ?? '',
                'variants' => $variants,
            ];
        }

        set_transient( self::GF_TRANSIENT_KEY, $fonts, 7 * DAY_IN_SECONDS );

        return $fonts;
    }

    /**
     * AJAX: Search Google Fonts.
     */
    public function handle_google_fonts_search() {
        check_ajax_referer( 'ecf_upload_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $query = sanitize_text_field( wp_unslash( $_GET['query'] ?? '' ) );

        $fonts = $this->get_google_fonts_list();
        if ( is_wp_error( $fonts ) ) {
            wp_send_json_error( $fonts->get_error_message() );
        }

        // Filter by search query.
        if ( ! empty( $query ) ) {
            $query_lower = strtolower( $query );
            $fonts = array_values( array_filter( $fonts, function ( $font ) use ( $query_lower ) {
                return strpos( strtolower( $font['family'] ), $query_lower ) !== false;
            } ) );
        }

        // Return first 30 results.
        $fonts = array_slice( $fonts, 0, 30 );

        wp_send_json_success( $fonts );
    }

    /**
     * AJAX: Install a Google Font.
     *
     * Downloads woff2 files from the Google Fonts CSS API and creates the font family.
     */
    public function handle_google_font_install() {
        check_ajax_referer( 'ecf_upload_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }

        $family = $this->sanitize_font_name( wp_unslash( $_POST['family'] ?? '' ) );
        if ( empty( $family ) ) {
            wp_send_json_error( 'No font family specified.' );
        }

        // Fetch the CSS from Google Fonts API with woff2 user-agent.
        // Request all available weights (ital,wght@0,100..900;1,100..900).
        $css_url = 'https://fonts.googleapis.com/css2?family='
            . rawurlencode( $family )
            . ':ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900'
            . '&display=swap';

        $response = wp_remote_get( $css_url, [
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( 'Failed to fetch font CSS: ' . $response->get_error_message() );
        }

        $css = wp_remote_retrieve_body( $response );
        if ( empty( $css ) ) {
            wp_send_json_error( 'Empty response from Google Fonts.' );
        }

        // Parse the CSS to extract @font-face blocks.
        $variants = $this->parse_google_fonts_css( $css, $family );
        if ( empty( $variants ) ) {
            wp_send_json_error( 'Could not parse any font variants from Google Fonts response.' );
        }

        // Download each woff2 file.
        $this->maybe_create_fonts_dir();
        $downloaded_variants = [];

        foreach ( $variants as $variant ) {
            $slug     = sanitize_file_name( strtolower( str_replace( ' ', '-', $family ) ) );
            $filename = $slug . '-' . $variant['weight'] . ( $variant['style'] === 'italic' ? 'i' : '' ) . '.woff2';

            $destination = ECF_FONTS_DIR . $filename;

            // Validate the destination is inside the fonts directory.
            if ( ! $this->is_path_within_fonts_dir( $destination ) ) {
                continue;
            }

            // Skip if already downloaded.
            if ( ! file_exists( $destination ) ) {
                $file_response = wp_remote_get( $variant['url'], [
                    'timeout' => 30,
                    'stream'  => true,
                    'filename' => $destination,
                ] );

                if ( is_wp_error( $file_response ) || wp_remote_retrieve_response_code( $file_response ) !== 200 ) {
                    // Clean up partial file.
                    if ( file_exists( $destination ) ) {
                        unlink( $destination );
                    }
                    continue;
                }
            }

            $downloaded_variants[] = [
                'file'   => $filename,
                'weight' => $variant['weight'],
                'style'  => $variant['style'],
            ];
        }

        if ( empty( $downloaded_variants ) ) {
            wp_send_json_error( 'Failed to download any font files.' );
        }

        // Add to font families.
        $fonts = $this->get_font_families();

        // Check if this family already exists.
        $existing_index = null;
        foreach ( $fonts as $i => $f ) {
            if ( strcasecmp( $f['name'], $family ) === 0 ) {
                $existing_index = $i;
                break;
            }
        }

        if ( $existing_index !== null ) {
            $fonts[ $existing_index ]['variants'] = $downloaded_variants;
        } else {
            $fonts[] = [
                'name'     => $family,
                'variants' => $downloaded_variants,
            ];
        }

        $this->save_font_families( $fonts );

        wp_send_json_success( [
            'family'   => $family,
            'variants' => $downloaded_variants,
            'message'  => sprintf(
                'Installed "%s" with %d variant(s).',
                $family,
                count( $downloaded_variants )
            ),
        ] );
    }

    /**
     * Parse Google Fonts CSS response to extract font variant details and URLs.
     *
     * Google's CSS API returns multiple @font-face blocks per weight/style — one
     * per Unicode subset (latin, latin-ext, cyrillic, greek, etc.).  We split the
     * CSS on the subset comments and only keep the "latin" block for each
     * weight/style so we download one file per variant containing the primary
     * character set.  If no subset comment is found we fall back to the last
     * block for each weight/style (which is typically latin).
     *
     * @param string $css    The CSS returned by Google Fonts API.
     * @param string $family The font family name.
     * @return array Array of [ 'url' => string, 'weight' => string, 'style' => string ].
     */
    private function parse_google_fonts_css( $css, $family ) {
        $variants = [];
        $seen     = []; // track weight-style combos to avoid duplicates

        // Split into chunks on the "/* subset-name */" comments.
        // Each chunk contains one @font-face block preceded by its subset label.
        $chunks = preg_split( '/\/\*\s*([\w-]+)\s*\*\//', $css, -1, PREG_SPLIT_DELIM_CAPTURE );

        // Walk through chunks in pairs: [subset_label, css_block, subset_label, css_block, ...]
        for ( $i = 1; $i < count( $chunks ) - 1; $i += 2 ) {
            $subset = trim( $chunks[ $i ] );
            $block  = $chunks[ $i + 1 ];

            // Only keep the "latin" subset (not "latin-ext").
            if ( $subset !== 'latin' ) {
                continue;
            }

            // Extract font-style.
            $style = 'normal';
            if ( preg_match( '/font-style:\s*(italic|normal)/i', $block, $m ) ) {
                $style = strtolower( $m[1] );
            }

            // Extract font-weight.
            $weight = '400';
            if ( preg_match( '/font-weight:\s*(\d+)/i', $block, $m ) ) {
                $weight = $m[1];
            }

            $key = $weight . '-' . $style;
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }

            // Extract the woff2 URL.
            if ( preg_match( '/url\((https:\/\/fonts\.gstatic\.com\/[^)]+\.woff2)\)/i', $block, $m ) ) {
                $variants[]    = [
                    'url'    => $m[1],
                    'weight' => $weight,
                    'style'  => $style,
                ];
                $seen[ $key ] = true;
            }
        }

        return $variants;
    }

    // -------------------------------------------------------------------------
    // CSS Generation
    // -------------------------------------------------------------------------

    /**
     * Generate a static CSS file with all @font-face declarations.
     *
     * @param array|null $fonts Optional. Fonts data. Uses stored option if null.
     */
    public function generate_css_file( $fonts = null ) {
        if ( null === $fonts ) {
            $fonts = $this->get_font_families();
        }

        // Generate relative URLs in the static CSS file to prevent CORS issues inside iframes (Gutenberg / EtchWP canvas)
        $css = $this->build_fontface_css( $fonts, true );

        $this->maybe_create_fonts_dir();
        $this->write_file( ECF_FONTS_DIR . 'ecf-fonts.css', $css );
    }

    /**
     * Build the @font-face CSS string.
     *
     * @param array $fonts
     * @return string
     */
    public function build_fontface_css( $fonts = null, $relative = false ) {
        if ( null === $fonts ) {
            $fonts = $this->get_font_families();
        }

        $acss = $this->get_acss_settings();
        $css  = "/* Generated by Etch Custom Fonts v" . ECF_VERSION . " */\n\n";

        foreach ( $fonts as $family ) {
            if ( empty( $family['name'] ) || empty( $family['variants'] ) ) {
                continue;
            }

            foreach ( $family['variants'] as $variant ) {
                if ( empty( $variant['file'] ) ) {
                    continue;
                }

                $ext = strtolower( pathinfo( $variant['file'], PATHINFO_EXTENSION ) );
                $format_map = [
                    'woff2' => 'woff2',
                    'woff'  => 'woff',
                    'ttf'   => 'truetype',
                    'otf'   => 'opentype',
                ];
                $format = $format_map[ $ext ] ?? 'woff2';

                // Use relative URL if requested, otherwise absolute URL
                $file_url = $relative ? esc_attr( $variant['file'] ) : esc_url( ECF_FONTS_URL . $variant['file'] );

                $weight = $variant['weight'] ?? '400';
                $style  = $variant['style'] ?? 'normal';

                $css .= "@font-face {\n";
                $css .= "  font-family: \"{$family['name']}\";\n";
                $css .= "  src: url(\"{$file_url}\") format(\"{$format}\");\n";
                $css .= "  font-weight: {$weight};\n";
                $css .= "  font-style: {$style};\n";
                $css .= "  font-display: swap;\n";
                $css .= "}\n\n";
            }
        }

        // ACSS variable mapping — use !important to ensure these override
        // ACSS's own :root declarations regardless of stylesheet load order.
        if ( ! empty( $acss['heading_font'] ) || ! empty( $acss['text_font'] ) ) {
            $css .= "/* ACSS Font Variable Overrides */\n";
            $css .= ":root {\n";
            if ( ! empty( $acss['heading_font'] ) ) {
                $css .= "  --heading-font-family: \"{$acss['heading_font']}\" !important;\n";
            }
            if ( ! empty( $acss['text_font'] ) ) {
                $css .= "  --text-font-family: \"{$acss['text_font']}\" !important;\n";
            }
            $css .= "}\n";
        }

        return $css;
    }

    // -------------------------------------------------------------------------
    // Frontend & Etch Builder Enqueue
    // -------------------------------------------------------------------------

    /**
     * Enqueue the generated CSS file (preferred method).
     */
    public function enqueue_fontface_stylesheet() {
        $css_file = ECF_FONTS_DIR . 'ecf-fonts.css';
        if ( file_exists( $css_file ) ) {
            wp_enqueue_style(
                'ecf-fonts',
                ECF_FONTS_URL . 'ecf-fonts.css',
                [],
                filemtime( $css_file )
            );
        }
    }

    /**
     * Render inline @font-face CSS in wp_head (early fallback for frontend).
     */
    public function render_fontface_css() {
        $fonts = $this->get_font_families();
        if ( empty( $fonts ) ) {
            return;
        }

        $css = $this->build_fontface_css( $fonts );
        if ( ! empty( trim( $css ) ) ) {
            echo "\n<style id=\"ecf-fonts-inline\">\n{$css}</style>\n";
        }
    }

    /**
     * Add the font stylesheet to Etch's canvas iframe via its filter.
     *
     * @param array $stylesheets Existing additional stylesheets.
     * @return array
     */
    public function add_etch_canvas_stylesheet( $stylesheets ) {
        $css_file = ECF_FONTS_DIR . 'ecf-fonts.css';
        if ( file_exists( $css_file ) ) {
            $stylesheets[] = [
                'id'  => 'ecf-fonts',
                'url' => ECF_FONTS_URL . 'ecf-fonts.css',
            ];
        }
        return $stylesheets;
    }

    // -------------------------------------------------------------------------
    // Gutenberg / Theme.json Integration
    // -------------------------------------------------------------------------

    /**
     * Register ECF fonts in Gutenberg's theme.json data so they appear in the
     * Site Editor Typography panel and block-level font family pickers.
     *
     * This filter injects font-family entries (name + slug only, no src) into
     * the theme layer of theme.json. We use the 'theme' layer rather than
     * 'default' so our fonts aren't overridden by the active theme's own
     * theme.json fontFamilies definition. Because no `fontFace` / `src` is
     * provided, Gutenberg will NOT generate its own @font-face rules — our
     * existing CSS (inline + static stylesheet + enqueue_block_assets) handles
     * the actual font loading, avoiding any double-loading issues.
     *
     * Reads directly from the `ecf_font_families` option, so any fonts that
     * are added or removed via the ECF admin page are automatically reflected
     * in Gutenberg on the next page load with no extra save step required.
     *
     * To revert this feature: comment out or remove the add_filter() call for
     * 'wp_theme_json_data_theme' in the constructor above.
     *
     * @since 1.4.0
     * @param WP_Theme_JSON_Data $theme_json The default theme.json data object.
     * @return WP_Theme_JSON_Data
     */
    public function register_fonts_in_theme_json( $theme_json ) {
        $fonts = $this->get_font_families();
        if ( empty( $fonts ) ) {
            return $theme_json;
        }

        $font_families = [];
        foreach ( $fonts as $family ) {
            if ( empty( $family['name'] ) ) {
                continue;
            }

            // Slug used internally by Gutenberg — lowercase, hyphenated.
            $slug = sanitize_title( $family['name'] );

            // Register name and slug only. Omitting fontFace/src prevents
            // Gutenberg from generating its own @font-face declarations.
            $font_families[] = [
                'fontFamily' => "'{$family['name']}'",
                'name'       => $family['name'],
                'slug'       => $slug,
            ];
        }

        if ( empty( $font_families ) ) {
            return $theme_json;
        }

        // Merge our fonts into the theme layer of theme.json.
        $theme_json->update_with( [
            'version'  => 2,
            'settings' => [
                'typography' => [
                    'fontFamilies' => $font_families,
                ],
            ],
        ] );

        return $theme_json;
    }
}

// Initialize
Etch_Custom_Fonts::instance();
