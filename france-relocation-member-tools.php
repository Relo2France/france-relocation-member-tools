<?php
/**
 * Plugin Name: France Relocation Member Tools
 * Plugin URI: https://relo2france.com
 * Description: Premium member features for the France Relocation Assistant - document generation, checklists, guides, and personalized relocation planning.
 * Version: 1.0.80
 * Author: Relo2France
 * Author URI: https://relo2france.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fra-member-tools
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * 
 * @package FRA_Member_Tools
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('FRAMT_VERSION', '1.0.80');
define('FRAMT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FRAMT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FRAMT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 * 
 * Handles initialization, dependency checks, and core functionality
 * for the France Relocation Member Tools add-on.
 *
 * @since 1.0.0
 */
final class FRA_Member_Tools {

    /**
     * Singleton instance
     *
     * @var FRA_Member_Tools|null
     */
    private static $instance = null;

    /**
     * Plugin components
     *
     * @var array
     */
    private $components = array();

    /**
     * Get singleton instance
     *
     * @return FRA_Member_Tools
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private to enforce singleton
     */
    private function __construct() {
        $this->define_hooks();
    }

    /**
     * Define plugin hooks
     *
     * @return void
     */
    private function define_hooks() {
        // Check dependencies before loading
        add_action('plugins_loaded', array($this, 'check_dependencies'), 5);
        
        // Initialize plugin after dependencies confirmed
        add_action('plugins_loaded', array($this, 'init'), 10);
        
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Check plugin dependencies
     *
     * @return void
     */
    public function check_dependencies() {
        $missing = array();

        // Check for main France Relocation Assistant plugin
        if (!class_exists('France_Relocation_Assistant')) {
            $missing[] = 'France Relocation Assistant';
        }

        // Check for MemberPress (recommended) or other membership plugins
        $has_membership = $this->detect_membership_plugin();
        
        if (!$has_membership) {
            // Show admin notice but don't block - allow testing without membership
            add_action('admin_notices', array($this, 'membership_plugin_notice'));
        }

        // Block if main plugin missing
        if (!empty($missing)) {
            add_action('admin_notices', function() use ($missing) {
                $this->dependency_notice($missing);
            });
            return;
        }
    }

    /**
     * Detect which membership plugin is active
     *
     * @return string|false Membership plugin identifier or false
     */
    public function detect_membership_plugin() {
        // MemberPress (recommended)
        if (defined('MEPR_VERSION') || class_exists('MeprUser')) {
            return 'memberpress';
        }

        // Paid Memberships Pro
        if (defined('PMPRO_VERSION') || function_exists('pmpro_hasMembershipLevel')) {
            return 'pmpro';
        }

        // Restrict Content Pro
        if (class_exists('RCP_Member') || function_exists('rcp_is_active')) {
            return 'rcp';
        }

        // WooCommerce Memberships
        if (function_exists('wc_memberships')) {
            return 'woocommerce';
        }

        return false;
    }

    /**
     * Show dependency missing notice
     *
     * @param array $missing Missing plugin names
     * @return void
     */
    public function dependency_notice($missing) {
        $plugins = implode(', ', $missing);
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('France Relocation Member Tools', 'fra-member-tools'); ?>:</strong>
                <?php 
                printf(
                    esc_html__('This plugin requires %s to be installed and activated.', 'fra-member-tools'),
                    esc_html($plugins)
                ); 
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Show membership plugin recommendation notice
     *
     * @return void
     */
    public function membership_plugin_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e('France Relocation Member Tools', 'fra-member-tools'); ?>:</strong>
                <?php esc_html_e('No membership plugin detected. We recommend MemberPress for the best experience. The plugin will run in demo mode.', 'fra-member-tools'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Initialize plugin
     *
     * @return void
     */
    public function init() {
        // Don't init if main plugin missing
        if (!class_exists('France_Relocation_Assistant')) {
            return;
        }

        // Load text domain
        load_plugin_textdomain(
            'fra-member-tools',
            false,
            dirname(FRAMT_PLUGIN_BASENAME) . '/languages/'
        );

        // Load core classes
        $this->load_dependencies();

        // Initialize components
        $this->init_components();

        // Register assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Register AJAX handlers
        $this->register_ajax_handlers();
        
        // Register admin menu
        add_action('admin_menu', array($this, 'register_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Hook into main plugin
        $this->integrate_with_main_plugin();
    }

    /**
     * Load required files
     *
     * @return void
     */
    private function load_dependencies() {
        // Core classes
        require_once FRAMT_PLUGIN_DIR . 'includes/class-framt-membership.php';
        require_once FRAMT_PLUGIN_DIR . 'includes/class-framt-profile.php';
        require_once FRAMT_PLUGIN_DIR . 'includes/class-framt-dashboard.php';
        require_once FRAMT_PLUGIN_DIR . 'includes/class-framt-documents.php';
        require_once FRAMT_PLUGIN_DIR . 'includes/class-framt-checklists.php';
        require_once FRAMT_PLUGIN_DIR . 'includes/class-framt-glossary.php';
        require_once FRAMT_PLUGIN_DIR . 'includes/class-framt-guides.php';
        require_once FRAMT_PLUGIN_DIR . 'includes/class-framt-guide-generator.php';
        require_once FRAMT_PLUGIN_DIR . 'includes/class-framt-ai-guide-generator.php';
        require_once FRAMT_PLUGIN_DIR . 'includes/class-framt-chat-handler.php';
        require_once FRAMT_PLUGIN_DIR . 'includes/class-framt-document-generator.php';
        require_once FRAMT_PLUGIN_DIR . 'includes/class-framt-ai-verification.php';
        require_once FRAMT_PLUGIN_DIR . 'includes/class-framt-messages.php';
    }

    /**
     * Initialize plugin components
     *
     * @return void
     */
    private function init_components() {
        $this->components['membership'] = FRAMT_Membership::get_instance();
        $this->components['profile'] = FRAMT_Profile::get_instance();
        $this->components['dashboard'] = FRAMT_Dashboard::get_instance();
        $this->components['documents'] = FRAMT_Documents::get_instance();
        $this->components['checklists'] = FRAMT_Checklists::get_instance();
        $this->components['glossary'] = FRAMT_Glossary::get_instance();
        $this->components['guides'] = FRAMT_Guides::get_instance();
        $this->components['guide_generator'] = FRAMT_Guide_Generator::get_instance();
        $this->components['ai_guide_generator'] = FRAMT_AI_Guide_Generator::get_instance();
        $this->components['chat_handler'] = FRAMT_Chat_Handler::get_instance();
        $this->components['doc_generator'] = FRAMT_Document_Generator::get_instance();
        $this->components['ai_verification'] = FRAMT_AI_Verification::get_instance();
        $this->components['messages'] = new FRAMT_Messages();
    }

    /**
     * Get a plugin component
     *
     * @param string $component Component name
     * @return object|null
     */
    public function get_component($component) {
        return isset($this->components[$component]) ? $this->components[$component] : null;
    }

    /**
     * Register AJAX handlers
     *
     * @return void
     */
    private function register_ajax_handlers() {
        // Section loading
        add_action('wp_ajax_framt_load_section', array($this, 'ajax_load_section'));
        
        // Profile actions
        add_action('wp_ajax_framt_save_profile', array($this->components['profile'], 'ajax_save_profile'));
        add_action('wp_ajax_framt_get_profile', array($this->components['profile'], 'ajax_get_profile'));

        // Document actions
        add_action('wp_ajax_framt_generate_document', array($this->components['doc_generator'], 'ajax_generate_document'));
        add_action('wp_ajax_framt_download_document', array($this->components['doc_generator'], 'ajax_download_document'));
        add_action('wp_ajax_framt_save_document', array($this->components['documents'], 'ajax_save_document'));
        add_action('wp_ajax_framt_get_documents', array($this->components['documents'], 'ajax_get_documents'));
        add_action('wp_ajax_framt_delete_document', array($this->components['documents'], 'ajax_delete_document'));

        // Checklist actions
        add_action('wp_ajax_framt_update_checklist', array($this->components['checklists'], 'ajax_update_checklist'));
        add_action('wp_ajax_framt_get_checklist_status', array($this->components['checklists'], 'ajax_get_status'));
        add_action('wp_ajax_framt_toggle_interactive', array($this, 'ajax_toggle_interactive'));

        // Chat/document creation flow
        add_action('wp_ajax_framt_chat_message', array($this->components['chat_handler'], 'ajax_handle_message'));
        add_action('wp_ajax_framt_start_document_flow', array($this->components['chat_handler'], 'ajax_start_document_flow'));
        add_action('wp_ajax_framt_handle_doc_message', array($this->components['chat_handler'], 'ajax_handle_message'));
        add_action('wp_ajax_framt_doc_chat', array($this, 'ajax_doc_chat'));
        add_action('wp_ajax_framt_download_generated_document', array($this, 'ajax_download_generated_document'));
        
        // Guides
        add_action('wp_ajax_framt_load_guide', array($this, 'ajax_load_guide'));
        add_action('wp_ajax_framt_get_guide_questions', array($this, 'ajax_get_guide_questions'));
        add_action('wp_ajax_framt_generate_guide', array($this, 'ajax_generate_guide'));
        add_action('wp_ajax_framt_download_guide', array($this, 'ajax_download_guide'));
        add_action('wp_ajax_framt_guide_chat', array($this, 'ajax_guide_chat'));

        // Dashboard
        add_action('wp_ajax_framt_get_dashboard_data', array($this->components['dashboard'], 'ajax_get_dashboard_data'));
        
        // Health Insurance Verification
        add_action('wp_ajax_framt_verify_health_insurance', array($this, 'ajax_verify_health_insurance'));
        add_action('wp_ajax_framt_health_followup', array($this, 'ajax_health_followup'));
        add_action('wp_ajax_framt_clear_health_verification', array($this, 'ajax_clear_health_verification'));
        
        // Document cleanup cron
        add_action('framt_cleanup_user_document', array($this, 'cleanup_user_document'), 10, 2);
        add_action('framt_cleanup_generated_document', array($this, 'cleanup_generated_document'), 10, 3);
        add_action('framt_daily_cleanup', array($this, 'cleanup_expired_documents'));
        add_action('wp_ajax_framt_clear_health_insurance_verification', array($this, 'ajax_clear_health_insurance_verification'));
    }
    
    /**
     * AJAX: Load specific guide content
     */
    public function ajax_load_guide() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Login required', 'fra-member-tools')));
            return;
        }
        
        $guide_id = sanitize_key($_POST['guide'] ?? '');
        
        if (empty($guide_id)) {
            wp_send_json_error(array('message' => __('No guide specified', 'fra-member-tools')));
            return;
        }
        
        $html = $this->render_guide_content($guide_id);
        
        if ($html) {
            wp_send_json_success(array('html' => $html));
        } else {
            wp_send_json_error(array('message' => __('Guide not found', 'fra-member-tools')));
        }
    }
    
    /**
     * Render guide content
     */
    private function render_guide_content($guide_id) {
        $guides = $this->components['guides']->get_guides();
        
        if (!isset($guides[$guide_id])) {
            return false;
        }
        
        $guide = $guides[$guide_id];
        $user_id = get_current_user_id();
        
        ob_start();
        ?>
        <div class="framt-guide-detail">
            <button class="framt-btn framt-btn-small framt-btn-secondary" data-action="back-to-guides" style="margin-bottom: 1rem;">← Back to Guides</button>
            
            <div class="framt-guide-header">
                <span class="framt-guide-icon-large"><?php echo esc_html($guide['icon']); ?></span>
                <h2><?php echo esc_html($guide['title']); ?></h2>
                <p><?php echo esc_html($guide['description']); ?></p>
            </div>
            
            <div class="framt-guide-content">
                <?php 
                switch ($guide_id) {
                    case 'apostille':
                        echo $this->render_apostille_guide($user_id);
                        break;
                    case 'pet-relocation':
                        echo $this->render_pet_guide();
                        break;
                    case 'french-mortgages':
                        echo $this->render_mortgage_guide();
                        break;
                    case 'bank-ratings':
                        echo $this->render_bank_ratings_guide();
                        break;
                    default:
                        echo '<p>' . esc_html__('Guide content coming soon.', 'fra-member-tools') . '</p>';
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render apostille guide
     */
    private function render_apostille_guide($user_id) {
        $guide_data = $this->components['guides']->get_apostille_guide($user_id);
        
        ob_start();
        ?>
        <div class="framt-guide-section">
            <h3><?php esc_html_e('What is an Apostille?', 'fra-member-tools'); ?></h3>
            <p><?php esc_html_e('An apostille is an international certification that authenticates the origin of a public document. France requires apostilled documents for vital records (birth, marriage, divorce certificates) used in visa applications.', 'fra-member-tools'); ?></p>
        </div>
        
        <div class="framt-guide-section">
            <h3><?php esc_html_e('Documents That Need Apostilles', 'fra-member-tools'); ?></h3>
            <ul>
                <li><?php esc_html_e('Birth certificates (for all applicants)', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Marriage certificates (if married)', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Divorce decrees (if applicable)', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Death certificates (if widowed)', 'fra-member-tools'); ?></li>
            </ul>
        </div>
        
        <?php if (!empty($guide_data['states'])) : ?>
        <div class="framt-guide-section framt-personalized">
            <h3><?php esc_html_e('📍 Your Personalized Information', 'fra-member-tools'); ?></h3>
            <p><?php esc_html_e('Based on your profile, here are the states where you\'ll need to get apostilles:', 'fra-member-tools'); ?></p>
            
            <?php foreach ($guide_data['states'] as $state => $info) : 
                $state_info = $guide_data['state_info'][$state] ?? null;
            ?>
                <div class="framt-state-card">
                    <h4><?php echo esc_html($state); ?> - <?php echo esc_html($info['document']); ?></h4>
                    <?php if ($state_info) : ?>
                        <ul>
                            <li><strong><?php esc_html_e('Agency:', 'fra-member-tools'); ?></strong> <?php echo esc_html($state_info['agency']); ?></li>
                            <li><strong><?php esc_html_e('Method:', 'fra-member-tools'); ?></strong> <?php echo esc_html($state_info['method']); ?></li>
                            <li><strong><?php esc_html_e('Cost:', 'fra-member-tools'); ?></strong> <?php echo esc_html($state_info['cost']); ?></li>
                            <li><strong><?php esc_html_e('Processing Time:', 'fra-member-tools'); ?></strong> <?php echo esc_html($state_info['time']); ?></li>
                        </ul>
                        <a href="<?php echo esc_url($state_info['url']); ?>" target="_blank" class="framt-btn framt-btn-small framt-btn-primary"><?php esc_html_e('Visit State Website', 'fra-member-tools'); ?></a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else : ?>
        <div class="framt-guide-section">
            <p class="framt-tip"><?php esc_html_e('💡 Complete your profile to see personalized apostille information for your specific states.', 'fra-member-tools'); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="framt-guide-section">
            <h3><?php esc_html_e('General Process', 'fra-member-tools'); ?></h3>
            <ol>
                <li><?php esc_html_e('Obtain a certified copy of the document from the issuing agency', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Submit the document to your state\'s Secretary of State office', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Pay the apostille fee (typically $5-$25 per document)', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Wait for processing (1-6 weeks depending on state)', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Receive document with apostille certificate attached', 'fra-member-tools'); ?></li>
            </ol>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render pet relocation guide
     */
    private function render_pet_guide() {
        ob_start();
        ?>
        <div class="framt-guide-section">
            <h3><?php esc_html_e('Requirements for Bringing Pets to France', 'fra-member-tools'); ?></h3>
            <p><?php esc_html_e('France follows EU pet travel regulations. Here\'s what you need to bring your furry friends.', 'fra-member-tools'); ?></p>
        </div>
        
        <div class="framt-guide-section">
            <h3><?php esc_html_e('Required Documents', 'fra-member-tools'); ?></h3>
            <ul>
                <li><strong><?php esc_html_e('Microchip:', 'fra-member-tools'); ?></strong> <?php esc_html_e('ISO 11784/11785 compliant 15-digit microchip', 'fra-member-tools'); ?></li>
                <li><strong><?php esc_html_e('Rabies Vaccination:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Must be given after microchipping, at least 21 days before travel', 'fra-member-tools'); ?></li>
                <li><strong><?php esc_html_e('EU Health Certificate:', 'fra-member-tools'); ?></strong> <?php esc_html_e('USDA-endorsed within 10 days of travel', 'fra-member-tools'); ?></li>
                <li><strong><?php esc_html_e('Rabies Titer Test:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Not required from US, but recommended', 'fra-member-tools'); ?></li>
            </ul>
        </div>
        
        <div class="framt-guide-section">
            <h3><?php esc_html_e('Timeline', 'fra-member-tools'); ?></h3>
            <ul>
                <li><strong><?php esc_html_e('4+ months before:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Ensure microchip is implanted', 'fra-member-tools'); ?></li>
                <li><strong><?php esc_html_e('3 months before:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Rabies vaccination (if not current)', 'fra-member-tools'); ?></li>
                <li><strong><?php esc_html_e('10 days before:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Vet examination and EU health certificate', 'fra-member-tools'); ?></li>
                <li><strong><?php esc_html_e('2-3 days before:', 'fra-member-tools'); ?></strong> <?php esc_html_e('USDA endorsement of health certificate', 'fra-member-tools'); ?></li>
            </ul>
        </div>
        
        <div class="framt-guide-section">
            <h3><?php esc_html_e('Airline Considerations', 'fra-member-tools'); ?></h3>
            <p><?php esc_html_e('Each airline has different policies for pet travel. Book early as cabin spots for pets are limited. Consider:', 'fra-member-tools'); ?></p>
            <ul>
                <li><?php esc_html_e('Cabin vs. cargo (based on pet size)', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Carrier size requirements', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Temperature restrictions for cargo', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Additional fees ($100-$500+)', 'fra-member-tools'); ?></li>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render mortgage guide
     */
    private function render_mortgage_guide() {
        ob_start();
        ?>
        <div class="framt-guide-section">
            <h3><?php esc_html_e('Getting a French Mortgage as an American', 'fra-member-tools'); ?></h3>
            <p><?php esc_html_e('Yes, Americans can get mortgages in France! The process differs from the US, but many banks welcome international buyers.', 'fra-member-tools'); ?></p>
        </div>
        
        <div class="framt-guide-section">
            <h3><?php esc_html_e('Key Differences from US Mortgages', 'fra-member-tools'); ?></h3>
            <ul>
                <li><?php esc_html_e('Lower LTV ratios (typically 70-80% for non-residents)', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Debt-to-income ratio strict limit of 35%', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Life insurance required for mortgage amount', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Fixed rates more common than variable', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Notaire fees are higher (~7-8% for older properties)', 'fra-member-tools'); ?></li>
            </ul>
        </div>
        
        <div class="framt-guide-section">
            <h3><?php esc_html_e('Documents Needed', 'fra-member-tools'); ?></h3>
            <ul>
                <li><?php esc_html_e('Passport copies', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Last 3 years of tax returns', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Proof of income (pay stubs, pension statements)', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Bank statements (3-6 months)', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Proof of down payment funds', 'fra-member-tools'); ?></li>
                <li><?php esc_html_e('Property details (compromis de vente)', 'fra-member-tools'); ?></li>
            </ul>
        </div>
        
        <div class="framt-guide-section">
            <h3><?php esc_html_e('Recommended Banks for Americans', 'fra-member-tools'); ?></h3>
            <p><?php esc_html_e('Some banks are more experienced with international clients:', 'fra-member-tools'); ?></p>
            <ul>
                <li><strong>BNP Paribas</strong> - <?php esc_html_e('Large international presence', 'fra-member-tools'); ?></li>
                <li><strong>Crédit Agricole</strong> - <?php esc_html_e('Strong in rural areas', 'fra-member-tools'); ?></li>
                <li><strong>CIC</strong> - <?php esc_html_e('Good rates for non-residents', 'fra-member-tools'); ?></li>
                <li><strong>Crédit Mutuel</strong> - <?php esc_html_e('Flexible with self-employed', 'fra-member-tools'); ?></li>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render bank ratings guide
     */
    private function render_bank_ratings_guide() {
        ob_start();
        ?>
        <div class="framt-guide-section">
            <h3><?php esc_html_e('French Banks Comparison for Mortgages', 'fra-member-tools'); ?></h3>
            <p><?php esc_html_e('Based on feedback from Americans who have purchased property in France.', 'fra-member-tools'); ?></p>
        </div>
        
        <div class="framt-bank-ratings">
            <div class="framt-bank-card">
                <h4>BNP Paribas ⭐⭐⭐⭐</h4>
                <p><strong><?php esc_html_e('Best for:', 'fra-member-tools'); ?></strong> <?php esc_html_e('English speakers, online banking', 'fra-member-tools'); ?></p>
                <p><strong><?php esc_html_e('Pros:', 'fra-member-tools'); ?></strong> <?php esc_html_e('International experience, English support, good app', 'fra-member-tools'); ?></p>
                <p><strong><?php esc_html_e('Cons:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Higher fees, slower processing', 'fra-member-tools'); ?></p>
            </div>
            
            <div class="framt-bank-card">
                <h4>Crédit Agricole ⭐⭐⭐⭐</h4>
                <p><strong><?php esc_html_e('Best for:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Rural properties, competitive rates', 'fra-member-tools'); ?></p>
                <p><strong><?php esc_html_e('Pros:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Excellent rural coverage, good rates, local relationships', 'fra-member-tools'); ?></p>
                <p><strong><?php esc_html_e('Cons:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Less English support, varies by branch', 'fra-member-tools'); ?></p>
            </div>
            
            <div class="framt-bank-card">
                <h4>CIC ⭐⭐⭐⭐⭐</h4>
                <p><strong><?php esc_html_e('Best for:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Non-residents, best rates', 'fra-member-tools'); ?></p>
                <p><strong><?php esc_html_e('Pros:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Most competitive rates, experienced with Americans', 'fra-member-tools'); ?></p>
                <p><strong><?php esc_html_e('Cons:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Stricter requirements, limited branch network', 'fra-member-tools'); ?></p>
            </div>
            
            <div class="framt-bank-card">
                <h4>Crédit Mutuel ⭐⭐⭐⭐</h4>
                <p><strong><?php esc_html_e('Best for:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Self-employed, flexible situations', 'fra-member-tools'); ?></p>
                <p><strong><?php esc_html_e('Pros:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Flexible income verification, good customer service', 'fra-member-tools'); ?></p>
                <p><strong><?php esc_html_e('Cons:', 'fra-member-tools'); ?></strong> <?php esc_html_e('Rates slightly higher, regional variations', 'fra-member-tools'); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Get guide questions
     */
    public function ajax_get_guide_questions() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Login required', 'fra-member-tools')));
            return;
        }
        
        $guide_type = sanitize_key($_POST['guide_type'] ?? '');
        
        if (empty($guide_type)) {
            wp_send_json_error(array('message' => __('No guide type specified', 'fra-member-tools')));
            return;
        }
        
        $generator = $this->components['guide_generator'];
        $questions = $generator->get_guide_questions($guide_type);
        
        // Get user profile for pre-filling
        $profile = FRAMT_Profile::get_instance()->get_profile(get_current_user_id());
        
        // Guide titles
        $guide_titles = array(
            'pet-relocation' => __('Pet Relocation Guide', 'fra-member-tools'),
            'french-mortgages' => __('French Mortgage Guide', 'fra-member-tools'),
            'apostille' => __('Apostille Guide', 'fra-member-tools'),
            'bank-ratings' => __('Bank Comparison Guide', 'fra-member-tools'),
        );
        $title = isset($guide_titles[$guide_type]) ? $guide_titles[$guide_type] : __('Personalized Guide', 'fra-member-tools');
        
        wp_send_json_success(array(
            'questions' => $questions,
            'profile' => $profile,
            'title' => $title,
        ));
    }
    
    /**
     * AJAX: Generate personalized guide
     */
    public function ajax_generate_guide() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Login required', 'fra-member-tools')));
            return;
        }
        
        $guide_type = sanitize_key($_POST['guide_type'] ?? '');
        $answers = json_decode(stripslashes($_POST['answers'] ?? '{}'), true);
        
        if (empty($guide_type)) {
            wp_send_json_error(array('message' => __('No guide type specified', 'fra-member-tools')));
            return;
        }
        
        try {
            $ai_generator = $this->components['ai_guide_generator'];
            $profile = FRAMT_Profile::get_instance()->get_profile(get_current_user_id());
            
            // Check if AI is configured
            if (!$ai_generator->is_configured()) {
                // Fall back to template-based generator
                $generator = $this->components['guide_generator'];
                $guide_data = $generator->generate_guide($guide_type, $answers, $profile);
                
                if ($guide_data) {
                    $guide_id = 'guide_' . get_current_user_id() . '_' . time();
                    set_transient($guide_id, $guide_data, HOUR_IN_SECONDS);
                    
                    wp_send_json_success(array(
                        'guide_id' => $guide_id,
                        'title' => $guide_data['title'],
                        'content' => $guide_data['content'],
                        'ai_generated' => false,
                    ));
                } else {
                    wp_send_json_error(array('message' => __('Failed to generate guide.', 'fra-member-tools')));
                }
                return;
            }
            
            // Use AI generator
            $guide_data = $ai_generator->generate_guide($guide_type, $answers, $profile);
            
            if (is_wp_error($guide_data)) {
                wp_send_json_error(array('message' => $guide_data->get_error_message()));
                return;
            }
            
            // Store the generated guide for download
            $guide_id = 'guide_' . get_current_user_id() . '_' . time();
            set_transient($guide_id, $guide_data, HOUR_IN_SECONDS);
            
            wp_send_json_success(array(
                'guide_id' => $guide_id,
                'title' => $guide_data['title'],
                'subtitle' => $guide_data['subtitle'],
                'date' => $guide_data['date'],
                'preview' => wp_trim_words(strip_tags($guide_data['ai_content']), 100, '...'),
                'ai_generated' => true,
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        } catch (Error $e) {
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Download generated guide
     */
    public function ajax_download_guide() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Login required', 'fra-member-tools')));
            return;
        }
        
        $guide_id = sanitize_key($_POST['guide_id'] ?? '');
        $format = sanitize_key($_POST['format'] ?? 'word');
        
        if (empty($guide_id)) {
            wp_send_json_error(array('message' => __('No guide ID specified', 'fra-member-tools')));
            return;
        }
        
        // Get the stored guide data
        $guide_data = get_transient($guide_id);
        
        if (!$guide_data) {
            wp_send_json_error(array('message' => __('Guide not found or expired. Please regenerate.', 'fra-member-tools')));
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Create user-specific document directory
        $upload_dir = wp_upload_dir();
        $user_doc_dir = $upload_dir['basedir'] . '/framt-documents/user-' . $user_id;
        
        if (!file_exists($user_doc_dir)) {
            wp_mkdir_p($user_doc_dir);
        }
        
        $filename = sanitize_file_name($guide_data['title'] . '-' . date('Y-m-d'));
        
        // Check if this is an AI-generated guide
        $is_ai_guide = isset($guide_data['ai_content']);
        
        if ($format === 'word') {
            if ($is_ai_guide) {
                $ai_generator = $this->components['ai_guide_generator'];
                $html = $ai_generator->to_word_document($guide_data);
            } else {
                $generator = $this->components['guide_generator'];
                $html = $generator->to_word_document($guide_data);
            }
            
            $file_path = $user_doc_dir . '/' . $filename . '.doc';
            file_put_contents($file_path, $html);
            
            $url = $upload_dir['baseurl'] . '/framt-documents/user-' . $user_id . '/' . $filename . '.doc';
            $file_ext = 'doc';
        } else {
            // PDF - Generate print-ready HTML (same styling as Word, optimized for printing)
            if ($is_ai_guide) {
                $ai_generator = $this->components['ai_guide_generator'];
                $html = $ai_generator->to_word_document($guide_data);
            } else {
                $generator = $this->components['guide_generator'];
                $html = $generator->to_word_document($guide_data);
            }
            
            // Add print-specific CSS and auto-print script
            $print_additions = '
<style>
@media print {
    body { margin: 0; padding: 20pt; }
    .no-print { display: none; }
}
@page {
    margin: 0.75in;
    size: letter;
}
</style>
<script>
// Auto-trigger print dialog when opened
window.onload = function() {
    // Small delay to ensure styles are loaded
    setTimeout(function() {
        window.print();
    }, 500);
};
</script>';
            
            // Insert before </head>
            $html = str_replace('</head>', $print_additions . '</head>', $html);
            
            // Add print instruction banner at top (hidden when printing)
            $print_banner = '<div class="no-print" style="background: #fffbeb; border: 1px solid #f59e0b; padding: 12px 20px; margin-bottom: 20px; border-radius: 6px; font-family: system-ui, sans-serif;">
                <strong>📄 Print to PDF:</strong> A print dialog should open automatically. Select "Save as PDF" or "Microsoft Print to PDF" as your printer to save this document.
                <button onclick="window.print()" style="margin-left: 15px; padding: 6px 12px; background: #1a1a1a; color: white; border: none; border-radius: 4px; cursor: pointer;">Print Now</button>
            </div>';
            
            // Insert after <body>
            $html = preg_replace('/(<body[^>]*>)/', '$1' . $print_banner, $html);
            
            $file_path = $user_doc_dir . '/' . $filename . '.html';
            file_put_contents($file_path, $html);
            
            $url = $upload_dir['baseurl'] . '/framt-documents/user-' . $user_id . '/' . $filename . '.html';
            $file_ext = 'html';
        }
        
        // Save document record to database for "My Documents"
        $this->save_user_document($user_id, array(
            'title' => $guide_data['title'],
            'type' => 'guide',
            'guide_type' => $guide_data['type'] ?? '',
            'file_path' => $file_path,
            'file_url' => $url,
            'file_ext' => $file_ext,
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ));
        
        // Schedule cleanup for 30 days
        wp_schedule_single_event(strtotime('+30 days'), 'framt_cleanup_user_document', array($file_path, $user_id));
        
        wp_send_json_success(array('url' => $url));
    }
    
    /**
     * AJAX: Guide chat - AI-powered guide creation via conversational interface
     */
    public function ajax_guide_chat() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Login required', 'fra-member-tools')));
            return;
        }
        
        $guide_type = sanitize_key($_POST['guide_type'] ?? '');
        $message = sanitize_text_field($_POST['message'] ?? '');
        $context = json_decode(stripslashes($_POST['context'] ?? '{}'), true);
        
        if (empty($guide_type)) {
            wp_send_json_error(array('message' => __('No guide type specified', 'fra-member-tools')));
            return;
        }
        
        $user_id = get_current_user_id();
        $profile = FRAMT_Profile::get_instance()->get_profile($user_id);
        
        // Guide configurations
        $guide_configs = $this->get_guide_chat_configs();
        
        if (!isset($guide_configs[$guide_type])) {
            wp_send_json_error(array('message' => __('Unknown guide type', 'fra-member-tools')));
            return;
        }
        
        $config = $guide_configs[$guide_type];
        $step = $context['step'] ?? 0;
        $answers = $context['answers'] ?? array();
        
        // Handle the message based on current step
        if ($message === 'start') {
            // Initial greeting
            $response = $this->get_guide_chat_intro($guide_type, $config, $profile);
        } else {
            // Process the answer and get next question
            $response = $this->process_guide_chat_answer($guide_type, $config, $message, $context, $profile);
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Get guide chat configurations
     */
    private function get_guide_chat_configs() {
        return array(
            'apostille' => array(
                'title' => __('Apostille Guide', 'fra-member-tools'),
                'intro' => __("I'll help you create a personalized apostille guide. This will include step-by-step instructions for getting your documents authenticated for use in France.", 'fra-member-tools'),
                'questions' => array(
                    array(
                        'key' => 'documents_needed',
                        'question' => __('Which documents do you need apostilled? (Select all that apply)', 'fra-member-tools'),
                        'type' => 'multi',
                        'options' => array(
                            array('value' => 'birth_cert', 'label' => 'Birth certificate'),
                            array('value' => 'marriage_cert', 'label' => 'Marriage certificate'),
                            array('value' => 'divorce_decree', 'label' => 'Divorce decree'),
                            array('value' => 'death_cert', 'label' => 'Death certificate'),
                            array('value' => 'court_docs', 'label' => 'Court documents'),
                            array('value' => 'diploma', 'label' => 'Diploma/degree'),
                            array('value' => 'background_check', 'label' => 'FBI background check'),
                        ),
                    ),
                    array(
                        'key' => 'birth_state',
                        'question' => __('Which US state were you born in? (This determines where to get your birth certificate apostilled)', 'fra-member-tools'),
                        'type' => 'text',
                        'placeholder' => 'e.g., California, Texas, New York...',
                        'condition' => array('documents_needed' => array('birth_cert')),
                        'profile_field' => 'birth_state',
                    ),
                    array(
                        'key' => 'marriage_state',
                        'question' => __('Which state were you married in?', 'fra-member-tools'),
                        'type' => 'text',
                        'placeholder' => 'e.g., California, Texas, New York...',
                        'condition' => array('documents_needed' => array('marriage_cert')),
                        'profile_field' => 'marriage_state',
                    ),
                    array(
                        'key' => 'urgency',
                        'question' => __('How soon do you need these apostilled documents?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'asap', 'label' => '🚨 As soon as possible (expedited)'),
                            array('value' => '2_4_weeks', 'label' => '📅 Within 2-4 weeks'),
                            array('value' => 'flexible', 'label' => '🕐 No rush, flexible timeline'),
                        ),
                    ),
                ),
            ),
            'pet-relocation' => array(
                'title' => __('Pet Relocation Guide', 'fra-member-tools'),
                'intro' => __("I'll create a personalized guide for bringing your pet to France, including all the veterinary requirements, paperwork, and timeline.", 'fra-member-tools'),
                'questions' => array(
                    array(
                        'key' => 'pet_type',
                        'question' => __('What type of pet are you bringing to France?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'dog', 'label' => '🐕 Dog'),
                            array('value' => 'cat', 'label' => '🐈 Cat'),
                            array('value' => 'both', 'label' => '🐕🐈 Both dog and cat'),
                        ),
                    ),
                    array(
                        'key' => 'pet_count',
                        'question' => __('How many pets are you bringing?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => '1', 'label' => '1 pet'),
                            array('value' => '2', 'label' => '2 pets'),
                            array('value' => '3_plus', 'label' => '3 or more pets'),
                        ),
                    ),
                    array(
                        'key' => 'travel_method',
                        'question' => __('How will you be traveling to France?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'air_cabin', 'label' => '✈️ Flying - pet in cabin'),
                            array('value' => 'air_cargo', 'label' => '✈️ Flying - pet in cargo'),
                            array('value' => 'unsure', 'label' => '🤔 Not sure yet'),
                        ),
                    ),
                    array(
                        'key' => 'departure_state',
                        'question' => __('Which US state will you be departing from?', 'fra-member-tools'),
                        'type' => 'text',
                        'placeholder' => 'e.g., California, Texas, New York...',
                    ),
                    array(
                        'key' => 'move_date',
                        'question' => __('When are you planning to move? (This helps create your preparation timeline)', 'fra-member-tools'),
                        'type' => 'text',
                        'placeholder' => 'e.g., March 2025, Summer 2025...',
                    ),
                ),
            ),
            'french-mortgages' => array(
                'title' => __('French Mortgage Guide', 'fra-member-tools'),
                'intro' => __("I'll help you understand French mortgages and create a personalized evaluation based on your situation.", 'fra-member-tools'),
                'questions' => array(
                    array(
                        'key' => 'purchase_price',
                        'question' => __('What is the approximate purchase price of the property you\'re considering?', 'fra-member-tools'),
                        'type' => 'text',
                        'placeholder' => 'e.g., €500,000',
                    ),
                    array(
                        'key' => 'loan_amount',
                        'question' => __('How much do you plan to borrow?', 'fra-member-tools'),
                        'type' => 'text',
                        'placeholder' => 'e.g., €400,000',
                    ),
                    array(
                        'key' => 'loan_term',
                        'question' => __('What loan term are you considering?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => '15', 'label' => '15 years'),
                            array('value' => '20', 'label' => '20 years'),
                            array('value' => '25', 'label' => '25 years'),
                            array('value' => 'unsure', 'label' => 'Not sure yet'),
                        ),
                    ),
                    array(
                        'key' => 'using_broker',
                        'question' => __('Are you planning to use a mortgage broker?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'yes', 'label' => 'Yes, I have a broker'),
                            array('value' => 'considering', 'label' => 'Considering it'),
                            array('value' => 'no', 'label' => 'No, going directly to banks'),
                        ),
                    ),
                ),
            ),
            'bank-ratings' => array(
                'title' => __('Bank Comparison Guide', 'fra-member-tools'),
                'intro' => __("I'll help you compare French banks and find the best options for Americans moving to France.", 'fra-member-tools'),
                'questions' => array(
                    array(
                        'key' => 'banking_needs',
                        'question' => __('What are your primary banking needs? (Select all that apply)', 'fra-member-tools'),
                        'type' => 'multi',
                        'options' => array(
                            array('value' => 'daily', 'label' => 'Daily banking (checking/debit)'),
                            array('value' => 'mortgage', 'label' => 'Mortgage financing'),
                            array('value' => 'savings', 'label' => 'Savings/investment'),
                            array('value' => 'transfers', 'label' => 'International transfers'),
                        ),
                    ),
                    array(
                        'key' => 'english_support',
                        'question' => __('How important is English language support?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'essential', 'label' => 'Essential - I don\'t speak French'),
                            array('value' => 'preferred', 'label' => 'Preferred but not required'),
                            array('value' => 'not_needed', 'label' => 'Not needed - I speak French'),
                        ),
                    ),
                    array(
                        'key' => 'online_banking',
                        'question' => __('How important is online/mobile banking?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'essential', 'label' => 'Essential - my primary way to bank'),
                            array('value' => 'important', 'label' => 'Important but not critical'),
                            array('value' => 'not_important', 'label' => 'Not important'),
                        ),
                    ),
                ),
            ),
        );
    }
    
    /**
     * Get guide chat intro message
     */
    private function get_guide_chat_intro($guide_type, $config, $profile) {
        $user = wp_get_current_user();
        $name = $user->first_name ?: 'there';
        
        $greeting = sprintf(__('Hi %s! ', 'fra-member-tools'), $name) . $config['intro'];
        
        $first_question = $config['questions'][0] ?? null;
        
        if (!$first_question) {
            return array(
                'message' => $greeting,
                'generating' => true,
            );
        }
        
        // Check if we can pre-fill from profile
        $profile_hint = '';
        if (!empty($first_question['profile_field']) && !empty($profile[$first_question['profile_field']])) {
            $profile_hint = "\n\n" . sprintf(__('(From your profile: **%s**)', 'fra-member-tools'), $profile[$first_question['profile_field']]);
        }
        
        return array(
            'message' => $greeting . "\n\n" . $first_question['question'] . $profile_hint,
            'options' => $first_question['type'] === 'options' ? $first_question['options'] : null,
            'multi_select' => $first_question['type'] === 'multi' ? $first_question['options'] : null,
            'show_input' => $first_question['type'] === 'text',
            'placeholder' => $first_question['placeholder'] ?? '',
            'step' => 0,
            'collected' => array(),
        );
    }
    
    /**
     * Process guide chat answer and get next question
     */
    private function process_guide_chat_answer($guide_type, $config, $message, $context, $profile) {
        $step = $context['step'] ?? 0;
        $answers = $context['answers'] ?? array();
        $questions = $config['questions'];
        
        // Save current answer
        if (isset($questions[$step])) {
            $current_question = $questions[$step];
            $answers[$current_question['key']] = $message;
        }
        
        // Find next applicable question
        $next_step = $step + 1;
        $next_question = null;
        
        while ($next_step < count($questions)) {
            $q = $questions[$next_step];
            
            // Check conditions
            if (!empty($q['condition'])) {
                $condition_met = false;
                foreach ($q['condition'] as $field => $valid_values) {
                    $answer_value = $answers[$field] ?? '';
                    // Handle array answers (multi-select)
                    if (is_array($answer_value)) {
                        foreach ($valid_values as $v) {
                            if (in_array($v, $answer_value)) {
                                $condition_met = true;
                                break;
                            }
                        }
                    } else {
                        if (in_array($answer_value, $valid_values)) {
                            $condition_met = true;
                        }
                    }
                }
                if (!$condition_met) {
                    $next_step++;
                    continue;
                }
            }
            
            $next_question = $q;
            break;
        }
        
        // If no more questions, generate the guide
        if (!$next_question) {
            return $this->generate_guide_from_chat($guide_type, $answers, $profile);
        }
        
        // Check profile for pre-fill hint
        $profile_hint = '';
        if (!empty($next_question['profile_field']) && !empty($profile[$next_question['profile_field']])) {
            $profile_hint = "\n\n" . sprintf(__('(From your profile: **%s**)', 'fra-member-tools'), $profile[$next_question['profile_field']]);
        }
        
        // Check if this is the last question
        $remaining = 0;
        for ($i = $next_step + 1; $i < count($questions); $i++) {
            $q = $questions[$i];
            if (empty($q['condition'])) {
                $remaining++;
            } else {
                foreach ($q['condition'] as $field => $valid_values) {
                    if (isset($answers[$field]) && in_array($answers[$field], $valid_values)) {
                        $remaining++;
                        break;
                    }
                }
            }
        }
        $is_last = ($remaining === 0);
        
        $message_text = $next_question['question'] . $profile_hint;
        if ($is_last) {
            $message_text .= "\n\n" . __('_(This is the last question - your guide will be generated after you answer.)_', 'fra-member-tools');
        }
        
        return array(
            'message' => $message_text,
            'options' => $next_question['type'] === 'options' ? $next_question['options'] : null,
            'multi_select' => $next_question['type'] === 'multi' ? $next_question['options'] : null,
            'show_input' => $next_question['type'] === 'text',
            'placeholder' => $next_question['placeholder'] ?? '',
            'step' => $next_step,
            'collected' => $answers,
            'is_last_question' => $is_last,
        );
    }
    
    /**
     * Generate guide from chat answers
     */
    private function generate_guide_from_chat($guide_type, $answers, $profile) {
        try {
            $ai_generator = $this->components['ai_guide_generator'];
            
            // Check if AI is configured
            if ($ai_generator->is_configured()) {
                $guide_data = $ai_generator->generate_guide($guide_type, $answers, $profile);
                
                if (is_wp_error($guide_data)) {
                    // Fall back to template
                    $generator = $this->components['guide_generator'];
                    $guide_data = $generator->generate_guide($guide_type, $answers, $profile);
                }
            } else {
                // Use template-based generator
                $generator = $this->components['guide_generator'];
                $guide_data = $generator->generate_guide($guide_type, $answers, $profile);
            }
            
            if (!$guide_data) {
                return array(
                    'message' => __('Sorry, there was an error generating your guide. Please try again.', 'fra-member-tools'),
                    'error' => true,
                );
            }
            
            // Store the guide for download
            $guide_id = 'guide_' . get_current_user_id() . '_' . time();
            set_transient($guide_id, $guide_data, HOUR_IN_SECONDS);
            
            return array(
                'message' => __('✅ Your personalized guide is ready!', 'fra-member-tools'),
                'generating' => true,
                'guide_ready' => true,
                'guide_id' => $guide_id,
                'guide_title' => $guide_data['title'],
            );
            
        } catch (Exception $e) {
            return array(
                'message' => __('Sorry, there was an error generating your guide. Please try again.', 'fra-member-tools'),
                'error' => true,
            );
        }
    }
    
    /**
     * AJAX: Document chat - AI-powered document creation
     */
    public function ajax_doc_chat() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Login required', 'fra-member-tools')));
            return;
        }
        
        $document_type = sanitize_key($_POST['document_type'] ?? '');
        $message = sanitize_text_field($_POST['message'] ?? '');
        $context = json_decode(stripslashes($_POST['context'] ?? '{}'), true);
        
        if (empty($document_type)) {
            wp_send_json_error(array('message' => __('No document type specified', 'fra-member-tools')));
            return;
        }
        
        $user_id = get_current_user_id();
        $profile = FRAMT_Profile::get_instance()->get_profile($user_id);
        
        // Document type configurations
        $doc_configs = $this->get_document_chat_configs();
        
        if (!isset($doc_configs[$document_type])) {
            wp_send_json_error(array('message' => __('Unknown document type', 'fra-member-tools')));
            return;
        }
        
        $config = $doc_configs[$document_type];
        $step = $context['step'] ?? 0;
        $answers = $context['answers'] ?? array();
        
        // Handle the message based on current step
        if ($message === 'start') {
            // Initial greeting
            $response = $this->get_doc_chat_intro($document_type, $config, $profile);
        } else {
            // Process the answer and get next question
            $response = $this->process_doc_chat_answer($document_type, $config, $message, $context, $profile);
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Get document chat configurations
     */
    private function get_document_chat_configs() {
        return array(
            'cover-letter' => array(
                'title' => __('Visa Cover Letter', 'fra-member-tools'),
                'questions' => array(
                    array(
                        'key' => 'visa_type',
                        'question' => __('What type of visa are you applying for?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'visitor', 'label' => 'Visitor (VLS-TS Visiteur)'),
                            array('value' => 'talent', 'label' => 'Talent Passport'),
                            array('value' => 'student', 'label' => 'Student Visa'),
                            array('value' => 'family', 'label' => 'Family Reunification'),
                            array('value' => 'retirement', 'label' => 'Retirement Visa'),
                        ),
                        'profile_field' => 'visa_type',
                    ),
                    array(
                        'key' => 'consulate',
                        'question' => __('Which consulate are you applying to?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'washington', 'label' => 'Washington, D.C.'),
                            array('value' => 'newyork', 'label' => 'New York'),
                            array('value' => 'losangeles', 'label' => 'Los Angeles'),
                            array('value' => 'chicago', 'label' => 'Chicago'),
                            array('value' => 'houston', 'label' => 'Houston'),
                            array('value' => 'miami', 'label' => 'Miami'),
                            array('value' => 'atlanta', 'label' => 'Atlanta'),
                            array('value' => 'sanfrancisco', 'label' => 'San Francisco'),
                            array('value' => 'boston', 'label' => 'Boston'),
                        ),
                        'profile_field' => 'application_location',
                    ),
                    array(
                        'key' => 'accommodation',
                        'question' => __('What is your accommodation situation in France?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'purchased', 'label' => 'I have purchased property'),
                            array('value' => 'purchasing', 'label' => 'Currently purchasing property'),
                            array('value' => 'renting', 'label' => 'I have a rental arrangement'),
                            array('value' => 'staying_family', 'label' => 'Staying with family/friends'),
                            array('value' => 'temporary', 'label' => 'Temporary accommodation while searching'),
                        ),
                        'profile_field' => 'housing_plans',
                    ),
                    array(
                        'key' => 'property_details',
                        'question' => __('Please describe your property or accommodation (address, type of property, region):'),
                        'type' => 'text',
                        'placeholder' => 'e.g., 3-bedroom house in Dordogne, or apartment rental in Paris',
                        'condition' => array('accommodation' => array('purchased', 'purchasing', 'renting')),
                    ),
                    array(
                        'key' => 'move_reason',
                        'question' => __('What is your primary reason for moving to France? (This personalizes your letter)', 'fra-member-tools'),
                        'type' => 'text',
                        'placeholder' => 'e.g., Quality of life, retirement, cultural immersion, family...',
                    ),
                    array(
                        'key' => 'applicants',
                        'question' => __('Who is included in this visa application?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'solo', 'label' => 'Just myself'),
                            array('value' => 'couple', 'label' => 'Myself and spouse/partner'),
                            array('value' => 'family', 'label' => 'Family with children'),
                        ),
                        'profile_field' => 'applicants',
                    ),
                ),
            ),
            'financial-statement' => array(
                'title' => __('Proof of Sufficient Means', 'fra-member-tools'),
                'questions' => array(
                    array(
                        'key' => 'consulate',
                        'question' => __('Which consulate are you applying to?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'washington', 'label' => 'Washington, D.C.'),
                            array('value' => 'newyork', 'label' => 'New York'),
                            array('value' => 'losangeles', 'label' => 'Los Angeles'),
                            array('value' => 'chicago', 'label' => 'Chicago'),
                            array('value' => 'houston', 'label' => 'Houston'),
                            array('value' => 'miami', 'label' => 'Miami'),
                            array('value' => 'atlanta', 'label' => 'Atlanta'),
                            array('value' => 'sanfrancisco', 'label' => 'San Francisco'),
                            array('value' => 'boston', 'label' => 'Boston'),
                        ),
                        'profile_field' => 'application_location',
                    ),
                    array(
                        'key' => 'applicants',
                        'question' => __('Who is included in this visa application?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'solo', 'label' => 'Just myself'),
                            array('value' => 'couple', 'label' => 'Myself and spouse/partner'),
                            array('value' => 'family', 'label' => 'Family with children'),
                        ),
                    ),
                    array(
                        'key' => 'employment_status',
                        'question' => __('What is your current employment status?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'employed', 'label' => 'Currently employed'),
                            array('value' => 'self_employed', 'label' => 'Self-employed / Business owner'),
                            array('value' => 'retired', 'label' => 'Retired'),
                            array('value' => 'not_working', 'label' => 'Not currently working (savings/investments)'),
                        ),
                    ),
                    array(
                        'key' => 'employment_details',
                        'question' => __('Please describe your employment (job title, company, annual salary):'),
                        'type' => 'text',
                        'placeholder' => 'e.g., CEO at Tech Company, $150,000/year',
                        'condition' => array('employment_status' => array('employed', 'self_employed')),
                    ),
                    array(
                        'key' => 'financial_summary',
                        'question' => __('Please provide a summary of your financial resources. Include: bank account balances, retirement accounts (401k, IRA), investments, and any other assets.'),
                        'type' => 'text',
                        'placeholder' => 'e.g., $250,000 savings, $130,000 in 401(k), $50,000 in stocks...',
                    ),
                    array(
                        'key' => 'documents_attached',
                        'question' => __('What supporting documents will you attach?'),
                        'type' => 'text',
                        'placeholder' => 'e.g., Bank statements, pay stubs, employment letter, 401(k) statement, tax returns...',
                    ),
                ),
            ),
            'attestation' => array(
                'title' => __('No Work Attestation', 'fra-member-tools'),
                'questions' => array(
                    array(
                        'key' => 'birth_date',
                        'question' => __('What is your date of birth?'),
                        'type' => 'text',
                        'placeholder' => 'e.g., January 15, 1985',
                        'profile_field' => 'birth_date',
                    ),
                    array(
                        'key' => 'birth_place',
                        'question' => __('Where were you born? (City, State/Province, Country)'),
                        'type' => 'text',
                        'placeholder' => 'e.g., Denver, Colorado, USA',
                        'profile_field' => 'birth_place',
                    ),
                    array(
                        'key' => 'current_address',
                        'question' => __('What is your current home address?'),
                        'type' => 'text',
                        'placeholder' => 'e.g., 123 Main Street, City, State ZIP, Country',
                        'profile_field' => 'current_address',
                    ),
                    array(
                        'key' => 'visa_type',
                        'question' => __('What type of visa are you applying for?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'visitor', 'label' => 'Long-Stay Visitor Visa (VLS-TS Visiteur)'),
                            array('value' => 'retirement', 'label' => 'Retirement Visa'),
                            array('value' => 'other', 'label' => 'Other non-work visa'),
                        ),
                        'profile_field' => 'visa_type',
                    ),
                ),
            ),
            'accommodation-letter' => array(
                'title' => __('Proof of Accommodation', 'fra-member-tools'),
                'questions' => array(
                    array(
                        'key' => 'consulate',
                        'question' => __('Which consulate are you applying to?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'washington', 'label' => 'Washington, D.C.'),
                            array('value' => 'newyork', 'label' => 'New York'),
                            array('value' => 'losangeles', 'label' => 'Los Angeles'),
                            array('value' => 'chicago', 'label' => 'Chicago'),
                            array('value' => 'houston', 'label' => 'Houston'),
                            array('value' => 'miami', 'label' => 'Miami'),
                            array('value' => 'atlanta', 'label' => 'Atlanta'),
                            array('value' => 'sanfrancisco', 'label' => 'San Francisco'),
                            array('value' => 'boston', 'label' => 'Boston'),
                        ),
                        'profile_field' => 'application_location',
                    ),
                    array(
                        'key' => 'accommodation_type',
                        'question' => __('What is your accommodation situation?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'purchase_complete', 'label' => 'Property purchase completed'),
                            array('value' => 'purchase_pending', 'label' => 'Property purchase in progress (signed compromis de vente)'),
                            array('value' => 'rental', 'label' => 'Rental arrangement'),
                            array('value' => 'host', 'label' => 'Staying with host (family/friend)'),
                        ),
                    ),
                    array(
                        'key' => 'property_address',
                        'question' => __('What is the full property address in France?'),
                        'type' => 'text',
                        'placeholder' => 'e.g., 45 Rue de la République, 75011 Paris, France',
                    ),
                    array(
                        'key' => 'property_type',
                        'question' => __('What type of property is it?'),
                        'type' => 'text',
                        'placeholder' => 'e.g., Residential dwelling, apartment, house, château...',
                    ),
                    array(
                        'key' => 'purchase_price',
                        'question' => __('What is the purchase price? (If applicable)'),
                        'type' => 'text',
                        'placeholder' => 'e.g., €820,000',
                        'condition' => array('accommodation_type' => array('purchase_complete', 'purchase_pending')),
                    ),
                    array(
                        'key' => 'deposit_paid',
                        'question' => __('How much deposit has been paid? (If applicable)'),
                        'type' => 'text',
                        'placeholder' => 'e.g., €82,000 (10%)',
                        'condition' => array('accommodation_type' => array('purchase_pending')),
                    ),
                    array(
                        'key' => 'expected_closing',
                        'question' => __('When is the expected closing date?'),
                        'type' => 'text',
                        'placeholder' => 'e.g., January-February 2026',
                        'condition' => array('accommodation_type' => array('purchase_pending')),
                    ),
                    array(
                        'key' => 'applicants',
                        'question' => __('Who will be residing at this property?', 'fra-member-tools'),
                        'type' => 'options',
                        'options' => array(
                            array('value' => 'solo', 'label' => 'Just myself'),
                            array('value' => 'couple', 'label' => 'Myself and spouse/partner'),
                            array('value' => 'family', 'label' => 'Family with children'),
                        ),
                    ),
                    array(
                        'key' => 'documents_attached',
                        'question' => __('What supporting documents will you attach?'),
                        'type' => 'text',
                        'placeholder' => 'e.g., Compromis de vente, mortgage commitment, deposit proof, power of attorney...',
                    ),
                ),
            ),
        );
    }
    
    /**
     * Get document chat intro message
     */
    private function get_doc_chat_intro($document_type, $config, $profile) {
        $user = wp_get_current_user();
        $name = $profile['legal_first_name'] ?? $user->first_name ?: $user->display_name;
        
        $intro = sprintf(
            __('Hi %s! 👋 I\'ll help you create your **%s**.', 'fra-member-tools'),
            $name,
            $config['title']
        );
        
        $intro .= "\n\n" . __('I\'ll ask you a few questions to personalize the document. Some answers may already be filled from your profile.', 'fra-member-tools');
        
        // Get the first question
        $first_question = $config['questions'][0] ?? null;
        
        if ($first_question) {
            // Check if we have this from profile
            $profile_value = null;
            if (!empty($first_question['profile_field']) && !empty($profile[$first_question['profile_field']])) {
                $profile_value = $profile[$first_question['profile_field']];
            }
            
            $intro .= "\n\n" . $first_question['question'];
            
            if ($profile_value) {
                $intro .= "\n\n" . sprintf(__('(Based on your profile: **%s** - click to confirm or choose differently)', 'fra-member-tools'), $profile_value);
            }
            
            return array(
                'message' => $intro,
                'options' => $first_question['type'] === 'options' ? $first_question['options'] : null,
                'show_input' => $first_question['type'] === 'text',
                'placeholder' => $first_question['placeholder'] ?? '',
                'step' => 0,
            );
        }
        
        return array(
            'message' => $intro,
            'show_input' => true,
        );
    }
    
    /**
     * Process document chat answer
     */
    private function process_doc_chat_answer($document_type, $config, $message, $context, $profile) {
        $step = $context['step'] ?? 0;
        $answers = $context['answers'] ?? array();
        $questions = $config['questions'];
        
        // Store current answer
        if (isset($questions[$step])) {
            $answers[$questions[$step]['key']] = $message;
        }
        
        // Find next question (handle conditions)
        $next_step = $step + 1;
        while ($next_step < count($questions)) {
            $next_q = $questions[$next_step];
            
            // Check conditions
            if (!empty($next_q['condition'])) {
                $condition_met = false;
                foreach ($next_q['condition'] as $field => $valid_values) {
                    if (isset($answers[$field]) && in_array($answers[$field], $valid_values)) {
                        $condition_met = true;
                        break;
                    }
                }
                if (!$condition_met) {
                    $next_step++;
                    continue;
                }
            }
            break;
        }
        
        // Check if we have more questions
        if ($next_step < count($questions)) {
            $next_question = $questions[$next_step];
            
            // Check if this is the last question
            $remaining_questions = 0;
            for ($i = $next_step; $i < count($questions); $i++) {
                $q = $questions[$i];
                // Check if question will be shown (no condition or condition met)
                if (empty($q['condition'])) {
                    $remaining_questions++;
                } else {
                    foreach ($q['condition'] as $field => $valid_values) {
                        if (isset($answers[$field]) && in_array($answers[$field], $valid_values)) {
                            $remaining_questions++;
                            break;
                        }
                    }
                }
            }
            $is_last = ($remaining_questions <= 1);
            
            // Check profile for pre-fill
            $profile_hint = '';
            if (!empty($next_question['profile_field']) && !empty($profile[$next_question['profile_field']])) {
                $profile_hint = "\n\n" . sprintf(__('(From your profile: **%s**)', 'fra-member-tools'), $profile[$next_question['profile_field']]);
            }
            
            $message = $next_question['question'] . $profile_hint;
            if ($is_last) {
                $message .= "\n\n" . __('_(This is the last question - your document will be generated after you answer.)_', 'fra-member-tools');
            }
            
            return array(
                'message' => $message,
                'options' => $next_question['type'] === 'options' ? $next_question['options'] : null,
                'show_input' => $next_question['type'] === 'text',
                'placeholder' => $next_question['placeholder'] ?? '',
                'step' => $next_step,
                'collected' => $answers,
                'is_last_question' => $is_last,
            );
        }
        
        // All questions answered - generate document
        return $this->generate_document_from_chat($document_type, $answers, $profile);
    }
    
    /**
     * Generate document from chat answers
     */
    private function generate_document_from_chat($document_type, $answers, $profile) {
        $user = wp_get_current_user();
        $user_id = get_current_user_id();
        
        // Get the AI API key
        $api_key = get_option('fra_api_key');
        
        if (!$api_key) {
            // Fall back to template-based generation
            return $this->generate_template_document($document_type, $answers, $profile);
        }
        
        // Use AI to generate the document
        try {
            $document_content = $this->generate_ai_document($document_type, $answers, $profile, $api_key);
            
            // Save the document
            $doc_id = $this->save_generated_document($user_id, $document_type, $document_content, $answers);
            
            return array(
                'message' => __('✅ Your document is ready! I\'ve personalized it based on your answers.', 'fra-member-tools'),
                'generating' => true,
                'document_ready' => true,
                'document_id' => $doc_id,
                'document_title' => $document_content['title'],
            );
        } catch (Exception $e) {
            return array(
                'message' => __('I encountered an error generating your document. Please try again or contact support.', 'fra-member-tools'),
                'show_input' => false,
            );
        }
    }
    
    /**
     * Generate AI-powered document
     */
    private function generate_ai_document($document_type, $answers, $profile, $api_key) {
        $user = wp_get_current_user();
        
        // Build context for AI - handle empty middle name
        $name_parts = array_filter(array(
            $profile['legal_first_name'] ?? '',
            $profile['legal_middle_name'] ?? '',
            $profile['legal_last_name'] ?? '',
        ));
        $full_name = trim(implode(' ', $name_parts));
        
        if (empty($full_name)) {
            $full_name = $user->display_name;
        }
        
        $document_templates = array(
            'cover-letter' => $this->get_cover_letter_prompt($answers, $profile, $full_name),
            'financial-statement' => $this->get_financial_statement_prompt($answers, $profile, $full_name),
            'attestation' => $this->get_attestation_prompt($answers, $profile, $full_name),
            'accommodation-letter' => $this->get_accommodation_letter_prompt($answers, $profile, $full_name),
        );
        
        $prompt = $document_templates[$document_type] ?? '';
        
        if (empty($prompt)) {
            throw new Exception('Unknown document type');
        }
        
        // Call Anthropic API
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => json_encode(array(
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 4000,
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt),
                ),
            )),
        ));
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['content'][0]['text'])) {
            throw new Exception('Invalid API response');
        }
        
        $content = $body['content'][0]['text'];
        
        $titles = array(
            'cover-letter' => __('Visa Cover Letter', 'fra-member-tools'),
            'financial-statement' => __('Financial Resources Statement', 'fra-member-tools'),
            'attestation' => __('Attestation on Honor', 'fra-member-tools'),
            'accommodation-letter' => __('Accommodation Letter', 'fra-member-tools'),
        );
        
        return array(
            'title' => $full_name . ' - ' . $titles[$document_type],
            'content' => $content,
            'type' => $document_type,
            'generated_at' => current_time('mysql'),
        );
    }
    
    /**
     * Get cover letter prompt
     */
    private function get_cover_letter_prompt($answers, $profile, $full_name) {
        $consulate_addresses = array(
            'washington' => "Consulate General of France\nVisa Section\nWashington, D.C.",
            'newyork' => "Consulate General of France\nVisa Section\nNew York, NY",
            'losangeles' => "Consulate General of France\nVisa Section\nLos Angeles, CA",
            'chicago' => "Consulate General of France\nVisa Section\nChicago, IL",
            'houston' => "Consulate General of France\nVisa Section\nHouston, TX",
            'miami' => "Consulate General of France\nVisa Section\nMiami, FL",
            'atlanta' => "Consulate General of France\nVisa Section\nAtlanta, GA",
            'sanfrancisco' => "Consulate General of France\nVisa Section\nSan Francisco, CA",
            'boston' => "Consulate General of France\nVisa Section\nBoston, MA",
        );
        
        $visa_types = array(
            'visitor' => 'Long-Stay Visitor Visa (VLS-TS Visiteur)',
            'talent' => 'Talent Passport Visa',
            'student' => 'Student Visa (VLS-TS Étudiant)',
            'family' => 'Family Reunification Visa',
            'retirement' => 'Retirement Visa',
        );
        
        $consulate = $consulate_addresses[$answers['consulate'] ?? 'washington'] ?? $consulate_addresses['washington'];
        $visa_type = $visa_types[$answers['visa_type'] ?? 'visitor'] ?? $visa_types['visitor'];
        
        $prompt = "Generate a professional visa cover letter for a French long-stay visa application. 

APPLICANT INFORMATION:
- Full Legal Name: {$full_name}
- Visa Type: {$visa_type}
- Consulate: {$consulate}
- Accommodation: " . ($answers['accommodation'] ?? 'Not specified') . "
- Property Details: " . ($answers['property_details'] ?? 'Not specified') . "
- Reason for Moving: " . ($answers['move_reason'] ?? 'Quality of life and cultural immersion') . "
- Applying with: " . ($answers['applicants'] ?? 'solo') . "

PROFILE DATA (if available):
- Employment Status: " . ($profile['employment_status'] ?? 'Not specified') . "
- Target Location in France: " . ($profile['target_location'] ?? 'Not specified') . "

FORMAT REQUIREMENTS:
1. Start with Date: (leave blank for user to fill)
2. Consulate address
3. Subject line with visa type
4. Professional greeting
5. Organized sections with bold underlined headers:
   - Introduction paragraph
   - Property/Accommodation section (if applicable)
   - Personal Situation section
   - Financial Resources section (mention they have documentation attached)
   - Health Insurance section (mention coverage meets requirements)
   - Conclusion
6. Formal closing with signature line

STYLE:
- Professional but warm tone
- Concise paragraphs
- Demonstrate genuine intent to integrate into French community
- Reference attached documentation where appropriate
- Use proper French terminology (e.g., VLS-TS Visiteur, compromis de vente, attestation sur l'honneur)

Generate the complete letter ready for the applicant to review and sign.";
        
        return $prompt;
    }
    
    /**
     * Get financial statement prompt
     */
    private function get_financial_statement_prompt($answers, $profile, $full_name) {
        $consulate_addresses = array(
            'washington' => "Consulate General of France\nVisa Section\nWashington, D.C.",
            'newyork' => "Consulate General of France\nVisa Section\nNew York, NY",
            'losangeles' => "Consulate General of France\nVisa Section\nLos Angeles, CA",
            'chicago' => "Consulate General of France\nVisa Section\nChicago, IL",
            'houston' => "Consulate General of France\nVisa Section\nHouston, TX",
            'miami' => "Consulate General of France\nVisa Section\nMiami, FL",
            'atlanta' => "Consulate General of France\nVisa Section\nAtlanta, GA",
            'sanfrancisco' => "Consulate General of France\nVisa Section\nSan Francisco, CA",
            'boston' => "Consulate General of France\nVisa Section\nBoston, MA",
        );
        
        $consulate = $consulate_addresses[$answers['consulate'] ?? 'washington'] ?? $consulate_addresses['washington'];
        $is_couple = ($answers['applicants'] ?? 'solo') !== 'solo';
        $employment_status = $answers['employment_status'] ?? 'not_working';
        
        return "Generate a Statement of Financial Resources (Proof of Sufficient Means) for a French long-stay visitor visa application.

APPLICANT INFORMATION:
- Full Legal Name: {$full_name}
- Consulate: {$consulate}
- Applying as: " . ($answers['applicants'] ?? 'solo') . "
- Employment Status: {$employment_status}
- Employment Details: " . ($answers['employment_details'] ?? 'N/A') . "
- Financial Summary: " . ($answers['financial_summary'] ?? 'Not specified') . "
- Documents to Attach: " . ($answers['documents_attached'] ?? 'Not specified') . "

INSTRUCTIONS:
Parse the financial summary provided and organize it into appropriate categories. The applicant has provided their financial information in a single response - extract and organize this into the proper sections.

FORMAT REQUIREMENTS - Create a formal letter with:
1. Date line (leave blank: Date: / / )
2. Consulate address
3. Subject: Statement of Financial Resources – Proof of Sufficient Means
4. Opening: 'Dear Visa Officer,' followed by intro paragraph

STRUCTURE WITH NUMBERED SECTIONS (only include sections that apply based on provided info):

**<u>1. EMPLOYMENT INCOME</u>** (if employed/self-employed)
- Description of employment from the details provided
- Create a table showing: Compensation Component | Annual Amount (USD)
- Note: 'Supporting Documentation Attached'

**<u>2. RETIREMENT & INVESTMENT ACCOUNTS</u>** (if any mentioned in financial summary)
- Table showing: Account Type | Account Holder | Balance (USD)
- Include 401(k), IRA, pension, stocks, bonds, etc.

**<u>3. LIQUID ASSETS – BANK ACCOUNTS</u>** (if any mentioned)
- Table showing: Account Type | Institution | Balance (USD)
- Include savings, checking, money market accounts

**<u>4. MULTI-CURRENCY ACCOUNTS</u>** (only if mentioned - Wise, Revolut, etc.)

**<u>5. OTHER ASSETS</u>** (if any other assets mentioned)

**<u>SUMMARY OF TOTAL ASSETS</u>**
- Table summarizing all categories with totals
- Grand total line

CLOSING:
- Statement that resources substantially exceed minimum requirements (~€1,500/month or ~€18,000/year)
- 'Respectfully submitted,'
- Signature line(s)

STYLE:
- Professional, formal tone
- Use tables for financial data (format as: Column | Column | Column)
- If couple, use 'we' and 'our combined household'
- Organize whatever information was provided into logical sections
- If specific institution names weren't provided, use generic 'Bank Account' or 'Savings Account'";
    }
    
    /**
     * Get attestation prompt
     */
    private function get_attestation_prompt($answers, $profile, $full_name) {
        $visa_types = array(
            'visitor' => 'Long-Stay Visitor Visa (VLS-TS Visiteur)',
            'retirement' => 'Retirement Visa',
            'other' => 'Long-Stay Visa',
        );
        $visa_type = $visa_types[$answers['visa_type'] ?? 'visitor'] ?? $visa_types['visitor'];
        
        return "Generate an Attestation on Honor (Attestation sur l'Honneur) for a French visa application. The document should be in ENGLISH ONLY - do not include French translations.

APPLICANT INFORMATION:
- Full Legal Name: {$full_name}
- Date of Birth: " . ($answers['birth_date'] ?? '[Date of Birth]') . "
- Place of Birth: " . ($answers['birth_place'] ?? '[Place of Birth]') . "
- Current Address: " . ($answers['current_address'] ?? '[Current Address]') . "
- Visa Type: {$visa_type}

FORMAT REQUIREMENTS:

TITLE (centered, bold):
ATTESTATION ON HONOR
(Attestation sur l'Honneur)

SUBTITLE (centered):
Undertaking Not to Engage in Professional Activity in France

BODY:

Opening paragraph:
\"I, the undersigned, [Full Name], born on [Date] in [Place], currently residing at [Address], hereby solemnly declare and attest on my honor that:\"

NUMBERED DECLARATIONS:
1. I am applying for a {$visa_type} to reside in France;

2. I undertake not to exercise any professional activity, whether salaried or self-employed, on French territory during the validity of my visa;

3. I understand that the Long-Stay Visitor Visa does not authorize me to work in France;

4. I am aware that any violation of this undertaking may result in the cancellation of my visa and/or refusal of renewal;

5. I have sufficient financial resources to support myself during my stay in France without needing to work.

CLOSING STATEMENT:
\"I make this solemn declaration conscientiously believing it to be true and knowing that it has the same force and effect as if made under oath.\"

SIGNATURE SECTION:
Done at: ________________________________
Date: ________________________________
Signature: ________________________________
[Full Name]

STYLE:
- Clean, formal layout
- Single-language (English only)
- Professional legal document tone
- The French title in parentheses is acceptable for reference, but the body must be entirely in English";
    }
    
    /**
     * Get accommodation letter prompt
     */
    private function get_accommodation_letter_prompt($answers, $profile, $full_name) {
        $consulate_addresses = array(
            'washington' => "Consulate General of France\nVisa Section\nWashington, D.C.",
            'newyork' => "Consulate General of France\nVisa Section\nNew York, NY",
            'losangeles' => "Consulate General of France\nVisa Section\nLos Angeles, CA",
            'chicago' => "Consulate General of France\nVisa Section\nChicago, IL",
            'houston' => "Consulate General of France\nVisa Section\nHouston, TX",
            'miami' => "Consulate General of France\nVisa Section\nMiami, FL",
            'atlanta' => "Consulate General of France\nVisa Section\nAtlanta, GA",
            'sanfrancisco' => "Consulate General of France\nVisa Section\nSan Francisco, CA",
            'boston' => "Consulate General of France\nVisa Section\nBoston, MA",
        );
        
        $consulate = $consulate_addresses[$answers['consulate'] ?? 'washington'] ?? $consulate_addresses['washington'];
        $accommodation_type = $answers['accommodation_type'] ?? 'purchase_pending';
        $is_purchase = in_array($accommodation_type, array('purchase_complete', 'purchase_pending'));
        
        return "Generate a Letter of Explanation – Proof of Accommodation for a French long-stay visitor visa application.

APPLICANT INFORMATION:
- Full Legal Name: {$full_name}
- Consulate: {$consulate}
- Accommodation Type: {$accommodation_type}
- Property Address: " . ($answers['property_address'] ?? 'Not specified') . "
- Property Type: " . ($answers['property_type'] ?? 'Residential dwelling') . "
- Purchase Price: " . ($answers['purchase_price'] ?? 'Not specified') . "
- Deposit Paid: " . ($answers['deposit_paid'] ?? 'Not specified') . "
- Expected Closing: " . ($answers['expected_closing'] ?? 'Not specified') . "
- Residents: " . ($answers['applicants'] ?? 'solo') . "
- Documents to Attach: " . ($answers['documents_attached'] ?? 'Not specified') . "

FORMAT REQUIREMENTS:
1. Date line (leave blank: Date: / / )
2. Consulate address
3. Subject: Letter of Explanation – Proof of Accommodation
4. Professional greeting: \"Dear Visa Officer,\"

CONTENT SECTIONS (use bold underlined headers):

OPENING PARAGRAPH:
- Explain you are submitting this letter to explain your proof of accommodation
- If property purchase in progress, explain why you're providing a compromis de vente rather than final deed
- Emphasize that this documentation is more binding than a hotel reservation or rental

**<u>Property Under Contract</u>** (or \"Property Details\" if purchase complete):
- Address: [full address]
- Property Type: [type]
- Purchase Price: [amount]
- Cadastral References: (if known, or omit)

**<u>Current Status of Purchase</u>** (for pending purchases):
List as numbered items with dates:
1. Signed Offer to Purchase (Offre d'Achat): [date]
2. Signed Preliminary Sales Agreement (Compromis de Vente): [date]
3. 10-Day Withdrawal Period (Délai de Rétractation): Expired – legally bound
4. Deposit Paid (Séquestre): [amount] held by notaire
5. Mortgage Commitment: [status]
6. Power of Attorney: [if applicable]
7. Expected Closing (Acte de Vente): [date range]

**<u>Why This Documentation Represents Secure Accommodation</u>**:
Explain with bullet points:
• Financial commitment at risk: deposit would be forfeited
• Legal obligation: compromis de vente is binding under French law
• Bank commitment confirmed (if applicable)
• Closing is imminent

**<u>Attached Supporting Documents</u>**:
Numbered list of all documents being submitted

**<u>Our Intent</u>** (or \"My Intent\" for solo):
- Statement about purchasing as primary residence
- Who will reside full-time vs. part-time if applicable

CLOSING:
- Request that documentation be accepted as proof of accommodation
- Professional sign-off: \"Respectfully submitted,\"
- Signature line(s) for all applicants

STYLE:
- Professional, persuasive tone
- Use proper French legal terms (compromis de vente, notaire, Acte de Vente, etc.)
- Emphasize the binding nature of the commitment
- If couple, use \"we\" and \"our\" throughout";
    }
    
    /**
     * Save generated document
     */
    private function save_generated_document($user_id, $document_type, $document_content, $answers) {
        $doc_id = 'gendoc_' . $user_id . '_' . time();
        
        set_transient($doc_id, array(
            'content' => $document_content,
            'answers' => $answers,
            'type' => $document_type,
        ), DAY_IN_SECONDS);
        
        return $doc_id;
    }
    
    /**
     * Generate template-based document (fallback)
     */
    private function generate_template_document($document_type, $answers, $profile) {
        // Simple template fallback when AI is not available
        $user = wp_get_current_user();
        $full_name = $user->display_name;
        
        $content = "Document generated based on your answers.\n\nPlease note: AI generation is not configured. This is a basic template.";
        
        $doc_id = 'gendoc_' . $user->ID . '_' . time();
        
        set_transient($doc_id, array(
            'content' => array('title' => $full_name . ' - Document', 'content' => $content),
            'answers' => $answers,
            'type' => $document_type,
        ), DAY_IN_SECONDS);
        
        return array(
            'message' => __('Your document has been created using a basic template. For AI-powered personalized documents, please configure the API key in settings.', 'fra-member-tools'),
            'document_ready' => true,
            'document_id' => $doc_id,
            'document_title' => $full_name . ' - Document',
        );
    }
    
    /**
     * AJAX: Download generated document
     */
    public function ajax_download_generated_document() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Login required', 'fra-member-tools')));
            return;
        }
        
        $doc_id = sanitize_key($_POST['document_id'] ?? '');
        $format = sanitize_key($_POST['format'] ?? 'word');
        
        $doc_data = get_transient($doc_id);
        
        if (!$doc_data) {
            wp_send_json_error(array('message' => __('Document not found or expired', 'fra-member-tools')));
            return;
        }
        
        $user_id = get_current_user_id();
        $content = $doc_data['content'];
        
        // Create file
        $upload_dir = wp_upload_dir();
        $user_doc_dir = $upload_dir['basedir'] . '/framt-documents/user-' . $user_id;
        
        if (!file_exists($user_doc_dir)) {
            wp_mkdir_p($user_doc_dir);
        }
        
        $filename = sanitize_file_name($content['title'] . '-' . date('Y-m-d'));
        
        // Generate HTML content
        $html = $this->generate_document_html($content);
        
        if ($format === 'word') {
            $file_path = $user_doc_dir . '/' . $filename . '.doc';
            file_put_contents($file_path, $html);
            $url = $upload_dir['baseurl'] . '/framt-documents/user-' . $user_id . '/' . $filename . '.doc';
            $file_ext = 'doc';
        } else {
            // PDF - add print styling
            $html = str_replace('</head>', '<script>window.onload=function(){window.print();}</script></head>', $html);
            $file_path = $user_doc_dir . '/' . $filename . '.html';
            file_put_contents($file_path, $html);
            $url = $upload_dir['baseurl'] . '/framt-documents/user-' . $user_id . '/' . $filename . '.html';
            $file_ext = 'html';
        }
        
        // Check if already saved (prevent duplicate saves)
        $saved_key = 'framt_saved_' . md5($doc_id . '_' . $user_id);
        if (!get_transient($saved_key)) {
            // Calculate expiration (60 days from now)
            $expires_at = date('Y-m-d H:i:s', strtotime('+60 days'));
            
            // Save document record to "My Documents"
            $documents_handler = FRAMT_Documents::get_instance();
            $saved_doc_id = $documents_handler->save_document(array(
                'type' => $doc_data['type'] ?? 'cover-letter',
                'title' => $content['title'],
                'content' => array(
                    'file_path' => $file_path,
                    'file_url' => $url,
                    'file_ext' => $file_ext,
                    'generated_at' => current_time('mysql'),
                ),
                'meta' => array(
                    'answers' => $doc_data['answers'] ?? array(),
                    'format' => $format,
                    'expires_at' => $expires_at,
                    'source_id' => $doc_id,
                ),
            ), $user_id);
            
            // Mark as saved for 24 hours to prevent duplicates
            set_transient($saved_key, $saved_doc_id, DAY_IN_SECONDS);
            
            // Schedule cleanup for 60 days
            if ($saved_doc_id) {
                wp_schedule_single_event(strtotime('+60 days'), 'framt_cleanup_generated_document', array($saved_doc_id, $file_path, $user_id));
            }
        }
        
        wp_send_json_success(array('url' => $url));
    }
    
    /**
     * Generate document HTML
     */
    private function generate_document_html($content) {
        $title = esc_html($content['title']);
        $body = $content['content'];
        
        // First, escape HTML but preserve our formatting markers
        // Replace underline markers temporarily
        $body = preg_replace('/<u>([^<]+)<\/u>/', '[[UNDERLINE_START]]$1[[UNDERLINE_END]]', $body);
        
        // Escape HTML
        $body = esc_html($body);
        
        // Restore underline
        $body = str_replace('[[UNDERLINE_START]]', '<u>', $body);
        $body = str_replace('[[UNDERLINE_END]]', '</u>', $body);
        
        // Convert markdown-style bold **text** BEFORE nl2br
        $body = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $body);
        
        // Convert underlined bold headers to proper section headers BEFORE nl2br
        // This prevents extra <br> tags around headers
        $body = preg_replace('/<strong><u>([^<]+)<\/u><\/strong>/', '[[H3_START]]$1[[H3_END]]', $body);
        
        // Remove extra newlines around headers
        $body = preg_replace('/\n*\[\[H3_START\]\]/', "\n\n[[H3_START]]", $body);
        $body = preg_replace('/\[\[H3_END\]\]\n*/', "[[H3_END]]\n", $body);
        
        // Convert newlines to <br>
        $body = nl2br($body);
        
        // Now restore headers (which will have their own spacing via CSS)
        $body = str_replace('[[H3_START]]', '<h3>', $body);
        $body = str_replace('[[H3_END]]', '</h3>', $body);
        
        // Remove <br> immediately after </h3> or before <h3>
        $body = preg_replace('/<\/h3>\s*<br\s*\/?>\s*<br\s*\/?>/', '</h3>', $body);
        $body = preg_replace('/<br\s*\/?>\s*<br\s*\/?>\s*<h3>/', '<h3>', $body);
        $body = preg_replace('/<\/h3>\s*<br\s*\/?>/', '</h3>', $body);
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . $title . '</title>
    <style>
        body { 
            font-family: "Times New Roman", Times, serif; 
            font-size: 11pt; 
            line-height: 1.6; 
            max-width: 8.5in;
            margin: 1in auto;
            padding: 0 0.5in;
            color: #000;
        }
        h3 { 
            margin-top: 20px; 
            margin-bottom: 8px;
            font-size: 11pt; 
            font-weight: bold;
            text-decoration: underline;
        }
        p { margin-bottom: 12px; }
        strong { font-weight: bold; }
        u { text-decoration: underline; }
        @media print {
            body { margin: 0; padding: 0.75in; }
        }
    </style>
</head>
<body>
' . $body . '
</body>
</html>';
    }

    /**
     * Save user document record
     */
    private function save_user_document($user_id, $data) {
        $documents = get_user_meta($user_id, 'framt_documents', true);
        if (!is_array($documents)) {
            $documents = array();
        }
        
        $doc_id = 'doc_' . time() . '_' . wp_rand(1000, 9999);
        $data['id'] = $doc_id;
        $documents[$doc_id] = $data;
        
        update_user_meta($user_id, 'framt_documents', $documents);
        
        return $doc_id;
    }
    
    /**
     * Cleanup a specific user document
     */
    public function cleanup_user_document($file_path, $user_id) {
        // Delete the file
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Remove from user meta
        $documents = get_user_meta($user_id, 'framt_documents', true);
        if (is_array($documents)) {
            foreach ($documents as $doc_id => $doc) {
                if (isset($doc['file_path']) && $doc['file_path'] === $file_path) {
                    unset($documents[$doc_id]);
                    break;
                }
            }
            update_user_meta($user_id, 'framt_documents', $documents);
        }
    }
    
    /**
     * Cleanup a generated document (called by scheduled event)
     */
    public function cleanup_generated_document($doc_id, $file_path, $user_id) {
        // Delete the file
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from database
        $documents_handler = FRAMT_Documents::get_instance();
        $documents_handler->delete_document($doc_id, $user_id);
    }
    
    /**
     * Daily cleanup of expired documents (called by cron)
     */
    public function cleanup_expired_documents() {
        global $wpdb;
        
        // Get all users with documents
        $users_with_docs = $wpdb->get_col(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'framt_documents'"
        );
        
        $now = current_time('mysql');
        
        foreach ($users_with_docs as $user_id) {
            $documents = get_user_meta($user_id, 'framt_documents', true);
            if (!is_array($documents)) continue;
            
            $updated = false;
            foreach ($documents as $doc_id => $doc) {
                if (isset($doc['expires_at']) && $doc['expires_at'] < $now) {
                    // Delete file
                    if (isset($doc['file_path']) && file_exists($doc['file_path'])) {
                        unlink($doc['file_path']);
                    }
                    unset($documents[$doc_id]);
                    $updated = true;
                }
            }
            
            if ($updated) {
                update_user_meta($user_id, 'framt_documents', $documents);
            }
        }
    }
    
    /**
     * AJAX: Verify health insurance document using AI
     */
    public function ajax_verify_health_insurance() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Login required', 'fra-member-tools')));
            return;
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'fra-member-tools')));
            return;
        }
        
        $user_id = get_current_user_id();
        $file = $_FILES['file'];
        
        // Validate file
        $allowed_types = array('application/pdf', 'image/jpeg', 'image/png', 'image/jpg');
        $file_type = wp_check_filetype($file['name']);
        $mime_type = $file['type'];
        
        // More accurate MIME type detection
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
        
        if (!in_array($mime_type, $allowed_types)) {
            wp_send_json_error(array('message' => __('Invalid file type. Please upload a PDF, JPG, or PNG.', 'fra-member-tools')));
            return;
        }
        
        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('File too large. Maximum size is 10MB.', 'fra-member-tools')));
            return;
        }
        
        // Get AI verification instance
        $ai_verification = FRAMT_AI_Verification::get_instance();
        
        // Check if API is configured
        if (!$ai_verification->is_configured()) {
            wp_send_json_error(array(
                'message' => __('AI verification requires the Anthropic API to be enabled in the main France Relocation Assistant plugin settings.', 'fra-member-tools'),
                'error' => 'not_configured',
            ));
            return;
        }
        
        // Get user context for better analysis
        $profile = FRAMT_Profile::get_instance()->get_profile($user_id);
        $user_context = array(
            'visa_type' => isset($profile['visa_type']) ? $profile['visa_type'] : 'long-stay visa',
            'planned_duration' => isset($profile['stay_duration']) ? $profile['stay_duration'] : 'one year or more',
        );
        
        // Run AI verification
        $result = $ai_verification->verify_health_insurance(
            $file['tmp_name'],
            $mime_type,
            $user_context
        );
        
        if (!$result['success']) {
            wp_send_json_error(array(
                'message' => isset($result['message']) ? $result['message'] : __('Verification failed. Please try again.', 'fra-member-tools'),
                'error' => isset($result['error']) ? $result['error'] : 'unknown',
            ));
            return;
        }
        
        // Store verification result
        $verification = array(
            'status' => $result['status'],
            'uploaded_at' => current_time('mysql'),
            'filename' => sanitize_file_name($file['name']),
            'findings' => $result['findings_html'],
            'checklist' => $result['checklist'],
            'raw_response' => $result['raw_response'],
        );
        
        update_user_meta($user_id, 'framt_health_insurance_verification', $verification);
        
        // If verified, mark the health-insurance checklist item as complete
        if ($result['status'] === 'verified' && isset($this->components['checklists'])) {
            $this->components['checklists']->update_item($user_id, 'visa-application', 'health-insurance', 'complete');
        }
        
        // Build HTML for chat interface
        $html = $this->build_health_verification_chat_html($verification);
        
        wp_send_json_success(array(
            'message' => __('Verification complete', 'fra-member-tools'),
            'status' => $result['status'],
            'html' => $html,
            'findings' => $result['findings_html'],
            'raw' => $result['raw_response'],
        ));
    }
    
    /**
     * Build HTML for health verification chat display
     */
    private function build_health_verification_chat_html($verification) {
        $status = isset($verification['status']) ? $verification['status'] : 'unknown';
        $checklist = isset($verification['checklist']) ? $verification['checklist'] : array();
        
        ob_start();
        
        // Status badge with message
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
        
        // Checklist - compact labels
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
        
        // Single short disclaimer
        echo '<p class="framt-health-disclaimer-text"><em>' . esc_html__('This is AI guidance only. The French consulate makes the final determination.', 'fra-member-tools') . '</em></p>';
        
        // Clear & Start Over button
        echo '<div class="framt-health-actions">';
        echo '<button class="framt-btn framt-btn-secondary framt-btn-small" data-action="clear-verification">';
        echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg> ';
        echo esc_html__('Clear & Start Over', 'fra-member-tools');
        echo '</button>';
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * AJAX: Clear health insurance verification
     */
    public function ajax_clear_health_insurance_verification() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Login required', 'fra-member-tools')));
            return;
        }
        
        $user_id = get_current_user_id();
        delete_user_meta($user_id, 'framt_health_insurance_verification');
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Clear health verification (new endpoint)
     */
    public function ajax_clear_health_verification() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Login required', 'fra-member-tools')));
            return;
        }
        
        $user_id = get_current_user_id();
        delete_user_meta($user_id, 'framt_health_insurance_verification');
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Health insurance follow-up question
     */
    public function ajax_health_followup() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Login required', 'fra-member-tools')));
            return;
        }
        
        $question = isset($_POST['question']) ? sanitize_text_field($_POST['question']) : '';
        $context_raw = isset($_POST['context']) ? stripslashes($_POST['context']) : '{}';
        $context = json_decode($context_raw, true);
        if (!is_array($context)) {
            $context = array();
        }
        
        if (empty($question)) {
            wp_send_json_error(array('message' => __('Please enter a question', 'fra-member-tools')));
            return;
        }
        
        // Get API key
        $api_key = get_option('fra_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('AI not configured', 'fra-member-tools')));
            return;
        }
        
        // Build context from previous verification
        $verification_context = '';
        if (!empty($context['status'])) {
            $verification_context = "Previous verification status: " . $context['status'] . "\n";
        }
        if (!empty($context['raw'])) {
            $verification_context .= "Previous analysis:\n" . $context['raw'] . "\n";
        }
        
        // Build prompt
        $prompt = "You are a helpful assistant specializing in French visa requirements and health insurance. 

