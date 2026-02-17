<?php
/**
 * Plugin Name: Front Door Film Submissions
 * Plugin URI: https://frontdoormedia.org
 * Description: Film submission form for Front Door Media. Use shortcode [frontdoor_submission_form] to embed the form.
 * Version: 1.0.0
 * Author: Front Door Media
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FRONTDOOR_API_URL', 'https://frontdoor-api-npz4.onrender.com');
define('FRONTDOOR_VERSION', '1.0.0');

/**
 * Enqueue plugin styles and scripts
 */
function frontdoor_enqueue_assets() {
    wp_enqueue_style(
        'frontdoor-submissions',
        plugin_dir_url(__FILE__) . 'assets/css/frontdoor-form.css',
        array(),
        FRONTDOOR_VERSION
    );
    
    wp_enqueue_script(
        'frontdoor-submissions',
        plugin_dir_url(__FILE__) . 'assets/js/frontdoor-form.js',
        array('jquery'),
        FRONTDOOR_VERSION,
        true
    );
    
    wp_localize_script('frontdoor-submissions', 'frontdoorAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('frontdoor_submit_nonce'),
        'apiUrl' => FRONTDOOR_API_URL
    ));
}
add_action('wp_enqueue_scripts', 'frontdoor_enqueue_assets');

/**
 * Shortcode for the submission form
 */
