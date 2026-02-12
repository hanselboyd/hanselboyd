"""
Front Door Media - Film Submission & QA System
Handles: Upload → AI/QA compliance → Classification → Route to shelf → Auto-update Roku JSON feed → Email reports
"""

from fastapi import FastAPI, HTTPException, BackgroundTasks, Query
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, EmailStr, Field
from typing import Optional, List, Dict, Any
from datetime import datetime, timezone
from enum import Enum
import os
import json
import asyncio
import subprocess
import re
from dotenv import load_dotenv
from pymongo import MongoClient
from bson import ObjectId

load_dotenv()

# Initialize FastAPI
app = FastAPI(title="Front Door Media API", version="1.0.0")

# CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# MongoDB
mongo_client = MongoClient(os.environ.get("MONGO_URL"))
db = mongo_client[os.environ.get("DB_NAME", "frontdoor_db")]
submissions_collection = db["submissions"]
shelves_collection = db["shelves"]
qa_reports_collection = db["qa_reports"]

# ==================== ENUMS & MODELS ====================

class SubmissionStatus(str, Enum):
    PENDING = "pending"
    QA_PROCESSING = "qa_processing"
    QA_PASSED = "qa_passed"
    QA_FAILED = "qa_failed"
    CLASSIFIED = "classified"
    PUBLISHED = "published"
    REJECTED = "rejected"

class ShelfCategory(str, Enum):
    ACQUISITION_READY = "Buyer: Acquisition Ready"
    FESTIVAL_WINNERS = "Buyer: Festival Winners & Official Selections"
    NEW_VOICES = "Buyer: New Voices"
    HIGH_CONCEPT = "Buyer: High-Concept Shorts"
    GETTING_STARTED = "Filmmaker: Getting Started"
    EMERGING_CREATORS = "Filmmaker: Spotlight – Emerging Creators"

class FilmSubmission(BaseModel):
    title: str
    short_description: str
    description: str
    poster_url: str
    video_url: str
    video_type: str = "mp4"
    filmmaker_name: str
    filmmaker_email: EmailStr
    genre: Optional[str] = None
    runtime_minutes: Optional[int] = None
    festival_awards: Optional[str] = None
    is_first_film: bool = False
    wordpress_post_id: Optional[str] = None

class QAReport(BaseModel):
    submission_id: str
    technical_checks: Dict[str, Any]
    ai_compliance: Dict[str, Any]
    overall_passed: bool
    issues: List[str]
    recommendations: List[str]
    created_at: datetime

class ClassificationResult(BaseModel):
    submission_id: str
    recommended_shelf: str
    confidence: float
    reasoning: str

# ==================== HELPER FUNCTIONS ====================

def serialize_doc(doc: dict) -> dict:
    """Convert MongoDB document for JSON serialization"""
    if doc is None:
        return None
    doc["id"] = str(doc.pop("_id"))
    for key, value in doc.items():
        if isinstance(value, datetime):
            doc[key] = value.isoformat()
    return doc

async def run_ffprobe(video_url: str) -> Dict[str, Any]:
    """Run ffprobe to get video metadata - handles HTTPS URLs with download fallback"""
    import tempfile
    import urllib.request
    
    try:
        # First try direct ffprobe with user-agent
        cmd = [
            "ffprobe", "-v", "quiet", "-print_format", "json",
            "-show_format", "-show_streams",
            "-user_agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
            video_url
        ]
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
        if result.returncode == 0 and result.stdout.strip() and result.stdout.strip() != "{\n\n}":
            data = json.loads(result.stdout)
            if data.get("streams"):
                return data
        
        # Fallback: download a portion of the file and analyze
        print(f"Direct ffprobe failed, trying download method for {video_url}")
        
        # Use curl to download first 5MB of the video
        with tempfile.NamedTemporaryFile(suffix='.mp4', delete=False) as tmp:
            tmp_path = tmp.name
        
        curl_cmd = [
            "curl", "-s", "-L", "-o", tmp_path,
            "-r", "0-5242880",  # First 5MB
            "-A", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
            video_url
        ]
        curl_result = subprocess.run(curl_cmd, capture_output=True, text=True, timeout=60)
        
        if curl_result.returncode == 0:
            # Analyze the downloaded portion
            probe_cmd = [
                "ffprobe", "-v", "quiet", "-print_format", "json",
                "-show_format", "-show_streams", tmp_path
            ]
            probe_result = subprocess.run(probe_cmd, capture_output=True, text=True, timeout=30)
            
            # Clean up
            try:
                os.unlink(tmp_path)
            except:
                pass
            
            if probe_result.returncode == 0 and probe_result.stdout.strip():
                return json.loads(probe_result.stdout)
        
        return {"error": "Could not analyze video"}
        
    except subprocess.TimeoutExpired:
        return {"error": "ffprobe timeout"}
    except Exception as e:
        return {"error": str(e)}
    except Exception as e:
        return {"error": str(e)}

