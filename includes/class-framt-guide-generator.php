<?php
/**
 * AI-Powered Guide Generator
 *
 * Generates personalized, professionally formatted guides based on user profile.
 *
 * @package FRA_Member_Tools
 * @since 1.0.24
 */

if (!defined('ABSPATH')) {
    exit;
}

class FRAMT_Guide_Generator {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Get guide questions for a specific guide type
     */
    public function get_guide_questions($guide_type) {
        $questions = array(
            'french-mortgages' => array(
                array(
                    'id' => 'purchase_price',
                    'question' => 'What is the purchase price of the property?',
                    'type' => 'currency',
                    'placeholder' => '€500,000',
                    'profile_field' => null,
                ),
                array(
                    'id' => 'loan_amount',
                    'question' => 'How much do you plan to borrow?',
                    'type' => 'currency',
                    'placeholder' => '€400,000',
                    'profile_field' => null,
                ),
                array(
                    'id' => 'target_rate',
                    'question' => 'What interest rate are you targeting?',
                    'type' => 'choice',
                    'options' => array(
                        '3.0-3.3%' => '3.0% - 3.3% (Excellent)',
                        '3.4-3.6%' => '3.4% - 3.6% (Good)',
                        '3.7-4.0%' => '3.7% - 4.0% (Acceptable)',
                        'unsure' => 'I\'m not sure what\'s realistic',
                    ),
                    'profile_field' => null,
                ),
                array(
                    'id' => 'loan_term',
                    'question' => 'What loan term are you considering?',
                    'type' => 'choice',
                    'options' => array(
                        '15' => '15 years',
                        '20' => '20 years',
                        '25' => '25 years',
                        'unsure' => 'I\'m not sure yet',
                    ),
                    'profile_field' => null,
                ),
                array(
                    'id' => 'early_payoff',
                    'question' => 'Do you plan to pay off the mortgage early?',
                    'type' => 'choice',
                    'options' => array(
                        'yes_2_3' => 'Yes, within 2-3 years',
                        'yes_5_10' => 'Yes, within 5-10 years',
                        'maybe' => 'Maybe, depending on circumstances',
                        'no' => 'No, I plan to keep it full term',
                    ),
                    'profile_field' => null,
                ),
                array(
                    'id' => 'early_payoff_year',
                    'question' => 'What year do you expect to pay off early?',
                    'type' => 'text',
                    'placeholder' => '2028',
                    'condition' => array('early_payoff' => array('yes_2_3', 'yes_5_10')),
                    'profile_field' => null,
                ),
                array(
                    'id' => 'using_broker',
                    'question' => 'Are you working with a mortgage broker?',
                    'type' => 'choice',
                    'options' => array(
                        'yes' => 'Yes, I have a broker',
                        'considering' => 'Considering using one',
                        'no' => 'No, going directly to banks',
                    ),
                    'profile_field' => null,
                ),
                array(
                    'id' => 'closing_timeline',
                    'question' => 'When do you need to close?',
                    'type' => 'choice',
                    'options' => array(
                        '1_2_months' => '1-2 months',
                        '3_4_months' => '3-4 months',
                        '6_months' => '6+ months',
                        'flexible' => 'Flexible timeline',
                    ),
                    'profile_field' => null,
                ),
            ),
            'pet-relocation' => array(
                array(
                    'id' => 'pet_type',
                    'question' => 'What type of pet(s) are you bringing?',
                    'type' => 'multi_choice',
                    'options' => array(
                        'dog' => 'Dog',
                        'cat' => 'Cat',
                        'both' => 'Both dog and cat',
                        'other' => 'Other (bird, rabbit, etc.)',
                    ),
                ),
                array(
                    'id' => 'pet_count',
                    'question' => 'How many pets total?',
                    'type' => 'choice',
                    'options' => array(
                        '1' => '1 pet',
                        '2' => '2 pets',
                        '3_plus' => '3 or more pets',
                    ),
                ),
                array(
                    'id' => 'travel_method',
                    'question' => 'How will you travel to France?',
                    'type' => 'choice',
                    'options' => array(
                        'flying_cabin' => 'Flying - pet in cabin',
                        'flying_cargo' => 'Flying - pet in cargo',
                        'driving' => 'Driving through (from UK/Europe)',
                        'pet_transport' => 'Using a pet transport service',
                        'unsure' => 'Not decided yet',
                    ),
                ),
                array(
                    'id' => 'microchipped',
                    'question' => 'Is your pet already microchipped?',
                    'type' => 'choice',
                    'options' => array(
                        'yes_iso' => 'Yes, ISO 15-digit chip',
                        'yes_other' => 'Yes, but not sure what type',
                        'no' => 'No, not yet',
                    ),
                ),
                array(
                    'id' => 'rabies_status',
                    'question' => 'What is your pet\'s rabies vaccination status?',
                    'type' => 'choice',
                    'options' => array(
                        'current' => 'Currently vaccinated',
                        'expired' => 'Vaccination expired',
                        'never' => 'Never vaccinated',
                        'unsure' => 'Not sure',
                    ),
                ),
                array(
                    'id' => 'move_timeline',
                    'question' => 'When are you planning to move?',
                    'type' => 'choice',
                    'options' => array(
                        'under_30' => 'Less than 30 days',
                        '1_3_months' => '1-3 months',
                        '3_6_months' => '3-6 months',
                        '6_plus' => '6+ months',
                    ),
                ),
            ),
            'apostille' => array(
                array(
                    'id' => 'documents_needed',
                    'question' => 'Which documents do you need apostilled?',
                    'type' => 'multi_choice',
                    'options' => array(
                        'birth_cert' => 'Birth certificate',
                        'marriage_cert' => 'Marriage certificate',
                        'divorce_decree' => 'Divorce decree',
                        'death_cert' => 'Death certificate',
                        'court_docs' => 'Court documents',
                        'notarized_docs' => 'Notarized documents',
                    ),
                ),
                array(
                    'id' => 'urgency',
                    'question' => 'How urgently do you need these?',
                    'type' => 'choice',
                    'options' => array(
                        'asap' => 'As soon as possible (expedited)',
                        '2_4_weeks' => 'Within 2-4 weeks',
                        'flexible' => 'No rush, flexible timeline',
                    ),
                ),
            ),
            'bank-ratings' => array(
                array(
                    'id' => 'banking_needs',
                    'question' => 'What are your primary banking needs?',
                    'type' => 'multi_choice',
                    'options' => array(
                        'daily' => 'Daily banking (checking/debit)',
                        'mortgage' => 'Mortgage financing',
                        'savings' => 'Savings/investment',
                        'transfers' => 'International transfers',
                    ),
                ),
                array(
                    'id' => 'english_support',
                    'question' => 'How important is English language support?',
                    'type' => 'choice',
                    'options' => array(
                        'essential' => 'Essential - I don\'t speak French',
                        'preferred' => 'Preferred but not required',
                        'not_needed' => 'Not needed - I speak French',
                    ),
                ),
                array(
                    'id' => 'online_banking',
                    'question' => 'How important is online/mobile banking?',
                    'type' => 'choice',
                    'options' => array(
                        'essential' => 'Essential - primary way I bank',
                        'important' => 'Important but not critical',
                        'not_important' => 'Not important',
                    ),
                ),
            ),
        );

        return $questions[$guide_type] ?? array();
    }

