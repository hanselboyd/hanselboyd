<?php
/**
 * Plugin Name: Front Door Film Submissions
 * Plugin URI: https://frontdoormedia.org
 * Description: Film submission form for Front Door Media with Stripe payment. Use shortcode [frontdoor_submission_form] to embed the form.
 * Version: 2.0.0
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
define('FRONTDOOR_SUBMISSION_FEE', 25.00); // Submission fee in USD
define('FRONTDOOR_FROM_EMAIL', 'submissions@frontdoormedia.org'); // SendGrid verified sender
define('FRONTDOOR_FROM_NAME', 'Front Door Media');

/**
 * Get Stripe keys from wp-config.php or options
 */
function frontdoor_get_stripe_keys() {
    return array(
        'publishable' => defined('FRONTDOOR_STRIPE_PUBLISHABLE_KEY') ? FRONTDOOR_STRIPE_PUBLISHABLE_KEY : get_option('frontdoor_stripe_publishable_key', ''),
        'secret' => defined('FRONTDOOR_STRIPE_SECRET_KEY') ? FRONTDOOR_STRIPE_SECRET_KEY : get_option('frontdoor_stripe_secret_key', '')
    );
}

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
    
    $from_email = defined('FRONTDOOR_FROM_EMAIL') ? FRONTDOOR_FROM_EMAIL : 'submissions@frontdoormedia.org';
    $from_name = defined('FRONTDOOR_FROM_NAME') ? FRONTDOOR_FROM_NAME : 'Front Door Media';
    
    $data = array(
        'personalizations' => array(
            array(
                'to' => array(
                    array(
                        'email' => $to_email,
                        'name' => $to_name
                    )
                ),
                'subject' => $subject
            )
        ),
        'from' => array(
            'email' => $from_email,
            'name' => $from_name
        ),
        'content' => array(
            array(
                'type' => 'text/html',
                'value' => $html_content
            )
        )
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
    
    if ($status_code >= 200 && $status_code < 300) {
        return true;
    } else {
        error_log('Front Door SendGrid Error: HTTP ' . $status_code . ' - ' . wp_remote_retrieve_body($response));
        return false;
    }
}

/**
 * Send submission confirmation email
 */
