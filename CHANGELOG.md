# Changelog

All notable changes to this project will be documented in this file.

## [1.4.1] - 2026-05-27

### Fixed
- Fixed custom fonts failing to load inside Gutenberg block editor iframe and EtchWP canvas on WordPress 7.0 due to CORS. The static `ecf-fonts.css` now uses relative paths for font files instead of absolute URLs.
- Added version auto-migration logic inside `register_settings` to automatically regenerate `ecf-fonts.css` with relative URLs on plugin upgrade.

## [1.4.0] - 2026-03-30

### Added
- Gutenberg integration: ECF fonts now appear in the Site Editor Typography panel and block-level font pickers
- Uses `wp_theme_json_data_theme` filter to register font names at the theme layer (not default) so they aren't overridden by the active theme's own theme.json fontFamilies
- Font CSS now loads inside the Gutenberg block editor iframe via `enqueue_block_assets`, so fonts render correctly when editing posts/pages
- Registers font names only (no src/fontFace) to prevent Gutenberg generating duplicate `@font-face` rules
- Fully automatic — existing installed fonts appear immediately after plugin update, no re-save needed
- To revert: comment out the `wp_theme_json_data_theme` and `enqueue_block_assets` lines in the constructor

## [1.3.0] - 2026-03-28

### Security
- Added path traversal protection on file upload, deletion, and Google Font download
- Font file URLs in generated CSS are now escaped with `esc_url()`
- Google Font family names are sanitized through `sanitize_font_name()` before saving
- Fixed PHP 7.4 compatibility (`str_ends_with()` replaced with `substr()`)

## [1.2.0] - 2026-03-28

### Fixed
- Google Fonts CSS parser now correctly extracts only the latin subset for each weight/style (previously downloaded the wrong unicode-range subset)
- Font file URLs in `@font-face` declarations now use absolute URLs from `content_url()` instead of hardcoded relative paths

## [1.1.0] - 2026-03-28

### Added
- Google Fonts integration: search, preview, and one-click install
- Automatic font family creation when installing from Google Fonts
- Page auto-reloads after Google Font install so the font family appears immediately
- Variant details shown in Google Fonts search results (weight names + italic indicator)

### Changed
- Etch builder canvas integration now uses Etch's own asset pipeline (`etch/canvas/enqueue_assets` and `etch/canvas/additional_stylesheets`) instead of `admin_head`/`wp_footer` hooks

## [1.0.0] - 2026-03-28

### Added
- Initial release
- Drag-and-drop font file upload (woff2, woff, ttf, otf)
- Magic byte validation on uploaded files
- Font family definition with weight/style variant mapping
- `@font-face` CSS generation (inline and static file)
- Automatic.css integration (`--heading-font-family`, `--text-font-family`)
- Live font preview on admin page
- Generated CSS debug output
