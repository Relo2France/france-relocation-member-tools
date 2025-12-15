<?php
/**
 * Chat Handler for Document Creation
 *
 * Handles the conversational flow for creating documents.
 *
 * @package FRA_Member_Tools
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FRAMT_Chat_Handler {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Get document introduction message
     */
    public function get_document_intro($document_type) {
        $types = FRAMT_Documents::get_instance()->get_document_types();
        
        if (!isset($types[$document_type])) {
            return null;
        }

        $type = $types[$document_type];
        $profile = FRAMT_Profile::get_instance();
        $user_profile = $profile->get_profile(get_current_user_id());

        $intro = array(
            'title' => $type['title'],
            'icon' => $type['icon'],
            'description' => $type['description'],
            'profile_summary' => $this->get_profile_summary($user_profile),
            'needs_verification' => !empty($user_profile),
            'questions' => $this->get_document_questions($document_type),
        );

        return $intro;
    }

    /**
     * Get profile summary for verification
     */
    private function get_profile_summary($profile) {
        if (empty($profile)) {
            return array();
        }

        $summary = array();
        $display = FRAMT_Profile::get_instance();

        $fields = array(
            'applicants' => __('Applying with', 'fra-member-tools'),
            'visa_type' => __('Visa type', 'fra-member-tools'),
            'employment_status' => __('Your status', 'fra-member-tools'),
            'application_location' => __('Applying from', 'fra-member-tools'),
            'target_location' => __('Target location', 'fra-member-tools'),
        );

        foreach ($fields as $field => $label) {
            if (!empty($profile[$field])) {
                $summary[] = array(
                    'field' => $field,
                    'label' => $label,
                    'value' => $display->get_display_value($field),
                );
            }
        }

        return $summary;
    }

    /**
     * Get questions for a document type
     */
    private function get_document_questions($document_type) {
        $questions = array(
            'cover-letter' => array(
                array(
                    'id' => 'privacy_choice',
                    'question' => __('This letter will include personal details. Would you prefer to use placeholders (like [YOUR NAME]) or provide your actual information?', 'fra-member-tools'),
                    'type' => 'choice',
                    'options' => array(
                        'placeholders' => __('Use placeholders', 'fra-member-tools'),
                        'actual' => __('Provide my information', 'fra-member-tools'),
                    ),
                ),
                array(
                    'id' => 'property_status',
                    'question' => __('Do you have property or accommodation arranged in France?', 'fra-member-tools'),
                    'type' => 'choice',
                    'options' => array(
                        'purchased' => __('I\'ve purchased property', 'fra-member-tools'),
                        'purchasing' => __('I\'m in the process of purchasing', 'fra-member-tools'),
                        'renting' => __('I have a rental arranged', 'fra-member-tools'),
                        'none' => __('Not yet', 'fra-member-tools'),
                    ),
                ),
                array(
                    'id' => 'move_reason',
                    'question' => __('What\'s your primary reason for moving to France? (This helps personalize your cover letter)', 'fra-member-tools'),
                    'type' => 'text',
                ),
            ),
            'financial-statement' => array(
                array(
                    'id' => 'privacy_choice',
                    'question' => __('This document includes financial figures. Would you like to use placeholders or provide specific amounts?', 'fra-member-tools'),
                    'type' => 'choice',
                    'options' => array(
                        'placeholders' => __('Use placeholders', 'fra-member-tools'),
                        'actual' => __('Provide amounts', 'fra-member-tools'),
                    ),
                ),
                array(
                    'id' => 'include_table',
                    'question' => __('Would you like to include a summary table of your financial resources?', 'fra-member-tools'),
                    'type' => 'choice',
                    'options' => array(
                        'yes' => __('Yes, include a table', 'fra-member-tools'),
                        'no' => __('No, narrative only', 'fra-member-tools'),
                    ),
                ),
            ),
            'no-work-attestation' => array(
                array(
                    'id' => 'activities',
                    'question' => __('How would you describe what you\'ll be doing in France? (e.g., managing property, learning French, enjoying retirement)', 'fra-member-tools'),
                    'type' => 'text',
                ),
            ),
            'accommodation-letter' => array(
                array(
                    'id' => 'accommodation_type',
                    'question' => __('What type of accommodation documentation are you providing?', 'fra-member-tools'),
                    'type' => 'choice',
                    'options' => array(
                        'purchase' => __('Property purchase agreement', 'fra-member-tools'),
                        'rental' => __('Rental lease', 'fra-member-tools'),
                        'host' => __('Staying with host', 'fra-member-tools'),
                    ),
                ),
            ),
        );

        return isset($questions[$document_type]) ? $questions[$document_type] : array();
    }

    /**
     * Process a chat message in document flow
     */
    public function process_message($document_type, $message, $context) {
        // Context contains: current_question_index, answers so far, profile data
        $questions = $this->get_document_questions($document_type);
        $current_index = $context['question_index'] ?? 0;
        
        // Save answer
        if (isset($questions[$current_index])) {
            $context['answers'][$questions[$current_index]['id']] = $message;
        }

        // Move to next question
        $next_index = $current_index + 1;

        // Check if we're done
        if ($next_index >= count($questions)) {
            // All questions answered - generate document
            return array(
                'complete' => true,
                'answers' => $context['answers'],
                'next_action' => 'generate',
            );
        }

        // Return next question
        $next_question = $questions[$next_index];
        
        // Add soft progress indicator if near end
        $remaining = count($questions) - $next_index;
        $progress_message = '';
        if ($remaining <= 2 && $remaining > 0) {
            $progress_message = sprintf(
                __('Just %d more question%s...', 'fra-member-tools'),
                $remaining,
                $remaining > 1 ? 's' : ''
            );
        }

        return array(
            'complete' => false,
            'question' => $next_question,
            'question_index' => $next_index,
            'progress_message' => $progress_message,
            'answers' => $context['answers'],
        );
    }

    /**
     * AJAX: Handle chat message
     */
    public function ajax_handle_message() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'fra-member-tools'));
        }

        $document_type = sanitize_key($_POST['document_type'] ?? '');
        $message = sanitize_text_field($_POST['message'] ?? '');
        $context = json_decode(stripslashes($_POST['context'] ?? '{}'), true);

        $result = $this->process_message($document_type, $message, $context);

        wp_send_json_success($result);
    }

    /**
     * AJAX: Start document creation flow
     */
    public function ajax_start_document_flow() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'fra-member-tools'));
        }

        $document_type = sanitize_key($_POST['document_type'] ?? '');
        $intro = $this->get_document_intro($document_type);

        if (!$intro) {
            wp_send_json_error(__('Unknown document type.', 'fra-member-tools'));
        }

        // Render HTML for the document creation flow
        $html = $this->render_document_flow_html($intro, $document_type);
        
        wp_send_json_success(array(
            'html' => $html,
            'intro' => $intro,
        ));
    }
    
    /**
     * Render HTML for document creation flow
     */
    private function render_document_flow_html($intro, $document_type) {
        $questions = $intro['questions'] ?? array();
        $first_question = !empty($questions) ? $questions[0] : null;
        
        ob_start();
        ?>
        <div class="framt-document-flow">
            <div class="framt-doc-flow-header">
                <button class="framt-btn framt-btn-small framt-btn-ghost" data-action="back-to-documents">← Back to Documents</button>
                <h2><span class="framt-doc-icon"><?php echo esc_html($intro['icon']); ?></span> <?php echo esc_html($intro['title']); ?></h2>
                <p><?php echo esc_html($intro['description']); ?></p>
            </div>
            
            <?php if (!empty($intro['profile_summary'])) : ?>
            <div class="framt-doc-profile-summary">
                <h4>📋 Using your profile information:</h4>
                <div class="framt-profile-values">
                    <?php foreach ($intro['profile_summary'] as $item) : ?>
                    <div class="framt-profile-value">
                        <span class="framt-value-label"><?php echo esc_html($item['label']); ?>:</span>
                        <span class="framt-value-text"><?php echo esc_html($item['value']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($first_question) : ?>
            <div class="framt-doc-question" data-question-index="0">
                <h3><?php echo esc_html($first_question['question']); ?></h3>
                
                <?php if ($first_question['type'] === 'choice' && !empty($first_question['options'])) : ?>
                <div class="framt-doc-options">
                    <?php foreach ($first_question['options'] as $value => $label) : ?>
                    <button class="framt-doc-option" data-action="answer-question" data-answer="<?php echo esc_attr($value); ?>">
                        <?php echo esc_html($label); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <div class="framt-doc-text-input">
                    <input type="text" class="framt-doc-input" placeholder="<?php esc_attr_e('Type your answer...', 'fra-member-tools'); ?>">
                    <button class="framt-btn framt-btn-primary" data-action="submit-answer"><?php esc_html_e('Continue', 'fra-member-tools'); ?></button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