function frontdoor_send_confirmation_email($submission_data, $submission_id = '') {
    $filmmaker_email = $submission_data['filmmaker_email'];
    $filmmaker_name = $submission_data['filmmaker_name'];
    $film_title = $submission_data['title'];
    $payment_amount = isset($submission_data['payment_amount']) ? $submission_data['payment_amount'] : FRONTDOOR_SUBMISSION_FEE;
    
    $subject = "Submission Received: {$film_title}";
    
    $portal_url = home_url('/filmmaker-portal/?email=' . urlencode($filmmaker_email));
    
    $html_content = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f5; padding: 40px 20px;">
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                        <!-- Header -->
                        <tr>
                            <td style="background: linear-gradient(135deg, #1e1b4b 0%, #4c1d95 100%); padding: 40px 30px; text-align: center;">
                                <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 700;">Front Door Media</h1>
                                <p style="color: #c4b5fd; margin: 10px 0 0 0; font-size: 16px;">Film Submission Confirmed</p>
                            </td>
                        </tr>
                        
                        <!-- Success Icon -->
                        <tr>
                            <td style="padding: 40px 30px 20px; text-align: center;">
                                <div style="width: 70px; height: 70px; background-color: #10b981; border-radius: 50%; display: inline-block; line-height: 70px;">
                                    <span style="color: #ffffff; font-size: 36px;">&#10003;</span>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Main Content -->
                        <tr>
                            <td style="padding: 0 30px 30px;">
                                <h2 style="color: #1e293b; margin: 0 0 20px; font-size: 22px; text-align: center;">Thank you, ' . esc_html($filmmaker_name) . '!</h2>
                                <p style="color: #64748b; font-size: 16px; line-height: 1.6; margin: 0 0 25px; text-align: center;">
                                    We\'ve received your film submission and payment. Our automated QA system is now reviewing your submission.
                                </p>
                                
                                <!-- Film Details Box -->
                                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; border-radius: 8px; margin-bottom: 25px;">
                                    <tr>
                                        <td style="padding: 25px;">
                                            <h3 style="color: #1e293b; margin: 0 0 15px; font-size: 18px;">' . esc_html($film_title) . '</h3>
                                            <table width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td style="color: #64748b; font-size: 14px; padding: 5px 0;">Genre:</td>
                                                    <td style="color: #1e293b; font-size: 14px; padding: 5px 0; text-align: right;">' . esc_html($submission_data['genre'] ?? 'Not specified') . '</td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #64748b; font-size: 14px; padding: 5px 0;">Submission Fee:</td>
                                                    <td style="color: #1e293b; font-size: 14px; padding: 5px 0; text-align: right;">$' . number_format($payment_amount, 2) . ' USD</td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #64748b; font-size: 14px; padding: 5px 0;">Status:</td>
                                                    <td style="padding: 5px 0; text-align: right;">
                                                        <span style="background-color: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">QA In Progress</span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- What\'s Next -->
                                <h3 style="color: #1e293b; margin: 0 0 15px; font-size: 16px;">What happens next?</h3>
                                <ol style="color: #64748b; font-size: 14px; line-height: 1.8; margin: 0 0 25px; padding-left: 20px;">
                                    <li><strong>QA Review</strong> - Our system checks video quality, audio, and technical specs (24-48 hours)</li>
                                    <li><strong>Curatorial Review</strong> - Our team reviews your film for our platform</li>
                                    <li><strong>Publication</strong> - Approved films are published to our Roku channel</li>
                                </ol>
                                
                                <!-- CTA Button -->
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td align="center" style="padding: 10px 0 20px;">
                                            <a href="' . esc_url($portal_url) . '" style="display: inline-block; background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%); color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 50px; font-size: 16px; font-weight: 600;">Track Your Submission</a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style="background-color: #f8fafc; padding: 25px 30px; border-top: 1px solid #e2e8f0;">
                                <p style="color: #94a3b8; font-size: 13px; margin: 0; text-align: center;">
                                    Questions? Reply to this email or visit <a href="https://frontdoormedia.org" style="color: #8b5cf6;">frontdoormedia.org</a>
                                </p>
                                <p style="color: #cbd5e1; font-size: 12px; margin: 15px 0 0; text-align: center;">
                                    &copy; ' . date('Y') . ' Front Door Media. All rights reserved.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
    
    return frontdoor_send_email($filmmaker_email, $filmmaker_name, $subject, $html_content);
}

/**
 * Enqueue plugin styles and scripts
 */
function frontdoor_enqueue_assets() {
    $stripe_keys = frontdoor_get_stripe_keys();
    
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
        'apiUrl' => FRONTDOOR_API_URL,
        'stripePublishable' => $stripe_keys['publishable'],
        'submissionFee' => FRONTDOOR_SUBMISSION_FEE
    ));
}
add_action('wp_enqueue_scripts', 'frontdoor_enqueue_assets');

/**
 * Shortcode for the submission form with Stripe payment
 */
