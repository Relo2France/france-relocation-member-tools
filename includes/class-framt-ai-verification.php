<?php
/**
 * AI Verification for Documents
 *
 * Handles AI-powered verification of health insurance and other documents
 * using the Anthropic Claude API (shared with main plugin).
 *
 * @package FRA_Member_Tools
 * @since 1.0.12
 */

if (!defined('ABSPATH')) {
    exit;
}

class FRAMT_AI_Verification {

    private static $instance = null;
    
    /**
     * Anthropic API endpoint
     */
    const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    
    /**
     * Model to use for verification
     */
    const MODEL = 'claude-sonnet-4-20250514';
    
    /**
     * Max tokens for response
     */
    const MAX_TOKENS = 2048;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}
    
    /**
     * Get API key from main plugin's settings
     * Uses the same 'fra_api_key' option as the main France Relocation Assistant plugin
     */
    private function get_api_key() {
        // Use the main plugin's API key setting
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
     * Verify health insurance document
     *
     * @param string $file_path Path to uploaded file
     * @param string $file_type MIME type of file
     * @param array $user_context Optional context about the user's visa type, etc.
     * @return array Verification result
     */
    public function verify_health_insurance($file_path, $file_type, $user_context = array()) {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => 'API not configured',
                'message' => __('AI verification is not configured. Please contact the site administrator.', 'fra-member-tools'),
            );
        }
        
        // Read and encode file
        $file_data = $this->prepare_file_for_api($file_path, $file_type);
        
        if (is_wp_error($file_data)) {
            return array(
                'success' => false,
                'error' => $file_data->get_error_code(),
                'message' => $file_data->get_error_message(),
            );
        }
        
        // Build the verification prompt
        $prompt = $this->build_health_insurance_prompt($user_context);
        
        // Make API request
        $response = $this->call_api_with_image($prompt, $file_data);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_code(),
                'message' => $response->get_error_message(),
            );
        }
        
        // Parse AI response
        return $this->parse_verification_response($response);
    }
    
    /**
     * Prepare file for API submission
     *
     * @param string $file_path Path to file
     * @param string $file_type MIME type
     * @return array|WP_Error File data array or error
     */
    private function prepare_file_for_api($file_path, $file_type) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('File not found.', 'fra-member-tools'));
        }
        
        $file_content = file_get_contents($file_path);
        
        if ($file_content === false) {
            return new WP_Error('file_read_error', __('Could not read file.', 'fra-member-tools'));
        }
        
        // Check file size (max 20MB for API)
        if (strlen($file_content) > 20 * 1024 * 1024) {
            return new WP_Error('file_too_large', __('File is too large for analysis.', 'fra-member-tools'));
        }
        
        // Handle PDF files - convert to base64 and use document type
        if ($file_type === 'application/pdf') {
            return array(
                'type' => 'document',
                'source' => array(
                    'type' => 'base64',
                    'media_type' => 'application/pdf',
                    'data' => base64_encode($file_content),
                ),
            );
        }
        
        // Handle image files
        $supported_images = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        
        if (in_array($file_type, $supported_images)) {
            return array(
                'type' => 'image',
                'source' => array(
                    'type' => 'base64',
                    'media_type' => $file_type,
                    'data' => base64_encode($file_content),
                ),
            );
        }
        
        return new WP_Error('unsupported_type', __('Unsupported file type. Please upload a PDF, JPG, or PNG.', 'fra-member-tools'));
    }
    
    /**
     * Build the health insurance verification prompt
     *
     * @param array $user_context User's visa type and other context
     * @return string The prompt
     */
    private function build_health_insurance_prompt($user_context = array()) {
        $visa_type = $user_context['visa_type'] ?? 'long-stay visa';
        $duration = $user_context['planned_duration'] ?? 'one year or more';
        
        $prompt = <<<PROMPT
You are an expert document reviewer helping Americans relocate to France. Your task is to analyze this health insurance certificate/policy document and determine if it meets the requirements for a French {$visa_type}.

## French Health Insurance Requirements for Visa Applications

The health insurance must meet ALL of these requirements:

1. **Minimum Coverage Amount**: At least €30,000 (or equivalent ~\$33,000 USD) in medical coverage
2. **Hospitalization Coverage**: Must explicitly cover hospital stays and medical treatment
3. **Repatriation Coverage**: Must include medical repatriation/evacuation back to home country
4. **Geographic Coverage**: Must be valid in France AND all Schengen Area countries (26 European countries)
5. **Duration**: Must cover the entire planned stay period ({$duration})
6. **No Deductible Issues**: Policies with very high deductibles may be problematic

## Your Analysis Task

Please carefully examine this document and provide:

1. **Overall Assessment**: Does this certificate appear to meet French visa requirements?
   - Use "VERIFIED" if all requirements appear to be met
   - Use "ISSUES" if some requirements are unclear or potentially not met
   - Use "INSUFFICIENT" if the document clearly doesn't meet requirements

2. **Requirement Checklist**: For each of the 6 requirements above, indicate:
   - ✅ Met (clearly stated in document)
   - ⚠️ Unclear (not explicitly stated or hard to determine)
   - ❌ Not Met (clearly missing or insufficient)

3. **Specific Findings**: Quote or reference specific parts of the document that support your assessment

4. **Recommendations**: If there are issues, provide specific advice on what the applicant should do

## Response Format

Please structure your response as follows:

ASSESSMENT: [VERIFIED/ISSUES/INSUFFICIENT]

CHECKLIST:
- Coverage Amount: [✅/⚠️/❌] [brief explanation]
- Hospitalization: [✅/⚠️/❌] [brief explanation]
- Repatriation: [✅/⚠️/❌] [brief explanation]
- Schengen Coverage: [✅/⚠️/❌] [brief explanation]
- Duration: [✅/⚠️/❌] [brief explanation]
- Deductible: [✅/⚠️/❌] [brief explanation]

FINDINGS:
[Your detailed findings with specific references to the document]

RECOMMENDATIONS:
[Any advice for the applicant, or confirmation that they're good to proceed]

IMPORTANT NOTES:
- Be thorough but practical - this is a real service helping real people
- If the document is hard to read, mention that
- If this doesn't appear to be a health insurance document, say so clearly
- French consulates can be strict, so err on the side of caution
- The final determination is always made by the French consulate
PROMPT;

        return $prompt;
    }
    
    /**
     * Call Anthropic API with an image/document
     *
     * @param string $prompt The text prompt
     * @param array $file_data The prepared file data
     * @return string|WP_Error API response text or error
     */
    private function call_api_with_image($prompt, $file_data) {
        $api_key = $this->get_api_key();
        
        $body = array(
            'model' => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        $file_data,
                        array(
                            'type' => 'text',
                            'text' => $prompt,
                        ),
                    ),
                ),
            ),
        );
        
        $response = wp_remote_post(self::API_ENDPOINT, array(
            'timeout' => 120, // Document analysis can take time
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => wp_json_encode($body),
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error(
                'api_request_failed',
                sprintf(__('API request failed: %s', 'fra-member-tools'), $response->get_error_message())
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code !== 200) {
            $error_message = $data['error']['message'] ?? __('Unknown API error', 'fra-member-tools');
            
            // Handle specific error cases
            if ($status_code === 401) {
                return new WP_Error('invalid_api_key', __('Invalid API key. Please check your configuration.', 'fra-member-tools'));
            }
            
            if ($status_code === 429) {
                return new WP_Error('rate_limited', __('Too many requests. Please try again in a moment.', 'fra-member-tools'));
            }
            
            return new WP_Error('api_error', $error_message);
        }
        
        // Extract text from response
        if (isset($data['content'][0]['text'])) {
            return $data['content'][0]['text'];
        }
        
        return new WP_Error('invalid_response', __('Invalid response from AI service.', 'fra-member-tools'));
    }
    
    /**
     * Parse the AI verification response
     *
     * @param string $response Raw AI response
     * @return array Structured verification result
     */
    private function parse_verification_response($response) {
        // Determine overall status
        $status = 'issues'; // Default to issues
        
        if (preg_match('/ASSESSMENT:\s*(VERIFIED|ISSUES|INSUFFICIENT)/i', $response, $matches)) {
            $assessment = strtoupper($matches[1]);
            
            switch ($assessment) {
                case 'VERIFIED':
                    $status = 'verified';
                    break;
                case 'ISSUES':
                    $status = 'issues';
                    break;
                case 'INSUFFICIENT':
                    $status = 'failed';
                    break;
            }
        }
        
        // Parse checklist items
        $checklist = array();
        $checklist_patterns = array(
            'coverage_amount' => '/Coverage Amount:\s*([✅⚠️❌])\s*(.+?)(?=\n|$)/u',
            'hospitalization' => '/Hospitalization:\s*([✅⚠️❌])\s*(.+?)(?=\n|$)/u',
            'repatriation' => '/Repatriation:\s*([✅⚠️❌])\s*(.+?)(?=\n|$)/u',
            'schengen' => '/Schengen Coverage:\s*([✅⚠️❌])\s*(.+?)(?=\n|$)/u',
            'duration' => '/Duration:\s*([✅⚠️❌])\s*(.+?)(?=\n|$)/u',
            'deductible' => '/Deductible:\s*([✅⚠️❌])\s*(.+?)(?=\n|$)/u',
        );
        
        foreach ($checklist_patterns as $key => $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                $icon = $matches[1];
                $status_type = 'unclear';
                
                if ($icon === '✅') $status_type = 'met';
                elseif ($icon === '❌') $status_type = 'not_met';
                elseif ($icon === '⚠️') $status_type = 'unclear';
                
                $checklist[$key] = array(
                    'status' => $status_type,
                    'icon' => $icon,
                    'detail' => trim($matches[2]),
                );
            }
        }
        
        // Extract findings section
        $findings = '';
        if (preg_match('/FINDINGS:\s*\n(.*?)(?=\n(?:RECOMMENDATIONS:|$))/s', $response, $matches)) {
            $findings = trim($matches[1]);
        }
        
        // Extract recommendations section
        $recommendations = '';
        if (preg_match('/RECOMMENDATIONS:\s*\n(.*)$/s', $response, $matches)) {
            $recommendations = trim($matches[1]);
        }
        
        // Build HTML findings for display
        $findings_html = $this->format_findings_html($status, $checklist, $findings, $recommendations);
        
        return array(
            'success' => true,
            'status' => $status,
            'checklist' => $checklist,
            'findings' => $findings,
            'recommendations' => $recommendations,
            'findings_html' => $findings_html,
            'raw_response' => $response,
        );
    }
    
    /**
     * Format findings as HTML for display
     *
     * @param string $status Overall status
     * @param array $checklist Parsed checklist
     * @param string $findings Findings text
     * @param string $recommendations Recommendations text
     * @return string HTML
     */
    private function format_findings_html($status, $checklist, $findings, $recommendations) {
        $html = '<div class="framt-ai-findings">';
        
        // Checklist
        if (!empty($checklist)) {
            $html .= '<h5>' . esc_html__('Requirements Checklist', 'fra-member-tools') . '</h5>';
            $html .= '<ul class="framt-checklist-results">';
            
            $labels = array(
                'coverage_amount' => __('Coverage Amount (€30,000+)', 'fra-member-tools'),
                'hospitalization' => __('Hospitalization Coverage', 'fra-member-tools'),
                'repatriation' => __('Repatriation Coverage', 'fra-member-tools'),
                'schengen' => __('Schengen Area Coverage', 'fra-member-tools'),
                'duration' => __('Duration of Coverage', 'fra-member-tools'),
                'deductible' => __('Deductible Level', 'fra-member-tools'),
            );
            
            foreach ($checklist as $key => $item) {
                $label = $labels[$key] ?? $key;
                $html .= sprintf(
                    '<li class="framt-check-%s"><span class="framt-check-icon">%s</span> <strong>%s:</strong> %s</li>',
                    esc_attr($item['status']),
                    esc_html($item['icon']),
                    esc_html($label),
                    esc_html($item['detail'])
                );
            }
            
            $html .= '</ul>';
        }
        
        // Findings
        if (!empty($findings)) {
            $html .= '<h5>' . esc_html__('Detailed Findings', 'fra-member-tools') . '</h5>';
            $html .= '<div class="framt-findings-text">' . wp_kses_post(nl2br(esc_html($findings))) . '</div>';
        }
        
        // Recommendations
        if (!empty($recommendations)) {
            $html .= '<h5>' . esc_html__('Recommendations', 'fra-member-tools') . '</h5>';
            $html .= '<div class="framt-recommendations-text">' . wp_kses_post(nl2br(esc_html($recommendations))) . '</div>';
        }
        
        // Disclaimer
        $html .= '<div class="framt-ai-disclaimer">';
        $html .= '<p><strong>' . esc_html__('Important:', 'fra-member-tools') . '</strong> ';
        $html .= esc_html__('This AI analysis is provided as guidance only. The French consulate makes the final determination on whether your documentation meets their requirements. If you have any concerns, consider contacting your insurance provider for a letter confirming coverage details.', 'fra-member-tools');
        $html .= '</p></div>';
        
        $html .= '</div>';
        
        return $html;
    }
}
