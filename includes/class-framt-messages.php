<?php
/**
 * Messages/Support Ticket System
 *
 * Provides a messaging system between members and admin for priority support.
 * Members can create tickets, view responses, and reply.
 * Admin can view all tickets, respond, and close them.
 *
 * @package France_Relocation_Member_Tools
 * @since 1.0.64
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRAMT_Messages
 *
 * Handles all messaging functionality including database operations,
 * AJAX handlers, and rendering.
 */
class FRAMT_Messages {

    /**
     * Database table name for messages
     *
     * @var string
     */
    private $table_messages;

    /**
     * Database table name for replies
     *
     * @var string
     */
    private $table_replies;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_messages = $wpdb->prefix . 'framt_messages';
        $this->table_replies = $wpdb->prefix . 'framt_message_replies';

        // Register AJAX handlers
        add_action('wp_ajax_framt_get_messages', array($this, 'ajax_get_messages'));
        add_action('wp_ajax_framt_get_message', array($this, 'ajax_get_message'));
        add_action('wp_ajax_framt_create_message', array($this, 'ajax_create_message'));
        add_action('wp_ajax_framt_reply_message', array($this, 'ajax_reply_message'));
        add_action('wp_ajax_framt_delete_message', array($this, 'ajax_delete_message'));
        add_action('wp_ajax_framt_get_unread_count', array($this, 'ajax_get_unread_count'));
        
        // Admin AJAX handlers
        add_action('wp_ajax_framt_admin_get_messages', array($this, 'ajax_admin_get_messages'));
        add_action('wp_ajax_framt_admin_get_message', array($this, 'ajax_admin_get_message'));
        add_action('wp_ajax_framt_admin_reply_message', array($this, 'ajax_admin_reply_message'));
        add_action('wp_ajax_framt_admin_close_message', array($this, 'ajax_admin_close_message'));
        add_action('wp_ajax_framt_admin_reopen_message', array($this, 'ajax_admin_reopen_message'));
        add_action('wp_ajax_framt_admin_get_members', array($this, 'ajax_admin_get_members'));
        add_action('wp_ajax_framt_admin_compose_message', array($this, 'ajax_admin_compose_message'));
        add_action('wp_ajax_framt_admin_get_auto_settings', array($this, 'ajax_admin_get_auto_settings'));
        add_action('wp_ajax_framt_admin_save_auto_settings', array($this, 'ajax_admin_save_auto_settings'));

        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // MemberPress signup hook for welcome message
        add_action('mepr-signup', array($this, 'send_welcome_message'), 10, 1);
        add_action('mepr_after_signup', array($this, 'send_welcome_message'), 10, 1);
        
