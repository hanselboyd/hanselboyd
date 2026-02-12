import React, { useState } from 'react';

const API_URL = process.env.REACT_APP_BACKEND_URL || '';

// Status display with colors and descriptions
const StatusDisplay = ({ status }) => {
  const statusConfig = {
    pending: { color: 'bg-yellow-500', label: 'Pending Review', desc: 'Your submission is in the queue' },
    qa_processing: { color: 'bg-blue-500', label: 'Processing', desc: 'QA checks are running' },
    qa_passed: { color: 'bg-green-500', label: 'QA Passed', desc: 'Technical checks passed' },
    qa_failed: { color: 'bg-red-500', label: 'Needs Attention', desc: 'Some issues were found' },
    classified: { color: 'bg-purple-500', label: 'Under Review', desc: 'Being reviewed for publication' },
    published: { color: 'bg-emerald-600', label: 'Published', desc: 'Live on Front Door!' },
    rejected: { color: 'bg-gray-500', label: 'Not Selected', desc: 'Not selected for publication' }
  };
  
  const config = statusConfig[status] || { color: 'bg-gray-400', label: status, desc: '' };
  
  return (
    <div className="flex items-center gap-3">
      <span className={`px-3 py-1 rounded-full text-sm font-medium text-white ${config.color}`}>
        {config.label}
      </span>
      <span className="text-zinc-400 text-sm">{config.desc}</span>
    </div>
  );
};

// QA Check Item
const QACheck = ({ passed, label, detail }) => (
  <div className="flex items-center justify-between py-2 border-b border-zinc-700 last:border-0">
    <div className="flex items-center gap-2">
      <span className={`text-lg ${passed ? 'text-green-400' : 'text-red-400'}`}>
        {passed ? '✓' : '✗'}
      </span>
      <span className="text-zinc-300">{label}</span>
    </div>
    {detail && <span className="text-zinc-500 text-sm">{detail}</span>}
  </div>
);

// Submission Card for Portal
const SubmissionCard = ({ submission, onViewDetails }) => {
  const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleDateString('en-US', { 
      year: 'numeric', month: 'short', day: 'numeric' 
    });
  };

  return (
    <div className="bg-zinc-800 border border-zinc-700 rounded-xl overflow-hidden hover:border-violet-500/30 transition-all">
      {submission.poster_url && (
        <img 
          src={submission.poster_url} 
          alt={submission.title}
          className="w-full h-48 object-cover"
        />
      )}
      <div className="p-5">
        <h3 className="text-xl font-semibold text-white mb-2">{submission.title}</h3>
        <StatusDisplay status={submission.status} />
        
        <div className="mt-4 space-y-2 text-sm">
          <div className="flex justify-between text-zinc-400">
            <span>Submitted</span>
            <span>{formatDate(submission.created_at)}</span>
          </div>
          <div className="flex justify-between text-zinc-400">
            <span>Last Updated</span>
            <span>{formatDate(submission.updated_at)}</span>
          </div>
          {submission.assigned_shelf && (
            <div className="flex justify-between text-emerald-400">
              <span>Shelf</span>
              <span className="text-right max-w-[200px]">{submission.assigned_shelf}</span>
            </div>
          )}
          {submission.recommended_shelf && !submission.assigned_shelf && (
            <div className="flex justify-between text-violet-400">
              <span>Recommended</span>
              <span className="text-right max-w-[200px]">{submission.recommended_shelf}</span>
            </div>
          )}
        </div>

        {submission.qa_summary && (
          <div className="mt-4 pt-4 border-t border-zinc-700">
            <h4 className="text-sm font-medium text-zinc-300 mb-2">QA Summary</h4>
            <div className="grid grid-cols-2 gap-2 text-xs">
              <QACheck passed={submission.qa_summary.technical_checks?.video_accessible} label="Video" />
              <QACheck passed={submission.qa_summary.technical_checks?.codec_valid} label="Codec" />
              <QACheck passed={submission.qa_summary.technical_checks?.resolution_acceptable} label="Resolution" />
              <QACheck passed={submission.qa_summary.technical_checks?.audio_present} label="Audio" />
            </div>
          </div>
        )}

        <button
          onClick={() => onViewDetails(submission)}
          className="w-full mt-4 px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg transition-colors"
        >
          View Full Report
        </button>
      </div>
    </div>
  );
};