function frontdoor_submission_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => 'Submit Your Film',
        'show_guidelines' => 'true'
    ), $atts);
    
    ob_start();
    ?>
    <div class="frontdoor-submission-wrapper">
        <div class="frontdoor-form-header">
            <h2><?php echo esc_html($atts['title']); ?></h2>
            <?php if ($atts['show_guidelines'] === 'true'): ?>
            <p class="frontdoor-subtitle">Join our curated platform connecting filmmakers with buyers and audiences worldwide.</p>
            <?php endif; ?>
        </div>
        
        <!-- Success Message (hidden by default) -->
        <div id="frontdoor-success" class="frontdoor-message frontdoor-success" style="display: none;">
            <div class="frontdoor-message-icon">✓</div>
            <h3>Submission Received!</h3>
            <p>Your film has been submitted successfully. Our automated QA system is now reviewing your submission.</p>
            <p>You'll receive an email at <strong id="frontdoor-submitted-email"></strong> with your QA results and next steps.</p>
            <p class="frontdoor-tracking">Track your submission status anytime at our <a href="/filmmaker-portal">Filmmaker Portal</a>.</p>
        </div>
        
        <!-- Error Message (hidden by default) -->
        <div id="frontdoor-error" class="frontdoor-message frontdoor-error" style="display: none;">
            <div class="frontdoor-message-icon">!</div>
            <h3>Submission Error</h3>
            <p id="frontdoor-error-text">There was an error processing your submission. Please try again.</p>
        </div>
        
        <!-- Submission Form -->
        <form id="frontdoor-submission-form" class="frontdoor-form">
            <div class="frontdoor-form-section">
                <h3>Film Details</h3>
                
                <div class="frontdoor-field">
                    <label for="fd-title">Film Title <span class="required">*</span></label>
                    <input type="text" id="fd-title" name="title" required placeholder="Enter your film's title">
                </div>
                
                <div class="frontdoor-field">
                    <label for="fd-short-description">Tagline / Short Description <span class="required">*</span></label>
                    <input type="text" id="fd-short-description" name="short_description" required maxlength="150" placeholder="A brief one-line description (max 150 characters)">
                    <span class="frontdoor-char-count"><span id="fd-short-desc-count">0</span>/150</span>
                </div>
                
                <div class="frontdoor-field">
                    <label for="fd-description">Full Synopsis <span class="required">*</span></label>
                    <textarea id="fd-description" name="description" required rows="4" placeholder="Tell us about your film's story, themes, and what makes it unique..."></textarea>
                </div>
                
                <div class="frontdoor-field-row">
                    <div class="frontdoor-field">
                        <label for="fd-genre">Genre</label>
                        <select id="fd-genre" name="genre">
                            <option value="">Select Genre</option>
                            <option value="Drama">Drama</option>
                            <option value="Comedy">Comedy</option>
                            <option value="Documentary">Documentary</option>
                            <option value="Horror">Horror</option>
                            <option value="Thriller">Thriller</option>
                            <option value="Sci-Fi">Sci-Fi</option>
                            <option value="Animation">Animation</option>
                            <option value="Experimental">Experimental</option>
                            <option value="Music Video">Music Video</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="frontdoor-field">
                        <label for="fd-runtime">Runtime (minutes)</label>
                        <input type="number" id="fd-runtime" name="runtime_minutes" min="1" max="180" placeholder="e.g., 15">
                    </div>
                </div>
                
                <div class="frontdoor-field">
                    <label for="fd-awards">Festival Awards & Selections</label>
                    <textarea id="fd-awards" name="festival_awards" rows="2" placeholder="List any festivals, awards, or official selections..."></textarea>
                </div>
                
                <div class="frontdoor-field frontdoor-checkbox">
                    <label>
                        <input type="checkbox" id="fd-first-film" name="is_first_film" value="1">
                        <span>This is my first film</span>
                    </label>
                </div>
            </div>
            
            <div class="frontdoor-form-section">
                <h3>Media Files</h3>
                
                <div class="frontdoor-field">
                    <label for="fd-video-url">Video URL <span class="required">*</span></label>
                    <input type="url" id="fd-video-url" name="video_url" required placeholder="https://your-hosting.com/your-film.mp4">
                    <p class="frontdoor-help">Direct link to your video file (MP4 preferred). Supported hosts: Vimeo, YouTube, Dropbox, Google Drive, or direct URL.</p>
                </div>
                
                <div class="frontdoor-field">
                    <label for="fd-poster-url">Poster/Thumbnail URL <span class="required">*</span></label>
                    <input type="url" id="fd-poster-url" name="poster_url" required placeholder="https://your-hosting.com/poster.jpg">
                    <p class="frontdoor-help">High-resolution poster image (minimum 1280x720, JPG or PNG).</p>
                </div>
            </div>
            
            <div class="frontdoor-form-section">
                <h3>Filmmaker Information</h3>
                
                <div class="frontdoor-field-row">
                    <div class="frontdoor-field">
                        <label for="fd-filmmaker-name">Your Name <span class="required">*</span></label>
                        <input type="text" id="fd-filmmaker-name" name="filmmaker_name" required placeholder="Full name">
                    </div>
                    
                    <div class="frontdoor-field">
                        <label for="fd-filmmaker-email">Email Address <span class="required">*</span></label>
                        <input type="email" id="fd-filmmaker-email" name="filmmaker_email" required placeholder="your@email.com">
                    </div>
                </div>
            </div>
            
            <div class="frontdoor-form-section frontdoor-terms">
                <div class="frontdoor-field frontdoor-checkbox">
                    <label>
                        <input type="checkbox" id="fd-terms" name="terms" required>
                        <span>I confirm that I have the rights to submit this film and agree to the <a href="/terms" target="_blank">Terms of Service</a> and <a href="/submission-guidelines" target="_blank">Submission Guidelines</a>. <span class="required">*</span></span>
                    </label>
                </div>
            </div>
            
            <div class="frontdoor-form-actions">
                <button type="submit" id="frontdoor-submit-btn" class="frontdoor-submit-btn">
                    <span class="btn-text">Submit Film for Review</span>
                    <span class="btn-loading" style="display: none;">
                        <span class="spinner"></span> Submitting...
                    </span>
                </button>
            </div>
        </form>
        
        <?php if ($atts['show_guidelines'] === 'true'): ?>
        <div class="frontdoor-guidelines">
            <h4>Technical Requirements</h4>
            <ul>
                <li><strong>Video:</strong> H.264 codec, minimum 720p resolution, MP4 format preferred</li>
                <li><strong>Audio:</strong> Stereo or 5.1 surround, properly mixed</li>
                <li><strong>Duration:</strong> 30 seconds to 90 minutes</li>
                <li><strong>Poster:</strong> Minimum 1280x720 pixels, JPG or PNG</li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('frontdoor_submission_form', 'frontdoor_submission_form_shortcode');

