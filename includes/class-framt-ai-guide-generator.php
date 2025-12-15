<?php
/**
 * AI-Powered Guide Generator
 *
 * Uses Claude API to generate personalized, comprehensive guides.
 *
 * @package FRA_Member_Tools
 * @since 1.0.30
 */

if (!defined('ABSPATH')) {
    exit;
}

class FRAMT_AI_Guide_Generator {

    private static $instance = null;
    
    const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    const MODEL = 'claude-sonnet-4-20250514';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Get API key from main plugin
     */
    private function get_api_key() {
        return get_option('fra_api_key', '');
    }

    /**
     * Get visa application guide data from knowledge base
     * This data is updated weekly by the main plugin's scraper
     */
    private function get_visa_guide_kb_data() {
        $knowledge_base = get_option('fra_knowledge_base', array());
        return $knowledge_base['visa_application_guide'] ?? array();
    }

    /**
     * Format KB fees data into a string for the AI prompt
     */
    private function format_kb_fees_for_prompt($kb_data) {
        $fees = $kb_data['fees_and_costs'] ?? array();
        $docs = $kb_data['document_requirements'] ?? array();
        $financial = $docs['financial_thresholds'] ?? array();
        $tls = $kb_data['tlscontact_info'] ?? array();

        $lines = array();

        // Visa fees
        $visa_fee = $fees['visa_application_fee'] ?? '€99';
        $tls_fee = $fees['tlscontact_service_fee'] ?? $tls['service_fee'] ?? '€43';
        $ofii_fee = $fees['ofii_validation_tax'] ?? '€225';

        $lines[] = "- Visa Application Fee: {$visa_fee} per person";
        $lines[] = "- TLScontact Service Fee: {$tls_fee} per person";
        $lines[] = "- OFII Validation Tax: {$ofii_fee} per person";

        // Financial thresholds
        $monthly_individual = $financial['individual_monthly'] ?? '€1,450';
        $monthly_couple = $financial['couple_monthly'] ?? '€2,175';
        $annual_individual = $financial['annual_individual'] ?? '€17,400';
        $annual_couple = $financial['annual_couple'] ?? '€26,100';

        $lines[] = "- Financial Requirement (Individual): {$monthly_individual}/month or {$annual_individual}/year";
        $lines[] = "- Financial Requirement (Couple): {$monthly_couple}/month or {$annual_couple}/year";

        // Other estimates
        $translation_est = $fees['certified_translation_estimate'] ?? '€30-50 per document';
        $insurance_est = $fees['health_insurance_annual_estimate'] ?? '€600-1,200';

        $lines[] = "- Certified Translation: {$translation_est}";
        $lines[] = "- Health Insurance (12 months): {$insurance_est}";

        // TLScontact note
        $tls_note = $tls['note'] ?? 'TLScontact handles visa appointments in the US';
        $lines[] = "- Note: {$tls_note}";

        return implode("\n", $lines);
    }

    /**
     * Check if API is configured
     */
    public function is_configured() {
        $api_key = $this->get_api_key();
        $ai_enabled = get_option('fra_enable_ai', false);
        return !empty($api_key) && $ai_enabled;
    }

