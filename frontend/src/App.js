import React, { useState, useEffect, useCallback } from 'react';
import './App.css';
import FilmmakerPortal from './FilmmakerPortal';

const API_URL = process.env.REACT_APP_BACKEND_URL || '';

// Status badges with colors
const StatusBadge = ({ status }) => {
  const statusColors = {
    pending: 'bg-yellow-500',
    qa_processing: 'bg-blue-500',
    qa_passed: 'bg-green-500',
    qa_failed: 'bg-red-500',
    classified: 'bg-purple-500',
    published: 'bg-emerald-600',
    rejected: 'bg-gray-500'
  };
  
  return (
    <span className={`px-2 py-1 rounded-full text-xs font-medium text-white ${statusColors[status] || 'bg-gray-400'}`}>
      {status?.replace('_', ' ').toUpperCase()}
    </span>
  );
};

// Stats Card Component
const StatsCard = ({ title, value, color = 'blue' }) => (
  <div className={`bg-zinc-800 border border-zinc-700 rounded-xl p-6 hover:border-${color}-500/50 transition-all`}>
    <p className="text-zinc-400 text-sm font-medium">{title}</p>
    <p className={`text-3xl font-bold text-${color}-400 mt-2`}>{value}</p>
  </div>
);

// Submission Card Component
const SubmissionCard = ({ submission, onPublish, onReject, onReprocess, onViewQA }) => {
  const [selectedShelf, setSelectedShelf] = useState(submission.recommended_shelf || '');
  const [loading, setLoading] = useState(false);

  const shelves = [
    "Buyer: Acquisition Ready",
    "Buyer: Festival Winners & Official Selections",
    "Buyer: New Voices",
    "Buyer: High-Concept Shorts",
    "Filmmaker: Getting Started",
    "Filmmaker: Spotlight – Emerging Creators"
  ];

  const handlePublish = async () => {
    setLoading(true);
    await onPublish(submission.id, selectedShelf);
    setLoading(false);
  };

  return (
    <div className="bg-zinc-800 border border-zinc-700 rounded-xl p-6 hover:border-violet-500/30 transition-all" data-testid={`submission-card-${submission.id}`}>
      <div className="flex justify-between items-start mb-4">
        <div>
          <h3 className="text-lg font-semibold text-white">{submission.title}</h3>
          <p className="text-zinc-400 text-sm">{submission.filmmaker_name} • {submission.filmmaker_email}</p>
        </div>
        <StatusBadge status={submission.status} />
      </div>
      
      <p className="text-zinc-300 text-sm mb-4 line-clamp-2">{submission.description}</p>
      
      {submission.poster_url && (
        <img 
          src={submission.poster_url} 
          alt={submission.title}
          className="w-full h-40 object-cover rounded-lg mb-4"
        />
      )}
      
      <div className="grid grid-cols-2 gap-2 text-xs text-zinc-400 mb-4">
        <div>Genre: {submission.genre || 'N/A'}</div>
        <div>Awards: {submission.festival_awards || 'None'}</div>
        {submission.recommended_shelf && (
          <div className="col-span-2 text-violet-400">
            Recommended: {submission.recommended_shelf}
            {submission.classification_confidence && ` (${Math.round(submission.classification_confidence * 100)}%)`}
          </div>
        )}
      </div>
      
      {/* Actions based on status */}
      <div className="flex flex-wrap gap-2 mt-4 pt-4 border-t border-zinc-700">
        {submission.status === 'classified' && (
          <>
            <select 
              value={selectedShelf}
              onChange={(e) => setSelectedShelf(e.target.value)}
              className="flex-1 bg-zinc-700 text-white text-sm rounded-lg px-3 py-2 border border-zinc-600"
              data-testid="shelf-select"
            >
              <option value="">Select shelf...</option>
              {shelves.map(shelf => (
                <option key={shelf} value={shelf}>{shelf}</option>
              ))}
            </select>
            <button 
              onClick={handlePublish}
              disabled={!selectedShelf || loading}
              className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-zinc-600 text-white text-sm rounded-lg transition-colors"
              data-testid="publish-btn"
            >
              {loading ? 'Publishing...' : 'Publish'}
            </button>
          </>
        )}
        
        {['qa_failed', 'pending'].includes(submission.status) && (
          <button 
            onClick={() => onReprocess(submission.id)}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-colors"
            data-testid="reprocess-btn"
          >
            Reprocess QA
          </button>
        )}
        
        {submission.status !== 'published' && submission.status !== 'rejected' && (
          <button 
            onClick={() => onReject(submission.id)}
            className="px-4 py-2 bg-red-600/20 hover:bg-red-600/40 text-red-400 text-sm rounded-lg transition-colors"
            data-testid="reject-btn"
          >
            Reject
          </button>
        )}
        
        <button 
          onClick={() => onViewQA(submission.id)}
          className="px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-white text-sm rounded-lg transition-colors"
          data-testid="view-qa-btn"
        >
          View QA Report
        </button>
      </div>
    </div>
  );
};