/**
 * Handle AJAX form submission
 */
function frontdoor_handle_submission() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'frontdoor_submit_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed. Please refresh and try again.'));
        return;
    }
    
    // Sanitize input data
    $submission_data = array(
        'title' => sanitize_text_field($_POST['title']),
        'short_description' => sanitize_text_field($_POST['short_description']),
        'description' => sanitize_textarea_field($_POST['description']),
        'poster_url' => esc_url_raw($_POST['poster_url']),
        'video_url' => esc_url_raw($_POST['video_url']),
        'video_type' => 'mp4',
        'filmmaker_name' => sanitize_text_field($_POST['filmmaker_name']),
        'filmmaker_email' => sanitize_email($_POST['filmmaker_email']),
        'is_first_film' => isset($_POST['is_first_film']) && $_POST['is_first_film'] === '1'
    );
    
    // Add optional fields only if they have values
    if (!empty($_POST['genre'])) {
        $submission_data['genre'] = sanitize_text_field($_POST['genre']);
    }
    if (!empty($_POST['runtime_minutes'])) {
        $submission_data['runtime_minutes'] = intval($_POST['runtime_minutes']);
    }
    if (!empty($_POST['festival_awards'])) {
        $submission_data['festival_awards'] = sanitize_textarea_field($_POST['festival_awards']);
    }
    
    // Get WordPress post ID if available
    $post_id = get_the_ID();
    if ($post_id) {
        $submission_data['wordpress_post_id'] = strval($post_id);
    }
    
    // Validate required fields
    $required_fields = array('title', 'short_description', 'description', 'poster_url', 'video_url', 'filmmaker_name', 'filmmaker_email');
    foreach ($required_fields as $field) {
        if (empty($submission_data[$field])) {
            wp_send_json_error(array('message' => 'Please fill in all required fields.'));
            return;
        }
    }
    
    // Validate email
    if (!is_email($submission_data['filmmaker_email'])) {
        wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        return;
    }
    
    // Send to Front Door API
    $response = wp_remote_post(FRONTDOOR_API_URL . '/api/submissions', array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($submission_data)
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Connection error. Please try again later.'));
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($response_code === 200 || $response_code === 201) {
        // Success
        wp_send_json_success(array(
            'message' => 'Submission received successfully!',
            'submission_id' => isset($response_body['id']) ? $response_body['id'] : '',
            'email' => $submission_data['filmmaker_email']
        ));
    } else {
        // API error - extract meaningful error message
        $error_message = 'Submission failed. Please try again.';
        if (is_array($response_body)) {
            if (isset($response_body['detail'])) {
                // Handle Pydantic validation errors
                if (is_array($response_body['detail'])) {
                    $errors = array();
                    foreach ($response_body['detail'] as $err) {
                        if (isset($err['msg'])) {
                            $field = isset($err['loc']) ? end($err['loc']) : 'field';
                            $errors[] = ucfirst($field) . ': ' . $err['msg'];
                        }
                    }
                    $error_message = !empty($errors) ? implode('. ', $errors) : 'Validation error.';
                } else {
                    $error_message = $response_body['detail'];
                }
            } elseif (isset($response_body['message'])) {
                $error_message = $response_body['message'];
            }
        }
        wp_send_json_error(array('message' => $error_message));
    }
}
add_action('wp_ajax_frontdoor_submit', 'frontdoor_handle_submission');
add_action('wp_ajax_nopriv_frontdoor_submit', 'frontdoor_handle_submission');

/**
 * Shortcode for filmmaker portal lookup
 */
