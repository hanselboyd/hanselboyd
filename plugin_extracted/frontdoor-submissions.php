<?php
/**
 * Plugin Name: Front Door Film Submissions
 * Plugin URI: https://frontdoormedia.org
 * Description: Film submission form for Front Door Media. Use shortcode [frontdoor_submission_form] to embed the form.
 * Version: 2.1.0
 * Author: Front Door Media
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FRONTDOOR_API_URL', 'https://frontdoor-api-npz4.onrender.com');
define('FRONTDOOR_VERSION', '2.1.0');
define('FRONTDOOR_FROM_EMAIL', 'submission@frontdoormedia.org');
define('FRONTDOOR_FROM_NAME', 'Front Door Media');

/**
 * Get SendGrid API key
 */
function frontdoor_get_sendgrid_key() {
    return defined('FRONTDOOR_SENDGRID_API_KEY') ? FRONTDOOR_SENDGRID_API_KEY : get_option('frontdoor_sendgrid_api_key', '');
}

/**
 * Send email via SendGrid
 */
function frontdoor_send_email($to_email, $to_name, $subject, $html_content) {
    $api_key = frontdoor_get_sendgrid_key();
    
    if (empty($api_key)) {
        error_log('Front Door: SendGrid API key not configured');
        return false;
    }
    
    $data = array(
        'personalizations' => array(
            array(
                'to' => array(array('email' => $to_email, 'name' => $to_name)),
                'subject' => $subject
            )
        ),
        'from' => array('email' => FRONTDOOR_FROM_EMAIL, 'name' => FRONTDOOR_FROM_NAME),
        'content' => array(array('type' => 'text/html', 'value' => $html_content))
    );
    
    $response = wp_remote_post('https://api.sendgrid.com/v3/mail/send', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($data),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        error_log('Front Door SendGrid Error: ' . $response->get_error_message());
        return false;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    return ($status_code >= 200 && $status_code < 300);
}

/**
 * Send submission confirmation email
 */
function frontdoor_send_confirmation_email($submission_data) {
    $filmmaker_email = $submission_data['filmmaker_email'];
    $filmmaker_name = $submission_data['filmmaker_name'];
    $film_title = $submission_data['title'];
    
    $subject = "Submission Received: {$film_title}";
    $portal_url = home_url('/filmmaker-portal/?email=' . urlencode($filmmaker_email));
    
    $html_content = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
    <body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f5; padding: 40px 20px;">
            <tr><td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <tr><td style="background: linear-gradient(135deg, #1e1b4b 0%, #4c1d95 100%); padding: 40px 30px; text-align: center;">
                        <h1 style="color: #ffffff; margin: 0; font-size: 28px;">Front Door Media</h1>
                        <p style="color: #c4b5fd; margin: 10px 0 0 0; font-size: 16px;">Film Submission Confirmed</p>
                    </td></tr>
                    <tr><td style="padding: 40px 30px 20px; text-align: center;">
                        <div style="width: 70px; height: 70px; background-color: #10b981; border-radius: 50%; display: inline-block; line-height: 70px;">
                            <span style="color: #ffffff; font-size: 36px;">&#10003;</span>
                        </div>
                    </td></tr>
                    <tr><td style="padding: 0 30px 30px;">
                        <h2 style="color: #1e293b; margin: 0 0 20px; font-size: 22px; text-align: center;">Thank you, ' . esc_html($filmmaker_name) . '!</h2>
                        <p style="color: #64748b; font-size: 16px; line-height: 1.6; margin: 0 0 25px; text-align: center;">We\'ve received your film submission. Our automated QA system is now reviewing it.</p>
                        <table width="100%" style="background-color: #f8fafc; border-radius: 8px; margin-bottom: 25px;">
                            <tr><td style="padding: 25px;">
                                <h3 style="color: #1e293b; margin: 0 0 15px; font-size: 18px;">' . esc_html($film_title) . '</h3>
                                <p style="color: #64748b; margin: 5px 0;"><strong>Genre:</strong> ' . esc_html($submission_data['genre'] ?? 'Not specified') . '</p>
                                <p style="color: #64748b; margin: 5px 0;"><strong>Status:</strong> <span style="background-color: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 20px; font-size: 12px;">QA In Progress</span></p>
                            </td></tr>
                        </table>
                        <h3 style="color: #1e293b; margin: 0 0 15px; font-size: 16px;">What happens next?</h3>
                        <ol style="color: #64748b; font-size: 14px; line-height: 1.8; margin: 0 0 25px; padding-left: 20px;">
                            <li><strong>QA Review</strong> - Video quality, audio, and technical specs (24-48 hours)</li>
                            <li><strong>Curatorial Review</strong> - Our team reviews your film</li>
                            <li><strong>Publication</strong> - Approved films go to our Roku channel</li>
                        </ol>
                        <table width="100%"><tr><td align="center" style="padding: 10px 0 20px;">
                            <a href="' . esc_url($portal_url) . '" style="display: inline-block; background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%); color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 50px; font-size: 16px; font-weight: 600;">Track Your Submission</a>
                        </td></tr></table>
                    </td></tr>
                    <tr><td style="background-color: #f8fafc; padding: 25px 30px; border-top: 1px solid #e2e8f0;">
                        <p style="color: #94a3b8; font-size: 13px; margin: 0; text-align: center;">Questions? Reply to this email or visit <a href="https://frontdoormedia.org" style="color: #8b5cf6;">frontdoormedia.org</a></p>
                    </td></tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>';
    
    return frontdoor_send_email($filmmaker_email, $filmmaker_name, $subject, $html_content);
}

/**
 * Enqueue plugin styles and scripts
 */
function frontdoor_enqueue_assets() {
    wp_enqueue_style('frontdoor-submissions', plugin_dir_url(__FILE__) . 'assets/css/frontdoor-form.css', array(), FRONTDOOR_VERSION);
    wp_enqueue_script('frontdoor-submissions', plugin_dir_url(__FILE__) . 'assets/js/frontdoor-form.js', array('jquery'), FRONTDOOR_VERSION, true);
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
            <p class="frontdoor-subtitle">Join our curated Broadcast Network connecting filmmakers with buyers and audiences worldwide.</p>
            <?php endif; ?>
            <p class="frontdoor-portal-link">Already submitted?<a href="/filmmaker-portal">Track Your Submission &rarr;</a></p>
        </div>
        
        <!-- Success Message -->
        <div id="frontdoor-success" class="frontdoor-message frontdoor-success" style="display: none;">
            <div class="frontdoor-message-icon">&#10003;</div>
            <h3>Submission Received!</h3>
            <p>Your film has been submitted successfully. Our automated QA system is now reviewing your submission.</p>
            <p>You'll receive an email at <strong id="frontdoor-submitted-email"></strong> with your QA results and next steps.</p>
            <p class="frontdoor-tracking">Track your submission status anytime</p>
            <a href="/filmmaker-portal" class="frontdoor-btn-secondary">View in Filmmaker Portal &rarr;</a>
        </div>
        
        <!-- Error Message -->
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
                        <label for="fd-genre">Genre <span class="required">*</span></label>
                        <select id="fd-genre" name="genre" required>
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
                    <p class="frontdoor-help">Direct link to your video file (MP4 preferred). Supported: Vimeo, YouTube, Dropbox, Google Drive, or direct URL.</p>
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
 * Handle form submission
 */
function frontdoor_handle_submission() {
    if (!wp_verify_nonce($_POST['nonce'], 'frontdoor_submit_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed. Please refresh and try again.'));
        return;
    }
    
    $submission_data = array(
        'title' => sanitize_text_field($_POST['title']),
        'short_description' => sanitize_text_field($_POST['short_description']),
        'description' => sanitize_textarea_field($_POST['description']),
        'poster_url' => esc_url_raw($_POST['poster_url']),
        'video_url' => esc_url_raw($_POST['video_url']),
        'video_type' => 'mp4',
        'filmmaker_name' => sanitize_text_field($_POST['filmmaker_name']),
        'filmmaker_email' => sanitize_email($_POST['filmmaker_email']),
        'is_first_film' => isset($_POST['is_first_film']) && $_POST['is_first_film'] === '1',
        'genre' => sanitize_text_field($_POST['genre'])
    );
    
    if (!empty($_POST['runtime_minutes'])) {
        $submission_data['runtime_minutes'] = intval($_POST['runtime_minutes']);
    }
    if (!empty($_POST['festival_awards'])) {
        $submission_data['festival_awards'] = sanitize_textarea_field($_POST['festival_awards']);
    }
    
    // Validate required fields
    $required = array('title', 'short_description', 'description', 'poster_url', 'video_url', 'filmmaker_name', 'filmmaker_email', 'genre');
    foreach ($required as $field) {
        if (empty($submission_data[$field])) {
            wp_send_json_error(array('message' => 'Please fill in all required fields.'));
            return;
        }
    }
    
    if (!is_email($submission_data['filmmaker_email'])) {
        wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        return;
    }
    
    // Send to Front Door API
    $response = wp_remote_post(FRONTDOOR_API_URL . '/api/submissions', array(
        'timeout' => 30,
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode($submission_data)
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Connection error. Please try again later.'));
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($response_code === 200 || $response_code === 201) {
        // Send confirmation email
        frontdoor_send_confirmation_email($submission_data);
        
        wp_send_json_success(array(
            'message' => 'Submission received successfully!',
            'submission_id' => isset($response_body['id']) ? $response_body['id'] : '',
            'email' => $submission_data['filmmaker_email']
        ));
    } else {
        $error_message = 'Submission failed. Please try again.';
        if (isset($response_body['detail'])) {
            $error_message = is_array($response_body['detail']) ? 'Validation error.' : $response_body['detail'];
        }
        wp_send_json_error(array('message' => $error_message));
    }
}
add_action('wp_ajax_frontdoor_submit', 'frontdoor_handle_submission');
add_action('wp_ajax_nopriv_frontdoor_submit', 'frontdoor_handle_submission');

/**
 * Filmmaker portal shortcode
 */
function frontdoor_portal_shortcode($atts) {
    $atts = shortcode_atts(array('title' => 'Check Submission Status'), $atts);
    $prefill_email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
    
    ob_start();
    ?>
    <div class="frontdoor-portal-wrapper frontdoor-portal-dark">
        <div class="frontdoor-portal-header">
            <h1>Front Door Media</h1>
            <h2>Filmmaker Portal</h2>
            <p>Track your submission status and view QA feedback</p>
        </div>
        
        <div class="frontdoor-portal-content">
            <div class="frontdoor-portal-form-section">
                <p class="frontdoor-portal-form-label">Enter your email address to find your submissions</p>
                <form id="frontdoor-portal-form" class="frontdoor-portal-form-inline">
                    <input type="email" id="fd-portal-email" name="email" required placeholder="your@email.com" value="<?php echo esc_attr($prefill_email); ?>">
                    <button type="submit" class="frontdoor-portal-btn">
                        <span class="btn-text">Look Up</span>
                        <span class="btn-loading" style="display: none;"><span class="spinner"></span></span>
                    </button>
                </form>
            </div>
            
            <div id="frontdoor-portal-results" class="frontdoor-portal-results" style="display: none;"></div>
            
            <div class="frontdoor-portal-info">
                <div class="frontdoor-portal-info-icon">&#127916;</div>
                <h3>Check Your Submission Status</h3>
                <p>Enter the email address you used when submitting your film to see its current status, QA feedback, and publication details.</p>
            </div>
            
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
    
    <?php if (!empty($prefill_email)): ?>
    <script>jQuery(document).ready(function($) { setTimeout(function() { $('#frontdoor-portal-form').trigger('submit'); }, 500); });</script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
add_shortcode('frontdoor_portal', 'frontdoor_portal_shortcode');

/**
 * Handle portal lookup
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
    
    $response = wp_remote_get(FRONTDOOR_API_URL . '/api/portal/lookup?email=' . urlencode($email), array('timeout' => 30));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Connection error. Please try again.'));
        return;
    }
    
    wp_send_json_success(json_decode(wp_remote_retrieve_body($response), true));
}
add_action('wp_ajax_frontdoor_portal_lookup', 'frontdoor_portal_lookup');
add_action('wp_ajax_nopriv_frontdoor_portal_lookup', 'frontdoor_portal_lookup');

// ==================== ADMIN ====================

function frontdoor_admin_menu() {
    add_menu_page('Front Door Submissions', 'Film Submissions', 'manage_options', 'frontdoor-submissions', 'frontdoor_admin_page', 'dashicons-video-alt3', 30);
    add_submenu_page('frontdoor-submissions', 'Settings', 'Settings', 'manage_options', 'frontdoor-settings', 'frontdoor_settings_page');
}
add_action('admin_menu', 'frontdoor_admin_menu');

function frontdoor_settings_page() {
    if (isset($_POST['frontdoor_save_settings']) && wp_verify_nonce($_POST['frontdoor_settings_nonce'], 'frontdoor_save_settings')) {
        update_option('frontdoor_sendgrid_api_key', sanitize_text_field($_POST['sendgrid_api_key']));
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    
    if (isset($_POST['frontdoor_test_email']) && wp_verify_nonce($_POST['frontdoor_settings_nonce'], 'frontdoor_save_settings')) {
        $test_email = sanitize_email($_POST['test_email_address']);
        if (is_email($test_email)) {
            $result = frontdoor_send_confirmation_email(array(
                'title' => 'Test Film',
                'filmmaker_name' => 'Test User',
                'filmmaker_email' => $test_email,
                'genre' => 'Test'
            ));
            echo $result 
                ? '<div class="notice notice-success"><p>Test email sent to ' . esc_html($test_email) . '!</p></div>'
                : '<div class="notice notice-error"><p>Failed to send test email. Check your SendGrid API key.</p></div>';
        }
    }
    
    $sendgrid_key = frontdoor_get_sendgrid_key();
    ?>
    <div class="wrap">
        <h1>Front Door Settings</h1>
        
        <form method="post">
            <?php wp_nonce_field('frontdoor_save_settings', 'frontdoor_settings_nonce'); ?>
            
            <h2>SendGrid Email Configuration</h2>
            <p>Enter your SendGrid API key for confirmation emails. Get one at <a href="https://app.sendgrid.com/settings/api_keys" target="_blank">SendGrid Dashboard</a>.</p>
            
            <table class="form-table">
                <tr>
                    <th><label for="sendgrid_api_key">SendGrid API Key</label></th>
                    <td>
                        <input type="password" name="sendgrid_api_key" id="sendgrid_api_key" class="regular-text" value="<?php echo esc_attr($sendgrid_key); ?>" placeholder="SG.xxxxx..." />
                        <p class="description">Starts with SG. Requires Mail Send permission.</p>
                    </td>
                </tr>
                <tr>
                    <th>Sender Email</th>
                    <td><code><?php echo esc_html(FRONTDOOR_FROM_EMAIL); ?></code><p class="description">Must be verified in SendGrid.</p></td>
                </tr>
            </table>
            
            <p class="submit"><input type="submit" name="frontdoor_save_settings" class="button-primary" value="Save Settings" /></p>
        </form>
        
        <hr>
        
        <h2>Test Email</h2>
        <form method="post">
            <?php wp_nonce_field('frontdoor_save_settings', 'frontdoor_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="test_email_address">Send Test To</label></th>
                    <td>
                        <input type="email" name="test_email_address" id="test_email_address" class="regular-text" placeholder="your@email.com" />
                        <input type="submit" name="frontdoor_test_email" class="button" value="Send Test Email" />
                    </td>
                </tr>
            </table>
        </form>
        
        <hr>
        <h2>wp-config.php (recommended)</h2>
        <pre style="background: #f1f1f1; padding: 15px; border-radius: 4px;">define('FRONTDOOR_SENDGRID_API_KEY', 'SG.your_key_here');</pre>
    </div>
    <?php
}

function frontdoor_admin_page() {
    $response = wp_remote_get(FRONTDOOR_API_URL . '/api/submissions?limit=100', array('timeout' => 30));
    $submissions = is_wp_error($response) ? array() : json_decode(wp_remote_retrieve_body($response), true);
    
    $stats_response = wp_remote_get(FRONTDOOR_API_URL . '/api/stats', array('timeout' => 30));
    $stats = is_wp_error($stats_response) ? array() : json_decode(wp_remote_retrieve_body($stats_response), true);
    ?>
    <div class="wrap">
        <h1>Film Submissions</h1>
        
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px;">
            <div style="background: #667eea; color: white; padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 2em; font-weight: bold;"><?php echo $stats['total_submissions'] ?? 0; ?></div>
                <div>Total</div>
            </div>
            <div style="background: #f59e0b; color: white; padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 2em; font-weight: bold;"><?php echo $stats['by_status']['pending'] ?? 0; ?></div>
                <div>Pending</div>
            </div>
            <div style="background: #3b82f6; color: white; padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 2em; font-weight: bold;"><?php echo ($stats['by_status']['qa_passed'] ?? 0) + ($stats['by_status']['classified'] ?? 0); ?></div>
                <div>Ready</div>
            </div>
            <div style="background: #10b981; color: white; padding: 20px; border-radius: 8px; text-align: center;">
                <div style="font-size: 2em; font-weight: bold;"><?php echo $stats['by_status']['published'] ?? 0; ?></div>
                <div>Published</div>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Filmmaker</th>
                    <th>Status</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissions)): ?>
                <tr><td colspan="4" style="text-align: center; padding: 40px;">No submissions yet.</td></tr>
                <?php else: ?>
                <?php foreach ($submissions as $sub): ?>
                <tr>
                    <td><strong><?php echo esc_html($sub['title']); ?></strong></td>
                    <td><?php echo esc_html($sub['filmmaker_name']); ?><br><small><?php echo esc_html($sub['filmmaker_email']); ?></small></td>
                    <td><span style="background: #e2e8f0; padding: 4px 10px; border-radius: 20px; font-size: 12px;"><?php echo esc_html(ucwords(str_replace('_', ' ', $sub['status']))); ?></span></td>
                    <td><?php echo isset($sub['created_at']) ? date('M j, Y', strtotime($sub['created_at'])) : 'N/A'; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function frontdoor_activate() {
    $css_dir = plugin_dir_path(__FILE__) . 'assets/css';
    $js_dir = plugin_dir_path(__FILE__) . 'assets/js';
    if (!file_exists($css_dir)) wp_mkdir_p($css_dir);
    if (!file_exists($js_dir)) wp_mkdir_p($js_dir);
}
register_activation_hook(__FILE__, 'frontdoor_activate');
