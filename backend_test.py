#!/usr/bin/env python3
"""
Backend API Testing for Front Door Media Film Submission System
Tests all endpoints: submissions, QA reports, feed, stats, webhooks
"""

import requests
import json
import sys
from datetime import datetime
from typing import Dict, Any

class FrontDoorAPITester:
    def __init__(self, base_url="https://roku-image-fix.preview.emergentagent.com"):
        self.base_url = base_url
        self.tests_run = 0
        self.tests_passed = 0
        self.test_submission_id = None
        
    def log_test(self, name: str, success: bool, details: str = ""):
        """Log test result"""
        self.tests_run += 1
        if success:
            self.tests_passed += 1
            print(f"✅ {name} - PASSED {details}")
        else:
            print(f"❌ {name} - FAILED {details}")
        return success

    def run_test(self, name: str, method: str, endpoint: str, expected_status: int, 
                 data: Dict = None, params: Dict = None) -> tuple[bool, Dict]:
        """Run a single API test"""
        url = f"{self.base_url}/api/{endpoint}"
        headers = {'Content-Type': 'application/json'}
        
        try:
            if method == 'GET':
                response = requests.get(url, headers=headers, params=params, timeout=30)
            elif method == 'POST':
                response = requests.post(url, json=data, headers=headers, timeout=30)
            elif method == 'PUT':
                response = requests.put(url, json=data, headers=headers, timeout=30)
            elif method == 'DELETE':
                response = requests.delete(url, headers=headers, timeout=30)
            
            success = response.status_code == expected_status
            response_data = {}
            
            try:
                response_data = response.json()
            except:
                response_data = {"raw_response": response.text}
            
            details = f"Status: {response.status_code}"
            if not success:
                details += f" (Expected: {expected_status})"
                if response.text:
                    details += f" Response: {response.text[:200]}"
            
            return self.log_test(name, success, details), response_data
            
        except requests.exceptions.Timeout:
            return self.log_test(name, False, "Request timeout"), {}
        except Exception as e:
            return self.log_test(name, False, f"Error: {str(e)}"), {}

    def test_health_check(self):
        """Test health endpoint"""
        return self.run_test("Health Check", "GET", "health", 200)

    def test_get_stats(self):
        """Test dashboard stats endpoint"""
        success, data = self.run_test("Get Dashboard Stats", "GET", "stats", 200)
        if success and data:
            print(f"   📊 Total submissions: {data.get('total_submissions', 0)}")
            print(f"   📊 Published: {data.get('by_status', {}).get('published', 0)}")
        return success, data

    def test_list_submissions(self):
        """Test list submissions endpoint"""
        success, data = self.run_test("List All Submissions", "GET", "submissions", 200)
        if success and isinstance(data, list):
            print(f"   📋 Found {len(data)} submissions")
            if data:
                # Store first submission ID for later tests
                self.test_submission_id = data[0].get('id')
                print(f"   📋 First submission: {data[0].get('title', 'Unknown')}")
        return success, data

    def test_list_submissions_with_filter(self):
        """Test list submissions with status filter"""
        return self.run_test("List Submissions (Published Filter)", "GET", "submissions", 200, 
                           params={"status": "published"})

    def test_get_single_submission(self):
        """Test get single submission"""
        if not self.test_submission_id:
            return self.log_test("Get Single Submission", False, "No submission ID available")
        
        success, data = self.run_test("Get Single Submission", "GET", 
                                    f"submissions/{self.test_submission_id}", 200)
        if success and data:
            print(f"   📄 Title: {data.get('title', 'Unknown')}")
            print(f"   📄 Status: {data.get('status', 'Unknown')}")
        return success, data

    def test_create_submission(self):
        """Test create new submission"""
        test_submission = {
            "title": f"Test Film {datetime.now().strftime('%H%M%S')}",
            "short_description": "A test film for API testing",
            "description": "This is a comprehensive test film created by the automated testing system to verify the submission pipeline works correctly.",
            "poster_url": "https://via.placeholder.com/400x600/333/fff?text=Test+Poster",
            "video_url": "https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4",
            "video_type": "mp4",
            "filmmaker_name": "Test Filmmaker",
            "filmmaker_email": "test@example.com",
            "genre": "Drama",
            "festival_awards": "Test Festival Winner 2024",
            "is_first_film": False
        }
        
        success, data = self.run_test("Create New Submission", "POST", "submissions", 200, 
                                    data=test_submission)
        if success and data:
            new_id = data.get('id')
            if new_id:
                self.test_submission_id = new_id
                print(f"   🆕 Created submission ID: {new_id}")
        return success, data

    def test_reprocess_qa(self):
        """Test reprocess QA endpoint"""
        if not self.test_submission_id:
            return self.log_test("Reprocess QA", False, "No submission ID available")
        
        return self.run_test("Reprocess QA", "POST", 
                           f"submissions/{self.test_submission_id}/reprocess", 200)

    def test_publish_submission(self):
        """Test publish submission endpoint"""
        if not self.test_submission_id:
            return self.log_test("Publish Submission", False, "No submission ID available")
        
        # Use query parameter for shelf
        success, data = self.run_test("Publish Submission", "POST", 
                                    f"submissions/{self.test_submission_id}/publish?shelf=Buyer%3A%20Festival%20Winners%20%26%20Official%20Selections", 
                                    200)
        return success, data

    def test_reject_submission(self):
        """Test reject submission endpoint"""
        if not self.test_submission_id:
            return self.log_test("Reject Submission", False, "No submission ID available")
        
        return self.run_test("Reject Submission", "POST", 
                           f"submissions/{self.test_submission_id}/reject", 200)

    def test_get_qa_report(self):
        """Test get QA report endpoint"""
        if not self.test_submission_id:
            return self.log_test("Get QA Report", False, "No submission ID available")
        
        success, data = self.run_test("Get QA Report", "GET", 
                                    f"qa-reports/{self.test_submission_id}", 200)
        if success and data:
            print(f"   📋 QA Status: {'PASSED' if data.get('overall_passed') else 'FAILED'}")
        return success, data

    def test_get_feed(self):
        """Test get Roku feed endpoint"""
        success, data = self.run_test("Get Roku Feed", "GET", "feed", 200)
        if success and data:
            categories = data.get('categories', [])
            print(f"   📺 Feed has {len(categories)} categories")
            for cat in categories[:3]:  # Show first 3 categories
                items = len(cat.get('items', []))
                print(f"   📺 {cat.get('title', 'Unknown')}: {items} items")
        return success, data

    def test_regenerate_feed(self):
        """Test regenerate feed endpoint"""
        success, data = self.run_test("Regenerate Feed", "POST", "feed/regenerate", 200)
        if success and data:
            print(f"   🔄 Categories: {data.get('categories', 0)}")
        return success, data

    def test_wordpress_webhook(self):
        """Test WordPress webhook endpoint"""
        webhook_data = {
            "film_title": f"Webhook Test {datetime.now().strftime('%H%M%S')}",
            "short_description": "Test via webhook",
            "description": "This film was submitted via WordPress webhook for testing",
            "poster_url": "https://via.placeholder.com/400x600/666/fff?text=Webhook+Test",
            "video_url": "https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4",
            "filmmaker_name": "Webhook Tester",
            "email": "webhook@example.com",
            "genre": "Documentary",
            "post_id": "wp_12345"
        }
        
        return self.run_test("WordPress Webhook", "POST", "webhook/wordpress", 200, 
                           data=webhook_data)

    def run_all_tests(self):
        """Run comprehensive test suite"""
        print("🚀 Starting Front Door Media API Tests")
        print("=" * 60)
        
        # Basic connectivity
        self.test_health_check()
        
        # Dashboard and stats
        self.test_get_stats()
        
        # Submission management
        self.test_list_submissions()
        self.test_list_submissions_with_filter()
        self.test_get_single_submission()
        
        # Create new submission for testing
        self.test_create_submission()
        
        # Wait a moment for QA processing to potentially start
        import time
        print("⏳ Waiting 3 seconds for QA processing...")
        time.sleep(3)
        
        # Test submission operations
        self.test_reprocess_qa()
        self.test_get_qa_report()
        
        # Test publishing workflow
        self.test_publish_submission()
        
        # Feed operations
        self.test_get_feed()
        self.test_regenerate_feed()
        
        # Webhook
        self.test_wordpress_webhook()
        
        # Final rejection test (on a different submission to avoid conflicts)
        # self.test_reject_submission()  # Skip to avoid rejecting our test submission
        
        print("=" * 60)
        print(f"📊 Test Results: {self.tests_passed}/{self.tests_run} passed")
        
        if self.tests_passed == self.tests_run:
            print("🎉 All tests passed!")
            return 0
        else:
            print(f"⚠️  {self.tests_run - self.tests_passed} tests failed")
            return 1

def main():
    """Main test runner"""
    tester = FrontDoorAPITester()
    return tester.run_all_tests()

if __name__ == "__main__":
    sys.exit(main())