        // Fallback: WordPress user registration
        add_action('user_register', array($this, 'maybe_send_welcome_on_register'), 20, 1);
    }

    /**
     * Create database tables on plugin activation
     *
     * @return void
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_messages = $wpdb->prefix . 'framt_messages';
        $table_replies = $wpdb->prefix . 'framt_message_replies';

        $sql_messages = "CREATE TABLE $table_messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            subject varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'open',
            priority varchar(20) NOT NULL DEFAULT 'normal',
            has_unread_admin tinyint(1) NOT NULL DEFAULT 0,
            has_unread_user tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            closed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql_replies = "CREATE TABLE $table_replies (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            message_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            content longtext NOT NULL,
            is_admin tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY message_id (message_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_messages);
        dbDelta($sql_replies);
    }

    /**
     * Add admin menu page
     *
     * @return void
     */
    public function add_admin_menu() {
        // Get unread count for menu badge
        $unread_count = $this->get_admin_unread_count();
        $menu_title = 'Member Messages';
        if ($unread_count > 0) {
            $menu_title .= sprintf(' <span class="awaiting-mod">%d</span>', $unread_count);
        }

        add_submenu_page(
            'france-relocation-assistant',
            'Member Messages',
            $menu_title,
            'manage_options',
            'fra-messages',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Get admin unread count
     *
     * @return int
     */
    public function get_admin_unread_count() {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_messages} WHERE has_unread_admin = 1 AND status != 'closed'"
        );
    }

    /**
     * Get user unread count
     *
     * @param int $user_id User ID
     * @return int
     */
    public function get_user_unread_count($user_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_messages} WHERE user_id = %d AND has_unread_user = 1",
            $user_id
        ));
    }

    /**
     * AJAX: Get messages for current user
     *
     * @return void
     */
    public function ajax_get_messages() {
        check_ajax_referer('framt_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $user_id = get_current_user_id();
        $messages = $this->get_user_messages($user_id);

        wp_send_json_success(array(
            'messages' => $messages,
            'unread_count' => $this->get_user_unread_count($user_id)
        ));
    }

    /**
     * Get messages for a user
     *
     * @param int $user_id User ID
     * @return array
     */
    public function get_user_messages($user_id) {
        global $wpdb;

        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, 
                    (SELECT COUNT(*) FROM {$this->table_replies} WHERE message_id = m.id) as reply_count,
                    (SELECT content FROM {$this->table_replies} WHERE message_id = m.id ORDER BY created_at DESC LIMIT 1) as last_reply
             FROM {$this->table_messages} m
             WHERE m.user_id = %d
             ORDER BY m.updated_at DESC",
            $user_id
        ), ARRAY_A);

        return $messages;
    }

    /**
     * AJAX: Get single message with replies
     *
     * @return void
     */
    public function ajax_get_message() {
        check_ajax_referer('framt_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $message_id = intval($_POST['message_id']);
        $user_id = get_current_user_id();

        $message = $this->get_message($message_id, $user_id);

        if (!$message) {
            wp_send_json_error(array('message' => 'Message not found'));
        }

        // Mark as read by user
        $this->mark_read_by_user($message_id);

        $replies = $this->get_replies($message_id);

        wp_send_json_success(array(
            'message' => $message,
            'replies' => $replies
        ));
    }

    /**
     * Get a single message
     *
     * @param int $message_id Message ID
     * @param int $user_id User ID (for permission check)
     * @return array|null
     */
    public function get_message($message_id, $user_id = null) {
        global $wpdb;

        $where = $user_id ? $wpdb->prepare(" AND user_id = %d", $user_id) : "";

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_messages} WHERE id = %d $where",
            $message_id
        ), ARRAY_A);
    }

    /**
     * Get replies for a message
     *
     * @param int $message_id Message ID
     * @return array
     */
    public function get_replies($message_id) {
        global $wpdb;

        $replies = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name as author_name
             FROM {$this->table_replies} r
             LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
             WHERE r.message_id = %d
             ORDER BY r.created_at ASC",
            $message_id
        ), ARRAY_A);

        return $replies;
    }

    /**
     * Mark message as read by user
     *
     * @param int $message_id Message ID
     * @return void
     */
    public function mark_read_by_user($message_id) {
        global $wpdb;
        $wpdb->update(
            $this->table_messages,
            array('has_unread_user' => 0),
            array('id' => $message_id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * Mark message as read by admin
     *
     * @param int $message_id Message ID
     * @return void
     */
    public function mark_read_by_admin($message_id) {
        global $wpdb;
        $wpdb->update(
            $this->table_messages,
            array('has_unread_admin' => 0),
            array('id' => $message_id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * AJAX: Create new message
     *
     * @return void
     */
    public function ajax_create_message() {
        check_ajax_referer('framt_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $user_id = get_current_user_id();
        $subject = sanitize_text_field($_POST['subject']);
        $content = sanitize_textarea_field($_POST['content']);

        if (empty($subject) || empty($content)) {
            wp_send_json_error(array('message' => 'Subject and message are required'));
        }

        $message_id = $this->create_message($user_id, $subject, $content);

        if ($message_id) {
            // Send notification email to admin
            $this->send_admin_notification($message_id, 'new');
            
            wp_send_json_success(array(
                'message' => 'Message sent successfully',
                'message_id' => $message_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to create message'));
        }
    }

    /**
     * Create a new message
     *
     * @param int $user_id User ID
     * @param string $subject Subject
     * @param string $content Content
     * @return int|false Message ID or false
     */
    public function create_message($user_id, $subject, $content) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table_messages,
            array(
                'user_id' => $user_id,
                'subject' => $subject,
                'status' => 'open',
                'has_unread_admin' => 1,
                'has_unread_user' => 0
            ),
            array('%d', '%s', '%s', '%d', '%d')
        );

        if (!$inserted) {
            return false;
        }

        $message_id = $wpdb->insert_id;

        // Add initial content as first reply
        $wpdb->insert(
            $this->table_replies,
            array(
                'message_id' => $message_id,
                'user_id' => $user_id,
                'content' => $content,
                'is_admin' => 0
            ),
            array('%d', '%d', '%s', '%d')
        );

        return $message_id;
    }

    /**
     * AJAX: Reply to message (user)
     *
     * @return void
     */
    public function ajax_reply_message() {
        check_ajax_referer('framt_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $message_id = intval($_POST['message_id']);
        $content = sanitize_textarea_field($_POST['content']);
        $user_id = get_current_user_id();

        // Verify ownership
        $message = $this->get_message($message_id, $user_id);
        if (!$message) {
            wp_send_json_error(array('message' => 'Message not found'));
        }

        if (empty($content)) {
            wp_send_json_error(array('message' => 'Reply content is required'));
        }

        $reply_id = $this->add_reply($message_id, $user_id, $content, false);

        if ($reply_id) {
            // Send notification to admin
            $this->send_admin_notification($message_id, 'reply');
            
            wp_send_json_success(array('message' => 'Reply sent'));
        } else {
            wp_send_json_error(array('message' => 'Failed to send reply'));
        }
    }

    /**
     * Add a reply to a message
     *
     * @param int $message_id Message ID
     * @param int $user_id User ID
     * @param string $content Content
     * @param bool $is_admin Is admin reply
     * @return int|false Reply ID or false
     */
    public function add_reply($message_id, $user_id, $content, $is_admin = false) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table_replies,
            array(
                'message_id' => $message_id,
                'user_id' => $user_id,
                'content' => $content,
                'is_admin' => $is_admin ? 1 : 0
            ),
            array('%d', '%d', '%s', '%d')
        );

        if (!$inserted) {
            return false;
        }

        // Update message timestamp and unread flags
        $update_data = array('updated_at' => current_time('mysql'));
        
        if ($is_admin) {
            $update_data['has_unread_user'] = 1;
            $update_data['has_unread_admin'] = 0;
        } else {
            $update_data['has_unread_admin'] = 1;
            $update_data['has_unread_user'] = 0;
        }

        $wpdb->update(
            $this->table_messages,
            $update_data,
            array('id' => $message_id)
        );

        return $wpdb->insert_id;
    }

    /**
     * AJAX: Delete message (user)
     *
     * @return void
     */
    public function ajax_delete_message() {
        check_ajax_referer('framt_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $message_id = intval($_POST['message_id']);
        $user_id = get_current_user_id();

        // Verify ownership
        $message = $this->get_message($message_id, $user_id);
        if (!$message) {
            wp_send_json_error(array('message' => 'Message not found'));
        }

        $deleted = $this->delete_message($message_id);

        if ($deleted) {
            wp_send_json_success(array('message' => 'Message deleted'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete message'));
        }
    }

    /**
     * Delete a message and its replies
     *
     * @param int $message_id Message ID
     * @return bool
     */
    public function delete_message($message_id) {
        global $wpdb;

        // Delete replies first
        $wpdb->delete($this->table_replies, array('message_id' => $message_id), array('%d'));
        
        // Delete message
        $deleted = $wpdb->delete($this->table_messages, array('id' => $message_id), array('%d'));

        return $deleted !== false;
    }

    /**
     * AJAX: Get unread count for user
     *
     * @return void
     */
    public function ajax_get_unread_count() {
        check_ajax_referer('framt_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $user_id = get_current_user_id();
        $count = $this->get_user_unread_count($user_id);

        wp_send_json_success(array('count' => $count));
    }

    /**
     * Send email notification to admin
     *
     * @param int $message_id Message ID
     * @param string $type Notification type (new, reply)
     * @return void
     */
    public function send_admin_notification($message_id, $type = 'new') {
        $message = $this->get_message($message_id);
        if (!$message) {
            return;
        }

        $user = get_user_by('id', $message['user_id']);
        $admin_email = get_option('admin_email');
        
        // Get the latest reply content
        $replies = $this->get_replies($message_id);
        $latest_reply = !empty($replies) ? end($replies) : null;
        $message_content = $latest_reply ? $latest_reply['content'] : '';
        
        $subject = $type === 'new' 
            ? '🔔 [Relo2France] New Support Message: ' . $message['subject']
            : '💬 [Relo2France] Member Reply: ' . $message['subject'];

        // Build HTML email
        $body = '<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1e3a5f; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .header h2 { margin: 0; font-size: 18px; }
        .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; }
        .message-box { background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #e85a1b; margin: 15px 0; }
        .meta { color: #6b7280; font-size: 14px; margin-bottom: 10px; }
        .meta strong { color: #1e3a5f; }
        .button { display: inline-block; background: #e85a1b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin-top: 15px; }
        .footer { padding: 15px; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>' . ($type === 'new' ? '📩 New Support Message' : '💬 New Reply to Support Ticket') . '</h2>
        </div>
        <div class="content">
            <div class="meta">
                <strong>From:</strong> ' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')<br>
                <strong>Subject:</strong> ' . esc_html($message['subject']) . '<br>
                <strong>Status:</strong> ' . ucfirst($message['status']) . '
            </div>
            
            <div class="message-box">
                <strong>Message:</strong><br><br>
                ' . nl2br(esc_html($message_content)) . '
            </div>
            
            <a href="' . admin_url('admin.php?page=fra-messages&message_id=' . $message_id) . '" class="button">
                View & Respond
            </a>
        </div>
        <div class="footer">
            This is an automated notification from Relo2France Member Support System.
        </div>
    </div>
</body>
</html>';

        // Set content type to HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($admin_email, $subject, $body, $headers);
    }

    /**
     * Send email notification to user
     *
     * @param int $message_id Message ID
     * @param string $reply_content Reply content preview
     * @return void
     */
    public function send_user_notification($message_id, $reply_content) {
        $message = $this->get_message($message_id);
        if (!$message) {
            return;
        }

        $user = get_user_by('id', $message['user_id']);
        if (!$user) {
            return;
        }

        $subject = '[Relo2France] Reply to: ' . $message['subject'];

        $body = sprintf(
            "Hello %s,\n\n" .
            "You have received a reply to your support message.\n\n" .
            "Subject: %s\n\n" .
            "Reply Preview:\n%s\n\n" .
            "View the full conversation at:\n%s",
            $user->display_name,
            $message['subject'],
            wp_trim_words($reply_content, 50),
            home_url('/?view_message=' . $message_id)
        );

        wp_mail($user->user_email, $subject, $body);
    }

    // =========================================
    // ADMIN AJAX HANDLERS
    // =========================================

    /**
     * AJAX: Get all messages for admin
     *
     * @return void
     */
    public function ajax_admin_get_messages() {
        check_ajax_referer('framt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Not authorized'));
        }

        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
        $messages = $this->get_all_messages($status);

        wp_send_json_success(array(
            'messages' => $messages,
            'unread_count' => $this->get_admin_unread_count()
        ));
    }

    /**
     * Get all messages for admin
     *
     * @param string $status Filter by status
     * @return array
     */
    public function get_all_messages($status = 'all') {
        global $wpdb;

        $where = "";
        if ($status !== 'all') {
            $where = $wpdb->prepare(" WHERE m.status = %s", $status);
        }

        $messages = $wpdb->get_results(
            "SELECT m.*, u.display_name, u.user_email,
                    (SELECT COUNT(*) FROM {$this->table_replies} WHERE message_id = m.id) as reply_count
             FROM {$this->table_messages} m
             LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
             $where
             ORDER BY m.has_unread_admin DESC, m.updated_at DESC",
            ARRAY_A
        );

        return $messages;
    }

    /**
     * AJAX: Get single message for admin
     *
     * @return void
     */
    public function ajax_admin_get_message() {
        check_ajax_referer('framt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Not authorized'));
        }

        $message_id = intval($_POST['message_id']);
        $message = $this->get_message($message_id);

        if (!$message) {
            wp_send_json_error(array('message' => 'Message not found'));
        }

        // Mark as read by admin
        $this->mark_read_by_admin($message_id);

        // Get user info
        $user = get_user_by('id', $message['user_id']);
        $message['user_name'] = $user ? $user->display_name : 'Unknown';
        $message['user_email'] = $user ? $user->user_email : '';

        $replies = $this->get_replies($message_id);

        wp_send_json_success(array(
            'message' => $message,
            'replies' => $replies
        ));
    }

    /**
     * AJAX: Admin reply to message
     *
     * @return void
     */
    public function ajax_admin_reply_message() {
        check_ajax_referer('framt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Not authorized'));
        }

        $message_id = intval($_POST['message_id']);
        $content = sanitize_textarea_field($_POST['content']);
        $user_id = get_current_user_id();

        $message = $this->get_message($message_id);
        if (!$message) {
            wp_send_json_error(array('message' => 'Message not found'));
        }

        if (empty($content)) {
            wp_send_json_error(array('message' => 'Reply content is required'));
        }

        $reply_id = $this->add_reply($message_id, $user_id, $content, true);

        if ($reply_id) {
            // Reopen if closed
            if ($message['status'] === 'closed') {
                $this->update_message_status($message_id, 'open');
            }
            
            // Send notification to user
            $this->send_user_notification($message_id, $content);
            
            wp_send_json_success(array('message' => 'Reply sent'));
        } else {
            wp_send_json_error(array('message' => 'Failed to send reply'));
        }
    }

    /**
     * AJAX: Close message
     *
     * @return void
     */
    public function ajax_admin_close_message() {
        check_ajax_referer('framt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Not authorized'));
        }

        $message_id = intval($_POST['message_id']);
        $this->update_message_status($message_id, 'closed');

        wp_send_json_success(array('message' => 'Message closed'));
    }

    /**
     * AJAX: Reopen message
     *
     * @return void
     */
    public function ajax_admin_reopen_message() {
        check_ajax_referer('framt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Not authorized'));
        }

        $message_id = intval($_POST['message_id']);
        $this->update_message_status($message_id, 'open');

        wp_send_json_success(array('message' => 'Message reopened'));
    }

    /**
     * AJAX: Get members list for compose dropdown
     *
     * @return void
     */
    public function ajax_admin_get_members() {
        check_ajax_referer('framt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Not authorized'));
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        $args = array(
            'role__in' => array('subscriber', 'customer', 'member'),
            'number' => 50,
            'orderby' => 'display_name',
            'order' => 'ASC',
        );
        
        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }
        
        $users = get_users($args);
        $members = array();
        
        foreach ($users as $user) {
            $members[] = array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
            );
        }
        
        // Get total count for "All Members"
        $total_count = count_users();
        $subscriber_count = isset($total_count['avail_roles']['subscriber']) ? $total_count['avail_roles']['subscriber'] : 0;

        wp_send_json_success(array(
            'members' => $members,
            'total_members' => $subscriber_count
        ));
    }

    /**
     * AJAX: Admin compose and send message
     *
     * @return void
     */
    public function ajax_admin_compose_message() {
        check_ajax_referer('framt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Not authorized'));
        }

        $recipient = sanitize_text_field($_POST['recipient']);
        $subject = sanitize_text_field($_POST['subject']);
        $content = sanitize_textarea_field($_POST['content']);
        $admin_id = get_current_user_id();

        if (empty($subject) || empty($content)) {
            wp_send_json_error(array('message' => 'Subject and message are required'));
        }

        $sent_count = 0;

        if ($recipient === 'all') {
            // Send to all members
            $users = get_users(array('role__in' => array('subscriber', 'customer', 'member')));
            foreach ($users as $user) {
                if ($this->create_admin_message($user->ID, $subject, $content, $admin_id)) {
                    $sent_count++;
                }
            }
        } else {
            // Send to individual member
            $user_id = intval($recipient);
            if ($this->create_admin_message($user_id, $subject, $content, $admin_id)) {
                $sent_count++;
            }
        }

        if ($sent_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf('Message sent to %d member(s)', $sent_count),
                'count' => $sent_count
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to send message'));
        }
    }

    /**
     * Create a message from admin to user
     *
     * @param int $user_id Recipient user ID
     * @param string $subject Subject
     * @param string $content Content
     * @param int $admin_id Admin user ID
     * @return int|false Message ID or false
     */
    public function create_admin_message($user_id, $subject, $content, $admin_id) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table_messages,
            array(
                'user_id' => $user_id,
                'subject' => $subject,
                'status' => 'open',
                'has_unread_admin' => 0,
                'has_unread_user' => 1
            ),
            array('%d', '%s', '%s', '%d', '%d')
        );

        if (!$inserted) {
            return false;
        }

        $message_id = $wpdb->insert_id;

        // Add content as admin reply
        $wpdb->insert(
            $this->table_replies,
            array(
                'message_id' => $message_id,
                'user_id' => $admin_id,
                'content' => $content,
                'is_admin' => 1
            ),
            array('%d', '%d', '%s', '%d')
        );

        // Send email notification to user
        $this->send_user_notification($message_id, $content);

        return $message_id;
    }

    /**
     * AJAX: Get auto message settings
     *
     * @return void
     */
    public function ajax_admin_get_auto_settings() {
        check_ajax_referer('framt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Not authorized'));
        }

        $settings = get_option('framt_auto_messages', $this->get_default_auto_settings());

        wp_send_json_success(array('settings' => $settings));
    }

    /**
     * AJAX: Save auto message settings
     *
     * @return void
     */
    public function ajax_admin_save_auto_settings() {
        check_ajax_referer('framt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Not authorized'));
        }

        $settings = array(
            'welcome_enabled' => isset($_POST['welcome_enabled']) && $_POST['welcome_enabled'] === 'true',
            'welcome_subject' => sanitize_text_field($_POST['welcome_subject']),
            'welcome_message' => wp_kses_post($_POST['welcome_message']),
        );

        update_option('framt_auto_messages', $settings);

        wp_send_json_success(array('message' => 'Settings saved'));
    }

    /**
     * Get default auto message settings
     *
     * @return array
     */
    public function get_default_auto_settings() {
        return array(
            'welcome_enabled' => true,
            'welcome_subject' => '🎉 Welcome to Relo2France!',
            'welcome_message' => $this->get_default_welcome_message(),
        );
    }

    /**
     * Get default welcome message template
     *
     * @return string
     */
    public function get_default_welcome_message() {
        return "Welcome to Relo2France! We're thrilled to have you join our community of people planning their move to France.

**Your Account is Ready!**

Here's what you can do with your membership:

📊 **Dashboard** — Your personalized command center for tracking your relocation progress.

📋 **Custom Checklists** — Step-by-step visa application checklists tailored to your visa type.

📄 **Document Generator** — Create properly formatted documents for your French visa application.

📚 **Knowledge Base** — Comprehensive guides on visas, healthcare, taxes, property, and more.

💬 **AI Assistant** — Get instant answers about relocating to France from our AI-powered guide.

**Getting Started:**

1. Complete your **Visa Profile** — This helps us personalize all your documents and checklists.
2. Explore the **Knowledge Base** — Start with topics relevant to your visa type.
3. Generate your first **Checklist** — See exactly what documents you need.

If you have any questions about your membership or find any errors on the site, use the **My Messages** feature to reach us.

À bientôt!
The Relo2France Team";
    }

    /**
     * Send welcome message on MemberPress signup
     *
     * @param MeprTransaction|MeprUser $txn_or_user Transaction or user object
     * @return void
     */
    public function send_welcome_message($txn_or_user) {
        $settings = get_option('framt_auto_messages', $this->get_default_auto_settings());
        
        if (!$settings['welcome_enabled']) {
            return;
        }

        // Get user ID from transaction or user object
        $user_id = null;
        if (is_object($txn_or_user)) {
            if (isset($txn_or_user->user_id)) {
                $user_id = $txn_or_user->user_id;
            } elseif (isset($txn_or_user->ID)) {
                $user_id = $txn_or_user->ID;
            }
        } elseif (is_numeric($txn_or_user)) {
            $user_id = intval($txn_or_user);
        }

        if (!$user_id) {
            return;
        }

        // Check if welcome message already sent
        $already_sent = get_user_meta($user_id, '_framt_welcome_sent', true);
        if ($already_sent) {
            return;
        }

        // Get user info for personalization
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        // Personalize message
        $subject = $settings['welcome_subject'];
        $message = $settings['welcome_message'];
        
        // Replace placeholders
        $message = str_replace('{first_name}', $user->first_name ?: $user->display_name, $message);
        $message = str_replace('{display_name}', $user->display_name, $message);
        $message = str_replace('{email}', $user->user_email, $message);

        // Create the welcome message
        $admin_id = 1; // Use admin user ID 1 or get first admin
        $admins = get_users(array('role' => 'administrator', 'number' => 1));
        if (!empty($admins)) {
            $admin_id = $admins[0]->ID;
        }

        $message_id = $this->create_admin_message($user_id, $subject, $message, $admin_id);

        if ($message_id) {
            // Mark welcome as sent
            update_user_meta($user_id, '_framt_welcome_sent', time());
        }
    }

    /**
     * Maybe send welcome on regular WordPress registration
     * (Fallback if MemberPress hooks don't fire)
     *
     * @param int $user_id User ID
     * @return void
     */
    public function maybe_send_welcome_on_register($user_id) {
        // Only send if user has a member role
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $member_roles = array('subscriber', 'customer', 'member');
        $has_member_role = !empty(array_intersect($member_roles, $user->roles));

        if ($has_member_role) {
            $this->send_welcome_message($user_id);
        }
    }

    /**
     * Update message status
     *
     * @param int $message_id Message ID
     * @param string $status New status
     * @return bool
     */
    public function update_message_status($message_id, $status) {
        global $wpdb;

        $data = array('status' => $status);
        
        if ($status === 'closed') {
            $data['closed_at'] = current_time('mysql');
        } else {
            $data['closed_at'] = null;
        }

        return $wpdb->update(
            $this->table_messages,
            $data,
            array('id' => $message_id)
        ) !== false;
    }

    /**
     * Render admin page
     *
     * @return void
     */
    public function render_admin_page() {
        ?>
        <div class="wrap framt-messages-admin">
            <h1>Member Messages</h1>
            
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper framt-admin-tabs">
                <a href="#inbox" class="nav-tab nav-tab-active" data-tab="inbox">📥 Inbox</a>
                <a href="#compose" class="nav-tab" data-tab="compose">✉️ Compose</a>
                <a href="#auto" class="nav-tab" data-tab="auto">🤖 Auto Messages</a>
            </nav>
            
            <div id="framt-messages-app">
                <!-- INBOX TAB -->
                <div class="framt-tab-content" id="tab-inbox">
                    <div class="framt-messages-filters">
                        <button type="button" class="button framt-filter-btn active" data-status="all">All</button>
                        <button type="button" class="button framt-filter-btn" data-status="open">Open</button>
                        <button type="button" class="button framt-filter-btn" data-status="closed">Closed</button>
                        <span class="framt-unread-badge" style="display:none;"></span>
                    </div>

                    <div class="framt-messages-container">
                        <div class="framt-messages-list">
                            <div class="framt-messages-loading">Loading messages...</div>
                        </div>
                        <div class="framt-message-detail">
                            <div class="framt-no-message-selected">
                                <p>Select a message to view</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COMPOSE TAB -->
                <div class="framt-tab-content" id="tab-compose" style="display: none;">
                    <div class="framt-compose-admin">
                        <h2>Send Message to Members</h2>
                        <p class="description">Send a message to an individual member or broadcast to all members.</p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="compose-recipient">Recipient</label></th>
                                <td>
                                    <select id="compose-recipient" class="framt-recipient-select">
                                        <option value="">-- Select recipient --</option>
                                        <option value="all">📢 All Members</option>
                                    </select>
                                    <p class="description">Type to search members by name or email</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="compose-subject">Subject</label></th>
                                <td>
                                    <input type="text" id="compose-subject" class="regular-text" placeholder="Message subject">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="compose-content">Message</label></th>
                                <td>
                                    <textarea id="compose-content" rows="12" class="large-text" placeholder="Write your message here...

You can use **bold** and other markdown formatting."></textarea>
                                    <p class="description">Supports basic formatting. Use {first_name}, {display_name}, {email} for personalization.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="button" id="admin-send-message" class="button button-primary button-large">
                                📤 Send Message
                            </button>
                            <span id="compose-status" style="margin-left: 15px;"></span>
                        </p>
                    </div>
                </div>

                <!-- AUTO MESSAGES TAB -->
                <div class="framt-tab-content" id="tab-auto" style="display: none;">
                    <div class="framt-auto-messages">
                        <h2>Automatic Messages</h2>
                        <p class="description">Configure messages that are automatically sent to members.</p>
                        
                        <div class="framt-auto-section">
                            <h3>🎉 Welcome Message</h3>
                            <p class="description">Sent automatically when a new member signs up.</p>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Enable</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="auto-welcome-enabled" value="1">
                                            Send welcome message to new members
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="auto-welcome-subject">Subject</label></th>
                                    <td>
                                        <input type="text" id="auto-welcome-subject" class="regular-text" placeholder="Welcome to Relo2France!">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="auto-welcome-message">Message</label></th>
                                    <td>
                                        <textarea id="auto-welcome-message" rows="18" class="large-text code"></textarea>
                                        <p class="description">
                                            Available placeholders: {first_name}, {display_name}, {email}<br>
                                            Supports **bold**, *italic*, and basic markdown.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="button" id="save-auto-settings" class="button button-primary">
                                    💾 Save Settings
                                </button>
                                <button type="button" id="reset-auto-settings" class="button">
                                    Reset to Default
                                </button>
                                <span id="auto-status" style="margin-left: 15px;"></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .framt-messages-admin { max-width: 1400px; }
            .framt-admin-tabs { margin-bottom: 20px; }
            .framt-tab-content { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; }
            
            .framt-messages-filters { margin-bottom: 20px; display: flex; gap: 10px; align-items: center; }
            .framt-filter-btn.active { background: #1e3a5f; color: white; border-color: #1e3a5f; }
            .framt-unread-badge { background: #e85a1b; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; }
            
            .framt-messages-container { display: grid; grid-template-columns: 400px 1fr; gap: 20px; min-height: 500px; }
            
            .framt-messages-list { background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; overflow-y: auto; max-height: 600px; }
            .framt-message-item { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.15s; background: #fff; }
            .framt-message-item:hover { background: #f7f7f7; }
            .framt-message-item.active { background: #e8f4fc; border-left: 3px solid #1e3a5f; }
            .framt-message-item.unread { background: #fff8e5; }
            .framt-message-item.unread.active { background: #e8f4fc; }
            .framt-message-subject { font-weight: 600; margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center; }
            .framt-message-meta { font-size: 12px; color: #666; }
            .framt-message-status { font-size: 11px; padding: 2px 6px; border-radius: 3px; }
            .framt-message-status.open { background: #d4edda; color: #155724; }
            .framt-message-status.closed { background: #f8d7da; color: #721c24; }
            
            .framt-message-detail { background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 20px; }
            .framt-no-message-selected { text-align: center; color: #666; padding: 100px 20px; }
            
            .framt-detail-header { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
            .framt-detail-subject { font-size: 18px; font-weight: 600; margin-bottom: 10px; }
            .framt-detail-meta { font-size: 13px; color: #666; }
            .framt-detail-actions { margin-top: 10px; }
            .framt-detail-actions .button { margin-right: 5px; }
            
            .framt-replies { max-height: 350px; overflow-y: auto; margin-bottom: 20px; padding-right: 10px; }
            .framt-reply { padding: 15px; margin-bottom: 10px; border-radius: 8px; }
            .framt-reply.user { background: #fff; border: 1px solid #ddd; margin-right: 40px; }
            .framt-reply.admin { background: #e3f2fd; margin-left: 40px; }
            .framt-reply-meta { font-size: 11px; color: #666; margin-bottom: 8px; }
            .framt-reply-content { white-space: pre-wrap; line-height: 1.6; }
            
            .framt-reply-form textarea { width: 100%; min-height: 100px; margin-bottom: 10px; }
            .framt-reply-form .button-primary { background: #1e3a5f; border-color: #1e3a5f; }
            
            /* Compose tab */
            .framt-compose-admin { max-width: 800px; }
            .framt-compose-admin .form-table th { width: 120px; }
            .framt-recipient-select { min-width: 350px; }
            #compose-content { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            
            /* Auto messages tab */
            .framt-auto-messages { max-width: 900px; }
            .framt-auto-section { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-top: 20px; }
            .framt-auto-section h3 { margin-top: 0; }
            #auto-welcome-message { font-family: Consolas, Monaco, monospace; font-size: 13px; }
            
            /* Status messages */
            .framt-success { color: #155724; }
            .framt-error { color: #721c24; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var currentMessageId = null;
            var currentStatus = 'all';
            var nonce = '<?php echo wp_create_nonce('framt_admin_nonce'); ?>';
            var membersLoaded = false;

            // ===================
            // TAB NAVIGATION
            // ===================
            $('.framt-admin-tabs .nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                
                $('.framt-admin-tabs .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.framt-tab-content').hide();
                $('#tab-' + tab).show();
                
                // Load data for specific tabs
                if (tab === 'compose' && !membersLoaded) {
                    loadMembers();
                }
                if (tab === 'auto') {
                    loadAutoSettings();
                }
            });

            // ===================
            // INBOX FUNCTIONS
            // ===================
            function loadMessages() {
                $.post(ajaxurl, {
                    action: 'framt_admin_get_messages',
                    nonce: nonce,
                    status: currentStatus
                }, function(response) {
                    if (response.success) {
                        renderMessagesList(response.data.messages);
                        if (response.data.unread_count > 0) {
                            $('.framt-unread-badge').text(response.data.unread_count + ' unread').show();
                        } else {
                            $('.framt-unread-badge').hide();
                        }
                    }
                });
            }

            function renderMessagesList(messages) {
                var html = '';
                if (messages.length === 0) {
                    html = '<div style="padding: 40px; text-align: center; color: #666;">No messages found</div>';
                } else {
                    messages.forEach(function(msg) {
                        var unreadClass = msg.has_unread_admin == 1 ? ' unread' : '';
                        var activeClass = msg.id == currentMessageId ? ' active' : '';
                        html += '<div class="framt-message-item' + unreadClass + activeClass + '" data-id="' + msg.id + '">' +
                            '<div class="framt-message-subject">' +
                                '<span>' + escapeHtml(msg.subject) + '</span>' +
                                '<span class="framt-message-status ' + msg.status + '">' + msg.status + '</span>' +
                            '</div>' +
                            '<div class="framt-message-meta">' +
                                '<strong>' + escapeHtml(msg.display_name || 'Unknown') + '</strong> &bull; ' +
                                formatDate(msg.updated_at) + ' &bull; ' +
                                msg.reply_count + ' replies' +
                            '</div>' +
                        '</div>';
                    });
                }
                $('.framt-messages-list').html(html);
            }

            function loadMessage(messageId) {
                currentMessageId = messageId;
                $('.framt-message-item').removeClass('active');
                $('.framt-message-item[data-id="' + messageId + '"]').addClass('active').removeClass('unread');

                $.post(ajaxurl, {
                    action: 'framt_admin_get_message',
                    nonce: nonce,
                    message_id: messageId
                }, function(response) {
                    if (response.success) {
                        renderMessageDetail(response.data.message, response.data.replies);
                    }
                });
            }

            function renderMessageDetail(message, replies) {
                var statusBtn = message.status === 'open' 
                    ? '<button type="button" class="button framt-close-btn">Close Ticket</button>'
                    : '<button type="button" class="button framt-reopen-btn">Reopen Ticket</button>';

                var html = '<div class="framt-detail-header">' +
                    '<div class="framt-detail-subject">' + escapeHtml(message.subject) + '</div>' +
                    '<div class="framt-detail-meta">' +
                        'From: <strong>' + escapeHtml(message.user_name) + '</strong> (' + escapeHtml(message.user_email) + ')<br>' +
                        'Created: ' + formatDate(message.created_at) + ' &bull; ' +
                        'Status: <span class="framt-message-status ' + message.status + '">' + message.status + '</span>' +
                    '</div>' +
                    '<div class="framt-detail-actions">' + statusBtn + '</div>' +
                '</div>';

                html += '<div class="framt-replies">';
                replies.forEach(function(reply) {
                    var typeClass = reply.is_admin == 1 ? 'admin' : 'user';
                    var authorLabel = reply.is_admin == 1 ? '👤 Admin' : escapeHtml(reply.author_name);
                    html += '<div class="framt-reply ' + typeClass + '">' +
                        '<div class="framt-reply-meta">' + authorLabel + ' &bull; ' + formatDate(reply.created_at) + '</div>' +
                        '<div class="framt-reply-content">' + escapeHtml(reply.content) + '</div>' +
                    '</div>';
                });
                html += '</div>';

                html += '<div class="framt-reply-form">' +
                    '<textarea id="framt-reply-content" placeholder="Type your reply..."></textarea>' +
                    '<button type="button" class="button button-primary framt-send-reply">Send Reply</button>' +
                '</div>';

                $('.framt-message-detail').html(html);
            }

            // Inbox event handlers
            $(document).on('click', '.framt-message-item', function() {
                loadMessage($(this).data('id'));
            });

            $(document).on('click', '.framt-filter-btn', function() {
                currentStatus = $(this).data('status');
                $('.framt-filter-btn').removeClass('active');
                $(this).addClass('active');
                loadMessages();
            });

            $(document).on('click', '.framt-send-reply', function() {
                var content = $('#framt-reply-content').val().trim();
                if (!content) {
                    alert('Please enter a reply');
                    return;
                }

                $(this).prop('disabled', true).text('Sending...');

                $.post(ajaxurl, {
                    action: 'framt_admin_reply_message',
                    nonce: nonce,
                    message_id: currentMessageId,
                    content: content
                }, function(response) {
                    if (response.success) {
                        loadMessage(currentMessageId);
                        loadMessages();
                    } else {
                        alert(response.data.message || 'Failed to send reply');
                    }
                }).always(function() {
                    $('.framt-send-reply').prop('disabled', false).text('Send Reply');
                });
            });

            $(document).on('click', '.framt-close-btn', function() {
                if (!confirm('Close this ticket?')) return;
                
                $.post(ajaxurl, {
                    action: 'framt_admin_close_message',
                    nonce: nonce,
                    message_id: currentMessageId
                }, function(response) {
                    if (response.success) {
                        loadMessage(currentMessageId);
                        loadMessages();
                    }
                });
            });

            $(document).on('click', '.framt-reopen-btn', function() {
                $.post(ajaxurl, {
                    action: 'framt_admin_reopen_message',
                    nonce: nonce,
                    message_id: currentMessageId
                }, function(response) {
                    if (response.success) {
                        loadMessage(currentMessageId);
                        loadMessages();
                    }
                });
            });

            // ===================
            // COMPOSE FUNCTIONS
            // ===================
            function loadMembers() {
                $.post(ajaxurl, {
                    action: 'framt_admin_get_members',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        var $select = $('#compose-recipient');
                        $select.find('option:not(:first):not([value="all"])').remove();
                        
                        // Update "All Members" option with count
                        $select.find('option[value="all"]').text('📢 All Members (' + response.data.total_members + ')');
                        
                        response.data.members.forEach(function(member) {
                            $select.append('<option value="' + member.id + '">' + 
                                escapeHtml(member.name) + ' (' + escapeHtml(member.email) + ')</option>');
                        });
                        
                        membersLoaded = true;
                    }
                });
            }

            $('#admin-send-message').on('click', function() {
                var recipient = $('#compose-recipient').val();
                var subject = $('#compose-subject').val().trim();
                var content = $('#compose-content').val().trim();
                
                if (!recipient) {
                    alert('Please select a recipient');
                    return;
                }
                if (!subject || !content) {
                    alert('Please fill in subject and message');
                    return;
                }
                
                var confirmMsg = recipient === 'all' 
                    ? 'Send this message to ALL members?' 
                    : 'Send this message?';
                    
                if (!confirm(confirmMsg)) return;
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('Sending...');
                $('#compose-status').text('');
                
                $.post(ajaxurl, {
                    action: 'framt_admin_compose_message',
                    nonce: nonce,
                    recipient: recipient,
                    subject: subject,
                    content: content
                }, function(response) {
                    if (response.success) {
                        $('#compose-status').html('<span class="framt-success">✓ ' + response.data.message + '</span>');
                        $('#compose-subject').val('');
                        $('#compose-content').val('');
                        $('#compose-recipient').val('');
                    } else {
                        $('#compose-status').html('<span class="framt-error">✗ ' + (response.data.message || 'Failed to send') + '</span>');
                    }
                }).always(function() {
                    $btn.prop('disabled', false).text('📤 Send Message');
                });
            });

            // ===================
            // AUTO MESSAGES FUNCTIONS
            // ===================
            function loadAutoSettings() {
                $.post(ajaxurl, {
                    action: 'framt_admin_get_auto_settings',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        var s = response.data.settings;
                        $('#auto-welcome-enabled').prop('checked', s.welcome_enabled);
                        $('#auto-welcome-subject').val(s.welcome_subject);
                        $('#auto-welcome-message').val(s.welcome_message);
                    }
                });
            }

            $('#save-auto-settings').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Saving...');
                $('#auto-status').text('');
                
                $.post(ajaxurl, {
                    action: 'framt_admin_save_auto_settings',
                    nonce: nonce,
                    welcome_enabled: $('#auto-welcome-enabled').is(':checked'),
                    welcome_subject: $('#auto-welcome-subject').val(),
                    welcome_message: $('#auto-welcome-message').val()
                }, function(response) {
                    if (response.success) {
                        $('#auto-status').html('<span class="framt-success">✓ Settings saved</span>');
                    } else {
                        $('#auto-status').html('<span class="framt-error">✗ Failed to save</span>');
                    }
                }).always(function() {
                    $btn.prop('disabled', false).text('💾 Save Settings');
                });
            });

            $('#reset-auto-settings').on('click', function() {
                if (!confirm('Reset welcome message to default?')) return;
                
                // Load defaults
                $('#auto-welcome-enabled').prop('checked', true);
                $('#auto-welcome-subject').val('🎉 Welcome to Relo2France!');
                $('#auto-welcome-message').val(<?php echo json_encode($this->get_default_welcome_message()); ?>);
                
                $('#auto-status').html('<span class="framt-success">✓ Reset to defaults (click Save to apply)</span>');
            });

            // ===================
            // UTILITY FUNCTIONS
            // ===================
            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function formatDate(dateStr) {
                var date = new Date(dateStr);
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            }

            // ===================
            // INITIAL LOAD
            // ===================
            var urlParams = new URLSearchParams(window.location.search);
            var messageIdParam = urlParams.get('message_id');

            loadMessages();
            
            if (messageIdParam) {
                setTimeout(function() {
                    loadMessage(messageIdParam);
                }, 500);
            }
        });
        </script>
        <?php
    }

    /**
     * Render frontend messages section
     *
     * @return string HTML
     */
    public function render_frontend_section() {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your messages.</p>';
        }

        ob_start();
        ?>
        <div class="framt-messages-frontend" id="framt-messages-section">
            <div class="framt-messages-header">
                <h3>My Messages</h3>
                <button type="button" class="framt-btn framt-btn-primary framt-compose-btn">
                    ✉️ New Message
                </button>
            </div>

            <!-- Support scope notice -->
            <div class="framt-messages-notice">
                <div class="framt-notice-icon">ℹ️</div>
                <div class="framt-notice-content">
                    <strong>What we can help with:</strong> Membership questions, site errors, or content that needs correction.
                    <p class="framt-notice-disclaimer">We do not provide visa assistance, as this is a legal question that should be directed to a qualified immigration professional. This site is provided as a convenience to assist with the visa application process only.</p>
                </div>
            </div>

            <div class="framt-messages-content">
                <!-- Inbox view -->
                <div class="framt-inbox-view">
                    <div class="framt-messages-loading">Loading messages...</div>
                </div>

                <!-- Compose view (hidden by default) -->
                <div class="framt-compose-view" style="display: none;">
                    <div class="framt-compose-header">
                        <button type="button" class="framt-back-to-inbox">← Back to Inbox</button>
                    </div>
                    <div class="framt-compose-form">
                        <div class="framt-form-group">
                            <label>Subject</label>
                            <input type="text" id="framt-compose-subject" placeholder="What do you need help with?">
                        </div>
                        <div class="framt-form-group">
                            <label>Message</label>
                            <textarea id="framt-compose-content" rows="6" placeholder="Describe your question or issue in detail..."></textarea>
                        </div>
                        <button type="button" class="framt-btn framt-btn-primary framt-send-message">
                            Send Message
                        </button>
                    </div>
                </div>

                <!-- Message detail view (hidden by default) -->
                <div class="framt-message-view" style="display: none;">
                    <div class="framt-message-header">
                        <button type="button" class="framt-back-to-inbox">← Back to Inbox</button>
                    </div>
                    <div class="framt-message-conversation"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
