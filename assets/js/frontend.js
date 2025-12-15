/**
 * France Relocation Member Tools - Frontend JavaScript
 * 
 * Handles member dashboard, profile, guides, documents, and checklists.
 * 
 * @package FRA_Member_Tools
 * @since 1.0.0
 * @version 1.0.34
 */

(function($) {
    'use strict';

    /**
     * Debug mode - set to false for production
     * @type {boolean}
     */
    var DEBUG = false;

    /**
     * Log helper - only logs in debug mode
     * @param {...*} args - Arguments to log
     */
    function log() {
        if (DEBUG && console && console.log) {
            console.log.apply(console, ['FRAMT:'].concat(Array.prototype.slice.call(arguments)));
        }
    }

    /**
     * Main FRAMT object
     */
    var FRAMT = {
        
        /** @type {boolean} Track if welcome chat shown this session */
        hasSeenWelcome: false,
        
        /** @type {Object|null} Current document context */
        currentDocContext: null,
        
        /** @type {string|null} Current document type */
        currentDocType: null,
        
        /** @type {Object|null} Current guide context */
        currentGuideContext: null,

        /**
         * Initialize the module
         */
        init: function() {
            this.bindEvents();
            this.initGlossary();
            this.listenForNavigation();
        },

        /**
         * Listen for navigation events from main plugin
         */
        listenForNavigation: function() {
            var self = this;
            
            // Listen for custom navigation event from main plugin
            document.addEventListener('fra:navigate', function(e) {
                var section = e.detail.section;
                log('Navigation event received:', section);
                self.loadSection(section);
            });
            
            // Direct binding for member nav buttons ONLY (not auth dashboard tiles)
            // Use specific selectors to avoid conflicts with main plugin's tile handlers
            $(document).on('click', '.fra-member-nav-btn[data-section], .framt-nav-btn[data-section]', function(e) {
                var section = $(this).data('section');
                var memberSections = ['dashboard', 'my-checklists', 'create-documents', 'upload-verify', 'glossary', 'guides', 'profile', 'messages'];
                
                if (memberSections.indexOf(section) !== -1) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    $('.fra-member-nav-btn, .framt-nav-btn').removeClass('active');
                    $(this).addClass('active');
                    
                    self.loadSection(section);
                }
            });
        },

        /**
         * Load content for a section via AJAX
         * @param {string} section - Section identifier
         */
        loadSection: function(section) {
            var self = this;
            var chatPanel = document.querySelector('.fra-chat-panel');
            var contentBody = document.getElementById('fra-member-content-body');
            
            if (!contentBody) {
                console.error('FRAMT: No content container found');
                return;
            }
            
            if (typeof framtData === 'undefined') {
                console.error('FRAMT: framtData not defined');
                return;
            }
            
            // Use class-based toggle (matches main plugin approach)
            if (chatPanel) {
                chatPanel.classList.add('showing-member-content');
            }
            
            // Show loading state
            contentBody.innerHTML = '<div class="fra-loading" style="padding: 2rem; text-align: center;">Loading...</div>';
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_load_section',
                    nonce: framtData.nonce,
                    section: section
                },
                success: function(response) {
                    if (response.success && response.data && response.data.html) {
                        contentBody.innerHTML = response.data.html;
                        
                        // Ensure scrollability
                        contentBody.scrollTop = 0;
                        
                        // CRITICAL: Ensure auth views stay hidden after content loads
                        var authViews = ['login', 'signup', 'dashboard', 'account', 'subscriptions', 'payments'];
                        authViews.forEach(function(v) {
                            var el = document.getElementById('fra-inchat-' + v);
                            if (el) el.style.display = 'none';
                        });
                        if (chatPanel) {
                            chatPanel.classList.remove('showing-inchat-auth');
                            chatPanel.classList.add('showing-member-content');
                        }
                        
                        self.initSectionFeatures(section);
                        
                        // Show profile reminder for users with incomplete profiles (< 50%)
                        if (section === 'dashboard' && !self.hasSeenWelcome) {
                            var profileCompletion = framtData.profileCompletion || 0;
                            if (profileCompletion < 50) {
                                self.hasSeenWelcome = true;
                                setTimeout(function() {
                                    self.showWelcomeChat();
                                }, 300);
                            }
                        }
                    } else {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : 'Unable to load content';
                        contentBody.innerHTML = '<div class="fra-error" style="padding: 2rem; text-align: center;">' + errorMsg + '</div>';
                    }
                },
                error: function(xhr, status, error) {
                    contentBody.innerHTML = '<div class="fra-error" style="padding: 2rem; text-align: center;">Error loading content. Please try again.</div>';
                }
            });
        },

        /**
         * Initialize features for loaded section
         * @param {string} section - Section identifier
         */
        initSectionFeatures: function(section) {
            switch(section) {
                case 'glossary':
                    this.initGlossary();
                    break;
                case 'my-checklists':
                    this.initChecklists();
                    break;
                case 'dashboard':
                    this.initDashboard();
                    break;
                case 'profile':
                    this.initProfile();
                    break;
                case 'messages':
                    this.initMessages();
                    break;
            }
        },

        /**
         * Initialize profile form handling
         */
        initProfile: function() {
            var self = this;
            var form = document.getElementById('framt-profile-form');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    self.saveProfile(form);
                });
            }
        },
        
        /**
         * Save profile data via AJAX
         * @param {HTMLFormElement} form - The profile form element
         */
        saveProfile: function(form) {
            var self = this;
            var formData = new FormData(form);
            var profileData = {};
            
            formData.forEach(function(value, key) {
                profileData[key] = value;
            });
            
            var submitBtn = form.querySelector('button[type="submit"]');
            var originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Saving...';
            submitBtn.disabled = true;
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_save_profile',
                    nonce: framtData.nonce,
                    profile: profileData
                },
                success: function(response) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    
                    if (response.success) {
                        self.showNotification('Profile saved successfully!', 'success');
                        
                        // Update progress bar
                        if (response.data.completion !== undefined) {
                            var progressFill = document.querySelector('.framt-progress-fill');
                            var progressText = document.querySelector('.framt-profile-completion span');
                            if (progressFill) progressFill.style.width = response.data.completion + '%';
                            if (progressText) progressText.textContent = response.data.completion + '% complete';
                        }
                    } else {
                        self.showNotification(response.data || 'Failed to save profile', 'error');
                    }
                },
                error: function() {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    self.showNotification('Error saving profile. Please try again.', 'error');
                }
            });
        },
        
        /**
         * Show notification message
         * @param {string} message - Message to display
         * @param {string} type - 'success' or 'error'
         */
        showNotification: function(message, type) {
            var existing = document.querySelector('.framt-notification');
            if (existing) existing.remove();
            
            var notification = document.createElement('div');
            notification.className = 'framt-notification framt-notification-' + type;
            notification.innerHTML = '<span>' + this.escapeHtml(message) + '</span>';
            
            var container = document.querySelector('.framt-profile') || document.querySelector('.framt-member-content');
            if (container) {
                container.insertBefore(notification, container.firstChild);
                
                setTimeout(function() {
                    notification.classList.add('framt-notification-fade');
                    setTimeout(function() {
                        if (notification.parentNode) notification.remove();
                    }, 300);
                }, 4000);
            }
        },

        /**
         * Escape HTML to prevent XSS
         * @param {string} text - Text to escape
         * @returns {string} Escaped text
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Initialize dashboard features
         */
        initDashboard: function() {
            // Dashboard-specific initialization
        },

        /**
         * Initialize checklist features
         */
        initChecklists: function() {
            // Checklist-specific initialization
        },

        /**
         * Initialize messages section
         */
        initMessages: function() {
            var self = this;
            
            // Load inbox initially
            this.loadMessagesInbox();
            
            // Compose button
            $(document).off('click.framt-compose').on('click.framt-compose', '.framt-compose-btn', function() {
                self.showComposeView();
            });
            
            // Back to inbox
            $(document).off('click.framt-back-inbox').on('click.framt-back-inbox', '.framt-back-to-inbox', function() {
                self.showInboxView();
            });
            
            // Send new message
            $(document).off('click.framt-send-msg').on('click.framt-send-msg', '.framt-send-message', function() {
                self.sendNewMessage();
            });
            
            // Click on message item
            $(document).off('click.framt-msg-item').on('click.framt-msg-item', '.framt-message-item', function() {
                var messageId = $(this).data('id');
                self.loadMessageConversation(messageId);
            });
            
            // Send reply
            $(document).off('click.framt-send-reply').on('click.framt-send-reply', '.framt-send-reply', function() {
                self.sendMessageReply();
            });
            
            // Delete message
            $(document).off('click.framt-delete-msg').on('click.framt-delete-msg', '.framt-delete-message', function() {
                var messageId = $(this).data('id');
                self.deleteMessage(messageId);
            });
        },

        /**
         * Load messages inbox
         */
        loadMessagesInbox: function() {
            var self = this;
            var container = document.querySelector('.framt-inbox-view');
            if (!container) return;
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_get_messages',
                    nonce: framtData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderInbox(response.data.messages);
                        self.updateUnreadBadge(response.data.unread_count);
                    } else {
                        container.innerHTML = '<div class="framt-inbox-empty"><p>Error loading messages</p></div>';
                    }
                },
                error: function() {
                    container.innerHTML = '<div class="framt-inbox-empty"><p>Error loading messages</p></div>';
                }
            });
        },

        /**
         * Render inbox list
         */
        renderInbox: function(messages) {
            var container = document.querySelector('.framt-inbox-view');
            if (!container) return;
            
            if (!messages || messages.length === 0) {
                container.innerHTML = '<div class="framt-inbox-empty">' +
                    '<div class="framt-inbox-empty-icon">📭</div>' +
                    '<p>No messages yet</p>' +
                    '<p style="font-size: 0.875rem;">Click "New Message" to contact support</p>' +
                '</div>';
                return;
            }
            
            var html = '<div class="framt-inbox-list">';
            for (var i = 0; i < messages.length; i++) {
                var msg = messages[i];
                var unreadClass = msg.has_unread_user == 1 ? ' unread' : '';
                var statusClass = msg.status;
                var preview = msg.last_reply ? msg.last_reply.substring(0, 60) + '...' : '';
                
                html += '<div class="framt-message-item' + unreadClass + '" data-id="' + msg.id + '">' +
                    '<div class="framt-message-icon">' + (msg.has_unread_user == 1 ? '🔔' : '✉️') + '</div>' +
                    '<div class="framt-message-content">' +
                        '<div class="framt-message-subject">' + this.escapeHtml(msg.subject) + '</div>' +
                        '<div class="framt-message-meta">' +
                            '<span class="framt-message-status ' + statusClass + '">' + statusClass + '</span>' +
                            '<span>' + this.formatDate(msg.updated_at) + '</span>' +
                            '<span>' + msg.reply_count + ' replies</span>' +
                        '</div>' +
                        (preview ? '<div class="framt-message-preview">' + this.escapeHtml(preview) + '</div>' : '') +
                    '</div>' +
                '</div>';
            }
            html += '</div>';
            
            container.innerHTML = html;
        },

        /**
         * Show compose view
         */
        showComposeView: function() {
            document.querySelector('.framt-inbox-view').style.display = 'none';
            document.querySelector('.framt-message-view').style.display = 'none';
            document.querySelector('.framt-compose-view').style.display = 'block';
            document.getElementById('framt-compose-subject').value = '';
            document.getElementById('framt-compose-content').value = '';
        },

        /**
         * Show inbox view
         */
        showInboxView: function() {
            document.querySelector('.framt-compose-view').style.display = 'none';
            document.querySelector('.framt-message-view').style.display = 'none';
            document.querySelector('.framt-inbox-view').style.display = 'block';
            this.loadMessagesInbox();
        },

        /**
         * Send new message
         */
        sendNewMessage: function() {
            var self = this;
            var subject = document.getElementById('framt-compose-subject').value.trim();
            var content = document.getElementById('framt-compose-content').value.trim();
            
            if (!subject || !content) {
                alert('Please fill in both subject and message');
                return;
            }
            
            var btn = document.querySelector('.framt-send-message');
            btn.disabled = true;
            btn.textContent = 'Sending...';
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_create_message',
                    nonce: framtData.nonce,
                    subject: subject,
                    content: content
                },
                success: function(response) {
                    if (response.success) {
                        self.showInboxView();
                    } else {
                        alert(response.data.message || 'Failed to send message');
                    }
                },
                error: function() {
                    alert('Error sending message');
                },
                complete: function() {
                    btn.disabled = false;
                    btn.textContent = 'Send Message';
                }
            });
        },

        /**
         * Load message conversation
         */
        loadMessageConversation: function(messageId) {
            var self = this;
            self.currentMessageId = messageId;
            
            document.querySelector('.framt-inbox-view').style.display = 'none';
            document.querySelector('.framt-compose-view').style.display = 'none';
            document.querySelector('.framt-message-view').style.display = 'block';
            
            var container = document.querySelector('.framt-message-conversation');
            container.innerHTML = '<div style="text-align: center; padding: 2rem;">Loading...</div>';
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_get_message',
                    nonce: framtData.nonce,
                    message_id: messageId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderConversation(response.data.message, response.data.replies);
                    } else {
                        container.innerHTML = '<p>Error loading message</p>';
                    }
                },
                error: function() {
                    container.innerHTML = '<p>Error loading message</p>';
                }
            });
        },

        /**
         * Render conversation view
         */
        renderConversation: function(message, replies) {
            var html = '<div class="framt-conversation-subject">' + this.escapeHtml(message.subject) + '</div>' +
                '<div class="framt-conversation-meta">' +
                    '<span class="framt-message-status ' + message.status + '">' + message.status + '</span> &bull; ' +
                    'Created ' + this.formatDate(message.created_at) +
                '</div>';
            
            html += '<div class="framt-conversation-replies">';
            for (var i = 0; i < replies.length; i++) {
                var reply = replies[i];
                var typeClass = reply.is_admin == 1 ? 'admin' : 'user';
                var authorLabel = reply.is_admin == 1 ? 'Support Team' : 'You';
                
                html += '<div class="framt-reply ' + typeClass + '">' +
                    '<div class="framt-reply-header">' +
                        '<span class="framt-reply-author">' + authorLabel + '</span>' +
                        '<span>' + this.formatDate(reply.created_at) + '</span>' +
                    '</div>' +
                    '<div class="framt-reply-content">' + this.escapeHtml(reply.content) + '</div>' +
                '</div>';
            }
            html += '</div>';
            
            if (message.status !== 'closed') {
                html += '<div class="framt-reply-form">' +
                    '<textarea id="framt-reply-content" placeholder="Type your reply..."></textarea>' +
                    '<div class="framt-reply-actions">' +
                        '<button type="button" class="framt-btn framt-btn-primary framt-send-reply">Send Reply</button>' +
                    '</div>' +
                '</div>';
            } else {
                html += '<div class="framt-reply-form" style="background: #f3f4f6; padding: 1rem; border-radius: 6px; text-align: center; color: #6b7280;">' +
                    'This ticket has been closed. Need more help? <button type="button" class="framt-compose-btn" style="background: none; border: none; color: #1e3a5f; text-decoration: underline; cursor: pointer;">Open a new ticket</button>' +
                '</div>';
            }
            
            html += '<div class="framt-message-actions">' +
                '<button type="button" class="framt-delete-message" data-id="' + message.id + '">Delete this conversation</button>' +
            '</div>';
            
            document.querySelector('.framt-message-conversation').innerHTML = html;
        },

        /**
         * Send reply to message
         */
        sendMessageReply: function() {
            var self = this;
            var content = document.getElementById('framt-reply-content').value.trim();
            
            if (!content) {
                alert('Please enter a reply');
                return;
            }
            
            var btn = document.querySelector('.framt-send-reply');
            btn.disabled = true;
            btn.textContent = 'Sending...';
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_reply_message',
                    nonce: framtData.nonce,
                    message_id: self.currentMessageId,
                    content: content
                },
                success: function(response) {
                    if (response.success) {
                        self.loadMessageConversation(self.currentMessageId);
                    } else {
                        alert(response.data.message || 'Failed to send reply');
                    }
                },
                error: function() {
                    alert('Error sending reply');
                },
                complete: function() {
                    btn.disabled = false;
                    btn.textContent = 'Send Reply';
                }
            });
        },

        /**
         * Delete message
         */
        deleteMessage: function(messageId) {
            var self = this;
            
            if (!confirm('Are you sure you want to delete this conversation? This cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_delete_message',
                    nonce: framtData.nonce,
                    message_id: messageId
                },
                success: function(response) {
                    if (response.success) {
                        self.showInboxView();
                    } else {
                        alert(response.data.message || 'Failed to delete message');
                    }
                },
                error: function() {
                    alert('Error deleting message');
                }
            });
        },

        /**
         * Update unread badge in dropdown
         */
        updateUnreadBadge: function(count) {
            var badge = document.getElementById('fra-messages-badge');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        },

        /**
         * Format date for display
         */
        formatDate: function(dateStr) {
            var date = new Date(dateStr);
            var now = new Date();
            var diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));
            
            if (diffDays === 0) {
                return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            } else if (diffDays === 1) {
                return 'Yesterday';
            } else if (diffDays < 7) {
                return diffDays + ' days ago';
            } else {
                return date.toLocaleDateString();
            }
        },

        /**
         * Bind global event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Action buttons
            $(document).off('click.framt-action').on('click.framt-action', '[data-action]', function(e) {
                self.handleAction.call(self, e);
            });
            
            // Dashboard message row click - navigate to messages section and open that message
            $(document).off('click.framt-msg-row').on('click.framt-msg-row', '.framt-message-row[data-message-id]', function(e) {
                e.preventDefault();
                var messageId = $(this).data('message-id');
                self.navigateTo('messages');
                // Slight delay to ensure section loads, then open the message
                setTimeout(function() {
                    if (typeof self.loadMessageConversation === 'function') {
                        self.loadMessageConversation(messageId);
                    }
                }, 400);
            });
            
            // Document chat - option buttons
            $(document).off('click.framt-doc-option').on('click.framt-doc-option', '.framt-doc-chat-option', function(e) {
                e.preventDefault();
                var value = $(this).data('value');
                var label = $(this).text();
                
                // Disable all options after clicking
                $(this).closest('.framt-doc-chat-options').find('button').prop('disabled', true);
                $(this).addClass('selected');
                
                // Send the selected value
                self.sendDocChatMessage(value, false);
            });
            
            // Document chat - send button
            $(document).off('click.framt-doc-send').on('click.framt-doc-send', '#framt-doc-chat-send', function(e) {
                e.preventDefault();
                var input = document.getElementById('framt-doc-chat-input');
                if (input && input.value.trim()) {
                    self.sendDocChatMessage(input.value.trim(), false);
                    input.value = '';
                }
            });
            
            // Document chat - enter key
            $(document).off('keypress.framt-doc-input').on('keypress.framt-doc-input', '#framt-doc-chat-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#framt-doc-chat-send').trigger('click');
                }
            });
            
            // Guide chat - send button
            $(document).off('click.framt-guide-send').on('click.framt-guide-send', '#framt-guide-chat-send', function(e) {
                e.preventDefault();
                var input = document.getElementById('framt-guide-chat-input');
                if (input && input.value.trim()) {
                    self.sendGuideChatMessage(input.value.trim(), false);
                    input.value = '';
                }
            });
            
            // Guide chat - enter key
            $(document).off('keypress.framt-guide-input').on('keypress.framt-guide-input', '#framt-guide-chat-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#framt-guide-chat-send').trigger('click');
                }
            });
            
            // Welcome chat button
            $(document).off('click.framt-getstarted').on('click.framt-getstarted', '[data-action="lets-get-started"]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.showWelcomeChat();
            });
            
            // Guide buttons
            $(document).off('click.framt-guide').on('click.framt-guide', '.framt-guide-card button, [data-action="view-guide"]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var guideId = $(this).data('guide') || $(this).closest('[data-guide]').data('guide');
                if (guideId) {
                    self.viewGuide(guideId);
                }
            });
            
            // Health insurance upload
            this.bindHealthInsuranceEvents();
        },

        /**
         * Bind health insurance upload events
         */
        bindHealthInsuranceEvents: function() {
            var self = this;
            
            // Glossary term toggle
            $(document).off('click.framt-glossary').on('click.framt-glossary', '.framt-term-toggle', function(e) {
                e.preventDefault();
                var $termItem = $(this).closest('.framt-term-item');
                $termItem.toggleClass('active');
            });
            
            // Health insurance file upload
            $(document).on('change', '#health-insurance-file', function(e) {
                self.handleHealthInsuranceUpload(e);
            });
            
            // Health question send button
            $(document).off('click.framt-health-send').on('click.framt-health-send', '#framt-health-send-btn', function(e) {
                e.preventDefault();
                var question = $('#framt-health-question-input').val();
                self.sendHealthQuestion(question);
            });
            
            // Health question enter key
            $(document).off('keypress.framt-health-input').on('keypress.framt-health-input', '#framt-health-question-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#framt-health-send-btn').trigger('click');
                }
            });
            
            $(document).on('dragover', '#health-insurance-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-over');
            });
            
            $(document).on('dragleave', '#health-insurance-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
            });
            
            $(document).on('drop', '#health-insurance-dropzone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
                
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    $('#health-insurance-file')[0].files = files;
                    $('#health-insurance-file').trigger('change');
                }
            });
            
            // Help popup - open on click
            $(document).off('click.framt-help-btn').on('click.framt-help-btn', '.framt-help-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var popup = $(this).siblings('.framt-help-popup');
                // Close any other open popups first
                $('.framt-help-popup').not(popup).hide();
                popup.toggle();
            });
            
            // Help popup - close on "Got it" button
            $(document).off('click.framt-help-close').on('click.framt-help-close', '.framt-help-popup-close', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.framt-help-popup').hide();
            });
            
            // Help popup - close when clicking outside
            $(document).on('click.framt-help-outside', function(e) {
                if (!$(e.target).closest('.framt-help-btn, .framt-help-popup').length) {
                    $('.framt-help-popup').hide();
                }
            });
        },
        
        /**
         * Handle health insurance file upload
         * @param {Event} e - Change event
         */
        handleHealthInsuranceUpload: function(e) {
            var self = this;
            var file = e.target.files[0];
            if (!file) return;
            
            if (file.size > 10 * 1024 * 1024) {
                alert('File is too large. Maximum size is 10MB.');
                return;
            }
            
            var validTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            if (validTypes.indexOf(file.type) === -1) {
                alert('Invalid file type. Please upload a PDF, JPG, or PNG file.');
                return;
            }
            
            // Hide dropzone, show analyzing indicator
            $('#framt-health-upload-area .framt-health-dropzone').hide();
            $('#health-analyzing-indicator').show();
            
            // Add user message showing file uploaded
            this.addHealthChatMessage('📄 Uploaded: ' + file.name, 'user');
            
            var formData = new FormData();
            formData.append('action', 'framt_verify_health_insurance');
            formData.append('nonce', framtData.nonce);
            formData.append('file', file);
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 120000,
                success: function(response) {
                    $('#health-analyzing-indicator').hide();
                    
                    if (response.success) {
                        // Hide upload area entirely
                        $('#framt-health-upload-area').hide();
                        
                        // Add AI response with verification results
                        self.addHealthChatMessage(response.data.html, 'ai', true);
                        
                        // Show the chat input for follow-up questions
                        $('#framt-health-chat-input').show();
                        
                        // Scroll to TOP of results so user sees status badge first
                        var chatContainer = document.getElementById('framt-health-chat-messages');
                        if (chatContainer) {
                            chatContainer.scrollTop = 0;
                        }
                        // Also scroll the main content area to show the result
                        var verifyChat = document.querySelector('.framt-health-verify-chat');
                        if (verifyChat) {
                            verifyChat.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                        
                        // Store verification context for follow-up questions
                        self.healthVerificationContext = {
                            status: response.data.status,
                            findings: response.data.findings,
                            raw: response.data.raw || ''
                        };
                    } else {
                        // Show error and restore dropzone
                        self.addHealthChatMessage('❌ ' + (response.data.message || response.data || 'Verification failed. Please try again.'), 'ai');
                        $('#framt-health-upload-area .framt-health-dropzone').show();
                    }
                },
                error: function(xhr, status, error) {
                    $('#health-analyzing-indicator').hide();
                    $('#framt-health-upload-area .framt-health-dropzone').show();
                    
                    if (status === 'timeout') {
                        self.addHealthChatMessage('⏱️ The analysis is taking longer than expected. Please try again.', 'ai');
                    } else {
                        self.addHealthChatMessage('❌ Upload failed. Please try again.', 'ai');
                    }
                }
            });
        },
        
        /**
         * Add message to health insurance chat
         */
        addHealthChatMessage: function(content, role, isHtml) {
            var messagesContainer = document.getElementById('framt-health-chat-messages');
            if (!messagesContainer) return;
            
            var messageDiv = document.createElement('div');
            messageDiv.className = 'framt-health-chat-message framt-health-chat-' + role;
            
            var avatarHtml = role === 'ai' ? 
                '<div class="framt-health-chat-avatar">🏥</div>' : 
                '<div class="framt-health-chat-avatar">👤</div>';
            
            var bubbleContent = isHtml ? content : this.escapeHtml(content);
            var contentHtml = '<div class="framt-health-chat-bubble">' + bubbleContent + '</div>';
            
            messageDiv.innerHTML = avatarHtml + contentHtml;
            messagesContainer.appendChild(messageDiv);
            
            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        },
        
        /**
         * Handle health insurance follow-up question
         */
        sendHealthQuestion: function(question) {
            var self = this;
            
            if (!question || !question.trim()) return;
            
            // Add user question to chat
            this.addHealthChatMessage(question, 'user');
            
            // Clear input
            $('#framt-health-question-input').val('');
            
            // Show typing indicator
            this.addHealthChatMessage('<div class="framt-typing-indicator">●●●</div>', 'ai', true);
            
            // Send question to AI
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_health_followup',
                    nonce: framtData.nonce,
                    question: question,
                    context: JSON.stringify(this.healthVerificationContext || {})
                },
                timeout: 60000,
                success: function(response) {
                    // Remove typing indicator
                    $('.framt-health-chat-messages .framt-typing-indicator').closest('.framt-health-chat-message').remove();
                    
                    if (response.success) {
                        self.addHealthChatMessage(self.formatHealthResponse(response.data.answer), 'ai', true);
                    } else {
                        self.addHealthChatMessage('Sorry, I couldn\'t process your question. Please try again.', 'ai');
                    }
                },
                error: function() {
                    $('.framt-health-chat-messages .framt-typing-indicator').closest('.framt-health-chat-message').remove();
                    self.addHealthChatMessage('Connection error. Please try again.', 'ai');
                }
            });
        },
        
        /**
         * Format health AI response
         */
        formatHealthResponse: function(content) {
            if (!content) return '';
            
            // Convert markdown-style formatting
            var html = content
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/\n\n/g, '</p><p>')
                .replace(/\n/g, '<br>');
            
            return '<p>' + html + '</p>';
        },

        /**
         * Handle action button clicks
         * @param {Event} e - Click event
         */
        handleAction: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(e.currentTarget);
            var action = $btn.data('action');

            switch (action) {
                case 'start-onboarding':
                    this.startOnboarding();
                    break;
                case 'lets-get-started':
                    this.showWelcomeChat();
                    break;
                case 'complete-profile':
                case 'view-profile':
                    this.navigateTo('profile');
                    break;
                case 'create-document':
                    this.navigateTo('create-documents');
                    break;
                case 'create-doc-type':
                    this.startDocumentCreation($btn.data('type'));
                    break;
                case 'view-checklists':
                    this.navigateTo('my-checklists');
                    break;
                case 'view-messages':
                    this.navigateTo('messages');
                    break;
                case 'new-message':
                    this.navigateTo('messages');
                    // Slight delay to ensure section loads, then trigger compose
                    setTimeout(function() {
                        var composeBtn = document.querySelector('.framt-compose-btn');
                        if (composeBtn) composeBtn.click();
                    }, 300);
                    break;
                case 'download-word':
                case 'download-pdf':
                    this.downloadDocument($btn.data('id') || $btn.data('doc-id'), action.replace('download-', ''));
                    break;
                case 'edit':
                case 'edit-document':
                    this.editDocument($btn.data('id') || $btn.data('doc-id'));
                    break;
                case 'delete':
                case 'delete-document':
                    this.deleteDocument($btn.data('id') || $btn.data('doc-id'));
                    break;
                case 'view-guide':
                    this.viewGuide($btn.data('guide'));
                    break;
                case 'generate-guide':
                    this.startGuideGeneration($btn.data('guide'));
                    break;
                case 'download-guide-word':
                case 'download-guide-pdf':
                    this.downloadGuide($btn.data('guide-id'), action.replace('download-guide-', ''));
                    break;
                case 'view-full-term':
                    this.viewFullTerm($btn.data('term'));
                    break;
                case 'show-requirements':
                    this.showRequirements($btn.data('type'), $btn.data('visa'));
                    break;
                case 'clear-verification':
                    this.clearVerification();
                    break;
                case 'back-to-guides':
                    this.loadSection('guides');
                    break;
                case 'back-to-documents':
                    this.loadSection('create-documents');
                    break;
                case 'guide-answer':
                    this.handleGuideAnswer($btn.data('question'), $btn.data('answer'));
                    break;
                case 'guide-answer-text':
                    var textVal = $('#guide-input-' + $btn.data('question')).val();
                    this.handleGuideAnswer($btn.data('question'), textVal);
                    break;
                case 'guide-answer-multi':
                    var multiVal = [];
                    $('input[name="' + $btn.data('question') + '"]:checked').each(function() {
                        multiVal.push($(this).val());
                    });
                    this.handleGuideAnswer($btn.data('question'), multiVal);
                    break;
                case 'answer-question':
                    this.handleDocAnswer($btn.data('answer'));
                    break;
                case 'submit-answer':
                    this.handleDocAnswer($btn.siblings('.framt-doc-input').val());
                    break;
                case 'generate-document':
                    this.generateDocument();
                    break;
                case 'start-profile-setup':
                    this.startProfileSetupChat();
                    break;
                case 'dismiss-reminder':
                    this.dismissReminder();
                    break;
                case 'download-generated-doc':
                    this.downloadGeneratedDocument($btn.data('doc-id'), $btn.data('format'));
                    break;
                case 'restart-doc-chat':
                    if (this.currentDocType) {
                        this.startDocumentCreation(this.currentDocType);
                    }
                    break;
                case 'explore-first':
                    this.showExploreMessage();
                    break;
                default:
                    if (action) {
                        this.navigateTo(action);
                    }
            }
        },

        /**
         * Navigate to a section
         * @param {string} section - Section to navigate to
         */
        navigateTo: function(section) {
            if (typeof FRAMemberTools !== 'undefined' && FRAMemberTools.navigateToSection) {
                FRAMemberTools.navigateToSection(section);
            } else {
                var $link = $('[data-section="' + section + '"]').first();
                if ($link.length) {
                    $link.trigger('click');
                } else {
                    document.dispatchEvent(new CustomEvent('fra:navigate', {
                        detail: { section: section }
                    }));
                }
            }
        },

        /**
         * Start onboarding flow
         */
        startOnboarding: function() {
            this.navigateTo('profile');
        },
        
        /**
         * Show welcome chat with AI assistant
         */
        showWelcomeChat: function() {
            // Check if this is a new signup (URL had new_signup param) - they already saw welcome
            var urlParams = new URLSearchParams(window.location.search);
            var isNewSignup = sessionStorage.getItem('framt_new_signup') === '1';
            
            // If new signup, mark it and don't show anything
            if (urlParams.get('new_signup') === '1') {
                sessionStorage.setItem('framt_new_signup', '1');
            }
            
            // Show profile reminder for returning users with incomplete profile (< 50%)
            if (!isNewSignup && framtData.profileCompletion < 50) {
                this.showProfileReminder();
            }
            
            this.bindWelcomeChatEvents();
        },
        
        /**
         * Show profile reminder for returning users
         */
        showProfileReminder: function() {
            // Try multiple container selectors
            var container = document.querySelector('.framt-dashboard') || 
                           document.getElementById('framt-section-content') ||
                           document.getElementById('fra-member-content-body');
            if (!container) return;
            
            // Don't show if reminder already exists
            if (container.querySelector('.framt-profile-reminder')) return;
            
            var userName = framtData.userName || 'there';
            var completion = framtData.profileCompletion || 0;
            
            var reminderHtml = '<div class="framt-welcome-chat framt-profile-reminder">' +
                '<div class="framt-chat-message framt-chat-ai">' +
                    '<div class="framt-chat-avatar">🇫🇷</div>' +
                    '<div class="framt-chat-bubble">' +
                        '<p><strong>Welcome back, ' + this.escapeHtml(userName) + '!</strong></p>' +
                        '<p>Your profile is ' + completion + '% complete. Finishing your <strong>Visa Profile</strong> helps us personalize all documents, guides, and checklists specifically for your situation.</p>' +
                        '<p class="framt-chat-note"><em>It only takes 2-3 minutes and makes everything more relevant to you.</em></p>' +
                    '</div>' +
                '</div>' +
                '<div class="framt-welcome-actions">' +
                    '<button type="button" class="framt-btn framt-btn-primary" data-action="start-profile-setup">✏️ Complete My Profile</button>' +
                    '<button type="button" class="framt-btn framt-btn-secondary framt-btn-text" data-action="dismiss-reminder">Maybe later</button>' +
                '</div>' +
            '</div>';
            
            // Prepend to container
            container.insertAdjacentHTML('afterbegin', reminderHtml);
        },
        
        /**
         * Render welcome chat UI (legacy - keeping for reference)
         * @returns {string} HTML string
         */
        renderWelcomeChatUI: function() {
            // This is now handled by showProfileReminder for returning users
            // New users see the welcome message on the main page before reaching dashboard
            return '';
        },
        
        /**
         * Bind welcome chat event handlers
         */
        bindWelcomeChatEvents: function() {
            var self = this;
            
            $(document).off('click.welcome-chat').on('click.welcome-chat', '[data-action="start-profile-setup"]', function(e) {
                e.preventDefault();
                self.startProfileSetupChat();
            });
            
            $(document).off('click.explore-first').on('click.explore-first', '[data-action="explore-first"]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.dismissReminder();
            });
            
            $(document).off('click.dismiss-reminder').on('click.dismiss-reminder', '[data-action="dismiss-reminder"]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.dismissReminder();
            });
        },
        
        /**
         * Dismiss the profile reminder
         */
        dismissReminder: function() {
            var container = document.querySelector('.framt-profile-reminder');
            if (container) {
                $(container).fadeOut(300, function() {
                    $(this).remove();
                });
            }
        },
        
        /**
         * Start profile setup from chat
         */
        startProfileSetupChat: function() {
            var container = document.querySelector('.framt-welcome-chat, .framt-profile-reminder');
            if (container) {
                $(container).fadeOut(300, function() {
                    $(this).remove();
                    FRAMT.loadSection('profile');
                });
            } else {
                FRAMT.loadSection('profile');
            }
        },
        
        /**
         * Show explore message (legacy - now just dismisses)
         */
        showExploreMessage: function() {
            this.dismissReminder();
        },

        /**
         * Show loading overlay
         */
        showLoading: function() {
            if (!document.getElementById('framt-loading')) {
                var loading = document.createElement('div');
                loading.id = 'framt-loading';
                loading.className = 'framt-loading-overlay';
                loading.innerHTML = '<div class="framt-loading-spinner"></div>';
                document.body.appendChild(loading);
            }
        },
        
        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            var loading = document.getElementById('framt-loading');
            if (loading) loading.remove();
        },

        /**
         * Download document
         * @param {string} docId - Document ID
         * @param {string} format - 'word' or 'pdf'
         */
        downloadDocument: function(docId, format) {
            this.showLoading();
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_download_document',
                    nonce: framtData.nonce,
                    document_id: docId,
                    format: format
                },
                success: function(response) {
                    FRAMT.hideLoading();
                    if (response.success && response.data.url) {
                        window.location.href = response.data.url;
                    } else {
                        alert(response.data || 'Could not download document');
                    }
                },
                error: function() {
                    FRAMT.hideLoading();
                    alert('Error downloading document');
                }
            });
        },

        /**
         * Edit document
         * @param {string} docId - Document ID
         */
        editDocument: function(docId) {
            // Implementation for editing documents
            log('Edit document:', docId);
        },

        /**
         * Delete document
         * @param {string} docId - Document ID
         */
        deleteDocument: function(docId) {
            if (!confirm(framtData.strings.confirmDelete || 'Are you sure you want to delete this document?')) return;
            
            this.showLoading();
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_delete_document',
                    nonce: framtData.nonce,
                    document_id: docId
                },
                success: function(response) {
                    FRAMT.hideLoading();
                    if (response.success) {
                        // Stay on my-documents page and reload it
                        FRAMT.loadSection('my-documents');
                    } else {
                        alert(response.data || 'Could not delete document');
                    }
                },
                error: function() {
                    FRAMT.hideLoading();
                    alert('Error deleting document');
                }
            });
        },

        /**
         * View guide
         * @param {string} guideId - Guide identifier
         */
        viewGuide: function(guideId) {
            log('View guide:', guideId);
            this.showLoading();
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_load_guide',
                    nonce: framtData.nonce,
                    guide: guideId
                },
                success: function(response) {
                    FRAMT.hideLoading();
                    if (response.success && response.data.html) {
                        var container = document.getElementById('fra-member-content-body') || 
                                       document.getElementById('fra-member-content');
                        if (container) {
                            container.innerHTML = response.data.html;
                        }
                    } else {
                        alert(response.data?.message || 'Could not load guide');
                    }
                },
                error: function() {
                    FRAMT.hideLoading();
                    alert('Error loading guide');
                }
            });
        },

        /**
         * Start guide generation flow
         * @param {string} guideType - Type of guide
         */
        startGuideGeneration: function(guideType) {
            // Initialize guide chat context
            this.currentGuideContext = {
                type: guideType,
                step: 0,
                answers: {}
            };
            
            // Show chat interface
            var container = document.getElementById('fra-member-content-body') || 
                           document.getElementById('fra-member-content');
            
            if (!container) return;
            
            var guideTitles = {
                'apostille': 'Apostille Guide',
                'pet-relocation': 'Pet Relocation Guide',
                'french-mortgages': 'French Mortgage Guide',
                'bank-ratings': 'Bank Comparison Guide'
            };
            
            var title = guideTitles[guideType] || 'Personalized Guide';
            
            container.innerHTML = '<div class="framt-guide-chat-container">' +
                '<div class="framt-guide-chat-header">' +
                    '<button class="framt-btn framt-btn-small framt-btn-ghost" data-action="back-to-guides">← Back to Guides</button>' +
                    '<h2>' + this.escapeHtml(title) + '</h2>' +
                '</div>' +
                '<div class="framt-guide-chat-messages" id="framt-guide-chat-messages"></div>' +
                '<div class="framt-guide-chat-input-area" id="framt-guide-chat-input-area" style="display: none;">' +
                    '<input type="text" id="framt-guide-chat-input" class="framt-guide-chat-input" placeholder="Type your answer...">' +
                    '<button class="framt-btn framt-btn-primary" id="framt-guide-chat-send">Send</button>' +
                '</div>' +
            '</div>';
            
            // Start the chat flow
            this.sendGuideChatMessage('start', true);
        },
        
        /**
         * Send a message to the guide chat
         */
        sendGuideChatMessage: function(message, isStart) {
            var self = this;
            var ctx = this.currentGuideContext;
            
            if (!ctx) return;
            
            // Show user message (unless it's the start)
            if (!isStart && message !== 'start') {
                this.addGuideChatMessage(message, 'user');
            }
            
            // Show typing indicator
            this.showGuideTypingIndicator();
            
            // Hide input while processing
            var inputArea = document.getElementById('framt-guide-chat-input-area');
            if (inputArea) inputArea.style.display = 'none';
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                timeout: 120000,
                data: {
                    action: 'framt_guide_chat',
                    nonce: framtData.nonce,
                    guide_type: ctx.type,
                    message: message,
                    context: JSON.stringify({
                        step: ctx.step,
                        answers: ctx.answers
                    })
                },
                success: function(response) {
                    self.hideGuideTypingIndicator();
                    
                    if (response.success) {
                        var data = response.data;
                        
                        // Update context
                        if (typeof data.step !== 'undefined') {
                            ctx.step = data.step;
                        }
                        if (data.collected) {
                            ctx.answers = data.collected;
                        }
                        
                        // Show AI message
                        self.addGuideChatMessage(data.message, 'ai', data.options, data.multi_select);
                        
                        // Check if guide is ready
                        if (data.guide_ready) {
                            self.showGeneratedGuide({
                                guide_id: data.guide_id,
                                title: data.guide_title
                            });
                        } else if (data.show_input) {
                            // Show text input
                            var input = document.getElementById('framt-guide-chat-input');
                            if (inputArea) {
                                inputArea.style.display = 'flex';
                                if (input) {
                                    input.focus();
                                    input.placeholder = data.placeholder || 'Type your answer...';
                                }
                            }
                        }
                        // If options are provided, user clicks an option (input stays hidden)
                    } else {
                        self.addGuideChatMessage(response.data?.message || 'Sorry, there was an error. Please try again.', 'ai');
                        if (inputArea) inputArea.style.display = 'flex';
                    }
                },
                error: function(xhr, status, error) {
                    self.hideGuideTypingIndicator();
                    if (status === 'timeout') {
                        self.addGuideChatMessage('The request is taking longer than expected. Please try again.', 'ai');
                    } else {
                        self.addGuideChatMessage('Connection error. Please try again.', 'ai');
                    }
                    if (inputArea) inputArea.style.display = 'flex';
                }
            });
        },
        
        /**
         * Add a message to the guide chat
         */
        addGuideChatMessage: function(content, role, options, multiSelect) {
            var messagesContainer = document.getElementById('framt-guide-chat-messages');
            if (!messagesContainer) return;
            
            var self = this;
            var messageDiv = document.createElement('div');
            messageDiv.className = 'framt-guide-chat-message framt-guide-chat-' + role;
            
            var avatarHtml = role === 'ai' ? 
                '<div class="framt-guide-chat-avatar">🇫🇷</div>' : 
                '<div class="framt-guide-chat-avatar">👤</div>';
            
            // Format content (convert markdown-style bold and italic)
            var formattedContent = this.escapeHtml(content)
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/_([^_]+)_/g, '<em>$1</em>')
                .replace(/\n/g, '<br>');
            
            var contentHtml = '<div class="framt-guide-chat-bubble">' + formattedContent + '</div>';
            
            messageDiv.innerHTML = avatarHtml + contentHtml;
            messagesContainer.appendChild(messageDiv);
            
            // Add options if provided
            if (options && options.length > 0) {
                var optionsDiv = document.createElement('div');
                optionsDiv.className = 'framt-guide-chat-options';
                
                for (var i = 0; i < options.length; i++) {
                    var opt = options[i];
                    var btn = document.createElement('button');
                    btn.className = 'framt-guide-chat-option';
                    btn.setAttribute('data-value', opt.value);
                    btn.textContent = opt.label;
                    btn.addEventListener('click', function() {
                        var value = this.getAttribute('data-value');
                        // Disable all options
                        optionsDiv.querySelectorAll('button').forEach(function(b) {
                            b.disabled = true;
                        });
                        this.classList.add('selected');
                        self.sendGuideChatMessage(value, false);
                    });
                    optionsDiv.appendChild(btn);
                }
                
                messagesContainer.appendChild(optionsDiv);
            }
            
            // Add multi-select if provided
            if (multiSelect && multiSelect.length > 0) {
                var multiDiv = document.createElement('div');
                multiDiv.className = 'framt-guide-chat-multi';
                
                for (var j = 0; j < multiSelect.length; j++) {
                    var mopt = multiSelect[j];
                    var label = document.createElement('label');
                    label.className = 'framt-guide-multi-option';
                    label.innerHTML = '<input type="checkbox" value="' + self.escapeHtml(mopt.value) + '">' +
                        '<span>' + self.escapeHtml(mopt.label) + '</span>';
                    multiDiv.appendChild(label);
                }
                
                var continueBtn = document.createElement('button');
                continueBtn.className = 'framt-btn framt-btn-primary framt-guide-multi-continue';
                continueBtn.textContent = 'Continue';
                continueBtn.addEventListener('click', function() {
                    var selected = [];
                    multiDiv.querySelectorAll('input:checked').forEach(function(cb) {
                        selected.push(cb.value);
                    });
                    if (selected.length === 0) {
                        alert('Please select at least one option');
                        return;
                    }
                    // Disable inputs
                    multiDiv.querySelectorAll('input').forEach(function(cb) {
                        cb.disabled = true;
                    });
                    this.disabled = true;
                    self.sendGuideChatMessage(selected.join(','), false);
                });
                multiDiv.appendChild(continueBtn);
                
                messagesContainer.appendChild(multiDiv);
            }
            
            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        },
        
        /**
         * Show typing indicator for guide chat
         */
        showGuideTypingIndicator: function() {
            var messagesContainer = document.getElementById('framt-guide-chat-messages');
            if (!messagesContainer) return;
            
            var existingIndicator = messagesContainer.querySelector('.framt-guide-typing');
            if (existingIndicator) return;
            
            var indicator = document.createElement('div');
            indicator.className = 'framt-guide-chat-message framt-guide-chat-ai framt-guide-typing';
            indicator.innerHTML = '<div class="framt-guide-chat-avatar">🇫🇷</div>' +
                '<div class="framt-guide-chat-bubble">' +
                    '<div class="framt-typing-dots"><span></span><span></span><span></span></div>' +
                '</div>';
            messagesContainer.appendChild(indicator);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        },
        
        /**
         * Hide typing indicator for guide chat
         */
        hideGuideTypingIndicator: function() {
            var indicator = document.querySelector('.framt-guide-typing');
            if (indicator) {
                indicator.remove();
            }
        },

        /**
         * Show guide question (legacy - kept for compatibility)
         * @param {number} index - Question index
         */
        showGuideQuestion: function(index) {
            var ctx = this.currentGuideContext;
            if (!ctx || !ctx.questions || index >= ctx.questions.length) {
                this.generateGuide();
                return;
            }
            
            var question = ctx.questions[index];
            var container = document.getElementById('fra-member-content-body') || 
                           document.getElementById('fra-member-content');
            
            if (!container) return;
            
            var self = this;
            var html = '<div class="framt-guide-flow">' +
                '<div class="framt-guide-header">' +
                    '<button class="framt-btn framt-btn-small framt-btn-ghost" data-action="back-to-guides">← Back to Guides</button>' +
                    '<h2>' + this.escapeHtml(ctx.title || 'Guide') + '</h2>' +
                    '<div class="framt-guide-progress">' +
                        '<div class="framt-progress-bar"><div class="framt-progress-fill" style="width: ' + ((index / ctx.questions.length) * 100) + '%"></div></div>' +
                        '<span>Question ' + (index + 1) + ' of ' + ctx.questions.length + '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="framt-guide-question">' +
                    '<h3>' + this.escapeHtml(question.question) + '</h3>';
            
            // Handle choice questions (options as object: {value: label})
            if ((question.type === 'choice' || question.type === 'multi_choice') && question.options) {
                html += '<div class="framt-guide-options">';
                
                // Options come as object {value: label} from PHP
                var optionKeys = Object.keys(question.options);
                for (var i = 0; i < optionKeys.length; i++) {
                    var optValue = optionKeys[i];
                    var optLabel = question.options[optValue];
                    html += '<button class="framt-guide-option" data-action="guide-answer" data-question="' + question.id + '" data-answer="' + self.escapeHtml(optValue) + '">' +
                        '<span class="framt-option-label">' + self.escapeHtml(optLabel) + '</span>' +
                    '</button>';
                }
                html += '</div>';
            } else if (question.type === 'multi' && question.options) {
                html += '<div class="framt-guide-multi">';
                var multiKeys = Object.keys(question.options);
                for (var j = 0; j < multiKeys.length; j++) {
                    var moptValue = multiKeys[j];
                    var moptLabel = question.options[moptValue];
                    html += '<label class="framt-multi-option">' +
                        '<input type="checkbox" name="' + question.id + '" value="' + self.escapeHtml(moptValue) + '">' +
                        '<span>' + self.escapeHtml(moptLabel) + '</span>' +
                    '</label>';
                }
                html += '</div>' +
                    '<button class="framt-btn framt-btn-primary" data-action="guide-answer-multi" data-question="' + question.id + '">Continue</button>';
            } else {
                // Text input for currency, text, date types
                var inputType = 'text';
                var placeholder = question.placeholder || 'Type your answer...';
                
                html += '<div class="framt-guide-input">' +
                    '<input type="' + inputType + '" id="guide-input-' + question.id + '" placeholder="' + self.escapeHtml(placeholder) + '">' +
                    '<button class="framt-btn framt-btn-primary" data-action="guide-answer-text" data-question="' + question.id + '">Continue</button>' +
                '</div>';
            }
            
            html += '</div></div>';
            container.innerHTML = html;
            
            ctx.questionIndex = index;
        },

        /**
         * Handle guide answer
         * @param {string} questionId - Question ID
         * @param {*} answer - Answer value
         */
        handleGuideAnswer: function(questionId, answer) {
            var ctx = this.currentGuideContext;
            if (!ctx) return;
            
            ctx.answers[questionId] = answer;
            this.showGuideQuestion(ctx.questionIndex + 1);
        },

        /**
         * Generate guide with collected answers
         */
        generateGuide: function() {
            var ctx = this.currentGuideContext;
            if (!ctx) return;
            
            var container = document.getElementById('fra-member-content-body') || 
                           document.getElementById('fra-member-content');
            
            if (container) {
                container.innerHTML = '<div class="framt-generating-guide">' +
                    '<div class="framt-spinner"></div>' +
                    '<h2>Generating Your Personalized Guide...</h2>' +
                    '<p>Our AI is creating a comprehensive guide based on your answers.</p>' +
                    '<p class="framt-generating-note">This may take 15-30 seconds.</p>' +
                '</div>';
            }
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                timeout: 120000,
                data: {
                    action: 'framt_generate_guide',
                    nonce: framtData.nonce,
                    guide_type: ctx.type,
                    answers: ctx.answers
                },
                success: function(response) {
                    if (response.success) {
                        FRAMT.showGeneratedGuide(response.data);
                    } else {
                        alert(response.data?.message || 'Could not generate guide');
                        FRAMT.loadSection('guides');
                    }
                },
                error: function() {
                    alert('Error generating guide. Please try again.');
                    FRAMT.loadSection('guides');
                }
            });
        },

        /**
         * Show generated guide
         * @param {Object} data - Guide data from server
         */
        showGeneratedGuide: function(data) {
            var container = document.getElementById('fra-member-content-body') || 
                           document.getElementById('fra-member-content');
            
            if (!container) return;
            
            var html = '<div class="framt-guide-result">' +
                '<div class="framt-guide-header">' +
                    '<button class="framt-btn framt-btn-small framt-btn-ghost" data-action="back-to-guides">← Back to Guides</button>' +
                    '<h2>' + this.escapeHtml(data.title) + '</h2>';
            
            if (data.ai_generated) {
                html += '<p class="framt-ai-badge">✨ AI-Generated & Personalized</p>';
            }
            
            html += '</div>' +
                '<div class="framt-guide-content">';
            
            if (data.preview) {
                html += '<div class="framt-guide-preview"><p>' + this.escapeHtml(data.preview) + '</p></div>';
            }
            
            html += '</div>' +
                '<div class="framt-guide-actions">' +
                    '<button class="framt-btn framt-btn-primary framt-btn-large" data-action="download-guide-word" data-guide-id="' + data.guide_id + '">📄 Download Word Document</button>' +
                    '<button class="framt-btn framt-btn-secondary framt-btn-large" data-action="download-guide-pdf" data-guide-id="' + data.guide_id + '">🖨️ Print / Save as PDF</button>' +
                '</div>' +
            '</div>';
            
            container.innerHTML = html;
        },

        /**
         * Download generated guide
         * @param {string} guideId - Guide ID
         * @param {string} format - 'word' or 'pdf'
         */
        downloadGuide: function(guideId, format) {
            this.showLoading();
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_download_guide',
                    nonce: framtData.nonce,
                    guide_id: guideId,
                    format: format
                },
                success: function(response) {
                    FRAMT.hideLoading();
                    if (response.success && response.data.url) {
                        if (format === 'pdf') {
                            window.open(response.data.url, '_blank');
                        } else {
                            window.location.href = response.data.url;
                        }
                    } else {
                        alert(response.data?.message || 'Could not download guide');
                    }
                },
                error: function() {
                    FRAMT.hideLoading();
                    alert('Error downloading guide. Please try again.');
                }
            });
        },

        /**
         * Start document creation flow - chat-based
         * @param {string} docType - Document type
         */
        startDocumentCreation: function(docType) {
            var self = this;
            this.currentDocType = docType;
            this.currentDocContext = {
                type: docType,
                step: 0,
                messages: [],
                answers: {}
            };
            
            var container = document.getElementById('fra-member-content-body') || 
                           document.getElementById('fra-member-content');
            if (!container) return;
            
            // Get document type info
            var docTitles = {
                'cover-letter': { title: 'Visa Cover Letter', icon: '✉️' },
                'financial-statement': { title: 'Proof of Sufficient Means', icon: '💰' },
                'attestation': { title: 'No Work Attestation', icon: '📜' },
                'accommodation-letter': { title: 'Proof of Accommodation', icon: '🏠' }
            };
            var docInfo = docTitles[docType] || { title: 'Document', icon: '📄' };
            
            // Render chat interface
            container.innerHTML = this.renderDocChatInterface(docInfo);
            
            // Start the conversation with AI
            this.sendDocChatMessage('start', true);
        },
        
        /**
         * Render document chat interface
         */
        renderDocChatInterface: function(docInfo) {
            return '<div class="framt-doc-chat">' +
                '<div class="framt-doc-chat-header">' +
                    '<button class="framt-btn framt-btn-small framt-btn-ghost" data-action="back-to-documents">← Back</button>' +
                    '<h2>' + docInfo.icon + ' Create ' + this.escapeHtml(docInfo.title) + '</h2>' +
                '</div>' +
                '<div class="framt-doc-chat-messages" id="framt-doc-chat-messages">' +
                    '<div class="framt-doc-chat-loading"><span class="framt-typing-indicator">●●●</span></div>' +
                '</div>' +
                '<div class="framt-doc-chat-input-area" id="framt-doc-chat-input-area" style="display: none;">' +
                    '<input type="text" id="framt-doc-chat-input" class="framt-doc-chat-input" placeholder="Type your answer...">' +
                    '<button class="framt-btn framt-btn-primary" id="framt-doc-chat-send">Send</button>' +
                '</div>' +
            '</div>';
        },
        
        /**
         * Send message in document chat
         */
        sendDocChatMessage: function(message, isStart) {
            var self = this;
            var messagesContainer = document.getElementById('framt-doc-chat-messages');
            var inputArea = document.getElementById('framt-doc-chat-input-area');
            var input = document.getElementById('framt-doc-chat-input');
            
            if (!isStart && message && message !== 'start') {
                // Add user message to chat
                this.addDocChatMessage(message, 'user');
                this.currentDocContext.messages.push({ role: 'user', content: message });
            }
            
            // If this was the last question, show generating indicator immediately
            if (this.currentDocContext.isLastQuestion && !isStart) {
                this.showDocGeneratingIndicator();
            } else {
                this.showDocTypingIndicator();
            }
            
            // Hide input while AI is responding
            if (inputArea) inputArea.style.display = 'none';
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                timeout: 90000, // 90 second timeout for AI generation
                data: {
                    action: 'framt_doc_chat',
                    nonce: framtData.nonce,
                    document_type: this.currentDocType,
                    message: message,
                    context: JSON.stringify(this.currentDocContext)
                },
                success: function(response) {
                    self.hideDocTypingIndicator();
                    
                    if (response.success) {
                        // Add AI response
                        self.addDocChatMessage(response.data.message, 'ai', response.data.options);
                        self.currentDocContext.messages.push({ role: 'assistant', content: response.data.message });
                        
                        // Update step from server response
                        if (response.data.step !== undefined) {
                            self.currentDocContext.step = response.data.step;
                        }
                        
                        // Track if next question is the last
                        self.currentDocContext.isLastQuestion = response.data.is_last_question || false;
                        
                        // Update context with any collected data
                        if (response.data.collected) {
                            self.currentDocContext.answers = response.data.collected;
                        }
                        
                        // Check if document is ready
                        if (response.data.document_ready) {
                            self.showDocumentReady(response.data);
                        } else if (response.data.show_input) {
                            // Show input for text responses
                            if (inputArea) {
                                inputArea.style.display = 'flex';
                                if (input) {
                                    input.focus();
                                    input.placeholder = response.data.placeholder || 'Type your answer...';
                                }
                            }
                        }
                        // If options are provided, input is hidden (user clicks an option)
                    } else {
                        self.addDocChatMessage('Sorry, there was an error. Please try again.', 'ai');
                        if (inputArea) inputArea.style.display = 'flex';
                    }
                },
                error: function(xhr, status, error) {
                    self.hideDocTypingIndicator();
                    if (status === 'timeout') {
                        self.addDocChatMessage('The document is taking longer than expected. Please try again.', 'ai');
                    } else {
                        self.addDocChatMessage('Connection error. Please try again.', 'ai');
                    }
                    if (inputArea) inputArea.style.display = 'flex';
                }
            });
        },
        
        /**
         * Add message to document chat
         */
        addDocChatMessage: function(content, role, options) {
            var messagesContainer = document.getElementById('framt-doc-chat-messages');
            if (!messagesContainer) return;
            
            var messageDiv = document.createElement('div');
            messageDiv.className = 'framt-doc-chat-message framt-doc-chat-' + role;
            
            var avatarHtml = role === 'ai' ? 
                '<div class="framt-doc-chat-avatar">🇫🇷</div>' : 
                '<div class="framt-doc-chat-avatar">👤</div>';
            
            var contentHtml = '<div class="framt-doc-chat-bubble">' + this.formatDocChatContent(content) + '</div>';
            
            messageDiv.innerHTML = avatarHtml + contentHtml;
            messagesContainer.appendChild(messageDiv);
            
            // Add options if provided
            if (options && options.length > 0) {
                var optionsDiv = document.createElement('div');
                optionsDiv.className = 'framt-doc-chat-options';
                
                for (var i = 0; i < options.length; i++) {
                    var opt = options[i];
                    var btn = document.createElement('button');
                    btn.className = 'framt-doc-chat-option';
                    btn.textContent = opt.label;
                    btn.setAttribute('data-value', opt.value);
                    optionsDiv.appendChild(btn);
                }
                messagesContainer.appendChild(optionsDiv);
            }
            
            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        },
        
        /**
         * Format document chat content
         */
        formatDocChatContent: function(content) {
            if (!content) return '';
            
            // Convert newlines to paragraphs
            var paragraphs = content.split('\n\n');
            var html = '';
            
            for (var i = 0; i < paragraphs.length; i++) {
                var p = paragraphs[i].trim();
                if (p) {
                    // Convert single newlines to <br>
                    p = p.replace(/\n/g, '<br>');
                    // Convert **bold**
                    p = p.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
                    // Convert _italic_
                    p = p.replace(/_\(([^)]+)\)_/g, '<em>($1)</em>');
                    p = p.replace(/_([^_]+)_/g, '<em>$1</em>');
                    html += '<p>' + p + '</p>';
                }
            }
            
            return html || content;
        },
        
        /**
         * Show typing indicator
         */
        showDocTypingIndicator: function() {
            var messagesContainer = document.getElementById('framt-doc-chat-messages');
            if (!messagesContainer) return;
            
            // Remove existing indicator
            var existing = messagesContainer.querySelector('.framt-doc-chat-loading');
            if (existing) existing.remove();
            
            var indicator = document.createElement('div');
            indicator.className = 'framt-doc-chat-loading';
            indicator.innerHTML = '<div class="framt-doc-chat-avatar">🇫🇷</div><span class="framt-typing-indicator">●●●</span>';
            messagesContainer.appendChild(indicator);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        },
        
        /**
         * Show generating document indicator
         */
        showDocGeneratingIndicator: function() {
            var messagesContainer = document.getElementById('framt-doc-chat-messages');
            if (!messagesContainer) return;
            
            // Remove existing indicator
            var existing = messagesContainer.querySelector('.framt-doc-chat-loading');
            if (existing) existing.remove();
            
            var indicator = document.createElement('div');
            indicator.className = 'framt-doc-chat-loading framt-doc-generating';
            indicator.innerHTML = '<div class="framt-doc-chat-avatar">🇫🇷</div>' +
                '<div class="framt-generating-message">' +
                    '<div class="framt-generating-icon">📝</div>' +
                    '<div class="framt-generating-text">' +
                        '<strong>Generating your document...</strong>' +
                        '<p>This may take 15-30 seconds. Please wait.</p>' +
                    '</div>' +
                    '<div class="framt-generating-spinner"></div>' +
                '</div>';
            messagesContainer.appendChild(indicator);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        },
        
        /**
         * Hide typing indicator
         */
        hideDocTypingIndicator: function() {
            var indicator = document.querySelector('.framt-doc-chat-loading');
            if (indicator) indicator.remove();
        },
        
        /**
         * Show document ready UI
         */
        showDocumentReady: function(data) {
            var messagesContainer = document.getElementById('framt-doc-chat-messages');
            if (!messagesContainer) return;
            
            var readyDiv = document.createElement('div');
            readyDiv.className = 'framt-doc-ready';
            readyDiv.innerHTML = '<div class="framt-doc-ready-content">' +
                '<h3>✅ Your document is ready!</h3>' +
                '<p>' + this.escapeHtml(data.document_title || 'Document') + '</p>' +
                '<p class="framt-doc-ready-note">Your document will be saved to "My Visa Documents" when you download it.</p>' +
                '<div class="framt-doc-ready-actions">' +
                    '<button class="framt-btn framt-btn-primary framt-btn-large" data-action="download-generated-doc" data-format="word" data-doc-id="' + data.document_id + '">📄 Download Word</button>' +
                    '<button class="framt-btn framt-btn-secondary framt-btn-large" data-action="download-generated-doc" data-format="pdf" data-doc-id="' + data.document_id + '">🖨️ Print / PDF</button>' +
                '</div>' +
                '<div class="framt-doc-ready-secondary">' +
                    '<button class="framt-btn framt-btn-ghost" data-action="restart-doc-chat">🔄 Start Over</button>' +
                    '<button class="framt-btn framt-btn-ghost" data-action="back-to-documents">📁 Create Different Document</button>' +
                '</div>' +
            '</div>';
            messagesContainer.appendChild(readyDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        },
        
        /**
         * Download generated document
         */
        downloadGeneratedDocument: function(docId, format) {
            this.showLoading();
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_download_generated_document',
                    nonce: framtData.nonce,
                    document_id: docId,
                    format: format
                },
                success: function(response) {
                    FRAMT.hideLoading();
                    if (response.success && response.data.url) {
                        if (format === 'pdf') {
                            window.open(response.data.url, '_blank');
                        } else {
                            window.location.href = response.data.url;
                        }
                    } else {
                        alert(response.data?.message || 'Could not download document');
                    }
                },
                error: function() {
                    FRAMT.hideLoading();
                    alert('Error downloading document');
                }
            });
        },

        /**
         * Generate document
         */
        generateDocument: function() {
            this.showLoading();
            
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_generate_document',
                    nonce: framtData.nonce,
                    document_type: this.currentDocType,
                    answers: this.currentDocContext ? this.currentDocContext.answers : {}
                },
                success: function(response) {
                    FRAMT.hideLoading();
                    if (response.success) {
                        var container = document.getElementById('fra-member-content-body') || 
                                       document.getElementById('fra-member-content');
                        if (container) {
                            container.innerHTML = response.data.html;
                        }
                    } else {
                        alert(response.data?.message || 'Could not generate document');
                    }
                },
                error: function() {
                    FRAMT.hideLoading();
                    alert('Error generating document');
                }
            });
        },

        /**
         * View full glossary term
         * @param {string} term - Term identifier
         */
        viewFullTerm: function(term) {
            // Implementation for viewing full glossary term
            log('View term:', term);
        },

        /**
         * Show requirements
         * @param {string} type - Requirement type
         * @param {string} visa - Visa type
         */
        showRequirements: function(type, visa) {
            // Implementation for showing requirements
            log('Show requirements:', type, visa);
        },

        /**
         * Clear verification results
         */
        clearVerification: function() {
            var self = this;
            $.ajax({
                url: framtData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'framt_clear_health_verification',
                    nonce: framtData.nonce
                },
                success: function() {
                    self.healthVerificationContext = null;
                    FRAMT.loadSection('upload-verify');
                }
            });
        },

        /**
         * Initialize glossary features
         */
        initGlossary: function() {
            var searchInput = document.getElementById('framt-glossary-search');
            var categoryFilter = document.getElementById('framt-glossary-category');
            
            if (searchInput) {
                var self = this;
                var debounceTimer;
                
                searchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function() {
                        self.filterGlossary();
                    }, 200);
                });
            }
            
            if (categoryFilter) {
                categoryFilter.addEventListener('change', this.filterGlossary.bind(this));
            }
        },
        
        /**
         * Filter glossary terms
         */
        filterGlossary: function() {
            var searchInput = document.getElementById('framt-glossary-search');
            var categoryFilter = document.getElementById('framt-glossary-category');
            var terms = document.querySelectorAll('.framt-glossary-term');
            
            var searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            var category = categoryFilter ? categoryFilter.value : '';
            
            terms.forEach(function(term) {
                var termText = (term.textContent || '').toLowerCase();
                var termCategory = term.dataset.category || '';
                
                var matchesSearch = !searchTerm || termText.indexOf(searchTerm) !== -1;
                var matchesCategory = !category || termCategory === category;
                
                term.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
            });
        }
    };

    // Initialize when DOM ready
    $(document).ready(function() {
        FRAMT.init();
    });

    // Expose globally for debugging
    window.FRAMT = FRAMT;

})(jQuery);