function frontdoor_portal_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => 'Check Submission Status'
    ), $atts);
    
    // Check for email in URL parameter
    $prefill_email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
    $auto_lookup = !empty($prefill_email);
    
    ob_start();
    ?>
    <div class="frontdoor-portal-wrapper frontdoor-portal-dark">
        <!-- Dark Header -->
        <div class="frontdoor-portal-header">
            <h1>Front Door Media</h1>
            <h2>Filmmaker Portal</h2>
            <p>Track your submission status and view QA feedback</p>
        </div>
        
        <div class="frontdoor-portal-content">
            <!-- Lookup Form -->
            <div class="frontdoor-portal-form-section">
                <p class="frontdoor-portal-form-label">Enter your email address to find your submissions</p>
                <form id="frontdoor-portal-form" class="frontdoor-portal-form-inline">
                    <input type="email" id="fd-portal-email" name="email" required placeholder="your@email.com" value="<?php echo esc_attr($prefill_email); ?>">
                    <button type="submit" class="frontdoor-portal-btn">
                        <span class="btn-text">Look Up</span>
                        <span class="btn-loading" style="display: none;">
                            <span class="spinner"></span>
                        </span>
                    </button>
                </form>
            </div>
            
            <!-- Results -->
            <div id="frontdoor-portal-results" class="frontdoor-portal-results" style="display: none;">
                <!-- Results will be populated by JavaScript -->
            </div>
            
            <!-- Info Section -->
            <div class="frontdoor-portal-info">
                <div class="frontdoor-portal-info-icon">🎬</div>
                <h3>Check Your Submission Status</h3>
                <p>Enter the email address you used when submitting your film to see its current status, QA feedback, and publication details.</p>
            </div>
            
            <!-- FAQ Section -->
            <div class="frontdoor-portal-faq">
                <h3>Frequently Asked Questions</h3>
                <div class="frontdoor-faq-item">
                    <h4>How long does QA take?</h4>
                    <p>Most submissions are processed within 24-48 hours. You'll receive an email when QA is complete.</p>
                </div>
                <div class="frontdoor-faq-item">
                    <h4>What if my submission fails QA?</h4>
                    <p>You'll receive detailed feedback about what needs to be fixed. You can then resubmit with corrections.</p>
                </div>
                <div class="frontdoor-faq-item">
                    <h4>When will my film be published?</h4>
                    <p>After passing QA, your film is reviewed by our curatorial team. Approved films are published to Roku within 7 days.</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($auto_lookup): ?>
    <script>
    jQuery(document).ready(function($) {
        setTimeout(function() {
            $('#frontdoor-portal-form').trigger('submit');
        }, 500);
    });
    </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
add_shortcode('frontdoor_portal', 'frontdoor_portal_shortcode');

/**
 * Handle portal lookup AJAX
 */
