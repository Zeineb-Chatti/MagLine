if (typeof window.messengerGlobals === 'undefined') {
    window.messengerGlobals = {
        currentChatUserId: null,
        currentChatUserName: null,
        currentUserId: null,
        searchTimeout: null,
        messengerInitialized: false,
        pendingChatOpen: null,
        refreshInterval: null,
        isRefreshing: false,
        lastConversationData: null,
        lastMessageData: null,
        messagesPage: 1,
        hasMoreMessages: true,
        isLoadingMessages: false,
        typingTimeout: null,
        isInitialLoad: true,
        lastMessageId: null,
        initialLoadComplete: false,
        scrollPositionBeforeLoad: 0,
        isScrollListenerActive: false,
        totalMessagesLoaded: 0
    };
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.messengerData) {
        console.error('Messenger initialization error: No messengerData found');
        showToast('System error: Please refresh the page', 'error');
        return;
    }

    Object.assign(window.messengerGlobals, {
        currentUserId: window.messengerData.currentUserId,
        pendingChatOpen: window.messengerData.forcedChatData || null,
        initialLoadComplete: false
    });

    initializeMessenger();
});

async function initializeMessenger() {
    try {
        setLoadingState(true);
        showSkeletonLoading();
        await loadConversations();
        
        window.messengerGlobals.messengerInitialized = true;
        
        if (window.messengerGlobals.pendingChatOpen) {
            await openChatFromData(window.messengerGlobals.pendingChatOpen);
            window.messengerGlobals.pendingChatOpen = null;
        } else {
            await handleAutoOpenConversation();
        }
        
        startRefreshCycle();
        setupEventListeners();
        document.dispatchEvent(new CustomEvent('messengerLoaded'));
        
    } catch (error) {
        console.error('Messenger initialization failed:', error);
        showToast('Failed to initialize messenger. Please refresh.', 'error');
        showErrorState();
    } finally {
        setLoadingState(false);
    }
}

function showSkeletonLoading() {
    const container = document.getElementById('conversationsUl');
    if (!container) return;
    
    container.innerHTML = '';
    for (let i = 0; i < 5; i++) {
        const skeleton = document.createElement('div');
        skeleton.className = 'conversation-skeleton';
        skeleton.innerHTML = `
            <div class="skeleton-avatar"></div>
            <div class="skeleton-info">
                <div class="skeleton-name"></div>
                <div class="skeleton-preview"></div>
            </div>
        `;
        container.appendChild(skeleton);
    }
}

function showErrorState() {
    const container = document.getElementById('conversationsUl');
    if (!container) return;
    
    container.innerHTML = `
        <div class="loading-conversations error-state">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Error loading conversations</span>
            <button class="retry-btn" onclick="initializeMessenger()">
                <i class="fas fa-sync-alt"></i> Retry
            </button>
        </div>
    `;
}

function startRefreshCycle() {
    if (window.messengerGlobals.refreshInterval) {
        clearInterval(window.messengerGlobals.refreshInterval);
    }

    window.messengerGlobals.refreshInterval = setInterval(async () => {
        if (window.messengerGlobals.isRefreshing) return;

        window.messengerGlobals.isRefreshing = true;
        try {
            await loadConversations();
            
            if (window.messengerGlobals.currentChatUserId) {
                await fetchNewMessagesOnly();
            }
        } catch (error) {
            console.error("Background refresh error:", error);
        } finally {
            window.messengerGlobals.isRefreshing = false;
        }
    }, 5000);
}

async function fetchNewMessagesOnly() {
    try {
        const res = await fetch(`get_messages.php?with_user=${window.messengerGlobals.currentChatUserId}&page=1&limit=1&_=${Date.now()}`);
        const data = await res.json();

        if (!data.success || !data.messages.length) return;

        const lastMessage = data.messages[data.messages.length - 1];
        const container = document.getElementById('messagesList');

        if (!window.messengerGlobals.lastMessageId || 
            window.messengerGlobals.lastMessageId !== lastMessage.id) {
            
            const msgDiv = document.createElement('div');
            msgDiv.className = `message ${lastMessage.sender_id == window.messengerGlobals.currentUserId ? 'sent' : 'received'}`;
            msgDiv.innerHTML = `
                <div class="message-content">${lastMessage.message}</div>
                <div class="message-meta">
                    <span class="message-time">${formatTime(lastMessage.created_at)}</span>
                    ${lastMessage.sender_id == window.messengerGlobals.currentUserId ? 
                      `<span class="message-status ${lastMessage.is_read ? 'read' : 'delivered'}">
                        <i class="fas fa-${lastMessage.is_read ? 'check-double' : 'check'}"></i>
                      </span>` : ''}
                </div>
            `;
            
            container.appendChild(msgDiv);
            
            if (isScrolledToBottom(container)) {
                container.scrollTop = container.scrollHeight;
            }

            window.messengerGlobals.lastMessageId = lastMessage.id;
        }
    } catch (err) {
        console.error("Failed to fetch new message:", err);
    }
}

