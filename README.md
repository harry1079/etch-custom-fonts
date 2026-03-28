# Etch Custom Fonts

A lightweight custom font manager for [EtchWP](https://etchwp.com/) + [Automatic.css](https://automaticcss.com/) workflows. Upload local font files or install from Google Fonts, and manage `@font-face` declarations without conflicts — no Yabe Webfont or additional plugins required.

Fonts are self-hosted in `/wp-content/fonts/` for GDPR compliance and optimal performance.

## Features

- **Upload font files** — drag-and-drop `.woff2`, `.woff`, `.ttf`, and `.otf` files
- **Google Fonts integration** — search, preview, and install fonts from the Google Fonts library with one click (files are downloaded locally)
- **Automatic font family mapping** — Google Fonts installs auto-create the font family definition with all available weight/style variants
- **Etch builder canvas support** — fonts load inside the Etch builder iframe via Etch's `etch/canvas/additional_stylesheets` and `etch/canvas/enqueue_assets` hooks
- **Automatic.css integration** — map font families to `--heading-font-family` and `--text-font-family` custom properties
- **Self-hosted** — all font files are stored locally in `/wp-content/fonts/` (no external requests on the frontend)
- **Security hardened** — magic byte validation on uploads, path traversal protection, CSS injection prevention, nonce verification, and capability checks on all endpoints

## Requirements

- WordPress 6.0+
- PHP 7.4+
- [EtchWP](https://etchwp.com/) (recommended, but not strictly required for frontend font loading)

## Installation

### From a zip file

1. Download the [latest release](https://github.com/harry1079/etch-custom-fonts/releases) zip file
2. In WordPress, go to **Plugins > Add New > Upload Plugin**
3. Upload the zip and click **Install Now**
4. Activate the plugin

### Manual installation

1. Clone or download this repository
2. Copy the files to `/wp-content/plugins/etch-custom-fonts/`
3. Activate the plugin from the **Plugins** screen

## Usage

After activation, go to **Settings > Etch Custom Fonts**.

### Uploading local fonts

1. In **Section 1**, drag and drop your font files or click browse to upload
2. In **Section 3**, click **+ Add Font Family**, give it a name, and map each uploaded file to a weight and style

### Installing from Google Fonts

1. In **Section 2**, type a font name in the search box
2. Click **Install** on the font you want
3. The page will reload with the font family fully configured — files downloaded, variants mapped, and CSS generated

### ACSS integration

In **Section 4**, select your installed fonts from the `--heading-font-family` and `--text-font-family` dropdowns, then click **Save Font Settings**.

## How it works

The plugin generates `@font-face` CSS declarations and loads them via:

- **Frontend** — inline `<style>` in `wp_head` and a linked stylesheet via `wp_enqueue_scripts`
- **Etch builder canvas** — registered through Etch's asset pipeline (`etch/canvas/enqueue_assets` action and `etch/canvas/additional_stylesheets` filter) so fonts render inside the builder iframe
- **Static CSS file** — written to `/wp-content/fonts/ecf-fonts.css` for optimal caching

## Compatibility and maintenance

This plugin has a very small dependency surface:

- **Core WordPress APIs** (`wp_head`, `wp_enqueue_scripts`, `wp_ajax_*`, Settings API) — stable for 15+ years, extremely unlikely to change
- **Etch builder hooks** (`etch/canvas/additional_stylesheets`, `etch/canvas/enqueue_assets`) — if Etch refactors their canvas asset pipeline, these hook names may need updating. If this happens, fonts continue working on the live frontend — only the builder canvas preview would be affected until the plugin is updated
- **ACSS custom properties** (`--heading-font-family`, `--text-font-family`) — part of ACSS's public API, very unlikely to change
- **Google Fonts metadata endpoint** — used for search/install only. If Google changes this endpoint, the search feature would need updating, but **all previously installed fonts continue working** since they are self-hosted local files

### What if Google Fonts search stops working?

The Google Fonts search and install feature depends on Google's public metadata API and CSS API. If either changes, search/install may stop working until the plugin is updated. This does **not** affect fonts that have already been installed — those are local woff2 files in `/wp-content/fonts/` and will continue to work indefinitely regardless of any external API changes.

You can always fall back to manually downloading font files and uploading them via Section 1.

## License

[GPL-2.0-or-later](LICENSE)