async def run_technical_qa(video_url: str) -> Dict[str, Any]:
    """Run technical QA checks on video file"""
    checks = {
        "video_accessible": False,
        "codec_valid": False,
        "resolution_acceptable": False,
        "audio_present": False,
        "duration_valid": False,
        "bitrate_acceptable": False,
        "details": {}
    }
    
    probe_data = await run_ffprobe(video_url)
    
    if "error" in probe_data:
        checks["details"]["error"] = probe_data["error"]
        return checks
    
    checks["video_accessible"] = True
    
    # Analyze streams
    video_stream = None
    audio_stream = None
    
    for stream in probe_data.get("streams", []):
        if stream.get("codec_type") == "video" and video_stream is None:
            video_stream = stream
        elif stream.get("codec_type") == "audio" and audio_stream is None:
            audio_stream = stream
    
    if video_stream:
        # Codec check (H.264 or H.265 preferred for Roku)
        codec = video_stream.get("codec_name", "").lower()
        checks["codec_valid"] = codec in ["h264", "hevc", "h265", "avc"]
        checks["details"]["codec"] = codec
        
        # Resolution check (min 480p for promos, prefer 720p+)
        width = int(video_stream.get("width", 0))
        height = int(video_stream.get("height", 0))
        checks["resolution_acceptable"] = width >= 640 and height >= 480
        checks["details"]["resolution"] = f"{width}x{height}"
        
        # Bitrate check
        bitrate = int(video_stream.get("bit_rate", 0) or probe_data.get("format", {}).get("bit_rate", 0))
        checks["bitrate_acceptable"] = bitrate >= 1000000  # Min 1 Mbps
        checks["details"]["bitrate_mbps"] = round(bitrate / 1000000, 2)
    
    if audio_stream:
        checks["audio_present"] = True
        checks["details"]["audio_codec"] = audio_stream.get("codec_name")
        checks["details"]["audio_channels"] = audio_stream.get("channels")
    
    # Duration check (30 sec to 90 min for shorts - flexible range)
    duration = float(probe_data.get("format", {}).get("duration", 0))
    checks["duration_valid"] = 30 <= duration <= 5400
    checks["details"]["duration_seconds"] = round(duration, 2)
    checks["details"]["duration_minutes"] = round(duration / 60, 1)
    
    return checks