function frontdoor_submission_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => 'Submit Your Film',
        'show_guidelines' => 'true'
    ), $atts);
    
    // Check for return from Stripe
    $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';
    $payment_cancelled = isset($_GET['payment_cancelled']) ? true : false;
    
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
        
        <!-- Progress Steps -->
        <div class="frontdoor-progress">
            <div class="frontdoor-step active" data-step="1">
                <span class="step-number">1</span>
                <span class="step-label">Film Details</span>
            </div>
            <div class="frontdoor-step" data-step="2">
                <span class="step-number">2</span>
                <span class="step-label">Payment</span>
            </div>
        </div>
        
        <!-- Success Message (hidden by default) -->
        <div id="frontdoor-success" class="frontdoor-message frontdoor-success" style="display: none;">
            <div class="frontdoor-message-icon">&#10003;</div>
            <h3>Submission Received!</h3>
            <p>Your film has been submitted successfully. Our automated QA system is now reviewing your submission.</p>
            <p>You'll receive an email at <strong id="frontdoor-submitted-email"></strong> with your QA results and next steps.</p>
            <p class="frontdoor-tracking">Track your submission status anytime</p>
            <a href="/filmmaker-portal" class="frontdoor-btn-secondary">View in Filmmaker Portal &rarr;</a>
        </div>
        
        <!-- Error Message (hidden by default) -->
        <div id="frontdoor-error" class="frontdoor-message frontdoor-error" style="display: none;">
            <div class="frontdoor-message-icon">!</div>
            <h3>Submission Error</h3>
            <p id="frontdoor-error-text">There was an error processing your submission. Please try again.</p>
        </div>
        
        <!-- Payment Cancelled Message -->
        <?php if ($payment_cancelled): ?>
        <div class="frontdoor-message frontdoor-warning">
            <div class="frontdoor-message-icon">!</div>
            <h3>Payment Cancelled</h3>
            <p>Your payment was cancelled. Please fill out the form again to submit your film.</p>
        </div>
        <?php endif; ?>
        
        <!-- Processing Payment Message (shown when returning from Stripe) -->
        <div id="frontdoor-processing" class="frontdoor-message frontdoor-processing" style="display: <?php echo $session_id ? 'block' : 'none'; ?>;">
            <div class="frontdoor-spinner"></div>
            <h3>Processing your submission...</h3>
            <p>We received your payment, please wait while we complete your submission.</p>
        </div>
        
        <!-- Submission Form -->
        <form id="frontdoor-submission-form" class="frontdoor-form" style="display: <?php echo ($session_id || $payment_cancelled) ? 'none' : 'block'; ?>;">
            <!-- Step 1: Film Details -->
            <div class="frontdoor-form-step" data-step="1">
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
                            <span>By continuing, you confirm that you have the rights to submit this film and agree to the <a href="/terms" target="_blank">Terms of Service</a> and <a href="/submission-guidelines" target="_blank">Submission Guidelines</a>.</span>
                        </label>
                    </div>
                </div>
                
                <div class="frontdoor-form-actions">
                    <button type="button" id="frontdoor-continue-btn" class="frontdoor-submit-btn">
                        <span class="btn-text">Continue to Payment</span>
                    </button>
                </div>
            </div>
            
            <!-- Step 2: Payment -->
            <div class="frontdoor-form-step" data-step="2" style="display: none;">
                <div class="frontdoor-form-section">
                    <button type="button" class="frontdoor-back-btn" id="frontdoor-back-to-details">&larr; Back to Details</button>
                    
                    <h3>Payment</h3>
                    
                    <div class="frontdoor-payment-summary">
                        <h4>Your Submission</h4>
                        <p class="frontdoor-film-title" id="payment-film-title">Your Film Title</p>
                        <div class="frontdoor-summary-details">
                            <p><strong>Genre:</strong> <span id="payment-genre"></span></p>
                            <p><strong>Filmmaker:</strong> <span id="payment-filmmaker"></span></p>
                            <p><strong>Email:</strong> <span id="payment-email"></span></p>
                        </div>
                        
                        <div class="frontdoor-fee-box">
                            <span class="fee-label">Submission Fee</span>
                            <span class="fee-amount">$<?php echo number_format(FRONTDOOR_SUBMISSION_FEE, 2); ?> USD</span>
                        </div>
                    </div>
                </div>
                
                <div class="frontdoor-form-actions">
                    <button type="submit" id="frontdoor-pay-btn" class="frontdoor-submit-btn frontdoor-pay-btn">
                        <span class="btn-text">Pay & Submit Film</span>
                        <span class="btn-loading" style="display: none;">
                            <span class="spinner"></span> Processing...
                        </span>
                    </button>
                    <p class="frontdoor-secure-note">Secure payment powered by Stripe</p>
                </div>
            </div>
        </form>
        
        <?php if ($atts['show_guidelines'] === 'true'): ?>
        <div class="frontdoor-guidelines" id="frontdoor-guidelines">
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
    
    <?php if ($session_id): ?>
    <script>
    // Auto-process the submission after returning from Stripe
    jQuery(document).ready(function($) {
        var sessionId = '<?php echo esc_js($session_id); ?>';
        if (sessionId) {
            frontdoorProcessPayment(sessionId);
        }
    });
    </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