A user has uploaded their health insurance certificate and received an AI analysis. They now have a follow-up question.

" . $verification_context . "

User's question: " . $question . "

Please provide a helpful, accurate answer about their health insurance coverage or French visa health insurance requirements. Be concise but thorough. If you're not sure about something specific to their policy, say so and provide general guidance.";

        // Call Claude API
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => wp_json_encode(array(
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 1024,
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt)
                ),
            )),
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['content'][0]['text'])) {
            wp_send_json_success(array('answer' => $data['content'][0]['text']));
        } else {
            wp_send_json_error(array('message' => __('Could not get response', 'fra-member-tools')));
        }
    }
    
    /**
     * AJAX: Toggle interactive checklists
     */
    public function ajax_toggle_interactive() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Login required', 'fra-member-tools')));
            return;
        }
        
        $user_id = get_current_user_id();
        $enabled = !empty($_POST['enabled']);
        
        update_user_meta($user_id, 'framt_interactive_checklists', $enabled);
        
        wp_send_json_success();
    }
    
    /**
     * AJAX handler for loading section content
     */
    public function ajax_load_section() {
        check_ajax_referer('framt_nonce', 'nonce');
        
        // Allow admins or members
        if (!current_user_can('manage_options') && !$this->components['membership']->is_member()) {
            wp_send_json_error(array('message' => 'Access denied'));
            return;
        }
        
        $section = sanitize_key($_POST['section'] ?? '');
        $html = '';
        
        switch ($section) {
            case 'dashboard':
                $html = $this->components['dashboard']->render();
                break;
            case 'my-checklists':
                $html = $this->components['checklists']->render();
                break;
            case 'create-documents':
                $html = $this->components['documents']->render();
                break;
            case 'upload-verify':
                $html = $this->components['documents']->render_upload();
                break;
            case 'glossary':
                $html = $this->components['glossary']->render();
                break;
            case 'guides':
                $html = $this->components['guides']->render();
                break;
            case 'profile':
                $html = $this->components['profile']->render();
                break;
            case 'my-documents':
                $html = $this->components['documents']->render_my_documents();
                break;
            case 'messages':
                $html = $this->components['messages']->render_frontend_section();
                break;
            default:
                $html = '<div class="fra-error">Unknown section: ' . esc_html($section) . '</div>';
        }
        
        wp_send_json_success(array('html' => $html));
    }

    /**
     * Integrate with main France Relocation Assistant plugin
     *
     * @return void
     */
    private function integrate_with_main_plugin() {
        // Add member navigation items
        add_filter('fra_navigation_items', array($this, 'add_member_navigation'));

        // Add member content areas
        add_filter('fra_content_sections', array($this, 'add_member_sections'));

        // Modify AI capabilities for members
        add_filter('fra_ai_capabilities', array($this, 'extend_ai_capabilities'), 10, 2);

        // Add member-specific system prompts
        add_filter('fra_system_prompt', array($this, 'add_member_context'), 10, 2);
    }

    /**
     * Add member navigation items to main plugin
     *
     * @param array $items Existing navigation items
     * @return array Modified navigation items
     */
    public function add_member_navigation($items) {
        $is_member = $this->components['membership']->is_member();
        
        // Get order and settings from main plugin options
        $mt_order = get_option('fra_member_tools_order', array('dashboard', 'my-checklists', 'create-documents', 'upload-verify', 'glossary', 'guides'));
        $mt_settings = get_option('fra_member_tools_settings', array());
        $teaser_message = get_option('fra_mt_teaser_message', __('Unlock personalized documents, checklists, and guides', 'fra-member-tools'));

        // Default items with their properties
        $default_items = array(
            'dashboard' => array(
                'label' => __('Dashboard', 'fra-member-tools'),
                'icon' => '📊',
                'priority' => 5,
            ),
            'my-checklists' => array(
                'label' => __('My Checklists', 'fra-member-tools'),
                'icon' => '📋',
                'priority' => 15,
            ),
            'create-documents' => array(
                'label' => __('Create Documents', 'fra-member-tools'),
                'icon' => '📄',
                'priority' => 20,
                'children' => $this->get_document_types(),
            ),
            'upload-verify' => array(
                'label' => __('Upload & Verify', 'fra-member-tools'),
                'icon' => '📎',
                'priority' => 25,
            ),
            'glossary' => array(
                'label' => __('Glossary', 'fra-member-tools'),
                'icon' => '📚',
                'priority' => 30,
            ),
            'guides' => array(
                'label' => __('Guides', 'fra-member-tools'),
                'icon' => '📖',
                'priority' => 35,
                'children' => $this->get_guide_types(),
            ),
        );

        // Build member items in saved order
        $member_items = array();
        $priority = 5;
        
        foreach ($mt_order as $key) {
            if (!isset($default_items[$key])) continue;
            
            // Check if item is enabled (default to true if not set)
            $settings = isset($mt_settings[$key]) ? $mt_settings[$key] : array();
            $enabled = isset($settings['enabled']) ? $settings['enabled'] : true;
            
            if (!$enabled) continue;
            
            // Build item with custom label/icon if set
            $item = $default_items[$key];
            $item['label'] = !empty($settings['label']) ? $settings['label'] : $item['label'];
            $item['icon'] = !empty($settings['icon']) ? $settings['icon'] : $item['icon'];
            $item['priority'] = $priority;
            $item['locked'] = !$is_member;
            
            $member_items[$key] = $item;
            $priority += 5;
        }

        // Add metadata for the section
        $membership_settings = get_option('fra_membership', array());
        $upgrade_url = !empty($membership_settings['upgrade_url']) ? $membership_settings['upgrade_url'] : '/membership/';
        
        $items['_member_tools_meta'] = array(
            'is_member' => $is_member,
            'upgrade_url' => $upgrade_url,
            'teaser_message' => $teaser_message,
        );

        return array_merge($member_items, $items);
    }

    /**
     * Get available document types
     *
     * @return array Document types for navigation
     */
    private function get_document_types() {
        return array(
            'cover-letter' => __('Cover Letter', 'fra-member-tools'),
            'financial-statement' => __('Proof of Sufficient Means', 'fra-member-tools'),
            'no-work-attestation' => __('No Work Attestation', 'fra-member-tools'),
            'accommodation-letter' => __('Proof of Accommodation', 'fra-member-tools'),
        );
    }

    /**
     * Get available guide types
     *
     * @return array Guide types for navigation
     */
    private function get_guide_types() {
        return array(
            'apostille-guide' => __('Apostille Guide', 'fra-member-tools'),
            'pet-relocation' => __('Pet Relocation', 'fra-member-tools'),
            'french-mortgages' => __('French Mortgages', 'fra-member-tools'),
            'bank-ratings' => __('Bank Ratings', 'fra-member-tools'),
        );
    }

    /**
     * Add member content sections
     *
     * @param array $sections Existing content sections
     * @return array Modified sections
     */
    public function add_member_sections($sections) {
        if (!$this->components['membership']->is_member()) {
            return $sections;
        }

        $member_sections = array(
            'dashboard' => array($this->components['dashboard'], 'render'),
            'my-checklists' => array($this->components['checklists'], 'render'),
            'create-documents' => array($this->components['documents'], 'render'),
            'upload-verify' => array($this->components['documents'], 'render_upload'),
            'glossary' => array($this->components['glossary'], 'render'),
            'guides' => array($this->components['guides'], 'render'),
        );

        return array_merge($member_sections, $sections);
    }

    /**
     * Extend AI capabilities for members
     *
     * @param array $capabilities Current capabilities
     * @param bool $is_member Whether user is a member
     * @return array Extended capabilities
     */
    public function extend_ai_capabilities($capabilities, $is_member) {
        if ($is_member) {
            $capabilities['document_creation'] = true;
            $capabilities['personalized_checklists'] = true;
            $capabilities['custom_timelines'] = true;
            $capabilities['unlimited_queries'] = true;
        }
        return $capabilities;
    }

    /**
     * Add member context to AI system prompt
     *
     * @param string $prompt Current system prompt
     * @param int $user_id User ID
     * @return string Modified prompt
     */
    public function add_member_context($prompt, $user_id) {
        if (!$this->components['membership']->is_member($user_id)) {
            return $prompt;
        }

        $profile = $this->components['profile']->get_profile($user_id);
        
        if (empty($profile)) {
            return $prompt;
        }

        $context = "\n\n**MEMBER PROFILE CONTEXT:**\n";
        $context .= $this->format_profile_for_ai($profile);
        
        return $prompt . $context;
    }

    /**
     * Format profile data for AI context
     *
     * @param array $profile User profile data
     * @return string Formatted profile context
     */
    private function format_profile_for_ai($profile) {
        $context = "";

        if (!empty($profile['applicants'])) {
            $context .= "- Applicants: {$profile['applicants']}\n";
        }
        if (!empty($profile['visa_type'])) {
            $context .= "- Visa Type: {$profile['visa_type']}\n";
        }
        if (!empty($profile['current_state'])) {
            $context .= "- Current State: {$profile['current_state']}\n";
        }
        if (!empty($profile['target_location'])) {
            $context .= "- Target Location in France: {$profile['target_location']}\n";
        }
        if (!empty($profile['timeline'])) {
            $context .= "- Move Timeline: {$profile['timeline']}\n";
        }
        if (!empty($profile['employment_status'])) {
            $context .= "- Employment Status: {$profile['employment_status']}\n";
        }
        if (!empty($profile['application_location'])) {
            $context .= "- Applying From: {$profile['application_location']}\n";
        }

        return $context;
    }

    /**
     * Enqueue frontend assets
     *
     * @return void
     */
    public function enqueue_frontend_assets() {
        // Always load for admins, otherwise check membership
        if (!current_user_can('manage_options') && !$this->components['membership']->is_member()) {
            return;
        }

        wp_enqueue_style(
            'framt-frontend',
            FRAMT_PLUGIN_URL . 'assets/css/frontend.css',
            array('fra-frontend-style'),
            FRAMT_VERSION
        );

        wp_enqueue_script(
            'framt-frontend',
            FRAMT_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery', 'fra-frontend-script'),
            FRAMT_VERSION,
            true
        );

        // Get user info for personalization
        $user = wp_get_current_user();
        $user_id = get_current_user_id();
        $profile = $this->components['profile'];
        $needs_onboarding = $profile->needs_onboarding($user_id);
        $user_name = $user->first_name ?: $user->display_name ?: '';
        
        // Get customizer colors for profile header
        $customizer = get_option('fra_customizer', array());
        $profile_colors = array(
            'headerBg' => $customizer['color_profile_header_bg'] ?? '#1a1a1a',
            'headerText' => $customizer['color_profile_header_text'] ?? '#fafaf8',
            'progressFill' => $customizer['color_profile_progress_fill'] ?? '#d4a853',
        );

        wp_localize_script('framt-frontend', 'framtData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('framt_nonce'),
            'userId' => $user_id,
            'userName' => $user_name,
            'isMember' => true,
            'needsOnboarding' => $needs_onboarding,
            'profileCompletion' => $profile->get_completion_percentage($user_id),
            'colors' => $profile_colors,
            'strings' => array(
                'loading' => __('Loading...', 'fra-member-tools'),
                'error' => __('An error occurred. Please try again.', 'fra-member-tools'),
                'saved' => __('Saved successfully!', 'fra-member-tools'),
                'confirmDelete' => __('Are you sure you want to delete this?', 'fra-member-tools'),
            ),
        ));
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets($hook) {
        // Only load on relevant admin pages
        if (strpos($hook, 'fra-member') === false) {
            return;
        }

        wp_enqueue_style(
            'framt-admin',
            FRAMT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FRAMT_VERSION
        );

        wp_enqueue_script(
            'framt-admin',
            FRAMT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            FRAMT_VERSION,
            true
        );
    }
    
    /**
     * Register admin menu
     *
     * @return void
     */
    public function register_admin_menu() {
        add_submenu_page(
            'france-relocation', // Parent slug (main plugin)
            __('Member Tools', 'fra-member-tools'),
            __('Member Tools', 'fra-member-tools'),
            'manage_options',
            'fra-member-tools',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register plugin settings (placeholder for future settings)
     *
     * @return void
     */
    public function register_settings() {
        // Member Tools uses the main plugin's API key (fra_api_key)
        // No separate settings needed for AI features
    }
    
    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $ai_verification = FRAMT_AI_Verification::get_instance();
        $api_configured = $ai_verification->is_configured();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('AI-Powered Features Status', 'fra-member-tools'); ?></h2>
                
                <?php if ($api_configured) : ?>
                    <p style="color: #46b450; font-size: 14px;">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <strong><?php esc_html_e('AI Features Active', 'fra-member-tools'); ?></strong>
                    </p>
                    <p><?php esc_html_e('The Member Tools plugin is using the API key configured in the main France Relocation Assistant plugin.', 'fra-member-tools'); ?></p>
                    
                    <h3><?php esc_html_e('Available AI Features:', 'fra-member-tools'); ?></h3>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><strong><?php esc_html_e('Health Insurance Verification', 'fra-member-tools'); ?></strong> - <?php esc_html_e('Members can upload their health insurance certificates and get AI-powered analysis to verify they meet French visa requirements.', 'fra-member-tools'); ?></li>
                    </ul>
                    
                <?php else : ?>
                    <p style="color: #dc3232; font-size: 14px;">
                        <span class="dashicons dashicons-warning"></span>
                        <strong><?php esc_html_e('AI Features Not Available', 'fra-member-tools'); ?></strong>
                    </p>
                    <p><?php esc_html_e('To enable AI-powered features like Health Insurance Verification, please configure the Anthropic API key in the main plugin settings.', 'fra-member-tools'); ?></p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=france-relocation-settings')); ?>" class="button button-primary">
                            <?php esc_html_e('Go to Main Plugin Settings', 'fra-member-tools'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Member Tools Overview', 'fra-member-tools'); ?></h2>
                <p><?php esc_html_e('This plugin provides premium features for your members:', 'fra-member-tools'); ?></p>
                
                <table class="widefat" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Feature', 'fra-member-tools'); ?></th>
                            <th><?php esc_html_e('Description', 'fra-member-tools'); ?></th>
                            <th><?php esc_html_e('Status', 'fra-member-tools'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('Member Dashboard', 'fra-member-tools'); ?></strong></td>
                            <td><?php esc_html_e('Personalized dashboard showing relocation progress', 'fra-member-tools'); ?></td>
                            <td><span style="color: #46b450;">✓ <?php esc_html_e('Active', 'fra-member-tools'); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Profile Management', 'fra-member-tools'); ?></strong></td>
                            <td><?php esc_html_e('Collect and store member relocation details', 'fra-member-tools'); ?></td>
                            <td><span style="color: #46b450;">✓ <?php esc_html_e('Active', 'fra-member-tools'); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Document Creation', 'fra-member-tools'); ?></strong></td>
                            <td><?php esc_html_e('AI-assisted document generation for visa applications', 'fra-member-tools'); ?></td>
                            <td><span style="color: #46b450;">✓ <?php esc_html_e('Active', 'fra-member-tools'); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Health Insurance Verification', 'fra-member-tools'); ?></strong></td>
                            <td><?php esc_html_e('AI analysis of health insurance certificates', 'fra-member-tools'); ?></td>
                            <td>
                                <?php if ($api_configured) : ?>
                                    <span style="color: #46b450;">✓ <?php esc_html_e('Active', 'fra-member-tools'); ?></span>
                                <?php else : ?>
                                    <span style="color: #dc3232;">✗ <?php esc_html_e('Needs API Key', 'fra-member-tools'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Interactive Checklists', 'fra-member-tools'); ?></strong></td>
                            <td><?php esc_html_e('Step-by-step visa application checklists', 'fra-member-tools'); ?></td>
                            <td><span style="color: #46b450;">✓ <?php esc_html_e('Active', 'fra-member-tools'); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Glossary', 'fra-member-tools'); ?></strong></td>
                            <td><?php esc_html_e('French administrative terms and definitions', 'fra-member-tools'); ?></td>
                            <td><span style="color: #46b450;">✓ <?php esc_html_e('Active', 'fra-member-tools'); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Step-by-Step Guides', 'fra-member-tools'); ?></strong></td>
                            <td><?php esc_html_e('Detailed guides for common relocation tasks', 'fra-member-tools'); ?></td>
                            <td><span style="color: #46b450;">✓ <?php esc_html_e('Active', 'fra-member-tools'); ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Usage', 'fra-member-tools'); ?></h2>
                <p><?php esc_html_e('Members access the Member Tools through the [fra_member_tools] shortcode. Add this shortcode to any page to display the member dashboard.', 'fra-member-tools'); ?></p>
                <p><code>[fra_member_tools]</code></p>
            </div>
        </div>
        <?php
    }

    /**
     * Plugin activation
     *
     * @return void
     */
    public function activate() {
        // Create database tables
        $this->create_tables();

        // Set default options
        $this->set_default_options();

        // Schedule daily cleanup of expired documents
        if (!wp_next_scheduled('framt_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'framt_daily_cleanup');
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create plugin database tables
     *
     * @return void
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();

        // Member profiles table
        $table_profiles = $wpdb->prefix . 'framt_profiles';
        $sql_profiles = "CREATE TABLE $table_profiles (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            profile_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

        // Saved documents table
        $table_documents = $wpdb->prefix . 'framt_documents';
        $sql_documents = "CREATE TABLE $table_documents (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            document_type varchar(50) NOT NULL,
            document_title varchar(255) NOT NULL,
            document_data longtext NOT NULL,
            document_meta longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY document_type (document_type)
        ) $charset_collate;";

        // Checklist progress table
        $table_checklists = $wpdb->prefix . 'framt_checklists';
        $sql_checklists = "CREATE TABLE $table_checklists (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            checklist_type varchar(50) NOT NULL,
            item_id varchar(100) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            handled_own tinyint(1) DEFAULT 0,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_checklist_item (user_id, checklist_type, item_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Messages table for support tickets
        $table_messages = $wpdb->prefix . 'framt_messages';
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

        // Message replies table
        $table_replies = $wpdb->prefix . 'framt_message_replies';
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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_profiles);
        dbDelta($sql_documents);
        dbDelta($sql_checklists);
        dbDelta($sql_messages);
        dbDelta($sql_replies);
    }

    /**
     * Set default plugin options
     *
     * @return void
     */
    private function set_default_options() {
        $defaults = array(
            'framt_version' => FRAMT_VERSION,
            'framt_enable_demo_mode' => false,
            'framt_default_language' => 'en',
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Plugin deactivation
     *
     * @return void
     */
    public function deactivate() {
        // Clear scheduled events if any
        wp_clear_scheduled_hook('framt_daily_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Initialize plugin
 *
 * @return FRA_Member_Tools
 */
function framt() {
    return FRA_Member_Tools::get_instance();
}

// Start the plugin
framt();