    /**
     * Generate personalized guide content
     */
    public function generate_guide($guide_type, $answers, $profile) {
        switch ($guide_type) {
            case 'french-mortgages':
                return $this->generate_mortgage_guide($answers, $profile);
            case 'pet-relocation':
                return $this->generate_pet_guide($answers, $profile);
            case 'apostille':
                return $this->generate_apostille_guide($answers, $profile);
            case 'bank-ratings':
                return $this->generate_bank_guide($answers, $profile);
            default:
                return null;
        }
    }

    /**
     * Generate French Mortgage Evaluation Guide
     */
    private function generate_mortgage_guide($answers, $profile) {
        $user = wp_get_current_user();
        $name = $user->display_name ?: 'Member';
        
        // Calculate values
        $purchase_price = $this->parse_currency($answers['purchase_price'] ?? '500000');
        $loan_amount = $this->parse_currency($answers['loan_amount'] ?? '400000');
        $down_payment = $purchase_price - $loan_amount;
        $ltv = round(($loan_amount / $purchase_price) * 100);
        $loan_term = intval($answers['loan_term'] ?? 20);
        
        // Determine rate benchmarks based on LTV
        if ($ltv <= 70) {
            $excellent_rate = '3.0% - 3.3%';
            $average_rate = '3.4% - 3.6%';
            $poor_rate = '3.7%+';
        } elseif ($ltv <= 80) {
            $excellent_rate = '3.2% - 3.5%';
            $average_rate = '3.5% - 3.7%';
            $poor_rate = '3.8%+';
        } else {
            $excellent_rate = '3.4% - 3.7%';
            $average_rate = '3.7% - 4.0%';
            $poor_rate = '4.0%+';
        }
        
        // Calculate monthly payment estimates (at 3.5%)
        $rate = 0.035;
        $monthly_rate = $rate / 12;
        $num_payments = $loan_term * 12;
        $monthly_payment = $loan_amount * ($monthly_rate * pow(1 + $monthly_rate, $num_payments)) / (pow(1 + $monthly_rate, $num_payments) - 1);
        $monthly_payment = round($monthly_payment);
        
        // Early payoff calculations
        $early_payoff_year = $answers['early_payoff_year'] ?? '';
        $years_until_payoff = $early_payoff_year ? (intval($early_payoff_year) - intval(date('Y'))) : 0;
        
        // Estimate remaining balance
        $remaining_balance = $loan_amount;
        if ($years_until_payoff > 0 && $years_until_payoff < $loan_term) {
            // Simplified remaining balance calculation
            $payments_made = $years_until_payoff * 12;
            for ($i = 0; $i < $payments_made; $i++) {
                $interest = $remaining_balance * $monthly_rate;
                $principal = $monthly_payment - $interest;
                $remaining_balance -= $principal;
            }
            $remaining_balance = round($remaining_balance);
        }
        
        // IRA penalty calculations
        $ira_6months = round($remaining_balance * $rate * 0.5);
        $ira_3percent = round($remaining_balance * 0.03);
        $ira_standard = min($ira_6months, $ira_3percent);
        
        // Target location
        $target_location = $profile['target_location'] ?? 'France';
        
        // Build the guide content
        $content = array(
            'title' => 'French Mortgage Evaluation Guide',
            'subtitle' => 'Personalized for ' . esc_html($name),
            'date' => date('F j, Y'),
            'summary' => array(
                'loan_amount' => '€' . number_format($loan_amount),
                'purchase_price' => '€' . number_format($purchase_price),
                'down_payment' => '€' . number_format($down_payment) . ' (' . $ltv . '% LTV)',
                'loan_term' => $loan_term . ' years',
                'target_location' => $target_location,
            ),
            'sections' => array(),
        );
        
        // Section 1: Offer Quality Benchmarks
        $content['sections'][] = array(
            'title' => '1. Offer Quality Benchmarks',
            'intro' => 'Use these benchmarks to assess whether mortgage offers are excellent, average, or poor for your situation.',
            'subsections' => array(
                array(
                    'title' => 'Excellent Offer (Best Case)',
                    'type' => 'success',
                    'items' => array(
                        'Interest Rate: ' . $excellent_rate,
                        'Monthly Payment: €' . number_format($monthly_payment - 50) . ' - €' . number_format($monthly_payment),
                        'Early Repayment Penalties (IRA): Waived or maximum 1%',
                        'Application Fees: Waived or under €500',
                        'Insurance: External insurance allowed',
                        'Guarantee Type: Surety bond with fees ~1%',
                    ),
                ),
                array(
                    'title' => 'Average Offer (Acceptable)',
                    'type' => 'warning',
                    'items' => array(
                        'Interest Rate: ' . $average_rate,
                        'Monthly Payment: €' . number_format($monthly_payment) . ' - €' . number_format($monthly_payment + 70),
                        'Early Repayment Penalties: Standard 6 months interest or 3%',
                        'Application Fees: €500 - €1,200',
                        'Insurance: Bank or external with approval',
                        'Guarantee Fees: 1.5% - 2%',
                    ),
                ),
                array(
                    'title' => 'Poor Offer (Reject)',
                    'type' => 'critical',
                    'items' => array(
                        'Interest Rate: ' . $poor_rate,
                        'Monthly Payment: €' . number_format($monthly_payment + 120) . '+',
                        'Early Repayment Penalties: 3% with no flexibility',
                        'Application Fees: €1,500+',
                        'Insurance: Bank only, no external option',
                        'Only 1 offer or pressure tactics',
                    ),
                ),
            ),
        );
        
        // Section 2: Early Payoff Analysis (if applicable)
        if ($years_until_payoff > 0 && $years_until_payoff < $loan_term) {
            $content['sections'][] = array(
                'title' => '2. Early Payoff Analysis (' . $early_payoff_year . ')',
                'intro' => 'Based on your plan to pay off in ' . $early_payoff_year . ', here\'s what to expect.',
                'calculations' => array(
                    array(
                        'label' => 'Estimated Remaining Balance',
                        'value' => '€' . number_format($remaining_balance),
                    ),
                    array(
                        'label' => 'Standard IRA (6 months interest)',
                        'value' => '€' . number_format($ira_6months),
                    ),
                    array(
                        'label' => 'Maximum IRA (3% of balance)',
                        'value' => '€' . number_format($ira_3percent),
                    ),
                    array(
                        'label' => 'Your Penalty (whichever is lower)',
                        'value' => '€' . number_format($ira_standard),
                        'highlight' => true,
                    ),
                ),
                'tip' => 'IMPORTANT: Negotiate to have IRA penalties waived or reduced. Saving €' . number_format($ira_standard) . ' in penalties could offset broker fees entirely.',
            );
        }
        
        // Section 3: Critical Questions
        $content['sections'][] = array(
            'title' => ($years_until_payoff > 0 ? '3' : '2') . '. Critical Questions to Ask',
            'intro' => 'Use this checklist when reviewing mortgage offers.',
            'checklists' => array(
                array(
                    'title' => 'Interest Rate & Costs',
                    'items' => array(
                        'What is the nominal interest rate (taux nominal)?',
                        'What is the APR/TAEG (includes all fees)?',
                        'What is the exact monthly payment (mensualité)?',
                        'What are the total fees over the loan term?',
                    ),
                ),
                array(
                    'title' => 'Early Repayment Terms',
                    'items' => array(
                        'What are the early repayment penalties (IRA)?',
                        'Can penalties be waived or reduced?',
                        'Are penalties different for partial vs. full repayment?',
                        'Do penalties decrease over time?',
                    ),
                ),
                array(
                    'title' => 'Insurance & Guarantee',
                    'items' => array(
                        'What insurance (assurance emprunteur) is required?',
                        'Can I use external insurance (délégation d\'assurance)?',
                        'Is it a mortgage (hypothèque) or surety bond (caution)?',
                        'What are the guarantee fees?',
                    ),
                ),
            ),
        );
        
        // Section 4: Key French Terms
        $content['sections'][] = array(
            'title' => ($years_until_payoff > 0 ? '4' : '3') . '. Key French Mortgage Terms',
            'intro' => 'Understanding these terms will help you read loan documents.',
            'terms' => array(
                array('french' => 'Prêt immobilier', 'english' => 'Mortgage loan'),
                array('french' => 'Taux nominal', 'english' => 'Nominal interest rate'),
                array('french' => 'TAEG', 'english' => 'Annual Percentage Rate (APR)'),
                array('french' => 'Mensualité', 'english' => 'Monthly payment'),
                array('french' => 'IRA', 'english' => 'Early repayment penalties'),
                array('french' => 'Remboursement anticipé', 'english' => 'Early repayment'),
                array('french' => 'Assurance emprunteur', 'english' => 'Borrower insurance'),
                array('french' => 'Délégation d\'assurance', 'english' => 'External insurance option'),
                array('french' => 'Hypothèque', 'english' => 'Mortgage (traditional lien)'),
                array('french' => 'Caution', 'english' => 'Surety bond'),
                array('french' => 'Frais de dossier', 'english' => 'Application fees'),
                array('french' => 'Offre de prêt', 'english' => 'Loan offer'),
                array('french' => 'Délai de réflexion', 'english' => 'Cooling-off period (10 days)'),
                array('french' => 'Notaire', 'english' => 'Notary'),
            ),
        );
        
        // Section 5: Comparison Worksheet
        $content['sections'][] = array(
            'title' => ($years_until_payoff > 0 ? '5' : '4') . '. Offer Comparison Worksheet',
            'intro' => 'Use this table to compare offers side-by-side.',
            'worksheet' => array(
                'columns' => array('Bank A', 'Bank B', 'Bank C'),
                'rows' => array(
                    'Bank Name',
                    'Nominal Rate',
                    'TAEG (APR)',
                    'Monthly Payment',
                    'IRA Penalty Terms',
                    'Application Fees',
                    'Insurance Cost',
                    'Guarantee Type',
                    'Guarantee Fees',
                    'Total Upfront Costs',
                    'Your Notes',
                ),
            ),
        );
        
        // Section 6: Decision Framework
        $content['sections'][] = array(
            'title' => ($years_until_payoff > 0 ? '6' : '5') . '. Decision Framework',
            'scenarios' => array(
                array(
                    'title' => 'Scenario A: Strong Offers',
                    'type' => 'success',
                    'conditions' => 'Multiple competitive offers, rates at target or below, IRA flexibility',
                    'action' => 'Accept best offer and proceed confidently',
                ),
                array(
                    'title' => 'Scenario B: Acceptable Offers',
                    'type' => 'warning',
                    'conditions' => 'Rates slightly high, standard IRA terms, limited offers',
                    'action' => 'Push back and ask broker to renegotiate or submit to more banks',
                ),
                array(
                    'title' => 'Scenario C: Poor Offers',
                    'type' => 'critical',
                    'conditions' => 'Only 1 offer, rates above target, no IRA flexibility',
                    'action' => 'Give broker ONE chance to improve, then consider alternatives',
                ),
            ),
        );
        
        return array(
            'type' => 'french-mortgages',
            'title' => 'French Mortgage Evaluation Guide',
            'content' => $content,
            'meta' => array(
                'loan_amount' => $loan_amount,
                'purchase_price' => $purchase_price,
                'ltv' => $ltv,
                'generated' => date('Y-m-d H:i:s'),
            ),
        );
    }

