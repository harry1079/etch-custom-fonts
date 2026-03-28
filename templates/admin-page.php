<?php
/**
 * Admin page template for Etch Custom Fonts.
 *
 * @var array $fonts          Current font families.
 * @var array $acss_settings  ACSS integration settings.
 * @var array $uploaded_files List of font files in /wp-content/fonts/.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap ecf-wrap">
    <h1>Etch Custom Fonts</h1>
    <p class="ecf-description">
        Upload font files and define font families. These will be available via <code>@font-face</code>
        on your site and inside the Etch builder canvas — no Yabe Webfont required.
    </p>

    <!-- ================================================================== -->
    <!-- SECTION 1: Upload Font Files                                       -->
    <!-- ================================================================== -->
    <div class="ecf-section">
        <h2>1. Upload Font Files</h2>
        <p>Upload <code>.woff2</code>, <code>.woff</code>, <code>.ttf</code>, or <code>.otf</code> files.
           They'll be stored in <code>/wp-content/fonts/</code>.</p>

        <div class="ecf-upload-area" id="ecf-upload-area">
            <div class="ecf-upload-dropzone" id="ecf-dropzone">
                <span class="dashicons dashicons-upload"></span>
                <p>Drag &amp; drop font files here, or <label for="ecf-file-input" class="ecf-browse-link">browse</label></p>
                <input type="file" id="ecf-file-input" accept=".woff2,.woff,.ttf,.otf" multiple hidden />
            </div>
            <div id="ecf-upload-status"></div>
        </div>

        <h3>Uploaded Files</h3>
        <table class="widefat ecf-files-table" id="ecf-files-table">
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Type</th>
                    <th style="width:80px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $uploaded_files ) ) : ?>
                    <tr class="ecf-no-files"><td colspan="3">No font files uploaded yet.</td></tr>
                <?php else : ?>
                    <?php foreach ( $uploaded_files as $file ) :
                        $ext = strtoupper( pathinfo( $file, PATHINFO_EXTENSION ) );
                    ?>
                        <tr data-filename="<?php echo esc_attr( $file ); ?>">
                            <td><code><?php echo esc_html( $file ); ?></code></td>
                            <td><?php echo esc_html( $ext ); ?></td>
                            <td>
                                <button type="button" class="button button-small ecf-delete-file"
                                        data-filename="<?php echo esc_attr( $file ); ?>">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ================================================================== -->
    <!-- SECTION 2: Google Fonts                                            -->
    <!-- ================================================================== -->
    <div class="ecf-section">
        <h2>2. Google Fonts</h2>
        <p>Search and install fonts directly from the Google Fonts library. Files are downloaded
           locally to <code>/wp-content/fonts/</code> and a font family is created automatically.</p>

        <div class="ecf-gf-search-wrap">
            <input type="text" id="ecf-gf-search" class="regular-text"
                   placeholder="Search Google Fonts (e.g. Inter, Roboto, Playfair Display)" />
            <button type="button" class="button button-primary" id="ecf-gf-search-btn">Search</button>
        </div>

        <div id="ecf-gf-results" class="ecf-gf-results"></div>
        <div id="ecf-gf-status"></div>
    </div>

    <!-- ================================================================== -->
    <!-- SECTION 3: Define Font Families                                    -->
    <!-- ================================================================== -->
    <form method="post" action="options.php" id="ecf-settings-form">
        <?php settings_fields( 'ecf_settings_group' ); ?>

        <div class="ecf-section">
            <h2>3. Define Font Families</h2>
            <p>Map your uploaded files to named font families with weight and style variants.</p>

            <div id="ecf-families-container">
                <?php if ( empty( $fonts ) ) : ?>
                    <!-- Empty state — JS will populate -->
                <?php else : ?>
                    <?php foreach ( $fonts as $fi => $family ) : ?>
                        <div class="ecf-family-card" data-index="<?php echo $fi; ?>">
                            <div class="ecf-family-header">
                                <label>
                                    Font Family Name
                                    <input type="text"
                                           name="ecf_font_families[<?php echo $fi; ?>][name]"
                                           value="<?php echo esc_attr( $family['name'] ); ?>"
                                           placeholder="e.g. Inter, Satoshi, Cabinet Grotesk"
                                           class="regular-text ecf-family-name" />
                                </label>
                                <button type="button" class="button ecf-remove-family">Remove Family</button>
                            </div>

                            <table class="widefat ecf-variants-table">
                                <thead>
                                    <tr>
                                        <th>Font File</th>
                                        <th style="width:140px">Weight</th>
                                        <th style="width:120px">Style</th>
                                        <th style="width:60px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ( ! empty( $family['variants'] ) ) : ?>
                                        <?php foreach ( $family['variants'] as $vi => $variant ) : ?>
                                            <tr>
                                                <td>
                                                    <select name="ecf_font_families[<?php echo $fi; ?>][variants][<?php echo $vi; ?>][file]"
                                                            class="ecf-file-select">
                                                        <option value="">— Select file —</option>
                                                        <?php foreach ( $uploaded_files as $uf ) : ?>
                                                            <option value="<?php echo esc_attr( $uf ); ?>"
                                                                <?php selected( $variant['file'], $uf ); ?>>
                                                                <?php echo esc_html( $uf ); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="ecf_font_families[<?php echo $fi; ?>][variants][<?php echo $vi; ?>][weight]">
                                                        <?php
                                                        $weights = [
                                                            '100' => '100 (Thin)',
                                                            '200' => '200 (Extra Light)',
                                                            '300' => '300 (Light)',
                                                            '400' => '400 (Regular)',
                                                            '500' => '500 (Medium)',
                                                            '600' => '600 (Semi Bold)',
                                                            '700' => '700 (Bold)',
                                                            '800' => '800 (Extra Bold)',
                                                            '900' => '900 (Black)',
                                                            '100 900' => '100–900 (Variable)',
                                                        ];
                                                        foreach ( $weights as $val => $label ) :
                                                        ?>
                                                            <option value="<?php echo esc_attr( $val ); ?>"
                                                                <?php selected( $variant['weight'], $val ); ?>>
                                                                <?php echo esc_html( $label ); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="ecf_font_families[<?php echo $fi; ?>][variants][<?php echo $vi; ?>][style]">
                                                        <option value="normal" <?php selected( $variant['style'], 'normal' ); ?>>Normal</option>
                                                        <option value="italic" <?php selected( $variant['style'], 'italic' ); ?>>Italic</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <button type="button" class="button button-small ecf-remove-variant">✕</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <button type="button" class="button ecf-add-variant" data-family-index="<?php echo $fi; ?>">
                                + Add Variant
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <p style="margin-top: 16px;">
                <button type="button" class="button button-secondary" id="ecf-add-family">
                    + Add Font Family
                </button>
            </p>
        </div>

        <!-- ================================================================== -->
        <!-- SECTION 4: ACSS Integration                                        -->
        <!-- ================================================================== -->
        <div class="ecf-section">
            <h2>4. Automatic.css Integration</h2>
            <p>Optionally map your font families to ACSS custom properties.
               These <code>:root</code> variables will override the ACSS dashboard values.</p>

            <table class="form-table">
                <tr>
                    <th><label for="ecf-acss-heading">--heading-font-family</label></th>
                    <td>
                        <select name="ecf_acss_settings[heading_font]" id="ecf-acss-heading" class="regular-text">
                            <option value="">— None (use ACSS default) —</option>
                            <?php foreach ( $fonts as $family ) : ?>
                                <option value="<?php echo esc_attr( $family['name'] ); ?>"
                                    <?php selected( $acss_settings['heading_font'], $family['name'] ); ?>>
                                    <?php echo esc_html( $family['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="ecf-acss-text">--text-font-family</label></th>
                    <td>
                        <select name="ecf_acss_settings[text_font]" id="ecf-acss-text" class="regular-text">
                            <option value="">— None (use ACSS default) —</option>
                            <?php foreach ( $fonts as $family ) : ?>
                                <option value="<?php echo esc_attr( $family['name'] ); ?>"
                                    <?php selected( $acss_settings['text_font'], $family['name'] ); ?>>
                                    <?php echo esc_html( $family['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ================================================================== -->
        <!-- SECTION 5: Preview & Save                                          -->
        <!-- ================================================================== -->
        <div class="ecf-section">
            <h2>5. Preview</h2>
            <div id="ecf-preview" class="ecf-preview-box">
                <p class="ecf-preview-placeholder">Save your settings to see a live preview of your fonts here.</p>
                <?php foreach ( $fonts as $family ) : ?>
                    <div class="ecf-preview-family" style="font-family: '<?php echo esc_attr( $family['name'] ); ?>', sans-serif;">
                        <strong><?php echo esc_html( $family['name'] ); ?></strong><br />
                        <span style="font-weight: 400;">The quick brown fox jumps over the lazy dog (400)</span><br />
                        <span style="font-weight: 700;">The quick brown fox jumps over the lazy dog (700)</span><br />
                        <span style="font-style: italic;">The quick brown fox jumps over the lazy dog (italic)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php submit_button( 'Save Font Settings' ); ?>
    </form>

    <!-- ================================================================== -->
    <!-- Generated CSS Output (for debugging)                               -->
    <!-- ================================================================== -->
    <div class="ecf-section ecf-debug">
        <h2>Generated CSS <small>(for reference)</small></h2>
        <pre id="ecf-generated-css"><code><?php echo esc_html( Etch_Custom_Fonts::instance()->build_fontface_css() ); ?></code></pre>
    </div>
</div>