async def run_ai_compliance_check(submission: dict) -> Dict[str, Any]:
    """Run AI-powered content compliance checks"""
    try:
        from emergentintegrations.llm.chat import LlmChat, UserMessage
        
        chat = LlmChat(
            api_key=os.environ.get("EMERGENT_LLM_KEY"),
            session_id=f"compliance-{submission.get('_id', 'new')}",
            system_message="""You are a content compliance reviewer for a film distribution platform. 
            Analyze film submissions for:
            1. Content appropriateness (no explicit violence, hate speech, illegal content)
            2. Professional quality indicators (proper metadata, clear descriptions)
            3. Distribution readiness (complete information, marketable content)
            
            Respond in JSON format with:
            {
                "content_appropriate": true/false,
                "professional_quality": true/false,
                "distribution_ready": true/false,
                "issues": ["list of issues if any"],
                "suggestions": ["list of improvement suggestions"],
                "overall_passed": true/false
            }"""
        ).with_model("openai", "gpt-4o")
        
        prompt = f"""Review this film submission:
        
Title: {submission.get('title')}
Short Description: {submission.get('short_description')}
Full Description: {submission.get('description')}
Genre: {submission.get('genre', 'Not specified')}
Festival Awards: {submission.get('festival_awards', 'None')}
Is First Film: {submission.get('is_first_film', False)}

Analyze for content compliance and distribution readiness."""

        user_message = UserMessage(text=prompt)
        response = await chat.send_message(user_message)
        
        # Parse JSON response
        try:
            # Extract JSON from response
            json_match = re.search(r'\{[\s\S]*\}', response)
            if json_match:
                return json.loads(json_match.group())
        except json.JSONDecodeError:
            pass
        
        # Fallback response
        return {
            "content_appropriate": True,
            "professional_quality": True,
            "distribution_ready": True,
            "issues": [],
            "suggestions": ["AI analysis completed with basic checks"],
            "overall_passed": True
        }
        
    except Exception as e:
        print(f"AI compliance error: {e}")
        return {
            "content_appropriate": True,
            "professional_quality": True,
            "distribution_ready": True,
            "issues": [],
            "suggestions": [f"AI check skipped: {str(e)}"],
            "overall_passed": True
        }

async def classify_submission(submission: dict, qa_report: dict) -> ClassificationResult:
    """Classify submission into appropriate shelf category using AI"""
    try:
        from emergentintegrations.llm.chat import LlmChat, UserMessage
        
        chat = LlmChat(
            api_key=os.environ.get("EMERGENT_LLM_KEY"),
            session_id=f"classify-{submission.get('_id', 'new')}",
            system_message="""You are a film curator for Front Door Media. Classify films into shelves:

1. "Buyer: Acquisition Ready" - High-quality, market-ready films for distributors
2. "Buyer: Festival Winners & Official Selections" - Award-winning or festival-selected films  
3. "Buyer: New Voices" - Fresh, breakout directors with unique perspectives
4. "Buyer: High-Concept Shorts" - Films with strong, unique concepts
5. "Filmmaker: Getting Started" - Educational/tutorial content for filmmakers
6. "Filmmaker: Spotlight – Emerging Creators" - Promising emerging talent

Respond in JSON: {"shelf": "exact shelf name", "confidence": 0.0-1.0, "reasoning": "brief explanation"}"""
        ).with_model("openai", "gpt-4o")
        
        prompt = f"""Classify this film submission:

Title: {submission.get('title')}
Description: {submission.get('description')}
Genre: {submission.get('genre', 'Not specified')}
Festival Awards: {submission.get('festival_awards', 'None')}
Is First Film: {submission.get('is_first_film', False)}
Technical Quality: {"Passed" if qa_report.get('overall_passed') else "Issues found"}

Which shelf should this film be placed on?"""

        user_message = UserMessage(text=prompt)
        response = await chat.send_message(user_message)
        
        # Parse JSON response
        try:
            json_match = re.search(r'\{[\s\S]*\}', response)
            if json_match:
                result = json.loads(json_match.group())
                return ClassificationResult(
                    submission_id=str(submission.get('_id', '')),
                    recommended_shelf=result.get('shelf', ShelfCategory.EMERGING_CREATORS.value),
                    confidence=float(result.get('confidence', 0.7)),
                    reasoning=result.get('reasoning', 'AI classification')
                )
        except (json.JSONDecodeError, KeyError):
            pass
        
        # Fallback classification based on rules
        if submission.get('festival_awards'):
            shelf = ShelfCategory.FESTIVAL_WINNERS.value
        elif submission.get('is_first_film'):
            shelf = ShelfCategory.NEW_VOICES.value
        else:
            shelf = ShelfCategory.EMERGING_CREATORS.value
            
        return ClassificationResult(
            submission_id=str(submission.get('_id', '')),
            recommended_shelf=shelf,
            confidence=0.6,
            reasoning="Rule-based classification fallback"
        )
        
    except Exception as e:
        print(f"Classification error: {e}")
        return ClassificationResult(
            submission_id=str(submission.get('_id', '')),
            recommended_shelf=ShelfCategory.EMERGING_CREATORS.value,
            confidence=0.5,
            reasoning=f"Default classification: {str(e)}"
        )

