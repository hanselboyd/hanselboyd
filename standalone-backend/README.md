# Front Door Media - Standalone Backend API

A simple FastAPI backend for the Front Door Media film submission form with Stripe payments.

## Quick Start

### 1. Install Dependencies
```bash
pip install -r requirements.txt
```

### 2. Configure Environment Variables
```bash
cp .env.example .env
# Edit .env with your actual values
```

### 3. Run Locally
```bash
uvicorn server:app --reload --port 8000
```

The API will be available at `http://localhost:8000`

## Deployment Options

### Option A: Railway (Recommended - Free tier available)
1. Create account at [railway.app](https://railway.app)
2. Connect your GitHub repo or upload the code
3. Add environment variables in Railway dashboard
4. Deploy - Railway auto-detects Python/FastAPI

### Option B: Render (Free tier available)
1. Create account at [render.com](https://render.com)
2. Create new "Web Service"
3. Connect repo or upload code
4. Set build command: `pip install -r requirements.txt`
5. Set start command: `uvicorn server:app --host 0.0.0.0 --port $PORT`
6. Add environment variables

### Option C: DigitalOcean App Platform
1. Create account at [digitalocean.com](https://digitalocean.com)
2. Create new App
3. Upload code or connect repo
4. Configure environment variables
5. Deploy

### Option D: Any VPS (DigitalOcean Droplet, AWS EC2, etc.)
```bash
# On your server
git clone <your-repo>
cd standalone-backend
pip install -r requirements.txt
cp .env.example .env
# Edit .env with your values

# Run with gunicorn for production
pip install gunicorn
gunicorn server:app -w 4 -k uvicorn.workers.UvicornWorker -b 0.0.0.0:8000
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | Health check |
| GET | `/api` | API status |
| POST | `/api/create-payment-intent` | Create Stripe payment intent |
| POST | `/api/submissions` | Save film submission |
| GET | `/api/submissions` | List all submissions |
| GET | `/api/submissions/{id}` | Get submission by ID |

## Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `STRIPE_SECRET_KEY` | Your Stripe secret key (sk_live_...) | Yes |
| `MONGO_URL` | MongoDB connection string | No (uses file storage if not set) |
| `DB_NAME` | Database name | No (defaults to frontdoor_submissions) |
| `ALLOWED_ORIGINS` | Comma-separated allowed origins | No (defaults to frontdoormedia.org) |
| `PORT` | Server port | No (defaults to 8000) |

## Update WordPress Form

After deploying, update the `API_URL` in your WordPress form code:

```javascript
const CONFIG = {
    STRIPE_PUBLIC_KEY: 'pk_live_51T0rflF8g6zxqgeMOd3kJViRC6aE7h1QVeElrteBBXC63MrSGXvlBvlUSRlBXOUZr7tCf60j2cL92mAutHhuL1C800NJ075iec',
    API_URL: 'https://your-deployed-api-url.com/api',  // <-- UPDATE THIS
    SUBMISSION_FEE: 2500
};
```

## Storage Options

The API supports two storage backends:

1. **MongoDB** (recommended for production) - Set `MONGO_URL`
2. **File Storage** (simple fallback) - If no MongoDB URL is set, submissions are saved to `submissions.json`

## Security Notes

- Never expose your `STRIPE_SECRET_KEY` in frontend code
- Use HTTPS in production
- Set `ALLOWED_ORIGINS` to only your WordPress site domains
- Consider adding rate limiting for production use
