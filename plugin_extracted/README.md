# Front Door Film Submissions - WordPress Plugin

A WordPress plugin that adds a film submission form with Stripe payment, filmmaker portal, admin dashboard, and analytics to your website, connected to the Front Door Media API.

## Installation

1. Download the `frontdoor-submissions-with-stripe.zip` file
2. Go to your WordPress admin → Plugins → Add New → Upload Plugin
3. Upload the zip file and click "Install Now"
4. Activate the plugin

## Stripe Configuration (REQUIRED)

You **must** configure your Stripe API keys for payments to work.

### Option 1: WordPress Admin (Easy)
1. Go to **WordPress Admin → Film Submissions → Settings**
2. Enter your Stripe Publishable Key (starts with `pk_`)
3. Enter your Stripe Secret Key (starts with `sk_`)
4. Click "Save Settings"

### Option 2: wp-config.php (Recommended for security)
Add these lines to your `wp-config.php` file:

```php
define('FRONTDOOR_STRIPE_PUBLISHABLE_KEY', 'pk_live_your_key_here');
define('FRONTDOOR_STRIPE_SECRET_KEY', 'sk_live_your_key_here');
```

You can get your API keys from [Stripe Dashboard → API Keys](https://dashboard.stripe.com/apikeys)

## Usage

### Submission Form

Add the film submission form to any page or post using the shortcode:

```
[frontdoor_submission_form]
```

**Options:**
- `title` - Custom form title (default: "Submit Your Film")
- `show_guidelines` - Show technical guidelines below form (default: "true")

Example with custom title:
```
[frontdoor_submission_form title="Submit Your Short Film" show_guidelines="false"]
```

### Filmmaker Portal

Add the status lookup portal to any page:

```
[frontdoor_portal]
```

**Options:**
- `title` - Custom section title (default: "Check Submission Status")

Example:
```
[frontdoor_portal title="Track Your Submission"]
```

### Analytics Dashboard (Public)

Display platform statistics on any public page:

```
[frontdoor_analytics]
```

**Options:**
- `title` - Custom title (default: "Platform Statistics")

Example:
```
[frontdoor_analytics title="Front Door Media Stats"]
```

### Admin Dashboard

After activation, go to **WordPress Admin → Film Submissions** to:
- View all film submissions
- See submission details and QA reports
- Publish films to Roku shelves (Now Programming, Premieres, Concept Cinema, AI Nonfiction, Spotlight, Archive)
- Reject submissions with reason
- View analytics

## Features

- **Two-Step Submission Form**: Film details → Stripe payment → Submission
- **Stripe Integration**: Secure credit card payments via Stripe Checkout
- **$25 Submission Fee**: Configurable in the plugin (FRONTDOOR_SUBMISSION_FEE constant)
- **Beautiful Form Design**: Modern, responsive form with gradient accents
- **Real-time Validation**: Client-side and server-side validation
- **AJAX Submission**: Smooth submission without page reload
- **Admin Dashboard**: Review, publish, and reject submissions from WordPress
- **Analytics**: View pipeline metrics and shelf distribution
- **Filmmaker Portal**: Let filmmakers check their submission status
- **QA Results Display**: Shows technical QA check results
- **Email Auto-fill**: Portal links from emails auto-populate and search
- **Mobile Responsive**: Works great on all devices

## Payment Flow

1. User fills out film details form (step 1)
2. User reviews summary and clicks "Pay & Submit Film" (step 2)
3. User is redirected to Stripe Checkout for secure payment
4. After successful payment, user is redirected back to your site
5. The plugin automatically verifies payment and submits to Front Door API
6. User sees success confirmation with filmmaker portal link

## API Connection

The plugin connects to the Front Door Media API at:
`https://frontdoor-api-npz4.onrender.com`

To change the API URL, edit the `FRONTDOOR_API_URL` constant in `frontdoor-submissions.php`.

## Shelves

Films can be published to these shelves:
- Now Programming
- Premieres
- Concept Cinema
- AI Nonfiction
- Spotlight
- Archive

## Page Setup Recommendations

1. Create a page called "Submit Film" with the `[frontdoor_submission_form]` shortcode
2. Create a page called "Filmmaker Portal" with the `[frontdoor_portal]` shortcode  
3. Create a page called "Statistics" with the `[frontdoor_analytics]` shortcode (optional)
4. Create pages for "Terms of Service" and "Submission Guidelines" (linked in the form)

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- jQuery (included with WordPress)
- Stripe account with API keys

## Changing the Submission Fee

To change the submission fee, edit this line in `frontdoor-submissions.php`:

```php
define('FRONTDOOR_SUBMISSION_FEE', 25.00); // Submission fee in USD
```

## Changelog

### 2.0.0
- Added Stripe payment integration
- Two-step submission form (details → payment)
- Payment confirmation and polling
- Settings page for Stripe API keys
- Support for wp-config.php configuration
- Updated shelf names to match Roku channel

### 1.1.0
- Added WordPress admin dashboard for managing submissions
- Added publish/reject functionality
- Added analytics dashboard (admin + public shortcode)
- Added submission details modal with QA report
- Email links now auto-fill and auto-search

### 1.0.0
- Initial release
- Submission form shortcode
- Filmmaker portal shortcode
- Full QA pipeline integration