    /**
     * Generate a personalized guide using AI
     */
    public function generate_guide($guide_type, $answers, $profile) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('AI is not configured. Please enable AI in the main plugin settings.', 'fra-member-tools'));
        }

        $prompt = $this->build_prompt($guide_type, $answers, $profile);
        
        if (is_wp_error($prompt)) {
            return $prompt;
        }

        $response = $this->call_api($prompt);
        
        if (is_wp_error($response)) {
            return $response;
        }

        return $this->format_response($guide_type, $response, $answers, $profile);
    }

    /**
     * Build the prompt for the AI
     */
    private function build_prompt($guide_type, $answers, $profile) {
        $user_name = '';
        $user = wp_get_current_user();
        if ($user) {
            $user_name = $user->first_name ?: $user->display_name;
        }

        $current_date = date('F j, Y');
        
        $prompts = array(
            'pet-relocation' => $this->build_pet_prompt($answers, $profile, $user_name, $current_date),
            'french-mortgages' => $this->build_mortgage_prompt($answers, $profile, $user_name, $current_date),
            'apostille' => $this->build_apostille_prompt($answers, $profile, $user_name, $current_date),
            'bank-ratings' => $this->build_bank_prompt($answers, $profile, $user_name, $current_date),
            'visa-application' => $this->build_visa_application_prompt($answers, $profile, $user_name, $current_date),
        );

        return $prompts[$guide_type] ?? new WP_Error('invalid_guide', __('Invalid guide type', 'fra-member-tools'));
    }

    /**
     * Build pet relocation guide prompt
     */
    private function build_pet_prompt($answers, $profile, $user_name, $current_date) {
        $pet_type = $answers['pet_type'] ?? $profile['has_pets'] ?? 'dog';
        if (is_array($pet_type)) {
            $pet_type = implode(' and ', $pet_type);
        }

        $pet_count = $answers['pet_count'] ?? '1';
        $travel_method = $answers['travel_method'] ?? 'flying_cargo';
        $microchipped = $answers['microchipped'] ?? 'no';
        $rabies_status = $answers['rabies_status'] ?? 'unsure';
        $move_timeline = $answers['move_timeline'] ?? $answers['move_date'] ?? $profile['target_move_date'] ?? '3_6_months';
        $departure_state = $answers['departure_state'] ?? $profile['current_state'] ?? '';
        $target_location = $profile['target_location'] ?? 'France';
        $pet_details = $profile['pet_details'] ?? '';
        
        $travel_descriptions = array(
            'flying_cabin' => 'flying with pet in the cabin',
            'flying_cargo' => 'flying with pet in cargo/hold',
            'driving' => 'driving to France (through UK/Europe)',
            'pet_transport' => 'using a professional pet transport service',
            'unsure' => 'not yet decided on travel method',
        );
        
        $timeline_descriptions = array(
            'under_30' => 'less than 30 days',
            '1_3_months' => '1-3 months',
            '3_6_months' => '3-6 months',
            '6_plus' => '6+ months',
        );
        
        $travel_desc = $travel_descriptions[$travel_method] ?? $travel_method;
        $timeline_desc = $timeline_descriptions[$move_timeline] ?? $move_timeline;
        
        return "You are an expert in international pet relocation, specifically helping Americans move their pets to France. Generate a comprehensive, personalized guide for this specific situation.

USER SITUATION:
- Name: {$user_name}
- Pet type: {$pet_type}" . ($pet_details ? " ({$pet_details})" : "") . "
- Number of pets: {$pet_count}
- Travel method: {$travel_desc}
- Departing from: " . ($departure_state ? $departure_state : "United States") . "
- Destination in France: {$target_location}
- Current microchip status: {$microchipped}
- Current rabies vaccination status: {$rabies_status}
- Timeline to move: {$timeline_desc}
- Current date: {$current_date}

Generate a detailed, personalized pet relocation guide that includes:

1. **EXECUTIVE SUMMARY** - A brief overview of what they need to do and their timeline urgency

2. **EU ENTRY REQUIREMENTS** - Explain the mandatory requirements:
   - ISO 15-digit microchip (must be implanted BEFORE rabies vaccination)
   - Rabies vaccination (at least 21 days before travel)
   - EU Health Certificate (APHIS Form 7001)
   - Mark which ones they've already completed based on their answers

3. **PERSONALIZED TIMELINE** - Create a specific timeline based on their move date and current status. Be specific about dates and deadlines.

4. **TRAVEL METHOD DETAILS** - Based on their chosen travel method, provide:
   - Current airline policies and fees (be specific with airline names and approximate costs)
   - Carrier/crate requirements
   - Booking tips and restrictions
   - Any breed-specific considerations

5. **STEP-BY-STEP PROCESS** - Detailed walkthrough:
   - Finding a USDA-accredited veterinarian
   - Getting the health certificate
   - USDA APHIS endorsement process
   - VEHCS electronic submission option
   - What to expect at French customs

6. **COSTS BREAKDOWN** - Estimated costs for:
   - Veterinary visits
   - Microchip (if needed)
   - Vaccinations
   - Health certificate
   - USDA endorsement
   - Airline fees
   - Total estimated cost

7. **DOCUMENT CHECKLIST** - Everything they need to have ready

8. **ARRIVAL IN FRANCE** - What to do after arriving:
   - Customs process
   - Registering with French vet
   - Getting French pet passport
   - Pet insurance in France

9. **EMERGENCY CONTACTS & RESOURCES** - Useful contacts and websites

Be specific, actionable, and personalized. Use their actual situation to give relevant advice. If they're flying in cabin, don't talk about cargo. If their microchip is already done, acknowledge that.

Format the response as clean HTML with these exact section headers. Use <h2> for main sections, <h3> for subsections, <ul>/<li> for lists, <p> for paragraphs, and <strong> for emphasis. Keep the tone professional but warm and reassuring.";
    }

    /**
     * Build mortgage guide prompt
     */
    private function build_mortgage_prompt($answers, $profile, $user_name, $current_date) {
        $purchase_price = $answers['purchase_price'] ?? '€500,000';
        $loan_amount = $answers['loan_amount'] ?? '€400,000';
        $target_rate = $answers['target_rate'] ?? 'unsure';
        $loan_term = $answers['loan_term'] ?? '20';
        $early_payoff = $answers['early_payoff'] ?? 'no';
        $early_payoff_year = $answers['early_payoff_year'] ?? '';
        $using_broker = $answers['using_broker'] ?? 'considering';
        $closing_timeline = $answers['closing_timeline'] ?? '3_4_months';

        // Use more profile data
        $target_location = $profile['target_location'] ?? 'France';
        $employment_status = $profile['employment_status'] ?? '';
        $income_sources = $profile['income_sources'] ?? array();
        $visa_type = $profile['visa_type'] ?? '';
        $applicants = $profile['applicants'] ?? '';
        $timeline = $profile['timeline'] ?? '';

        // Build profile context
        $profile_context = '';
        if ($employment_status) {
            $profile_context .= "- Employment status: {$employment_status}\n";
        }
        if (!empty($income_sources)) {
            $sources = is_array($income_sources) ? implode(', ', $income_sources) : $income_sources;
            $profile_context .= "- Income sources: {$sources}\n";
        }
        if ($visa_type && $visa_type !== 'undecided') {
            $profile_context .= "- Visa type: {$visa_type}\n";
        }
        if ($applicants) {
            $profile_context .= "- Applying with: {$applicants}\n";
        }

        return "You are an expert in French mortgages for American buyers. Generate a comprehensive, personalized mortgage evaluation guide.

USER SITUATION:
- Name: {$user_name}
- Purchase price: {$purchase_price}
- Loan amount needed: {$loan_amount}
- Target interest rate: {$target_rate}
- Loan term: {$loan_term} years
- Early payoff plans: {$early_payoff}
- Early payoff year: {$early_payoff_year}
- Using a broker: {$using_broker}
- Closing timeline: {$closing_timeline}
- Target location in France: {$target_location}
{$profile_context}- Current date: {$current_date}

Generate a detailed mortgage evaluation guide including:

1. **EXECUTIVE SUMMARY** - Overview of their situation and what to expect

2. **OFFER QUALITY BENCHMARKS** - Create three tiers:
   - EXCELLENT OFFER: What rates/terms they should aim for given their profile
   - ACCEPTABLE OFFER: Standard market terms
   - POOR OFFER: Red flags to reject
   Include specific rate ranges, fees, and terms for each tier

3. **EARLY REPAYMENT ANALYSIS** (if they plan early payoff):
   - Calculate estimated remaining balance at payoff date
   - IRA penalty calculations (6 months interest vs 3% rule)
   - Negotiation strategies to reduce/eliminate penalties

4. **CRITICAL QUESTIONS CHECKLIST** - Questions to ask banks/brokers about:
   - Interest rates and TAEG
   - Early repayment terms
   - Insurance requirements
   - Guarantee types
   - Fees

5. **FRENCH MORTGAGE TERMS GLOSSARY** - Key French terms they'll encounter

6. **BANK COMPARISON WORKSHEET** - Template for comparing offers

7. **DECISION FRAMEWORK** - How to evaluate and decide on offers

8. **NEGOTIATION TIPS** - How to leverage their profile for better terms

9. **TIMELINE & PROCESS** - What to expect from application to closing

Format as clean HTML with <h2> sections, <h3> subsections, tables where appropriate, and clear formatting. Be specific with numbers and calculations based on their inputs.";
    }

    /**
     * Build apostille guide prompt
     */
    private function build_apostille_prompt($answers, $profile, $user_name, $current_date) {
        $documents = $answers['documents_needed'] ?? array('birth_cert');
        // Handle comma-separated string from chat interface
        if (is_string($documents)) {
            $documents = array_map('trim', explode(',', $documents));
        }
        if (!is_array($documents)) {
            $documents = array($documents);
        }
        $urgency = $answers['urgency'] ?? 'flexible';
        
        // Use answers first, then fall back to profile
        $birth_state = $answers['birth_state'] ?? $profile['birth_state'] ?? '';
        $spouse_birth_state = $profile['spouse_birth_state'] ?? '';
        $marriage_state = $answers['marriage_state'] ?? $profile['marriage_state'] ?? '';
        
        $docs_list = implode(', ', $documents);
        
        return "You are an expert in US document authentication and apostilles for use in France. Generate a comprehensive, personalized apostille guide.

USER SITUATION:
- Name: {$user_name}
- Documents needed: {$docs_list}
- Urgency: {$urgency}
- Birth state: {$birth_state}
- Spouse birth state: {$spouse_birth_state}
- Marriage state: {$marriage_state}
- Current date: {$current_date}

Generate a detailed apostille guide including:

1. **WHAT IS AN APOSTILLE** - Clear explanation for someone unfamiliar

2. **YOUR DOCUMENTS** - For each document they need:
   - Which state to contact
   - Exact agency name and contact info
   - Current fees
   - Processing times (standard vs expedited)
   - Online vs mail options
   - Direct links to official websites

3. **STEP-BY-STEP PROCESS** - Detailed instructions for:
   - Obtaining certified copies if needed
   - Completing apostille applications
   - Payment methods
   - Mailing instructions
   - Tracking submissions

4. **TIMELINE** - Based on their urgency, create a realistic timeline

5. **COSTS BREAKDOWN** - Total estimated costs for all documents

6. **EXPEDITED OPTIONS** - If they need faster processing:
   - State expedited services
   - Private apostille services
   - Costs vs time tradeoffs

7. **COMMON MISTAKES TO AVOID** - Pitfalls that delay the process

8. **CHECKLIST** - Everything they need to gather and do

Format as clean HTML. Be specific with state agencies, fees, and timelines. Include actual website URLs where helpful.";
    }

    /**
     * Build bank ratings guide prompt
     */
    private function build_bank_prompt($answers, $profile, $user_name, $current_date) {
        $needs = $answers['banking_needs'] ?? array('daily');
        // Handle comma-separated string from chat interface
        if (is_string($needs)) {
            $needs = array_map('trim', explode(',', $needs));
        }
        if (!is_array($needs)) {
            $needs = array($needs);
        }
        $english_support = $answers['english_support'] ?? 'preferred';
        $online_banking = $answers['online_banking'] ?? 'important';

        // Use profile data for context
        $french_proficiency = $profile['french_proficiency'] ?? '';
        $employment_status = $profile['employment_status'] ?? '';
        $visa_type = $profile['visa_type'] ?? '';
        $target_location = $profile['target_location'] ?? 'France';
        $housing_plan = $profile['housing_plan'] ?? '';

        $needs_list = implode(', ', $needs);

        // Build additional context from profile
        $profile_context = '';
        if ($french_proficiency) {
            $profile_context .= "- French language level: {$french_proficiency}\n";
        }
        if ($employment_status) {
            $profile_context .= "- Employment status: {$employment_status}\n";
        }
        if ($visa_type && $visa_type !== 'undecided') {
            $profile_context .= "- Visa type: {$visa_type}\n";
        }
        if ($housing_plan) {
            $profile_context .= "- Housing plans: {$housing_plan}\n";
        }

        return "You are an expert in French banking for American expats. Generate a comprehensive, personalized bank comparison guide.

USER SITUATION:
- Name: {$user_name}
- Banking needs: {$needs_list}
- English support importance: {$english_support}
- Online banking importance: {$online_banking}
- Target location in France: {$target_location}
{$profile_context}- Current date: {$current_date}

Generate a detailed French bank comparison guide including:

1. **TOP RECOMMENDATIONS** - Based on their specific needs, rank the best 3-4 banks with:
   - Why it's good for them specifically
   - Pros and cons
   - English support availability
   - Online/mobile banking quality
   - Fees
   - Ease of opening as American

2. **DETAILED BANK PROFILES** - For major French banks:
   - BNP Paribas
   - Crédit Agricole
   - Société Générale
   - CIC / Crédit Mutuel
   - Boursorama (online)
   - Hello Bank (online)
   
   Include for each:
   - Best suited for
   - Account types
   - Fees structure
   - American-friendliness
   - English support
   - Mobile app quality

3. **OPENING AN ACCOUNT** - Process for Americans:
   - Required documents
   - Proof of address challenges
   - FATCA implications
   - Timeline expectations

4. **COSTS COMPARISON** - Table comparing:
   - Monthly fees
   - Card fees
   - International transfer fees
   - ATM fees

5. **SPECIAL CONSIDERATIONS FOR AMERICANS** - FATCA, tax reporting, limitations

6. **RECOMMENDATIONS BY NEED**:
   - Best for mortgages
   - Best for daily banking
   - Best online-only option
   - Best for English speakers

Format as clean HTML with comparison tables where appropriate. Be specific and actionable.";
    }

    /**
     * Build visa application guide prompt
     */
    private function build_visa_application_prompt($answers, $profile, $user_name, $current_date) {
        // Get user's spouse name if applicable
        $spouse_name = '';
        if (!empty($profile['spouse_legal_first_name'])) {
            $spouse_name = $profile['spouse_legal_first_name'];
            if (!empty($profile['spouse_legal_last_name'])) {
                $spouse_name .= ' ' . $profile['spouse_legal_last_name'];
            }
        }

        // Collect all relevant data from answers and profile
        $visa_type = $answers['visa_type'] ?? $profile['visa_type'] ?? 'visitor';
        $applicants = $answers['applicants'] ?? $profile['applicants'] ?? 'alone';
        $employment_status = $answers['employment_status'] ?? $profile['employment_status'] ?? '';
        $current_state = $answers['current_state'] ?? $profile['current_state'] ?? '';
        $target_move_date = $answers['target_move_date'] ?? $profile['target_move_date'] ?? '';
        $housing_situation = $answers['housing_situation'] ?? $profile['housing_plan'] ?? 'undecided';
        $target_location = $profile['target_location'] ?? 'France';

        // Additional profile data for context
        $income_sources = $profile['income_sources'] ?? array();
        $french_proficiency = $profile['french_proficiency'] ?? '';
        $num_children = $profile['num_children'] ?? '';
        $children_ages = $profile['children_ages'] ?? '';

        // Build visa type descriptions
        $visa_type_names = array(
            'visitor' => 'VLS-TS Visiteur (Long-Stay Visitor Visa)',
            'talent_passport' => 'Passeport Talent (Talent Passport)',
            'employee' => 'VLS-TS Salarié (Employee Visa)',
            'entrepreneur' => 'VLS-TS Entrepreneur/Profession Libérale',
            'student' => 'VLS-TS Étudiant (Student Visa)',
            'family' => 'Regroupement Familial (Family Reunification)',
            'spouse_french' => 'VLS-TS Conjoint de Français (Spouse of French National)',
            'retiree' => 'VLS-TS Visiteur (Retiree/Non-Working)',
        );

        $visa_type_name = $visa_type_names[$visa_type] ?? $visa_type;

        // Build applicants description
        $applicants_desc = 'applying alone';
        if ($applicants === 'spouse') {
            $applicants_desc = 'applying with spouse' . ($spouse_name ? " ({$spouse_name})" : '');
        } elseif ($applicants === 'spouse_kids') {
            $children_info = '';
            if ($num_children) {
                $children_info = " and {$num_children} child(ren)";
                if ($children_ages) {
                    $children_info .= " (ages: {$children_ages})";
                }
            }
            $applicants_desc = 'applying with spouse' . ($spouse_name ? " ({$spouse_name})" : '') . $children_info;
        }

        // Build employment description
        $employment_descriptions = array(
            'employed' => 'currently employed (W-2 employee)',
            'self_employed' => 'self-employed / business owner',
            'retired' => 'retired',
            'not_working' => 'not currently working (living on savings/investments)',
        );
        $employment_desc = $employment_descriptions[$employment_status] ?? $employment_status;

        // Build housing description
        $housing_descriptions = array(
            'already_own' => 'already owns property in France',
            'buying' => 'in process of purchasing property',
            'renting' => 'will rent accommodation',
            'staying_with' => 'staying with family/friends initially',
            'undecided' => 'housing not yet determined',
        );
        $housing_desc = $housing_descriptions[$housing_situation] ?? $housing_situation;

        // Build income sources string
        $income_desc = '';
        if (!empty($income_sources)) {
            $sources = is_array($income_sources) ? implode(', ', $income_sources) : $income_sources;
            $income_desc = "Income sources: {$sources}";
        }

        // Build the comprehensive prompt with visa-specific requirements
        $visa_specific_info = $this->get_visa_specific_requirements($visa_type);

        // Get current data from knowledge base (updated weekly)
        $kb_data = $this->get_visa_guide_kb_data();
        $current_fees = $this->format_kb_fees_for_prompt($kb_data);
        $kb_last_verified = $kb_data['lastVerified'] ?? 'December 2025';

        return "You are an expert French immigration attorney and visa consultant specializing in helping Americans relocate to France. Generate a comprehensive, personalized step-by-step visa application guide.

USER SITUATION:
- Name: {$user_name}
- Visa Type: {$visa_type_name}
- Application: {$applicants_desc}
- Employment Status: {$employment_desc}
- Current US State: " . ($current_state ?: 'Not specified') . "
- Target Move Date: " . ($target_move_date ?: 'Not specified') . "
- Housing in France: {$housing_desc}
- Target Location in France: {$target_location}
" . ($income_desc ? "- {$income_desc}\n" : "") . "
" . ($french_proficiency ? "- French Language Level: {$french_proficiency}\n" : "") . "
- Current Date: {$current_date}
- Data Last Verified: {$kb_last_verified}

{$visa_specific_info}

CURRENT FEES AND COSTS (verified {$kb_last_verified}):
{$current_fees}

Generate a detailed, personalized visa application guide following this EXACT structure:

1. **UNDERSTANDING YOUR VISA APPLICATION**
   - Explain what the {$visa_type_name} is and who it's for
   - Confirm this is the right visa for their situation
   - If applying with spouse/family, explain that EACH PERSON needs a SEPARATE, COMPLETE application
   - Key requirements and restrictions for this visa type

2. **YOUR SPECIFIC SITUATION**
   Create a comparison table (if applicable) showing:
   - Each applicant's details (name, application type, residency intent, income source, work status)
   - Any special notes for their specific circumstances
   - Warnings about employment/work restrictions

3. **APPLICATION TIMELINE**
   - KEY CONSTRAINT: Cannot apply more than 3 months before planned arrival
   - Create a specific timeline based on their target move date
   - Include: NOW actions, 1-2 months before, application window, appointment, decision timeline
   - Format as a clear table with TIMING | ACTION | DETAILS columns

4. **COMPLETE DOCUMENT CHECKLIST**
   - Emphasize: Need COMPLETE SETS for each applicant, ORIGINALS + PHOTOCOPIES
   - List documents they can share (marriage cert, property deed, joint accounts)

   For EACH applicant, create a detailed checklist with categories:
   **Identity & Travel Documents:**
   - Passport requirements (validity, blank pages, age)
   - Passport photos (exact specifications)
   - Application form (CERFA)
   - Birth certificate with apostille + French translation

   **Proof of Accommodation:**
   - Based on their housing situation

   **Financial Documentation:**
   - Bank statements (3-6 months)
   - Proof of income (specific to their employment status)
   - Tax returns
   - Attestation of financial independence (if applicable)
   - Include minimum income thresholds

   **Health Insurance:**
   - Minimum €30,000 coverage requirement
   - Must cover medical repatriation
   - Must cover entire visa duration (12 months)
   - Schengen zone validity

   **Marriage/Family Documentation (if applicable):**
   - Marriage certificate with apostille + translation
   - Children's birth certificates (if applicable)

   **Additional Forms:**
   - OFII form
   - Appointment confirmation

5. **STEP-BY-STEP APPLICATION PROCESS**

   **Phase 1: Online Application (France-Visas Portal)**
   - Website: france-visas.gouv.fr
   - Creating accounts (separate for each applicant)
   - Using the Visa Wizard to select correct visa type
   - Completing the form correctly for their employment status
   - Important: Save application numbers

   **Phase 2: Book TLScontact Appointments**
   - Website: visas-fr.tlscontact.com
   - NOTE: TLScontact replaced VFS Global as of April 18, 2025
   - List of US centers (Washington DC, New York, Boston, Atlanta, Miami, Chicago, Houston, Los Angeles, San Francisco, Seattle)
   - Tips for booking back-to-back appointments
   - Service fees (~€45 per person)

   **Phase 3: In-Person Appointment**
   - Arrival time
   - What NOT to bring (no large bags)
   - Document organization
   - Biometrics process
   - Visa fee: €99 per person
   - Passport retention notice

   **Phase 4: Wait & Receive Decision**
   - Processing time (2-4 weeks typical)
   - How to track status
   - Passport return by registered mail

6. **AFTER ARRIVAL: VISA VALIDATION**
   - MANDATORY: Must validate VLS-TS online within 3 months of arrival
   - Website: administration-etrangers-en-france.interieur.gouv.fr (ANEF platform)
   - Information needed for validation
   - Payment: €225 each (€200 OFII tax + €25 stamp duty)
   - Payment methods (online or tabac shop)
   - Possible medical examination

7. **TOTAL COSTS SUMMARY**
   Create a detailed costs table:
   | EXPENSE | PER PERSON | TOTAL (×number of applicants) |
   Include:
   - Visa application fee (€99)
   - TLScontact service fee (~€45)
   - OFII validation tax (€225)
   - Certified French translations (estimate)
   - Health insurance (12 months estimate)
   - ESTIMATED TOTAL

8. **PRO TIPS & INSIDER KNOWLEDGE**
   Include 8-10 specific tips such as:
   - Applications are truly individual (married couples = separate applications)
   - Bring extra copies
   - Original documents aren't kept
   - Photo specifications
   - Travel insurance vs health insurance
   - Appointment booking timing
   - Financial threshold flexibility for couples
   - Property deed as strong evidence
   - OFII validation is online only now
   - Any visa-specific tips

9. **CONTACT INFORMATION**
   - TLScontact Helpline
   - French Consulate Visa Email (based on their state/jurisdiction)
   - France-Visas Portal
   - TLScontact Portal

FORMAT AS CLEAN, PROFESSIONAL HTML using these specific classes for optimal rendering:

SECTION HEADERS:
- <h2>1. Section Title</h2> - Main numbered sections with blue styling
- <h3>Subsection Title</h3> - Blue subsection headers
- <h4>Sub-subsection</h4> - Regular sub-subsections

WARNING/ALERT BOXES (use for critical information):
<div class=\"warning-box\">
    <div class=\"warning-title\">⚠️ Important Warning</div>
    <p>Warning text here...</p>
</div>

INFO BOXES (use for helpful information):
<div class=\"info-box\">
    <p>ℹ️ Informational note here...</p>
</div>

SUCCESS/TIP BOXES:
<div class=\"success-box\">
    <p>✅ Success tip or confirmed information...</p>
</div>

PRO TIP BOXES:
<div class=\"tip-box\">
    <div class=\"tip-title\">💡 Pro Tip</div>
    <p>Insider knowledge here...</p>
</div>

PHASE HEADERS (for timeline sections):
<div class=\"phase-header\">Phase 1: Description</div>

TABLES:
- Use standard <table> with <thead>/<tbody> for all data tables
- Tables will auto-style with dark blue headers
- For comparison tables (multiple applicants): <table class=\"comparison-table\">
- For cost breakdowns: <table class=\"cost-table\">
- Use <tr class=\"total-row\"> for total row in cost tables

DOCUMENT CHECKLISTS:
<div class=\"checklist-section\">
    <div class=\"checklist-category\">Category Name</div>
    <div class=\"checklist-item\">Document item 1</div>
    <div class=\"checklist-item\">Document item 2</div>
</div>

GENERAL FORMATTING:
- <ul>/<li> for regular lists
- <strong> for emphasis
- ⚠️ emoji for critical warnings
- ✅ for confirmed items
- 📝 for notes
- ℹ️ for informational callouts

Be specific, actionable, and personalized to their exact situation. Include actual fees, timelines, and requirements current as of {$current_date}.";
    }

    /**
     * Get visa-specific requirements and notes
     */
    private function get_visa_specific_requirements($visa_type) {
        $requirements = array(
            'visitor' => "
VISA-SPECIFIC REQUIREMENTS FOR VLS-TS VISITEUR:
- This visa is for financially independent individuals who will NOT work in France
- Must prove sufficient financial resources (~€1,400/month individual, ~€2,100/month couple)
- Must sign attestation stating they will not engage in professional activity in France
- Can stay in France full-time (>183 days/year) or part-time
- Valid for 12 months, renewable as carte de séjour
- If applicant is employed in US but won't work IN France, they need carefully worded employment letter emphasizing US-based work only
",
            'talent_passport' => "
VISA-SPECIFIC REQUIREMENTS FOR PASSEPORT TALENT:
- Multiple categories: Company founder, Innovative project, Investor, Researcher, Artist, Skilled worker, etc.
- Each category has specific requirements
- Often requires sponsorship or project documentation
- May require minimum salary thresholds (varies by category)
- Usually valid for up to 4 years
- Allows work in France
- Family members can apply for 'Passeport talent famille'
- Ask which specific Talent Passport category they're applying for
",
            'employee' => "
VISA-SPECIFIC REQUIREMENTS FOR VLS-TS SALARIÉ:
- Requires work authorization (autorisation de travail) from French employer
- French employer must initiate the process through DIRECCTE
- Employment contract must meet French labor standards
- Minimum salary may apply
- Tied to specific employer
- Valid for duration of contract up to 12 months
- Renewable as carte de séjour
",
            'entrepreneur' => "
VISA-SPECIFIC REQUIREMENTS FOR ENTREPRENEUR VISA:
- For self-employed professionals or business creators
- Requires detailed business plan
- Must prove economic viability
- May require professional qualifications
- Proof of sufficient investment capital
- Registration with relevant French professional bodies
- May require approval from relevant chambers (commerce, crafts, etc.)
",
            'student' => "
VISA-SPECIFIC REQUIREMENTS FOR VLS-TS ÉTUDIANT:
- Requires acceptance from French educational institution
- Must register through Campus France (mandatory for most nationalities)
- Proof of sufficient funds for studies (~€615/month or ~€7,380/year)
- Proof of accommodation for first months
- May work up to 964 hours/year (about 20 hours/week)
- Health insurance through French social security system
- Academic transcripts and diplomas required
",
            'family' => "
VISA-SPECIFIC REQUIREMENTS FOR FAMILY REUNIFICATION:
- Family member in France must have legal residence for at least 18 months
- Sponsor must meet income requirements
- Sponsor must have adequate housing
- Complex process involving OFII and Prefecture
- Long processing times (6-12 months)
- Specific forms and procedures
",
            'spouse_french' => "
VISA-SPECIFIC REQUIREMENTS FOR SPOUSE OF FRENCH NATIONAL:
- Must be legally married to French citizen
- Marriage must be recognized in France
- No minimum duration of marriage required
- French spouse doesn't need to reside in France
- Transcription of foreign marriage certificate if married abroad
- Simpler process than family reunification
- Grants right to work immediately
",
            'retiree' => "
VISA-SPECIFIC REQUIREMENTS FOR RETIREE VISA:
- Same visa category as Visitor (VLS-TS Visiteur)
- Must prove retirement income (pension, investments, social security)
- Minimum ~€1,400/month individual, ~€2,100/month couple
- Must sign attestation of no professional activity
- Proof of stable, ongoing income
- Valid for 12 months, renewable
- Consider French tax implications
",
        );

        return $requirements[$visa_type] ?? $requirements['visitor'];
    }

    /**
     * Call the Claude API
     */
    private function call_api($prompt) {
        $api_key = $this->get_api_key();

        $body = array(
            'model' => self::MODEL,
            'max_tokens' => 4096,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
        );

        $response = wp_remote_post(self::API_ENDPOINT, array(
            'timeout' => 120,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => json_encode($body),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code !== 200) {
            $error_message = $data['error']['message'] ?? __('API request failed', 'fra-member-tools');
            return new WP_Error('api_error', $error_message);
        }

        if (!isset($data['content'][0]['text'])) {
            return new WP_Error('invalid_response', __('Invalid API response', 'fra-member-tools'));
        }

        return $data['content'][0]['text'];
    }

    /**
     * Format the AI response into a guide structure
     */
    private function format_response($guide_type, $ai_content, $answers, $profile) {
        $user = wp_get_current_user();
        $user_name = $user->first_name ?: $user->display_name ?: 'Member';
        
        $titles = array(
            'pet-relocation' => 'Pet Relocation Guide to France',
            'french-mortgages' => 'French Mortgage Evaluation Guide',
            'apostille' => 'Apostille Guide',
            'bank-ratings' => 'French Bank Comparison Guide',
            'visa-application' => 'Step-by-Step Visa Application Guide',
        );

        $subtitles = array(
            'pet-relocation' => 'Personalized for ' . $user_name,
            'french-mortgages' => 'Prepared for ' . $user_name,
            'apostille' => 'Customized for ' . $user_name,
            'bank-ratings' => 'Recommendations for ' . $user_name,
            'visa-application' => 'Personalized for ' . $user_name,
        );

        return array(
            'type' => $guide_type,
            'title' => $titles[$guide_type] ?? 'Guide',
            'subtitle' => $subtitles[$guide_type] ?? '',
            'date' => date('F j, Y'),
            'ai_content' => $ai_content,
            'meta' => array(
                'generated' => date('Y-m-d H:i:s'),
                'answers' => $answers,
            ),
        );
    }

    /**
     * Convert guide to beautifully formatted Word document
     * Styled to match professional PDF guide layout
     */
    public function to_word_document($guide_data) {
        $title = $guide_data['title'];
        $subtitle = $guide_data['subtitle'] ?? '';
        $date = $guide_data['date'];
        $ai_content = $guide_data['ai_content'];
        $guide_type = $guide_data['type'] ?? '';

        // Professional color palette matching PDF template
        $colors = array(
            'primary' => '#2b5797',      // Primary blue (headers)
            'primary_dark' => '#1e3f6f', // Darker blue (table headers)
            'text' => '#333333',         // Body text
            'text_light' => '#666666',   // Secondary text
            'warning_bg' => '#fff3cd',   // Warning box background
            'warning_border' => '#ffc107', // Warning box border (amber)
            'warning_text' => '#856404', // Warning text
            'info_bg' => '#e7f3ff',      // Info box background
            'info_border' => '#2b5797',  // Info box border
            'success_bg' => '#d4edda',   // Success box background
            'success_border' => '#28a745', // Success border
            'tip_bg' => '#f8f9fa',       // Tip box background
            'border' => '#dee2e6',       // Table/general borders
            'row_alt' => '#f8f9fa',      // Alternating row background
        );

        // Get header text based on guide type
        $header_text = $this->get_guide_header_text($guide_type, $title);

        $html = '<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
<head>
<meta charset="utf-8">
<meta name="ProgId" content="Word.Document">
<style>
@page {
    margin: 0.75in 1in;
    mso-header-margin: 0.5in;
    mso-footer-margin: 0.5in;
}
body {
    font-family: "Segoe UI", Calibri, Arial, sans-serif;
    font-size: 11pt;
    line-height: 1.5;
    color: ' . $colors['text'] . ';
    background: white;
}

/* Running Header */
.page-header {
    font-size: 9pt;
    color: ' . $colors['text_light'] . ';
    border-bottom: 1px solid ' . $colors['border'] . ';
    padding-bottom: 8pt;
    margin-bottom: 20pt;
    text-align: right;
}

/* Title Block - Centered with visa type */
.title-block {
    text-align: center;
    padding: 30pt 0 25pt;
    margin-bottom: 25pt;
}
.title-block h1 {
    font-size: 26pt;
    font-weight: 600;
    margin: 0 0 10pt;
    color: ' . $colors['primary'] . ';
}
.title-block .visa-type {
    font-size: 14pt;
    font-weight: 400;
    color: ' . $colors['text_light'] . ';
    margin: 0 0 8pt;
    font-style: italic;
}
.title-block .date {
    font-size: 10pt;
    color: ' . $colors['text_light'] . ';
    margin: 0;
}

/* Warning/Alert Box - Yellow/Amber style */
.warning-box {
    background: ' . $colors['warning_bg'] . ';
    border: 1px solid ' . $colors['warning_border'] . ';
    border-radius: 4pt;
    padding: 15pt 18pt;
    margin: 20pt 0;
}
.warning-box .warning-icon {
    font-size: 14pt;
    margin-right: 8pt;
}
.warning-box .warning-title {
    font-weight: 600;
    color: ' . $colors['warning_text'] . ';
    font-size: 12pt;
    margin-bottom: 8pt;
}
.warning-box p {
    color: ' . $colors['warning_text'] . ';
    margin: 0;
    font-size: 10pt;
}

/* Info Box - Blue style */
.info-box {
    background: ' . $colors['info_bg'] . ';
    border-left: 4px solid ' . $colors['info_border'] . ';
    padding: 12pt 15pt;
    margin: 15pt 0;
}
.info-box p {
    margin: 0;
    color: ' . $colors['text'] . ';
}

/* Success/Tip Box */
.success-box {
    background: ' . $colors['success_bg'] . ';
    border-left: 4px solid ' . $colors['success_border'] . ';
    padding: 12pt 15pt;
    margin: 15pt 0;
}

/* Pro Tip Box */
.tip-box {
    background: ' . $colors['tip_bg'] . ';
    border: 1px solid ' . $colors['border'] . ';
    border-radius: 4pt;
    padding: 12pt 15pt;
    margin: 15pt 0;
}
.tip-box .tip-title {
    font-weight: 600;
    color: ' . $colors['primary'] . ';
    margin-bottom: 5pt;
}

/* Section Headers - Blue with numbers */
h2 {
    font-size: 16pt;
    font-weight: 600;
    color: ' . $colors['primary'] . ';
    margin: 30pt 0 15pt;
    padding-bottom: 5pt;
    border-bottom: 2px solid ' . $colors['primary'] . ';
}

/* Subsection Headers - Blue */
h3 {
    font-size: 13pt;
    font-weight: 600;
    color: ' . $colors['primary'] . ';
    margin: 22pt 0 10pt;
}

/* Sub-subsection Headers */
h4 {
    font-size: 11pt;
    font-weight: 600;
    color: ' . $colors['text'] . ';
    margin: 15pt 0 8pt;
}

/* Body Text */
p {
    margin: 0 0 10pt;
    line-height: 1.6;
}

/* Lists */
ul, ol {
    margin: 10pt 0 15pt 25pt;
    padding: 0;
}
li {
    margin: 5pt 0;
    line-height: 1.5;
}

/* Tables - Professional with dark blue headers */
table {
    width: 100%;
    border-collapse: collapse;
    margin: 18pt 0;
    font-size: 10pt;
    border: 1px solid ' . $colors['border'] . ';
}
th {
    background: ' . $colors['primary_dark'] . ';
    color: white;
    padding: 10pt 12pt;
    text-align: left;
    font-weight: 600;
    font-size: 10pt;
    border: 1px solid ' . $colors['primary_dark'] . ';
}
td {
    padding: 10pt 12pt;
    border: 1px solid ' . $colors['border'] . ';
    vertical-align: top;
}
tr:nth-child(even) td {
    background: ' . $colors['row_alt'] . ';
}

/* Comparison Table for multiple applicants */
.comparison-table th {
    text-align: center;
}
.comparison-table td {
    text-align: center;
}
.comparison-table td:first-child {
    text-align: left;
    font-weight: 500;
}

/* Document Checklist Styling */
.checklist-section {
    background: ' . $colors['tip_bg'] . ';
    border: 1px solid ' . $colors['border'] . ';
    border-radius: 4pt;
    padding: 15pt 20pt;
    margin: 15pt 0;
}
.checklist-category {
    font-weight: 600;
    color: ' . $colors['primary'] . ';
    margin: 12pt 0 8pt;
    font-size: 11pt;
}
.checklist-item {
    margin: 6pt 0;
    padding-left: 22pt;
    position: relative;
}
.checklist-item:before {
    content: "☐";
    position: absolute;
    left: 0;
    color: ' . $colors['primary'] . ';
    font-size: 12pt;
}

/* Timeline/Phase styling */
.phase-header {
    background: ' . $colors['info_bg'] . ';
    padding: 8pt 12pt;
    margin: 15pt 0 10pt;
    border-left: 4px solid ' . $colors['primary'] . ';
    font-weight: 600;
    color: ' . $colors['primary'] . ';
}

/* Cost breakdown table */
.cost-table td:last-child {
    text-align: right;
    font-weight: 500;
}
.cost-table tr.total-row td {
    background: ' . $colors['info_bg'] . ';
    font-weight: 700;
    border-top: 2px solid ' . $colors['primary'] . ';
}

/* Emphasis and highlights */
strong {
    font-weight: 600;
    color: ' . $colors['text'] . ';
}
em {
    font-style: italic;
}

/* Links */
a {
    color: ' . $colors['primary'] . ';
    text-decoration: underline;
}

/* Footer */
.footer {
    margin-top: 40pt;
    padding-top: 15pt;
    border-top: 2px solid ' . $colors['border'] . ';
    font-size: 9pt;
    color: ' . $colors['text_light'] . ';
    text-align: center;
}
.footer p {
    margin: 3pt 0;
}
</style>
</head>
<body>

<div class="page-header">' . esc_html($header_text) . '</div>

<div class="title-block">
    <h1>' . esc_html($title) . '</h1>
    <p class="visa-type">' . esc_html($subtitle) . '</p>
    <p class="date">Generated: ' . esc_html($date) . '</p>
</div>

' . $ai_content . '

<div class="footer">
    <p><strong>France Relocation Assistant</strong> • relo2france.com</p>
    <p>This guide was personalized based on your specific situation.</p>
    <p>Information current as of ' . esc_html($date) . '. Always verify with official sources before proceeding.</p>
</div>

</body>
</html>';

        return $html;
    }

    /**
     * Get header text for guide type
     */
    private function get_guide_header_text($guide_type, $title) {
        $headers = array(
            'visa-application' => 'France VLS-TS Visa Application Guide',
            'healthcare' => 'France Healthcare Navigation Guide',
            'banking' => 'France Banking Setup Guide',
            'housing' => 'France Housing Search Guide',
            'relocation-timeline' => 'France Relocation Timeline',
            'bank-ratings' => 'France Bank Comparison Guide',
        );

        return $headers[$guide_type] ?? $title;
    }
}