async function loadConversations() {
    const container = document.getElementById('conversationsUl');
    if (!container) return;

    try {
        const wasScrolledToBottom = isScrolledToBottom(container);
        const scrollTopBefore = container.scrollTop;

        const res = await fetch('get_conversations.php?_=' + Date.now());
        const data = await res.json();

        if (!data.success) throw new Error(data.error || 'Invalid response');

        if (!window.messengerGlobals.lastConversationData || 
            JSON.stringify(window.messengerGlobals.lastConversationData) !== JSON.stringify(data)) {
            
            window.messengerGlobals.lastConversationData = data;
            
            stabilizeDomUpdates(container, () => {
                const fragment = document.createDocumentFragment();
                
                data.conversations.forEach(conv => {
                    const li = document.createElement('li');
                    
                    // Check if this conversation is currently active and override unread count
                    const isCurrentChat = window.messengerGlobals.currentChatUserId == conv.id;
                    const unreadCount = isCurrentChat ? 0 : conv.unread_count;
                    
                    li.className = `conversation-item ${unreadCount > 0 ? 'unread' : ''} ${
                        isCurrentChat ? 'active' : ''
                    }`;
                    li.dataset.userId = conv.id;
                    
                    const avatarUrl = getPhotoUrl(conv.photo, conv.name);
                    
                    li.innerHTML = `
                        <img src="${avatarUrl}" alt="${conv.name}" class="conversation-avatar" loading="lazy">
                        <div class="conversation-info">
                            <div class="conversation-name">${conv.name}</div>
                            ${conv.last_message ? `<div class="conversation-preview">${conv.last_message}</div>` : ''}
                        </div>
                        ${unreadCount > 0 ? `<span class="unread-badge">${unreadCount}</span>` : ''}
                        ${conv.last_message_time ? `<div class="conversation-time">${formatTime(conv.last_message_time)}</div>` : ''}
                    `;
                    
                    li.addEventListener('click', () => openChat(conv.id, conv.name, conv.photo));
                    fragment.appendChild(li);
                });

                container.innerHTML = '';
                container.appendChild(fragment);
            });

            if (wasScrolledToBottom) {
                container.scrollTop = container.scrollHeight;
            } else {
                container.scrollTop = scrollTopBefore;
            }
        }

    } catch (error) {
        console.error('Conversation load error:', error);
        if (!container.innerHTML.includes('Error')) {
            container.innerHTML = '<div class="loading-conversations">Error loading conversations</div>';
        }
    }
}

