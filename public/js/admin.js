/**
 * Admin Dashboard JavaScript
 * Event approval and management functions
 */

// Get base URL from current location
const BASE_URL = window.location.origin + '/evento';

document.addEventListener('DOMContentLoaded', function() {
    
    // Event Approval Handler
    document.querySelectorAll('.approve-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const eventId = this.dataset.eventId;
            
            if (confirm('Are you sure you want to approve this event?')) {
                await handleEventAction(eventId, 'approve');
            }
        });
    });
    
    // Event Rejection Handler
    document.querySelectorAll('.reject-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const eventId = this.dataset.eventId;
            const reason = prompt('Please provide a reason for rejection (optional):');
            
            if (reason !== null) { // User didn't cancel
                await handleEventAction(eventId, 'reject', reason);
            }
        });
    });
    
    // Animate role stat bars
    setTimeout(() => {
        document.querySelectorAll('.role-stat-fill').forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0';
            setTimeout(() => {
                bar.style.width = width;
            }, 100);
        });
    }, 500);
    
    // Close modal on background click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
    
    // Add fade out animation styles
    if (!document.getElementById('admin-animations')) {
        const style = document.createElement('style');
        style.id = 'admin-animations';
        style.textContent = `
            @keyframes fadeOut {
                from {
                    opacity: 1;
                    transform: scale(1);
                }
                to {
                    opacity: 0;
                    transform: scale(0.9);
                }
            }
        `;
        document.head.appendChild(style);
    }
});

/**
 * Handle event approval/rejection
 */
async function handleEventAction(eventId, action, reason = null) {
    try {
        const response = await fetch(`${BASE_URL}/api/approve-event.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                event_id: eventId,
                action: action,
                reason: reason
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            
            // Remove the card from DOM
            const card = document.querySelector(`.admin-event-card [data-event-id="${eventId}"]`)?.closest('.admin-event-card');
            if (card) {
                card.style.animation = 'fadeOut 0.3s ease-out';
                setTimeout(() => {
                    card.remove();
                    
                    // Check if no more pending events
                    if (document.querySelectorAll('.admin-event-card').length === 0) {
                        location.reload();
                    }
                }, 300);
            }
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Action failed. Please try again.', 'error');
    }
}

/**
 * Delete user (with confirmation)
 */
async function deleteUser(userId, userName) {
    if (!confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch(`${BASE_URL}/api/delete-user.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ user_id: userId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to delete user', 'error');
    }
}

/**
 * Toggle user status (activate/deactivate)
 */
async function toggleUserStatus(userId, currentStatus) {
    const action = currentStatus ? 'deactivate' : 'activate';
    
    if (!confirm(`Are you sure you want to ${action} this user?`)) {
        return;
    }
    
    try {
        const response = await fetch(`${BASE_URL}/api/toggle-user-status.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ user_id: userId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to update user status', 'error');
    }
}

/**
 * Assign role to user
 */
async function assignRole(userId, roleId) {
    try {
        const response = await fetch(`${BASE_URL}/api/assign-role.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: userId,
                role_id: roleId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Failed to assign role', 'error');
    }
}

/**
 * Export data to CSV
 */
function exportData(type) {
    showToast('Preparing export...', 'success');
    window.location.href = `${BASE_URL}/api/export.php?type=${type}`;
}

/**
 * Modal management
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

/**
 * Search and filter functionality
 */
function filterTable(searchTerm, tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const lowerSearch = searchTerm.toLowerCase();
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(lowerSearch) ? '' : 'none';
    });
}

/**
 * Bulk actions
 */
function selectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function bulkAction(action) {
    const selected = Array.from(document.querySelectorAll('.item-checkbox:checked'))
        .map(cb => cb.value);
    
    if (selected.length === 0) {
        showToast('Please select at least one item', 'error');
        return;
    }
    
    if (confirm(`Perform ${action} on ${selected.length} selected item(s)?`)) {
        // Implement bulk action logic
        console.log('Bulk action:', action, selected);
        showToast(`${action} completed for ${selected.length} items`, 'success');
    }
}