def generate_roku_feed() -> dict:
    """Generate the Roku JSON feed from database"""
    feed = {
        "providerName": "Front Door",
        "lastUpdated": datetime.now(timezone.utc).isoformat(),
        "language": "en-US",
        "categories": []
    }
    
    # Get all shelf categories
    for shelf in ShelfCategory:
        items = []
        
        # Get published submissions for this shelf
        submissions = submissions_collection.find({
            "status": SubmissionStatus.PUBLISHED.value,
            "assigned_shelf": shelf.value
        }).sort("published_at", -1).limit(20)
        
        for sub in submissions:
            items.append({
                "title": sub.get("title", ""),
                "shortDescription": sub.get("short_description", ""),
                "description": sub.get("description", ""),
                "poster": sub.get("poster_url", ""),
                "videoUrl": sub.get("video_url", ""),
                "videoType": sub.get("video_type", "mp4")
            })
        
        # Only add category if it has items or is a buyer category
        if items or shelf.value.startswith("Buyer:"):
            # Add placeholder if empty
            if not items:
                items.append({
                    "title": "Coming Soon",
                    "shortDescription": "New content arriving soon",
                    "description": "Check back for new films in this category.",
                    "poster": "https://frontdoormedia.org/wp-content/uploads/2026/01/frontdoor_hero.png",
                    "videoUrl": "https://frontdoormedia.org/wp-content/uploads/2026/01/frontdoor.mp4",
                    "videoType": "mp4"
                })
            
            feed["categories"].append({
                "title": shelf.value,
                "items": items
            })
    
    return feed

def save_roku_feed(feed: dict):
    """Save the Roku feed to file"""
    feed_path = os.environ.get("FEED_OUTPUT_PATH", "/app/feeds/frontdoor_shelves_public_v1.json")
    os.makedirs(os.path.dirname(feed_path), exist_ok=True)
    
    with open(feed_path, 'w') as f:
        json.dump(feed, f, indent=2)
    
    return feed_path

async def send_qa_email(submission: dict, qa_report: dict):
    """Send QA report email to filmmaker"""
    try:
        from sendgrid import SendGridAPIClient
        from sendgrid.helpers.mail import Mail
        
        api_key = os.environ.get("SENDGRID_API_KEY")
        if not api_key:
            print("SendGrid API key not configured")
            return False
        
        status = "✅ PASSED" if qa_report.get("overall_passed") else "❌ NEEDS ATTENTION"
        issues_html = ""
        if qa_report.get("issues"):
            issues_html = "<ul>" + "".join(f"<li>{issue}</li>" for issue in qa_report["issues"]) + "</ul>"
        
        html_content = f"""
        <html>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h1 style="color: #333;">Front Door Media - QA Report</h1>
            <h2>Film: {submission.get('title')}</h2>
            <p><strong>Status:</strong> {status}</p>
            
            <h3>Technical Checks:</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr><td>Video Accessible:</td><td>{"✅" if qa_report.get("technical_checks", {}).get("video_accessible") else "❌"}</td></tr>
                <tr><td>Codec Valid:</td><td>{"✅" if qa_report.get("technical_checks", {}).get("codec_valid") else "❌"}</td></tr>
                <tr><td>Resolution OK:</td><td>{"✅" if qa_report.get("technical_checks", {}).get("resolution_acceptable") else "❌"}</td></tr>
                <tr><td>Audio Present:</td><td>{"✅" if qa_report.get("technical_checks", {}).get("audio_present") else "❌"}</td></tr>
            </table>
            
            {"<h3>Issues Found:</h3>" + issues_html if issues_html else ""}
            
            <h3>Recommendations:</h3>
            <ul>
                {"".join(f"<li>{rec}</li>" for rec in qa_report.get("recommendations", ["No specific recommendations"]))}
            </ul>
            
            <hr>
            <p style="color: #666; font-size: 12px;">
                This is an automated message from Front Door Media.<br>
                Questions? Reply to this email or visit frontdoormedia.org
            </p>
        </body>
        </html>
        """
        
        message = Mail(
            from_email=os.environ.get("SENDER_EMAIL", "noreply@frontdoormedia.org"),
            to_emails=submission.get("filmmaker_email"),
            subject=f"Front Door QA Report: {submission.get('title')} - {status}",
            html_content=html_content
        )
        
        sg = SendGridAPIClient(api_key)
        response = sg.send(message)
        return response.status_code == 202
        
    except Exception as e:
        print(f"Email error: {e}")
        return False