    /**
     * Generate Pet Relocation Guide
     */
    private function generate_pet_guide($answers, $profile) {
        $pet_type_raw = $answers['pet_type'] ?? 'dog';
        // Handle array from multi_choice
        if (is_array($pet_type_raw)) {
            $pet_type = implode(', ', $pet_type_raw);
            $pet_type_display = ucwords(str_replace('_', ' ', $pet_type));
        } else {
            $pet_type = $pet_type_raw;
            $pet_type_display = ucfirst($pet_type);
        }
        
        $travel_method = $answers['travel_method'] ?? 'flying_cargo';
        $microchipped = $answers['microchipped'] ?? 'no';
        $rabies_status = $answers['rabies_status'] ?? 'unsure';
        $timeline = $answers['move_timeline'] ?? '3_6_months';
        $pet_count = $answers['pet_count'] ?? '1';
        
        // Determine pet label
        $pet_label = 'Pet';
        if (is_array($pet_type_raw)) {
            if (in_array('dog', $pet_type_raw) && in_array('cat', $pet_type_raw)) {
                $pet_label = 'Dog & Cat';
            } elseif (in_array('both', $pet_type_raw)) {
                $pet_label = 'Dog & Cat';
            } elseif (in_array('dog', $pet_type_raw)) {
                $pet_label = 'Dog';
            } elseif (in_array('cat', $pet_type_raw)) {
                $pet_label = 'Cat';
            }
        } else {
            $pet_label = ucfirst($pet_type_raw);
        }
        
        $content = array(
            'title' => 'Pet Relocation Guide to France',
            'subtitle' => 'Personalized for Your ' . $pet_label,
            'date' => date('F j, Y'),
            'sections' => array(),
        );
        
        // Section 1: Requirements
        $content['sections'][] = array(
            'title' => '1. EU Entry Requirements for Pets',
            'intro' => 'To bring your pet to France, you must meet these mandatory requirements:',
            'requirements' => array(
                array(
                    'title' => 'ISO Microchip (15-digit)',
                    'status' => ($microchipped === 'yes_iso') ? 'complete' : 'needed',
                    'details' => 'Must be ISO 11784/11785 compliant 15-digit microchip. CRITICAL: Must be implanted BEFORE rabies vaccination or vaccination is invalid.',
                ),
                array(
                    'title' => 'Rabies Vaccination',
                    'status' => ($rabies_status === 'current') ? 'complete' : 'needed',
                    'details' => 'Must be administered at least 21 days before travel. Vaccine must be approved and administered by licensed veterinarian. Valid for up to 3 years depending on vaccine used.',
                ),
                array(
                    'title' => 'EU Health Certificate (APHIS Form 7001)',
                    'status' => 'needed',
                    'details' => 'Must be issued by USDA-accredited veterinarian within 10 days of travel, then endorsed by USDA APHIS. Valid for 10 days for entry and 4 months for travel within EU.',
                ),
            ),
        );
        
        // Section 2: Timeline
        $timeline_items = array();
        
        if ($microchipped !== 'yes_iso') {
            $timeline_items[] = array('time' => '4+ months before', 'task' => 'Get ISO 15-digit microchip implanted at your vet');
        }
        if ($rabies_status !== 'current') {
            $timeline_items[] = array('time' => '4+ months before', 'task' => 'Get rabies vaccination (must be AFTER microchip implantation)');
        }
        $timeline_items[] = array('time' => '30 days before', 'task' => 'Confirm all vaccinations are current and microchip is registered');
        $timeline_items[] = array('time' => '21+ days before', 'task' => 'Ensure rabies vaccination is at least 21 days old (EU requirement)');
        
        if (strpos($travel_method, 'flying') !== false) {
            $timeline_items[] = array('time' => '4-6 weeks before', 'task' => 'Contact airline about pet policy, book pet on flight');
            $timeline_items[] = array('time' => '2-3 weeks before', 'task' => 'Purchase airline-approved carrier/crate if needed');
        }
        
        $timeline_items[] = array('time' => '10 days before', 'task' => 'Visit USDA-accredited vet for health examination');
        $timeline_items[] = array('time' => '10 days before', 'task' => 'Vet completes EU Health Certificate (APHIS 7001)');
        $timeline_items[] = array('time' => '7-10 days before', 'task' => 'Submit certificate to USDA APHIS for endorsement');
        $timeline_items[] = array('time' => '2-3 days before', 'task' => 'Receive endorsed certificate from USDA (or use VEHCS for faster processing)');
        $timeline_items[] = array('time' => 'Travel day', 'task' => 'Carry all original documents with you (not in checked luggage)');
        
        $content['sections'][] = array(
            'title' => '2. Your Personalized Timeline',
            'intro' => 'Based on your situation, here\'s your step-by-step timeline:',
            'timeline' => $timeline_items,
        );
        
        // Section 3: Travel Method Specific Info
        $travel_info = array();
        if ($travel_method === 'flying_cabin') {
            $travel_info = array(
                'title' => '3. Flying with Pet in Cabin',
                'intro' => 'Since you plan to fly with your pet in the cabin, here\'s what you need to know:',
                'subsections' => array(
                    array(
                        'title' => 'Cabin Requirements',
                        'type' => 'info',
                        'items' => array(
                            'Pet + carrier must typically weigh under 8kg (17.6 lbs) total',
                            'Carrier must fit under seat in front of you',
                            'Carrier dimensions typically max: 46cm x 28cm x 24cm',
                            'Pet must remain in carrier throughout flight',
                            'Book early - cabin pet spots are very limited (often 1-2 per cabin)',
                        ),
                    ),
                    array(
                        'title' => 'Airline Fees (Approximate)',
                        'type' => 'warning',
                        'items' => array(
                            'Air France: $200 each way',
                            'United: $125 each way (domestic connections may differ)',
                            'Delta: $200 each way',
                            'American: $150 each way',
                            'Always confirm current fees when booking',
                        ),
                    ),
                ),
            );
        } elseif ($travel_method === 'flying_cargo') {
            $travel_info = array(
                'title' => '3. Flying with Pet in Cargo',
                'intro' => 'Since you plan to fly with your pet in cargo, here\'s what you need to know:',
                'subsections' => array(
                    array(
                        'title' => 'Cargo Requirements',
                        'type' => 'info',
                        'items' => array(
                            'IATA-approved hard-sided crate required',
                            'Crate must be large enough for pet to stand, turn around, and lie down',
                            'Attach water dish inside (freeze water before flight)',
                            'No sedatives - airlines prohibit sedated animals',
                            'Temperature restrictions apply (some airlines won\'t fly pets in extreme heat/cold)',
                        ),
                    ),
                    array(
                        'title' => 'Important Warnings',
                        'type' => 'critical',
                        'items' => array(
                            'Brachycephalic breeds (pugs, bulldogs, Persian cats) may be restricted or banned',
                            'Check airline\'s breed restrictions before booking',
                            'Cargo pet fees range $200-$500+ each way',
                            'Book well in advance - cargo pet spots are limited',
                        ),
                    ),
                ),
            );
        } elseif ($travel_method === 'pet_transport') {
            $travel_info = array(
                'title' => '3. Using a Pet Transport Service',
                'intro' => 'Professional pet transport services handle the logistics for you:',
                'subsections' => array(
                    array(
                        'title' => 'Benefits',
                        'type' => 'success',
                        'items' => array(
                            'They handle all paperwork and USDA endorsement',
                            'Door-to-door service available',
                            'Experience with airline requirements',
                            'Less stressful for you (and often for pet)',
                        ),
                    ),
                    array(
                        'title' => 'Considerations',
                        'type' => 'warning',
                        'items' => array(
                            'Costs range $2,000-$5,000+ depending on service level',
                            'Research companies thoroughly - check reviews',
                            'Reputable services: Air Animal, Pet Relocation, Happy Tails Travel',
                            'Get quotes from multiple providers',
                        ),
                    ),
                ),
            );
        }
        
        if (!empty($travel_info)) {
            $content['sections'][] = $travel_info;
        }
        
        // Section 4: Document Checklist
        $content['sections'][] = array(
            'title' => (empty($travel_info) ? '3' : '4') . '. Document Checklist',
            'intro' => 'Make sure you have all these documents ready for travel:',
            'checklists' => array(
                array(
                    'title' => 'Required Documents',
                    'items' => array(
                        'EU Health Certificate (APHIS 7001) - USDA endorsed original',
                        'Rabies vaccination certificate (showing date and vaccine details)',
                        'Microchip documentation (showing 15-digit ISO number)',
                        'Proof of microchip implantation date (must be before rabies vaccine)',
                        'Your passport and travel documents',
                        'Pet\'s photo (in case documents need verification)',
                    ),
                ),
            ),
        );
        
        // Section 5: Arrival in France
        $content['sections'][] = array(
            'title' => (empty($travel_info) ? '4' : '5') . '. Arriving in France',
            'intro' => 'What to expect when you arrive:',
            'subsections' => array(
                array(
                    'title' => 'At the Airport',
                    'type' => 'info',
                    'items' => array(
                        'Proceed through customs with pet',
                        'Have all documents ready for inspection',
                        'Border officials may scan microchip to verify',
                        'Usually a quick process if documents are in order',
                    ),
                ),
                array(
                    'title' => 'After Arrival',
                    'type' => 'success',
                    'items' => array(
                        'Register with local French veterinarian within first few weeks',
                        'Get French pet passport (Passeport Européen pour Animaux) for future EU travel',
                        'Update microchip registration with French address',
                        'Consider pet insurance in France (assurance animaux)',
                    ),
                ),
            ),
        );
        
        // Section 6: Useful Contacts
        $content['sections'][] = array(
            'title' => (empty($travel_info) ? '5' : '6') . '. Useful Contacts & Resources',
            'intro' => 'Key contacts for your pet relocation:',
            'subsections' => array(
                array(
                    'title' => 'US Resources',
                    'type' => 'info',
                    'items' => array(
                        'USDA APHIS Pet Travel: aphis.usda.gov/aphis/pet-travel',
                        'VEHCS (Veterinary Export Health Certification System): for electronic processing',
                        'Find USDA-accredited vet: aphis.usda.gov/aphis/ourfocus/animalhealth',
                    ),
                ),
                array(
                    'title' => 'French Resources',
                    'type' => 'info',
                    'items' => array(
                        'French Customs (Douane): douane.gouv.fr',
                        'I-CAD (French microchip registry): i-cad.fr',
                        'SPA (French animal welfare): la-spa.fr',
                    ),
                ),
            ),
        );
        
        return array(
            'type' => 'pet-relocation',
            'title' => 'Pet Relocation Guide',
            'content' => $content,
            'meta' => array(
                'pet_type' => $pet_type,
                'travel_method' => $travel_method,
                'generated' => date('Y-m-d H:i:s'),
            ),
        );
    }