function frontdoor_portal_lookup() {
    if (!wp_verify_nonce($_POST['nonce'], 'frontdoor_submit_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }
    
    $email = sanitize_email($_POST['email']);
    
    if (!is_email($email)) {
        wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        return;
    }
    
    $response = wp_remote_get(FRONTDOOR_API_URL . '/api/portal/lookup?email=' . urlencode($email), array(
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Connection error. Please try again.'));
        return;
    }
    
    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    wp_send_json_success($response_body);
}
add_action('wp_ajax_frontdoor_portal_lookup', 'frontdoor_portal_lookup');
add_action('wp_ajax_nopriv_frontdoor_portal_lookup', 'frontdoor_portal_lookup');

// ==================== ADMIN DASHBOARD ====================

/**
 * Add admin menu
 */
function frontdoor_admin_menu() {
    add_menu_page(
        'Front Door Submissions',
        'Film Submissions',
        'manage_options',
        'frontdoor-submissions',
        'frontdoor_admin_page',
        'dashicons-video-alt3',
        30
    );
    
    add_submenu_page(
        'frontdoor-submissions',
        'Analytics',
        'Analytics',
        'manage_options',
        'frontdoor-analytics',
        'frontdoor_analytics_page'
    );
}
add_action('admin_menu', 'frontdoor_admin_menu');

/**
 * Enqueue admin assets
 */
function frontdoor_admin_assets($hook) {
    if (strpos($hook, 'frontdoor') === false) {
        return;
    }
    
    wp_enqueue_style(
        'frontdoor-admin',
        plugin_dir_url(__FILE__) . 'assets/css/frontdoor-admin.css',
        array(),
        FRONTDOOR_VERSION
    );
    
    wp_enqueue_script(
        'frontdoor-admin',
        plugin_dir_url(__FILE__) . 'assets/js/frontdoor-admin.js',
        array('jquery'),
        FRONTDOOR_VERSION,
        true
    );
    
    wp_localize_script('frontdoor-admin', 'frontdoorAdmin', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('frontdoor_admin_nonce'),
        'apiUrl' => FRONTDOOR_API_URL
    ));
}
add_action('admin_enqueue_scripts', 'frontdoor_admin_assets');

/**
 * Admin submissions page
 */
function frontdoor_admin_page() {
    // Fetch submissions from API
    $response = wp_remote_get(FRONTDOOR_API_URL . '/api/submissions?limit=100', array('timeout' => 30));
    $submissions = array();
    $stats = array();
    
    if (!is_wp_error($response)) {
        $submissions = json_decode(wp_remote_retrieve_body($response), true);
    }
    
    // Fetch stats
    $stats_response = wp_remote_get(FRONTDOOR_API_URL . '/api/stats', array('timeout' => 30));
    if (!is_wp_error($stats_response)) {
        $stats = json_decode(wp_remote_retrieve_body($stats_response), true);
    }
    
    ?>
    <div class="wrap frontdoor-admin">
        <h1>Film Submissions</h1>
        
        <!-- Stats Cards -->
        <div class="frontdoor-stats-grid">
            <div class="frontdoor-stat-card">
                <span class="stat-number"><?php echo isset($stats['total_submissions']) ? $stats['total_submissions'] : 0; ?></span>
                <span class="stat-label">Total Submissions</span>
            </div>
            <div class="frontdoor-stat-card pending">
                <span class="stat-number"><?php echo isset($stats['by_status']['pending']) ? $stats['by_status']['pending'] : 0; ?></span>
                <span class="stat-label">Pending</span>
            </div>
            <div class="frontdoor-stat-card processing">
                <span class="stat-number"><?php echo isset($stats['by_status']['classified']) ? $stats['by_status']['classified'] : 0; ?></span>
                <span class="stat-label">Ready to Review</span>
            </div>
            <div class="frontdoor-stat-card published">
                <span class="stat-number"><?php echo isset($stats['by_status']['published']) ? $stats['by_status']['published'] : 0; ?></span>
                <span class="stat-label">Published</span>
            </div>
        </div>
        
        <!-- Submissions Table -->
        <div class="frontdoor-table-wrapper">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 25%;">Title</th>
                        <th style="width: 15%;">Filmmaker</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 18%;">Recommended Shelf</th>
                        <th style="width: 12%;">Submitted</th>
                        <th style="width: 18%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">No submissions yet.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($submissions as $sub): ?>
                    <tr data-id="<?php echo esc_attr($sub['id']); ?>">
                        <td>
                            <strong><?php echo esc_html($sub['title']); ?></strong>
                            <div class="row-actions">
                                <span class="view"><a href="#" class="frontdoor-view-details" data-id="<?php echo esc_attr($sub['id']); ?>">View Details</a></span>
                            </div>
                        </td>
                        <td>
                            <?php echo esc_html($sub['filmmaker_name']); ?><br>
                            <small><?php echo esc_html($sub['filmmaker_email']); ?></small>
                        </td>
                        <td>
                            <span class="frontdoor-status frontdoor-status-<?php echo esc_attr($sub['status']); ?>">
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $sub['status']))); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (isset($sub['recommended_shelf'])): ?>
                                <small><?php echo esc_html($sub['recommended_shelf']); ?></small>
                            <?php else: ?>
                                <small style="color: #999;">Pending classification</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo isset($sub['created_at']) ? date('M j, Y', strtotime($sub['created_at'])) : 'N/A'; ?>
                        </td>
                        <td class="frontdoor-actions">
                            <?php if ($sub['status'] === 'classified' || $sub['status'] === 'qa_passed'): ?>
                                <button class="button button-primary frontdoor-publish-btn" data-id="<?php echo esc_attr($sub['id']); ?>" data-shelf="<?php echo esc_attr($sub['recommended_shelf'] ?? ''); ?>">Publish</button>
                                <button class="button frontdoor-reject-btn" data-id="<?php echo esc_attr($sub['id']); ?>">Reject</button>
                            <?php elseif ($sub['status'] === 'published'): ?>
                                <span style="color: #46b450;">✓ Published</span>
                            <?php elseif ($sub['status'] === 'rejected'): ?>
                                <span style="color: #999;">Rejected</span>
                            <?php else: ?>
                                <span style="color: #999;">Processing...</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Details Modal -->
        <div id="frontdoor-modal" class="frontdoor-modal" style="display: none;">
            <div class="frontdoor-modal-content">
                <span class="frontdoor-modal-close">&times;</span>
                <div id="frontdoor-modal-body">Loading...</div>
            </div>
        </div>
        
        <!-- Publish Modal -->
        <div id="frontdoor-publish-modal" class="frontdoor-modal" style="display: none;">
            <div class="frontdoor-modal-content" style="max-width: 500px;">
                <span class="frontdoor-modal-close">&times;</span>
                <h2>Publish to Shelf</h2>
                <p>Select which shelf to publish this film to:</p>
                <select id="frontdoor-shelf-select" class="widefat">
                    <option value="Now Programming">Now Programming</option>
                    <option value="Premieres">Premieres</option>
                    <option value="Concept Cinema">Concept Cinema</option>
                    <option value="AI Nonfiction">AI Nonfiction</option>
                    <option value="Spotlight">Spotlight</option>
                    <option value="Archive">Archive</option>
                </select>
                <div style="margin-top: 20px; text-align: right;">
                    <button class="button" id="frontdoor-cancel-publish">Cancel</button>
                    <button class="button button-primary" id="frontdoor-confirm-publish">Publish Film</button>
                </div>
                <input type="hidden" id="frontdoor-publish-id" value="">
            </div>
        </div>
        
        <!-- Reject Modal -->
        <div id="frontdoor-reject-modal" class="frontdoor-modal" style="display: none;">
            <div class="frontdoor-modal-content" style="max-width: 500px;">
                <span class="frontdoor-modal-close">&times;</span>
                <h2>Reject Submission</h2>
                <p>Provide a reason for rejection (will be sent to filmmaker):</p>
                <textarea id="frontdoor-reject-reason" class="widefat" rows="4" placeholder="e.g., Video quality does not meet our standards..."></textarea>
                <div style="margin-top: 20px; text-align: right;">
                    <button class="button" id="frontdoor-cancel-reject">Cancel</button>
                    <button class="button" id="frontdoor-confirm-reject" style="background: #dc3232; color: white; border-color: #dc3232;">Reject Submission</button>
                </div>
                <input type="hidden" id="frontdoor-reject-id" value="">
            </div>
        </div>
    </div>
    <?php
}