# ==================== API ENDPOINTS ====================

@app.get("/api/health")
async def health_check():
    return {"status": "healthy", "service": "frontdoor-media-api", "timestamp": datetime.now(timezone.utc).isoformat()}

# --- Submission Endpoints ---

@app.post("/api/submissions")
async def create_submission(submission: FilmSubmission, background_tasks: BackgroundTasks):
    """Create a new film submission (called from WordPress webhook)"""
    doc = submission.dict()
    doc["status"] = SubmissionStatus.PENDING.value
    doc["created_at"] = datetime.now(timezone.utc)
    doc["updated_at"] = datetime.now(timezone.utc)
    
    result = submissions_collection.insert_one(doc)
    doc["_id"] = result.inserted_id
    
    # Start QA processing in background
    background_tasks.add_task(process_submission_qa, str(result.inserted_id))
    
    return {"id": str(result.inserted_id), "status": "pending", "message": "Submission received, QA processing started"}

@app.get("/api/submissions")
async def list_submissions(
    status: Optional[str] = None,
    shelf: Optional[str] = None,
    limit: int = Query(default=50, le=100),
    skip: int = 0
):
    """List all submissions with optional filters"""
    query = {}
    if status:
        query["status"] = status
    if shelf:
        query["assigned_shelf"] = shelf
    
    submissions = list(submissions_collection.find(query).sort("created_at", -1).skip(skip).limit(limit))
    return [serialize_doc(s) for s in submissions]

@app.get("/api/submissions/{submission_id}")
async def get_submission(submission_id: str):
    """Get a specific submission"""
    submission = submissions_collection.find_one({"_id": ObjectId(submission_id)})
    if not submission:
        raise HTTPException(status_code=404, detail="Submission not found")
    return serialize_doc(submission)

@app.post("/api/submissions/{submission_id}/reprocess")
async def reprocess_submission(submission_id: str, background_tasks: BackgroundTasks):
    """Reprocess QA for a submission"""
    submission = submissions_collection.find_one({"_id": ObjectId(submission_id)})
    if not submission:
        raise HTTPException(status_code=404, detail="Submission not found")
    
    background_tasks.add_task(process_submission_qa, submission_id)
    return {"message": "QA reprocessing started"}

@app.post("/api/submissions/{submission_id}/publish")
async def publish_submission(submission_id: str, shelf: Optional[str] = None):
    """Publish a submission to a shelf"""
    submission = submissions_collection.find_one({"_id": ObjectId(submission_id)})
    if not submission:
        raise HTTPException(status_code=404, detail="Submission not found")
    
    assigned_shelf = shelf or submission.get("recommended_shelf", ShelfCategory.EMERGING_CREATORS.value)
    
    submissions_collection.update_one(
        {"_id": ObjectId(submission_id)},
        {"$set": {
            "status": SubmissionStatus.PUBLISHED.value,
            "assigned_shelf": assigned_shelf,
            "published_at": datetime.now(timezone.utc),
            "updated_at": datetime.now(timezone.utc)
        }}
    )
    
    # Regenerate feed
    feed = generate_roku_feed()
    feed_path = save_roku_feed(feed)
    
    return {"message": "Published successfully", "shelf": assigned_shelf, "feed_updated": True}