    /**
     * Generate Apostille Guide
     */
    private function generate_apostille_guide($answers, $profile) {
        $documents = $answers['documents_needed'] ?? array('birth_cert');
        // Handle comma-separated string from chat interface
        if (is_string($documents)) {
            $documents = array_map('trim', explode(',', $documents));
        }
        // Ensure documents is an array
        if (!is_array($documents)) {
            $documents = array($documents);
        }
        $urgency = $answers['urgency'] ?? 'flexible';
        
        // Get states from answers first, then profile
        $birth_state = $answers['birth_state'] ?? $profile['birth_state'] ?? '';
        $marriage_state = $answers['marriage_state'] ?? $profile['marriage_state'] ?? '';
        
        // Build states needed
        $states_needed = array();
        
        if (in_array('birth_cert', $documents) && !empty($birth_state)) {
            if (!isset($states_needed[$birth_state])) {
                $states_needed[$birth_state] = array();
            }
            $states_needed[$birth_state][] = 'Birth Certificate';
        }
        if (in_array('marriage_cert', $documents) && !empty($marriage_state)) {
            if (!isset($states_needed[$marriage_state])) {
                $states_needed[$marriage_state] = array();
            }
            $states_needed[$marriage_state][] = 'Marriage Certificate';
        }
        
        $guides_instance = FRAMT_Guides::get_instance();
        $state_info = $guides_instance->get_apostille_guide(get_current_user_id());
        
        $content = array(
            'title' => 'Apostille Guide',
            'subtitle' => 'Personalized for Your Documents',
            'date' => date('F j, Y'),
            'sections' => array(),
        );
        
        // What is an apostille
        $content['sections'][] = array(
            'title' => '1. What is an Apostille?',
            'intro' => 'An apostille is an official certificate that authenticates the origin of a public document for use in another country.',
            'explanation' => 'France is part of the Hague Apostille Convention (1961). Without an apostille, French authorities cannot verify that your US document is legitimate.',
        );
        
        // State-specific instructions
        if (!empty($states_needed)) {
            $state_sections = array();
            foreach ($states_needed as $state => $docs) {
                $info = $state_info['state_info'][$state] ?? null;
                if ($info) {
                    $state_sections[] = array(
                        'state' => $state,
                        'documents' => $docs,
                        'agency' => $info['agency'],
                        'method' => $info['method'],
                        'cost' => $info['cost'],
                        'time' => $info['time'],
                        'url' => $info['url'],
                    );
                }
            }
            
            $content['sections'][] = array(
                'title' => '2. Your State-Specific Instructions',
                'states' => $state_sections,
            );
        }
        
        return array(
            'type' => 'apostille',
            'title' => 'Apostille Guide',
            'content' => $content,
            'meta' => array(
                'documents' => $documents,
                'generated' => date('Y-m-d H:i:s'),
            ),
        );
    }