async function loadMessages(loadMore = false) {
    if (!window.messengerGlobals.currentChatUserId) return;
    
    const container = document.getElementById('messagesList');
    if (!container) return;
    
    try {
        if (loadMore) {
            if (window.messengerGlobals.isLoadingMessages || !window.messengerGlobals.hasMoreMessages) {
                return;
            }
            
            window.messengerGlobals.messagesPage++;
            window.messengerGlobals.isLoadingMessages = true;
            
            const scrollTop = container.scrollTop;
            const scrollHeight = container.scrollHeight;
            
            const loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'messages-loading';
            loadingIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading older messages...';
            container.insertBefore(loadingIndicator, container.firstChild);
        } else {
            window.messengerGlobals.messagesPage = 1;
            window.messengerGlobals.hasMoreMessages = true;
            window.messengerGlobals.totalMessagesLoaded = 0;
            container.innerHTML = '<div class="messages-loading"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>';
        }

        const res = await fetch(`get_messages.php?with_user=${window.messengerGlobals.currentChatUserId}&page=${window.messengerGlobals.messagesPage}&limit=20&_=${Date.now()}`);
        const data = await res.json();

        if (!data.success) throw new Error(data.error || 'Failed to load messages');

        window.messengerGlobals.lastMessageData = data;
        window.messengerGlobals.hasMoreMessages = data.has_more;
        
        const loadingIndicator = container.querySelector('.messages-loading');
        if (loadingIndicator) loadingIndicator.remove();
        
        if (data.messages.length === 0 && !loadMore) {
            container.innerHTML = '<div class="no-messages">No messages yet. Start the conversation!</div>';
            return;
        }
        
        const fragment = document.createDocumentFragment();
        
        const messages = loadMore ? data.messages.reverse() : data.messages;
        
        messages.forEach(msg => {
            const msgDiv = document.createElement('div');
            msgDiv.className = `message ${msg.sender_id == window.messengerGlobals.currentUserId ? 'sent' : 'received'}`;
            msgDiv.innerHTML = `
                <div class="message-content">${msg.message}</div>
                <div class="message-meta">
                    <span class="message-time">${formatTime(msg.created_at)}</span>
                    ${msg.sender_id == window.messengerGlobals.currentUserId ? 
                      `<span class="message-status ${msg.is_read ? 'read' : 'delivered'}">
                        <i class="fas fa-${msg.is_read ? 'check-double' : 'check'}"></i>
                      </span>` : ''}
                </div>
            `;
            fragment.appendChild(msgDiv);
        });

        if (loadMore) {
            const spacer = container.querySelector('.messages-spacer');
            if (spacer) {
                container.insertBefore(fragment, spacer.nextSibling);
            } else {
                container.insertBefore(fragment, container.firstChild);
            }
            
            const newScrollHeight = container.scrollHeight;
            const heightDifference = newScrollHeight - scrollHeight;
            container.scrollTop = scrollTop + heightDifference;
            
            window.messengerGlobals.totalMessagesLoaded += messages.length;
        } else {
            container.innerHTML = '';
            
            const spacer = document.createElement('div');
            spacer.className = 'messages-spacer';
            container.appendChild(spacer);
            
            container.appendChild(fragment);
            window.messengerGlobals.totalMessagesLoaded = messages.length;
            
            if (messages.length > 0) {
                window.messengerGlobals.lastMessageId = messages[messages.length - 1].id;
            }
            
            setTimeout(() => {
                container.scrollTop = container.scrollHeight;
            }, 50);
            
            // Mark messages as read after loading (server-side only, UI already updated)
            markMessagesAsReadServerOnly(window.messengerGlobals.currentChatUserId);
        }

    } catch (error) {
        console.error('Message load error:', error);
        const loadingIndicator = container.querySelector('.messages-loading');
        if (loadingIndicator) loadingIndicator.remove();
        
        if (!loadMore) {
            container.innerHTML = '<div class="no-messages">Error loading messages</div>';
        }
    } finally {
        window.messengerGlobals.isLoadingMessages = false;
    }
}

// Separate server-side marking from UI updates
async function markMessagesAsReadServerOnly(senderId) {
    try {
        const response = await fetch('mark_messages_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `sender_id=${senderId}`
        });
        
        if (!response.ok) {
            throw new Error('Failed to mark messages as read');
        }
        
        return true;
    } catch (error) {
        console.error('Error marking messages as read:', error);
        return false;
    }
}

async function markMessagesAsRead(senderId) {
    try {
        updateConversationUnreadStatus(senderId);
        updateMessageReadStatuses();

        const response = await fetch('mark_messages_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `sender_id=${senderId}`
        });
        
        if (!response.ok) {
            throw new Error('Failed to mark messages as read');
        }
        
        return true;
    } catch (error) {
        console.error('Error marking messages as read:', error);
        return false;
    }
}

function updateConversationUnreadStatus(userId) {
    const conversationItem = document.querySelector(`.conversation-item[data-user-id="${userId}"]`);
    if (conversationItem) {
        conversationItem.classList.remove('unread');
        const unreadBadge = conversationItem.querySelector('.unread-badge');
        if (unreadBadge) {
            unreadBadge.style.transition = 'all 0.3s ease';
            unreadBadge.style.transform = 'scale(0)';
            unreadBadge.style.opacity = '0';
            setTimeout(() => {
                if (unreadBadge.parentNode) {
                    unreadBadge.remove();
                }
            }, 300);
        }
    }
    
    if (window.messengerGlobals.lastConversationData) {
        const conversation = window.messengerGlobals.lastConversationData.conversations.find(
            c => c.id == userId
        );
        if (conversation) {
            conversation.unread_count = 0;
        }
    }
}

