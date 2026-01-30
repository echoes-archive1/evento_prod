<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/middleware/Auth.php';

// Require admin authentication
Auth::requireRole('admin');

$page_title = "Email Notifications";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../public/css/admin.css" rel="stylesheet">
    <style>
        .email-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
            color: #6366f1;
        }
        
        .stat-card p {
            margin: 0;
            color: #666;
            font-weight: 600;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .action-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .action-button:hover {
            transform: translateY(-2px);
        }
        
        .queue-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .queue-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .queue-table th,
        .queue-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .queue-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-sent { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        
        .priority-high { color: #dc3545; font-weight: bold; }
        .priority-normal { color: #6c757d; }
        .priority-low { color: #28a745; }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>
        
        <main class="admin-main">
            <?php include __DIR__ . '/partials/header.php'; ?>
            
            <div class="admin-content">
                <div class="page-header">
                    <h1><i class="fas fa-envelope"></i> Email Notifications</h1>
                    <p>Manage email notifications, queue, and bulk communications</p>
                </div>

                <!-- Email Queue Statistics -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-chart-bar"></i> Email Queue Statistics</h2>
                        <button onclick="refreshStats()" class="btn-primary">
                            <i class="fas fa-refresh"></i> Refresh
                        </button>
                    </div>
                    <div class="card-content">
                        <div id="emailStats" class="email-stats">
                            <div class="stat-card">
                                <h3 id="pendingCount">-</h3>
                                <p>Pending</p>
                            </div>
                            <div class="stat-card">
                                <h3 id="processingCount">-</h3>
                                <p>Processing</p>
                            </div>
                            <div class="stat-card">
                                <h3 id="sentCount">-</h3>
                                <p>Sent Today</p>
                            </div>
                            <div class="stat-card">
                                <h3 id="failedCount">-</h3>
                                <p>Failed</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                    </div>
                    <div class="card-content">
                        <div class="action-buttons">
                            <button onclick="processQueue()" class="action-button">
                                <i class="fas fa-play"></i> Process Queue Now
                            </button>
                            <button onclick="sendWeeklyDigest()" class="action-button">
                                <i class="fas fa-calendar-week"></i> Send Weekly Digest
                            </button>
                            <button onclick="showBulkReminderModal()" class="action-button">
                                <i class="fas fa-bell"></i> Send Event Reminders
                            </button>
                            <button onclick="showEventUpdateModal()" class="action-button">
                                <i class="fas fa-bullhorn"></i> Send Event Update
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Event Selection for Notifications -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-calendar"></i> Event-based Notifications</h2>
                    </div>
                    <div class="card-content">
                        <div class="form-group">
                            <label for="eventSelect">Select Event:</label>
                            <select id="eventSelect" class="form-control">
                                <option value="">Loading events...</option>
                            </select>
                        </div>
                        <div class="action-buttons">
                            <button onclick="sendEventReminder('1_day')" class="action-button">
                                <i class="fas fa-clock"></i> 1-Day Reminder
                            </button>
                            <button onclick="sendEventReminder('1_hour')" class="action-button">
                                <i class="fas fa-hourglass-half"></i> 1-Hour Reminder
                            </button>
                            <button onclick="sendEventUpdate('updated')" class="action-button">
                                <i class="fas fa-edit"></i> Send Update
                            </button>
                            <button onclick="sendEventUpdate('cancelled')" class="action-button" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                                <i class="fas fa-times"></i> Send Cancellation
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recent Email Queue -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-list"></i> Recent Email Queue</h2>
                        <button onclick="refreshQueue()" class="btn-secondary">
                            <i class="fas fa-refresh"></i> Refresh
                        </button>
                    </div>
                    <div class="card-content">
                        <div id="emailQueue" class="queue-table">
                            <p>Loading queue...</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal for Event Updates -->
    <div id="eventUpdateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Send Event Update</h3>
                <button onclick="closeModal('eventUpdateModal')" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="updateMessage">Update Message:</label>
                    <textarea id="updateMessage" rows="4" class="form-control" placeholder="Enter update message for registered users..."></textarea>
                </div>
                <div class="form-actions">
                    <button onclick="sendEventUpdateWithMessage()" class="btn-primary">Send Update</button>
                    <button onclick="closeModal('eventUpdateModal')" class="btn-secondary">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedEvent = null;
        let currentUpdateType = 'updated';

        // Load initial data
        document.addEventListener('DOMContentLoaded', function() {
            loadEvents();
            refreshStats();
            refreshQueue();
        });

        function loadEvents() {
            fetch('../api/events.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('eventSelect');
                        select.innerHTML = '<option value="">Select an event...</option>';
                        
                        data.events.forEach(event => {
                            const option = document.createElement('option');
                            option.value = event.id;
                            option.textContent = `${event.event_name} (${event.event_date})`;
                            select.appendChild(option);
                        });
                    }
                });
        }

        function refreshStats() {
            fetch('../api/email-notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'queue_status'})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reset counts
                    document.getElementById('pendingCount').textContent = '0';
                    document.getElementById('processingCount').textContent = '0';
                    document.getElementById('sentCount').textContent = '0';
                    document.getElementById('failedCount').textContent = '0';
                    
                    // Update counts
                    data.totals.forEach(total => {
                        const elementId = total.status + 'Count';
                        const element = document.getElementById(elementId);
                        if (element) {
                            element.textContent = total.total;
                        }
                    });
                }
            });
        }

        function processQueue() {
            if (confirm('Process email queue now? This will send pending emails.')) {
                fetch('../api/email-notifications.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'process_queue', limit: 100})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(`Processed ${data.processed} emails, ${data.successful} successful`, 'success');
                        refreshStats();
                    } else {
                        showNotification(data.message, 'error');
                    }
                });
            }
        }

        function sendWeeklyDigest() {
            if (confirm('Send weekly digest to all users?')) {
                fetch('../api/email-notifications.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'send_weekly_digest'})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(`Queued ${data.queued_emails} digest emails`, 'success');
                        refreshStats();
                    } else {
                        showNotification(data.message, 'error');
                    }
                });
            }
        }

        function sendEventReminder(type) {
            const eventId = document.getElementById('eventSelect').value;
            if (!eventId) {
                showNotification('Please select an event', 'error');
                return;
            }

            const timeText = type === '1_hour' ? '1-hour' : '1-day';
            if (confirm(`Send ${timeText} reminders for selected event?`)) {
                fetch('../api/email-notifications.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'send_bulk_reminders',
                        event_id: eventId,
                        reminder_type: type
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(`Queued ${data.queued_emails} reminder emails`, 'success');
                        refreshStats();
                    } else {
                        showNotification(data.message, 'error');
                    }
                });
            }
        }

        function sendEventUpdate(type) {
            const eventId = document.getElementById('eventSelect').value;
            if (!eventId) {
                showNotification('Please select an event', 'error');
                return;
            }

            currentUpdateType = type;
            selectedEvent = eventId;
            document.getElementById('eventUpdateModal').style.display = 'flex';
        }

        function sendEventUpdateWithMessage() {
            const message = document.getElementById('updateMessage').value;
            
            fetch('../api/email-notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'send_event_update',
                    event_id: selectedEvent,
                    update_type: currentUpdateType,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(`Queued ${data.queued_emails} update emails`, 'success');
                    refreshStats();
                    closeModal('eventUpdateModal');
                } else {
                    showNotification(data.message, 'error');
                }
            });
        }

        function refreshQueue() {
            // This would need a separate API endpoint to get recent queue items
            // For now, just show a placeholder
            document.getElementById('emailQueue').innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #666;">
                                Queue details available via cron processor logs
                            </td>
                        </tr>
                    </tbody>
                </table>
            `;
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.getElementById('updateMessage').value = '';
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 2rem;
                border-radius: 8px;
                color: white;
                font-weight: 600;
                z-index: 1000;
                background: ${type === 'success' ? '#28a745' : '#dc3545'};
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 4000);
        }
    </script>

    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }
    </style>
</body>
</html>