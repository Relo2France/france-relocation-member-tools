<?php
/**
 * Membership Detection and Integration
 *
 * Handles detection of membership plugins and user membership status.
 * Supports MemberPress (recommended), PMPro, RCP, and WooCommerce Memberships.
 *
 * @package FRA_Member_Tools
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Membership detection and integration class
 */
class FRAMT_Membership {

    /**
     * Singleton instance
     *
     * @var FRAMT_Membership|null
     */
    private static $instance = null;

    /**
     * Detected membership plugin
     *
     * @var string|false
     */
    private $membership_plugin = null;

    /**
     * Get singleton instance
     *
     * @return FRAMT_Membership
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
        $this->membership_plugin = $this->detect_plugin();
    }

    /**
     * Detect which membership plugin is active
     *
     * @return string|false Plugin identifier or false
     */
    private function detect_plugin() {
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
     * Get the detected membership plugin
     *
     * @return string|false
     */
    public function get_plugin() {
        return $this->membership_plugin;
    }

    /**
     * Check if user is a member
     *
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool
     */
    public function is_member($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Demo mode - allow testing without membership plugin
        if (get_option('framt_enable_demo_mode', false)) {
            return is_user_logged_in();
        }

        switch ($this->membership_plugin) {
            case 'memberpress':
                return $this->check_memberpress($user_id);

            case 'pmpro':
                return $this->check_pmpro($user_id);

            case 'rcp':
                return $this->check_rcp($user_id);

            case 'woocommerce':
                return $this->check_woocommerce($user_id);

            default:
                // No membership plugin - check for admin or specific capability
                return user_can($user_id, 'manage_options');
        }
    }

    /**
     * Check MemberPress membership
     *
     * @param int $user_id User ID
     * @return bool
     */
    private function check_memberpress($user_id) {
        if (!class_exists('MeprUser')) {
            return false;
        }

        $mepr_user = new MeprUser($user_id);
        
        // Check if user has any active subscription
        if (method_exists($mepr_user, 'is_active')) {
            return $mepr_user->is_active();
        }

        // Fallback - check for active subscriptions
        if (method_exists($mepr_user, 'active_product_subscriptions')) {
            $subs = $mepr_user->active_product_subscriptions();
            return !empty($subs);
        }

        return false;
    }

    /**
     * Check Paid Memberships Pro membership
     *
     * @param int $user_id User ID
     * @return bool
     */
    private function check_pmpro($user_id) {
        if (!function_exists('pmpro_hasMembershipLevel')) {
            return false;
        }

        return pmpro_hasMembershipLevel(null, $user_id);
    }

    /**
     * Check Restrict Content Pro membership
     *
     * @param int $user_id User ID
     * @return bool
     */
    private function check_rcp($user_id) {
        if (!function_exists('rcp_is_active')) {
            return false;
        }

        return rcp_is_active($user_id);
    }

    /**
     * Check WooCommerce Memberships
     *
     * @param int $user_id User ID
     * @return bool
     */
    private function check_woocommerce($user_id) {
        if (!function_exists('wc_memberships_is_user_active_member')) {
            return false;
        }

        return wc_memberships_is_user_active_member($user_id);
    }

    /**
     * Get membership level/plan name
     *
     * @param int|null $user_id User ID
     * @return string|null
     */
    public function get_membership_level($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id || !$this->is_member($user_id)) {
            return null;
        }

        switch ($this->membership_plugin) {
            case 'memberpress':
                return $this->get_memberpress_level($user_id);

            case 'pmpro':
                return $this->get_pmpro_level($user_id);

            case 'rcp':
                return $this->get_rcp_level($user_id);

            case 'woocommerce':
                return $this->get_woocommerce_level($user_id);

            default:
                return 'Lifetime Member';
        }
    }

    /**
     * Get MemberPress membership level
     *
     * @param int $user_id User ID
     * @return string|null
     */
    private function get_memberpress_level($user_id) {
        if (!class_exists('MeprUser')) {
            return null;
        }

        $mepr_user = new MeprUser($user_id);
        
        if (method_exists($mepr_user, 'active_product_subscriptions')) {
            $subs = $mepr_user->active_product_subscriptions();
            if (!empty($subs)) {
                $product_id = reset($subs);
                $product = get_post($product_id);
                return $product ? $product->post_title : null;
            }
        }

        return null;
    }

