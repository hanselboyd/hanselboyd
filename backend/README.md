# Front Door Media - Backend API

Film submission system with AI/QA compliance, classification, and Roku feed generation.

## Environment Variables Required

Set these in Render Dashboard → Environment:

```
MONGO_URL=mongodb+srv://your-mongodb-atlas-connection-string
DB_NAME=frontdoor_db
EMERGENT_LLM_KEY=sk-emergent-1556d77DbBb9bE85a2
SENDGRID_API_KEY=SG.your-sendgrid-key
SENDER_EMAIL=noreply@frontdoormedia.org
WORDPRESS_URL=https://frontdoormedia.org
```

## Deploy to Render

1. Push this `backend/` folder to a GitHub repo
2. Connect repo to Render
3. Set environment variables
4. Deploy!

## API Endpoints

- `POST /api/webhook/wordpress` - WordPress form webhook
- `GET /api/feed` - Roku JSON feed
- `GET /api/portal/lookup?email=` - Filmmaker portal lookup
