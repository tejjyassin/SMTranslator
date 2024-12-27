(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Handle individual translation
        $('.translate-button').on('click', function(e) {
            e.preventDefault();
            const postId = $(this).data('post-id');
            showTranslationModal(postId);
        });

        // Handle bulk translations
        $('#bulk-translate').on('click', function(e) {
            e.preventDefault();
            const selectedPosts = [];
            $('.post-select:checked').each(function() {
                selectedPosts.push($(this).val());
            });

            if (selectedPosts.length === 0) {
                alert('Please select at least one post to translate');
                return;
            }

            showBulkTranslationModal(selectedPosts);
        });

        // Select all functionality
        $('#select-all').on('change', function() {
            $('.post-select').prop('checked', $(this).prop('checked'));
        });

        function showTranslationModal(postId) {
            // Implement modal HTML and functionality
            const modalHtml = `
                <div id="translation-modal" class="ait-modal">
                    <div class="ait-modal-content">
                        <h2>Translate Post</h2>
                        <div class="translation-options">
                            <h3>Select Target Languages:</h3>
                            <label><input type="checkbox" name="target_lang[]" value="en"> English</label><br>
                            <label><input type="checkbox" name="target_lang[]" value="ar"> Arabic</label><br>
                            <label><input type="checkbox" name="target_lang[]" value="es"> Spanish</label>
                        </div>
                        <div class="modal-actions">
                            <button class="ait-button" id="start-translation">Start Translation</button>
                            <button class="ait-button cancel-modal">Cancel</button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            $('#translation-modal').addClass('active');

            // Handle modal actions
            $('.cancel-modal').on('click', function() {
                $('#translation-modal').remove();
            });

            $('#start-translation').on('click', function() {
                const selectedLanguages = [];
                $('input[name="target_lang[]"]:checked').each(function() {
                    selectedLanguages.push($(this).val());
                });

                if (selectedLanguages.length === 0) {
                    alert('Please select at least one target language');
                    return;
                }

                initiateTranslation(postId, selectedLanguages);
            });
        }

        function initiateTranslation(postId, languages) {
            // Show loading state
            $('#start-translation').prop('disabled', true).text('Processing...');
            
            $.ajax({
                url: aitVars.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ait_translate_post',
                    security: aitVars.nonce, // Changed from 'nonce' to 'security'
                    post_id: postId,
                    languages: languages
                },
                success: function(response) {
                    console.log('Translation response:', response);
                    if (response.success) {
                        let message = 'Translation completed!\n\n';
                        Object.entries(response.data.results).forEach(([lang, result]) => {
                            if (result.success) {
                                message += `${lang}: Successfully created (ID: ${result.post_id})\n`;
                            } else {
                                message += `${lang}: Failed - ${result.message}\n`;
                            }
                        });
                        alert(message);
                        $('#translation-modal').remove();
                        location.reload();
                    } else {
                        alert(response.data.message || 'Translation failed');
                    }
                },
                
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr, status, error}); // Add error logging
                    alert('An error occurred during translation');
                },
                complete: function() {
                    $('#start-translation').prop('disabled', false).text('Start Translation');
                }
            });
        }

        function showBulkTranslationModal(postIds) {
            const modalHtml = `
                <div id="bulk-translation-modal" class="ait-modal">
                    <div class="ait-modal-content">
                        <h2>Bulk Translate Posts</h2>
                        <div class="translation-options">
                            <h3>Select Target Languages:</h3>
                            <label><input type="checkbox" name="bulk_target_lang[]" value="en"> English</label><br>
                            <label><input type="checkbox" name="bulk_target_lang[]" value="ar"> Arabic</label><br>
                            <label><input type="checkbox" name="bulk_target_lang[]" value="es"> Spanish</label>
                        </div>
                        <div class="modal-actions">
                            <button class="ait-button" id="start-bulk-translation">Start Translation</button>
                            <button class="ait-button cancel-modal">Cancel</button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            $('#bulk-translation-modal').addClass('active');

            $('.cancel-modal').on('click', function() {
                $('#bulk-translation-modal').remove();
            });

            $('#start-bulk-translation').on('click', function() {
                const selectedLanguages = [];
                $('input[name="bulk_target_lang[]"]:checked').each(function() {
                    selectedLanguages.push($(this).val());
                });

                if (selectedLanguages.length === 0) {
                    alert('Please select at least one target language');
                    return;
                }

                initiateBulkTranslation(postIds, selectedLanguages);
            });
        }

        function initiateBulkTranslation(postIds, languages) {
            $.ajax({
                url: aitVars.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ait_bulk_translate',
                    nonce: aitVars.nonce,
                    post_ids: postIds,
                    languages: languages
                },
                success: function(response) {
                    if (response.success) {
                        alert(aitVars.strings.success);
                        $('#bulk-translation-modal').remove();
                        location.reload();
                    } else {
                        alert(response.data.message || aitVars.strings.error);
                    }
                },
                error: function() {
                    alert(aitVars.strings.error);
                }
            });
        }
    });
})(jQuery);