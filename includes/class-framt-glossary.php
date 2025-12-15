<?php
/**
 * Glossary Management
 *
 * @package FRA_Member_Tools
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FRAMT_Glossary {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Get glossary terms organized by category
     */
    public function get_terms() {
        return array(
            'documents' => array(
                'title' => __('Document & Legal Terms', 'fra-member-tools'),
                'terms' => array(
                    'apostille' => array(
                        'title' => __('Apostille', 'fra-member-tools'),
                        'short' => __('Official certificate authenticating documents for international use.', 'fra-member-tools'),
                        'full' => $this->get_apostille_content(),
                    ),
                    'certified-copy' => array(
                        'title' => __('Certified Copy', 'fra-member-tools'),
                        'short' => __('An official copy verified by the issuing authority.', 'fra-member-tools'),
                    ),
                    'attestation' => array(
                        'title' => __('Attestation sur l\'honneur', 'fra-member-tools'),
                        'short' => __('A sworn statement declaring something to be true.', 'fra-member-tools'),
                    ),
                    'compromis' => array(
                        'title' => __('Compromis de vente', 'fra-member-tools'),
                        'short' => __('Preliminary sales agreement for property purchase.', 'fra-member-tools'),
                    ),
                    'acte' => array(
                        'title' => __('Acte de vente', 'fra-member-tools'),
                        'short' => __('Final deed of sale transferring property ownership.', 'fra-member-tools'),
                    ),
                    'procuration' => array(
                        'title' => __('Procuration', 'fra-member-tools'),
                        'short' => __('Power of attorney authorizing someone to act on your behalf.', 'fra-member-tools'),
                    ),
                ),
            ),
            'visa' => array(
                'title' => __('Visa & Residency Terms', 'fra-member-tools'),
                'terms' => array(
                    'vls-ts' => array(
                        'title' => __('VLS-TS', 'fra-member-tools'),
                        'short' => __('Long-stay visa equivalent to residence permit.', 'fra-member-tools'),
                    ),
                    'titre-sejour' => array(
                        'title' => __('Titre de séjour', 'fra-member-tools'),
                        'short' => __('Residence permit allowing you to live in France.', 'fra-member-tools'),
                    ),
                    'ofii' => array(
                        'title' => __('OFII', 'fra-member-tools'),
                        'short' => __('French Immigration and Integration Office.', 'fra-member-tools'),
                    ),
                    'prefecture' => array(
                        'title' => __('Préfecture', 'fra-member-tools'),
                        'short' => __('Regional government office handling residence permits.', 'fra-member-tools'),
                    ),
                    'recepisse' => array(
                        'title' => __('Récépissé', 'fra-member-tools'),
                        'short' => __('Temporary receipt serving as proof of pending application.', 'fra-member-tools'),
                    ),
                ),
            ),
            'healthcare' => array(
                'title' => __('Healthcare Terms', 'fra-member-tools'),
                'terms' => array(
                    'puma' => array(
                        'title' => __('PUMA', 'fra-member-tools'),
                        'short' => __('Universal healthcare coverage for legal residents.', 'fra-member-tools'),
                    ),
                    'carte-vitale' => array(
                        'title' => __('Carte Vitale', 'fra-member-tools'),
                        'short' => __('Green health insurance card for accessing French healthcare.', 'fra-member-tools'),
                    ),
                    'mutuelle' => array(
                        'title' => __('Mutuelle', 'fra-member-tools'),
                        'short' => __('Supplemental health insurance covering what PUMA doesn\'t.', 'fra-member-tools'),
                    ),
                    'cpam' => array(
                        'title' => __('CPAM', 'fra-member-tools'),
                        'short' => __('Local health insurance office.', 'fra-member-tools'),
                    ),
                    'medecin-traitant' => array(
                        'title' => __('Médecin traitant', 'fra-member-tools'),
                        'short' => __('Primary care doctor you designate for coordinated care.', 'fra-member-tools'),
                    ),
                ),
            ),
            'property' => array(
                'title' => __('Property & Financial Terms', 'fra-member-tools'),
                'terms' => array(
                    'notaire' => array(
                        'title' => __('Notaire', 'fra-member-tools'),
                        'short' => __('Public official who handles property transactions and legal documents.', 'fra-member-tools'),
                    ),
                    'frais-notaire' => array(
                        'title' => __('Frais de notaire', 'fra-member-tools'),
                        'short' => __('Notary fees and taxes (7-8% for older properties).', 'fra-member-tools'),
                    ),
                    'sequestre' => array(
                        'title' => __('Séquestre', 'fra-member-tools'),
                        'short' => __('Escrow deposit held by notaire during purchase.', 'fra-member-tools'),
                    ),
                    'rib' => array(
                        'title' => __('RIB', 'fra-member-tools'),
                        'short' => __('Bank account details document needed for French transactions.', 'fra-member-tools'),
                    ),
                    'taxe-fonciere' => array(
                        'title' => __('Taxe foncière', 'fra-member-tools'),
                        'short' => __('Annual property tax paid by owners.', 'fra-member-tools'),
                    ),
                ),
            ),
        );
    }

    /**
     * Get detailed apostille content
     */
    private function get_apostille_content() {
        return array(
            'what' => __('An apostille is an official certificate that authenticates the origin of a public document for use in another country.', 'fra-member-tools'),
            'why' => __('France is part of the Hague Apostille Convention (1961), which created a standardized way for countries to recognize each other\'s official documents.', 'fra-member-tools'),
            'looks_like' => __('A one-page certificate attached to your document with an official seal, signature, date, and unique certificate number.', 'fra-member-tools'),
            'documents' => array(
                __('Birth certificates', 'fra-member-tools'),
                __('Marriage certificates', 'fra-member-tools'),
                __('Divorce decrees', 'fra-member-tools'),
                __('Court documents', 'fra-member-tools'),
            ),
            'how' => __('Each US state handles apostilles through their Secretary of State office. Processing time and cost varies by state.', 'fra-member-tools'),
        );
    }

    /**
     * Render glossary page
     */
    public function render() {
        $terms = $this->get_terms();
        $profile = FRAMT_Profile::get_instance()->get_profile(get_current_user_id());

        ob_start();
        ?>
        <div class="framt-glossary">
            <div class="framt-glossary-header">
                <h2><?php esc_html_e('📚 Relocation Glossary', 'fra-member-tools'); ?></h2>
                <p><?php esc_html_e('Terms you\'ll encounter during your France relocation journey.', 'fra-member-tools'); ?></p>
            </div>

            <?php foreach ($terms as $category_key => $category) : ?>
                <div class="framt-glossary-category">
                    <h3><?php echo esc_html($category['title']); ?></h3>
                    
                    <div class="framt-terms-list">
                        <?php foreach ($category['terms'] as $term_key => $term) : ?>
                            <div class="framt-term-item" data-term="<?php echo esc_attr($term_key); ?>">
                                <button class="framt-term-toggle">
                                    <span class="framt-term-title"><?php echo esc_html($term['title']); ?></span>
                                    <span class="framt-term-arrow">▼</span>
                                </button>
                                <div class="framt-term-content">
                                    <p><?php echo esc_html($term['short']); ?></p>
                                    <?php if (!empty($term['full'])) : ?>
                                        <?php echo $this->render_full_term($term['full']); ?>
                                    <?php endif; ?>
                                </div>
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
     * Render full term content inline
     *
     * @param array $full Full term data
     * @return string HTML
     */
    private function render_full_term($full) {
        if (!is_array($full)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="framt-term-full">
            <?php if (!empty($full['what'])) : ?>
                <div class="framt-term-section">
                    <strong><?php esc_html_e('What is it?', 'fra-member-tools'); ?></strong>
                    <p><?php echo esc_html($full['what']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($full['why'])) : ?>
                <div class="framt-term-section">
                    <strong><?php esc_html_e('Why is it needed?', 'fra-member-tools'); ?></strong>
                    <p><?php echo esc_html($full['why']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($full['looks_like'])) : ?>
                <div class="framt-term-section">
                    <strong><?php esc_html_e('What does it look like?', 'fra-member-tools'); ?></strong>
                    <p><?php echo esc_html($full['looks_like']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($full['documents']) && is_array($full['documents'])) : ?>
                <div class="framt-term-section">
                    <strong><?php esc_html_e('Common documents:', 'fra-member-tools'); ?></strong>
                    <ul>
                        <?php foreach ($full['documents'] as $doc) : ?>
                            <li><?php echo esc_html($doc); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($full['how'])) : ?>
                <div class="framt-term-section">
                    <strong><?php esc_html_e('How to get one:', 'fra-member-tools'); ?></strong>
                    <p><?php echo esc_html($full['how']); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
