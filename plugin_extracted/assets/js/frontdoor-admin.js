/**
 * Front Door Admin JavaScript
 */

(function($) {
    'use strict';
    
    var currentPublishId = null;
    var currentRejectId = null;

    // Close modals
    $(document).on('click', '.frontdoor-modal-close, .frontdoor-modal', function(e) {
        if (e.target === this) {
            $('.frontdoor-modal').hide();
        }
    });

    // View Details
    $(document).on('click', '.frontdoor-view-details', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        
        $('#frontdoor-modal-body').html('<p style="text-align: center; padding: 40px;">Loading...</p>');
        $('#frontdoor-modal').show();
        
        $.ajax({
            url: frontdoorAdmin.ajaxurl,
            type: 'GET',
            data: {
                action: 'frontdoor_admin_get_details',
                submission_id: id
            },
            success: function(response) {
                if (response.success) {
                    renderDetails(response.data.submission, response.data.qa_report);
                } else {
                    $('#frontdoor-modal-body').html('<p style="color: red;">Error loading details</p>');
                }
            },
            error: function() {
                $('#frontdoor-modal-body').html('<p style="color: red;">Connection error</p>');
            }
        });
    });

    function renderDetails(sub, qa) {
        var html = '<h2 style="margin-top: 0;">' + escapeHtml(sub.title) + '</h2>';
        html += '<div class="frontdoor-details-grid">';
        
        // Basic Info
        html += '<div class="frontdoor-details-section">';
        html += '<h4>Film Details</h4>';
        html += detailRow('Status', '<span class="frontdoor-status frontdoor-status-' + sub.status + '">' + formatStatus(sub.status) + '</span>');
        html += detailRow('Genre', sub.genre || 'Not specified');
        html += detailRow('Runtime', sub.runtime_minutes ? sub.runtime_minutes + ' minutes' : 'Not specified');
        html += detailRow('First Film', sub.is_first_film ? 'Yes' : 'No');
        if (sub.festival_awards) {
            html += detailRow('Awards', sub.festival_awards);
        }
        html += '</div>';
        
        // Filmmaker Info
        html += '<div class="frontdoor-details-section">';
        html += '<h4>Filmmaker</h4>';
        html += detailRow('Name', sub.filmmaker_name);
        html += detailRow('Email', sub.filmmaker_email);
        html += detailRow('Submitted', sub.created_at ? new Date(sub.created_at).toLocaleDateString() : 'N/A');
        if (sub.recommended_shelf) {
            html += detailRow('Recommended', sub.recommended_shelf);
        }
        if (sub.assigned_shelf) {
            html += detailRow('Published To', sub.assigned_shelf);
        }
        html += '</div>';
        
        // Description
        html += '<div class="frontdoor-details-section full-width">';
        html += '<h4>Description</h4>';
        html += '<p><strong>Tagline:</strong> ' + escapeHtml(sub.short_description) + '</p>';
        html += '<p>' + escapeHtml(sub.description) + '</p>';
        html += '</div>';
        
        // QA Report
        if (qa) {
            html += '<div class="frontdoor-details-section full-width">';
            html += '<h4>QA Report</h4>';
            html += '<div class="frontdoor-qa-grid">';
            
            var checks = qa.technical_checks || {};
            html += qaItem('Video Accessible', checks.video_accessible);
            html += qaItem('Valid Codec', checks.codec_valid);
            html += qaItem('Resolution OK', checks.resolution_acceptable);
            html += qaItem('Audio Present', checks.audio_present);
            html += qaItem('Duration Valid', checks.duration_valid);
            html += qaItem('Bitrate OK', checks.bitrate_acceptable);
            
            html += '</div>';
            
            if (checks.details) {
                html += '<div style="margin-top: 15px; padding: 10px; background: #f1f5f9; border-radius: 6px; font-size: 0.85rem;">';
                if (checks.details.resolution) html += '<span style="margin-right: 15px;"><strong>Resolution:</strong> ' + checks.details.resolution + '</span>';
                if (checks.details.codec) html += '<span style="margin-right: 15px;"><strong>Codec:</strong> ' + checks.details.codec + '</span>';
                if (checks.details.duration_minutes) html += '<span style="margin-right: 15px;"><strong>Duration:</strong> ' + checks.details.duration_minutes + ' min</span>';
                html += '</div>';
            }
            
            if (qa.issues && qa.issues.length > 0) {
                html += '<div style="margin-top: 15px; padding: 10px; background: #fee2e2; border-radius: 6px; color: #991b1b;">';
                html += '<strong>Issues:</strong> ' + qa.issues.join(', ');
                html += '</div>';
            }
            
            html += '</div>';
        }
        
        // Media Preview
        html += '<div class="frontdoor-details-section full-width">';
        html += '<h4>Media</h4>';
        html += '<p><strong>Video URL:</strong> <a href="' + escapeHtml(sub.video_url) + '" target="_blank">' + escapeHtml(sub.video_url) + '</a></p>';
        html += '<p><strong>Poster:</strong></p>';
        html += '<img src="' + escapeHtml(sub.poster_url) + '" class="frontdoor-poster-preview" onerror="this.style.display=\'none\'">';
        html += '</div>';
        
        html += '</div>'; // end grid
        
        $('#frontdoor-modal-body').html(html);
    }

    function detailRow(label, value) {
        return '<div class="frontdoor-detail-row"><span class="frontdoor-detail-label">' + label + ':</span><span class="frontdoor-detail-value">' + value + '</span></div>';
    }

    function qaItem(label, passed) {
        var cls = passed ? 'passed' : 'failed';
        var icon = passed ? '✓' : '✗';
        return '<div class="frontdoor-qa-item ' + cls + '">' + icon + ' ' + label + '</div>';
    }

    function formatStatus(status) {
        return status.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
    }

    // Publish button click
    $(document).on('click', '.frontdoor-publish-btn', function() {
        currentPublishId = $(this).data('id');
        var shelf = $(this).data('shelf');
        
        if (shelf) {
            $('#frontdoor-shelf-select').val(shelf);
        }
        
        $('#frontdoor-publish-id').val(currentPublishId);
        $('#frontdoor-publish-modal').show();
    });

    // Cancel publish
    $('#frontdoor-cancel-publish').on('click', function() {
        $('#frontdoor-publish-modal').hide();
        currentPublishId = null;
    });

    // Confirm publish
    $('#frontdoor-confirm-publish').on('click', function() {
        var $btn = $(this);
        var shelf = $('#frontdoor-shelf-select').val();
        
        $btn.prop('disabled', true).text('Publishing...');
        
        $.ajax({
            url: frontdoorAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'frontdoor_admin_publish',
                nonce: frontdoorAdmin.nonce,
                submission_id: currentPublishId,
                shelf: shelf
            },
            success: function(response) {
                if (response.success) {
                    alert('Film published successfully to: ' + shelf);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('Connection error');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Publish Film');
                $('#frontdoor-publish-modal').hide();
            }
        });
    });

    // Reject button click
    $(document).on('click', '.frontdoor-reject-btn', function() {
        currentRejectId = $(this).data('id');
        $('#frontdoor-reject-id').val(currentRejectId);
        $('#frontdoor-reject-reason').val('');
        $('#frontdoor-reject-modal').show();
    });

    // Cancel reject
    $('#frontdoor-cancel-reject').on('click', function() {
        $('#frontdoor-reject-modal').hide();
        currentRejectId = null;
    });

    // Confirm reject
    $('#frontdoor-confirm-reject').on('click', function() {
        var $btn = $(this);
        var reason = $('#frontdoor-reject-reason').val();
        
        if (!reason.trim()) {
            alert('Please provide a reason for rejection');
            return;
        }
        
        $btn.prop('disabled', true).text('Rejecting...');
        
        $.ajax({
            url: frontdoorAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'frontdoor_admin_reject',
                nonce: frontdoorAdmin.nonce,
                submission_id: currentRejectId,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    alert('Submission rejected');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('Connection error');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Reject Submission');
                $('#frontdoor-reject-modal').hide();
            }
        });
    });

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);
