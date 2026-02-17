/**
 * Front Door Film Submissions - Form JavaScript
 */

(function($) {
    'use strict';

    // Character count for short description
    $('#fd-short-description').on('input', function() {
        $('#fd-short-desc-count').text($(this).val().length);
    });

    // Submission form handler
    $('#frontdoor-submission-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $('#frontdoor-submit-btn');
        var $btnText = $btn.find('.btn-text');
        var $btnLoading = $btn.find('.btn-loading');
        
        // Disable button and show loading
        $btn.prop('disabled', true);
        $btnText.hide();
        $btnLoading.show();
        
        // Hide any previous messages
        $('#frontdoor-success, #frontdoor-error').hide();
        
        // Collect form data
        var formData = {
            action: 'frontdoor_submit',
            nonce: frontdoorAjax.nonce,
            title: $('#fd-title').val(),
            short_description: $('#fd-short-description').val(),
            description: $('#fd-description').val(),
            genre: $('#fd-genre').val(),
            runtime_minutes: $('#fd-runtime').val(),
            festival_awards: $('#fd-awards').val(),
            is_first_film: $('#fd-first-film').is(':checked') ? '1' : '0',
            video_url: $('#fd-video-url').val(),
            poster_url: $('#fd-poster-url').val(),
            filmmaker_name: $('#fd-filmmaker-name').val(),
            filmmaker_email: $('#fd-filmmaker-email').val()
        };
        
        // Send AJAX request
        $.ajax({
            url: frontdoorAjax.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('#frontdoor-submitted-email').text(response.data.email);
                    $('#frontdoor-success').show();
                    $form.hide();
                    
                    // Scroll to success message
                    $('html, body').animate({
                        scrollTop: $('#frontdoor-success').offset().top - 100
                    }, 500);
                } else {
                    // Show error message - handle various response formats
                    var errorMsg = 'Submission failed. Please try again.';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMsg = response.data;
                        } else if (response.data.message) {
                            errorMsg = response.data.message;
                        } else if (response.data.detail) {
                            errorMsg = response.data.detail;
                        }
                    }
                    $('#frontdoor-error-text').text(errorMsg);
                    $('#frontdoor-error').show();
                    
                    // Scroll to error message
                    $('html, body').animate({
                        scrollTop: $('#frontdoor-error').offset().top - 100
                    }, 500);
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Network error. Please check your connection and try again.';
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.detail) errorMsg = resp.detail;
                    else if (resp.message) errorMsg = resp.message;
                } catch(e) {}
                $('#frontdoor-error-text').text(errorMsg);
                $('#frontdoor-error').show();
            },
            complete: function() {
                // Re-enable button
                $btn.prop('disabled', false);
                $btnText.show();
                $btnLoading.hide();
            }
        });
    });

    // Portal lookup form handler
    $('#frontdoor-portal-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $form.find('.frontdoor-submit-btn');
        var $btnText = $btn.find('.btn-text');
        var $btnLoading = $btn.find('.btn-loading');
        var $results = $('#frontdoor-portal-results');
        
        // Disable button and show loading
        $btn.prop('disabled', true);
        $btnText.hide();
        $btnLoading.show();
        $results.hide();
        
        var email = $('#fd-portal-email').val();
        
        $.ajax({
            url: frontdoorAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'frontdoor_portal_lookup',
                nonce: frontdoorAjax.nonce,
                email: email
            },
            success: function(response) {
                if (response.success) {
                    renderPortalResults(response.data, email);
                    $results.show();
                } else {
                    $results.html('<div class="frontdoor-error"><p>' + response.data.message + '</p></div>').show();
                }
            },
            error: function() {
                $results.html('<div class="frontdoor-error"><p>Network error. Please try again.</p></div>').show();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btnText.show();
                $btnLoading.hide();
            }
        });
    });

    // Render portal results
    function renderPortalResults(data, email) {
        var $results = $('#frontdoor-portal-results');
        
        if (!data.submissions || data.submissions.length === 0) {
            $results.html(
                '<div class="frontdoor-no-results">' +
                    '<p>No submissions found for <strong>' + escapeHtml(email) + '</strong></p>' +
                    '<p><a href="/submit-film">Submit your first film →</a></p>' +
                '</div>'
            );
            return;
        }
        
        var html = '<h3>Your Submissions (' + data.count + ')</h3>';
        
        data.submissions.forEach(function(sub) {
            var statusClass = 'frontdoor-status-' + sub.status;
            var statusLabel = formatStatus(sub.status);
            var createdDate = sub.created_at ? new Date(sub.created_at).toLocaleDateString() : 'N/A';
            
            html += '<div class="frontdoor-submission-card">';
            html += '<h4>' + escapeHtml(sub.title) + '</h4>';
            html += '<div class="frontdoor-submission-meta">';
            html += '<span class="frontdoor-status-badge ' + statusClass + '">' + statusLabel + '</span>';
            html += '<span>Submitted: ' + createdDate + '</span>';
            
            if (sub.recommended_shelf) {
                html += '<span>Shelf: ' + escapeHtml(sub.recommended_shelf) + '</span>';
            }
            
            html += '</div>';
            
            // QA Summary
            if (sub.qa_summary) {
                html += '<div class="frontdoor-qa-summary">';
                html += '<strong>QA Results:</strong>';
                html += '<div class="frontdoor-qa-checks">';
                
                var checks = sub.qa_summary.technical_checks;
                if (checks) {
                    html += qaCheckItem('Video Accessible', checks.video_accessible);
                    html += qaCheckItem('Valid Codec', checks.codec_valid);
                    html += qaCheckItem('Resolution OK', checks.resolution_acceptable);
                    html += qaCheckItem('Audio Present', checks.audio_present);
                }
                
                html += '</div>';
                
                if (sub.qa_summary.issues && sub.qa_summary.issues.length > 0) {
                    html += '<p style="color: #dc2626; margin-top: 10px;"><strong>Issues:</strong> ' + escapeHtml(sub.qa_summary.issues.join(', ')) + '</p>';
                }
                
                html += '</div>';
            }
            
            if (sub.rejection_reason) {
                html += '<p style="color: #6b7280; margin-top: 10px;"><strong>Reason:</strong> ' + escapeHtml(sub.rejection_reason) + '</p>';
            }
            
            html += '</div>';
        });
        
        $results.html(html);
    }

    function qaCheckItem(label, passed) {
        var className = passed ? 'passed' : 'failed';
        var icon = passed ? '✓' : '✗';
        return '<span class="frontdoor-qa-check ' + className + '">' + icon + ' ' + label + '</span>';
    }

    function formatStatus(status) {
        var statusMap = {
            'pending': 'Pending',
            'qa_processing': 'Processing',
            'qa_passed': 'QA Passed',
            'qa_failed': 'QA Failed',
            'classified': 'Under Review',
            'published': 'Published',
            'rejected': 'Not Selected'
        };
        return statusMap[status] || status;
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);