function updateMessageReadStatuses() {
    const messages = document.querySelectorAll('.message.sent .message-status');
    messages.forEach(statusEl => {
        if (statusEl.classList.contains('delivered')) {
            statusEl.classList.remove('delivered');
            statusEl.classList.add('read');
            const icon = statusEl.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-check-double';
            }
        }
    });
}

async function openChat(userId, userName, photoPath = null) {
    return new Promise((resolve) => {
        if (!window.messengerGlobals.messengerInitialized) {
            window.messengerGlobals.pendingChatOpen = { 
                user_id: userId, 
                name: userName, 
                photo: photoPath 
            };
            return resolve();
        }

        const messagesList = document.getElementById('messagesList');
        if (messagesList && window.messengerGlobals.isScrollListenerActive) {
            messagesList.removeEventListener('scroll', handleScroll);
            window.messengerGlobals.isScrollListenerActive = false;
        }

        window.messengerGlobals.currentChatUserId = userId;
        window.messengerGlobals.currentChatUserName = userName;
        window.messengerGlobals.messagesPage = 1;
        window.messengerGlobals.hasMoreMessages = true;
        window.messengerGlobals.isInitialLoad = true;
        window.messengerGlobals.lastMessageId = null;
        window.messengerGlobals.totalMessagesLoaded = 0;

        updateConversationUnreadStatus(userId);

        const photoUrl = getPhotoUrl(photoPath, userName);
        const chatHeader = document.getElementById('chatWith');
        
        if (chatHeader) {
            chatHeader.innerHTML = `
                <div class="active-chat-info" id="profileLink">
                    <img src="${photoUrl}" alt="${userName}" class="chat-user-avatar" loading="lazy">
                    <div class="chat-header-content">
                        <h4>${userName}</h4>
                        <a href="view_profile.php?user_id=${userId}" class="view-profile-btn elegant-btn">
                            <i class="fas fa-user"></i> 
                            <span>Profile</span>
                        </a>
                    </div>
                </div>
            `;

            document.getElementById('profileLink').addEventListener('click', (e) => {
                if (!e.target.classList.contains('view-profile-btn') && !e.target.closest('.view-profile-btn')) {
                    window.location.href = `view_profile.php?user_id=${userId}`;
                }
            });
        }

        document.getElementById('messageInputContainer').style.display = 'block';

        document.querySelectorAll('.conversation-item').forEach(item => {
            item.classList.toggle('active', item.dataset.userId == userId);
        });

        markMessagesAsRead(userId);

        loadMessages().then(() => {
            setupScrollListener();
            resolve();
        });
    });
}

function setupScrollListener() {
    const messagesList = document.getElementById('messagesList');
    if (!messagesList || window.messengerGlobals.isScrollListenerActive) return;
    
    window.messengerGlobals.isScrollListenerActive = true;
    
    messagesList.addEventListener('scroll', handleScroll);
}

function handleScroll() {
    const messagesList = document.getElementById('messagesList');
    if (!messagesList || window.messengerGlobals.isLoadingMessages || !window.messengerGlobals.hasMoreMessages) {
        return;
    }
    
    if (messagesList.scrollTop <= 50) {
        loadMessages(true);
    }
}

async function handleSendMessage(e) {
    e.preventDefault();
    
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    const sendBtn = e.target.querySelector('button[type="submit"]');
    
    if (!message || !window.messengerGlobals.currentChatUserId) {
        showToast('Please select a conversation and enter a message.', 'error');
        return;
    }

    if (sendBtn) sendBtn.disabled = true;
    
    try {
        const formData = new FormData();
        formData.append('receiver_id', window.messengerGlobals.currentChatUserId);
        formData.append('message', message);
        formData.append('csrf_token', window.csrfToken);
        
        const res = await fetch('send_message.php', {
            method: 'POST',
            body: formData
        });
        
        if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
        
        const data = await res.json();
        
        if (data.success) {
            input.value = '';
            await Promise.all([loadConversations(), fetchNewMessagesOnly()]);
            
            const container = document.getElementById('messagesList');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        } else {
            throw new Error(data.error || 'Failed to send message');
        }
    } catch (error) {
        console.error('Message send error:', error);
        showToast(error.message || 'Failed to send message. Please try again.', 'error');
    } finally {
        if (sendBtn) sendBtn.disabled = false;
        input.focus();
    }
}

