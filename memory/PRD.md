# Front Door Media Submission Form - PRD

## Original Problem Statement
User reported issues with the Front Door Media submission form:
1. Genre field not working properly
2. "Waking up servers" message appearing
3. User does NOT want the page hosted on Emergent - wants standalone code for WordPress

## Solution Delivered
Created **standalone HTML files** that can be embedded directly in WordPress without any Emergent hosting dependency.

## Files Created
1. `/app/frontdoor-submission-form.html` - Complete standalone HTML file
2. `/app/wordpress-embed-code.html` - WordPress-ready embed code with prefixed CSS classes

## Features
- ✅ Genre dropdown works (standard HTML select)
- ✅ No "waking up servers" message
- ✅ Stripe payment integration (live key: pk_live_51T0rflF8g6zxqgeMOd3kJViRC6aE7h1QVeElrteBBXC63MrSGXvlBvlUSRlBXOUZr7tCf60j2cL92mAutHhuL1C800NJ075iec)
- ✅ Two-step form (Film Details → Payment)
- ✅ Connects to existing backend API at roku-feed-debug.preview.emergentagent.com/api
- ✅ Responsive design
- ✅ Dark theme matching Front Door Media branding

## How to Use in WordPress
1. Go to WordPress page editor
2. Add a "Custom HTML" block
3. Copy entire content from `/app/wordpress-embed-code.html` and paste it

## Backend API Endpoints Used
- POST `/api/create-payment-intent` - Create Stripe payment intent
- POST `/api/submissions` - Save film submission data

## Date
February 16, 2026