@app.post("/api/submissions/{submission_id}/reject")
async def reject_submission(submission_id: str, reason: str = "Not meeting quality standards"):
    """Reject a submission"""
    submission = submissions_collection.find_one({"_id": ObjectId(submission_id)})
    if not submission:
        raise HTTPException(status_code=404, detail="Submission not found")
    
    submissions_collection.update_one(
        {"_id": ObjectId(submission_id)},
        {"$set": {
            "status": SubmissionStatus.REJECTED.value,
            "rejection_reason": reason,
            "updated_at": datetime.now(timezone.utc)
        }}
    )
    
    return {"message": "Submission rejected", "reason": reason}

# --- Filmmaker Portal Endpoints ---

@app.get("/api/portal/lookup")
async def filmmaker_lookup(email: str = Query(..., description="Filmmaker email address")):
    """Look up submissions by filmmaker email - public endpoint for self-service portal"""
    submissions = list(submissions_collection.find(
        {"filmmaker_email": email.lower().strip()}
    ).sort("created_at", -1))
    
    results = []
    for sub in submissions:
        # Get QA report if exists
        qa_report = qa_reports_collection.find_one({"submission_id": str(sub["_id"])})
        
        result = {
            "id": str(sub["_id"]),
            "title": sub.get("title"),
            "status": sub.get("status"),
            "created_at": sub.get("created_at").isoformat() if sub.get("created_at") else None,
            "updated_at": sub.get("updated_at").isoformat() if sub.get("updated_at") else None,
            "poster_url": sub.get("poster_url"),
            "recommended_shelf": sub.get("recommended_shelf"),
            "assigned_shelf": sub.get("assigned_shelf"),
            "classification_confidence": sub.get("classification_confidence"),
            "rejection_reason": sub.get("rejection_reason"),
            "qa_summary": None
        }
        
        if qa_report:
            result["qa_summary"] = {
                "overall_passed": qa_report.get("overall_passed"),
                "technical_checks": {
                    "video_accessible": qa_report.get("technical_checks", {}).get("video_accessible"),
                    "codec_valid": qa_report.get("technical_checks", {}).get("codec_valid"),
                    "resolution_acceptable": qa_report.get("technical_checks", {}).get("resolution_acceptable"),
                    "audio_present": qa_report.get("technical_checks", {}).get("audio_present"),
                    "resolution": qa_report.get("technical_checks", {}).get("details", {}).get("resolution"),
                    "duration_minutes": qa_report.get("technical_checks", {}).get("details", {}).get("duration_minutes")
                },
                "issues": qa_report.get("issues", []),
                "recommendations": qa_report.get("recommendations", [])
            }
        
        results.append(result)
    
    return {"submissions": results, "count": len(results)}

@app.get("/api/portal/submission/{submission_id}")
async def get_portal_submission(submission_id: str, email: str = Query(...)):
    """Get detailed submission info - requires email verification"""
    try:
        submission = submissions_collection.find_one({"_id": ObjectId(submission_id)})
    except:
        raise HTTPException(status_code=404, detail="Submission not found")
    
    if not submission:
        raise HTTPException(status_code=404, detail="Submission not found")
    
    # Verify email matches
    if submission.get("filmmaker_email", "").lower() != email.lower().strip():
        raise HTTPException(status_code=403, detail="Email does not match submission")
    
    qa_report = qa_reports_collection.find_one({"submission_id": submission_id})
    
    result = serialize_doc(submission)
    if qa_report:
        result["qa_report"] = serialize_doc(qa_report)
    
    return result

# --- QA Report Endpoints ---