    /**
     * Generate Bank Ratings Guide
     */
    private function generate_bank_guide($answers, $profile) {
        $needs = $answers['banking_needs'] ?? array('daily');
        // Handle comma-separated string from chat interface
        if (is_string($needs)) {
            $needs = array_map('trim', explode(',', $needs));
        }
        // Ensure needs is an array
        if (!is_array($needs)) {
            $needs = array($needs);
        }
        $english = $answers['english_support'] ?? 'preferred';
        $online = $answers['online_banking'] ?? 'important';
        
        $banks = array(
            array(
                'name' => 'BNP Paribas',
                'rating' => 4.5,
                'english' => true,
                'online' => true,
                'mortgage' => true,
                'expat_friendly' => true,
                'pros' => array('Largest French bank', 'English services', 'International presence'),
                'cons' => array('Higher fees', 'Can be bureaucratic'),
            ),
            array(
                'name' => 'Crédit Agricole',
                'rating' => 4.0,
                'english' => false,
                'online' => true,
                'mortgage' => true,
                'expat_friendly' => true,
                'pros' => array('Good mortgage rates', 'Strong regional presence'),
                'cons' => array('Limited English', 'Varies by region'),
            ),
            array(
                'name' => 'Société Générale',
                'rating' => 4.0,
                'english' => true,
                'online' => true,
                'mortgage' => true,
                'expat_friendly' => true,
                'pros' => array('English services available', 'Good online banking'),
                'cons' => array('Moderate fees'),
            ),
            array(
                'name' => 'Boursorama',
                'rating' => 4.5,
                'english' => false,
                'online' => true,
                'mortgage' => false,
                'expat_friendly' => false,
                'pros' => array('No account fees', 'Best online banking'),
                'cons' => array('French only', 'Must already be resident'),
            ),
        );
        
        // Filter and rank banks based on needs
        $ranked_banks = array();
        foreach ($banks as $bank) {
            $score = $bank['rating'];
            
            if ($english === 'essential' && !$bank['english']) {
                $score -= 2;
            }
            if ($online === 'essential' && !$bank['online']) {
                $score -= 1;
            }
            if (in_array('mortgage', $needs) && !$bank['mortgage']) {
                $score -= 1.5;
            }
            
            $bank['final_score'] = $score;
            $ranked_banks[] = $bank;
        }
        
        usort($ranked_banks, function($a, $b) {
            return $b['final_score'] <=> $a['final_score'];
        });
        
        $content = array(
            'title' => 'French Bank Ratings Guide',
            'subtitle' => 'Personalized Recommendations',
            'date' => date('F j, Y'),
            'sections' => array(
                array(
                    'title' => '1. Your Top Recommended Banks',
                    'intro' => 'Based on your needs, here are the best banks for you:',
                    'banks' => array_slice($ranked_banks, 0, 3),
                ),
            ),
        );
        
        return array(
            'type' => 'bank-ratings',
            'title' => 'French Bank Ratings Guide',
            'content' => $content,
            'meta' => array(
                'needs' => $needs,
                'generated' => date('Y-m-d H:i:s'),
            ),
        );
    }

