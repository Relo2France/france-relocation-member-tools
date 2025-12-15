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
        );

        return $prompts[$guide_type] ?? new WP_Error('invalid_guide', __('Invalid guide type', 'fra-member-tools'));
    }

    /**
     * Build pet relocation guide prompt
     */
    private function build_pet_prompt($answers, $profile, $user_name, $current_date) {
        $pet_type = $answers['pet_type'] ?? 'dog';
        if (is_array($pet_type)) {
            $pet_type = implode(' and ', $pet_type);
        }
        
        $pet_count = $answers['pet_count'] ?? '1';
        $travel_method = $answers['travel_method'] ?? 'flying_cargo';
        $microchipped = $answers['microchipped'] ?? 'no';
        $rabies_status = $answers['rabies_status'] ?? 'unsure';
        $move_timeline = $answers['move_timeline'] ?? '3_6_months';
        
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
- Pet type: {$pet_type}
- Number of pets: {$pet_count}
- Travel method: {$travel_desc}
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
        
        $target_location = $profile['target_location'] ?? 'France';
        
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
- Current date: {$current_date}

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
        
        $needs_list = implode(', ', $needs);
        
        return "You are an expert in French banking for American expats. Generate a comprehensive, personalized bank comparison guide.

USER SITUATION:
- Name: {$user_name}
- Banking needs: {$needs_list}
- English support importance: {$english_support}
- Online banking importance: {$online_banking}
- Current date: {$current_date}

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
        );
        
        $subtitles = array(
            'pet-relocation' => 'Personalized for ' . $user_name,
            'french-mortgages' => 'Prepared for ' . $user_name,
            'apostille' => 'Customized for ' . $user_name,
            'bank-ratings' => 'Recommendations for ' . $user_name,
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
     */
    public function to_word_document($guide_data) {
        $title = $guide_data['title'];
        $subtitle = $guide_data['subtitle'] ?? '';
        $date = $guide_data['date'];
        $ai_content = $guide_data['ai_content'];
        
        // Braun-inspired color palette
        $colors = array(
            'primary' => '#1a1a1a',      // Near black
            'secondary' => '#4a4a4a',    // Dark gray
            'accent' => '#d4a853',       // Warm gold
            'background' => '#fafaf8',   // Warm white
            'border' => '#e8e6e1',       // Warm gray
            'success' => '#4a5d4a',      // Muted green
            'warning' => '#b8860b',      // Dark goldenrod
            'muted' => '#6b6b6b',        // Medium gray
        );
        
        $html = '<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
<head>
<meta charset="utf-8">
<meta name="ProgId" content="Word.Document">
<style>
@page {
    margin: 1in;
}
body {
    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
    font-size: 11pt;
    line-height: 1.6;
    color: ' . $colors['primary'] . ';
    background: white;
}

/* Title Block */
.title-block {
    text-align: center;
    padding: 40pt 0 30pt;
    border-bottom: 2px solid ' . $colors['accent'] . ';
    margin-bottom: 30pt;
}
.title-block h1 {
    font-size: 28pt;
    font-weight: 300;
    letter-spacing: -0.5pt;
    margin: 0 0 8pt;
    color: ' . $colors['primary'] . ';
}
.title-block .subtitle {
    font-size: 14pt;
    font-weight: 400;
    color: ' . $colors['secondary'] . ';
    margin: 0 0 5pt;
}
.title-block .date {
    font-size: 10pt;
    color: ' . $colors['muted'] . ';
    margin: 0;
}

/* Section Headers */
h2 {
    font-size: 16pt;
    font-weight: 500;
    color: ' . $colors['primary'] . ';
    margin: 30pt 0 15pt;
    padding-bottom: 8pt;
    border-bottom: 1px solid ' . $colors['border'] . ';
}
h3 {
    font-size: 13pt;
    font-weight: 500;
    color: ' . $colors['secondary'] . ';
    margin: 20pt 0 10pt;
}
h4 {
    font-size: 11pt;
    font-weight: 600;
    color: ' . $colors['secondary'] . ';
    margin: 15pt 0 8pt;
}

/* Body Text */
p {
    margin: 0 0 10pt;
    text-align: justify;
}

/* Lists */
ul, ol {
    margin: 10pt 0 15pt 20pt;
    padding: 0;
}
li {
    margin: 6pt 0;
    line-height: 1.5;
}

/* Tables */
table {
    width: 100%;
    border-collapse: collapse;
    margin: 15pt 0;
    font-size: 10pt;
}
th {
    background: ' . $colors['primary'] . ';
    color: white;
    padding: 10pt 12pt;
    text-align: left;
    font-weight: 500;
    font-size: 9pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
}
td {
    padding: 10pt 12pt;
    border-bottom: 1px solid ' . $colors['border'] . ';
    vertical-align: top;
}
tr:nth-child(even) td {
    background: ' . $colors['background'] . ';
}

/* Info Boxes */
.info-box {
    background: ' . $colors['background'] . ';
    border-left: 3px solid ' . $colors['accent'] . ';
    padding: 12pt 15pt;
    margin: 15pt 0;
}
.success-box {
    background: #f0f5f0;
    border-left: 3px solid ' . $colors['success'] . ';
    padding: 12pt 15pt;
    margin: 15pt 0;
}
.warning-box {
    background: #fffbf5;
    border-left: 3px solid ' . $colors['warning'] . ';
    padding: 12pt 15pt;
    margin: 15pt 0;
}

/* Checklist */
.checklist {
    background: ' . $colors['background'] . ';
    padding: 15pt 20pt;
    margin: 15pt 0;
}
.checklist-item {
    margin: 8pt 0;
    padding-left: 25pt;
    position: relative;
}
.checklist-item:before {
    content: "☐";
    position: absolute;
    left: 0;
    color: ' . $colors['accent'] . ';
}

/* Strong/Bold */
strong {
    font-weight: 600;
    color: ' . $colors['primary'] . ';
}

/* Links */
a {
    color: ' . $colors['accent'] . ';
    text-decoration: none;
}

/* Footer */
.footer {
    margin-top: 40pt;
    padding-top: 15pt;
    border-top: 1px solid ' . $colors['border'] . ';
    font-size: 9pt;
    color: ' . $colors['muted'] . ';
    text-align: center;
}
</style>
</head>
<body>

<div class="title-block">
    <h1>' . esc_html($title) . '</h1>
    <p class="subtitle">' . esc_html($subtitle) . '</p>
    <p class="date">' . esc_html($date) . '</p>
</div>

' . $ai_content . '

<div class="footer">
    <p>Generated by France Relocation Assistant • relo2france.com</p>
    <p>This guide was personalized based on your specific situation. Information is current as of ' . esc_html($date) . '.</p>
</div>

</body>
</html>';

        return $html;
    }
}
