<?php
/**
 * Member Dashboard
 *
 * Handles the member dashboard display including welcome message,
 * progress stats, recent documents, and recommended next steps.
 *
 * @package FRA_Member_Tools
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Member dashboard class
 */
class FRAMT_Dashboard {

    /**
     * Singleton instance
     *
     * @var FRAMT_Dashboard|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return FRAMT_Dashboard
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize hooks if needed
    }

    /**
     * Render the dashboard
     *
     * @return string HTML output
     */
    public function render() {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return $this->render_login_prompt();
        }

        $data = $this->get_dashboard_data($user_id);
        
        ob_start();
        ?>
        <div class="framt-dashboard">
            <?php 
            // Profile reminder is injected by JS for incomplete profiles
            // Only show welcome if profile is 50%+ complete
            if ($data['profile_completion'] >= 50) {
                echo $this->render_welcome($data);
            }
            ?>
            
            <?php echo $this->render_progress($data); ?>
            <?php echo $this->render_next_steps($data); ?>
            <?php echo $this->render_messages($user_id); ?>
            <?php echo $this->render_quick_actions(); ?>
            <?php echo $this->render_membership_status($data); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get dashboard data for a user
     *
     * @param int $user_id User ID
     * @return array Dashboard data
     */
    public function get_dashboard_data($user_id) {
        $profile = FRAMT_Profile::get_instance();
        $membership = FRAMT_Membership::get_instance();
        $documents = FRAMT_Documents::get_instance();
        $checklists = FRAMT_Checklists::get_instance();

        $user = get_userdata($user_id);
        $profile_data = $profile->get_profile($user_id);

        return array(
            'user_name' => $user ? $user->display_name : '',
            'first_name' => $user ? $user->first_name : '',
            'profile_completion' => $profile->get_completion_percentage($user_id),
            'needs_onboarding' => $profile->needs_onboarding($user_id),
            'profile' => $profile_data,
            'membership' => $membership->get_status_info($user_id),
            'documents' => array(
                'created' => $documents->get_document_count($user_id),
                'total' => $this->get_total_document_types(),
                'recent' => $documents->get_recent_documents($user_id, 5),
            ),
            'checklists' => array(
                'completed' => $checklists->get_completed_count($user_id),
                'total' => $checklists->get_total_items($user_id),
                'percentage' => $checklists->get_completion_percentage($user_id),
            ),
            'next_steps' => $this->calculate_next_steps($user_id, $profile_data),
        );
    }