add_shortcode('frontdoor_submission_form', 'frontdoor_submission_form_shortcode');

/**
 * Create Stripe Checkout Session
 */
function frontdoor_create_checkout_session() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'frontdoor_submit_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed. Please refresh and try again.'));
        return;
    }
    
    $stripe_keys = frontdoor_get_stripe_keys();
    if (empty($stripe_keys['secret'])) {
        wp_send_json_error(array('message' => 'Payment system not configured. Please contact support.'));
        return;
    }
    
    // Sanitize and validate form data
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
    
    // Add optional fields
    if (!empty($_POST['runtime_minutes'])) {
        $submission_data['runtime_minutes'] = intval($_POST['runtime_minutes']);
    }
    if (!empty($_POST['festival_awards'])) {
        $submission_data['festival_awards'] = sanitize_textarea_field($_POST['festival_awards']);
    }
    
    // Validate required fields
    $required_fields = array('title', 'short_description', 'description', 'poster_url', 'video_url', 'filmmaker_name', 'filmmaker_email', 'genre');
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
    
    // Store submission data in WordPress options (more reliable than transients)
    // Key it by a unique identifier we'll pass to Stripe
    $submission_key = 'fd_sub_' . md5($submission_data['filmmaker_email'] . $submission_data['title'] . time());
    update_option($submission_key, $submission_data, false);
    
    // Also store in a session-based transient as backup
    set_transient($submission_key, $submission_data, 2 * HOUR_IN_SECONDS);
    
    // Build success and cancel URLs
    $current_url = isset($_POST['return_url']) ? esc_url_raw($_POST['return_url']) : home_url('/front-door-submission-form/');
    $success_url = add_query_arg('session_id', '{CHECKOUT_SESSION_ID}', $current_url);
    $cancel_url = add_query_arg('payment_cancelled', '1', $current_url);
    
    // Create Stripe Checkout Session using cURL
    $stripe_data = array(
        'payment_method_types[]' => 'card',
        'line_items[0][price_data][currency]' => 'usd',
        'line_items[0][price_data][unit_amount]' => intval(FRONTDOOR_SUBMISSION_FEE * 100), // Convert to cents
        'line_items[0][price_data][product_data][name]' => 'Film Submission: ' . $submission_data['title'],
        'line_items[0][price_data][product_data][description]' => 'Front Door Media Film Submission Fee',
        'line_items[0][quantity]' => 1,
        'mode' => 'payment',
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,
        'customer_email' => $submission_data['filmmaker_email'],
        'metadata[submission_key]' => $submission_key,
        'metadata[film_title]' => $submission_data['title'],
        'metadata[filmmaker_email]' => $submission_data['filmmaker_email']
    );
    
    $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($stripe_keys['secret'] . ':'),
            'Content-Type' => 'application/x-www-form-urlencoded'
        ),
        'body' => http_build_query($stripe_data),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        delete_transient($submission_key);
        wp_send_json_error(array('message' => 'Payment system error. Please try again.'));
        return;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['error'])) {
        delete_transient($submission_key);
        wp_send_json_error(array('message' => 'Payment error: ' . $body['error']['message']));
        return;
    }
    
    if (isset($body['url'])) {
        wp_send_json_success(array(
            'checkout_url' => $body['url'],
            'session_id' => $body['id']
        ));
    } else {
        delete_transient($submission_key);
        wp_send_json_error(array('message' => 'Could not create payment session.'));
    }
}
add_action('wp_ajax_frontdoor_create_checkout', 'frontdoor_create_checkout_session');
add_action('wp_ajax_nopriv_frontdoor_create_checkout', 'frontdoor_create_checkout_session');

/**
 * Process submission after successful Stripe payment
 */