    /**
     * Parse currency string to number
     */
    private function parse_currency($value) {
        return intval(preg_replace('/[^0-9]/', '', $value));
    }

    /**
     * Convert guide to Word document format
     */
    public function to_word_document($guide_data) {
        $content = $guide_data['content'];
        
        $html = '<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
<head>
<meta charset="utf-8">
<meta name="ProgId" content="Word.Document">
<style>
body { font-family: "Calibri", sans-serif; font-size: 11pt; line-height: 1.5; margin: 1in; color: #333; }
h1 { font-size: 24pt; color: #1a1a2e; margin-bottom: 5pt; border-bottom: 3px solid #c9a227; padding-bottom: 10pt; }
h2 { font-size: 16pt; color: #16213e; margin-top: 20pt; margin-bottom: 10pt; }
h3 { font-size: 13pt; color: #333; margin-top: 15pt; margin-bottom: 8pt; }
.subtitle { font-size: 12pt; color: #666; margin-bottom: 5pt; }
.date { font-size: 10pt; color: #999; margin-bottom: 20pt; }
.summary-box { background: #f5f7fa; border: 1px solid #ddd; padding: 15pt; margin: 15pt 0; }
.summary-item { margin: 5pt 0; }
.success-box { background: #f0f9f0; border-left: 4px solid #4a5d4a; padding: 10pt 15pt; margin: 10pt 0; }
.warning-box { background: #fffbf0; border-left: 4px solid #c9a227; padding: 10pt 15pt; margin: 10pt 0; }
.critical-box { background: #fdf6f6; border-left: 4px solid #722f37; padding: 10pt 15pt; margin: 10pt 0; }
.box-title { font-weight: bold; margin-bottom: 8pt; }
ul { margin: 10pt 0 10pt 20pt; }
li { margin: 5pt 0; }
table { width: 100%; border-collapse: collapse; margin: 15pt 0; }
th { background: #16213e; color: white; padding: 10pt; text-align: left; font-size: 10pt; }
td { padding: 8pt; border: 1px solid #ddd; font-size: 10pt; }
tr:nth-child(even) { background: #f9f9f9; }
.checklist { margin: 10pt 0; }
.checklist-item { margin: 8pt 0; padding-left: 25pt; position: relative; }
.checklist-item:before { content: "☐"; position: absolute; left: 0; }
.tip { background: #e8f4fd; border: 1px solid #b8d4e8; padding: 10pt; margin: 10pt 0; font-style: italic; }
.highlight { background: #fff3cd; padding: 3pt 6pt; font-weight: bold; }
</style>
</head>
<body>';
        
        // Title
        $html .= '<h1>' . esc_html($content['title']) . '</h1>';
        if (!empty($content['subtitle'])) {
            $html .= '<p class="subtitle">' . esc_html($content['subtitle']) . '</p>';
        }
        $html .= '<p class="date">' . esc_html($content['date']) . '</p>';
        
        // Summary box
        if (!empty($content['summary'])) {
            $html .= '<div class="summary-box">';
            foreach ($content['summary'] as $label => $value) {
                $label_display = ucwords(str_replace('_', ' ', $label));
                $html .= '<div class="summary-item"><strong>' . esc_html($label_display) . ':</strong> ' . esc_html($value) . '</div>';
            }
            $html .= '</div>';
        }
        
        // Sections
        foreach ($content['sections'] as $section) {
            $html .= '<h2>' . esc_html($section['title']) . '</h2>';
            
            if (!empty($section['intro'])) {
                $html .= '<p>' . esc_html($section['intro']) . '</p>';
            }
            
            // Subsections with colored boxes
            if (!empty($section['subsections'])) {
                foreach ($section['subsections'] as $sub) {
                    $box_class = ($sub['type'] ?? 'info') . '-box';
                    $html .= '<div class="' . $box_class . '">';
                    $html .= '<div class="box-title">' . esc_html($sub['title']) . '</div>';
                    if (!empty($sub['items'])) {
                        $html .= '<ul>';
                        foreach ($sub['items'] as $item) {
                            $html .= '<li>' . esc_html($item) . '</li>';
                        }
                        $html .= '</ul>';
                    }
                    $html .= '</div>';
                }
            }
            
            // Calculations
            if (!empty($section['calculations'])) {
                $html .= '<table>';
                foreach ($section['calculations'] as $calc) {
                    $value_class = !empty($calc['highlight']) ? 'highlight' : '';
                    $html .= '<tr>';
                    $html .= '<td><strong>' . esc_html($calc['label']) . '</strong></td>';
                    $html .= '<td class="' . $value_class . '">' . esc_html($calc['value']) . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</table>';
                
                if (!empty($section['tip'])) {
                    $html .= '<div class="tip">' . esc_html($section['tip']) . '</div>';
                }
            }
            
            // Checklists
            if (!empty($section['checklists'])) {
                foreach ($section['checklists'] as $checklist) {
                    $html .= '<h3>' . esc_html($checklist['title']) . '</h3>';
                    $html .= '<div class="checklist">';
                    foreach ($checklist['items'] as $item) {
                        $html .= '<div class="checklist-item">' . esc_html($item) . '</div>';
                    }
                    $html .= '</div>';
                }
            }
            
            // Terms table
            if (!empty($section['terms'])) {
                $html .= '<table>';
                $html .= '<tr><th>French Term</th><th>English Translation</th></tr>';
                foreach ($section['terms'] as $term) {
                    $html .= '<tr>';
                    $html .= '<td><strong>' . esc_html($term['french']) . '</strong></td>';
                    $html .= '<td>' . esc_html($term['english']) . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</table>';
            }
            
            // Worksheet
            if (!empty($section['worksheet'])) {
                $html .= '<table>';
                $html .= '<tr><th>Item</th>';
                foreach ($section['worksheet']['columns'] as $col) {
                    $html .= '<th>' . esc_html($col) . '</th>';
                }
                $html .= '</tr>';
                foreach ($section['worksheet']['rows'] as $row) {
                    $html .= '<tr><td><strong>' . esc_html($row) . '</strong></td>';
                    foreach ($section['worksheet']['columns'] as $col) {
                        $html .= '<td></td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</table>';
            }
            
            // Scenarios
            if (!empty($section['scenarios'])) {
                foreach ($section['scenarios'] as $scenario) {
                    $box_class = ($scenario['type'] ?? 'info') . '-box';
                    $html .= '<div class="' . $box_class . '">';
                    $html .= '<div class="box-title">' . esc_html($scenario['title']) . '</div>';
                    $html .= '<p><strong>When:</strong> ' . esc_html($scenario['conditions']) . '</p>';
                    $html .= '<p><strong>Action:</strong> ' . esc_html($scenario['action']) . '</p>';
                    $html .= '</div>';
                }
            }
            
            // Timeline
            if (!empty($section['timeline'])) {
                foreach ($section['timeline'] as $item) {
                    $html .= '<p><strong>' . esc_html($item['time']) . ':</strong> ' . esc_html($item['task']) . '</p>';
                }
            }
            
            // Requirements
            if (!empty($section['requirements'])) {
                foreach ($section['requirements'] as $req) {
                    $status_icon = ($req['status'] === 'complete') ? '✓' : '○';
                    $html .= '<p>' . $status_icon . ' <strong>' . esc_html($req['title']) . '</strong>';
                    if ($req['status'] !== 'complete') {
                        $html .= ' <em>(Needed)</em>';
                    }
                    $html .= '</p>';
                    $html .= '<p style="margin-left: 20pt; color: #666;">' . esc_html($req['details']) . '</p>';
                }
            }
            
            // Banks
            if (!empty($section['banks'])) {
                foreach ($section['banks'] as $i => $bank) {
                    $rank = $i + 1;
                    $html .= '<div class="summary-box">';
                    $html .= '<h3>#' . $rank . ' ' . esc_html($bank['name']) . '</h3>';
                    $html .= '<p><strong>Rating:</strong> ' . str_repeat('★', floor($bank['rating'])) . ' ' . $bank['rating'] . '/5</p>';
                    $html .= '<p><strong>Pros:</strong> ' . esc_html(implode(', ', $bank['pros'])) . '</p>';
                    $html .= '<p><strong>Cons:</strong> ' . esc_html(implode(', ', $bank['cons'])) . '</p>';
                    $html .= '</div>';
                }
            }
        }
        
        $html .= '</body></html>';
        
        return $html;
    }
}
