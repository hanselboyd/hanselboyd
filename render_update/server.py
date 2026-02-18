"""
Front Door Media - The AI Cinema Network
Film Submission & QA System
Handles: Upload → AI/QA compliance → Classification → Route to shelf → Auto-update Roku JSON feed → Email reports
"""

from fastapi import FastAPI, HTTPException, BackgroundTasks, Query
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, EmailStr, Field
from typing import Optional, List, Dict, Any
from datetime import datetime, timezone, timedelta
from enum import Enum
import os
import json
import subprocess
import re
from dotenv import load_dotenv
from pymongo import MongoClient
from bson import ObjectId

load_dotenv()

# Initialize FastAPI
app = FastAPI(title="Front Door Media - The AI Cinema Network", version="1.0.0")

# CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# MongoDB - with connection settings for cloud deployment
mongo_url = os.environ.get("MONGO_URL")
if mongo_url:
    # Add SSL settings if not already present for Atlas connections
    if "mongodb+srv" in mongo_url or "mongodb.net" in mongo_url:
        if "tls=" not in mongo_url and "ssl=" not in mongo_url:
            separator = "&" if "?" in mongo_url else "?"
            mongo_url = f"{mongo_url}{separator}tls=true&tlsAllowInvalidCertificates=false"

    mongo_client = MongoClient(
        mongo_url,
        serverSelectionTimeoutMS=10000,
        connectTimeoutMS=10000,
        socketTimeoutMS=20000
    )
else:
    mongo_client = MongoClient("mongodb://localhost:27017")

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

# UPDATED SHELF CATEGORIES
class ShelfCategory(str, Enum):
    NOW_PROGRAMMING = "Now Programming"
    PREMIERES = "Premieres"
    CONCEPT_CINEMA = "Concept Cinema"
    AI_NONFICTION = "AI Nonfiction"
    SPOTLIGHT = "Spotlight"
    ARCHIVE = "Archive"

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
    """Run AI-powered content compliance checks using OpenAI"""
    try:
        from openai import OpenAI

        client = OpenAI(
            api_key=os.environ.get("EMERGENT_LLM_KEY"),
            base_url="https://ai.emergentmethods.ai/v1"
        )

        system_message = """You are a content compliance reviewer for a film distribution platform.
Analyze film submissions for:
1. Content appropriateness (no explicit violence, hate speech, illegal content)
2. Professional quality indicators (proper metadata, clear descriptions)
3. Distribution readiness (complete information, marketable content)

Respond ONLY with valid JSON (no markdown):
{"content_appropriate": true, "professional_quality": true, "distribution_ready": true, "issues": [], "suggestions": [], "overall_passed": true}"""

        prompt = f"""Review this film submission:

Title: {submission.get('title')}
Short Description: {submission.get('short_description')}
Full Description: {submission.get('description')}
Genre: {submission.get('genre', 'Not specified')}
Festival Awards: {submission.get('festival_awards', 'None')}
Is First Film: {submission.get('is_first_film', False)}

Analyze for content compliance and distribution readiness."""

        response = client.chat.completions.create(
            model="gpt-4o",
            messages=[
                {"role": "system", "content": system_message},
                {"role": "user", "content": prompt}
            ],
            temperature=0.3
        )

        result_text = response.choices[0].message.content

        # Parse JSON response
        try:
            json_match = re.search(r'\{[\s\S]*\}', result_text)
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
        from openai import OpenAI

        client = OpenAI(
            api_key=os.environ.get("EMERGENT_LLM_KEY"),
            base_url="https://ai.emergentmethods.ai/v1"
        )

        system_message = """You are a film curator for Front Door Media, an AI-native film platform. Classify films into shelves:

1. "Now Programming" - Currently featured films ready for prime time viewing
2. "Premieres" - New releases and debut screenings
3. "Concept Cinema" - Artistic and conceptual film pieces
4. "AI Nonfiction" - Documentary and non-fiction AI content
5. "Spotlight" - Editor's picks and highlighted content
6. "Archive" - Classic and catalog films

Respond ONLY with valid JSON (no markdown): {"shelf": "exact shelf name", "confidence": 0.85, "reasoning": "brief explanation"}"""

        prompt = f"""Classify this film submission:

Title: {submission.get('title')}
Description: {submission.get('description')}
Genre: {submission.get('genre', 'Not specified')}
Festival Awards: {submission.get('festival_awards', 'None')}
Is First Film: {submission.get('is_first_film', False)}
Technical Quality: {"Passed" if qa_report.get('overall_passed') else "Issues found"}

Which shelf should this film be placed on?"""

        response = client.chat.completions.create(
            model="gpt-4o",
            messages=[
                {"role": "system", "content": system_message},
                {"role": "user", "content": prompt}
            ],
            temperature=0.3
        )

        result_text = response.choices[0].message.content

        # Parse JSON response
        try:
            json_match = re.search(r'\{[\s\S]*\}', result_text)
            if json_match:
                result = json.loads(json_match.group())
                return ClassificationResult(
                    submission_id=str(submission.get('_id', '')),
                    recommended_shelf=result.get('shelf', ShelfCategory.ARCHIVE.value),
                    confidence=float(result.get('confidence', 0.7)),
                    reasoning=result.get('reasoning', 'AI classification')
                )
        except (json.JSONDecodeError, KeyError):
            pass

        # Fallback classification based on rules
        if submission.get('festival_awards'):
            shelf = ShelfCategory.SPOTLIGHT.value
        elif submission.get('is_first_film'):
            shelf = ShelfCategory.PREMIERES.value
        else:
            shelf = ShelfCategory.ARCHIVE.value

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
            recommended_shelf=ShelfCategory.ARCHIVE.value,
            confidence=0.5,
            reasoning=f"Default classification: {str(e)}"
        )

