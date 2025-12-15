<?php
/**
 * Member Profile Management
 *
 * Handles member profile data including onboarding information,
 * document status, and personal preferences.
 *
 * @package FRA_Member_Tools
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Member profile management class
 */
class FRAMT_Profile {

    /**
     * Singleton instance
     *
     * @var FRAMT_Profile|null
     */
    private static $instance = null;

    /**
     * Database table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Profile field definitions
     *
     * @var array
     */
    private $fields;

    /**
     * Get singleton instance
     *
     * @return FRAMT_Profile
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
        $this->table_name = $wpdb->prefix . 'framt_profiles';
        $this->fields = $this->define_fields();
    }

    /**
     * Define profile fields and their properties
     *
     * @return array Field definitions
     */
    private function define_fields() {
        return array(
            // Personal Information (Legal Names for Documents)
            'legal_first_name' => array(
                'label' => __('Legal First Name', 'fra-member-tools'),
                'type' => 'text',
                'placeholder' => __('As shown on passport', 'fra-member-tools'),
                'group' => 'personal',
                'help' => __('Your first name exactly as it appears on your passport.', 'fra-member-tools'),
            ),
            'legal_middle_name' => array(
                'label' => __('Legal Middle Name(s)', 'fra-member-tools'),
                'type' => 'text',
                'placeholder' => __('Optional - as shown on passport', 'fra-member-tools'),
                'group' => 'personal',
                'help' => __('Include all middle names if they appear on your passport.', 'fra-member-tools'),
            ),
            'legal_last_name' => array(
                'label' => __('Legal Last Name', 'fra-member-tools'),
                'type' => 'text',
                'placeholder' => __('As shown on passport', 'fra-member-tools'),
                'group' => 'personal',
                'help' => __('Your surname exactly as it appears on your passport.', 'fra-member-tools'),
            ),
            'date_of_birth' => array(
                'label' => __('Date of Birth', 'fra-member-tools'),
                'type' => 'date',
                'group' => 'personal',
            ),
            'nationality' => array(
                'label' => __('Nationality', 'fra-member-tools'),
                'type' => 'text',
                'default' => 'American',
                'group' => 'personal',
            ),
            'passport_number' => array(
                'label' => __('Passport Number', 'fra-member-tools'),
                'type' => 'text',
                'placeholder' => __('Optional - for document pre-fill', 'fra-member-tools'),
                'group' => 'personal',
                'help' => __('Stored securely. Used to pre-fill visa documents.', 'fra-member-tools'),
            ),
            'passport_expiry' => array(
                'label' => __('Passport Expiry Date', 'fra-member-tools'),
                'type' => 'date',
                'group' => 'personal',
                'help' => __('Must be valid for at least 3 months beyond your planned stay.', 'fra-member-tools'),
            ),
            
            // Applicant Information
            'applicants' => array(
                'label' => __('Applicants', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'alone' => __('Applying alone', 'fra-member-tools'),
                    'spouse' => __('With spouse/partner', 'fra-member-tools'),
                    'spouse_kids' => __('With spouse and children', 'fra-member-tools'),
                    'kids_only' => __('With children (no spouse)', 'fra-member-tools'),
                ),
                'group' => 'applicants',
            ),
            'spouse_legal_first_name' => array(
                'label' => __('Spouse Legal First Name', 'fra-member-tools'),
                'type' => 'text',
                'placeholder' => __('As shown on passport', 'fra-member-tools'),
                'group' => 'applicants',
                'conditional' => array('applicants' => array('spouse', 'spouse_kids')),
            ),
            'spouse_legal_last_name' => array(
                'label' => __('Spouse Legal Last Name', 'fra-member-tools'),
                'type' => 'text',
                'placeholder' => __('As shown on passport', 'fra-member-tools'),
                'group' => 'applicants',
                'conditional' => array('applicants' => array('spouse', 'spouse_kids')),
            ),
            'spouse_date_of_birth' => array(
                'label' => __('Spouse Date of Birth', 'fra-member-tools'),
                'type' => 'date',
                'group' => 'applicants',
                'conditional' => array('applicants' => array('spouse', 'spouse_kids')),
            ),
            'spouse_name' => array(
                'label' => __('Spouse/Partner Preferred Name', 'fra-member-tools'),
                'type' => 'text',
                'placeholder' => __('How they prefer to be called', 'fra-member-tools'),
                'group' => 'applicants',
                'conditional' => array('applicants' => array('spouse', 'spouse_kids')),
            ),
            'spouse_work_status' => array(
                'label' => __('Spouse Work Status', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'employed' => __('Employed', 'fra-member-tools'),
                    'self_employed' => __('Self-employed', 'fra-member-tools'),
                    'retired' => __('Retired', 'fra-member-tools'),
                    'not_working' => __('Not working', 'fra-member-tools'),
                ),
                'group' => 'applicants',
                'conditional' => array('applicants' => array('spouse', 'spouse_kids')),
            ),
            'num_children' => array(
                'label' => __('Number of Children', 'fra-member-tools'),
                'type' => 'number',
                'group' => 'applicants',
                'conditional' => array('applicants' => array('spouse_kids', 'kids_only')),
            ),
            'children_ages' => array(
                'label' => __('Children Age Ranges', 'fra-member-tools'),
                'type' => 'text',
                'placeholder' => __('e.g., 5, 8, 12', 'fra-member-tools'),
                'group' => 'applicants',
                'conditional' => array('applicants' => array('spouse_kids', 'kids_only')),
            ),
            'has_pets' => array(
                'label' => __('Do you have pets?', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'no' => __('No', 'fra-member-tools'),
                    'dogs' => __('Dog(s)', 'fra-member-tools'),
                    'cats' => __('Cat(s)', 'fra-member-tools'),
                    'both' => __('Dogs and cats', 'fra-member-tools'),
                    'other' => __('Other pets', 'fra-member-tools'),
                ),
                'group' => 'applicants',
            ),
            'pet_details' => array(
                'label' => __('Pet Details', 'fra-member-tools'),
                'type' => 'text',
                'placeholder' => __('Number and type of pets', 'fra-member-tools'),
                'group' => 'applicants',
                'conditional' => array('has_pets' => array('dogs', 'cats', 'both', 'other')),
            ),

            // Visa & Work
            'visa_type' => array(
                'label' => __('Visa Type', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'undecided' => __('Undecided / Need help choosing', 'fra-member-tools'),
                    'visitor' => __('Visitor Visa (VLS-TS Visiteur)', 'fra-member-tools'),
                    'talent_passport' => __('Talent Passport', 'fra-member-tools'),
                    'employee' => __('Employee Visa', 'fra-member-tools'),
                    'entrepreneur' => __('Entrepreneur Visa', 'fra-member-tools'),
                    'student' => __('Student Visa', 'fra-member-tools'),
                    'family' => __('Family Reunification', 'fra-member-tools'),
                    'spouse_french' => __('Spouse of French National', 'fra-member-tools'),
                    'retiree' => __('Retiree Visa', 'fra-member-tools'),
                ),
                'group' => 'visa',
            ),
            'employment_status' => array(
                'label' => __('Your Employment Status', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'employed' => __('Employed', 'fra-member-tools'),
                    'self_employed' => __('Self-employed', 'fra-member-tools'),
                    'retired' => __('Retired', 'fra-member-tools'),
                    'not_working' => __('Not currently working', 'fra-member-tools'),
                ),
                'group' => 'visa',
            ),
            'work_in_france' => array(
                'label' => __('Planning to work in France?', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'no' => __('No', 'fra-member-tools'),
                    'yes_local' => __('Yes, for a French employer', 'fra-member-tools'),
                    'yes_remote' => __('Yes, remotely for a US company', 'fra-member-tools'),
                    'yes_self' => __('Yes, self-employed', 'fra-member-tools'),
                    'undecided' => __('Undecided', 'fra-member-tools'),
                ),
                'group' => 'visa',
            ),
            'industry' => array(
                'label' => __('Industry/Field', 'fra-member-tools'),
                'type' => 'text',
                'group' => 'visa',
                'conditional' => array('employment_status' => array('employed', 'self_employed')),
            ),
            'employer_name' => array(
                'label' => __('Employer Name', 'fra-member-tools'),
                'type' => 'text',
                'group' => 'visa',
                'conditional' => array('employment_status' => array('employed')),
            ),
            'job_title' => array(
                'label' => __('Job Title', 'fra-member-tools'),
                'type' => 'text',
                'group' => 'visa',
                'conditional' => array('employment_status' => array('employed', 'self_employed')),
            ),

            // Location
            'current_state' => array(
                'label' => __('Current US State', 'fra-member-tools'),
                'type' => 'select',
                'options' => $this->get_us_states(),
                'group' => 'location',
            ),
            'birth_state' => array(
                'label' => __('Your Birth State', 'fra-member-tools'),
                'type' => 'select',
                'options' => $this->get_us_states_with_other(),
                'group' => 'location',
            ),
            'birth_state_other' => array(
                'label' => __('Birth Country/Location', 'fra-member-tools'),
                'type' => 'text',
                'group' => 'location',
                'conditional' => array('birth_state' => array('other')),
            ),
            'spouse_birth_state' => array(
                'label' => __('Spouse Birth State', 'fra-member-tools'),
                'type' => 'select',
                'options' => $this->get_us_states_with_other(),
                'group' => 'location',
                'conditional' => array('applicants' => array('spouse', 'spouse_kids')),
            ),
            'marriage_state' => array(
                'label' => __('State/Country Where Married', 'fra-member-tools'),
                'type' => 'text',
                'group' => 'location',
                'conditional' => array('applicants' => array('spouse', 'spouse_kids')),
            ),
            'target_location' => array(
                'label' => __('Target Location in France', 'fra-member-tools'),
                'type' => 'text',
                'placeholder' => __('e.g., Paris, Dordogne, undecided', 'fra-member-tools'),
                'group' => 'location',
            ),
            'housing_plan' => array(
                'label' => __('Housing Plans', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'buying' => __('Buying property', 'fra-member-tools'),
                    'renting' => __('Renting', 'fra-member-tools'),
                    'undecided' => __('Undecided', 'fra-member-tools'),
                    'already_own' => __('Already own property', 'fra-member-tools'),
                ),
                'group' => 'location',
            ),

            // Timeline & Application
            'timeline' => array(
                'label' => __('Move Timeline', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'asap' => __('As soon as possible', 'fra-member-tools'),
                    '3_months' => __('Within 3 months', 'fra-member-tools'),
                    '6_months' => __('3-6 months', 'fra-member-tools'),
                    '12_months' => __('6-12 months', 'fra-member-tools'),
                    'over_12' => __('More than 12 months', 'fra-member-tools'),
                    'undecided' => __('Undecided', 'fra-member-tools'),
                ),
                'group' => 'timeline',
            ),
            'target_move_date' => array(
                'label' => __('Target Move Date', 'fra-member-tools'),
                'type' => 'date',
                'group' => 'timeline',
            ),
            'application_location' => array(
                'label' => __('Where are you applying from?', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'us' => __('United States (initial application)', 'fra-member-tools'),
                    'france' => __('France (renewal/reapplication)', 'fra-member-tools'),
                ),
                'group' => 'timeline',
            ),

            // Financial
            'income_sources' => array(
                'label' => __('Income Sources', 'fra-member-tools'),
                'type' => 'multiselect',
                'options' => array(
                    'employment' => __('Employment income', 'fra-member-tools'),
                    'retirement' => __('Retirement/pension', 'fra-member-tools'),
                    'investment' => __('Investment/dividend income', 'fra-member-tools'),
                    'savings' => __('Savings', 'fra-member-tools'),
                    'rental' => __('Rental income', 'fra-member-tools'),
                    'other' => __('Other', 'fra-member-tools'),
                ),
                'group' => 'financial',
            ),
            'french_mortgage' => array(
                'label' => __('Planning to get a French mortgage?', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'no' => __('No', 'fra-member-tools'),
                    'yes' => __('Yes', 'fra-member-tools'),
                    'maybe' => __('Maybe/Undecided', 'fra-member-tools'),
                ),
                'group' => 'financial',
            ),

            // Language
            'french_proficiency' => array(
                'label' => __('French Language Proficiency', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'none' => __('None', 'fra-member-tools'),
                    'basic' => __('Basic', 'fra-member-tools'),
                    'conversational' => __('Conversational', 'fra-member-tools'),
                    'fluent' => __('Fluent', 'fra-member-tools'),
                    'native' => __('Native', 'fra-member-tools'),
                ),
                'group' => 'language',
            ),

            // Document Status
            'has_birth_cert' => array(
                'label' => __('Have certified birth certificate?', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'yes' => __('Yes', 'fra-member-tools'),
                    'no' => __('No, need to order', 'fra-member-tools'),
                    'unsure' => __('Not sure if it\'s certified', 'fra-member-tools'),
                ),
                'group' => 'documents',
            ),
            'birth_cert_apostilled' => array(
                'label' => __('Is it apostilled?', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'yes' => __('Yes', 'fra-member-tools'),
                    'no' => __('No', 'fra-member-tools'),
                ),
                'group' => 'documents',
                'conditional' => array('has_birth_cert' => array('yes')),
            ),
            'has_marriage_cert' => array(
                'label' => __('Have certified marriage certificate?', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'yes' => __('Yes', 'fra-member-tools'),
                    'no' => __('No, need to order', 'fra-member-tools'),
                    'unsure' => __('Not sure if it\'s certified', 'fra-member-tools'),
                    'na' => __('Not applicable', 'fra-member-tools'),
                ),
                'group' => 'documents',
                'conditional' => array('applicants' => array('spouse', 'spouse_kids')),
            ),
            'marriage_cert_apostilled' => array(
                'label' => __('Is marriage certificate apostilled?', 'fra-member-tools'),
                'type' => 'select',
                'options' => array(
                    'yes' => __('Yes', 'fra-member-tools'),
                    'no' => __('No', 'fra-member-tools'),
                ),
                'group' => 'documents',
                'conditional' => array('has_marriage_cert' => array('yes')),
            ),
        );
    }

