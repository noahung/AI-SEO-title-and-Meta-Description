jQuery(document).ready(function($) {
    var selectedPages = [];
    var keyphrases = {}; // Store keyphrases globally

    $('#bsg-loading').hide();

    // Debug: Confirm the script is loaded
    console.log('Bulk SEO Generator script loaded.');

    // Open the page selection modal
    $('#bsg-select-pages').on('click', function(e) {
        e.preventDefault();

        // Debug: Confirm the button click is detected
        console.log('Select Pages button clicked.');

        // Fetch pages via AJAX
        $.ajax({
            url: bsg_ajax.ajax_url,
            method: 'POST',
            data: { action: 'bsg_fetch_pages', nonce: bsg_ajax.nonce },
            success: function(response) {
                // Debug: Log the AJAX response
                console.log('Fetch pages response:', response);

                if (response.success) {
                    // Sort pages alphabetically (client-side fallback)
                    response.data.sort(function(a, b) {
                        return a.title.localeCompare(b.title);
                    });

                    var pageListHtml = '<div class="bsg-page-grid">';
                    $.each(response.data, function(i, page) {
                        pageListHtml += '<div class="bsg-page-grid-item">' +
                            '<label>' +
                            '<input type="checkbox" class="bsg-page-checkbox" data-id="' + page.id + '" data-title="' + page.title + '"> ' +
                            '<span class="bsg-page-title">' + page.title + '</span>' +
                            '</label>' +
                            '</div>';
                    });
                    pageListHtml += '</div>';
                    $('#bsg-page-list').html(pageListHtml);
                    $('#bsg-page-modal').show();

                    // Debug: Confirm the modal is shown and the grid is rendered
                    console.log('Page selection modal displayed.');
                    console.log('Page grid HTML:', $('#bsg-page-list').html());
                } else {
                    alert('Error fetching pages: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                // Debug: Log AJAX error
                console.log('AJAX Error fetching pages:', status, error, xhr.responseText);
                alert('AJAX Error: ' + error);
            }
        });
    });

    // Close the modal
    $('#bsg-modal-close').on('click', function() {
        $('#bsg-page-modal').hide();

        // Debug: Confirm the modal is closed
        console.log('Page selection modal closed.');
    });

    // Confirm page selection
    $('#bsg-modal-confirm').on('click', function() {
        selectedPages = [];
        $('.bsg-page-checkbox:checked').each(function() {
            var $checkbox = $(this);
            selectedPages.push({
                id: $checkbox.data('id'),
                title: $checkbox.data('title')
            });
        });

        if (selectedPages.length === 0) {
            alert('Please select at least one page.');
            return;
        }

        // Update the preview
        var previewHtml = '';
        $.each(selectedPages, function(i, page) {
            previewHtml += '<div class="bsg-page-item">' + page.title + '</div>';
        });
        $('#bsg-page-preview').html(previewHtml);
        $('#bsg-selected-pages').show();

        $('#bsg-page-count').text('(' + selectedPages.length + ')');
        $('#bsg-preview').prop('disabled', false);
        $('#bsg-page-modal').hide();

        // Debug: Confirm pages are selected
        console.log('Selected pages:', selectedPages);
    });

    $('#bsg-preview').on('click', function() {
        if (selectedPages.length === 0) {
            alert('Please select some pages first.');
            return;
        }

        $('#bsg-loading').show();
        $('#bsg-error').hide();
        $('#bsg-results').hide();
        $('#bsg-save').hide();

        var page_ids = selectedPages.map(function(page) { return page.id; });

        $.ajax({
            url: bsg_ajax.ajax_url,
            method: 'POST',
            data: { 
                action: 'bsg_generate_seo_content', 
                nonce: bsg_ajax.nonce, 
                page_ids: page_ids,
                keyphrases: keyphrases // Send the stored keyphrases
            },
            success: function(response) {
                $('#bsg-loading').hide();
                if (response.success) {
                    var html = '';
                    $.each(response.data, function(i, item) {
                        // Store the keyphrase for this page
                        keyphrases[item.id] = item.keyphrase || '';

                        html += '<tr>' +
                            '<td><a href="' + item.permalink + '" target="_blank">' + item.title + '</a></td>' +
                            '<td>' + (item.current_title || '[None]') + '<br>' + (item.current_desc || '[None]') + '</td>' +
                            '<td>' +
                            '<div class="bsg-keyphrase-item">' +
                            '<label>Focused Keyphrase</label>' +
                            '<input type="text" class="bsg-keyphrase-input" data-id="' + item.id + '" value="' + (item.keyphrase || '') + '" placeholder="Enter focused keyphrase (e.g., aluminum windows)" />' +
                            '</div>' +
                            '<input type="text" class="bsg-seo-input" data-id="' + item.id + '" name="seo_title" value="' + (item.generated_title || '') + '" /><br>' +
                            '<textarea class="bsg-seo-input" data-id="' + item.id + '" name="meta_desc" rows="3">' + (item.generated_desc || '') + '</textarea>' +
                            '</td>' +
                            '</tr>';
                    });
                    $('#bsg-table-body').html(html);
                    $('#bsg-results').show();
                    $('#bsg-save').show();

                    // Add event listener to update keyphrases when they change
                    $('.bsg-keyphrase-input').on('input', function() {
                        var $input = $(this);
                        var page_id = $input.data('id');
                        keyphrases[page_id] = $input.val();
                    });
                } else {
                    $('#bsg-error').text(response.data).show();
                }
            },
            error: function(xhr, status, error) {
                $('#bsg-loading').hide();
                $('#bsg-error').text('AJAX Error: ' + error).show();
            }
        });
    });

    $('#bsg-save').on('click', function() {
        var seo_content = [];
        $('#bsg-table-body tr').each(function() {
            var $row = $(this);
            var page_id = $row.find('.bsg-seo-input').first().data('id');
            seo_content.push({
                id: page_id,
                seo_title: $row.find('input[name="seo_title"]').val(),
                meta_desc: $row.find('textarea[name="meta_desc"]').val()
            });
        });

        $('#bsg-loading').show();
        $('#bsg-error').hide();

        $.ajax({
            url: bsg_ajax.ajax_url,
            method: 'POST',
            data: { action: 'bsg_save_seo_content', nonce: bsg_ajax.nonce, seo_content: seo_content },
            success: function(response) {
                $('#bsg-loading').hide();
                if (response.success) {
                    alert('SEO content saved successfully!');
                } else {
                    $('#bsg-error').text(response.data).show();
                }
            },
            error: function(xhr, status, error) {
                $('#bsg-loading').hide();
                $('#bsg-error').text('AJAX Error: ' + error).show();
            }
        });
    });
});
