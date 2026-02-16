"""
Front Door Media - Standalone Backend API
==========================================
Deploy this on any Python hosting (Railway, Render, DigitalOcean, AWS, etc.)

Requirements:
- Python 3.9+
- pip install fastapi uvicorn stripe pymongo python-dotenv

Environment Variables (.env file):
- STRIPE_SECRET_KEY=sk_live_your_stripe_secret_key
- MONGO_URL=mongodb+srv://your_mongodb_connection_string
- DB_NAME=frontdoor_submissions
- ALLOWED_ORIGINS=https://frontdoormedia.org,https://www.frontdoormedia.org

Run locally: uvicorn server:app --reload --port 8000
"""

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, EmailStr
from typing import Optional
from datetime import datetime, timezone
import stripe
import os
import uuid
import logging

# Try to load from .env file if it exists
try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

# Configuration from environment variables
STRIPE_SECRET_KEY = os.environ.get('STRIPE_SECRET_KEY')
MONGO_URL = os.environ.get('MONGO_URL')
DB_NAME = os.environ.get('DB_NAME', 'frontdoor_submissions')
ALLOWED_ORIGINS = os.environ.get('ALLOWED_ORIGINS', 'https://frontdoormedia.org,https://www.frontdoormedia.org').split(',')

# Initialize Stripe
if STRIPE_SECRET_KEY:
    stripe.api_key = STRIPE_SECRET_KEY
else:
    logging.warning("STRIPE_SECRET_KEY not set - payments will fail")

# Initialize MongoDB (optional - falls back to file storage)
db = None
try:
    if MONGO_URL:
        from motor.motor_asyncio import AsyncIOMotorClient
        client = AsyncIOMotorClient(MONGO_URL)
        db = client[DB_NAME]
except ImportError:
    logging.warning("motor not installed - using file storage for submissions")
except Exception as e:
    logging.warning(f"MongoDB connection failed: {e} - using file storage")

# Create FastAPI app
app = FastAPI(
    title="Front Door Media Submission API",
    description="Backend API for film submission form with Stripe payments",
    version="1.0.0"
)

# CORS middleware - allow your WordPress site
app.add_middleware(
    CORSMiddleware,
    allow_origins=ALLOWED_ORIGINS + ["*"],  # Add * for testing, remove in production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


# ============ Models ============

class PaymentIntentRequest(BaseModel):
    amount: int  # Amount in cents
    email: EmailStr
    film_title: str


class PaymentIntentResponse(BaseModel):
    client_secret: str
    payment_intent_id: str


class FilmSubmission(BaseModel):
    film_title: str
    tagline: str
    synopsis: str
    genre: str
    runtime: Optional[int] = None
    awards: Optional[str] = None
    first_film: bool = False
    video_url: str
    poster_url: str
    filmmaker_name: str
    email: EmailStr
    payment_intent_id: str
    amount_paid: int


class SubmissionResponse(BaseModel):
    id: str
    film_title: str
    email: str
    status: str
    created_at: str


# ============ Helper Functions ============

async def save_submission_to_db(submission_data: dict) -> str:
    """Save submission to MongoDB or file"""
    submission_id = str(uuid.uuid4())
    submission_data['id'] = submission_id
    submission_data['status'] = 'pending_review'
    submission_data['created_at'] = datetime.now(timezone.utc).isoformat()
    
    if db is not None:
        # Save to MongoDB
        await db.submissions.insert_one(submission_data)
    else:
        # Fallback: Save to JSON file
        import json
        submissions_file = 'submissions.json'
        try:
            with open(submissions_file, 'r') as f:
                submissions = json.load(f)
        except (FileNotFoundError, json.JSONDecodeError):
            submissions = []
        
        submissions.append(submission_data)
        
        with open(submissions_file, 'w') as f:
            json.dump(submissions, f, indent=2)
    
    return submission_id


# ============ API Routes ============

@app.get("/")
async def root():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "service": "Front Door Media Submission API",
        "version": "1.0.0"
    }


