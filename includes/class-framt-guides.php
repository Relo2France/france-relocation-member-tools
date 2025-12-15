<?php
/**
 * Guides Management
 *
 * @package FRA_Member_Tools
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FRAMT_Guides {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Get available guides
     */
    public function get_guides() {
        return array(
            'apostille' => array(
                'title' => __('Apostille Guide', 'fra-member-tools'),
                'icon' => '📜',
                'description' => __('Step-by-step guide to getting your documents apostilled.', 'fra-member-tools'),
            ),
            'pet-relocation' => array(
                'title' => __('Pet Relocation Guide', 'fra-member-tools'),
                'icon' => '🐕',
                'description' => __('Complete guide to bringing your pets to France.', 'fra-member-tools'),
            ),
            'french-mortgages' => array(
                'title' => __('French Mortgages Guide', 'fra-member-tools'),
                'icon' => '🏦',
                'description' => __('How to get a mortgage in France as an American.', 'fra-member-tools'),
            ),
            'bank-ratings' => array(
                'title' => __('French Bank Ratings', 'fra-member-tools'),
                'icon' => '⭐',
                'description' => __('Comparison of French banks for mortgage lending.', 'fra-member-tools'),
            ),
        );
    }

    /**
     * Get apostille guide content personalized for user
     */
    public function get_apostille_guide($user_id) {
        $profile = FRAMT_Profile::get_instance()->get_profile($user_id);
        
        $states = array();
        
        // Add states based on profile
        if (!empty($profile['birth_state']) && $profile['birth_state'] !== 'other') {
            $states[$profile['birth_state']] = array(
                'document' => __('Your birth certificate', 'fra-member-tools'),
            );
        }
        
        if (!empty($profile['spouse_birth_state']) && $profile['spouse_birth_state'] !== 'other') {
            $states[$profile['spouse_birth_state']] = array(
                'document' => __('Spouse birth certificate', 'fra-member-tools'),
            );
        }
        
        if (!empty($profile['marriage_state'])) {
            $states[$profile['marriage_state']] = array(
                'document' => __('Marriage certificate', 'fra-member-tools'),
            );
        }

        return array(
            'states' => $states,
            'state_info' => $this->get_state_apostille_info(),
        );
    }

    /**
     * Get state apostille information
     */
    private function get_state_apostille_info() {
        return array(
            'CA' => array(
                'agency' => __('California Secretary of State', 'fra-member-tools'),
                'method' => __('Online or Mail', 'fra-member-tools'),
                'cost' => '$20',
                'time' => __('2-6 weeks', 'fra-member-tools'),
                'url' => 'https://www.sos.ca.gov/notary/apostille',
            ),
            'TX' => array(
                'agency' => __('Texas Secretary of State', 'fra-member-tools'),
                'method' => __('Mail only', 'fra-member-tools'),
                'cost' => '$15',
                'time' => __('3-5 weeks', 'fra-member-tools'),
                'url' => 'https://www.sos.state.tx.us/statdoc/apostilles.shtml',
            ),
            'NY' => array(
                'agency' => __('New York Department of State', 'fra-member-tools'),
                'method' => __('Mail or In-person', 'fra-member-tools'),
                'cost' => '$10',
                'time' => __('2-4 weeks', 'fra-member-tools'),
                'url' => 'https://dos.ny.gov/apostilles-and-authentications',
            ),
            'FL' => array(
                'agency' => __('Florida Department of State', 'fra-member-tools'),
                'method' => __('Online, Mail, or In-person', 'fra-member-tools'),
                'cost' => '$10',
                'time' => __('1-3 weeks', 'fra-member-tools'),
                'url' => 'https://dos.myflorida.com/sunbiz/other-services/apostille-authentication/',
            ),
            'VA' => array(
                'agency' => __('Virginia Secretary of the Commonwealth', 'fra-member-tools'),
                'method' => __('Online, Mail, or In-person', 'fra-member-tools'),
                'cost' => '$10',
                'time' => __('1-2 weeks', 'fra-member-tools'),
                'url' => 'https://commonwealth.virginia.gov/official-documents/',
            ),
        );
    }

    /**
     * Render guides page
     */
    public function render() {
        $guides = $this->get_guides();

        ob_start();
        ?>
        <div class="framt-guides">
            <h2><?php esc_html_e('📖 Guides', 'fra-member-tools'); ?></h2>
            <p><?php esc_html_e('Comprehensive guides personalized for your situation. View the guide info or generate a personalized document.', 'fra-member-tools'); ?></p>

            <div class="framt-guides-grid">
                <?php foreach ($guides as $key => $guide) : ?>
                    <div class="framt-guide-card" data-guide="<?php echo esc_attr($key); ?>">
                        <span class="framt-guide-icon"><?php echo esc_html($guide['icon']); ?></span>
                        <h3><?php echo esc_html($guide['title']); ?></h3>
                        <p><?php echo esc_html($guide['description']); ?></p>
                        <div class="framt-guide-actions">
                            <button class="framt-btn framt-btn-secondary framt-btn-small" data-action="view-guide" data-guide="<?php echo esc_attr($key); ?>">
                                <?php esc_html_e('View Info', 'fra-member-tools'); ?>
                            </button>
                            <button class="framt-btn framt-btn-primary framt-btn-small" data-action="generate-guide" data-guide="<?php echo esc_attr($key); ?>">
                                <?php esc_html_e('Generate for Me', 'fra-member-tools'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