    /**
     * Render welcome section
     *
     * @param array $data Dashboard data
     * @return string HTML
     */
    private function render_welcome($data) {
        $name = !empty($data['first_name']) ? $data['first_name'] : $data['user_name'];
        
        // Only show simple welcome - JS handles profile completion reminder
        ob_start();
        ?>
        <div class="framt-welcome">
            <h2>
                <?php echo esc_html(sprintf(__('👋 Welcome back, %s!', 'fra-member-tools'), $name)); ?>
            </h2>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render progress section
     *
     * @param array $data Dashboard data
     * @return string HTML
     */
    private function render_progress($data) {
        ob_start();
        ?>
        <div class="framt-card framt-progress-card">
            <h3><?php esc_html_e('📊 Your Progress', 'fra-member-tools'); ?></h3>
            
            <div class="framt-progress-items">
                <div class="framt-progress-item">
                    <label><?php esc_html_e('Profile', 'fra-member-tools'); ?></label>
                    <div class="framt-progress-bar">
                        <div class="framt-progress-fill" style="width: <?php echo esc_attr($data['profile_completion']); ?>%"></div>
                    </div>
                    <span class="framt-progress-text"><?php echo esc_html($data['profile_completion']); ?>%</span>
                </div>
                
                <div class="framt-progress-item">
                    <label><?php esc_html_e('Documents', 'fra-member-tools'); ?></label>
                    <div class="framt-progress-bar">
                        <?php 
                        $doc_pct = $data['documents']['total'] > 0 
                            ? round(($data['documents']['created'] / $data['documents']['total']) * 100) 
                            : 0;
                        ?>
                        <div class="framt-progress-fill" style="width: <?php echo esc_attr($doc_pct); ?>%"></div>
                    </div>
                    <span class="framt-progress-text">
                        <?php echo esc_html($data['documents']['created']); ?> / <?php echo esc_html($data['documents']['total']); ?>
                    </span>
                </div>
                
                <div class="framt-progress-item">
                    <label><?php esc_html_e('Checklist', 'fra-member-tools'); ?></label>
                    <div class="framt-progress-bar">
                        <div class="framt-progress-fill" style="width: <?php echo esc_attr($data['checklists']['percentage']); ?>%"></div>
                    </div>
                    <span class="framt-progress-text">
                        <?php echo esc_html($data['checklists']['completed']); ?> / <?php echo esc_html($data['checklists']['total']); ?>
                    </span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render next steps section
     *
     * @param array $data Dashboard data
     * @return string HTML
     */
    private function render_next_steps($data) {
        ob_start();
        ?>
        <div class="framt-card framt-next-steps-card">
            <h3><?php esc_html_e('🎯 Recommended Next Steps', 'fra-member-tools'); ?></h3>
            
            <?php if (!empty($data['profile']['target_move_date'])) : ?>
                <div class="framt-move-date">
                    <?php
                    $move_date = strtotime($data['profile']['target_move_date']);
                    $days_away = max(0, floor(($move_date - time()) / 86400));
                    printf(
                        esc_html__('Your move date: %s (%d days away)', 'fra-member-tools'),
                        esc_html(date_i18n(get_option('date_format'), $move_date)),
                        $days_away
                    );
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($data['next_steps'])) : ?>
                <p class="framt-all-done">
                    <?php esc_html_e('🎉 Great work! You\'re all caught up.', 'fra-member-tools'); ?>
                </p>
            <?php else : ?>
                <div class="framt-steps-list">
                    <?php foreach (array_slice($data['next_steps'], 0, 5) as $step) : ?>
                        <div class="framt-step-item framt-step-<?php echo esc_attr($step['priority']); ?>">
                            <span class="framt-step-lead-time"><?php echo esc_html($step['lead_time']); ?></span>
                            <span class="framt-step-title"><?php echo esc_html($step['title']); ?></span>
                            <button class="framt-btn framt-btn-small" data-action="<?php echo esc_attr($step['action']); ?>">
                                <?php esc_html_e('Get Started', 'fra-member-tools'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render recent documents section
     *
     * @param array $data Dashboard data
     * @return string HTML
     */
    private function render_recent_documents($data) {
        ob_start();
        ?>
        <div class="framt-card framt-recent-docs-card">
            <h3><?php esc_html_e('📄 Recent Documents', 'fra-member-tools'); ?></h3>
            
            <?php if (empty($data['documents']['recent'])) : ?>
                <p class="framt-no-docs">
                    <?php esc_html_e('No documents created yet. Create your first document to get started!', 'fra-member-tools'); ?>
                </p>
            <?php else : ?>
                <div class="framt-docs-list">
                    <?php foreach ($data['documents']['recent'] as $doc) : ?>
                        <div class="framt-doc-item">
                            <div class="framt-doc-info">
                                <span class="framt-doc-title"><?php echo esc_html($doc['title']); ?></span>
                                <span class="framt-doc-date"><?php echo esc_html($doc['date']); ?></span>
                            </div>
                            <div class="framt-doc-actions">
                                <button class="framt-btn framt-btn-small" data-action="download-doc" data-doc-id="<?php echo esc_attr($doc['id']); ?>">
                                    <?php esc_html_e('Download', 'fra-member-tools'); ?>
                                </button>
                                <button class="framt-btn framt-btn-small framt-btn-secondary" data-action="edit-doc" data-doc-id="<?php echo esc_attr($doc['id']); ?>">
                                    <?php esc_html_e('Edit', 'fra-member-tools'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render quick actions section
     *
     * @return string HTML
     */
    private function render_quick_actions() {
        ob_start();
        ?>
        <div class="framt-card framt-quick-actions-card">
            <h3><?php esc_html_e('⚡ Quick Actions', 'fra-member-tools'); ?></h3>
            
            <div class="framt-actions-grid">
                <button class="framt-action-btn" data-action="create-document">
                    <span class="framt-action-icon">📄</span>
                    <span class="framt-action-label"><?php esc_html_e('Create Document', 'fra-member-tools'); ?></span>
                </button>
                <button class="framt-action-btn" data-action="view-checklists">
                    <span class="framt-action-icon">📋</span>
                    <span class="framt-action-label"><?php esc_html_e('Checklists', 'fra-member-tools'); ?></span>
                </button>
                <button class="framt-action-btn" data-action="view-profile">
                    <span class="framt-action-icon">👤</span>
                    <span class="framt-action-label"><?php esc_html_e('My Visa Profile', 'fra-member-tools'); ?></span>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render messages/support section
     *
     * @param int $user_id User ID
     * @return string HTML
     */
    private function render_messages($user_id) {
        global $wpdb;
        
        $table_messages = $wpdb->prefix . 'framt_messages';
        $table_replies = $wpdb->prefix . 'framt_message_replies';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_messages'") === $table_messages;
        if (!$table_exists) {
            return ''; // Table not created yet
        }
        
        // Get unread count
        $unread_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_messages WHERE user_id = %d AND has_unread_user = 1",
            $user_id
        ));
        
        // Get recent messages (last 3)
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, 
                    (SELECT content FROM $table_replies WHERE message_id = m.id ORDER BY created_at DESC LIMIT 1) as last_reply
             FROM $table_messages m
             WHERE m.user_id = %d
             ORDER BY m.updated_at DESC
             LIMIT 3",
            $user_id
        ), ARRAY_A);
        
        ob_start();
        ?>
        <div class="framt-card framt-messages-card">
            <div class="framt-card-header">
                <h3>
                    <?php esc_html_e('✉️ Support Messages', 'fra-member-tools'); ?>
                    <?php if ($unread_count > 0) : ?>
                        <span class="framt-unread-badge"><?php echo esc_html($unread_count); ?></span>
                    <?php endif; ?>
                </h3>
                <button class="framt-btn framt-btn-small framt-btn-primary" data-action="view-messages">
                    <?php esc_html_e('View All', 'fra-member-tools'); ?>
                </button>
            </div>
            
            <?php if (empty($messages)) : ?>
                <div class="framt-messages-empty">
                    <p><?php esc_html_e('No messages yet.', 'fra-member-tools'); ?></p>
                    <button class="framt-btn framt-btn-outline" data-action="new-message">
                        <?php esc_html_e('✉️ Contact Support', 'fra-member-tools'); ?>
                    </button>
                </div>
            <?php else : ?>
                <div class="framt-messages-list-compact">
                    <?php foreach ($messages as $msg) : 
                        $is_unread = $msg['has_unread_user'] == 1;
                        $status_class = $msg['status'];
                        $preview = $msg['last_reply'] ? wp_trim_words($msg['last_reply'], 10, '...') : '';
                    ?>
                        <div class="framt-message-row <?php echo $is_unread ? 'unread' : ''; ?>" data-message-id="<?php echo esc_attr($msg['id']); ?>">
                            <div class="framt-message-indicator">
                                <?php echo $is_unread ? '🔔' : '📧'; ?>
                            </div>
                            <div class="framt-message-info">
                                <div class="framt-message-subject-line">
                                    <span class="framt-msg-subject"><?php echo esc_html($msg['subject']); ?></span>
                                    <span class="framt-msg-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($msg['status']); ?></span>
                                </div>
                                <?php if ($preview) : ?>
                                    <div class="framt-message-preview-text"><?php echo esc_html($preview); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="framt-message-date">
                                <?php echo esc_html($this->format_relative_date($msg['updated_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="framt-messages-footer">
                    <button class="framt-btn framt-btn-outline framt-btn-small" data-action="new-message">
                        <?php esc_html_e('✉️ New Message', 'fra-member-tools'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Format relative date
     *
     * @param string $date Date string
     * @return string Formatted date
     */
    private function format_relative_date($date) {
        $timestamp = strtotime($date);
        $now = time();
        $diff = $now - $timestamp;
        
        if ($diff < 60) {
            return __('Just now', 'fra-member-tools');
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return sprintf(_n('%d min ago', '%d mins ago', $mins, 'fra-member-tools'), $mins);
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'fra-member-tools'), $hours);
        } elseif ($diff < 172800) {
            return __('Yesterday', 'fra-member-tools');
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return sprintf(_n('%d day ago', '%d days ago', $days, 'fra-member-tools'), $days);
        } else {
            return date_i18n(get_option('date_format'), $timestamp);
        }
    }

    /**
     * Render membership status section
     *
     * @param array $data Dashboard data
     * @return string HTML
     */
    private function render_membership_status($data) {
        $membership = $data['membership'];
        
        ob_start();
        ?>
        <div class="framt-membership-status">
            <span class="framt-membership-icon">💳</span>
            <span class="framt-membership-text">
                <?php echo esc_html($membership['status_text']); ?>
            </span>
            <?php if (!$membership['is_lifetime'] && $membership['expiration']) : ?>
                <a href="<?php echo esc_url(get_permalink(get_option('mepr_account_page_id'))); ?>" class="framt-btn framt-btn-small">
                    <?php esc_html_e('Manage', 'fra-member-tools'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render login prompt
     *
     * @return string HTML
     */
    private function render_login_prompt() {
        ob_start();
        ?>
        <div class="framt-login-prompt">
            <p><?php esc_html_e('Please log in to access your member dashboard.', 'fra-member-tools'); ?></p>
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="framt-btn framt-btn-primary">
                <?php esc_html_e('Log In', 'fra-member-tools'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Calculate next steps based on profile and progress
     *
     * @param int $user_id User ID
     * @param array $profile Profile data
     * @return array Next steps
     */
    private function calculate_next_steps($user_id, $profile) {
        $steps = array();
        $documents = FRAMT_Documents::get_instance();
        $checklists = FRAMT_Checklists::get_instance();

        // Check profile completion first
        if (empty($profile) || empty($profile['onboarding_complete'])) {
            $steps[] = array(
                'title' => __('Complete your profile', 'fra-member-tools'),
                'lead_time' => __('10 min', 'fra-member-tools'),
                'priority' => 'high',
                'action' => 'start-onboarding',
            );
            return $steps; // Return early - profile is priority
        }

        // Get visa type for ordering
        $visa_type = $profile['visa_type'] ?? 'visitor';
        
        // Calculate steps based on lead time (longest first)
        $all_steps = $this->get_visa_steps($visa_type, $profile);

        // Filter out completed items
        foreach ($all_steps as $step) {
            if (!$this->is_step_complete($step, $user_id, $documents, $checklists)) {
                $steps[] = $step;
            }
        }

        // Sort by lead time (longest first)
        usort($steps, function($a, $b) {
            return $this->get_lead_time_priority($b['lead_time']) - $this->get_lead_time_priority($a['lead_time']);
        });

        return $steps;
    }

    /**
     * Get steps for a specific visa type
     *
     * @param string $visa_type Visa type
     * @param array $profile Profile data
     * @return array Steps
     */
    private function get_visa_steps($visa_type, $profile) {
        $steps = array();

        // Long lead time items (4+ weeks)
        $steps[] = array(
            'title' => __('Apostille documents', 'fra-member-tools'),
            'lead_time' => __('4-8 weeks', 'fra-member-tools'),
            'priority' => 'high',
            'action' => 'apostille-guide',
            'type' => 'guide',
        );

        if (!empty($profile['has_pets']) && $profile['has_pets'] !== 'no') {
            $steps[] = array(
                'title' => __('Pet preparation', 'fra-member-tools'),
                'lead_time' => __('4+ months', 'fra-member-tools'),
                'priority' => 'high',
                'action' => 'pet-guide',
                'type' => 'guide',
            );
        }

        // Medium lead time (1-4 weeks)
        $steps[] = array(
            'title' => __('Gather bank statements', 'fra-member-tools'),
            'lead_time' => __('1-2 weeks', 'fra-member-tools'),
            'priority' => 'medium',
            'action' => 'financial-checklist',
            'type' => 'checklist',
        );

        if ($profile['employment_status'] === 'employed') {
            $steps[] = array(
                'title' => __('Employment verification letter', 'fra-member-tools'),
                'lead_time' => __('3-5 days', 'fra-member-tools'),
                'priority' => 'medium',
                'action' => 'employment-letter',
                'type' => 'document',
            );
        }

        // Quick tasks (days)
        $steps[] = array(
            'title' => __('Health insurance', 'fra-member-tools'),
            'lead_time' => __('1-2 days', 'fra-member-tools'),
            'priority' => 'low',
            'action' => 'upload-insurance',
            'type' => 'upload',
        );

        $steps[] = array(
            'title' => __('Cover letter', 'fra-member-tools'),
            'lead_time' => __('30 min', 'fra-member-tools'),
            'priority' => 'low',
            'action' => 'cover-letter',
            'type' => 'document',
        );

        $steps[] = array(
            'title' => __('Financial statement', 'fra-member-tools'),
            'lead_time' => __('30 min', 'fra-member-tools'),
            'priority' => 'low',
            'action' => 'financial-statement',
            'type' => 'document',
        );

        $steps[] = array(
            'title' => __('Passport photos', 'fra-member-tools'),
            'lead_time' => __('Same day', 'fra-member-tools'),
            'priority' => 'low',
            'action' => 'passport-photos',
            'type' => 'checklist',
        );

        return $steps;
    }

    /**
     * Check if a step is complete
     *
     * @param array $step Step data
     * @param int $user_id User ID
     * @param FRAMT_Documents $documents Documents instance
     * @param FRAMT_Checklists $checklists Checklists instance
     * @return bool
     */
    private function is_step_complete($step, $user_id, $documents, $checklists) {
        switch ($step['type'] ?? '') {
            case 'document':
                return $documents->has_document($user_id, $step['action']);
            
            case 'checklist':
                return $checklists->is_item_complete($user_id, $step['action']);
            
            case 'upload':
                return $documents->has_upload($user_id, $step['action']);
            
            default:
                return false;
        }
    }

    /**
     * Get numeric priority for lead time sorting
     *
     * @param string $lead_time Lead time text
     * @return int Priority value
     */
    private function get_lead_time_priority($lead_time) {
        if (strpos($lead_time, 'month') !== false) {
            return 100;
        }
        if (strpos($lead_time, 'week') !== false) {
            preg_match('/(\d+)/', $lead_time, $matches);
            return isset($matches[1]) ? intval($matches[1]) * 7 : 14;
        }
        if (strpos($lead_time, 'day') !== false) {
            preg_match('/(\d+)/', $lead_time, $matches);
            return isset($matches[1]) ? intval($matches[1]) : 3;
        }
        if (strpos($lead_time, 'min') !== false) {
            return 0;
        }
        return 1;
    }

    /**
     * Get total document types available
     *
     * @return int
     */
    private function get_total_document_types() {
        return 4; // Cover letter, Financial statement, No work attestation, Accommodation
    }

    /**
     * AJAX handler: Get dashboard data
     *
     * @return void
     */
    public function ajax_get_dashboard_data() {
        check_ajax_referer('framt_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'fra-member-tools'));
        }

        wp_send_json_success($this->get_dashboard_data(get_current_user_id()));
    }
}