@app.get("/api")
async def api_root():
    """API root endpoint"""
    return {"message": "Front Door Media API is running"}


@app.post("/api/create-payment-intent", response_model=PaymentIntentResponse)
async def create_payment_intent(request: PaymentIntentRequest):
    """
    Create a Stripe PaymentIntent for the submission fee
    """
    if not STRIPE_SECRET_KEY:
        raise HTTPException(status_code=500, detail="Payment system not configured")
    
    try:
        # Create PaymentIntent with Stripe
        intent = stripe.PaymentIntent.create(
            amount=request.amount,
            currency='usd',
            metadata={
                'film_title': request.film_title,
                'email': request.email,
                'source': 'frontdoor_submission'
            },
            receipt_email=request.email,
            description=f"Submission fee for: {request.film_title}"
        )
        
        logger.info(f"Created payment intent {intent.id} for {request.email}")
        
        return PaymentIntentResponse(
            client_secret=intent.client_secret,
            payment_intent_id=intent.id
        )
    
    except stripe.error.StripeError as e:
        logger.error(f"Stripe error: {e}")
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        logger.error(f"Payment intent error: {e}")
        raise HTTPException(status_code=500, detail="Failed to create payment intent")


@app.post("/api/submissions", response_model=SubmissionResponse)
async def create_submission(submission: FilmSubmission):
    """
    Save a film submission after successful payment
    """
    try:
        # Verify the payment was successful (optional but recommended)
        if STRIPE_SECRET_KEY:
            try:
                intent = stripe.PaymentIntent.retrieve(submission.payment_intent_id)
                if intent.status != 'succeeded':
                    raise HTTPException(status_code=400, detail="Payment not completed")
            except stripe.error.StripeError as e:
                logger.warning(f"Could not verify payment: {e}")
        
        # Save submission
        submission_data = submission.model_dump()
        submission_id = await save_submission_to_db(submission_data)
        
        logger.info(f"Saved submission {submission_id} for {submission.email}")
        
        # TODO: Send confirmation email here (integrate with SendGrid, SES, etc.)
        
        return SubmissionResponse(
            id=submission_id,
            film_title=submission.film_title,
            email=submission.email,
            status='pending_review',
            created_at=datetime.now(timezone.utc).isoformat()
        )
    
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Submission error: {e}")
        raise HTTPException(status_code=500, detail="Failed to save submission")


@app.get("/api/submissions/{submission_id}")
async def get_submission(submission_id: str):
    """
    Get a submission by ID (for filmmaker portal)
    """
    if db is not None:
        submission = await db.submissions.find_one(
            {"id": submission_id},
            {"_id": 0}
        )
        if submission:
            return submission
    else:
        import json
        try:
            with open('submissions.json', 'r') as f:
                submissions = json.load(f)
                for sub in submissions:
                    if sub.get('id') == submission_id:
                        return sub
        except (FileNotFoundError, json.JSONDecodeError):
            pass
    
    raise HTTPException(status_code=404, detail="Submission not found")


@app.get("/api/submissions")
async def list_submissions(email: Optional[str] = None):
    """
    List submissions (optionally filtered by email)
    """
    if db is not None:
        query = {"email": email} if email else {}
        submissions = await db.submissions.find(query, {"_id": 0}).to_list(100)
        return {"submissions": submissions}
    else:
        import json
        try:
            with open('submissions.json', 'r') as f:
                submissions = json.load(f)
                if email:
                    submissions = [s for s in submissions if s.get('email') == email]
                return {"submissions": submissions}
        except (FileNotFoundError, json.JSONDecodeError):
            return {"submissions": []}


# ============ Run Server ============

if __name__ == "__main__":
    import uvicorn
    port = int(os.environ.get("PORT", 8000))
    uvicorn.run(app, host="0.0.0.0", port=port)