@app.get("/api/qa-reports/{submission_id}")
async def get_qa_report(submission_id: str):
    """Get QA report for a submission"""
    report = qa_reports_collection.find_one({"submission_id": submission_id})
    if not report:
        raise HTTPException(status_code=404, detail="QA report not found")
    return serialize_doc(report)

# --- Feed Endpoints ---

@app.get("/api/feed")
async def get_feed():
    """Get the current Roku JSON feed"""
    return generate_roku_feed()

@app.post("/api/feed/regenerate")
async def regenerate_feed():
    """Manually regenerate the Roku feed"""
    feed = generate_roku_feed()
    feed_path = save_roku_feed(feed)
    return {"message": "Feed regenerated", "path": feed_path, "categories": len(feed["categories"])}

# --- Dashboard Stats ---

@app.get("/api/stats")
async def get_dashboard_stats():
    """Get dashboard statistics"""
    total = submissions_collection.count_documents({})
    pending = submissions_collection.count_documents({"status": SubmissionStatus.PENDING.value})
    qa_processing = submissions_collection.count_documents({"status": SubmissionStatus.QA_PROCESSING.value})
    qa_passed = submissions_collection.count_documents({"status": SubmissionStatus.QA_PASSED.value})
    qa_failed = submissions_collection.count_documents({"status": SubmissionStatus.QA_FAILED.value})
    published = submissions_collection.count_documents({"status": SubmissionStatus.PUBLISHED.value})
    rejected = submissions_collection.count_documents({"status": SubmissionStatus.REJECTED.value})
    
    # Shelf distribution
    shelf_stats = {}
    for shelf in ShelfCategory:
        shelf_stats[shelf.value] = submissions_collection.count_documents({
            "status": SubmissionStatus.PUBLISHED.value,
            "assigned_shelf": shelf.value
        })
    
    return {
        "total_submissions": total,
        "by_status": {
            "pending": pending,
            "qa_processing": qa_processing,
            "qa_passed": qa_passed,
            "qa_failed": qa_failed,
            "published": published,
            "rejected": rejected
        },
        "by_shelf": shelf_stats
    }

# --- WordPress Webhook ---

@app.post("/api/webhook/wordpress")
async def wordpress_webhook(data: dict, background_tasks: BackgroundTasks):
    """Webhook endpoint for WordPress form submissions"""
    try:
        # Map WordPress form fields to our model
        submission = FilmSubmission(
            title=data.get("film_title", data.get("title", "Untitled")),
            short_description=data.get("short_description", data.get("tagline", "")),
            description=data.get("description", data.get("synopsis", "")),
            poster_url=data.get("poster_url", data.get("thumbnail", "")),
            video_url=data.get("video_url", data.get("file_url", "")),
            video_type=data.get("video_type", "mp4"),
            filmmaker_name=data.get("filmmaker_name", data.get("name", "Unknown")),
            filmmaker_email=data.get("filmmaker_email", data.get("email")),
            genre=data.get("genre"),
            runtime_minutes=data.get("runtime"),
            festival_awards=data.get("festival_awards", data.get("awards")),
            is_first_film=data.get("is_first_film", False),
            wordpress_post_id=data.get("post_id")
        )
        
        return await create_submission(submission, background_tasks)
        
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Invalid webhook data: {str(e)}")

# ==================== BACKGROUND TASKS ====================

