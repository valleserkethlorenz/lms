<?php
// Optional overrides let non-portal renderers (includes/bar-tr-league.php, served
// from the site root) reuse this popover. Defaults preserve the portal behaviour.
$notifRole = $notifRole ?? ($userType ?? (function_exists('currentRole') ? currentRole() : (int) ($_SESSION['lms_role'] ?? 0)));
$notifCsrf = $notifCsrf ?? (function_exists('csrfToken') ? csrfToken() : (string) ($_SESSION['csrf_token'] ?? ''));
?>
<style>
.notif-item{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border-bottom:1px solid #e5e7eb;cursor:pointer;transition:background-color 0.15s ease;text-decoration:none;color:inherit}
.notif-item:hover{background-color:#f9fafb;text-decoration:none;color:inherit}
.notif-item.unread{background-color:#eff6ff;border-left:3px solid #3b82f6}
.notif-item.unread:hover{background-color:#dbeafe}
.notif-icon{flex-shrink:0;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;color:white}
.notif-icon.team{background:#8b5cf6}
.notif-icon.schedule{background:#10b981}
.notif-icon.announcement{background:#f59e0b}
.notif-icon.default{background:#6b7280}
.notif-body{flex:1;min-width:0}
.notif-title{font-size:14px;font-weight:600;margin:0 0 4px 0;color:#111827}
.notif-message{font-size:13px;color:#6b7280;margin:0 0 6px 0;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.notif-time{font-size:11px;color:#9ca3af}
.notif-empty{text-align:center;padding:40px 20px;color:#9ca3af}
.notif-empty i{font-size:48px;margin-bottom:12px;display:block}
.popover{max-width:400px!important;width:400px}
.popover-body{padding:0!important;max-height:450px;overflow-y:auto}
.notif-popover-header{background:linear-gradient(135deg,#09173c 0%,#1a3a7c 100%);color:white;border-radius:8px 8px 0 0!important;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #1e40af}
.notif-popover-header h6{margin:0;font-size:15px;font-weight:600}
.notif-mark-all-btn{background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.3);color:white;padding:5px 12px;border-radius:6px;font-size:12px;cursor:pointer;transition:all 0.15s ease}
.notif-mark-all-btn:hover{background:rgba(255,255,255,0.3)}
.notif-loading{text-align:center;padding:40px 20px;color:#9ca3af}
.notif-mark-btn{background:none;border:none;color:#3b82f6;font-size:11px;cursor:pointer;padding:0;margin-top:4px}
.notif-mark-btn:hover{text-decoration:underline}
</style>
<script>
(function () {
    function initNotifPopover() {
    if (initNotifPopover.done) return;
    initNotifPopover.done = true;
var notifCsrfToken = <?= json_encode($notifCsrf) ?>;
var role = <?= json_encode($notifRole) ?>;
var actionUrl = <?= json_encode($notifActionUrl ?? '../../includes/notificationAction.php') ?>;
var routeBase = <?= json_encode($notifRouteBase ?? '') ?>;

var routes = {
    1: {
        'team_registration': 'pending_teams.php',
        'team_cancelled': 'pending_teams.php',
        'team_correction': 'pending_teams.php',
        'team_correction_reminder': 'pending_teams.php',
        'team_correction_expired': 'pending_teams.php',
        'team_approved': 'league_info.php',
        'team_rejected': 'league_info.php',
        'announcement': 'league_info.php',
        'schedule_updated': 'schedule.php',
        'schedule_update': 'schedule.php',
        'upcoming_match': 'schedule.php',
        'committee_assignment': 'schedule.php',
        'match_result': 'standing.php',
        'round_complete': 'standing.php',
        'group_stage_complete': 'standing.php',
        'tournament_complete': 'standing.php',
        'registration_closing': 'league.php'
    },
    2: {
        'team_registration': 'league.php',
        'team_cancelled': 'league.php',
        'team_approved': 'view_team.php',
        'team_rejected': 'league.php',
        'team_correction': 'view_team.php',
        'team_correction_reminder': 'view_team.php',
        'team_correction_expired': 'view_team.php',
        'schedule_updated': 'league.php',
        'schedule_update': 'league.php',
        'match_result': 'league.php',
        'registration_closing': 'browse_leagues.php',
        'upcoming_match': 'league.php',
        'announcement': 'league.php'
    },
    4: {
        'team_registration': 'matches.php',
        'team_cancelled': 'matches.php',
        'team_approved': 'matches.php',
        'team_rejected': 'matches.php',
        'team_correction': 'matches.php',
        'schedule_updated': 'matches.php',
        'schedule_update': 'matches.php',
        'match_result': 'matches.php',
        'registration_closing': 'matches.php',
        'upcoming_match': 'matches.php',
        'committee_assignment': 'matches.php',
        'round_complete': 'matches.php',
        'group_stage_complete': 'matches.php',
        'tournament_complete': 'matches.php',
        'announcement': 'dashboard.php'
    }
};

// Every type the notification helpers write needs an icon here, otherwise the
// bell falls back to a grey generic bell for the newer types.
var icons = {
    'team_registration': 'bi-people-fill',
    'team_cancelled': 'bi-person-x-fill',
    'team_approved': 'bi-check-circle-fill',
    'team_rejected': 'bi-x-circle-fill',
    'team_correction': 'bi-pencil-square',
    'team_correction_reminder': 'bi-alarm',
    'team_correction_expired': 'bi-hourglass-bottom',
    'schedule_update': 'bi-calendar-check-fill',
    'schedule_updated': 'bi-calendar-check-fill',
    'upcoming_match': 'bi-stopwatch-fill',
    'committee_assignment': 'bi-person-badge-fill',
    'match_result': 'bi-trophy-fill',
    'round_complete': 'bi-diagram-3-fill',
    'group_stage_complete': 'bi-diagram-3-fill',
    'tournament_complete': 'bi-trophy-fill',
    'registration_closing': 'bi-clock-fill',
    'announcement': 'bi-megaphone-fill'
};

// MySQL hands us "YYYY-MM-DD HH:MM:SS". The space separated form is parsed as
// local time (what the server wrote), but Safari rejects it outright, so
// normalise it to ISO and fall back for browsers that need slashes.
function parseServerDate(dateStr) {
    if (!dateStr) return null;
    var raw = String(dateStr).trim();
    var parsed = new Date(raw.replace(' ', 'T'));
    if (isNaN(parsed.getTime())) parsed = new Date(raw.replace(/-/g, '/'));
    return isNaN(parsed.getTime()) ? null : parsed;
}

function relativeTime(dateStr) {
    var when = parseServerDate(dateStr);
    if (!when) return '';
    var diff = Math.floor((Date.now() - when.getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
    if (diff < 172800) return 'Yesterday';
    if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
    return when.toLocaleDateString('en-US', {month:'short', day:'numeric'});
}

function getIcon(type) { return icons[type] || 'bi-bell-fill'; }

function getIconColor(type) {
    type = String(type || '');
    if (type.indexOf('team') !== -1) return 'team';
    if (type.indexOf('schedule') !== -1 || type.indexOf('match') !== -1) return 'schedule';
    if (type.indexOf('round') !== -1 || type.indexOf('stage') !== -1 || type.indexOf('tournament') !== -1) return 'schedule';
    if (type === 'announcement' || type === 'registration_closing') return 'announcement';
    return 'default';
}

function getUrl(type, relatedId, link) {
    // The server resolves the exact destination (league id, team, etc.) per role.
    if (link) return routeBase + link;

    var base = routeBase + ((routes[role] && routes[role][type]) || 'dashboard.php');
    if (!relatedId) return base;
    return base + (base.indexOf('?') === -1 ? '?' : '&') + 'notif_id=' + encodeURIComponent(relatedId);
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text == null ? '' : text;
    return div.innerHTML;
}

// The sidebar only prints the badge when the server already knows about unread
// rows, so after "mark all read" the element is gone. Re-create it on demand or
// the next new notification would never show a count until a full page reload.
function badgeElement() {
    var $badge = $('.role-notification-badge');
    if ($badge.length) return $badge;

    var $bell = $('.role-notification-link').first();
    if (!$bell.length) return $badge;

    $badge = $('<span class="role-notification-badge" style="display:none"></span>');
    $bell.append($badge);
    return $badge;
}

function updateBadge(count) {
    count = parseInt(count, 10) || 0;
    var $badge = badgeElement();
    if (!$badge.length) return;
    if (count > 0) {
        $badge.text(count > 99 ? '99+' : String(count)).show();
    } else {
        $badge.text('').hide();
    }
}

function fetchAll(callback) {
    $.ajax({
        url: actionUrl,
        type: 'POST',
        data: { action: 'get_all', csrf_token: notifCsrfToken },
        dataType: 'json',
        success: function(res) {
            if (res && res.status === 200) {
                callback(res.notifications || [], res.unread_count || 0);
            } else {
                callback([], 0);
            }
        },
        error: function() { callback([], 0); }
    });
}

$(document).ready(function() {
    var $bell = $('.role-notification-link');
    if (!$bell.length) return;
    
    var popover = new bootstrap.Popover($bell[0], {
        html: true,
        trigger: 'click',
        placement: 'bottom',
        content: '<div class="notif-loading"><i class="bi bi-hourglass-split"></i><br>Loading...</div>'
    });
    
    $bell.on('shown.bs.popover', function() {
        var $popContent = $('.popover-body');
        $popContent.html('<div class="notif-loading"><i class="bi bi-hourglass-split"></i><br>Loading...</div>');
        
        fetchAll(function(notifications, unreadCount) {
            $popContent.empty();
            
            if (unreadCount > 0) {
                $popContent.append('<div class="notif-popover-header"><h6>Notifications</h6><button class="notif-mark-all-btn">Mark all read</button></div>');
            } else {
                $popContent.append('<div class="notif-popover-header"><h6>Notifications</h6></div>');
            }
            
            var $list = $('<div></div>');
            $popContent.append($list);
            
            if (!notifications || notifications.length === 0) {
                $list.html('<div class="notif-empty"><i class="bi bi-inbox"></i><p>No notifications yet</p></div>');
            } else {
                notifications.forEach(function(n) {
                    var icon = getIcon(n.type);
                    var color = getIconColor(n.type);
                    var time = relativeTime(n.created);
                    var url = getUrl(n.type, n.related_id, n.link);
                    var unreadClass = n.is_read == 0 ? ' unread' : '';
                    
                    var html = '<a href="' + url + '" class="notif-item' + unreadClass + '" data-notif-id="' + n.id + '">' +
                        '<div class="notif-icon ' + color + '"><i class="bi ' + icon + '"></i></div>' +
                        '<div class="notif-body">' +
                        '<div class="notif-title">' + escapeHtml(n.title) + '</div>' +
                        '<div class="notif-message">' + escapeHtml(n.message) + '</div>' +
                        '<div class="notif-time">' + time + '</div>';
                    
                    if (n.is_read == 0) {
                        html += '<button class="notif-mark-btn" data-notif-id="' + n.id + '">Mark as read</button>';
                    }
                    
                    html += '</div></a>';
                    $list.append(html);
                });
            }
            
            updateBadge(unreadCount);
        });
    });
    
    $(document).on('click', '.notif-mark-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var notifId = $(this).data('notif-id');
        
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: { action: 'mark_read', notification_id: notifId, csrf_token: notifCsrfToken },
            dataType: 'json',
            success: function(res) {
                if (res && res.status === 200) {
                    fetchAll(function(notifications, unreadCount) {
                        var $list = $('.popover-body > div:last-child');
                        $list.empty();
                        
                        if (!notifications || notifications.length === 0) {
                            $list.html('<div class="notif-empty"><i class="bi bi-inbox"></i><p>No notifications yet</p></div>');
                        } else {
                            notifications.forEach(function(n) {
                                var icon = getIcon(n.type);
                                var color = getIconColor(n.type);
                                var time = relativeTime(n.created);
                                var url = getUrl(n.type, n.related_id, n.link);
                                var unreadClass = n.is_read == 0 ? ' unread' : '';
                                
                                var html = '<a href="' + url + '" class="notif-item' + unreadClass + '" data-notif-id="' + n.id + '">' +
                                    '<div class="notif-icon ' + color + '"><i class="bi ' + icon + '"></i></div>' +
                                    '<div class="notif-body">' +
                                    '<div class="notif-title">' + escapeHtml(n.title) + '</div>' +
                                    '<div class="notif-message">' + escapeHtml(n.message) + '</div>' +
                                    '<div class="notif-time">' + time + '</div>';
                                
                                if (n.is_read == 0) {
                                    html += '<button class="notif-mark-btn" data-notif-id="' + n.id + '">Mark as read</button>';
                                }
                                
                                html += '</div></a>';
                                $list.append(html);
                            });
                        }
                        
                        if (unreadCount === 0) {
                            $('.notif-popover-header button').remove();
                        }
                        
                        updateBadge(unreadCount);
                    });
                }
            }
        });
    });
    
    $(document).on('click', '.notif-mark-all-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: { action: 'mark_all_read', csrf_token: notifCsrfToken },
            dataType: 'json',
            success: function(res) {
                if (res && res.status === 200) {
                    fetchAll(function(notifications, unreadCount) {
                        $('.notif-popover-header button').remove();
                        var $list = $('.popover-body > div:last-child');
                        $list.empty();
                        
                        if (!notifications || notifications.length === 0) {
                            $list.html('<div class="notif-empty"><i class="bi bi-inbox"></i><p>No notifications yet</p></div>');
                        } else {
                            notifications.forEach(function(n) {
                                var icon = getIcon(n.type);
                                var color = getIconColor(n.type);
                                var time = relativeTime(n.created);
                                var url = getUrl(n.type, n.related_id, n.link);
                                
                                var html = '<a href="' + url + '" class="notif-item" data-notif-id="' + n.id + '">' +
                                    '<div class="notif-icon ' + color + '"><i class="bi ' + icon + '"></i></div>' +
                                    '<div class="notif-body">' +
                                    '<div class="notif-title">' + escapeHtml(n.title) + '</div>' +
                                    '<div class="notif-message">' + escapeHtml(n.message) + '</div>' +
                                    '<div class="notif-time">' + time + '</div>' +
                                    '</div></a>';
                                $list.append(html);
                            });
                        }
                        
                        updateBadge(0);
                    });
                }
            }
        });
    });
    
    // Opening a notification is the same intent as marking it read. The <a> is
    // about to navigate away, so the write is sent with keepalive:true (a plain
    // XHR would be cancelled by the unload) and the badge is corrected locally.
    function markReadOnLeave(notifId) {
        if (window.fetch) {
            fetch(actionUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({
                    action: 'mark_read',
                    notification_id: notifId,
                    csrf_token: notifCsrfToken
                }).toString(),
                credentials: 'same-origin',
                keepalive: true
            }).catch(function () {});
        } else {
            $.ajax({
                url: actionUrl,
                type: 'POST',
                data: { action: 'mark_read', notification_id: notifId, csrf_token: notifCsrfToken },
                dataType: 'json',
                async: false
            });
        }
    }

    $(document).on('click', '.notif-item.unread', function() {
        var $item = $(this);
        var notifId = parseInt($item.attr('data-notif-id'), 10);

        $item.removeClass('unread');
        $item.find('.notif-mark-btn').remove();

        var left = $('.role-notification-badge').first().text();
        if (left && left.indexOf('+') === -1) {
            var remaining = parseInt(left, 10);
            if (!isNaN(remaining)) updateBadge(Math.max(0, remaining - 1));
        }

        if (notifId) markReadOnLeave(notifId);
    });

    $(document).on('click', function(e) {
        if (!$bell.is(e.target) && $bell.has(e.target).length === 0 && 
            !$(e.target).closest('.popover').length) {
            popover.hide();
        }
    });
    
    // Poll the unread counter only while the tab is visible, and refresh the
    // moment it becomes visible again: a hidden tab used to keep polling every
    // minute and could resurrect a count the user had just cleared.
    function refreshBadge() {
        if (document.hidden) return;
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: { action: 'get_unread_count', csrf_token: notifCsrfToken },
            dataType: 'json',
            success: function(res) {
                if (res && res.status === 200) updateBadge(res.unread_count);
            }
        });
    }

    setInterval(refreshBadge, 60000);
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) refreshBadge();
    });
});
    }
    // jQuery/Bootstrap may still be loading when this partial is printed (the
    // root-level league pages load their bundle in the footer), so boot on
    // DOMContentLoaded unless they are already available — true on every portal page.
    if (typeof window.jQuery !== 'undefined' && typeof window.bootstrap !== 'undefined') {
        initNotifPopover();
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.jQuery !== 'undefined' && typeof window.bootstrap !== 'undefined') {
                initNotifPopover();
            }
        });
    }
})();
</script>