// QA Report Modal
const QAReportModal = ({ report, onClose }) => {
  if (!report) return null;
  
  const Check = ({ passed, label }) => (
    <div className="flex items-center gap-2">
      <span className={passed ? 'text-green-400' : 'text-red-400'}>
        {passed ? '✓' : '✗'}
      </span>
      <span className="text-zinc-300">{label}</span>
    </div>
  );
  
  return (
    <div className="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4" onClick={onClose}>
      <div className="bg-zinc-800 rounded-2xl max-w-2xl w-full max-h-[80vh] overflow-y-auto p-6" onClick={e => e.stopPropagation()}>
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-xl font-bold text-white">QA Report</h2>
          <button onClick={onClose} className="text-zinc-400 hover:text-white text-2xl">&times;</button>
        </div>
        
        <div className={`p-4 rounded-lg mb-6 ${report.overall_passed ? 'bg-green-500/20 border border-green-500/30' : 'bg-red-500/20 border border-red-500/30'}`}>
          <p className={`text-lg font-semibold ${report.overall_passed ? 'text-green-400' : 'text-red-400'}`}>
            {report.overall_passed ? '✓ QA PASSED' : '✗ QA FAILED'}
          </p>
        </div>
        
        <div className="space-y-6">
          <div>
            <h3 className="text-white font-semibold mb-3">Technical Checks</h3>
            <div className="grid grid-cols-2 gap-3">
              <Check passed={report.technical_checks?.video_accessible} label="Video Accessible" />
              <Check passed={report.technical_checks?.codec_valid} label="Codec Valid" />
              <Check passed={report.technical_checks?.resolution_acceptable} label="Resolution OK" />
              <Check passed={report.technical_checks?.audio_present} label="Audio Present" />
              <Check passed={report.technical_checks?.duration_valid} label="Duration Valid" />
              <Check passed={report.technical_checks?.bitrate_acceptable} label="Bitrate OK" />
            </div>
            {report.technical_checks?.details && (
              <div className="mt-3 p-3 bg-zinc-700/50 rounded-lg text-sm text-zinc-400">
                <p>Resolution: {report.technical_checks.details.resolution}</p>
                <p>Codec: {report.technical_checks.details.codec}</p>
                <p>Duration: {report.technical_checks.details.duration_minutes} min</p>
                <p>Bitrate: {report.technical_checks.details.bitrate_mbps} Mbps</p>
              </div>
            )}
          </div>
          
          <div>
            <h3 className="text-white font-semibold mb-3">AI Compliance</h3>
            <div className="grid grid-cols-2 gap-3">
              <Check passed={report.ai_compliance?.content_appropriate} label="Content Appropriate" />
              <Check passed={report.ai_compliance?.professional_quality} label="Professional Quality" />
              <Check passed={report.ai_compliance?.distribution_ready} label="Distribution Ready" />
            </div>
          </div>
          
          {report.issues?.length > 0 && (
            <div>
              <h3 className="text-red-400 font-semibold mb-3">Issues Found</h3>
              <ul className="list-disc list-inside text-zinc-300 space-y-1">
                {report.issues.map((issue, i) => <li key={i}>{issue}</li>)}
              </ul>
            </div>
          )}
          
          {report.recommendations?.length > 0 && (
            <div>
              <h3 className="text-blue-400 font-semibold mb-3">Recommendations</h3>
              <ul className="list-disc list-inside text-zinc-300 space-y-1">
                {report.recommendations.map((rec, i) => <li key={i}>{rec}</li>)}
              </ul>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

// New Submission Form
const NewSubmissionForm = ({ onSubmit, onClose }) => {
  const [formData, setFormData] = useState({
    title: '',
    short_description: '',
    description: '',
    poster_url: '',
    video_url: '',
    video_type: 'mp4',
    filmmaker_name: '',
    filmmaker_email: '',
    genre: '',
    festival_awards: '',
    is_first_film: false
  });
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    await onSubmit(formData);
    setLoading(false);
    onClose();
  };

  return (
    <div className="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4" onClick={onClose}>
      <div className="bg-zinc-800 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6" onClick={e => e.stopPropagation()}>
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-xl font-bold text-white">New Film Submission</h2>
          <button onClick={onClose} className="text-zinc-400 hover:text-white text-2xl">&times;</button>
        </div>
        
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2">
              <label className="block text-zinc-400 text-sm mb-1">Film Title *</label>
              <input
                type="text"
                required
                value={formData.title}
                onChange={e => setFormData({...formData, title: e.target.value})}
                className="w-full bg-zinc-700 text-white rounded-lg px-4 py-2 border border-zinc-600 focus:border-violet-500 outline-none"
                data-testid="input-title"
              />
            </div>
            
            <div>
              <label className="block text-zinc-400 text-sm mb-1">Filmmaker Name *</label>
              <input
                type="text"
                required
                value={formData.filmmaker_name}
                onChange={e => setFormData({...formData, filmmaker_name: e.target.value})}
                className="w-full bg-zinc-700 text-white rounded-lg px-4 py-2 border border-zinc-600 focus:border-violet-500 outline-none"
                data-testid="input-filmmaker-name"
              />
            </div>
            
            <div>
              <label className="block text-zinc-400 text-sm mb-1">Filmmaker Email *</label>
              <input
                type="email"
                required
                value={formData.filmmaker_email}
                onChange={e => setFormData({...formData, filmmaker_email: e.target.value})}
                className="w-full bg-zinc-700 text-white rounded-lg px-4 py-2 border border-zinc-600 focus:border-violet-500 outline-none"
                data-testid="input-filmmaker-email"
              />
            </div>
            
            <div className="col-span-2">
              <label className="block text-zinc-400 text-sm mb-1">Short Description *</label>
              <input
                type="text"
                required
                value={formData.short_description}
                onChange={e => setFormData({...formData, short_description: e.target.value})}
                className="w-full bg-zinc-700 text-white rounded-lg px-4 py-2 border border-zinc-600 focus:border-violet-500 outline-none"
                data-testid="input-short-desc"
              />
            </div>
            
            <div className="col-span-2">
              <label className="block text-zinc-400 text-sm mb-1">Full Description *</label>
              <textarea
                required
                rows={3}
                value={formData.description}
                onChange={e => setFormData({...formData, description: e.target.value})}
                className="w-full bg-zinc-700 text-white rounded-lg px-4 py-2 border border-zinc-600 focus:border-violet-500 outline-none resize-none"
                data-testid="input-description"
              />
            </div>
            
            <div>
              <label className="block text-zinc-400 text-sm mb-1">Poster URL *</label>
              <input
                type="url"
                required
                value={formData.poster_url}
                onChange={e => setFormData({...formData, poster_url: e.target.value})}
                className="w-full bg-zinc-700 text-white rounded-lg px-4 py-2 border border-zinc-600 focus:border-violet-500 outline-none"
                placeholder="https://..."
                data-testid="input-poster-url"
              />
            </div>
            
            <div>
              <label className="block text-zinc-400 text-sm mb-1">Video URL *</label>
              <input
                type="url"
                required
                value={formData.video_url}
                onChange={e => setFormData({...formData, video_url: e.target.value})}
                className="w-full bg-zinc-700 text-white rounded-lg px-4 py-2 border border-zinc-600 focus:border-violet-500 outline-none"
                placeholder="https://..."
                data-testid="input-video-url"
              />
            </div>
            
            <div>
              <label className="block text-zinc-400 text-sm mb-1">Genre</label>
              <input
                type="text"
                value={formData.genre}
                onChange={e => setFormData({...formData, genre: e.target.value})}
                className="w-full bg-zinc-700 text-white rounded-lg px-4 py-2 border border-zinc-600 focus:border-violet-500 outline-none"
                data-testid="input-genre"
              />
            </div>
            
            <div>
              <label className="block text-zinc-400 text-sm mb-1">Festival Awards</label>
              <input
                type="text"
                value={formData.festival_awards}
                onChange={e => setFormData({...formData, festival_awards: e.target.value})}
                className="w-full bg-zinc-700 text-white rounded-lg px-4 py-2 border border-zinc-600 focus:border-violet-500 outline-none"
                data-testid="input-awards"
              />
            </div>
            
            <div className="col-span-2 flex items-center gap-2">
              <input
                type="checkbox"
                id="is_first_film"
                checked={formData.is_first_film}
                onChange={e => setFormData({...formData, is_first_film: e.target.checked})}
                className="w-4 h-4 rounded bg-zinc-700 border-zinc-600"
                data-testid="input-first-film"
              />
              <label htmlFor="is_first_film" className="text-zinc-300">This is my first film</label>
            </div>
          </div>
          
          <div className="flex gap-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 px-4 py-3 bg-zinc-700 hover:bg-zinc-600 text-white rounded-lg transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={loading}
              className="flex-1 px-4 py-3 bg-violet-600 hover:bg-violet-700 disabled:bg-zinc-600 text-white rounded-lg transition-colors"
              data-testid="submit-form-btn"
            >
              {loading ? 'Submitting...' : 'Submit Film'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

// Main App Component
function App() {
  const [stats, setStats] = useState(null);
  const [submissions, setSubmissions] = useState([]);
  const [statusFilter, setStatusFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [qaReport, setQaReport] = useState(null);
  const [showNewForm, setShowNewForm] = useState(false);
  const [notification, setNotification] = useState(null);

  const showNotification = (message, type = 'success') => {
    setNotification({ message, type });
    setTimeout(() => setNotification(null), 3000);
  };

  const fetchStats = useCallback(async () => {
    try {
      const res = await fetch(`${API_URL}/api/stats`);
      const data = await res.json();
      setStats(data);
    } catch (err) {
      console.error('Failed to fetch stats:', err);
    }
  }, []);

  const fetchSubmissions = useCallback(async () => {
    try {
      setLoading(true);
      const url = statusFilter 
        ? `${API_URL}/api/submissions?status=${statusFilter}`
        : `${API_URL}/api/submissions`;
      const res = await fetch(url);
      const data = await res.json();
      setSubmissions(data);
    } catch (err) {
      console.error('Failed to fetch submissions:', err);
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    fetchStats();
    fetchSubmissions();
  }, [fetchStats, fetchSubmissions]);

  const handleNewSubmission = async (formData) => {
    try {
      const res = await fetch(`${API_URL}/api/submissions`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
      });
      if (res.ok) {
        showNotification('Submission created! QA processing started.');
        fetchSubmissions();
        fetchStats();
      } else {
        showNotification('Failed to create submission', 'error');
      }
    } catch (err) {
      showNotification('Error creating submission', 'error');
    }
  };

  const handlePublish = async (id, shelf) => {
    try {
      const res = await fetch(`${API_URL}/api/submissions/${id}/publish?shelf=${encodeURIComponent(shelf)}`, {
        method: 'POST'
      });
      if (res.ok) {
        showNotification('Film published successfully!');
        fetchSubmissions();
        fetchStats();
      } else {
        showNotification('Failed to publish', 'error');
      }
    } catch (err) {
      showNotification('Error publishing', 'error');
    }
  };

  const handleReject = async (id) => {
    if (!window.confirm('Are you sure you want to reject this submission?')) return;
    try {
      const res = await fetch(`${API_URL}/api/submissions/${id}/reject`, { method: 'POST' });
      if (res.ok) {
        showNotification('Submission rejected');
        fetchSubmissions();
        fetchStats();
      }
    } catch (err) {
      showNotification('Error rejecting', 'error');
    }
  };

  const handleReprocess = async (id) => {
    try {
      const res = await fetch(`${API_URL}/api/submissions/${id}/reprocess`, { method: 'POST' });
      if (res.ok) {
        showNotification('QA reprocessing started');
        fetchSubmissions();
      }
    } catch (err) {
      showNotification('Error starting reprocess', 'error');
    }
  };

  const handleViewQA = async (id) => {
    try {
      const res = await fetch(`${API_URL}/api/qa-reports/${id}`);
      if (res.ok) {
        const data = await res.json();
        setQaReport(data);
      } else {
        showNotification('QA report not found', 'error');
      }
    } catch (err) {
      showNotification('Error loading QA report', 'error');
    }
  };

  const handleRegenerateFeed = async () => {
    try {
      const res = await fetch(`${API_URL}/api/feed/regenerate`, { method: 'POST' });
      if (res.ok) {
        showNotification('Roku feed regenerated!');
      }
    } catch (err) {
      showNotification('Error regenerating feed', 'error');
    }
  };

  return (
    <div className="min-h-screen bg-zinc-900 text-white" data-testid="dashboard">
      {/* Notification */}
      {notification && (
        <div className={`fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg ${
          notification.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'
        }`}>
          {notification.message}
        </div>
      )}

      {/* Header */}
      <header className="bg-zinc-800 border-b border-zinc-700 sticky top-0 z-40">
        <div className="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold bg-gradient-to-r from-violet-400 to-pink-400 bg-clip-text text-transparent">
              Front Door Media
            </h1>
            <p className="text-zinc-400 text-sm">Film Submission Dashboard</p>
          </div>
          <div className="flex gap-3">
            <button
              onClick={handleRegenerateFeed}
              className="px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-white rounded-lg transition-colors"
              data-testid="regenerate-feed-btn"
            >
              Regenerate Feed
            </button>
            <button
              onClick={() => setShowNewForm(true)}
              className="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-lg transition-colors"
              data-testid="new-submission-btn"
            >
              + New Submission
            </button>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-6 py-8">
        {/* Stats */}
        {stats && (
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-8">
            <StatsCard title="Total" value={stats.total_submissions} color="violet" />
            <StatsCard title="Pending" value={stats.by_status?.pending || 0} color="yellow" />
            <StatsCard title="Processing" value={stats.by_status?.qa_processing || 0} color="blue" />
            <StatsCard title="QA Passed" value={stats.by_status?.qa_passed || 0} color="green" />
            <StatsCard title="QA Failed" value={stats.by_status?.qa_failed || 0} color="red" />
            <StatsCard title="Classified" value={(stats.by_status?.classified || 0)} color="purple" />
            <StatsCard title="Published" value={stats.by_status?.published || 0} color="emerald" />
          </div>
        )}

        {/* Filter */}
        <div className="flex gap-4 mb-6">
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="bg-zinc-800 text-white rounded-lg px-4 py-2 border border-zinc-700 focus:border-violet-500 outline-none"
            data-testid="status-filter"
          >
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="qa_processing">QA Processing</option>
            <option value="qa_passed">QA Passed</option>
            <option value="qa_failed">QA Failed</option>
            <option value="classified">Classified</option>
            <option value="published">Published</option>
            <option value="rejected">Rejected</option>
          </select>
          
          <button
            onClick={fetchSubmissions}
            className="px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-white rounded-lg transition-colors"
            data-testid="refresh-btn"
          >
            Refresh
          </button>
        </div>

        {/* Submissions Grid */}
        {loading ? (
          <div className="text-center py-12 text-zinc-400">Loading submissions...</div>
        ) : submissions.length === 0 ? (
          <div className="text-center py-12">
            <p className="text-zinc-400 text-lg mb-4">No submissions found</p>
            <button
              onClick={() => setShowNewForm(true)}
              className="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-lg transition-colors"
            >
              Create First Submission
            </button>
          </div>
        ) : (
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {submissions.map(submission => (
              <SubmissionCard
                key={submission.id}
                submission={submission}
                onPublish={handlePublish}
                onReject={handleReject}
                onReprocess={handleReprocess}
                onViewQA={handleViewQA}
              />
            ))}
          </div>
        )}
      </main>

      {/* Modals */}
      {qaReport && <QAReportModal report={qaReport} onClose={() => setQaReport(null)} />}
      {showNewForm && <NewSubmissionForm onSubmit={handleNewSubmission} onClose={() => setShowNewForm(false)} />}
    </div>
  );
}

export default App;
