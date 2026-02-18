/**
 * Front Door Film Submissions - Simple Form JavaScript
 */

(function($) {
    'use strict';

    // Character count for short description
    $('#fd-short-description').on('input', function() {
        $('#fd-short-desc-count').text($(this).val().length);
    });

    // Form submission
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
        
        $.ajax({
            url: frontdoorAjax.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#frontdoor-submitted-email').text(response.data.email);
                    $('#frontdoor-success').show();
                    $form.hide();
                    
                    $('html, body').animate({
                        scrollTop: $('#frontdoor-success').offset().top - 100
                    }, 500);
                } else {
                    var errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : 'Submission failed. Please try again.';
                    $('#frontdoor-error-text').text(errorMsg);
                    $('#frontdoor-error').show();
                }
            },
            error: function() {
                $('#frontdoor-error-text').text('Network error. Please check your connection and try again.');
                $('#frontdoor-error').show();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btnText.show();
                $btnLoading.hide();
            }
        });
    });

    // Portal lookup
    $('#frontdoor-portal-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $form.find('.frontdoor-portal-btn');
        var $btnText = $btn.find('.btn-text');
        var $btnLoading = $btn.find('.btn-loading');
        var $results = $('#frontdoor-portal-results');
        
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
                    $results.html('<div class="frontdoor-error"><p>' + (response.data.message || 'Error') + '</p></div>').show();
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

    function renderPortalResults(data, email) {
        var $results = $('#frontdoor-portal-results');
        
        if (!data.submissions || data.submissions.length === 0) {
            $results.html(
                '<div class="frontdoor-no-results">' +
                '<p>No submissions found for <strong>' + escapeHtml(email) + '</strong></p>' +
                '<p><a href="/front-door-submission-form/">Submit your first film &rarr;</a></p>' +
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
            
            if (sub.qa_summary && sub.qa_summary.technical_checks) {
                html += '<div class="frontdoor-qa-summary"><strong>QA Results:</strong><div class="frontdoor-qa-checks">';
                var checks = sub.qa_summary.technical_checks;
                html += qaCheckItem('Video Accessible', checks.video_accessible);
                html += qaCheckItem('Valid Codec', checks.codec_valid);
                html += qaCheckItem('Resolution OK', checks.resolution_acceptable);
                html += qaCheckItem('Audio Present', checks.audio_present);
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
        var icon = passed ? '&#10003;' : '&#10007;';
        var cls = passed ? 'passed' : 'failed';
        return '<span class="frontdoor-qa-check ' + cls + '">' + icon + ' ' + label + '</span>';
    }

    function formatStatus(status) {
        var map = {
            'pending': 'Pending',
            'qa_processing': 'Processing',
            'qa_passed': 'QA Passed',
            'qa_failed': 'QA Failed',
            'classified': 'Under Review',
            'published': 'Published',
            'rejected': 'Not Selected'
        };
        return map[status] || status;
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);