/**
 * Admin analytics page
 */
function frontdoor_analytics_page() {
    $stats_response = wp_remote_get(FRONTDOOR_API_URL . '/api/stats', array('timeout' => 30));
    $stats = array();
    if (!is_wp_error($stats_response)) {
        $stats = json_decode(wp_remote_retrieve_body($stats_response), true);
    }
    
    ?>
    <div class="wrap frontdoor-admin">
        <h1>Analytics Dashboard</h1>
        <?php echo frontdoor_render_analytics($stats); ?>
    </div>
    <?php
}

/**
 * Render analytics HTML (shared between admin and shortcode)
 */
function frontdoor_render_analytics($stats) {
    $total = isset($stats['total_submissions']) ? $stats['total_submissions'] : 0;
    $published = isset($stats['by_status']['published']) ? $stats['by_status']['published'] : 0;
    $rejected = isset($stats['by_status']['rejected']) ? $stats['by_status']['rejected'] : 0;
    $pending = isset($stats['by_status']['pending']) ? $stats['by_status']['pending'] : 0;
    $qa_passed = isset($stats['by_status']['qa_passed']) ? $stats['by_status']['qa_passed'] : 0;
    $qa_failed = isset($stats['by_status']['qa_failed']) ? $stats['by_status']['qa_failed'] : 0;
    $classified = isset($stats['by_status']['classified']) ? $stats['by_status']['classified'] : 0;
    
    $publish_rate = $total > 0 ? round(($published / $total) * 100, 1) : 0;
    $qa_pass_rate = ($qa_passed + $qa_failed + $classified + $published) > 0 
        ? round((($qa_passed + $classified + $published) / ($qa_passed + $qa_failed + $classified + $published)) * 100, 1) 
        : 0;
    
    ob_start();
    ?>
    <div class="frontdoor-analytics">
        <!-- Overview Stats -->
        <div class="frontdoor-analytics-grid">
            <div class="frontdoor-analytics-card">
                <div class="analytics-icon">🎬</div>
                <div class="analytics-value"><?php echo $total; ?></div>
                <div class="analytics-label">Total Submissions</div>
            </div>
            <div class="frontdoor-analytics-card success">
                <div class="analytics-icon">✅</div>
                <div class="analytics-value"><?php echo $published; ?></div>
                <div class="analytics-label">Published Films</div>
            </div>
            <div class="frontdoor-analytics-card info">
                <div class="analytics-icon">📊</div>
                <div class="analytics-value"><?php echo $publish_rate; ?>%</div>
                <div class="analytics-label">Publish Rate</div>
            </div>
            <div class="frontdoor-analytics-card warning">
                <div class="analytics-icon">🔍</div>
                <div class="analytics-value"><?php echo $qa_pass_rate; ?>%</div>
                <div class="analytics-label">QA Pass Rate</div>
            </div>
        </div>
        
        <!-- Status Breakdown -->
        <div class="frontdoor-analytics-section">
            <h3>Submission Pipeline</h3>
            <div class="frontdoor-pipeline">
                <div class="pipeline-stage">
                    <div class="pipeline-count"><?php echo $pending; ?></div>
                    <div class="pipeline-label">Pending</div>
                    <div class="pipeline-bar" style="background: #f59e0b;"></div>
                </div>
                <div class="pipeline-arrow">→</div>
                <div class="pipeline-stage">
                    <div class="pipeline-count"><?php echo $qa_passed + $classified; ?></div>
                    <div class="pipeline-label">Ready for Review</div>
                    <div class="pipeline-bar" style="background: #3b82f6;"></div>
                </div>
                <div class="pipeline-arrow">→</div>
                <div class="pipeline-stage">
                    <div class="pipeline-count"><?php echo $published; ?></div>
                    <div class="pipeline-label">Published</div>
                    <div class="pipeline-bar" style="background: #10b981;"></div>
                </div>
            </div>
            
            <div class="frontdoor-status-breakdown">
                <div class="status-item">
                    <span class="status-dot pending"></span>
                    <span class="status-name">Pending</span>
                    <span class="status-count"><?php echo $pending; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-dot processing"></span>
                    <span class="status-name">QA Processing</span>
                    <span class="status-count"><?php echo isset($stats['by_status']['qa_processing']) ? $stats['by_status']['qa_processing'] : 0; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-dot passed"></span>
                    <span class="status-name">QA Passed</span>
                    <span class="status-count"><?php echo $qa_passed; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-dot failed"></span>
                    <span class="status-name">QA Failed</span>
                    <span class="status-count"><?php echo $qa_failed; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-dot classified"></span>
                    <span class="status-name">Classified</span>
                    <span class="status-count"><?php echo $classified; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-dot published"></span>
                    <span class="status-name">Published</span>
                    <span class="status-count"><?php echo $published; ?></span>
                </div>
                <div class="status-item">
                    <span class="status-dot rejected"></span>
                    <span class="status-name">Rejected</span>
                    <span class="status-count"><?php echo $rejected; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Shelf Distribution -->
        <div class="frontdoor-analytics-section">
            <h3>Published by Shelf</h3>
            <div class="frontdoor-shelf-stats">
                <?php 
                $shelves = isset($stats['by_shelf']) ? $stats['by_shelf'] : array();
                $max_shelf = max(array_values($shelves) ?: array(1));
                foreach ($shelves as $shelf => $count): 
                    $percent = $max_shelf > 0 ? ($count / $max_shelf) * 100 : 0;
                ?>
                <div class="shelf-stat-item">
                    <div class="shelf-name"><?php echo esc_html($shelf); ?></div>
                    <div class="shelf-bar-container">
                        <div class="shelf-bar" style="width: <?php echo $percent; ?>%;"></div>
                        <span class="shelf-count"><?php echo $count; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Analytics shortcode for public pages
 */
function frontdoor_analytics_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => 'Platform Statistics'
    ), $atts);
    
    $stats_response = wp_remote_get(FRONTDOOR_API_URL . '/api/stats', array('timeout' => 30));
    $stats = array();
    if (!is_wp_error($stats_response)) {
        $stats = json_decode(wp_remote_retrieve_body($stats_response), true);
    }
    
    ob_start();
    ?>
    <div class="frontdoor-analytics-wrapper">
        <div class="frontdoor-form-header">
            <h2><?php echo esc_html($atts['title']); ?></h2>
        </div>
        <?php echo frontdoor_render_analytics($stats); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('frontdoor_analytics', 'frontdoor_analytics_shortcode');