function openChatFromData(chatData) {
    // Since messages are already marked as read in PHP, 
    // immediately update UI to reflect this
    if (chatData.user_id) {
        updateConversationUnreadStatus(chatData.user_id);
    }
    
    return openChat(chatData.user_id, chatData.name, chatData.photo).then(() => {
        setTimeout(() => {
            const input = document.getElementById('messageInput');
            if (input) input.focus();
        }, 300);
    });
}
async function handleAutoOpenConversation() {
    const urlParams = new URLSearchParams(window.location.search);
    const withUser = urlParams.get('with_user');
    const userName = urlParams.get('name');
    const userPhoto = urlParams.get('photo');

    if (withUser) {
        try {
            await openChat(withUser, userName || 'User', userPhoto);
            history.replaceState(null, null, window.location.pathname);
            document.getElementById('messageInput')?.focus();
        } catch (error) {
            console.error('Auto-open error:', error);
        }
    }
}

function filterConversations(query) {
    const conversations = document.querySelectorAll('.conversation-item');
    const searchTerm = query.toLowerCase();

    conversations.forEach(conv => {
        const name = conv.querySelector('.conversation-name')?.textContent.toLowerCase() || '';
        const preview = conv.querySelector('.conversation-preview')?.textContent.toLowerCase() || '';
        conv.style.display = (name.includes(searchTerm) || preview.includes(searchTerm)) ? 'flex' : 'none';
    });
}

function getPhotoUrl(photoPath, userName) {
    if (!photoPath) {
        return `https://ui-avatars.com/api/?name=${encodeURIComponent(userName)}&background=7c4dff&color=fff`;
    }
    
    const normalizedPath = photoPath.replace(/\\/g, '/');
    
    if (normalizedPath.startsWith('http') || normalizedPath.startsWith('/')) {
        return normalizedPath;
    }
    
    if (normalizedPath.includes('Manager_Photos')) {
        return '/Public/Uploads/' + normalizedPath;
    }
    
    return '/Public/Uploads/Manager_Photos/' + normalizedPath;
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;

    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
    if (diff < 86400000) return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    return date.toLocaleDateString();
}

function showToast(message, type) {
    const toastContainer = document.querySelector('.toast-container') || document.createElement('div');
    toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    if (!toastContainer.parentNode) document.body.appendChild(toastContainer);

    const toastEl = document.createElement('div');
    toastEl.className = `toast show align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                    data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    toastContainer.appendChild(toastEl);

    setTimeout(() => {
        toastEl.classList.remove('show');
        setTimeout(() => toastEl.remove(), 300);
    }, 3000);
}

function isScrolledToBottom(element) {
    return Math.abs(element.scrollHeight - element.scrollTop - element.clientHeight) < 50;
}

function stabilizeDomUpdates(container, updateFn) {
    const initialScroll = container.scrollTop;
    const initialHeight = container.scrollHeight;
    
    requestAnimationFrame(() => {
        updateFn();
        requestAnimationFrame(() => {
            container.scrollTop = initialScroll + (container.scrollHeight - initialHeight);
        });
    });
}

function setLoadingState(isLoading) {
    document.body.classList.toggle('messenger-loading', isLoading);
}

function setupEventListeners() {
    document.getElementById('sendMessageForm')?.addEventListener('submit', handleSendMessage);
    
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(window.messengerGlobals.searchTimeout);
            window.messengerGlobals.searchTimeout = setTimeout(() => {
                filterConversations(searchInput.value);
            }, 300);
        });
    }
    
    document.getElementById('messageInput')?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('sendMessageForm').dispatchEvent(new Event('submit'));
        }
    });
    
    window.addEventListener('beforeunload', () => {
        if (window.messengerGlobals.refreshInterval) {
            clearInterval(window.messengerGlobals.refreshInterval);
        }
    });

    document.getElementById('messageInput')?.addEventListener('input', handleTypingIndicator);
}

function handleTypingIndicator() {
    if (!window.messengerGlobals.currentChatUserId) return;
    
    sendTypingNotification(true);
    
    clearTimeout(window.messengerGlobals.typingTimeout);
    
    window.messengerGlobals.typingTimeout = setTimeout(() => {
        sendTypingNotification(false);
    }, 3000);
}

async function sendTypingNotification(isTyping) {
    try {
        await fetch('typing_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                recipient_id: window.messengerGlobals.currentChatUserId,
                is_typing: isTyping,
                csrf_token: window.csrfToken
            })
        });
    } catch (error) {
        console.error('Error sending typing notification:', error);
    }
}
