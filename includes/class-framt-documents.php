<?php
/**
 * Document Management
 *
 * Handles saved documents, document types, and document display.
 *
 * @package FRA_Member_Tools
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Document management class
 */
class FRAMT_Documents {

    /**
     * Singleton instance
     *
     * @var FRAMT_Documents|null
     */
    private static $instance = null;

    /**
     * Database table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Document type definitions
     *
     * @var array
     */
    private $document_types;

    /**
     * Get singleton instance
     *
     * @return FRAMT_Documents
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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'framt_documents';
        $this->document_types = $this->define_document_types();
    }

    /**
     * Define available document types
     *
     * @return array Document type definitions
     */
    private function define_document_types() {
        return array(
            'cover-letter' => array(
                'title' => __('Visa Cover Letter', 'fra-member-tools'),
                'description' => __('A personalized cover letter for your visa application.', 'fra-member-tools'),
                'icon' => '✉️',
            ),
            'financial-statement' => array(
                'title' => __('Proof of Sufficient Means', 'fra-member-tools'),
                'description' => __('Statement of financial resources demonstrating self-sufficiency.', 'fra-member-tools'),
                'icon' => '💰',
            ),
            'attestation' => array(
                'title' => __('No Work Attestation', 'fra-member-tools'),
                'description' => __('Bilingual declaration that you will not work in France.', 'fra-member-tools'),
                'icon' => '📜',
            ),
            'accommodation-letter' => array(
                'title' => __('Proof of Accommodation', 'fra-member-tools'),
                'description' => __('Letter explaining your housing arrangements in France.', 'fra-member-tools'),
                'icon' => '🏠',
            ),
        );
    }

    /**
     * Get all document types
     *
     * @return array
     */
    public function get_document_types() {
        return $this->document_types;
    }

    /**
     * Get a specific document type
     *
     * @param string $type Document type key
     * @return array|null
     */
    public function get_document_type($type) {
        return isset($this->document_types[$type]) ? $this->document_types[$type] : null;
    }

    /**
     * Get documents for a user
     *
     * @param int $user_id User ID
     * @param string|null $type Optional type filter
     * @return array
     */
    public function get_documents($user_id, $type = null) {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table_name} WHERE user_id = %d";
        $params = array($user_id);

        if ($type) {
            $sql .= " AND document_type = %s";
            $params[] = $type;
        }