// ==================== ADMIN AJAX HANDLERS ====================

/**
 * Handle publish action
 */
function frontdoor_admin_publish() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'frontdoor_admin_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    $submission_id = sanitize_text_field($_POST['submission_id']);
    $shelf = sanitize_text_field($_POST['shelf']);
    
    $response = wp_remote_post(FRONTDOOR_API_URL . '/api/submissions/' . $submission_id . '/publish?shelf=' . urlencode($shelf), array(
        'timeout' => 30,
        'headers' => array('Content-Type' => 'application/json'),
        'body' => '{}'
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Connection error'));
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($response_code === 200) {
        wp_send_json_success(array('message' => 'Film published successfully!'));
    } else {
        $error = isset($response_body['detail']) ? $response_body['detail'] : 'Failed to publish';
        wp_send_json_error(array('message' => $error));
    }
}
add_action('wp_ajax_frontdoor_admin_publish', 'frontdoor_admin_publish');

/**
 * Handle reject action
 */
function frontdoor_admin_reject() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'frontdoor_admin_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    $submission_id = sanitize_text_field($_POST['submission_id']);
    $reason = sanitize_textarea_field($_POST['reason']);
    
    $response = wp_remote_post(FRONTDOOR_API_URL . '/api/submissions/' . $submission_id . '/reject?reason=' . urlencode($reason), array(
        'timeout' => 30,
        'headers' => array('Content-Type' => 'application/json'),
        'body' => '{}'
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Connection error'));
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    
    if ($response_code === 200) {
        wp_send_json_success(array('message' => 'Submission rejected'));
    } else {
        wp_send_json_error(array('message' => 'Failed to reject submission'));
    }
}
add_action('wp_ajax_frontdoor_admin_reject', 'frontdoor_admin_reject');

/**
 * Get submission details
 */
function frontdoor_admin_get_details() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }
    
    $submission_id = sanitize_text_field($_GET['submission_id']);
    
    $response = wp_remote_get(FRONTDOOR_API_URL . '/api/submissions/' . $submission_id, array('timeout' => 30));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Connection error'));
        return;
    }
    
    $submission = json_decode(wp_remote_retrieve_body($response), true);
    
    // Get QA report
    $qa_response = wp_remote_get(FRONTDOOR_API_URL . '/api/qa-reports/' . $submission_id, array('timeout' => 30));
    $qa_report = null;
    if (!is_wp_error($qa_response) && wp_remote_retrieve_response_code($qa_response) === 200) {
        $qa_report = json_decode(wp_remote_retrieve_body($qa_response), true);
    }
    
    wp_send_json_success(array('submission' => $submission, 'qa_report' => $qa_report));
}
add_action('wp_ajax_frontdoor_admin_get_details', 'frontdoor_admin_get_details');

/**
 * Create plugin assets directory on activation
 */
function frontdoor_activate() {
    $css_dir = plugin_dir_path(__FILE__) . 'assets/css';
    $js_dir = plugin_dir_path(__FILE__) . 'assets/js';
    
    if (!file_exists($css_dir)) {
        wp_mkdir_p($css_dir);
    }
    if (!file_exists($js_dir)) {
        wp_mkdir_p($js_dir);
    }
}
register_activation_hook(__FILE__, 'frontdoor_activate');