    /**
     * Get PMPro membership level
     *
     * @param int $user_id User ID
     * @return string|null
     */
    private function get_pmpro_level($user_id) {
        if (!function_exists('pmpro_getMembershipLevelForUser')) {
            return null;
        }

        $level = pmpro_getMembershipLevelForUser($user_id);
        return $level ? $level->name : null;
    }

    /**
     * Get RCP membership level
     *
     * @param int $user_id User ID
     * @return string|null
     */
    private function get_rcp_level($user_id) {
        if (!function_exists('rcp_get_subscription')) {
            return null;
        }

        return rcp_get_subscription($user_id);
    }

    /**
     * Get WooCommerce membership level
     *
     * @param int $user_id User ID
     * @return string|null
     */
    private function get_woocommerce_level($user_id) {
        if (!function_exists('wc_memberships_get_user_active_memberships')) {
            return null;
        }

        $memberships = wc_memberships_get_user_active_memberships($user_id);
        
        if (!empty($memberships)) {
            $first = reset($memberships);
            $plan = $first->get_plan();
            return $plan ? $plan->get_name() : null;
        }

        return null;
    }

    /**
     * Get membership expiration date
     *
     * @param int|null $user_id User ID
     * @return string|null Date string or null for lifetime
     */
    public function get_expiration_date($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id || !$this->is_member($user_id)) {
            return null;
        }

        // For lifetime memberships, return null
        switch ($this->membership_plugin) {
            case 'memberpress':
                return $this->get_memberpress_expiration($user_id);

            default:
                return null; // Lifetime
        }
    }

    /**
     * Get MemberPress expiration date
     *
     * @param int $user_id User ID
     * @return string|null
     */
    private function get_memberpress_expiration($user_id) {
        if (!class_exists('MeprUser')) {
            return null;
        }

        $mepr_user = new MeprUser($user_id);
        
        if (method_exists($mepr_user, 'get_active_subscription_ids')) {
            $sub_ids = $mepr_user->get_active_subscription_ids();
            
            if (!empty($sub_ids)) {
                $sub = new MeprSubscription(reset($sub_ids));
                
                if ($sub && !$sub->is_lifetime()) {
                    return $sub->expires_at;
                }
            }
        }

        return null; // Lifetime or no expiration
    }

    /**
     * Check if membership is lifetime
     *
     * @param int|null $user_id User ID
     * @return bool
     */
    public function is_lifetime($user_id = null) {
        return $this->get_expiration_date($user_id) === null;
    }

    /**
     * Get membership status for display
     *
     * @param int|null $user_id User ID
     * @return array Status info array
     */
    public function get_status_info($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        $is_member = $this->is_member($user_id);
        $level = $this->get_membership_level($user_id);
        $expiration = $this->get_expiration_date($user_id);
        $is_lifetime = $this->is_lifetime($user_id);

        return array(
            'is_member' => $is_member,
            'level' => $level,
            'expiration' => $expiration,
            'is_lifetime' => $is_lifetime,
            'status_text' => $this->get_status_text($is_member, $expiration, $is_lifetime),
        );
    }

    /**
     * Get status text for display
     *
     * @param bool $is_member Is user a member
     * @param string|null $expiration Expiration date
     * @param bool $is_lifetime Is lifetime membership
     * @return string
     */
    private function get_status_text($is_member, $expiration, $is_lifetime) {
        if (!$is_member) {
            return __('Not a member', 'fra-member-tools');
        }

        if ($is_lifetime) {
            return __('Lifetime Member', 'fra-member-tools');
        }

        if ($expiration) {
            $date = date_i18n(get_option('date_format'), strtotime($expiration));
            return sprintf(__('Active until %s', 'fra-member-tools'), $date);
        }

        return __('Active', 'fra-member-tools');
    }
}