// Detailed Report Modal
const DetailedReport = ({ submission, onClose }) => {
  if (!submission) return null;
  
  const qa = submission.qa_summary;

  return (
    <div className="fixed inset-0 bg-black/90 flex items-center justify-center z-50 p-4 overflow-y-auto" onClick={onClose}>
      <div className="bg-zinc-800 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto my-8" onClick={e => e.stopPropagation()}>
        {submission.poster_url && (
          <img 
            src={submission.poster_url} 
            alt={submission.title}
            className="w-full h-56 object-cover"
          />
        )}
        
        <div className="p-6">
          <div className="flex justify-between items-start mb-4">
            <h2 className="text-2xl font-bold text-white">{submission.title}</h2>
            <button onClick={onClose} className="text-zinc-400 hover:text-white text-2xl leading-none">&times;</button>
          </div>
          
          <StatusDisplay status={submission.status} />

          {submission.rejection_reason && (
            <div className="mt-4 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
              <p className="text-red-400 font-medium">Reason: {submission.rejection_reason}</p>
            </div>
          )}

          {submission.assigned_shelf && (
            <div className="mt-4 p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-lg">
              <p className="text-emerald-400">
                <span className="font-medium">Published to:</span> {submission.assigned_shelf}
              </p>
            </div>
          )}

          {qa && (
            <>
              <div className="mt-6">
                <h3 className="text-lg font-semibold text-white mb-3">Technical Quality</h3>
                <div className="bg-zinc-900 rounded-lg p-4">
                  <QACheck 
                    passed={qa.technical_checks?.video_accessible} 
                    label="Video Accessible" 
                  />
                  <QACheck 
                    passed={qa.technical_checks?.codec_valid} 
                    label="Codec Valid (H.264/H.265)" 
                  />
                  <QACheck 
                    passed={qa.technical_checks?.resolution_acceptable} 
                    label="Resolution Acceptable" 
                    detail={qa.technical_checks?.resolution}
                  />
                  <QACheck 
                    passed={qa.technical_checks?.audio_present} 
                    label="Audio Track Present" 
                  />
                  {qa.technical_checks?.duration_minutes && (
                    <div className="flex justify-between py-2 text-zinc-400">
                      <span>Duration</span>
                      <span>{qa.technical_checks.duration_minutes} minutes</span>
                    </div>
                  )}
                </div>
              </div>

              {qa.issues && qa.issues.length > 0 && (
                <div className="mt-6">
                  <h3 className="text-lg font-semibold text-red-400 mb-3">Issues Found</h3>
                  <ul className="bg-red-500/10 rounded-lg p-4 space-y-2">
                    {qa.issues.map((issue, i) => (
                      <li key={i} className="text-zinc-300 text-sm flex gap-2">
                        <span className="text-red-400">•</span>
                        {issue}
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              {qa.recommendations && qa.recommendations.length > 0 && (
                <div className="mt-6">
                  <h3 className="text-lg font-semibold text-blue-400 mb-3">Recommendations</h3>
                  <ul className="bg-blue-500/10 rounded-lg p-4 space-y-2">
                    {qa.recommendations.map((rec, i) => (
                      <li key={i} className="text-zinc-300 text-sm flex gap-2">
                        <span className="text-blue-400">→</span>
                        {rec}
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </>
          )}

          <div className="mt-6 pt-6 border-t border-zinc-700">
            <p className="text-zinc-500 text-sm text-center">
              Questions? Contact us at <a href="mailto:support@frontdoormedia.org" className="text-violet-400 hover:underline">support@frontdoormedia.org</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

// Main Portal Component
export default function FilmmakerPortal() {
  const [email, setEmail] = useState('');
  const [submissions, setSubmissions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);
  const [error, setError] = useState('');
  const [selectedSubmission, setSelectedSubmission] = useState(null);

  const handleLookup = async (e) => {
    e.preventDefault();
    if (!email.trim()) return;
    
    setLoading(true);
    setError('');
    setSearched(true);
    
    try {
      const res = await fetch(`${API_URL}/api/portal/lookup?email=${encodeURIComponent(email.trim())}`);
      if (res.ok) {
        const data = await res.json();
        setSubmissions(data.submissions);
      } else {
        setError('Failed to look up submissions');
      }
    } catch (err) {
      setError('Connection error. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-zinc-900" data-testid="filmmaker-portal">
      {/* Header */}
      <header className="bg-gradient-to-r from-violet-900/50 to-pink-900/50 border-b border-zinc-800">
        <div className="max-w-4xl mx-auto px-6 py-12 text-center">
          <h1 className="text-4xl font-bold bg-gradient-to-r from-violet-400 to-pink-400 bg-clip-text text-transparent mb-2">
            Front Door Media
          </h1>
          <p className="text-xl text-zinc-300">Filmmaker Portal</p>
          <p className="text-zinc-400 mt-2">Track your submission status and view QA feedback</p>
        </div>
      </header>

      <main className="max-w-4xl mx-auto px-6 py-8">
        {/* Lookup Form */}
        <div className="bg-zinc-800 border border-zinc-700 rounded-xl p-6 mb-8">
          <form onSubmit={handleLookup} className="flex flex-col sm:flex-row gap-4">
            <div className="flex-1">
              <label htmlFor="email" className="block text-zinc-400 text-sm mb-2">
                Enter your email address to find your submissions
              </label>
              <input
                id="email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="your@email.com"
                className="w-full bg-zinc-700 text-white rounded-lg px-4 py-3 border border-zinc-600 focus:border-violet-500 outline-none"
                data-testid="portal-email-input"
              />
            </div>
            <button
              type="submit"
              disabled={loading || !email.trim()}
              className="px-8 py-3 bg-violet-600 hover:bg-violet-700 disabled:bg-zinc-600 text-white font-medium rounded-lg transition-colors self-end"
              data-testid="portal-lookup-btn"
            >
              {loading ? 'Searching...' : 'Look Up'}
            </button>
          </form>
        </div>

        {/* Error Message */}
        {error && (
          <div className="bg-red-500/10 border border-red-500/30 rounded-lg p-4 mb-6 text-red-400">
            {error}
          </div>
        )}

        {/* Results */}
        {searched && !loading && (
          <>
            {submissions.length === 0 ? (
              <div className="text-center py-12">
                <div className="text-6xl mb-4">🎬</div>
                <h2 className="text-xl font-semibold text-white mb-2">No submissions found</h2>
                <p className="text-zinc-400">
                  We couldn't find any submissions associated with this email address.
                </p>
                <p className="text-zinc-500 mt-4 text-sm">
                  Make sure you're using the same email you submitted with.
                </p>
              </div>
            ) : (
              <>
                <div className="flex justify-between items-center mb-6">
                  <h2 className="text-xl font-semibold text-white">
                    Your Submissions ({submissions.length})
                  </h2>
                </div>
                <div className="grid md:grid-cols-2 gap-6">
                  {submissions.map(sub => (
                    <SubmissionCard 
                      key={sub.id} 
                      submission={sub}
                      onViewDetails={setSelectedSubmission}
                    />
                  ))}
                </div>
              </>
            )}
          </>
        )}

        {/* Initial State */}
        {!searched && (
          <div className="text-center py-12">
            <div className="text-6xl mb-4">🎥</div>
            <h2 className="text-xl font-semibold text-white mb-2">Check Your Submission Status</h2>
            <p className="text-zinc-400 max-w-md mx-auto">
              Enter the email address you used when submitting your film to see its current status, 
              QA feedback, and publication details.
            </p>
          </div>
        )}

        {/* FAQ Section */}
        <div className="mt-12 pt-8 border-t border-zinc-800">
          <h3 className="text-lg font-semibold text-white mb-4">Frequently Asked Questions</h3>
          <div className="space-y-4">
            <details className="bg-zinc-800 rounded-lg p-4 cursor-pointer">
              <summary className="text-zinc-300 font-medium">What do the statuses mean?</summary>
              <div className="mt-3 text-zinc-400 text-sm space-y-2">
                <p><strong className="text-yellow-400">Pending:</strong> Your submission is in the review queue.</p>
                <p><strong className="text-blue-400">Processing:</strong> QA checks are currently running.</p>
                <p><strong className="text-green-400">QA Passed:</strong> Technical checks passed successfully.</p>
                <p><strong className="text-red-400">Needs Attention:</strong> Some issues were found - check the QA report.</p>
                <p><strong className="text-purple-400">Under Review:</strong> Being considered for a specific shelf.</p>
                <p><strong className="text-emerald-400">Published:</strong> Your film is live on Front Door!</p>
              </div>
            </details>
            <details className="bg-zinc-800 rounded-lg p-4 cursor-pointer">
              <summary className="text-zinc-300 font-medium">How long does review take?</summary>
              <p className="mt-3 text-zinc-400 text-sm">
                QA processing is automatic and typically completes within minutes. Editorial review for 
                publication may take 1-2 weeks depending on submission volume.
              </p>
            </details>
            <details className="bg-zinc-800 rounded-lg p-4 cursor-pointer">
              <summary className="text-zinc-300 font-medium">What if my QA failed?</summary>
              <p className="mt-3 text-zinc-400 text-sm">
                Check the detailed QA report for specific issues and recommendations. Common fixes include 
                re-encoding with H.264 codec, ensuring minimum 480p resolution, and including an audio track.
              </p>
            </details>
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="border-t border-zinc-800 mt-12 py-6 text-center text-zinc-500 text-sm">
        <p>© 2026 Front Door Media. All rights reserved.</p>
        <p className="mt-1">
          Questions? <a href="mailto:support@frontdoormedia.org" className="text-violet-400 hover:underline">support@frontdoormedia.org</a>
        </p>
      </footer>

      {/* Detail Modal */}
      {selectedSubmission && (
        <DetailedReport 
          submission={selectedSubmission} 
          onClose={() => setSelectedSubmission(null)} 
        />
      )}
    </div>
  );
}