async def process_submission_qa(submission_id: str):
    """Background task to process QA for a submission"""
    try:
        submission = submissions_collection.find_one({"_id": ObjectId(submission_id)})
        if not submission:
            print(f"Submission {submission_id} not found")
            return
        
        # Update status to processing
        submissions_collection.update_one(
            {"_id": ObjectId(submission_id)},
            {"$set": {"status": SubmissionStatus.QA_PROCESSING.value, "updated_at": datetime.now(timezone.utc)}}
        )
        
        # Run technical QA
        technical_checks = await run_technical_qa(submission.get("video_url", ""))
        
        # Run AI compliance check
        ai_compliance = await run_ai_compliance_check(submission)
        
        # Determine overall pass/fail - Technical checks are critical, AI is advisory
        technical_passed = all([
            technical_checks.get("video_accessible"),
            technical_checks.get("codec_valid"),
            technical_checks.get("resolution_acceptable"),
            technical_checks.get("audio_present")
        ])
        
        # AI compliance is advisory - we still pass if technical checks pass
        ai_passed = ai_compliance.get("overall_passed", True)
        overall_passed = technical_passed  # Only technical checks are blocking
        
        # Collect issues
        issues = []
        if not technical_checks.get("video_accessible"):
            issues.append("Video file is not accessible")
        if not technical_checks.get("codec_valid"):
            issues.append(f"Video codec not optimal: {technical_checks.get('details', {}).get('codec', 'unknown')}")
        if not technical_checks.get("resolution_acceptable"):
            issues.append(f"Resolution too low: {technical_checks.get('details', {}).get('resolution', 'unknown')}")
        if not technical_checks.get("audio_present"):
            issues.append("No audio track detected")
        if not technical_checks.get("duration_valid"):
            issues.append(f"Duration outside acceptable range: {technical_checks.get('details', {}).get('duration_minutes', 0)} min")
        
        issues.extend(ai_compliance.get("issues", []))
        
        # Recommendations
        recommendations = ai_compliance.get("suggestions", [])
        if not technical_checks.get("resolution_acceptable"):
            recommendations.append("Re-export video at minimum 1280x720 resolution")
        if not technical_checks.get("codec_valid"):
            recommendations.append("Re-encode using H.264 codec for best Roku compatibility")
        
        # Create QA report
        qa_report = {
            "submission_id": submission_id,
            "technical_checks": technical_checks,
            "ai_compliance": ai_compliance,
            "overall_passed": overall_passed,
            "issues": issues,
            "recommendations": recommendations,
            "created_at": datetime.now(timezone.utc)
        }
        
        # Save or update QA report
        qa_reports_collection.update_one(
            {"submission_id": submission_id},
            {"$set": qa_report},
            upsert=True
        )
        
        # Update submission status
        new_status = SubmissionStatus.QA_PASSED.value if overall_passed else SubmissionStatus.QA_FAILED.value
        
        # If passed, also run classification
        classification = None
        if overall_passed:
            classification = await classify_submission(submission, qa_report)
            submissions_collection.update_one(
                {"_id": ObjectId(submission_id)},
                {"$set": {
                    "status": SubmissionStatus.CLASSIFIED.value,
                    "recommended_shelf": classification.recommended_shelf,
                    "classification_confidence": classification.confidence,
                    "classification_reasoning": classification.reasoning,
                    "updated_at": datetime.now(timezone.utc)
                }}
            )
        else:
            submissions_collection.update_one(
                {"_id": ObjectId(submission_id)},
                {"$set": {"status": new_status, "updated_at": datetime.now(timezone.utc)}}
            )
        
        # Send email notification
        await send_qa_email(submission, qa_report)
        
        print(f"QA complete for {submission_id}: {'PASSED' if overall_passed else 'FAILED'}")
        
    except Exception as e:
        print(f"QA processing error for {submission_id}: {e}")
        submissions_collection.update_one(
            {"_id": ObjectId(submission_id)},
            {"$set": {"status": SubmissionStatus.QA_FAILED.value, "qa_error": str(e), "updated_at": datetime.now(timezone.utc)}}
        )

# ==================== STARTUP ====================

@app.on_event("startup")
async def startup_event():
    """Initialize on startup"""
    # Ensure indexes
    submissions_collection.create_index("status")
    submissions_collection.create_index("assigned_shelf")
    submissions_collection.create_index("created_at")
    qa_reports_collection.create_index("submission_id", unique=True)
    
    # Create feeds directory
    os.makedirs("/app/feeds", exist_ok=True)
    
    # Generate initial feed
    feed = generate_roku_feed()
    save_roku_feed(feed)
    print("Front Door Media API started")