        $sql .= " ORDER BY updated_at DESC";

        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        return array_map(array($this, 'format_document'), $results ?: array());
    }

    /**
     * Get recent documents
     *
     * @param int $user_id User ID
     * @param int $limit Limit
     * @return array
     */
    public function get_recent_documents($user_id, $limit = 5) {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY updated_at DESC LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        return array_map(array($this, 'format_document'), $results ?: array());
    }

    /**
     * Get document count
     *
     * @param int $user_id User ID
     * @return int
     */
    public function get_document_count($user_id) {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d", $user_id)
        );
    }

    /**
     * Check if user has document type
     *
     * @param int $user_id User ID
     * @param string $type Document type
     * @return bool
     */
    public function has_document($user_id, $type) {
        global $wpdb;
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = %d AND document_type = %s",
                $user_id,
                $type
            )
        );
    }

    /**
     * Check if user has upload
     *
     * @param int $user_id User ID
     * @param string $type Upload type
     * @return bool
     */
    public function has_upload($user_id, $type) {
        $uploads = get_user_meta($user_id, 'framt_uploads', true);
        return is_array($uploads) && isset($uploads[$type]);
    }

    /**
     * Get single document
     *
     * @param int $document_id Document ID
     * @param int $user_id User ID
     * @return array|null
     */
    public function get_document($document_id, $user_id) {
        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d AND user_id = %d",
                $document_id,
                $user_id
            ),
            ARRAY_A
        );

        return $result ? $this->format_document($result) : null;
    }

    /**
     * Save document
     *
     * @param array $data Document data
     * @param int $user_id User ID
     * @return int|false Document ID or false
     */
    public function save_document($data, $user_id) {
        global $wpdb;

        $document_data = array(
            'user_id' => $user_id,
            'document_type' => sanitize_key($data['type']),
            'document_title' => sanitize_text_field($data['title']),
            'document_data' => wp_json_encode($data['content']),
            'document_meta' => wp_json_encode($data['meta'] ?? array()),
        );

        if (!empty($data['id'])) {
            $wpdb->update(
                $this->table_name,
                $document_data,
                array('id' => absint($data['id']), 'user_id' => $user_id)
            );
            return absint($data['id']);
        }

        $wpdb->insert($this->table_name, $document_data);
        return $wpdb->insert_id ?: false;
    }

    /**
     * Delete document
     *
     * @param int $document_id Document ID
     * @param int $user_id User ID
     * @return bool
     */
    public function delete_document($document_id, $user_id) {
        global $wpdb;
        return $wpdb->delete(
            $this->table_name,
            array('id' => $document_id, 'user_id' => $user_id)
        ) !== false;
    }

    /**
     * Format document for display
     *
     * @param array $row Database row
     * @return array
     */
    private function format_document($row) {
        $type_info = $this->get_document_type($row['document_type']);
        $meta = json_decode($row['document_meta'], true) ?: array();
        
        // Calculate days remaining
        $days_remaining = null;
        $expires_at = null;
        if (!empty($meta['expires_at'])) {
            $expires_at = $meta['expires_at'];
            $expires_timestamp = strtotime($expires_at);
            $now = current_time('timestamp');
            $days_remaining = max(0, ceil(($expires_timestamp - $now) / DAY_IN_SECONDS));
        }

        return array(
            'id' => (int) $row['id'],
            'type' => $row['document_type'],
            'title' => $row['document_title'],
            'type_label' => $type_info ? $type_info['title'] : $row['document_type'],
            'icon' => $type_info ? $type_info['icon'] : '📄',
            'content' => json_decode($row['document_data'], true),
            'meta' => $meta,
            'date' => date_i18n(get_option('date_format'), strtotime($row['created_at'])),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'expires_at' => $expires_at,
            'days_remaining' => $days_remaining,
        );
    }

    /**
     * Render documents page (Create Documents)
     *
     * @return string HTML
     */
    public function render() {
        $user_id = get_current_user_id();
        $documents = $this->get_documents($user_id);
        $doc_types = $this->get_document_types();

        ob_start();
        ?>
        <div class="framt-create-documents">
            <div class="framt-create-header">
                <h2><?php esc_html_e('📄 Create Documents', 'fra-member-tools'); ?></h2>
                <p><?php esc_html_e('Generate personalized documents for your visa application. Select a document type to get started.', 'fra-member-tools'); ?></p>
            </div>

            <div class="framt-doc-types-grid">
                <?php foreach ($doc_types as $type_key => $type) : ?>
                    <div class="framt-doc-type-card" data-action="create-doc-type" data-type="<?php echo esc_attr($type_key); ?>">
                        <span class="framt-doc-type-icon"><?php echo esc_html($type['icon']); ?></span>
                        <h3><?php echo esc_html($type['title']); ?></h3>
                        <p><?php echo esc_html($type['description']); ?></p>
                        <button class="framt-btn framt-btn-primary framt-btn-small" data-action="create-doc-type" data-type="<?php echo esc_attr($type_key); ?>">
                            <?php esc_html_e('Create', 'fra-member-tools'); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($documents)) : ?>
                <div style="margin-top: 2rem;">
                    <h3><?php esc_html_e('📁 Your Saved Documents', 'fra-member-tools'); ?></h3>
                    <div class="framt-documents-list">
                        <?php foreach ($documents as $doc) : ?>
                            <div class="framt-document-card">
                                <span class="framt-doc-icon"><?php echo esc_html($doc['icon']); ?></span>
                                <div class="framt-doc-info">
                                    <strong><?php echo esc_html($doc['title']); ?></strong>
                                    <span><?php echo esc_html($doc['type_label']); ?> • <?php echo esc_html($doc['date']); ?></span>
                                </div>
                                <div class="framt-doc-actions">
                                    <button class="framt-btn framt-btn-small framt-btn-primary" data-action="download-word" data-id="<?php echo esc_attr($doc['id']); ?>">Word</button>
                                    <button class="framt-btn framt-btn-small framt-btn-secondary" data-action="download-pdf" data-id="<?php echo esc_attr($doc['id']); ?>">PDF</button>
                                    <button class="framt-btn framt-btn-small" data-action="edit" data-id="<?php echo esc_attr($doc['id']); ?>">✏️</button>
                                    <button class="framt-btn framt-btn-small framt-btn-danger" data-action="delete" data-id="<?php echo esc_attr($doc['id']); ?>">🗑️</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render upload page - Health Insurance Verification
     *
     * @return string HTML
     */
    public function render_upload() {
        $user_id = get_current_user_id();
        $verification = get_user_meta($user_id, 'framt_health_insurance_verification', true);
        if (!is_array($verification)) {
            $verification = array();
        }
        $profile = FRAMT_Profile::get_instance()->get_profile($user_id);
        $user = wp_get_current_user();
        $name = !empty($profile['legal_first_name']) ? $profile['legal_first_name'] : (!empty($user->first_name) ? $user->first_name : $user->display_name);
        
        // Get visa type for requirements
        $visa_type = isset($profile['visa_type']) ? $profile['visa_type'] : 'visitor';
        
        ob_start();
        ?>
        <div class="framt-health-verify-chat">
            <div class="framt-health-header">
                <div class="framt-health-header-content">
                    <h2><?php esc_html_e('🏥 Health Insurance Verification', 'fra-member-tools'); ?></h2>
                    <p><?php esc_html_e('Upload your health insurance certificate and our AI will verify it meets French visa requirements.', 'fra-member-tools'); ?></p>
                </div>
                <?php if (!empty($verification['status'])) : ?>
                <div class="framt-health-header-actions">
                    <button class="framt-btn framt-btn-secondary framt-btn-small" data-action="clear-verification">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                        <?php esc_html_e('Clear & Start Over', 'fra-member-tools'); ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($verification['status'])) : ?>
            <div class="framt-health-requirements-card">
                <h4><?php esc_html_e('📋 Requirements for Your Visa', 'fra-member-tools'); ?></h4>
                <div class="framt-requirements-grid">
                    <div class="framt-req-item">
                        <span class="framt-req-check">✓</span>
                        <span><?php esc_html_e('Minimum €30,000 coverage', 'fra-member-tools'); ?></span>
                    </div>
                    <div class="framt-req-item">
                        <span class="framt-req-check">✓</span>
                        <span><?php esc_html_e('Hospitalization coverage', 'fra-member-tools'); ?></span>
                    </div>
                    <div class="framt-req-item">
                        <span class="framt-req-check">✓</span>
                        <span><?php esc_html_e('Repatriation coverage', 'fra-member-tools'); ?></span>
                    </div>
                    <div class="framt-req-item">
                        <span class="framt-req-check">✓</span>
                        <span><?php esc_html_e('Valid for entire stay', 'fra-member-tools'); ?></span>
                    </div>
                    <div class="framt-req-item">
                        <span class="framt-req-check">✓</span>
                        <span><?php esc_html_e('Schengen area coverage', 'fra-member-tools'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="framt-health-chat-container">
                <div class="framt-health-chat-messages" id="framt-health-chat-messages">
                    <?php if (!empty($verification['status'])) : ?>
                        <!-- Show previous verification result -->
                        <div class="framt-health-chat-message framt-health-chat-ai">
                            <div class="framt-health-chat-avatar">🏥</div>
                            <div class="framt-health-chat-bubble">
                                <?php $this->render_verification_result_chat($verification); ?>
                            </div>
                        </div>
                    <?php else : ?>
                        <!-- Welcome message -->
                        <div class="framt-health-chat-message framt-health-chat-ai">
                            <div class="framt-health-chat-avatar">🏥</div>
                            <div class="framt-health-chat-bubble">
                                <p><?php printf(esc_html__('Hi %s! 👋 I\'m here to help verify your health insurance certificate meets French visa requirements.', 'fra-member-tools'), esc_html($name)); ?></p>
                                <p><?php esc_html_e('Upload your insurance document below and I\'ll analyze it for you. After the analysis, you can ask me any follow-up questions about the coverage or requirements.', 'fra-member-tools'); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($verification['status'])) : ?>
                <!-- Upload area (only show if not verified yet) -->
                <div class="framt-health-upload-area" id="framt-health-upload-area">
                    <div class="framt-health-dropzone" id="health-insurance-dropzone">
                        <div class="framt-dropzone-content">
                            <span class="framt-dropzone-icon">📄</span>
                            <h4><?php esc_html_e('Drop your certificate here', 'fra-member-tools'); ?></h4>
                            <p><?php esc_html_e('or click to browse', 'fra-member-tools'); ?></p>
                            <span class="framt-file-types"><?php esc_html_e('PDF, JPG, PNG (max 10MB)', 'fra-member-tools'); ?></span>
                        </div>
                        <input type="file" id="health-insurance-file" class="framt-file-input" accept=".pdf,.jpg,.jpeg,.png">
                        <label for="health-insurance-file" class="framt-dropzone-overlay"></label>
                    </div>
                    
                    <div class="framt-health-tips-inline">
                        <p><strong>💡 Tips:</strong> <?php esc_html_e('Upload a clear, readable copy showing coverage amounts and dates.', 'fra-member-tools'); ?></p>
                    </div>
                    
                    <div class="framt-health-analyzing" id="health-analyzing-indicator" style="display:none;">
                        <div class="framt-health-analyzing-content">
                            <div class="framt-spinner"></div>
                            <p><?php esc_html_e('Analyzing your document...', 'fra-member-tools'); ?></p>
                            <span class="framt-analyzing-hint"><?php esc_html_e('This usually takes 15-30 seconds', 'fra-member-tools'); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Chat input for follow-up questions -->
                <div class="framt-health-chat-input-area" id="framt-health-chat-input" <?php echo empty($verification['status']) ? 'style="display:none;"' : ''; ?>>
                    <input type="text" id="framt-health-question-input" placeholder="<?php esc_attr_e('Ask a follow-up question about your coverage...', 'fra-member-tools'); ?>">
                    <button class="framt-btn framt-btn-primary" id="framt-health-send-btn">
                        <?php esc_html_e('Ask', 'fra-member-tools'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render verification result in chat format
     */
    private function render_verification_result_chat($verification) {
        $status = isset($verification['status']) ? $verification['status'] : 'unknown';
        $checklist = isset($verification['checklist']) ? $verification['checklist'] : array();
        $findings = isset($verification['findings']) ? $verification['findings'] : '';
        
        // Status badge
        if ($status === 'verified') {
            echo '<div class="framt-health-status framt-health-status-verified">';
            echo '<span class="framt-status-icon">✅</span>';
            echo '<span class="framt-status-text">' . esc_html__('Certificate Verified', 'fra-member-tools') . '</span>';
            echo '</div>';
            echo '<p>' . esc_html__('Your health insurance certificate meets the French visa requirements.', 'fra-member-tools') . '</p>';
        } elseif ($status === 'issues') {
            echo '<div class="framt-health-status framt-health-status-issues">';
            echo '<span class="framt-status-icon">⚠️</span>';
            echo '<span class="framt-status-text">' . esc_html__('Potential Issues Found', 'fra-member-tools') . '</span>';
            echo '</div>';
        } else {
            echo '<div class="framt-health-status framt-health-status-failed">';
            echo '<span class="framt-status-icon">❌</span>';
            echo '<span class="framt-status-text">' . esc_html__('Does Not Meet Requirements', 'fra-member-tools') . '</span>';
            echo '</div>';
        }
        
        // Checklist - only show if there are items
        if (!empty($checklist) && is_array($checklist)) {
            echo '<div class="framt-health-checklist">';
            
            $labels = array(
                'coverage_amount' => __('Coverage Amount', 'fra-member-tools'),
                'hospitalization' => __('Hospitalization', 'fra-member-tools'),
                'repatriation' => __('Repatriation', 'fra-member-tools'),
                'schengen' => __('Schengen Coverage', 'fra-member-tools'),
                'duration' => __('Duration', 'fra-member-tools'),
                'deductible' => __('Deductible', 'fra-member-tools'),
            );
            
            foreach ($checklist as $key => $item) {
                $label = isset($labels[$key]) ? $labels[$key] : $key;
                $status_class = isset($item['status']) ? $item['status'] : 'unclear';
                $icon = isset($item['icon']) ? $item['icon'] : '?';
                $detail = isset($item['detail']) ? $item['detail'] : '';
                
                echo '<div class="framt-health-check-item framt-check-' . esc_attr($status_class) . '">';
                echo '<span class="framt-check-icon">' . esc_html($icon) . '</span>';
                echo '<div class="framt-check-content">';
                echo '<strong>' . esc_html($label) . ':</strong> ';
                echo '<span>' . esc_html($detail) . '</span>';
                echo '</div>';
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        // Single disclaimer at the end
        echo '<p class="framt-health-disclaimer-text"><em>' . esc_html__('This is AI guidance only. The French consulate makes the final determination.', 'fra-member-tools') . '</em></p>';
        
        // Clear & Start Over button
        echo '<div class="framt-health-actions">';
        echo '<button class="framt-btn framt-btn-secondary framt-btn-small" data-action="clear-verification">';
        echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg> ';
        echo esc_html__('Clear & Start Over', 'fra-member-tools');
        echo '</button>';
        echo '</div>';
    }

    /**
     * Render My Documents page
     *
     * @return string HTML output
     */
    public function render_my_documents() {
        $user_id = get_current_user_id();
        $documents = $this->get_documents($user_id);
        
        ob_start();
        ?>
        <div class="framt-my-documents">
            <div class="framt-documents-header">
                <h2><?php _e('My Visa Documents', 'fra-member-tools'); ?></h2>
                <button class="framt-btn framt-btn-primary" data-action="create-document">
                    <?php _e('+ Create New Document', 'fra-member-tools'); ?>
                </button>
            </div>
            
            <div class="framt-documents-notice">
                <span class="framt-notice-icon">ℹ️</span>
                <p><?php _e('Documents are automatically deleted after 60 days. Download and save important documents to your computer before they expire.', 'fra-member-tools'); ?></p>
            </div>
            
            <?php if (empty($documents)) : ?>
                <div class="framt-empty-state">
                    <div class="framt-empty-icon">📄</div>
                    <h3><?php _e('No documents yet', 'fra-member-tools'); ?></h3>
                    <p><?php _e('Create your first document to get started with your France relocation.', 'fra-member-tools'); ?></p>
                </div>
            <?php else : ?>
                <div class="framt-documents-list">
                    <?php foreach ($documents as $doc) : 
                        $days = $doc['days_remaining'];
                        $expiry_class = '';
                        $expiry_text = '';
                        
                        if ($days !== null) {
                            if ($days <= 7) {
                                $expiry_class = 'framt-expiry-urgent';
                                $expiry_text = sprintf(_n('%d day left', '%d days left', $days, 'fra-member-tools'), $days);
                            } elseif ($days <= 14) {
                                $expiry_class = 'framt-expiry-warning';
                                $expiry_text = sprintf(__('%d days left', 'fra-member-tools'), $days);
                            } else {
                                $expiry_class = 'framt-expiry-normal';
                                $expiry_text = sprintf(__('%d days left', 'fra-member-tools'), $days);
                            }
                        }
                    ?>
                        <div class="framt-document-card">
                            <div class="framt-document-icon"><?php echo esc_html($doc['icon'] ?? '📄'); ?></div>
                            <div class="framt-document-info">
                                <h4><?php echo esc_html($doc['title'] ?? 'Untitled'); ?></h4>
                                <p class="framt-document-type"><?php echo esc_html($doc['type_label'] ?? $doc['type']); ?></p>
                                <div class="framt-document-meta">
                                    <span class="framt-document-date"><?php echo esc_html($doc['date'] ?? ''); ?></span>
                                    <?php if ($expiry_text) : ?>
                                        <span class="framt-document-expiry <?php echo esc_attr($expiry_class); ?>">⏱️ <?php echo esc_html($expiry_text); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="framt-document-actions">
                                <?php if (!empty($doc['content']['file_url'])) : ?>
                                    <a href="<?php echo esc_url($doc['content']['file_url']); ?>" class="framt-doc-action-btn framt-doc-download" title="Download" download>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    </a>
                                <?php endif; ?>
                                <button class="framt-doc-action-btn framt-doc-delete" data-action="delete-document" data-doc-id="<?php echo esc_attr($doc['id']); ?>" title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
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
     * AJAX: Save document
     */
    public function ajax_save_document() {
        check_ajax_referer('framt_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error('Login required');
        }
        $data = $_POST['document'] ?? array();
        $id = $this->save_document($data, get_current_user_id());
        $id ? wp_send_json_success(array('id' => $id)) : wp_send_json_error('Save failed');
    }

    /**
     * AJAX: Get documents
     */
    public function ajax_get_documents() {
        check_ajax_referer('framt_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error('Login required');
        }
        wp_send_json_success(array('documents' => $this->get_documents(get_current_user_id())));
    }

    /**
     * AJAX: Delete document
     */
    public function ajax_delete_document() {
        check_ajax_referer('framt_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error('Login required');
        }
        $id = absint($_POST['document_id'] ?? 0);
        $this->delete_document($id, get_current_user_id())
            ? wp_send_json_success()
            : wp_send_json_error('Delete failed');
    }
}
