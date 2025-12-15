<?php
/**
 * Checklist Management
 *
 * @package FRA_Member_Tools
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FRAMT_Checklists {

    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'framt_checklists';
    }

    /**
     * Get checklist definitions based on visa type
     */
    public function get_checklists($visa_type = 'visitor') {
        return array(
            'visa-application' => array(
                'title' => __('Visa Application Checklist', 'fra-member-tools'),
                'items' => $this->get_visa_checklist_items($visa_type),
            ),
            'relocation' => array(
                'title' => __('Relocation Document Checklist', 'fra-member-tools'),
                'items' => $this->get_relocation_checklist_items(),
            ),
        );
    }

    /**
     * Get visa application checklist items
     */
    private function get_visa_checklist_items($visa_type) {
        $items = array(
            array('id' => 'passport', 'title' => __('Passport valid 6+ months', 'fra-member-tools'), 'lead_time' => __('Check now', 'fra-member-tools')),
            array('id' => 'photos', 'title' => __('Passport photos (2)', 'fra-member-tools'), 'lead_time' => __('Same day', 'fra-member-tools')),
            array('id' => 'application-form', 'title' => __('Completed visa application form', 'fra-member-tools'), 'lead_time' => __('30 min', 'fra-member-tools')),
            array('id' => 'cover-letter', 'title' => __('Cover letter', 'fra-member-tools'), 'lead_time' => __('30 min', 'fra-member-tools')),
            array('id' => 'financial-proof', 'title' => __('Proof of financial means', 'fra-member-tools'), 'lead_time' => __('1-2 weeks', 'fra-member-tools')),
            array('id' => 'accommodation', 'title' => __('Proof of accommodation', 'fra-member-tools'), 'lead_time' => __('Varies', 'fra-member-tools')),
            array('id' => 'health-insurance', 'title' => __('Health insurance certificate', 'fra-member-tools'), 'lead_time' => __('1-2 days', 'fra-member-tools')),
            array('id' => 'birth-cert', 'title' => __('Birth certificate (apostilled)', 'fra-member-tools'), 'lead_time' => __('4-8 weeks', 'fra-member-tools')),
        );

        if ($visa_type === 'visitor') {
            $items[] = array('id' => 'no-work', 'title' => __('No work attestation', 'fra-member-tools'), 'lead_time' => __('30 min', 'fra-member-tools'));
        }

        return $items;
    }

    /**
     * Get relocation checklist items
     */
    private function get_relocation_checklist_items() {
        return array(
            array('id' => 'bank-statements', 'title' => __('Bank statements (3 months)', 'fra-member-tools'), 'lead_time' => __('1-2 weeks', 'fra-member-tools')),
            array('id' => 'marriage-cert', 'title' => __('Marriage certificate (if applicable)', 'fra-member-tools'), 'lead_time' => __('4-8 weeks', 'fra-member-tools')),
            array('id' => 'employment-letter', 'title' => __('Employment verification letter', 'fra-member-tools'), 'lead_time' => __('3-5 days', 'fra-member-tools')),
            array('id' => 'tax-returns', 'title' => __('Tax returns (2 years)', 'fra-member-tools'), 'lead_time' => __('Check now', 'fra-member-tools')),
        );
    }

    /**
     * Get item status for user
     */
    public function get_item_status($user_id, $checklist_type, $item_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE user_id = %d AND checklist_type = %s AND item_id = %s",
                $user_id,
                $checklist_type,
                $item_id
            ),
            ARRAY_A
        );
    }

    /**
     * Update item status
     */
    public function update_item($user_id, $checklist_type, $item_id, $status, $handled_own = false) {
        global $wpdb;

        $exists = $this->get_item_status($user_id, $checklist_type, $item_id);

        $data = array(
            'status' => sanitize_key($status),
            'handled_own' => $handled_own ? 1 : 0,
            'completed_at' => $status === 'complete' ? current_time('mysql') : null,
        );

        if ($exists) {
            return $wpdb->update(
                $this->table_name,
                $data,
                array('user_id' => $user_id, 'checklist_type' => $checklist_type, 'item_id' => $item_id)
            );
        }

        $data['user_id'] = $user_id;
        $data['checklist_type'] = $checklist_type;
        $data['item_id'] = $item_id;

        return $wpdb->insert($this->table_name, $data);
    }

    /**
     * Check if item is complete
     */
    public function is_item_complete($user_id, $item_id) {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d AND item_id = %s AND (status = 'complete' OR handled_own = 1)",
                $user_id,
                $item_id
            )
        );
    }

    /**
     * Get completed count
     */
    public function get_completed_count($user_id) {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d AND (status = 'complete' OR handled_own = 1)",
                $user_id
            )
        );
    }

    /**
     * Get total items for user's visa type
     */
    public function get_total_items($user_id) {
        $profile = FRAMT_Profile::get_instance()->get_profile($user_id);
        $visa_type = $profile['visa_type'] ?? 'visitor';
        $checklists = $this->get_checklists($visa_type);

        $total = 0;
        foreach ($checklists as $checklist) {
            $total += count($checklist['items']);
        }
        return $total;
    }

    /**
     * Get completion percentage
     */
    public function get_completion_percentage($user_id) {
        $total = $this->get_total_items($user_id);
        if ($total === 0) return 0;
        
        $completed = $this->get_completed_count($user_id);
        return round(($completed / $total) * 100);
    }

    /**
     * Render checklists page
     */
    public function render() {
        $user_id = get_current_user_id();
        $profile = FRAMT_Profile::get_instance()->get_profile($user_id);
        $visa_type = $profile['visa_type'] ?? 'visitor';
        $checklists = $this->get_checklists($visa_type);
        $interactive = get_user_meta($user_id, 'framt_interactive_checklists', true);

        ob_start();
        ?>
        <div class="framt-checklists">
            <div class="framt-checklists-header">
                <h2><?php esc_html_e('📋 My Checklists', 'fra-member-tools'); ?></h2>
                <label class="framt-toggle">
                    <input type="checkbox" id="interactive-toggle" <?php checked($interactive); ?>>
                    <span><?php esc_html_e('Interactive Tracking', 'fra-member-tools'); ?></span>
                    <button type="button" class="framt-help-btn" aria-label="<?php esc_attr_e('Help', 'fra-member-tools'); ?>">?</button>
                    <div class="framt-help-popup" style="display: none;">
                        <div class="framt-help-popup-content">
                            <p><?php esc_html_e('Enable to check off items in the app. Your progress saves automatically.', 'fra-member-tools'); ?></p>
                            <button type="button" class="framt-help-popup-close"><?php esc_html_e('Got it', 'fra-member-tools'); ?></button>
                        </div>
                    </div>
                </label>
            </div>

            <?php foreach ($checklists as $key => $checklist) : ?>
                <div class="framt-checklist-card">
                    <h3><?php echo esc_html($checklist['title']); ?></h3>
                    
                    <div class="framt-checklist-items">
                        <?php foreach ($checklist['items'] as $item) : 
                            $status = $this->get_item_status($user_id, $key, $item['id']);
                            $is_complete = $status && ($status['status'] === 'complete' || $status['handled_own']);
                            $is_handled = $status && $status['handled_own'];
                        ?>
                            <div class="framt-checklist-item <?php echo $is_complete ? 'complete' : ''; ?>">
                                <?php if ($interactive) : ?>
                                    <input type="checkbox" 
                                           data-checklist="<?php echo esc_attr($key); ?>"
                                           data-item="<?php echo esc_attr($item['id']); ?>"
                                           <?php checked($is_complete); ?>>
                                <?php else : ?>
                                    <span class="framt-checkbox"><?php echo $is_complete ? '✅' : '☐'; ?></span>
                                <?php endif; ?>
                                
                                <span class="framt-item-title"><?php echo esc_html($item['title']); ?></span>
                                <span class="framt-lead-time"><?php echo esc_html($item['lead_time']); ?></span>
                                
                                <?php if ($is_handled) : ?>
                                    <span class="framt-handled-badge"><?php esc_html_e('Handling on my own', 'fra-member-tools'); ?></span>
                                <?php elseif (!$is_complete) : ?>
                                    <button class="framt-btn-small" 
                                            data-action="handle-own"
                                            data-checklist="<?php echo esc_attr($key); ?>"
                                            data-item="<?php echo esc_attr($item['id']); ?>">
                                        <?php esc_html_e('Handle on my own', 'fra-member-tools'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Update checklist item
     */
    public function ajax_update_checklist() {
        check_ajax_referer('framt_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error('Login required');

        $checklist = sanitize_key($_POST['checklist'] ?? '');
        $item = sanitize_key($_POST['item'] ?? '');
        $status = sanitize_key($_POST['status'] ?? 'pending');
        $handled = !empty($_POST['handled_own']);

        $this->update_item(get_current_user_id(), $checklist, $item, $status, $handled);
        wp_send_json_success(array('percentage' => $this->get_completion_percentage(get_current_user_id())));
    }

    /**
     * AJAX: Get checklist status
     */
    public function ajax_get_status() {
        check_ajax_referer('framt_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error('Login required');

        wp_send_json_success(array(
            'completed' => $this->get_completed_count(get_current_user_id()),
            'total' => $this->get_total_items(get_current_user_id()),
            'percentage' => $this->get_completion_percentage(get_current_user_id()),
        ));
    }
}
