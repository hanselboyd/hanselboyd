# Front Door Film Submissions - WordPress Plugin

A WordPress plugin that adds a film submission form, filmmaker portal, admin dashboard, and analytics to your website, connected to the Front Door Media API.

## Installation

1. Download the `frontdoor-submissions.zip` file
2. Go to your WordPress admin → Plugins → Add New → Upload Plugin
3. Upload the zip file and click "Install Now"
4. Activate the plugin

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
- Publish films to Roku shelves
- Reject submissions with reason
- View analytics

## Features

- **Beautiful Form Design**: Modern, responsive form with gradient accents
- **Real-time Validation**: Client-side and server-side validation
- **AJAX Submission**: Smooth submission without page reload
- **Admin Dashboard**: Review, publish, and reject submissions from WordPress
- **Analytics**: View pipeline metrics and shelf distribution
- **Filmmaker Portal**: Let filmmakers check their submission status
- **QA Results Display**: Shows technical QA check results
- **Email Auto-fill**: Portal links from emails auto-populate and search
- **Mobile Responsive**: Works great on all devices

## API Connection

The plugin connects to the Front Door Media API at:
`https://frontdoor-api-npz4.onrender.com`

To change the API URL, edit the `FRONTDOOR_API_URL` constant in `frontdoor-submissions.php`.

## Page Setup Recommendations

1. Create a page called "Submit Film" with the `[frontdoor_submission_form]` shortcode
2. Create a page called "Filmmaker Portal" with the `[frontdoor_portal]` shortcode  
3. Create a page called "Statistics" with the `[frontdoor_analytics]` shortcode (optional)
4. Create pages for "Terms of Service" and "Submission Guidelines" (linked in the form)

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- jQuery (included with WordPress)

## Changelog

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
