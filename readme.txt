=== Etch Custom Fonts ===
Contributors: harrybrown
Tags: fonts, custom-fonts, etch, etchwp, automatic-css, acss, webfonts, google-fonts
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later

A lightweight custom font manager for EtchWP + Automatic.css. Upload local fonts or install from Google Fonts — no Yabe Webfont needed.

== Description ==

Upload local font files or install directly from Google Fonts, then manage @font-face declarations without plugin conflicts in the Etch builder canvas. All fonts are self-hosted in /wp-content/fonts/ for GDPR compliance and performance.

**Features:**

* Upload woff2, woff, ttf, otf files via drag-and-drop
* Search and install Google Fonts with one click (downloaded locally)
* Automatic font family creation with all weight/style variants
* Fonts load inside the Etch builder canvas via Etch's asset pipeline
* Optional Automatic.css integration (--heading-font-family, --text-font-family)
* Self-hosted — no external requests on the frontend
* Security hardened — magic byte validation, path traversal protection, CSS injection prevention

== Installation ==

1. Upload the `etch-custom-fonts` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Settings > Etch Custom Fonts
4. Upload fonts or install from Google Fonts, configure ACSS mappings, and save

== Changelog ==

= 1.4.0 =
* Added Gutenberg integration — ECF fonts now appear in Site Editor Typography panel and block font pickers
* Font CSS now loads inside the block editor iframe so fonts render when editing
* Uses theme layer (not default) so fonts aren't overridden by active theme's theme.json
* Automatic for existing installs — no re-save needed after update
* To revert: comment out the wp_theme_json_data_theme and enqueue_block_assets lines in the constructor

= 1.3.0 =
* Security: path traversal protection on file operations
* Security: esc_url() on CSS output, sanitize_font_name() on Google Font installs
* Fixed PHP 7.4 compatibility

= 1.2.0 =
* Fixed Google Fonts latin subset extraction
* Fixed font file URLs to use absolute paths

= 1.1.0 =
* Added Google Fonts search, preview, and one-click install
* Automatic font family creation from Google Fonts
* Etch canvas integration via etch/canvas/additional_stylesheets

= 1.0.0 =
* Initial release