    /**
     * Get US states for dropdown
     *
     * @return array State options
     */
    private function get_us_states() {
        return array(
            '' => __('Select a state...', 'fra-member-tools'),
            'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
            'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
            'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
            'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
            'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
            'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
            'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
            'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
            'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
            'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
            'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
            'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
            'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        );
    }

    /**
     * Get US states with "Other" option
     *
     * @return array State options with other
     */
    private function get_us_states_with_other() {
        $states = $this->get_us_states();
        $states['other'] = __('Other (not US)', 'fra-member-tools');
        return $states;
    }

    /**
     * Get profile for a user
     *
     * @param int|null $user_id User ID
     * @return array Profile data
     */
    public function get_profile($user_id = null) {
        global $wpdb;

        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return array();
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT profile_data FROM {$this->table_name} WHERE user_id = %d",
                $user_id
            )
        );

        if ($row && !empty($row->profile_data)) {
            return json_decode($row->profile_data, true) ?: array();
        }

        return array();
    }
    
    /**
     * Get profile with pre-fill from registration data on first access
     *
     * This is called when rendering the profile. If the profile is empty,
     * it will pre-fill from MemberPress/WordPress registration data and
     * return a flag indicating the notice should be shown.
     *
     * @param int|null $user_id User ID
     * @return array Array with 'profile' data and 'show_prefill_notice' flag
     */
    public function get_profile_with_prefill($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return array('profile' => array(), 'show_prefill_notice' => false);
        }

        // Get existing profile
        $profile = $this->get_profile($user_id);
        $show_prefill_notice = false;
        
        // Check if this profile has been pre-filled already
        $prefill_done = get_user_meta($user_id, '_framt_prefill_done', true);
        
        // If profile is empty and prefill hasn't been done, do it now
        if (empty($prefill_done) && $this->is_profile_empty($profile)) {
            $prefill_data = $this->get_registration_prefill_data($user_id);
            
            if (!empty($prefill_data)) {
                // Merge prefill data into profile
                $profile = array_merge($profile, $prefill_data);
                
                // Save the pre-filled profile
                $this->save_profile($profile, $user_id);
                
                // Mark prefill as done
                update_user_meta($user_id, '_framt_prefill_done', current_time('mysql'));
                
                // Set flag to show notice
                $show_prefill_notice = true;
            }
        }
        
        return array(
            'profile' => $profile,
            'show_prefill_notice' => $show_prefill_notice,
        );
    }
    
    /**
     * Check if profile is essentially empty
     *
     * @param array $profile Profile data
     * @return bool True if profile has no meaningful data
     */
    private function is_profile_empty($profile) {
        if (empty($profile)) {
            return true;
        }
        
        // Check key fields that indicate real profile data
        $key_fields = array('legal_first_name', 'legal_last_name', 'visa_type', 'timeline');
        
        foreach ($key_fields as $field) {
            if (!empty($profile[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get registration data for pre-filling profile
     *
     * Pulls from WordPress user data and MemberPress address fields.
     *
     * @param int $user_id User ID
     * @return array Data to pre-fill
     */
    private function get_registration_prefill_data($user_id) {
        // Try to use France Relocation Assistant helper if available
        if (function_exists('fra') && method_exists(fra(), 'get_user_registration_data')) {
            return fra()->get_user_registration_data($user_id);
        }
        
        // Fallback: get data directly
        $user = get_userdata($user_id);
        if (!$user) {
            return array();
        }
        
        $data = array();
        
        if (!empty($user->first_name)) {
            $data['legal_first_name'] = $user->first_name;
        }
        if (!empty($user->last_name)) {
            $data['legal_last_name'] = $user->last_name;
        }
        
        // Try to get MemberPress address fields
        $mepr_country = get_user_meta($user_id, 'mepr-address-country', true);
        $mepr_state = get_user_meta($user_id, 'mepr-address-state', true);
        
        // Map US to "current_country" being US (they're moving FROM here)
        if (!empty($mepr_country) && $mepr_country === 'US') {
            // They're in the US, which is expected for this site
            // Don't set current_country as they'll specify their state
        }
        if (!empty($mepr_state)) {
            $data['current_state'] = $mepr_state;
        }
        
        return $data;
    }

    /**
     * Save profile for a user
     *
     * @param array $data Profile data
     * @param int|null $user_id User ID
     * @return bool Success
     */
    public function save_profile($data, $user_id = null) {
        global $wpdb;

        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Sanitize data
        $sanitized = $this->sanitize_profile_data($data);

        // Merge with existing data
        $existing = $this->get_profile($user_id);
        $merged = array_merge($existing, $sanitized);

        $json_data = wp_json_encode($merged);

        // Check if profile exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE user_id = %d",
                $user_id
            )
        );

        if ($exists) {
            return $wpdb->update(
                $this->table_name,
                array('profile_data' => $json_data),
                array('user_id' => $user_id),
                array('%s'),
                array('%d')
            ) !== false;
        }

        return $wpdb->insert(
            $this->table_name,
            array(
                'user_id' => $user_id,
                'profile_data' => $json_data,
            ),
            array('%d', '%s')
        ) !== false;
    }

    /**
     * Sanitize profile data
     *
     * @param array $data Raw profile data
     * @return array Sanitized data
     */
    private function sanitize_profile_data($data) {
        $sanitized = array();

        foreach ($data as $key => $value) {
            if (!isset($this->fields[$key])) {
                continue;
            }

            $field = $this->fields[$key];

            switch ($field['type']) {
                case 'text':
                case 'date':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;

                case 'select':
                    if (isset($field['options'][$value])) {
                        $sanitized[$key] = $value;
                    }
                    break;

                case 'multiselect':
                    if (is_array($value)) {
                        $sanitized[$key] = array_filter($value, function($v) use ($field) {
                            return isset($field['options'][$v]);
                        });
                    }
                    break;

                case 'number':
                    $sanitized[$key] = absint($value);
                    break;

                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Get profile completion percentage
     *
     * @param int|null $user_id User ID
     * @return int Percentage complete
     */
    public function get_completion_percentage($user_id = null) {
        $profile = $this->get_profile($user_id);

        if (empty($profile)) {
            return 0;
        }

        // Required fields for basic completion
        $required = array(
            'applicants',
            'visa_type',
            'employment_status',
            'current_state',
            'birth_state',
            'timeline',
            'application_location',
        );

        $completed = 0;
        foreach ($required as $field) {
            if (!empty($profile[$field])) {
                $completed++;
            }
        }

        return round(($completed / count($required)) * 100);
    }

    /**
     * Check if profile is complete enough for document generation
     *
     * @param int|null $user_id User ID
     * @return bool
     */
    public function is_profile_complete($user_id = null) {
        return $this->get_completion_percentage($user_id) >= 70;
    }

    /**
     * Check if onboarding is needed
     *
     * @param int|null $user_id User ID
     * @return bool
     */
    public function needs_onboarding($user_id = null) {
        $profile = $this->get_profile($user_id);
        return empty($profile) || empty($profile['onboarding_complete']);
    }

    /**
     * Mark onboarding as complete
     *
     * @param int|null $user_id User ID
     * @return bool
     */
    public function complete_onboarding($user_id = null) {
        return $this->save_profile(array('onboarding_complete' => true), $user_id);
    }

    /**
     * Get profile field definitions
     *
     * @return array Field definitions
     */
    public function get_fields() {
        return $this->fields;
    }

    /**
     * Get profile value formatted for display
     *
     * @param string $field Field name
     * @param int|null $user_id User ID
     * @return string Formatted value
     */
    public function get_display_value($field, $user_id = null) {
        $profile = $this->get_profile($user_id);

        if (!isset($profile[$field]) || !isset($this->fields[$field])) {
            return '';
        }

        $value = $profile[$field];
        $field_def = $this->fields[$field];

        if ($field_def['type'] === 'select' && isset($field_def['options'][$value])) {
            return $field_def['options'][$value];
        }

        if ($field_def['type'] === 'multiselect' && is_array($value)) {
            $labels = array();
            foreach ($value as $v) {
                if (isset($field_def['options'][$v])) {
                    $labels[] = $field_def['options'][$v];
                }
            }
            return implode(', ', $labels);
        }

        return $value;
    }

    /**
     * AJAX handler: Save profile
     *
     * @return void
     */
    public function ajax_save_profile() {
        check_ajax_referer('framt_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'fra-member-tools'));
        }

        $data = isset($_POST['profile']) ? $_POST['profile'] : array();

        if ($this->save_profile($data)) {
            wp_send_json_success(array(
                'message' => __('Profile saved successfully.', 'fra-member-tools'),
                'completion' => $this->get_completion_percentage(),
            ));
        }

        wp_send_json_error(__('Failed to save profile.', 'fra-member-tools'));
    }

    /**
     * AJAX handler: Get profile
     *
     * @return void
     */
    public function ajax_get_profile() {
        check_ajax_referer('framt_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(__('You must be logged in.', 'fra-member-tools'));
        }
        
        // Use prefill method to auto-populate on first access
        $prefill_result = $this->get_profile_with_prefill();

        wp_send_json_success(array(
            'profile' => $prefill_result['profile'],
            'completion' => $this->get_completion_percentage(),
            'fields' => $this->fields,
            'show_prefill_notice' => $prefill_result['show_prefill_notice'],
        ));
    }

    /**
     * Render the profile page
     *
     * @return string HTML output
     */
    public function render() {
        $user_id = get_current_user_id();
        
        // Use prefill method for first-time access
        $prefill_result = $this->get_profile_with_prefill($user_id);
        $profile = $prefill_result['profile'];
        $show_prefill_notice = $prefill_result['show_prefill_notice'];
        
        $completion = $this->get_completion_percentage($user_id);
        $current_user = wp_get_current_user();
        
        // Group fields by section
        $sections = array(
            'personal' => array(
                'title' => __('👤 Personal Information', 'fra-member-tools'),
                'description' => __('Legal names as they appear on your passport. These will be used for official documents.', 'fra-member-tools'),
                'fields' => array('legal_first_name', 'legal_middle_name', 'legal_last_name', 'date_of_birth', 'nationality', 'passport_number', 'passport_expiry'),
            ),
            'applicants' => array(
                'title' => __('👥 Who\'s Moving?', 'fra-member-tools'),
                'fields' => array('applicants', 'spouse_legal_first_name', 'spouse_legal_last_name', 'spouse_date_of_birth', 'spouse_name', 'spouse_work_status', 'num_children', 'children_ages', 'has_pets', 'pet_details'),
            ),
            'visa' => array(
                'title' => __('📋 Visa & Employment', 'fra-member-tools'),
                'fields' => array('visa_type', 'employment_status', 'work_in_france', 'industry', 'employer_name', 'job_title'),
            ),
            'location' => array(
                'title' => __('📍 Locations', 'fra-member-tools'),
                'fields' => array('current_state', 'birth_state', 'birth_state_other', 'spouse_birth_state', 'marriage_state', 'target_location'),
            ),
            'timeline' => array(
                'title' => __('📅 Timeline & Plans', 'fra-member-tools'),
                'fields' => array('housing_plans', 'timeline', 'target_move_date', 'application_location'),
            ),
            'finances' => array(
                'title' => __('💰 Financial', 'fra-member-tools'),
                'fields' => array('income_sources', 'french_mortgage', 'french_level'),
            ),
            'documents' => array(
                'title' => __('📄 Document Status', 'fra-member-tools'),
                'fields' => array('birth_cert_status', 'birth_cert_apostille', 'marriage_cert_status', 'marriage_cert_apostille'),
            ),
        );
        
        // Get customizer colors
        $customizer = get_option('fra_customizer', array());
        $header_bg = $customizer['color_profile_header_bg'] ?? '#1a1a1a';
        $header_text = $customizer['color_profile_header_text'] ?? '#fafaf8';
        $progress_fill = $customizer['color_profile_progress_fill'] ?? '#d4a853';
        
        ob_start();
        ?>
        <div class="framt-profile">
            
            <?php if ($show_prefill_notice) : ?>
            <!-- Pre-fill Notice - shown once on first profile access -->
            <div class="framt-prefill-notice" id="framt-prefill-notice">
                <div class="framt-prefill-notice-content">
                    <div class="framt-prefill-icon">✨</div>
                    <h3><?php _e('We\'ve pre-filled your profile!', 'fra-member-tools'); ?></h3>
                    <p><?php _e('We\'ve used your registration information to get you started. Feel free to edit any fields - your profile information is separate from your billing details and will be used for your visa documents.', 'fra-member-tools'); ?></p>
                    <button type="button" class="framt-prefill-dismiss" onclick="document.getElementById('framt-prefill-notice').style.display='none';">
                        <?php _e('Got it!', 'fra-member-tools'); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="framt-profile-header" style="background: <?php echo esc_attr($header_bg); ?>; color: <?php echo esc_attr($header_text); ?>;">
                <div class="framt-profile-avatar">
                    <?php echo get_avatar($user_id, 80); ?>
                </div>
                <div class="framt-profile-info">
                    <h2 style="color: <?php echo esc_attr($header_text); ?>;"><?php echo esc_html($current_user->display_name); ?></h2>
                    <p><?php echo esc_html($current_user->user_email); ?></p>
                    <div class="framt-profile-completion">
                        <div class="framt-progress-bar">
                            <div class="framt-progress-fill" style="width: <?php echo intval($completion); ?>%; background: <?php echo esc_attr($progress_fill); ?>;"></div>
                        </div>
                        <span><?php echo intval($completion); ?>% complete</span>
                    </div>
                </div>
            </div>

            <form id="framt-profile-form" class="framt-profile-form">
                <?php foreach ($sections as $section_key => $section) : ?>
                    <div class="framt-form-section">
                        <h3 class="framt-form-section-title"><?php echo esc_html($section['title']); ?></h3>
                        <div class="framt-form-grid">
                            <?php foreach ($section['fields'] as $field_key) : 
                                if (!isset($this->fields[$field_key])) continue;
                                $field = $this->fields[$field_key];
                                $value = $profile[$field_key] ?? '';
                            ?>
                            <div class="framt-form-group">
                                <label for="<?php echo esc_attr($field_key); ?>">
                                    <?php echo esc_html($field['label']); ?>
                                </label>
                                
                                <?php if ($field['type'] === 'select') : ?>
                                    <select name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>">
                                        <option value="">-- Select --</option>
                                        <?php foreach ($field['options'] as $opt_value => $opt_label) : ?>
                                            <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($value, $opt_value); ?>>
                                                <?php echo esc_html($opt_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($field['type'] === 'textarea') : ?>
                                    <textarea name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" rows="3"><?php echo esc_textarea($value); ?></textarea>
                                <?php elseif ($field['type'] === 'date') : ?>
                                    <input type="date" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" value="<?php echo esc_attr($value); ?>">
                                <?php elseif ($field['type'] === 'number') : ?>
                                    <input type="number" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" value="<?php echo esc_attr($value); ?>" min="0">
                                <?php else : ?>
                                    <input type="text" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" value="<?php echo esc_attr($value); ?>">
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="framt-form-actions">
                    <button type="submit" class="framt-btn framt-btn-primary">
                        <?php _e('Save Profile', 'fra-member-tools'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
