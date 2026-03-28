/**
 * Etch Custom Fonts — Admin JS
 */
(function ($) {
    'use strict';

    // Track current uploaded files for dynamic selects
    let uploadedFiles = [];

    // Initialize uploaded files from the existing table
    function initUploadedFiles() {
        $('#ecf-files-table tbody tr[data-filename]').each(function () {
            uploadedFiles.push($(this).data('filename'));
        });
    }

    // =========================================================================
    // File Upload (Drag & Drop + Browse)
    // =========================================================================

    const $dropzone = $('#ecf-dropzone');
    const $fileInput = $('#ecf-file-input');
    const $status = $('#ecf-upload-status');

    // Drag events
    $dropzone.on('dragover dragenter', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('ecf-dragover');
    });

    $dropzone.on('dragleave drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('ecf-dragover');
    });

    $dropzone.on('drop', function (e) {
        const files = e.originalEvent.dataTransfer.files;
        if (files.length) {
            uploadFiles(files);
        }
    });

    // Click to browse
    $dropzone.on('click', function (e) {
        if (e.target.tagName !== 'LABEL') {
            $fileInput.trigger('click');
        }
    });

    $fileInput.on('change', function () {
        if (this.files.length) {
            uploadFiles(this.files);
        }
        // Reset input so the same file can be re-selected
        this.value = '';
    });

    function uploadFiles(fileList) {
        Array.from(fileList).forEach(function (file) {
            const formData = new FormData();
            formData.append('action', 'ecf_upload_font');
            formData.append('nonce', ecfAdmin.nonce);
            formData.append('font_file', file);

            const $msg = $('<div class="ecf-upload-msg">').text('Uploading ' + file.name + '...');
            $status.append($msg);

            $.ajax({
                url: ecfAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        $msg.addClass('success').text('\u2713 ' + response.data.filename + ' uploaded.');
                        addFileToTable(response.data.filename);
                        uploadedFiles.push(response.data.filename);
                        refreshFileSelects();

                        // Auto-fade the message
                        setTimeout(function () { $msg.fadeOut(300, function () { $msg.remove(); }); }, 3000);
                    } else {
                        $msg.addClass('error').text('\u2715 ' + file.name + ': ' + response.data);
                    }
                },
                error: function () {
                    $msg.addClass('error').text('\u2715 ' + file.name + ': Upload failed.');
                }
            });
        });
    }

    function addFileToTable(filename) {
        const ext = filename.split('.').pop().toUpperCase();
        const $tbody = $('#ecf-files-table tbody');

        // Remove "no files" placeholder
        $tbody.find('.ecf-no-files').remove();

        const $row = $('<tr>').attr('data-filename', filename);
        $row.append($('<td>').append($('<code>').text(filename)));
        $row.append($('<td>').text(ext));
        $row.append(
            $('<td>').append(
                $('<button>')
                    .attr('type', 'button')
                    .addClass('button button-small ecf-delete-file')
                    .attr('data-filename', filename)
                    .text('Delete')
            )
        );

        $tbody.append($row);
    }

    // =========================================================================
    // File Deletion
    // =========================================================================

    $(document).on('click', '.ecf-delete-file', function () {
        const filename = $(this).data('filename');
        if (!confirm('Delete "' + filename + '" from /wp-content/fonts/?')) {
            return;
        }

        const $row = $(this).closest('tr');

        $.post(ecfAdmin.ajaxUrl, {
            action: 'ecf_delete_font_file',
            nonce: ecfAdmin.nonce,
            filename: filename
        }, function (response) {
            if (response.success) {
                $row.fadeOut(200, function () { $row.remove(); });
                uploadedFiles = uploadedFiles.filter(f => f !== filename);
                refreshFileSelects();
            } else {
                alert('Could not delete: ' + response.data);
            }
        });
    });

    // =========================================================================
    // Dynamic Font Families
    // =========================================================================

    let familyIndex = $('.ecf-family-card').length;

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function buildFileOptions(selectedFile) {
        let html = '<option value="">— Select file —</option>';
        uploadedFiles.forEach(function (f) {
            const sel = f === selectedFile ? ' selected' : '';
            const escaped = escapeHtml(f);
            html += '<option value="' + escaped + '"' + sel + '>' + escaped + '</option>';
        });
        return html;
    }

    function refreshFileSelects() {
        $('.ecf-file-select').each(function () {
            const current = $(this).val();
            $(this).html(buildFileOptions(current));
        });
    }

    // Add Family
    $('#ecf-add-family').on('click', function () {
        const fi = familyIndex++;
        const card = `
            <div class="ecf-family-card" data-index="${fi}">
                <div class="ecf-family-header">
                    <label>
                        Font Family Name
                        <input type="text" name="ecf_font_families[${fi}][name]"
                               value="" placeholder="e.g. Inter, Satoshi, Cabinet Grotesk"
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
                    <tbody></tbody>
                </table>
                <button type="button" class="button ecf-add-variant" data-family-index="${fi}">
                    + Add Variant
                </button>
            </div>`;

        $('#ecf-families-container').append(card);

        // Auto-add one variant row
        $(`.ecf-family-card[data-index="${fi}"] .ecf-add-variant`).trigger('click');
    });

    // Remove Family
    $(document).on('click', '.ecf-remove-family', function () {
        $(this).closest('.ecf-family-card').remove();
    });

    // Add Variant
    $(document).on('click', '.ecf-add-variant', function () {
        const fi = $(this).data('family-index') ?? $(this).closest('.ecf-family-card').data('index');
        const $tbody = $(this).closest('.ecf-family-card').find('.ecf-variants-table tbody');
        const vi = $tbody.find('tr').length;

        const weightOptions = [
            ['100', '100 (Thin)'],
            ['200', '200 (Extra Light)'],
            ['300', '300 (Light)'],
            ['400', '400 (Regular)'],
            ['500', '500 (Medium)'],
            ['600', '600 (Semi Bold)'],
            ['700', '700 (Bold)'],
            ['800', '800 (Extra Bold)'],
            ['900', '900 (Black)'],
            ['100 900', '100–900 (Variable)'],
        ];

        let weightHtml = '';
        weightOptions.forEach(function (w) {
            const sel = w[0] === '400' ? ' selected' : '';
            weightHtml += '<option value="' + w[0] + '"' + sel + '>' + w[1] + '</option>';
        });

        const row = `<tr>
            <td>
                <select name="ecf_font_families[${fi}][variants][${vi}][file]" class="ecf-file-select">
                    ${buildFileOptions('')}
                </select>
            </td>
            <td>
                <select name="ecf_font_families[${fi}][variants][${vi}][weight]">
                    ${weightHtml}
                </select>
            </td>
            <td>
                <select name="ecf_font_families[${fi}][variants][${vi}][style]">
                    <option value="normal">Normal</option>
                    <option value="italic">Italic</option>
                </select>
            </td>
            <td>
                <button type="button" class="button button-small ecf-remove-variant">✕</button>
            </td>
        </tr>`;

        $tbody.append(row);
    });

    // Remove Variant
    $(document).on('click', '.ecf-remove-variant', function () {
        $(this).closest('tr').remove();
    });

    // =========================================================================
    // Google Fonts Search & Install
    // =========================================================================

    const $gfSearch = $('#ecf-gf-search');
    const $gfResults = $('#ecf-gf-results');
    const $gfStatus = $('#ecf-gf-status');
    let gfSearchTimeout = null;

    // Search on button click
    $('#ecf-gf-search-btn').on('click', function () {
        searchGoogleFonts($gfSearch.val().trim());
    });

    // Search on Enter key
    $gfSearch.on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchGoogleFonts($(this).val().trim());
        }
    });

    // Live search with debounce (300ms after typing stops)
    $gfSearch.on('input', function () {
        clearTimeout(gfSearchTimeout);
        const query = $(this).val().trim();
        if (query.length < 2) {
            $gfResults.empty();
            return;
        }
        gfSearchTimeout = setTimeout(function () {
            searchGoogleFonts(query);
        }, 300);
    });

    function searchGoogleFonts(query) {
        if (!query) {
            $gfResults.empty();
            return;
        }

        $gfResults.html('<p class="ecf-gf-loading">Searching Google Fonts...</p>');

        $.ajax({
            url: ecfAdmin.ajaxUrl,
            type: 'GET',
            data: {
                action: 'ecf_search_google_fonts',
                nonce: ecfAdmin.nonce,
                query: query
            },
            success: function (response) {
                if (response.success && response.data.length) {
                    renderGoogleFontsResults(response.data);
                } else if (response.success) {
                    $gfResults.html('<p class="ecf-gf-empty">No fonts found matching "' + escapeHtml(query) + '".</p>');
                } else {
                    $gfResults.html('<p class="ecf-gf-error">' + escapeHtml(response.data) + '</p>');
                }
            },
            error: function () {
                $gfResults.html('<p class="ecf-gf-error">Search request failed. Please try again.</p>');
            }
        });
    }

    /**
     * Human-readable weight labels.
     */
    const weightLabels = {
        '100': 'Thin',
        '200': 'Extra Light',
        '300': 'Light',
        '400': 'Regular',
        '500': 'Medium',
        '600': 'Semi Bold',
        '700': 'Bold',
        '800': 'Extra Bold',
        '900': 'Black',
    };

    /**
     * Build a concise description of the variants a font includes.
     * e.g. "Regular, Medium, Bold + Italic"
     */
    function describeVariants(variants) {
        if (!variants || !variants.length) return '';

        const normals = [];
        let hasItalic = false;

        variants.forEach(function (v) {
            if (v.style === 'italic') {
                hasItalic = true;
            } else {
                normals.push(weightLabels[v.weight] || v.weight);
            }
        });

        let desc = normals.join(', ');
        if (hasItalic) {
            desc += ' + Italic';
        }
        return desc;
    }

    function renderGoogleFontsResults(fonts) {
        $gfResults.empty();

        // Collect all family names for a single Google Fonts stylesheet request
        const familyParams = fonts.map(function (font) {
            return 'family=' + encodeURIComponent(font.family);
        }).join('&');

        // Load preview stylesheet
        const previewLinkId = 'ecf-gf-preview-css';
        $('#' + previewLinkId).remove();
        $('head').append(
            '<link id="' + previewLinkId + '" rel="stylesheet" href="https://fonts.googleapis.com/css2?' + familyParams + '&display=swap" />'
        );

        fonts.forEach(function (font) {
            const $card = $('<div class="ecf-gf-card">');

            // Top row: name + category on the left, install button on the right
            const $header = $('<div class="ecf-gf-card-header">');
            const $info = $('<div class="ecf-gf-card-info">');
            $info.append('<span class="ecf-gf-card-name">' + escapeHtml(font.family) + '</span>');
            $info.append('<span class="ecf-gf-card-category">' + escapeHtml(font.category || '') + '</span>');
            $header.append($info);
            $header.append(
                '<button type="button" class="button button-primary button-small ecf-gf-install" data-family="' + escapeHtml(font.family) + '">Install</button>'
            );

            // Preview text
            const $preview = $('<div class="ecf-gf-card-preview">')
                .css('font-family', '"' + font.family + '", sans-serif')
                .text('The quick brown fox jumps over the lazy dog');

            // Variant details
            const variantCount = font.variants ? font.variants.length : 0;
            const variantDesc = describeVariants(font.variants);
            const $variants = $('<div class="ecf-gf-card-variants">')
                .text(variantCount + ' variant' + (variantCount !== 1 ? 's' : '') + (variantDesc ? ': ' + variantDesc : ''));

            $card.append($header).append($preview).append($variants);
            $gfResults.append($card);
        });
    }

    // Install a Google Font
    $(document).on('click', '.ecf-gf-install', function () {
        const $btn = $(this);
        const family = $btn.data('family');
        const $card = $btn.closest('.ecf-gf-card');

        $btn.prop('disabled', true).text('Installing...');

        $.ajax({
            url: ecfAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ecf_install_google_font',
                nonce: ecfAdmin.nonce,
                family: family
            },
            success: function (response) {
                if (response.success) {
                    $btn.text('Installed').addClass('ecf-gf-installed');

                    // Show success message briefly, then reload so the font family
                    // appears in Section 3 and the ACSS dropdowns.
                    const $msg = $('<div class="ecf-upload-msg success">')
                        .text(response.data.message + ' Reloading page...');
                    $gfStatus.append($msg);

                    setTimeout(function () {
                        window.location.reload();
                    }, 1000);
                } else {
                    $btn.prop('disabled', false).text('Install');
                    const $msg = $('<div class="ecf-upload-msg error">')
                        .text('Failed: ' + response.data);
                    $gfStatus.append($msg);
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Install');
                const $msg = $('<div class="ecf-upload-msg error">')
                    .text('Install request failed for "' + family + '".');
                $gfStatus.append($msg);
            }
        });
    });

    // =========================================================================
    // Init
    // =========================================================================

    initUploadedFiles();

})(jQuery);