function frontdoor_process_payment() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'frontdoor_submit_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }
    
    $session_id = sanitize_text_field($_POST['session_id']);
    if (empty($session_id)) {
        wp_send_json_error(array('message' => 'Invalid payment session.'));
        return;
    }
    
    $stripe_keys = frontdoor_get_stripe_keys();
    
    // Retrieve the Stripe session to verify payment and get metadata
    $response = wp_remote_get('https://api.stripe.com/v1/checkout/sessions/' . $session_id . '?expand[]=metadata', array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode($stripe_keys['secret'] . ':')
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Could not verify payment. Please contact support.'));
        return;
    }
    
    $session = json_decode(wp_remote_retrieve_body($response), true);
    
    if (!isset($session['payment_status']) || $session['payment_status'] !== 'paid') {
        wp_send_json_error(array('message' => 'Payment not completed. Status: ' . ($session['payment_status'] ?? 'unknown')));
        return;
    }
    
    // Get submission data from WordPress options (primary) or transient (backup)
    $submission_key = isset($session['metadata']['submission_key']) ? $session['metadata']['submission_key'] : '';
    $submission_data = get_option($submission_key);
    
    if (!$submission_data) {
        // Try transient as backup
        $submission_data = get_transient($submission_key);
    }
    
    if (!$submission_data) {
        // Last resort: reconstruct from Stripe metadata
        if (isset($session['metadata']['film_title']) && isset($session['metadata']['filmmaker_email'])) {
            wp_send_json_error(array(
                'message' => 'Payment received, but we couldn\'t finalize your submission (server error). Please contact support with your film title and email.',
                'film_title' => $session['metadata']['film_title'],
                'email' => $session['metadata']['filmmaker_email'],
                'session_id' => $session_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Submission data not found. Please contact support with session ID: ' . $session_id));
        }
        return;
    }
    
    // Check if already processed (prevent double submissions)
    $processed_key = 'fd_processed_' . $session_id;
    if (get_option($processed_key)) {
        // Already processed - return success
        wp_send_json_success(array(
            'message' => 'Submission already processed!',
            'email' => $submission_data['filmmaker_email']
        ));
        return;
    }
    
    // Add payment info to submission
    $submission_data['stripe_session_id'] = $session_id;
    $submission_data['payment_amount'] = $session['amount_total'] / 100;
    $submission_data['payment_currency'] = $session['currency'];
    
    // Send to Front Door API
    $api_response = wp_remote_post(FRONTDOOR_API_URL . '/api/submissions', array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($submission_data)
    ));
    
    // Clean up stored data
    delete_transient($submission_key);
    delete_option($submission_key);
    
    if (is_wp_error($api_response)) {
        wp_send_json_error(array('message' => 'Submission failed. Please contact support with session ID: ' . $session_id));
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($api_response);
    $response_body = json_decode(wp_remote_retrieve_body($api_response), true);
    
    if ($response_code === 200 || $response_code === 201) {
        // Mark as processed to prevent duplicates
        update_option($processed_key, true, false);
        
        // Send confirmation email via SendGrid
        $submission_id = isset($response_body['id']) ? $response_body['id'] : '';
        frontdoor_send_confirmation_email($submission_data, $submission_id);
        
        wp_send_json_success(array(
            'message' => 'Submission received successfully!',
            'submission_id' => $submission_id,
            'email' => $submission_data['filmmaker_email']
        ));
    } else {
        // Payment succeeded but submission failed - log this for manual processing
        error_log('Front Door: Payment succeeded but submission failed. Session: ' . $session_id . ', Error: ' . print_r($response_body, true));
        wp_send_json_error(array('message' => 'Payment received but submission failed. Please contact support with session ID: ' . $session_id));
    }
}
add_action('wp_ajax_frontdoor_process_payment', 'frontdoor_process_payment');
add_action('wp_ajax_nopriv_frontdoor_process_payment', 'frontdoor_process_payment');

/**
 * Legacy submission handler (without payment - kept for compatibility)
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
                <div class="frontdoor-portal-info-icon">&#127916;</div>
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
        'Settings',
        'Settings',
        'manage_options',
        'frontdoor-settings',
        'frontdoor_settings_page'
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
 * Settings page
 */
function frontdoor_settings_page() {
    // Save settings
    if (isset($_POST['frontdoor_save_settings']) && wp_verify_nonce($_POST['frontdoor_settings_nonce'], 'frontdoor_save_settings')) {
        update_option('frontdoor_stripe_publishable_key', sanitize_text_field($_POST['stripe_publishable_key']));
        update_option('frontdoor_stripe_secret_key', sanitize_text_field($_POST['stripe_secret_key']));
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    
    $stripe_keys = frontdoor_get_stripe_keys();
    ?>
    <div class="wrap">
        <h1>Front Door Settings</h1>
        
        <form method="post">
            <?php wp_nonce_field('frontdoor_save_settings', 'frontdoor_settings_nonce'); ?>
            
            <h2>Stripe Configuration</h2>
            <p>Enter your Stripe API keys. You can find these in your <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a>.</p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="stripe_publishable_key">Publishable Key</label></th>
                    <td>
                        <input type="text" name="stripe_publishable_key" id="stripe_publishable_key" class="regular-text" value="<?php echo esc_attr($stripe_keys['publishable']); ?>" placeholder="pk_live_..." />
                        <p class="description">Starts with pk_live_ or pk_test_</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="stripe_secret_key">Secret Key</label></th>
                    <td>
                        <input type="password" name="stripe_secret_key" id="stripe_secret_key" class="regular-text" value="<?php echo esc_attr($stripe_keys['secret']); ?>" placeholder="sk_live_..." />
                        <p class="description">Starts with sk_live_ or sk_test_. Keep this secret!</p>
                    </td>
                </tr>
            </table>
            
            <h2>Submission Fee</h2>
            <p>Current submission fee: <strong>$<?php echo number_format(FRONTDOOR_SUBMISSION_FEE, 2); ?> USD</strong></p>
            <p class="description">To change the fee, edit the FRONTDOOR_SUBMISSION_FEE constant in the plugin file.</p>
            
            <p class="submit">
                <input type="submit" name="frontdoor_save_settings" class="button-primary" value="Save Settings" />
            </p>
        </form>
        
        <hr>
        
        <h2>Alternative: wp-config.php Configuration</h2>
        <p>You can also define Stripe keys in your wp-config.php file (recommended for security):</p>
        <pre style="background: #f1f1f1; padding: 15px; border-radius: 4px;">
define('FRONTDOOR_STRIPE_PUBLISHABLE_KEY', 'pk_live_your_key_here');
define('FRONTDOOR_STRIPE_SECRET_KEY', 'sk_live_your_key_here');
        </pre>
    </div>
    <?php
}

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
                                <span style="color: #46b450;">&#10003; Published</span>
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
                <div class="analytics-icon">&#127916;</div>
                <div class="analytics-value"><?php echo $total; ?></div>
                <div class="analytics-label">Total Submissions</div>
            </div>
            <div class="frontdoor-analytics-card success">
                <div class="analytics-icon">&#10003;</div>
                <div class="analytics-value"><?php echo $published; ?></div>
                <div class="analytics-label">Published Films</div>
            </div>
            <div class="frontdoor-analytics-card info">
                <div class="analytics-icon">&#128200;</div>
                <div class="analytics-value"><?php echo $publish_rate; ?>%</div>
                <div class="analytics-label">Publish Rate</div>
            </div>
            <div class="frontdoor-analytics-card warning">
                <div class="analytics-icon">&#128269;</div>
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
                <div class="pipeline-arrow">&rarr;</div>
                <div class="pipeline-stage">
                    <div class="pipeline-count"><?php echo $qa_passed + $classified; ?></div>
                    <div class="pipeline-label">Ready for Review</div>
                    <div class="pipeline-bar" style="background: #3b82f6;"></div>
                </div>
                <div class="pipeline-arrow">&rarr;</div>
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