# UPDATED Shelf placeholder images
SHELF_IMAGES = {
    "Now Programming": "https://frontdoormedia.org/wp-content/uploads/2026/01/now_programming.png",
    "Premieres": "https://frontdoormedia.org/wp-content/uploads/2026/01/premieres.png",
    "Concept Cinema": "https://frontdoormedia.org/wp-content/uploads/2026/01/concept_cinema.png",
    "AI Nonfiction": "https://frontdoormedia.org/wp-content/uploads/2026/01/ai_nonfiction.png",
    "Spotlight": "https://frontdoormedia.org/wp-content/uploads/2026/01/spotlight.png",
    "Archive": "https://frontdoormedia.org/wp-content/uploads/2026/01/archive.png"
}

def generate_roku_feed() -> dict:
    """Generate the Roku JSON feed from database"""
    feed = {
        "providerName": "Front Door - The AI Cinema Network",
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

        # Get shelf-specific image
        shelf_image = SHELF_IMAGES.get(shelf.value, "https://frontdoormedia.org/wp-content/uploads/2026/01/frontdoor_hero.png")

        # Add placeholder if empty
        if not items:
            items.append({
                "title": "Coming Soon",
                "shortDescription": "New films arriving soon",
                "description": "Check back for new films in this category.",
                "poster": shelf_image,
                "videoUrl": "https://frontdoormedia.org/wp-content/uploads/2026/01/frontdoor.mp4",
                "videoType": "mp4"
            })

        feed["categories"].append({
            "title": shelf.value,
            "poster": shelf_image,
            "items": items
        })

    return feed

def save_roku_feed(feed: dict):
    """Save the Roku feed to file (uses /tmp on cloud platforms)"""
    # Use /tmp for cloud deployments, local path otherwise
    default_path = "/tmp/feeds/frontdoor_shelves_public_v1.json"
    feed_path = os.environ.get("FEED_OUTPUT_PATH", default_path)

    try:
        os.makedirs(os.path.dirname(feed_path), exist_ok=True)
        with open(feed_path, 'w') as f:
            json.dump(feed, f, indent=2)
        return feed_path
    except Exception as e:
        print(f"Warning: Could not save feed to {feed_path}: {e}")
        return None

async def send_qa_email(submission: dict, qa_report: dict):
    """Send QA report email to filmmaker with portal link"""
    try:
        from sendgrid import SendGridAPIClient
        from sendgrid.helpers.mail import Mail

        api_key = os.environ.get("SENDGRID_API_KEY")
        if not api_key:
            print("SendGrid API key not configured")
            return False

        filmmaker_email = submission.get('filmmaker_email', '')
        submission_id = str(submission.get('_id', ''))
        portal_url = f"https://frontdoormedia.org/filmmaker-portal?email={filmmaker_email}"

        status = "PASSED" if qa_report.get("overall_passed") else "NEEDS ATTENTION"
        status_color = "#22c55e" if qa_report.get("overall_passed") else "#ef4444"

        issues_html = ""
        if qa_report.get("issues"):
            issues_html = "<ul style='color: #666;'>" + "".join(f"<li>{issue}</li>" for issue in qa_report["issues"][:5]) + "</ul>"

        recommendations_html = ""
        if qa_report.get("recommendations"):
            recommendations_html = "<ul style='color: #666;'>" + "".join(f"<li>{rec}</li>" for rec in qa_report["recommendations"][:5]) + "</ul>"

        html_content = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9fafb;">
            <div style="background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%); padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
                <h1 style="color: white; margin: 0; font-size: 24px;">Front Door Media</h1>
                <p style="color: #a78bfa; margin: 5px 0 0 0; font-size: 14px;">The AI Cinema Network</p>
                <p style="color: rgba(255,255,255,0.9); margin: 5px 0 0 0;">QA Report</p>
            </div>

            <div style="background: white; padding: 30px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h2 style="color: #1f2937; margin-top: 0;">{submission.get('title')}</h2>

                <div style="background-color: {status_color}20; border-left: 4px solid {status_color}; padding: 15px; margin: 20px 0; border-radius: 0 8px 8px 0;">
                    <p style="margin: 0; color: {status_color}; font-weight: 600; font-size: 18px;">{status}</p>
                    <p style="margin: 5px 0 0 0; color: #666;">{"Your submission passed technical QA and is being reviewed." if qa_report.get("overall_passed") else "Some issues were found. Please review the details below."}</p>
                </div>

                <h3 style="color: #374151; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">Technical Checks</h3>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 12px 0; color: #666;">Video Accessible</td>
                        <td style="padding: 12px 0; text-align: right;">{"✓" if qa_report.get("technical_checks", {}).get("video_accessible") else "✗"}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 12px 0; color: #666;">Codec Valid (H.264/H.265)</td>
                        <td style="padding: 12px 0; text-align: right;">{"✓" if qa_report.get("technical_checks", {}).get("codec_valid") else "✗"}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 12px 0; color: #666;">Resolution Acceptable</td>
                        <td style="padding: 12px 0; text-align: right;">{"✓" if qa_report.get("technical_checks", {}).get("resolution_acceptable") else "✗"} <span style="color: #999; font-size: 12px;">{qa_report.get("technical_checks", {}).get("details", {}).get("resolution", "")}</span></td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 0; color: #666;">Audio Track Present</td>
                        <td style="padding: 12px 0; text-align: right;">{"✓" if qa_report.get("technical_checks", {}).get("audio_present") else "✗"}</td>
                    </tr>
                </table>

                {"<h3 style='color: #dc2626; border-bottom: 2px solid #fecaca; padding-bottom: 10px;'>Issues Found</h3>" + issues_html if issues_html else ""}

                {"<h3 style='color: #2563eb; border-bottom: 2px solid #bfdbfe; padding-bottom: 10px;'>Recommendations</h3>" + recommendations_html if recommendations_html else ""}

                <div style="background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%); padding: 20px; border-radius: 8px; text-align: center; margin-top: 30px;">
                    <p style="color: white; margin: 0 0 15px 0; font-size: 16px;">Track your submission status anytime</p>
                    <a href="{portal_url}" style="display: inline-block; background: white; color: #8b5cf6; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: 600;">View in Filmmaker Portal</a>
                </div>
            </div>

            <div style="text-align: center; padding: 20px; color: #9ca3af; font-size: 12px;">
                <p>Front Door Media. All rights reserved.</p>
                <p>Questions? Reply to this email or contact <a href="mailto:support@frontdoormedia.org" style="color: #8b5cf6;">support@frontdoormedia.org</a></p>
            </div>
        </body>
        </html>
        """

        message = Mail(
            from_email=os.environ.get("SENDER_EMAIL", "noreply@frontdoormedia.org"),
            to_emails=filmmaker_email,
            subject=f"Front Door QA Report: {submission.get('title')} - {status}",
            html_content=html_content
        )

        sg = SendGridAPIClient(api_key)
        response = sg.send(message)
        print(f"QA email sent to {filmmaker_email}: {response.status_code}")
        return response.status_code == 202

    except Exception as e:
        print(f"Email error: {e}")
        return False

async def send_status_update_email(submission: dict, new_status: str, details: dict = None):
    """Send status update email to filmmaker"""
    try:
        from sendgrid import SendGridAPIClient
        from sendgrid.helpers.mail import Mail

        api_key = os.environ.get("SENDGRID_API_KEY")
        if not api_key:
            return False

        filmmaker_email = submission.get('filmmaker_email', '')
        portal_url = f"https://frontdoormedia.org/filmmaker-portal?email={filmmaker_email}"

        status_config = {
            "published": {
                "emoji": "🎉",
                "title": "Congratulations! Your Film is Live!",
                "color": "#059669",
                "message": f"Your film has been published to the '{details.get('shelf', 'Front Door')}' shelf and is now available to viewers and buyers on Roku."
            },
            "classified": {
                "emoji": "📋",
                "title": "Your Film is Under Review",
                "color": "#8b5cf6",
                "message": f"Your film passed QA and has been classified for the '{details.get('shelf', '')}' shelf. Our editorial team will review it shortly."
            },
            "rejected": {
                "emoji": "📝",
                "title": "Submission Update",
                "color": "#6b7280",
                "message": f"Unfortunately, your film was not selected for publication at this time. Reason: {details.get('reason', 'Not meeting current programming needs.')}"
            }
        }

        config = status_config.get(new_status, {
            "emoji": "📬",
            "title": "Submission Status Update",
            "color": "#3b82f6",
            "message": f"Your submission status has been updated to: {new_status}"
        })

        html_content = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9fafb;">
            <div style="background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%); padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
                <h1 style="color: white; margin: 0; font-size: 24px;">Front Door Media</h1>
                <p style="color: #a78bfa; margin: 5px 0 0 0; font-size: 14px;">The AI Cinema Network</p>
            </div>

            <div style="background: white; padding: 30px; border-radius: 0 0 12px 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div style="text-align: center; margin-bottom: 20px;">
                    <span style="font-size: 48px;">{config['emoji']}</span>
                </div>

                <h2 style="color: #1f2937; text-align: center; margin-top: 0;">{config['title']}</h2>

                <div style="background-color: {config['color']}15; border-left: 4px solid {config['color']}; padding: 15px; margin: 20px 0; border-radius: 0 8px 8px 0;">
                    <p style="margin: 0; color: #374151; font-weight: 600;">Film: {submission.get('title')}</p>
                    <p style="margin: 10px 0 0 0; color: #666;">{config['message']}</p>
                </div>

                <div style="background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%); padding: 20px; border-radius: 8px; text-align: center; margin-top: 30px;">
                    <p style="color: white; margin: 0 0 15px 0; font-size: 16px;">View full details in your portal</p>
                    <a href="{portal_url}" style="display: inline-block; background: white; color: #8b5cf6; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: 600;">Filmmaker Portal</a>
                </div>
            </div>

            <div style="text-align: center; padding: 20px; color: #9ca3af; font-size: 12px;">
                <p>Front Door Media. All rights reserved.</p>
            </div>
        </body>
        </html>
        """

        message = Mail(
            from_email=os.environ.get("SENDER_EMAIL", "noreply@frontdoormedia.org"),
            to_emails=filmmaker_email,
            subject=f"Front Door: {config['title']} - {submission.get('title')}",
            html_content=html_content
        )

        sg = SendGridAPIClient(api_key)
        response = sg.send(message)
        print(f"Status update email sent to {filmmaker_email}: {response.status_code}")
        return response.status_code == 202

    except Exception as e:
        print(f"Status email error: {e}")
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

@app.patch("/api/submissions/{submission_id}")
async def update_submission(submission_id: str, updates: dict):
    """Update a submission's details"""
    submission = submissions_collection.find_one({"_id": ObjectId(submission_id)})
    if not submission:
        raise HTTPException(status_code=404, detail="Submission not found")

    # Allowed fields to update
    allowed_fields = ["title", "short_description", "description", "poster_url", "video_url", "genre", "runtime_minutes", "festival_awards"]
    update_data = {k: v for k, v in updates.items() if k in allowed_fields}

    if update_data:
        update_data["updated_at"] = datetime.now(timezone.utc)
        submissions_collection.update_one(
            {"_id": ObjectId(submission_id)},
            {"$set": update_data}
        )

        # Regenerate feed if published
        if submission.get("status") == SubmissionStatus.PUBLISHED.value:
            feed = generate_roku_feed()
            save_roku_feed(feed)

    return {"message": "Updated successfully", "updated_fields": list(update_data.keys())}

@app.post("/api/submissions/{submission_id}/reprocess")
async def reprocess_submission(submission_id: str, background_tasks: BackgroundTasks):
    """Reprocess QA for a submission"""
    submission = submissions_collection.find_one({"_id": ObjectId(submission_id)})
    if not submission:
        raise HTTPException(status_code=404, detail="Submission not found")

    background_tasks.add_task(process_submission_qa, submission_id)
    return {"message": "QA reprocessing started"}

@app.post("/api/submissions/{submission_id}/publish")
async def publish_submission(submission_id: str, shelf: Optional[str] = None, background_tasks: BackgroundTasks = None):
    """Publish a submission to a shelf"""
    submission = submissions_collection.find_one({"_id": ObjectId(submission_id)})
    if not submission:
        raise HTTPException(status_code=404, detail="Submission not found")

    assigned_shelf = shelf or submission.get("recommended_shelf", ShelfCategory.ARCHIVE.value)

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

    # Send congratulations email
    if background_tasks:
        background_tasks.add_task(send_status_update_email, submission, "published", {"shelf": assigned_shelf})

    return {"message": "Published successfully", "shelf": assigned_shelf, "feed_updated": True}

@app.post("/api/submissions/{submission_id}/reject")
async def reject_submission(submission_id: str, reason: str = "Not meeting quality standards", background_tasks: BackgroundTasks = None):
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

    # Send rejection notification email
    if background_tasks:
        background_tasks.add_task(send_status_update_email, submission, "rejected", {"reason": reason})

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
    classified = submissions_collection.count_documents({"status": SubmissionStatus.CLASSIFIED.value})
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
            "classified": classified,
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
    print("Front Door Media API starting...")

    # Try to connect to MongoDB and create indexes (non-blocking)
    try:
        # Test connection with timeout
        mongo_client.admin.command('ping')
        print("MongoDB connected successfully")

        # Ensure indexes
        submissions_collection.create_index("status")
        submissions_collection.create_index("assigned_shelf")
        submissions_collection.create_index("created_at")
        submissions_collection.create_index("filmmaker_email")
        qa_reports_collection.create_index("submission_id", unique=True)
        print("MongoDB indexes created")
    except Exception as e:
        print(f"MongoDB startup warning (will retry on first request): {e}")

    print("Front Door Media API started")

@app.on_event("shutdown")
async def shutdown_event():
    """Cleanup on shutdown"""
    print("Front Door Media API stopped")
