/**
 * Front Door Film Submissions - Form JavaScript with Stripe Payment
 */

(function($) {
    'use strict';

    // Character count for short description
    $('#fd-short-description').on('input', function() {
        $('#fd-short-desc-count').text($(this).val().length);
    });

    // Continue to Payment button
    $('#frontdoor-continue-btn').on('click', function() {
        var $form = $('#frontdoor-submission-form');
        var $step1 = $form.find('[data-step="1"]');
        var $step2 = $form.find('[data-step="2"]');
        
        // Validate required fields in step 1
        var isValid = true;
        $step1.find('[required]').each(function() {
            if (!$(this).val() || ($(this).is(':checkbox') && !$(this).is(':checked'))) {
                isValid = false;
                $(this).addClass('frontdoor-invalid');
            } else {
                $(this).removeClass('frontdoor-invalid');
            }
        });
        
        if (!isValid) {
            alert('Please fill in all required fields.');
            return;
        }
        
        // Update payment summary
        $('#payment-film-title').text($('#fd-title').val());
        $('#payment-genre').text($('#fd-genre').val() || 'Not specified');
        $('#payment-filmmaker').text($('#fd-filmmaker-name').val());
        $('#payment-email').text($('#fd-filmmaker-email').val());
        
        // Switch to step 2
        $step1.hide();
        $step2.show();
        
        // Update progress
        $('.frontdoor-step[data-step="1"]').removeClass('active').addClass('completed');
        $('.frontdoor-step[data-step="2"]').addClass('active');
        
        // Hide guidelines
        $('#frontdoor-guidelines').hide();
        
        // Scroll to top
        $('html, body').animate({
            scrollTop: $('.frontdoor-submission-wrapper').offset().top - 50
        }, 300);
    });

    // Back to Details button
    $('#frontdoor-back-to-details').on('click', function() {
        var $form = $('#frontdoor-submission-form');
        var $step1 = $form.find('[data-step="1"]');
        var $step2 = $form.find('[data-step="2"]');
        
        // Switch to step 1
        $step2.hide();
        $step1.show();
        
        // Update progress
        $('.frontdoor-step[data-step="2"]').removeClass('active');
        $('.frontdoor-step[data-step="1"]').removeClass('completed').addClass('active');
        
        // Show guidelines
        $('#frontdoor-guidelines').show();
    });

    // Payment form submission
    $('#frontdoor-submission-form').on('submit', function(e) {
        e.preventDefault();
        
        var $btn = $('#frontdoor-pay-btn');
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
            action: 'frontdoor_create_checkout',
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
            filmmaker_email: $('#fd-filmmaker-email').val(),
            return_url: window.location.href.split('?')[0]
        };
        
        // Create Stripe checkout session
        $.ajax({
            url: frontdoorAjax.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success && response.data.checkout_url) {
                    // Redirect to Stripe Checkout
                    window.location.href = response.data.checkout_url;
                } else {
                    // Show error message
                    var errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : 'Could not create payment session. Please try again.';
                    $('#frontdoor-error-text').text(errorMsg);
                    $('#frontdoor-error').show();
                    
                    // Re-enable button
                    $btn.prop('disabled', false);
                    $btnText.show();
                    $btnLoading.hide();
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Network error. Please check your connection and try again.';
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.data && resp.data.message) errorMsg = resp.data.message;
                } catch(e) {}
                $('#frontdoor-error-text').text(errorMsg);
                $('#frontdoor-error').show();
                
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
        var $btn = $form.find('.frontdoor-portal-btn');
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
                    $results.html('<div class="frontdoor-error"><p>' + (response.data.message || 'Error looking up submissions.') + '</p></div>').show();
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
        var icon = passed ? '&#10003;' : '&#10007;';
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

/**
 * Process payment after returning from Stripe
 */
function frontdoorProcessPayment(sessionId) {
    jQuery(function($) {
        var maxAttempts = 10;
        var attemptCount = 0;
        var pollInterval = 2000; // 2 seconds
        
        function checkPaymentAndSubmit() {
            attemptCount++;
            
            $.ajax({
                url: frontdoorAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'frontdoor_process_payment',
                    nonce: frontdoorAjax.nonce,
                    session_id: sessionId
                },
                success: function(response) {
                    $('#frontdoor-processing').hide();
                    
                    if (response.success) {
                        // Show success message
                        $('#frontdoor-submitted-email').text(response.data.email || '');
                        $('#frontdoor-success').show();
                        
                        // Clean up URL
                        if (window.history && window.history.replaceState) {
                            var cleanUrl = window.location.href.split('?')[0];
                            window.history.replaceState({}, document.title, cleanUrl);
                        }
                    } else {
                        // Check if we should retry (payment might still be processing)
                        if (attemptCount < maxAttempts && response.data && response.data.message && 
                            response.data.message.indexOf('not completed') !== -1) {
                            $('#frontdoor-processing').show();
                            setTimeout(checkPaymentAndSubmit, pollInterval);
                        } else {
                            // Show error
                            var errorMsg = response.data && response.data.message 
                                ? response.data.message 
                                : 'Payment processing failed.';
                            $('#frontdoor-error-text').text(errorMsg);
                            $('#frontdoor-error').show();
                        }
                    }
                },
                error: function() {
                    // Retry on network error
                    if (attemptCount < maxAttempts) {
                        setTimeout(checkPaymentAndSubmit, pollInterval);
                    } else {
                        $('#frontdoor-processing').hide();
                        $('#frontdoor-error-text').text('Network error while processing payment. Please contact support.');
                        $('#frontdoor-error').show();
                    }
                }
            });
        }
        
        // Start polling
        checkPaymentAndSubmit();
    });
}
