#!/usr/bin/env python3
"""
Portal-specific API Testing for Front Door Media
Tests the filmmaker portal endpoints and email notification features
"""

import requests
import json
import sys
import time
from datetime import datetime
from typing import Dict, Any

class PortalAPITester:
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

    def test_portal_lookup_with_test_email(self):
        """Test portal lookup with test@example.com (should have submissions)"""
        success, data = self.run_test(
            "Portal Lookup - test@example.com", 
            "GET", 
            "portal/lookup", 
            200, 
            params={"email": "test@example.com"}
        )
        
        if success and data:
            submissions = data.get('submissions', [])
            count = data.get('count', 0)
            print(f"   📧 Found {count} submissions for test@example.com")
            
            if submissions:
                # Check first submission structure
                first_sub = submissions[0]
                self.test_submission_id = first_sub.get('id')
                print(f"   📄 First submission: {first_sub.get('title', 'Unknown')}")
                print(f"   📄 Status: {first_sub.get('status', 'Unknown')}")
                
                # Check if QA summary is present
                if first_sub.get('qa_summary'):
                    qa = first_sub['qa_summary']
                    print(f"   🔍 QA Summary present: {qa.get('overall_passed', 'Unknown')}")
                    tech_checks = qa.get('technical_checks', {})
                    print(f"   🔍 Video accessible: {tech_checks.get('video_accessible', 'Unknown')}")
                else:
                    print("   ⚠️  No QA summary found")
        
        return success, data

    def test_portal_lookup_with_empty_email(self):
        """Test portal lookup with non-existent email"""
        success, data = self.run_test(
            "Portal Lookup - empty@nonexistent.com", 
            "GET", 
            "portal/lookup", 
            200, 
            params={"email": "empty@nonexistent.com"}
        )
        
        if success and data:
            count = data.get('count', 0)
            print(f"   📧 Found {count} submissions for empty email (should be 0)")
        
        return success, data

    def test_portal_lookup_missing_email(self):
        """Test portal lookup without email parameter (should fail)"""
        success, data = self.run_test(
            "Portal Lookup - Missing Email Parameter", 
            "GET", 
            "portal/lookup", 
            422  # FastAPI validation error
        )
        return success, data

    def test_publish_submission_with_email_check(self):
        """Test publish submission and check for email notification in logs"""
        if not self.test_submission_id:
            return self.log_test("Publish with Email Check", False, "No submission ID available")
        
        print(f"   📧 Testing publish for submission ID: {self.test_submission_id}")
        
        success, data = self.run_test(
            "Publish Submission (Email Check)", 
            "POST", 
            f"submissions/{self.test_submission_id}/publish?shelf=Buyer%3A%20Festival%20Winners%20%26%20Official%20Selections", 
            200
        )
        
        if success:
            print("   📧 Publish successful - email should be triggered (check backend logs)")
            print("   📧 Note: Email sending depends on SendGrid configuration")
        
        return success, data

    def test_reject_submission_with_email_check(self):
        """Test reject submission and check for email notification in logs"""
        # First create a new submission to reject
        test_submission = {
            "title": f"Test Rejection Film {datetime.now().strftime('%H%M%S')}",
            "short_description": "A test film for rejection testing",
            "description": "This film will be rejected to test the email notification system.",
            "poster_url": "https://via.placeholder.com/400x600/333/fff?text=Reject+Test",
            "video_url": "https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4",
            "video_type": "mp4",
            "filmmaker_name": "Rejection Tester",
            "filmmaker_email": "test@example.com",
            "genre": "Test",
            "is_first_film": False
        }
        
        # Create submission
        success, create_data = self.run_test(
            "Create Submission for Rejection Test", 
            "POST", 
            "submissions", 
            200, 
            data=test_submission
        )
        
        if not success:
            return False, {}
        
        reject_id = create_data.get('id')
        if not reject_id:
            return self.log_test("Reject with Email Check", False, "No submission ID from creation")
        
        print(f"   📧 Testing reject for submission ID: {reject_id}")
        
        # Wait a moment for creation to complete
        time.sleep(1)
        
        # Now reject it
        success, data = self.run_test(
            "Reject Submission (Email Check)", 
            "POST", 
            f"submissions/{reject_id}/reject", 
            200
        )
        
        if success:
            print("   📧 Reject successful - email should be triggered (check backend logs)")
            print("   📧 Note: Email sending depends on SendGrid configuration")
        
        return success, data

    def test_portal_submission_detail(self):
        """Test getting detailed submission info via portal endpoint"""
        if not self.test_submission_id:
            return self.log_test("Portal Submission Detail", False, "No submission ID available")
        
        success, data = self.run_test(
            "Portal Submission Detail", 
            "GET", 
            f"portal/submission/{self.test_submission_id}", 
            200,
            params={"email": "test@example.com"}
        )
        
        if success and data:
            print(f"   📄 Title: {data.get('title', 'Unknown')}")
            print(f"   📄 Status: {data.get('status', 'Unknown')}")
            if data.get('qa_report'):
                print("   🔍 Full QA report included")
        
        return success, data

    def test_portal_submission_detail_wrong_email(self):
        """Test getting submission detail with wrong email (should fail)"""
        if not self.test_submission_id:
            return self.log_test("Portal Detail Wrong Email", False, "No submission ID available")
        
        success, data = self.run_test(
            "Portal Detail - Wrong Email", 
            "GET", 
            f"portal/submission/{self.test_submission_id}", 
            403,  # Should be forbidden
            params={"email": "wrong@example.com"}
        )
        return success, data

    def run_portal_tests(self):
        """Run portal-specific test suite"""
        print("🎬 Starting Front Door Media Portal Tests")
        print("=" * 60)
        
        # Test portal lookup functionality
        self.test_portal_lookup_with_test_email()
        self.test_portal_lookup_with_empty_email()
        self.test_portal_lookup_missing_email()
        
        # Test detailed submission access
        self.test_portal_submission_detail()
        self.test_portal_submission_detail_wrong_email()
        
        # Test email notifications (publish/reject)
        self.test_publish_submission_with_email_check()
        self.test_reject_submission_with_email_check()
        
        print("=" * 60)
        print(f"📊 Portal Test Results: {self.tests_passed}/{self.tests_run} passed")
        
        if self.tests_passed == self.tests_run:
            print("🎉 All portal tests passed!")
            return 0
        else:
            print(f"⚠️  {self.tests_run - self.tests_passed} portal tests failed")
            return 1

def main():
    """Main test runner"""
    tester = PortalAPITester()
    return tester.run_portal_tests()

if __name__ == "__main__":
    sys.exit(main())