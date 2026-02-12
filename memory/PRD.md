# Front Door Media - Film Submission & Distribution System

## Original Problem Statement
Build a software according to this flow: Upload → AI/QA compliance → classification → route to shelf → auto-update Roku JSON feed → monthly email reports.

## Architecture
- **Frontend**: React dashboard for managing submissions
- **Backend**: FastAPI Python worker handling QA, classification, feed generation, email
- **Database**: MongoDB for submissions, QA reports, shelves
- **Integrations**: 
  - OpenAI GPT-4o for AI compliance checking
  - SendGrid for email notifications (pending API key)
  - ffprobe for video technical analysis

## User Personas
1. **Filmmakers**: Submit films, receive QA feedback, track status
2. **Curators/Admins**: Review submissions, publish to shelves, manage catalog
3. **Buyers**: Browse Roku channel shelves for acquisition-ready content

## Core Requirements (Static)
- [x] Film submission with metadata (title, description, poster, video URL)
- [x] Technical QA (codec, resolution, audio, duration)
- [x] AI compliance checking (content appropriateness, professional quality)
- [x] Automatic shelf classification with confidence scoring
- [x] Manual publish/reject workflow
- [x] Roku JSON feed auto-generation
- [x] WordPress webhook integration
- [ ] Monthly email reports (scheduled task)
- [ ] SendGrid integration (API key needed)

## What's Been Implemented (Feb 12, 2026)

### Backend APIs
- POST /api/submissions - Create film submission
- GET /api/submissions - List with filters
- GET /api/submissions/{id} - Get submission details
- POST /api/submissions/{id}/reprocess - Re-run QA
- POST /api/submissions/{id}/publish - Publish to shelf
- POST /api/submissions/{id}/reject - Reject submission
- GET /api/qa-reports/{id} - View QA report
- GET /api/feed - Get Roku JSON feed
- POST /api/feed/regenerate - Manual feed refresh
- GET /api/stats - Dashboard statistics
- POST /api/webhook/wordpress - WordPress integration

### Frontend Dashboard
- Stats overview (total, pending, processing, passed, failed, published)
- Submission cards with status badges
- Filter by status
- New submission form
- QA report modal
- Publish with shelf selection
- Reprocess and reject actions

### QA Pipeline
- ffprobe video analysis with HTTPS download fallback
- AI compliance using GPT-4o (content, quality, distribution readiness)
- Automatic shelf classification with 6 categories

## Shelf Categories
1. Buyer: Acquisition Ready
2. Buyer: Festival Winners & Official Selections
3. Buyer: New Voices
4. Buyer: High-Concept Shorts
5. Filmmaker: Getting Started
6. Filmmaker: Spotlight – Emerging Creators

## Prioritized Backlog

### P0 (Critical)
- None currently

### P1 (High)
- Configure SendGrid API key for email notifications
- Monthly report scheduled task

### P2 (Medium)
- Bulk import from WordPress media library
- Video thumbnail extraction
- Roku channel integration testing

## Next Tasks
1. Add SendGrid API key to enable email notifications
2. Implement monthly report cron job
3. Integrate with WordPress forms for auto-submission
4. Test Roku channel with live feed endpoint

## Update: Feb 12, 2026 - Filmmaker Portal Added

### New Features
- **Filmmaker Self-Service Portal** (`/portal` or `#portal`)
  - Email-based submission lookup
  - View submission status and progress
  - Detailed QA reports with technical checks
  - Issues and recommendations display
  - FAQ section for common questions
  - Mobile-responsive design

### New API Endpoints
- GET /api/portal/lookup?email={email} - Look up submissions by filmmaker email
- GET /api/portal/submission/{id}?email={email} - Get detailed submission with email verification

### SendGrid Integration
- API key configured
- Email notifications enabled for QA reports

## Update: Feb 12, 2026 - Email Notifications with Portal Links

### Enhanced Email System
- **Beautiful HTML email templates** with gradient headers and clear CTAs
- **QA Report Emails**: Sent after QA processing with technical check results
- **Publish Notification**: Congratulations email when film goes live
- **Reject Notification**: Polite notification with reason
- **All emails include portal link** so filmmakers can track status anytime

### Email Templates Include:
- Film title and status
- Technical check results (video, codec, resolution, audio)
- Issues and recommendations
- Direct "View in Filmmaker Portal" button
- Mobile-responsive design
