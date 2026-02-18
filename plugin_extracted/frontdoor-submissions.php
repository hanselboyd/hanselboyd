<?php
/**
 * Plugin Name: Front Door Film Submissions
 * Plugin URI: https://frontdoormedia.org
 * Description: Film submission form for Front Door Media. Use shortcode [frontdoor_submission_form] to embed the form.
 * Version: 2.2.0
 * Author: Front Door Media
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) exit;

// Constants
define('FRONTDOOR_API_URL', 'https://frontdoor-api-npz4.onrender.com');
define('FRONTDOOR_VERSION', '2.2.0');
define('FRONTDOOR_FROM_EMAIL', 'submission@frontdoormedia.org');
define('FRONTDOOR_FROM_NAME', 'Front Door Media');

// Default shelves
function frontdoor_get_shelves() {
    $default = array('Now Programming', 'Premieres', 'Concept Cinema', 'AI Nonfiction', 'Spotlight', 'Archive');
    $saved = get_option('frontdoor_shelves');
    return !empty($saved) ? array_filter(array_map('trim', explode("\n", $saved))) : $default;
}

function frontdoor_get_sendgrid_key() {
    return defined('FRONTDOOR_SENDGRID_API_KEY') ? FRONTDOOR_SENDGRID_API_KEY : get_option('frontdoor_sendgrid_api_key', '');
}

// SendGrid Email
function frontdoor_send_email($to_email, $to_name, $subject, $html_content) {
    $api_key = frontdoor_get_sendgrid_key();
    if (empty($api_key)) return false;
    
    $response = wp_remote_post('https://api.sendgrid.com/v3/mail/send', array(
        'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
        'body' => json_encode(array(
            'personalizations' => array(array('to' => array(array('email' => $to_email, 'name' => $to_name)), 'subject' => $subject)),
            'from' => array('email' => FRONTDOOR_FROM_EMAIL, 'name' => FRONTDOOR_FROM_NAME),
            'content' => array(array('type' => 'text/html', 'value' => $html_content))
        )),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) return false;
    $code = wp_remote_retrieve_response_code($response);
    return ($code >= 200 && $code < 300);
}

function frontdoor_send_confirmation_email($submission_data) {
    $email = $submission_data['filmmaker_email'];
    $name = $submission_data['filmmaker_name'];
    $title = $submission_data['title'];
    $portal_url = home_url('/filmmaker-portal/?email=' . urlencode($email));
    
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head>
    <body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
    <table width="100%" style="background:#f4f4f5;padding:40px 20px;"><tr><td align="center">
    <table width="600" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
    <tr><td style="background:linear-gradient(135deg,#1e1b4b,#4c1d95);padding:40px 30px;text-align:center;">
    <h1 style="color:#fff;margin:0;font-size:28px;">Front Door Media</h1>
    <p style="color:#c4b5fd;margin:10px 0 0;font-size:16px;">Film Submission Confirmed</p></td></tr>
    <tr><td style="padding:40px 30px;">
    <div style="text-align:center;margin-bottom:20px;"><div style="width:70px;height:70px;background:#10b981;border-radius:50%;display:inline-block;line-height:70px;"><span style="color:#fff;font-size:36px;">&#10003;</span></div></div>
    <h2 style="color:#1e293b;text-align:center;margin:0 0 20px;">Thank you, '.esc_html($name).'!</h2>
    <p style="color:#64748b;text-align:center;line-height:1.6;">We\'ve received your film submission. Our QA system is now reviewing it.</p>
    <table width="100%" style="background:#f8fafc;border-radius:8px;margin:25px 0;"><tr><td style="padding:25px;">
    <h3 style="color:#1e293b;margin:0 0 15px;">'.esc_html($title).'</h3>
    <p style="color:#64748b;margin:5px 0;"><strong>Genre:</strong> '.esc_html($submission_data['genre'] ?? 'Not specified').'</p>
    <p style="color:#64748b;margin:5px 0;"><strong>Status:</strong> <span style="background:#fef3c7;color:#92400e;padding:4px 12px;border-radius:20px;font-size:12px;">QA In Progress</span></p>
    </td></tr></table>
    <table width="100%"><tr><td align="center"><a href="'.esc_url($portal_url).'" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;text-decoration:none;padding:14px 30px;border-radius:50px;font-size:16px;font-weight:600;">Track Your Submission</a></td></tr></table>
    </td></tr>
    <tr><td style="background:#f8fafc;padding:25px 30px;border-top:1px solid #e2e8f0;text-align:center;">
    <p style="color:#94a3b8;font-size:13px;margin:0;">Questions? Visit <a href="https://frontdoormedia.org" style="color:#8b5cf6;">frontdoormedia.org</a></p></td></tr>
    </table></td></tr></table></body></html>';
    
    return frontdoor_send_email($email, $name, "Submission Received: {$title}", $html);
}

// Enqueue Assets
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

// Submission Form Shortcode
function frontdoor_submission_form_shortcode($atts) {
    $atts = shortcode_atts(array('title' => 'Submit Your Film', 'show_guidelines' => 'true'), $atts);
    
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
        
        <div id="frontdoor-success" class="frontdoor-message frontdoor-success" style="display: none;">
            <div class="frontdoor-message-icon">&#10003;</div>
            <h3>Submission Received!</h3>
            <p>Your film has been submitted successfully. You'll receive an email at <strong id="frontdoor-submitted-email"></strong> with next steps.</p>
            <a href="/filmmaker-portal" class="frontdoor-btn-secondary">View in Filmmaker Portal &rarr;</a>
        </div>
        
        <div id="frontdoor-error" class="frontdoor-message frontdoor-error" style="display: none;">
            <div class="frontdoor-message-icon">!</div>
            <h3>Submission Error</h3>
            <p id="frontdoor-error-text">There was an error processing your submission.</p>
        </div>
        
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
                    <textarea id="fd-description" name="description" required rows="4" placeholder="Tell us about your film's story..."></textarea>
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
                    <label><input type="checkbox" id="fd-first-film" name="is_first_film" value="1"><span>This is my first film</span></label>
                </div>
            </div>
            
            <div class="frontdoor-form-section">
                <h3>Media Files</h3>
                <div class="frontdoor-field">
                    <label for="fd-video-url">Video URL <span class="required">*</span></label>
                    <input type="url" id="fd-video-url" name="video_url" required placeholder="https://your-hosting.com/your-film.mp4">
                    <p class="frontdoor-help">Direct link to your video file. Supported: Vimeo, YouTube, Dropbox, Google Drive, or direct URL.</p>
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
                    <label><input type="checkbox" id="fd-terms" name="terms" required><span>I confirm that I have the rights to submit this film and agree to the <a href="/terms" target="_blank">Terms of Service</a> and <a href="/submission-guidelines" target="_blank">Submission Guidelines</a>. <span class="required">*</span></span></label>
                </div>
            </div>
            
            <div class="frontdoor-form-actions">
                <button type="submit" id="frontdoor-submit-btn" class="frontdoor-submit-btn">
                    <span class="btn-text">Submit Film for Review</span>
                    <span class="btn-loading" style="display: none;"><span class="spinner"></span> Submitting...</span>
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

// Handle Submission
function frontdoor_handle_submission() {
    if (!wp_verify_nonce($_POST['nonce'], 'frontdoor_submit_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }
    
    $data = array(
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
    
    if (!empty($_POST['runtime_minutes'])) $data['runtime_minutes'] = intval($_POST['runtime_minutes']);
    if (!empty($_POST['festival_awards'])) $data['festival_awards'] = sanitize_textarea_field($_POST['festival_awards']);
    
    foreach (array('title', 'short_description', 'description', 'poster_url', 'video_url', 'filmmaker_name', 'filmmaker_email', 'genre') as $field) {
        if (empty($data[$field])) {
            wp_send_json_error(array('message' => 'Please fill in all required fields.'));
            return;
        }
    }
    
    if (!is_email($data['filmmaker_email'])) {
        wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        return;
    }
    
    $response = wp_remote_post(FRONTDOOR_API_URL . '/api/submissions', array(
        'timeout' => 30,
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode($data)
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Connection error. Please try again.'));
        return;
    }
    
    $code = wp_remote_retrieve_response_code($response);
    if ($code === 200 || $code === 201) {
        frontdoor_send_confirmation_email($data);
        wp_send_json_success(array('message' => 'Success!', 'email' => $data['filmmaker_email']));
    } else {
        wp_send_json_error(array('message' => 'Submission failed. Please try again.'));
    }
}
add_action('wp_ajax_frontdoor_submit', 'frontdoor_handle_submission');
add_action('wp_ajax_nopriv_frontdoor_submit', 'frontdoor_handle_submission');

// Portal Shortcode
function frontdoor_portal_shortcode($atts) {
    $prefill = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
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
                    <input type="email" id="fd-portal-email" name="email" required placeholder="your@email.com" value="<?php echo esc_attr($prefill); ?>">
                    <button type="submit" class="frontdoor-portal-btn"><span class="btn-text">Look Up</span><span class="btn-loading" style="display:none;"><span class="spinner"></span></span></button>
                </form>
            </div>
            <div id="frontdoor-portal-results" class="frontdoor-portal-results" style="display:none;"></div>
            <div class="frontdoor-portal-info">
                <div class="frontdoor-portal-info-icon">&#127916;</div>
                <h3>Check Your Submission Status</h3>
                <p>Enter the email you used when submitting to see status, QA feedback, and publication details.</p>
            </div>
        </div>
    </div>
    <?php if ($prefill): ?><script>jQuery(function($){setTimeout(function(){$('#frontdoor-portal-form').trigger('submit');},500);});</script><?php endif; ?>
    <?php
    return ob_get_clean();
}
add_shortcode('frontdoor_portal', 'frontdoor_portal_shortcode');

// Portal Lookup
function frontdoor_portal_lookup() {
    if (!wp_verify_nonce($_POST['nonce'], 'frontdoor_submit_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }
    $email = sanitize_email($_POST['email']);
    if (!is_email($email)) {
        wp_send_json_error(array('message' => 'Please enter a valid email.'));
        return;
    }
    $response = wp_remote_get(FRONTDOOR_API_URL . '/api/portal/lookup?email=' . urlencode($email), array('timeout' => 30));
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Connection error.'));
        return;
    }
    wp_send_json_success(json_decode(wp_remote_retrieve_body($response), true));
}
add_action('wp_ajax_frontdoor_portal_lookup', 'frontdoor_portal_lookup');
add_action('wp_ajax_nopriv_frontdoor_portal_lookup', 'frontdoor_portal_lookup');

// Analytics Shortcode
function frontdoor_analytics_shortcode($atts) {
    $atts = shortcode_atts(array('title' => 'Platform Statistics'), $atts);
    $stats_response = wp_remote_get(FRONTDOOR_API_URL . '/api/stats', array('timeout' => 30));
    $stats = is_wp_error($stats_response) ? array() : json_decode(wp_remote_retrieve_body($stats_response), true);
    
    $total = $stats['total_submissions'] ?? 0;
    $published = $stats['by_status']['published'] ?? 0;
    $pending = $stats['by_status']['pending'] ?? 0;
    $qa_passed = $stats['by_status']['qa_passed'] ?? 0;
    $classified = $stats['by_status']['classified'] ?? 0;
    
    // Use configured shelves
    $configured_shelves = frontdoor_get_shelves();
    $api_shelf_data = $stats['by_shelf'] ?? array();
    
    ob_start();
    ?>
    <div class="frontdoor-analytics-wrapper">
        <div class="frontdoor-form-header"><h2><?php echo esc_html($atts['title']); ?></h2></div>
        <div class="frontdoor-analytics">
            <div class="frontdoor-analytics-grid">
                <div class="frontdoor-analytics-card"><div class="analytics-icon">&#127916;</div><div class="analytics-value"><?php echo $total; ?></div><div class="analytics-label">Total Submissions</div></div>
                <div class="frontdoor-analytics-card success"><div class="analytics-icon">&#10003;</div><div class="analytics-value"><?php echo $published; ?></div><div class="analytics-label">Published Films</div></div>
                <div class="frontdoor-analytics-card info"><div class="analytics-icon">&#128200;</div><div class="analytics-value"><?php echo $total > 0 ? round(($published/$total)*100) : 0; ?>%</div><div class="analytics-label">Publish Rate</div></div>
                <div class="frontdoor-analytics-card warning"><div class="analytics-icon">&#128269;</div><div class="analytics-value"><?php echo $qa_passed + $classified; ?></div><div class="analytics-label">Ready for Review</div></div>
            </div>
            <div class="frontdoor-analytics-section">
                <h3>Published by Shelf</h3>
                <div class="frontdoor-shelf-stats">
                    <?php 
                    // Build shelf counts using configured shelves
                    $shelf_counts = array();
                    foreach ($configured_shelves as $shelf) {
                        $shelf_counts[$shelf] = isset($api_shelf_data[$shelf]) ? $api_shelf_data[$shelf] : 0;
                    }
                    $max = max(array_values($shelf_counts) ?: array(1));
                    if ($max == 0) $max = 1;
                    
                    foreach ($shelf_counts as $shelf => $count): 
                        $pct = ($count / $max) * 100;
                    ?>
                    <div class="shelf-stat-item">
                        <div class="shelf-name"><?php echo esc_html($shelf); ?></div>
                        <div class="shelf-bar-container"><div class="shelf-bar" style="width:<?php echo $pct; ?>%;"></div><span class="shelf-count"><?php echo $count; ?></span></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('frontdoor_analytics', 'frontdoor_analytics_shortcode');

// ==================== ADMIN ====================

function frontdoor_admin_menu() {
    add_menu_page('Front Door Submissions', 'Film Submissions', 'manage_options', 'frontdoor-submissions', 'frontdoor_admin_page', 'dashicons-video-alt3', 30);
    add_submenu_page('frontdoor-submissions', 'Settings', 'Settings', 'manage_options', 'frontdoor-settings', 'frontdoor_settings_page');
    add_submenu_page('frontdoor-submissions', 'Analytics', 'Analytics', 'manage_options', 'frontdoor-analytics', 'frontdoor_analytics_page');
}
add_action('admin_menu', 'frontdoor_admin_menu');

function frontdoor_admin_assets($hook) {
    if (strpos($hook, 'frontdoor') === false) return;
    wp_enqueue_style('frontdoor-admin', plugin_dir_url(__FILE__) . 'assets/css/frontdoor-admin.css', array(), FRONTDOOR_VERSION);
    wp_enqueue_script('frontdoor-admin', plugin_dir_url(__FILE__) . 'assets/js/frontdoor-admin.js', array('jquery'), FRONTDOOR_VERSION, true);
    wp_localize_script('frontdoor-admin', 'frontdoorAdmin', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('frontdoor_admin_nonce'),
        'apiUrl' => FRONTDOOR_API_URL
    ));
}
add_action('admin_enqueue_scripts', 'frontdoor_admin_assets');

function frontdoor_settings_page() {
    if (isset($_POST['frontdoor_save_settings']) && wp_verify_nonce($_POST['frontdoor_settings_nonce'], 'frontdoor_save_settings')) {
        update_option('frontdoor_sendgrid_api_key', sanitize_text_field($_POST['sendgrid_api_key']));
        update_option('frontdoor_shelves', sanitize_textarea_field($_POST['shelves']));
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    
    if (isset($_POST['frontdoor_test_email']) && wp_verify_nonce($_POST['frontdoor_settings_nonce'], 'frontdoor_save_settings')) {
        $test_email = sanitize_email($_POST['test_email_address']);
        if (is_email($test_email)) {
            $result = frontdoor_send_confirmation_email(array('title' => 'Test Film', 'filmmaker_name' => 'Test User', 'filmmaker_email' => $test_email, 'genre' => 'Test'));
            echo $result ? '<div class="notice notice-success"><p>Test email sent!</p></div>' : '<div class="notice notice-error"><p>Failed to send. Check your API key.</p></div>';
        }
    }
    
    $sendgrid_key = frontdoor_get_sendgrid_key();
    $shelves = get_option('frontdoor_shelves', "Now Programming\nPremieres\nConcept Cinema\nAI Nonfiction\nSpotlight\nArchive");
    ?>
    <div class="wrap">
        <h1>Front Door Settings</h1>
        <form method="post">
            <?php wp_nonce_field('frontdoor_save_settings', 'frontdoor_settings_nonce'); ?>
            
            <h2>SendGrid Email</h2>
            <table class="form-table">
                <tr>
                    <th><label for="sendgrid_api_key">API Key</label></th>
                    <td><input type="password" name="sendgrid_api_key" id="sendgrid_api_key" class="regular-text" value="<?php echo esc_attr($sendgrid_key); ?>" placeholder="SG.xxxxx..." /><p class="description">Get from <a href="https://app.sendgrid.com/settings/api_keys" target="_blank">SendGrid Dashboard</a></p></td>
                </tr>
                <tr>
                    <th>Sender Email</th>
                    <td><code><?php echo esc_html(FRONTDOOR_FROM_EMAIL); ?></code><p class="description">Must be verified in SendGrid</p></td>
                </tr>
            </table>
            
            <h2>Shelves</h2>
            <table class="form-table">
                <tr>
                    <th><label for="shelves">Available Shelves</label></th>
                    <td><textarea name="shelves" id="shelves" rows="8" class="large-text code"><?php echo esc_textarea($shelves); ?></textarea><p class="description">One shelf per line. These appear in the publish dropdown.</p></td>
                </tr>
            </table>
            
            <p class="submit"><input type="submit" name="frontdoor_save_settings" class="button-primary" value="Save Settings" /></p>
        </form>
        
        <hr>
        <h2>Test Email</h2>
        <form method="post">
            <?php wp_nonce_field('frontdoor_save_settings', 'frontdoor_settings_nonce'); ?>
            <input type="email" name="test_email_address" class="regular-text" placeholder="your@email.com" />
            <input type="submit" name="frontdoor_test_email" class="button" value="Send Test" />
        </form>
        
        <hr>
        <h2>wp-config.php</h2>
        <pre style="background:#f1f1f1;padding:15px;border-radius:4px;">define('FRONTDOOR_SENDGRID_API_KEY', 'SG.your_key');</pre>
    </div>
    <?php
}

function frontdoor_admin_page() {
    $response = wp_remote_get(FRONTDOOR_API_URL . '/api/submissions?limit=100', array('timeout' => 30));
    $submissions = is_wp_error($response) ? array() : json_decode(wp_remote_retrieve_body($response), true);
    $stats_response = wp_remote_get(FRONTDOOR_API_URL . '/api/stats', array('timeout' => 30));
    $stats = is_wp_error($stats_response) ? array() : json_decode(wp_remote_retrieve_body($stats_response), true);
    $shelves = frontdoor_get_shelves();
    ?>
    <div class="wrap frontdoor-admin">
        <h1>Film Submissions</h1>
        
        <div class="frontdoor-stats-grid">
            <div class="frontdoor-stat-card"><span class="stat-number"><?php echo $stats['total_submissions'] ?? 0; ?></span><span class="stat-label">Total</span></div>
            <div class="frontdoor-stat-card pending"><span class="stat-number"><?php echo $stats['by_status']['pending'] ?? 0; ?></span><span class="stat-label">Pending</span></div>
            <div class="frontdoor-stat-card processing"><span class="stat-number"><?php echo ($stats['by_status']['qa_passed'] ?? 0) + ($stats['by_status']['classified'] ?? 0); ?></span><span class="stat-label">Ready</span></div>
            <div class="frontdoor-stat-card published"><span class="stat-number"><?php echo $stats['by_status']['published'] ?? 0; ?></span><span class="stat-label">Published</span></div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Title</th><th>Filmmaker</th><th>Status</th><th>Shelf</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($submissions)): ?>
                <tr><td colspan="6" style="text-align:center;padding:40px;">No submissions yet.</td></tr>
            <?php else: foreach ($submissions as $sub): ?>
                <tr data-id="<?php echo esc_attr($sub['id']); ?>">
                    <td><strong><?php echo esc_html($sub['title']); ?></strong><div class="row-actions"><a href="#" class="frontdoor-view-details" data-id="<?php echo esc_attr($sub['id']); ?>">View</a></div></td>
                    <td><?php echo esc_html($sub['filmmaker_name']); ?><br><small><?php echo esc_html($sub['filmmaker_email']); ?></small></td>
                    <td><span class="frontdoor-status frontdoor-status-<?php echo esc_attr($sub['status']); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $sub['status']))); ?></span></td>
                    <td><?php echo esc_html($sub['recommended_shelf'] ?? '-'); ?></td>
                    <td><?php echo isset($sub['created_at']) ? date('M j, Y', strtotime($sub['created_at'])) : '-'; ?></td>
                    <td>
                        <?php if ($sub['status'] === 'published'): ?>
                            <span style="color:#46b450;">&#10003; Published</span>
                        <?php elseif ($sub['status'] === 'rejected'): ?>
                            <span style="color:#999;">Rejected</span>
                        <?php else: ?>
                            <button class="button button-primary frontdoor-publish-btn" data-id="<?php echo esc_attr($sub['id']); ?>" data-shelf="<?php echo esc_attr($sub['recommended_shelf'] ?? ''); ?>">Approve & Publish</button>
                            <button class="button frontdoor-reject-btn" data-id="<?php echo esc_attr($sub['id']); ?>">Reject</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        
        <!-- Modals -->
        <div id="frontdoor-modal" class="frontdoor-modal" style="display:none;"><div class="frontdoor-modal-content"><span class="frontdoor-modal-close">&times;</span><div id="frontdoor-modal-body">Loading...</div></div></div>
        
        <div id="frontdoor-publish-modal" class="frontdoor-modal" style="display:none;">
            <div class="frontdoor-modal-content" style="max-width:500px;">
                <span class="frontdoor-modal-close">&times;</span>
                <h2>Publish to Shelf</h2>
                <select id="frontdoor-shelf-select" class="widefat">
                    <?php foreach ($shelves as $shelf): ?>
                    <option value="<?php echo esc_attr($shelf); ?>"><?php echo esc_html($shelf); ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="margin-top:20px;text-align:right;">
                    <button class="button" id="frontdoor-cancel-publish">Cancel</button>
                    <button class="button button-primary" id="frontdoor-confirm-publish">Publish</button>
                </div>
                <input type="hidden" id="frontdoor-publish-id">
            </div>
        </div>
        
        <div id="frontdoor-reject-modal" class="frontdoor-modal" style="display:none;">
            <div class="frontdoor-modal-content" style="max-width:500px;">
                <span class="frontdoor-modal-close">&times;</span>
                <h2>Reject Submission</h2>
                <textarea id="frontdoor-reject-reason" class="widefat" rows="4" placeholder="Reason for rejection..."></textarea>
                <div style="margin-top:20px;text-align:right;">
                    <button class="button" id="frontdoor-cancel-reject">Cancel</button>
                    <button class="button" id="frontdoor-confirm-reject" style="background:#dc3232;color:#fff;border-color:#dc3232;">Reject</button>
                </div>
                <input type="hidden" id="frontdoor-reject-id">
            </div>
        </div>
    </div>
    <?php
}

function frontdoor_analytics_page() {
    $stats_response = wp_remote_get(FRONTDOOR_API_URL . '/api/stats', array('timeout' => 30));
    $stats = is_wp_error($stats_response) ? array() : json_decode(wp_remote_retrieve_body($stats_response), true);
    ?>
    <div class="wrap frontdoor-admin">
        <h1>Analytics</h1>
        <?php echo frontdoor_analytics_shortcode(array('title' => '')); ?>
    </div>
    <?php
}

// Admin AJAX handlers
function frontdoor_admin_publish() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'frontdoor_admin_nonce')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }
    $id = sanitize_text_field($_POST['submission_id']);
    $shelf = sanitize_text_field($_POST['shelf']);
    $response = wp_remote_post(FRONTDOOR_API_URL . '/api/submissions/' . $id . '/publish?shelf=' . urlencode($shelf), array('timeout' => 30, 'headers' => array('Content-Type' => 'application/json'), 'body' => '{}'));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        wp_send_json_error(array('message' => 'Failed to publish'));
        return;
    }
    wp_send_json_success(array('message' => 'Published!'));
}
add_action('wp_ajax_frontdoor_admin_publish', 'frontdoor_admin_publish');

function frontdoor_admin_reject() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'frontdoor_admin_nonce')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }
    $id = sanitize_text_field($_POST['submission_id']);
    $reason = sanitize_textarea_field($_POST['reason']);
    $response = wp_remote_post(FRONTDOOR_API_URL . '/api/submissions/' . $id . '/reject?reason=' . urlencode($reason), array('timeout' => 30, 'headers' => array('Content-Type' => 'application/json'), 'body' => '{}'));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        wp_send_json_error(array('message' => 'Failed to reject'));
        return;
    }
    wp_send_json_success(array('message' => 'Rejected'));
}
add_action('wp_ajax_frontdoor_admin_reject', 'frontdoor_admin_reject');

function frontdoor_admin_get_details() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }
    $id = sanitize_text_field($_GET['submission_id']);
    $response = wp_remote_get(FRONTDOOR_API_URL . '/api/submissions/' . $id, array('timeout' => 30));
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Connection error'));
        return;
    }
    $submission = json_decode(wp_remote_retrieve_body($response), true);
    $qa_response = wp_remote_get(FRONTDOOR_API_URL . '/api/qa-reports/' . $id, array('timeout' => 30));
    $qa = (!is_wp_error($qa_response) && wp_remote_retrieve_response_code($qa_response) === 200) ? json_decode(wp_remote_retrieve_body($qa_response), true) : null;
    wp_send_json_success(array('submission' => $submission, 'qa_report' => $qa));
}
add_action('wp_ajax_frontdoor_admin_get_details', 'frontdoor_admin_get_details');

// Activation
function frontdoor_activate() {
    $dir = plugin_dir_path(__FILE__) . 'assets/';
    if (!file_exists($dir . 'css')) wp_mkdir_p($dir . 'css');
    if (!file_exists($dir . 'js')) wp_mkdir_p($dir . 'js');
}
register_activation_hook(__FILE__, 'frontdoor_activate');
