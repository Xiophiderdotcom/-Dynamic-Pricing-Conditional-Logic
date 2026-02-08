<?php
/**
 * Plugin Name: Advance Booking System
 * Description: Complete real estate photography booking with analytics, agent portal, and advanced management.
 * Version: 2.2.0
 * Author: Xiophider.com
 * Text Domain: advance-booking-system
 */

if (!defined('ABSPATH')) exit;

define('ABS_VERSION', '2.2.0');
define('ABS_PATH', plugin_dir_path(__FILE__));
define('ABS_URL', plugin_dir_url(__FILE__));

// Include customizer
require_once ABS_PATH . 'includes/customizer.php';

// ============================================
// ACTIVATION & TABLE CREATION
// ============================================
register_activation_hook(__FILE__, 'abs_activate');
function abs_activate() {
    abs_create_table();
    add_role('real_estate_agent', 'Real Estate Agent', ['read' => true]);
    
    // Default options
    $defaults = [
        'abs_google_api_key' => '',
        'abs_drone_price' => 100,
        'abs_dusk_price' => 150,
        'abs_proofing_price' => 75,
        'abs_admin_email' => 'bookings@onlyfotos.com.au',
        'abs_business_name' => 'OnlyFotos',
        'abs_business_address' => '3 Toorak Rd, South Yarra, VIC 3141',
        'abs_business_phone' => '',
        'abs_rental_offices' => "St Kilda\nMurrumbeena",
        'abs_sales_offices' => "Caulfield\nSt Kilda\nBentleigh\nCarnegie\nMurrumbeena\nNRC",
        'abs_working_days' => 'monday,tuesday,wednesday,thursday',
        'abs_time_slots' => "09:00\n10:00\n11:00\n12:00\n13:00\n14:00\n15:00\n16:00\n17:00",
        'abs_custom_addons' => '',
        'abs_email_subject' => '📷 Booking Confirmed - {address}',
    ];
    foreach ($defaults as $key => $val) {
        add_option($key, $val);
    }
    update_option('abs_db_version', '2.1.0');
}

function abs_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'abs_bookings';
    $charset = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        booking_type varchar(50) NOT NULL,
        agent_name varchar(255) NOT NULL,
        agent_email varchar(255) NOT NULL,
        agent_mobile varchar(50) NOT NULL,
        property_address text NOT NULL,
        booking_date date NOT NULL,
        booking_time time NOT NULL,
        addons text,
        property_access varchar(100) DEFAULT '',
        access_details text,
        office_location varchar(100) DEFAULT '',
        keysafe_code varchar(50) DEFAULT '',
        vendor_name varchar(255) DEFAULT '',
        vendor_contact varchar(100) DEFAULT '',
        notes text,
        status varchar(50) DEFAULT 'confirmed',
        submit_lat decimal(10,8) DEFAULT NULL,
        submit_lng decimal(11,8) DEFAULT NULL,
        submit_location varchar(255) DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY agent_email (agent_email),
        KEY booking_date (booking_date),
        KEY status (status)
    ) $charset;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Auto-create table on init
add_action('init', function() {
    if (get_option('abs_db_version') !== '2.1.0') {
        abs_create_table();
        update_option('abs_db_version', '2.1.0');
    }
});

// ============================================
// ASSETS
// ============================================
add_action('wp_enqueue_scripts', 'abs_enqueue_assets');
function abs_enqueue_assets() {
    wp_enqueue_style('abs-style', ABS_URL . 'assets/css/booking.css', [], ABS_VERSION);
    wp_enqueue_script('abs-booking', ABS_URL . 'assets/js/booking.js', ['jquery'], ABS_VERSION, true);
    
    $google_key = get_option('abs_google_api_key');
    if ($google_key) {
        wp_enqueue_script('google-places', "https://maps.googleapis.com/maps/api/js?key={$google_key}&libraries=places", [], null, true);
    }
    
    // Get time slots from settings
    $slots = array_filter(array_map('trim', explode("\n", get_option('abs_time_slots', "09:00\n10:00\n11:00\n12:00\n13:00\n14:00\n15:00\n16:00\n17:00"))));
    
    wp_localize_script('abs-booking', 'absData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('abs_booking'),
        'dronePrice' => intval(get_option('abs_drone_price', 100)),
        'duskPrice' => intval(get_option('abs_dusk_price', 150)),
        'proofingPrice' => intval(get_option('abs_proofing_price', 75)),
        'rentalOffices' => array_filter(array_map('trim', explode("\n", get_option('abs_rental_offices', '')))),
        'salesOffices' => array_filter(array_map('trim', explode("\n", get_option('abs_sales_offices', '')))),
        'timeSlots' => $slots,
        'duskStartHour' => 16,
        'userEmail' => is_user_logged_in() ? wp_get_current_user()->user_email : '',
        'userName' => is_user_logged_in() ? wp_get_current_user()->display_name : ''
    ]);
}

// Admin assets
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'abs-') !== false || $hook === 'toplevel_page_abs-dashboard') {
        wp_enqueue_style('abs-admin', ABS_URL . 'assets/css/admin.css', [], ABS_VERSION);
        wp_enqueue_script('abs-admin', ABS_URL . 'assets/js/admin.js', ['jquery'], ABS_VERSION, true);
        wp_localize_script('abs-admin', 'absAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('abs_admin')
        ]);
    }
});

// Disable cache
add_action('template_redirect', function() {
    if (is_page(['book-a-shoot', 'booking', 'agent-portal'])) {
        nocache_headers();
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    }
});

// ============================================
// SHORTCODE: BOOKING FORM
// ============================================
add_shortcode('abs_booking', 'abs_booking_shortcode');
function abs_booking_shortcode($atts) {
    $user = wp_get_current_user();
    $slots = array_filter(array_map('trim', explode("\n", get_option('abs_time_slots', "09:00\n10:00\n11:00\n12:00\n13:00\n14:00\n15:00\n16:00\n17:00"))));
    
    ob_start();
    ?>
    <div class="abs-booking-wrapper">
        <div class="abs-booking-hero">
            <!-- Logo placeholder - add your logo here -->
            <!-- <img src="path-to-your-logo.png" alt="Company Logo" class="hero-logo"> -->
            <h1><?php echo apply_filters('abs_hero_heading', '📸 Book a Photo Shoot'); ?></h1>
            <p><?php echo apply_filters('abs_hero_subheading', 'Professional real estate photography'); ?></p>
        </div>

        <div class="abs-form-container">
            <!-- Step 1: Type Selection -->
            <div class="abs-step active" id="step-1">
                <h2>Select Service Type</h2>
                <div class="abs-type-cards">
                    <div class="abs-type-card" data-type="rental">
                        <div class="card-icon">🏠</div>
                        <h3><?php echo apply_filters('abs_rental_card_title', 'Rental Photography'); ?></h3>
                        <p><?php echo apply_filters('abs_rental_card_desc', 'Fast and professional rental photography'); ?></p>
                        <ul>
                            <li>✓ 6 images + 1 complimentary</li>
                            <li>✓ Delivered within 24 hours</li>
                            <li>✓ Optional drone</li>
                        </ul>
                    </div>
                    <div class="abs-type-card featured" data-type="sales">
                        <span class="featured-badge">Popular</span>
                        <div class="card-icon">✨</div>
                        <h3><?php echo apply_filters('abs_sales_card_title', 'Sales Photography'); ?></h3>
                        <p><?php echo apply_filters('abs_sales_card_desc', 'Premium photography for property sales'); ?></p>
                        <ul>
                            <li>✓ Packages from 2 images to 15+ images</li>
                            <li>✓ Daylight or dusk photography options</li>
                            <li>✓ Proofing, drone and express turnaround available</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Step 2: Form -->
            <div class="abs-step" id="step-2">
                <div class="step-header">
                    <button class="abs-back" onclick="absGoBack()">← Back</button>
                    <h2>Booking Details</h2>
                </div>
                
                <form id="abs-booking-form">
                    <input type="hidden" name="booking_type" id="booking_type" value="">
                    <input type="hidden" name="submit_lat" id="submit_lat" value="">
                    <input type="hidden" name="submit_lng" id="submit_lng" value="">
                    <input type="hidden" name="submit_location" id="submit_location" value="">
                    
                    <div class="form-section">
                        <h3>📍 Property</h3>
                        <div class="form-group">
                            <label>Property Address *</label>
                            <input type="text" name="property_address" id="property_address" required placeholder="Start typing address...">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>👤 Your Details</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Name *</label>
                                <input type="text" name="agent_name" required value="<?php echo esc_attr($user->display_name); ?>" placeholder="Your name">
                            </div>
                            <div class="form-group">
                                <label>Mobile *</label>
                                <input type="tel" name="agent_mobile" required placeholder="0400 000 000">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="agent_email" required value="<?php echo esc_attr($user->user_email); ?>" placeholder="your@email.com">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>📅 Schedule</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Date *</label>
                                <input type="date" name="booking_date" id="booking_date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                            </div>
                            <div class="form-group">
                                <label>Time *</label>
                                <select name="booking_time" id="booking_time" required>
                                    <option value="">Select time...</option>
                                    <?php foreach ($slots as $slot): 
                                        $h = intval(explode(':', $slot)[0]);
                                        $display = date('g:i A', strtotime($slot));
                                    ?>
                                    <option value="<?php echo esc_attr($slot); ?>" data-hour="<?php echo $h; ?>"><?php echo $display; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="dusk-note" id="dusk-note" style="display:none;">🌅 <strong>Dusk:</strong> Only 4pm+ slots shown</div>
                    </div>

                    <div class="form-section">
                        <h3>➕ Add-ons</h3>
                        <div class="addon-grid">
                            <label class="addon-card">
                                <input type="checkbox" name="addon_drone" value="1">
                                <span class="addon-content">
                                    <span class="addon-icon">🚁</span>
                                    <span class="addon-name">Drone</span>
                                    <span class="addon-price">+$<?php echo intval(get_option('abs_drone_price', 100)); ?></span>
                                </span>
                            </label>
                            <label class="addon-card sales-only">
                                <input type="checkbox" name="addon_dusk" value="1" id="addon_dusk">
                                <span class="addon-content">
                                    <span class="addon-icon">🌅</span>
                                    <span class="addon-name">Dusk</span>
                                    <span class="addon-price">+$<?php echo intval(get_option('abs_dusk_price', 150)); ?></span>
                                </span>
                            </label>
                            <label class="addon-card sales-only">
                                <input type="checkbox" name="addon_proofing" value="1">
                                <span class="addon-content">
                                    <span class="addon-icon">✅</span>
                                    <span class="addon-name">Proofing</span>
                                    <span class="addon-price">+$<?php echo intval(get_option('abs_proofing_price', 75)); ?></span>
                                </span>
                            </label>
                            <label class="addon-card">
                                <input type="checkbox" name="addon_extra" value="1">
                                <span class="addon-content">
                                    <span class="addon-icon">📷</span>
                                    <span class="addon-name">Extra Images</span>
                                    <span class="addon-price">Quote</span>
                                </span>
                            </label>
                        </div>
                        <div class="addon-total">Total: <strong id="addon-total">$0</strong></div>
                    </div>

                    <div class="form-section">
                        <h3>🔑 Access</h3>
                        <div class="form-group">
                            <label>How will we access? *</label>
                            <select name="property_access" id="property_access" required>
                                <option value="">Select...</option>
                                <option value="keysafe">Keysafe</option>
                                <option value="meet_agent">Meet at property</option>
                                <option value="pickup_keys">Pick up keys from office</option>
                                <option value="vendor_tenant">Vendor/tenant access</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="conditional-field" id="field-keysafe" style="display:none;">
                            <div class="form-group"><label>Keysafe Code</label><input type="text" name="keysafe_code" placeholder="Code"></div>
                        </div>
                        <div class="conditional-field" id="field-office" style="display:none;">
                            <div class="form-group"><label>Office</label><select name="office_location" id="office_location"><option value="">Select...</option></select></div>
                        </div>
                        <div class="conditional-field" id="field-vendor" style="display:none;">
                            <div class="form-row">
                                <div class="form-group"><label>Name</label><input type="text" name="vendor_name" placeholder="Name"></div>
                                <div class="form-group"><label>Phone</label><input type="tel" name="vendor_contact" placeholder="Phone"></div>
                            </div>
                        </div>
                        <div class="conditional-field" id="field-other" style="display:none;">
                            <div class="form-group"><label>Details</label><textarea name="access_details" rows="2" placeholder="How to access..."></textarea></div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>📝 Notes</h3>
                        <div class="form-group"><textarea name="notes" rows="2" placeholder="Special instructions..."></textarea></div>
                    </div>

                    <button type="submit" class="abs-btn-submit"><?php echo apply_filters('abs_submit_button_text', 'Confirm Booking →'); ?></button>
                </form>
            </div>

            <!-- Step 3: Confirmation -->
            <div class="abs-step" id="step-3">
                <div class="confirmation-box">
                    <div class="confirm-icon">✅</div>
                    <h2><?php echo apply_filters('abs_confirmation_heading', 'Booking Confirmed!'); ?></h2>
                    <p><?php echo apply_filters('abs_confirmation_message', 'Your shoot is scheduled.'); ?></p>
                    <div id="booking-summary"></div>
                    <a href="<?php echo home_url('/agent-portal/'); ?>" class="abs-btn-primary">View My Bookings</a>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================
// SHORTCODE: AGENT PORTAL (FIXED)
// ============================================
add_shortcode('abs_portal', 'abs_portal_shortcode');
function abs_portal_shortcode() {
    global $wpdb;
    $table = $wpdb->prefix . 'abs_bookings';
    
    // Get bookings - match by logged-in email OR allow search
    $search_email = '';
    $bookings = [];
    
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $search_email = $user->user_email;
    }
    
    // Allow email search from URL
    if (!empty($_GET['email']) && filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
        $search_email = sanitize_email($_GET['email']);
    }
    
    if ($search_email) {
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE agent_email = %s ORDER BY booking_date DESC, booking_time DESC",
            $search_email
        ));
    }
    
    ob_start();
    ?>
    <div class="abs-portal">
        <div class="portal-header">
            <div>
                <h1>📋 My Bookings</h1>
                <p><?php echo $search_email ? "Showing bookings for: <strong>$search_email</strong>" : 'Enter your email to find bookings'; ?></p>
            </div>
            <div class="portal-actions">
                <a href="<?php echo home_url('/book-a-shoot/'); ?>" class="abs-btn-primary">+ New Booking</a>
                <?php if (is_user_logged_in()): ?>
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="abs-btn-secondary">Logout</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Email Search -->
        <form class="email-search" method="get">
            <input type="email" name="email" placeholder="Enter your booking email..." value="<?php echo esc_attr($search_email); ?>">
            <button type="submit" class="abs-btn-primary">Find Bookings</button>
        </form>

        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>No bookings found</h3>
                <p><?php echo $search_email ? "No bookings found for this email." : "Enter your email above to find your bookings."; ?></p>
                <a href="<?php echo home_url('/book-a-shoot/'); ?>" class="abs-btn-primary">Book Now</a>
            </div>
        <?php else: ?>
            <div class="portal-stats">
                <div class="stat"><span class="num"><?php echo count($bookings); ?></span><span class="label">Total</span></div>
                <div class="stat"><span class="num"><?php echo count(array_filter($bookings, function($b) { return strtotime($b->booking_date) >= strtotime('today'); })); ?></span><span class="label">Upcoming</span></div>
                <div class="stat"><span class="num"><?php echo count(array_filter($bookings, function($b) { return $b->status === 'cancelled'; })); ?></span><span class="label">Cancelled</span></div>
            </div>
            <div class="bookings-grid">
                <?php foreach ($bookings as $b): 
                    $is_past = strtotime($b->booking_date) < strtotime('today');
                    $addons = $b->addons ? json_decode($b->addons, true) : [];
                ?>
                <div class="booking-card <?php echo $is_past ? 'past' : 'upcoming'; ?> <?php echo $b->status === 'cancelled' ? 'cancelled' : ''; ?>">
                    <div class="card-header">
                        <span class="badge <?php echo $b->booking_type; ?>"><?php echo ucfirst($b->booking_type); ?></span>
                        <span class="badge status-<?php echo $b->status; ?>"><?php echo ucfirst($b->status); ?></span>
                    </div>
                    <div class="card-body">
                        <h3><?php echo esc_html($b->property_address); ?></h3>
                        <div class="booking-meta">
                            <span>📅 <?php echo date('M j, Y', strtotime($b->booking_date)); ?></span>
                            <span>🕐 <?php echo date('g:i A', strtotime($b->booking_time)); ?></span>
                        </div>
                        <?php if (!empty($addons)): ?>
                        <div class="booking-addons">
                            <?php foreach ($addons as $addon): ?>
                            <span class="addon-tag"><?php echo esc_html($addon); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <span class="booking-id">#<?php echo $b->id; ?></span>
                        <?php if (!$is_past && $b->status !== 'cancelled'): ?>
                        <button class="btn-cancel" onclick="absCancel(<?php echo $b->id; ?>, '<?php echo esc_js($search_email); ?>')">Cancel</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function absCancel(id, email) {
        if (!confirm('Cancel this booking?')) return;
        jQuery.post(absData.ajaxUrl, {
            action: 'abs_cancel_booking',
            nonce: absData.nonce,
            id: id,
            email: email
        }, function(r) {
            if (r.success) location.reload();
            else alert(r.data);
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================
// AJAX HANDLERS
// ============================================
add_action('wp_ajax_abs_submit_booking', 'abs_ajax_submit_booking');
add_action('wp_ajax_nopriv_abs_submit_booking', 'abs_ajax_submit_booking');
function abs_ajax_submit_booking() {
    check_ajax_referer('abs_booking', 'nonce');
    
    global $wpdb;
    $table = $wpdb->prefix . 'abs_bookings';
    
    // Ensure table
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        abs_create_table();
    }
    
    // Validate
    $required = ['booking_type', 'agent_name', 'agent_email', 'agent_mobile', 'property_address', 'booking_date', 'booking_time', 'property_access'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            wp_send_json_error("Missing: $field");
        }
    }
    
    // Addons
    $addons = [];
    if (!empty($_POST['addon_drone'])) $addons[] = 'Drone';
    if (!empty($_POST['addon_dusk'])) $addons[] = 'Dusk';
    if (!empty($_POST['addon_proofing'])) $addons[] = 'Proofing';
    if (!empty($_POST['addon_extra'])) $addons[] = 'Extra Images';
    
    $data = [
        'booking_type' => sanitize_text_field($_POST['booking_type']),
        'agent_name' => sanitize_text_field($_POST['agent_name']),
        'agent_email' => sanitize_email($_POST['agent_email']),
        'agent_mobile' => sanitize_text_field($_POST['agent_mobile']),
        'property_address' => sanitize_text_field($_POST['property_address']),
        'booking_date' => sanitize_text_field($_POST['booking_date']),
        'booking_time' => sanitize_text_field($_POST['booking_time']),
        'addons' => json_encode($addons),
        'property_access' => sanitize_text_field($_POST['property_access']),
        'access_details' => sanitize_textarea_field($_POST['access_details'] ?? ''),
        'office_location' => sanitize_text_field($_POST['office_location'] ?? ''),
        'keysafe_code' => sanitize_text_field($_POST['keysafe_code'] ?? ''),
        'vendor_name' => sanitize_text_field($_POST['vendor_name'] ?? ''),
        'vendor_contact' => sanitize_text_field($_POST['vendor_contact'] ?? ''),
        'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        'status' => 'confirmed',
        'submit_lat' => floatval($_POST['submit_lat'] ?? 0) ?: null,
        'submit_lng' => floatval($_POST['submit_lng'] ?? 0) ?: null,
        'submit_location' => sanitize_text_field($_POST['submit_location'] ?? '')
    ];
    
    $result = $wpdb->insert($table, $data);
    
    if ($result === false) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
    }
    
    $booking_id = $wpdb->insert_id;
    
    if ($booking_id) {
        abs_send_confirmation_email($booking_id, $data);
        wp_send_json_success(['id' => $booking_id, 'data' => $data]);
    } else {
        wp_send_json_error('Save failed');
    }
}

add_action('wp_ajax_abs_cancel_booking', 'abs_ajax_cancel_booking');
add_action('wp_ajax_nopriv_abs_cancel_booking', 'abs_ajax_cancel_booking');
function abs_ajax_cancel_booking() {
    check_ajax_referer('abs_booking', 'nonce');
    
    global $wpdb;
    $table = $wpdb->prefix . 'abs_bookings';
    $id = intval($_POST['id']);
    $email = sanitize_email($_POST['email'] ?? '');
    
    // Check ownership
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND agent_email = %s", $id, $email));
    
    if (!$booking && !current_user_can('manage_options')) {
        wp_send_json_error('Not authorized');
    }
    
    $wpdb->update($table, ['status' => 'cancelled'], ['id' => $id]);
    wp_send_json_success('Cancelled');
}

// Admin status update
add_action('wp_ajax_abs_update_status', 'abs_ajax_update_status');
function abs_ajax_update_status() {
    check_ajax_referer('abs_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('No permission');
    
    global $wpdb;
    $table = $wpdb->prefix . 'abs_bookings';
    $id = intval($_POST['id']);
    $status = sanitize_text_field($_POST['status']);
    
    $wpdb->update($table, ['status' => $status], ['id' => $id]);
    wp_send_json_success('Updated');
}

// Admin delete
add_action('wp_ajax_abs_delete_booking', 'abs_ajax_delete_booking');
function abs_ajax_delete_booking() {
    check_ajax_referer('abs_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('No permission');
    
    global $wpdb;
    $table = $wpdb->prefix . 'abs_bookings';
    $id = intval($_POST['id']);
    
    $wpdb->delete($table, ['id' => $id]);
    wp_send_json_success('Deleted');
}

// Export CSV
add_action('wp_ajax_abs_export_csv', 'abs_ajax_export_csv');
function abs_ajax_export_csv() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    
    global $wpdb;
    $table = $wpdb->prefix . 'abs_bookings';
    $bookings = $wpdb->get_results("SELECT * FROM $table ORDER BY booking_date DESC", ARRAY_A);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="bookings-' . date('Y-m-d') . '.csv"');
    
    $out = fopen('php://output', 'w');
    if (!empty($bookings)) {
        fputcsv($out, array_keys($bookings[0]));
        foreach ($bookings as $row) {
            fputcsv($out, $row);
        }
    }
    fclose($out);
    exit;
}

// Test API Key
add_action('wp_ajax_abs_test_api_key', 'abs_ajax_test_api_key');
function abs_ajax_test_api_key() {
    check_ajax_referer('abs_admin', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('No permission');
    
    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    
    if (empty($api_key)) {
        wp_send_json_error([
            'message' => 'API key is required',
            'status' => 'empty'
        ]);
    }
    
    // Test using Google Geocoding API
    $test_address = 'Sydney Opera House, Sydney NSW, Australia';
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
        'address' => $test_address,
        'key' => $api_key
    ]);
    
    $response = wp_remote_get($url, ['timeout' => 10]);
    
    if (is_wp_error($response)) {
        wp_send_json_error([
            'message' => 'Connection error: ' . $response->get_error_message(),
            'status' => 'error',
            'details' => 'Unable to reach Google Maps API'
        ]);
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!$data) {
        wp_send_json_error([
            'message' => 'Invalid response from API',
            'status' => 'error',
            'details' => 'Could not parse API response'
        ]);
    }
    
    // Check response status
    if ($data['status'] === 'OK') {
        $result = $data['results'][0] ?? null;
        wp_send_json_success([
            'message' => 'API key is valid and working!',
            'status' => 'valid',
            'details' => [
                'test_address' => $test_address,
                'formatted_address' => $result['formatted_address'] ?? 'N/A',
                'location' => $result['geometry']['location'] ?? null,
                'api_status' => $data['status']
            ]
        ]);
    } elseif ($data['status'] === 'REQUEST_DENIED') {
        wp_send_json_error([
            'message' => 'API key is invalid or restricted',
            'status' => 'denied',
            'details' => $data['error_message'] ?? 'The API key is not authorized for this service'
        ]);
    } elseif ($data['status'] === 'OVER_QUERY_LIMIT') {
        wp_send_json_error([
            'message' => 'API quota exceeded',
            'status' => 'quota',
            'details' => 'Your API key has exceeded the daily quota limit'
        ]);
    } else {
        wp_send_json_error([
            'message' => 'API test failed: ' . $data['status'],
            'status' => 'error',
            'details' => $data['error_message'] ?? 'Unknown error occurred'
        ]);
    }
}

// ============================================
// EMAIL
// ============================================
/**
 * Send booking confirmation email
 * 
 * IMPORTANT: This function ONLY sends booking confirmation details.
 * It does NOT:
 * - Create WordPress user accounts
 * - Send login credentials or passwords
 * - Share sensitive authentication information
 * 
 * Safe to forward to managers - contains NO login information.
 * 
 * @param int $id Booking ID
 * @param array $data Booking data
 */
function abs_send_confirmation_email($id, $data) {
    $to = $data['agent_email'];
    $subject = str_replace('{address}', $data['property_address'], get_option('abs_email_subject', '📷 Booking Confirmed - {address}'));
    
    $message = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
        <div style='background:linear-gradient(135deg,#2563eb,#1e40af);color:white;padding:30px;text-align:center;border-radius:8px 8px 0 0;'>
            <h1 style='margin:0;'>📸 Booking Confirmed</h1>
        </div>
        <div style='padding:30px;background:#f8fafc;'>
            <h2>Booking #{$id}</h2>
            <p><strong>Type:</strong> " . ucfirst($data['booking_type']) . "</p>
            <p><strong>Address:</strong> {$data['property_address']}</p>
            <p><strong>Date:</strong> " . date('l, F j, Y', strtotime($data['booking_date'])) . "</p>
            <p><strong>Time:</strong> " . date('g:i A', strtotime($data['booking_time'])) . "</p>
            <p><strong>Access:</strong> " . ucfirst(str_replace('_', ' ', $data['property_access'])) . "</p>
        </div>
    </div>";
    
    wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    wp_mail(get_option('abs_admin_email'), "New Booking #{$id}", $message, ['Content-Type: text/html; charset=UTF-8']);
}

// ============================================
// ADMIN MENU
// ============================================
add_action('admin_menu', 'abs_admin_menu');
function abs_admin_menu() {
    add_menu_page('Bookings', 'Bookings', 'manage_options', 'abs-dashboard', 'abs_admin_dashboard', 'dashicons-calendar-alt', 30);
    add_submenu_page('abs-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'abs-dashboard', 'abs_admin_dashboard');
    add_submenu_page('abs-dashboard', 'All Bookings', 'All Bookings', 'manage_options', 'abs-bookings', 'abs_admin_bookings');
    add_submenu_page('abs-dashboard', 'User Management', 'User Management', 'manage_options', 'abs-users', 'abs_admin_users');
    add_submenu_page('abs-dashboard', 'Settings', 'Settings', 'manage_options', 'abs-settings', 'abs_admin_settings');
}

// ============================================
// ADMIN DASHBOARD (ENHANCED)
// ============================================
function abs_admin_dashboard() {
    global $wpdb;
    $table = $wpdb->prefix . 'abs_bookings';
    
    // Stats
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $today = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE booking_date = %s", date('Y-m-d')));
    $week = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE booking_date >= %s", date('Y-m-d', strtotime('-7 days'))));
    $month = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE booking_date >= %s", date('Y-m-01')));
    $rentals = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE booking_type = 'rental'");
    $sales = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE booking_type = 'sales'");
    $confirmed = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'confirmed'");
    $cancelled = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'cancelled'");
    
    // Chart data (last 7 days)
    $chart_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE DATE(created_at) = %s", $date));
        $chart_data[] = ['date' => date('M j', strtotime($date)), 'count' => intval($count)];
    }
    
    // Recent bookings
    $recent = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 10");
    ?>
    <div class="wrap abs-dashboard">
        <h1>📊 Booking Dashboard</h1>
        
        <div class="abs-stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo $total; ?></div><div class="stat-label">Total Bookings</div></div>
            <div class="stat-card highlight"><div class="stat-value"><?php echo $today; ?></div><div class="stat-label">Today</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $week; ?></div><div class="stat-label">This Week</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $month; ?></div><div class="stat-label">This Month</div></div>
        </div>
        
        <div class="abs-dashboard-grid">
            <div class="abs-card">
                <h2>📈 Bookings (Last 7 Days)</h2>
                <div class="mini-chart">
                    <?php foreach ($chart_data as $d): ?>
                    <div class="chart-bar">
                        <div class="bar" style="height: <?php echo max(5, $d['count'] * 20); ?>px;" title="<?php echo $d['count']; ?>"></div>
                        <span class="label"><?php echo $d['date']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="abs-card">
                <h2>📊 Breakdown</h2>
                <div class="breakdown-grid">
                    <div class="breakdown-item"><span class="dot rental"></span>Rentals: <strong><?php echo $rentals; ?></strong></div>
                    <div class="breakdown-item"><span class="dot sales"></span>Sales: <strong><?php echo $sales; ?></strong></div>
                    <div class="breakdown-item"><span class="dot confirmed"></span>Confirmed: <strong><?php echo $confirmed; ?></strong></div>
                    <div class="breakdown-item"><span class="dot cancelled"></span>Cancelled: <strong><?php echo $cancelled; ?></strong></div>
                </div>
            </div>
        </div>
        
        <div class="abs-card">
            <div class="card-header">
                <h2>📋 Recent Bookings</h2>
                <a href="<?php echo admin_url('admin.php?page=abs-bookings'); ?>" class="button">View All</a>
            </div>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr><th>ID</th><th>Type</th><th>Address</th><th>Agent</th><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $b): ?>
                    <tr>
                        <td>#<?php echo $b->id; ?></td>
                        <td><span class="type-badge <?php echo $b->booking_type; ?>"><?php echo ucfirst($b->booking_type); ?></span></td>
                        <td><?php echo esc_html(substr($b->property_address, 0, 40)); ?><?php echo strlen($b->property_address) > 40 ? '...' : ''; ?></td>
                        <td><?php echo esc_html($b->agent_name); ?></td>
                        <td><?php echo date('M j', strtotime($b->booking_date)); ?></td>
                        <td><span class="status-badge <?php echo $b->status; ?>"><?php echo ucfirst($b->status); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="abs-quick-actions">
            <a href="<?php echo admin_url('admin-ajax.php?action=abs_export_csv'); ?>" class="button button-primary">📥 Export CSV</a>
            <a href="<?php echo admin_url('admin.php?page=abs-settings'); ?>" class="button">⚙️ Settings</a>
        </div>
    </div>
    <?php
}

// ============================================
// ALL BOOKINGS PAGE (ENHANCED)
// ============================================
function abs_admin_bookings() {
    global $wpdb;
    $table = $wpdb->prefix . 'abs_bookings';
    
    // Filters
    $where = "1=1";
    $status_filter = sanitize_text_field($_GET['status'] ?? '');
    $type_filter = sanitize_text_field($_GET['type'] ?? '');
    $search = sanitize_text_field($_GET['s'] ?? '');
    
    if ($status_filter) $where .= $wpdb->prepare(" AND status = %s", $status_filter);
    if ($type_filter) $where .= $wpdb->prepare(" AND booking_type = %s", $type_filter);
    if ($search) $where .= $wpdb->prepare(" AND (property_address LIKE %s OR agent_name LIKE %s OR agent_email LIKE %s)", "%$search%", "%$search%", "%$search%");
    
    $bookings = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY booking_date DESC, booking_time DESC");
    ?>
    <div class="wrap abs-bookings-page">
        <h1>📋 All Bookings <span class="count">(<?php echo count($bookings); ?>)</span></h1>
        
        <div class="tablenav top">
            <form method="get" class="abs-filters">
                <input type="hidden" name="page" value="abs-bookings">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="confirmed" <?php selected($status_filter, 'confirmed'); ?>>Confirmed</option>
                    <option value="completed" <?php selected($status_filter, 'completed'); ?>>Completed</option>
                    <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>>Cancelled</option>
                </select>
                <select name="type">
                    <option value="">All Types</option>
                    <option value="rental" <?php selected($type_filter, 'rental'); ?>>Rentals</option>
                    <option value="sales" <?php selected($type_filter, 'sales'); ?>>Sales</option>
                </select>
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search...">
                <button type="submit" class="button">Filter</button>
                <a href="<?php echo admin_url('admin.php?page=abs-bookings'); ?>" class="button">Clear</a>
            </form>
            <a href="<?php echo admin_url('admin-ajax.php?action=abs_export_csv'); ?>" class="button">📥 Export</a>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th width="70">Type</th>
                    <th>Address</th>
                    <th width="160">Agent</th>
                    <th width="80">Mobile</th>
                    <th width="100">Date/Time</th>
                    <th width="90">Add-ons</th>
                    <th width="80">Access</th>
                    <th width="80">Status</th>
                    <th width="100">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): 
                    $addons = $b->addons ? implode(', ', json_decode($b->addons, true)) : '-';
                ?>
                <tr>
                    <td>#<?php echo $b->id; ?></td>
                    <td><span class="type-badge <?php echo $b->booking_type; ?>"><?php echo ucfirst($b->booking_type); ?></span></td>
                    <td><strong><?php echo esc_html($b->property_address); ?></strong></td>
                    <td><?php echo esc_html($b->agent_name); ?><br><small><?php echo esc_html($b->agent_email); ?></small></td>
                    <td><small><?php echo esc_html($b->agent_mobile); ?></small></td>
                    <td><?php echo date('M j, Y', strtotime($b->booking_date)); ?><br><small><?php echo date('g:i A', strtotime($b->booking_time)); ?></small></td>
                    <td><small><?php echo esc_html($addons); ?></small></td>
                    <td><small><?php echo esc_html(ucfirst(str_replace('_', ' ', $b->property_access))); ?></small></td>
                    <td>
                        <select class="status-select" data-id="<?php echo $b->id; ?>">
                            <option value="confirmed" <?php selected($b->status, 'confirmed'); ?>>Confirmed</option>
                            <option value="completed" <?php selected($b->status, 'completed'); ?>>Completed</option>
                            <option value="cancelled" <?php selected($b->status, 'cancelled'); ?>>Cancelled</option>
                        </select>
                    </td>
                    <td>
                        <button class="button button-small btn-delete" data-id="<?php echo $b->id; ?>">🗑️</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// ============================================
// USER MANAGEMENT PAGE
// ============================================
function abs_admin_users() {
    // Disable WordPress new user email notifications
    add_filter('wp_new_user_notification_email_admin', '__return_false');
    add_filter('wp_new_user_notification_email', '__return_false');
    
    $message = '';
    $credentials = [];
    
    // Handle single user creation
    if (isset($_POST['abs_create_user'])) {
        check_admin_referer('abs_create_user');
        
        $name = sanitize_text_field($_POST['user_name']);
        $email = sanitize_email($_POST['user_email']);
        $username = sanitize_user($_POST['user_username'] ?: strtolower(str_replace(' ', '', $name)));
        $direct_line = sanitize_text_field($_POST['user_direct_line']);
        $telephone = sanitize_text_field($_POST['user_telephone']);
        
        if (!username_exists($username) && !email_exists($email)) {
            $password = wp_generate_password(12, false);
            $user_id = wp_create_user($username, $password, $email);
            
            if (!is_wp_error($user_id)) {
                wp_update_user(['ID' => $user_id, 'display_name' => $name, 'first_name' => $name]);
                $user = new WP_User($user_id);
                $user->set_role('real_estate_agent');
                
                // Save Direct Line and Telephone as user meta
                if ($direct_line) update_user_meta($user_id, 'abs_direct_line', $direct_line);
                if ($telephone) update_user_meta($user_id, 'abs_telephone', $telephone);
                
                $credentials[] = [
                    'name' => $name,
                    'email' => $email,
                    'username' => $username,
                    'password' => $password,
                    'direct_line' => $direct_line,
                    'telephone' => $telephone
                ];
                $message = '<div class="notice notice-success"><p>✅ User created successfully! Credentials shown below.</p></div>';
            } else {
                $message = '<div class="notice notice-error"><p>❌ Error: ' . $user_id->get_error_message() . '</p></div>';
            }
        } else {
            $message = '<div class="notice notice-error"><p>❌ Username or email already exists</p></div>';
        }
    }
    
    // Handle bulk import from CSV
    if (isset($_POST['abs_bulk_import']) && !empty($_FILES['agent_csv']['tmp_name'])) {
        check_admin_referer('abs_bulk_import');
        
        $file = $_FILES['agent_csv']['tmp_name'];
        $handle = fopen($file, 'r');
        $row = 0;
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row++;
            if ($row == 1) continue; // Skip header
            
            if (count($data) < 2) continue;
            
            $name = sanitize_text_field($data[0]);
            $email = sanitize_email($data[1]);
            $direct_line = isset($data[2]) ? sanitize_text_field($data[2]) : '';
            $telephone = isset($data[3]) ? sanitize_text_field($data[3]) : '';
            $username = sanitize_user(strtolower(str_replace(' ', '.', $name)));
            
            if (!username_exists($username) && !email_exists($email)) {
                $password = wp_generate_password(12, false);
                $user_id = wp_create_user($username, $password, $email);
                
                if (!is_wp_error($user_id)) {
                    wp_update_user(['ID' => $user_id, 'display_name' => $name, 'first_name' => $name]);
                    $user = new WP_User($user_id);
                    $user->set_role('real_estate_agent');
                    
                    // Save Direct Line and Telephone as user meta
                    if ($direct_line) update_user_meta($user_id, 'abs_direct_line', $direct_line);
                    if ($telephone) update_user_meta($user_id, 'abs_telephone', $telephone);
                    
                    $credentials[] = [
                        'name' => $name,
                        'email' => $email,
                        'username' => $username,
                        'password' => $password,
                        'direct_line' => $direct_line,
                        'telephone' => $telephone
                    ];
                }
            }
        }
        fclose($handle);
        
        if (!empty($credentials)) {
            $message = '<div class="notice notice-success"><p>✅ ' . count($credentials) . ' users created successfully! Credentials shown below.</p></div>';
        }
    }
    
    // Get existing agents
    $agents = get_users(['role' => 'real_estate_agent']);
    ?>
    <div class="wrap abs-users">
        <h1>👥 User Management</h1>
        <p>Create agent accounts without sending email notifications. Credentials can be distributed manually to managers.</p>
        
        <?php echo $message; ?>
        
        <?php if (!empty($credentials)): ?>
        <div class="notice notice-info" style="padding:20px; background:#fff; border-left:4px solid #2271b1;">
            <h2>🔑 New User Credentials - Save These!</h2>
            <p><strong>⚠️ IMPORTANT:</strong> No emails were sent. Please save and distribute these credentials manually.</p>
            <table class="widefat" style="margin-top:15px;">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Username</th><th>Password</th><th>Direct Line</th><th>Telephone</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($credentials as $cred): ?>
                    <tr>
                        <td><?php echo esc_html($cred['name']); ?></td>
                        <td><?php echo esc_html($cred['email']); ?></td>
                        <td><code><?php echo esc_html($cred['username']); ?></code></td>
                        <td><code style="background:#fef3c7; padding:4px 8px; font-size:14px;"><?php echo esc_html($cred['password']); ?></code></td>
                        <td><?php echo esc_html($cred['direct_line'] ?? '-'); ?></td>
                        <td><?php echo esc_html($cred['telephone'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button onclick="copyCredentials()" class="button button-primary" style="margin-top:15px;">📋 Copy to Clipboard</button>
        </div>
        <?php endif; ?>
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:30px; margin-top:30px;">
            <!-- Single User Creation -->
            <div class="abs-card">
                <h2>➕ Create Single User</h2>
                <form method="post">
                    <?php wp_nonce_field('abs_create_user'); ?>
                    <table class="form-table">
                        <tr><th>Name *</th><td><input type="text" name="user_name" required class="regular-text"></td></tr>
                        <tr><th>Email *</th><td><input type="email" name="user_email" required class="regular-text"></td></tr>
                        <tr><th>Direct Line</th><td><input type="text" name="user_direct_line" class="regular-text" placeholder="e.g. 03 9123 4567"></td></tr>
                        <tr><th>Telephone</th><td><input type="text" name="user_telephone" class="regular-text" placeholder="e.g. 0400 123 456"></td></tr>
                        <tr><th>Username</th><td><input type="text" name="user_username" class="regular-text"><p class="description">Leave blank to auto-generate</p></td></tr>
                    </table>
                    <p class="submit">
                        <button type="submit" name="abs_create_user" class="button button-primary">Create User</button>
                    </p>
                    <div style="background:#fef3c7; padding:12px; border-radius:6px; margin-top:10px;">
                        <strong>ℹ️ Note:</strong> No email will be sent. Password will be displayed after creation.
                    </div>
                </form>
            </div>
            
            <!-- Bulk Import -->
            <div class="abs-card">
                <h2>📁 Bulk Import from CSV</h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('abs_bulk_import'); ?>
                    <p>Upload a CSV file with agent information.</p>
                    <p><strong>CSV Format:</strong><br>
                    <code>Name, Email, Direct Line, Telephone</code><br>
                    <small>First row should be headers. Direct Line and Telephone are optional.</small></p>
                    <p><input type="file" name="agent_csv" accept=".csv" required></p>
                    <p class="submit">
                        <button type="submit" name="abs_bulk_import" class="button button-primary">Import Users</button>
                    </p>
                    <div style="background:#fef3c7; padding:12px; border-radius:6px; margin-top:10px;">
                        <strong>ℹ️ Note:</strong> No emails will be sent. All credentials will be displayed after import.
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Existing Agents -->
        <div class="abs-card" style="margin-top:30px;">
            <h2>📋 Existing Agents (<?php echo count($agents); ?>)</h2>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Direct Line</th>
                        <th>Telephone</th>
                        <th>Username</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agents as $agent): 
                        $direct_line = get_user_meta($agent->ID, 'abs_direct_line', true);
                        $telephone = get_user_meta($agent->ID, 'abs_telephone', true);
                    ?>
                    <tr>
                        <td><?php echo $agent->ID; ?></td>
                        <td><?php echo esc_html($agent->display_name); ?></td>
                        <td><?php echo esc_html($agent->user_email); ?></td>
                        <td><?php echo esc_html($direct_line ?: '-'); ?></td>
                        <td><?php echo esc_html($telephone ?: '-'); ?></td>
                        <td><code><?php echo esc_html($agent->user_login); ?></code></td>
                        <td><?php echo date('M j, Y', strtotime($agent->user_registered)); ?></td>
                        <td><a href="<?php echo admin_url('user-edit.php?user_id=' . $agent->ID); ?>" class="button button-small">Edit</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($agents)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:40px;">No agents found. Create your first agent above.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    function copyCredentials() {
        let text = "AGENT LOGIN CREDENTIALS\n\n";
        <?php foreach ($credentials as $cred): ?>
        text += "Name: <?php echo esc_js($cred['name']); ?>\n";
        text += "Email: <?php echo esc_js($cred['email']); ?>\n";
        text += "Username: <?php echo esc_js($cred['username']); ?>\n";
        text += "Password: <?php echo esc_js($cred['password']); ?>\n";
        <?php if (!empty($cred['direct_line'])): ?>
        text += "Direct Line: <?php echo esc_js($cred['direct_line']); ?>\n";
        <?php endif; ?>
        <?php if (!empty($cred['telephone'])): ?>
        text += "Telephone: <?php echo esc_js($cred['telephone']); ?>\n";
        <?php endif; ?>
        text += "\n";
        <?php endforeach; ?>
        
        navigator.clipboard.writeText(text).then(function() {
            alert('✅ Credentials copied to clipboard!');
        });
    }
    </script>
    <?php
}

// ============================================
// SETTINGS PAGE (ENHANCED)
// ============================================
function abs_admin_settings() {
    if (isset($_POST['abs_save'])) {
        check_admin_referer('abs_settings');
        update_option('abs_business_name', sanitize_text_field($_POST['abs_business_name']));
        update_option('abs_business_address', sanitize_text_field($_POST['abs_business_address']));
        update_option('abs_business_phone', sanitize_text_field($_POST['abs_business_phone']));
        update_option('abs_google_api_key', sanitize_text_field($_POST['abs_google_api_key']));
        update_option('abs_admin_email', sanitize_email($_POST['abs_admin_email']));
        update_option('abs_drone_price', intval($_POST['abs_drone_price']));
        update_option('abs_dusk_price', intval($_POST['abs_dusk_price']));
        update_option('abs_proofing_price', intval($_POST['abs_proofing_price']));
        update_option('abs_rental_offices', sanitize_textarea_field($_POST['abs_rental_offices']));
        update_option('abs_sales_offices', sanitize_textarea_field($_POST['abs_sales_offices']));
        update_option('abs_time_slots', sanitize_textarea_field($_POST['abs_time_slots']));
        update_option('abs_email_subject', sanitize_text_field($_POST['abs_email_subject']));
        echo '<div class="notice notice-success"><p>✅ Settings saved!</p></div>';
    }
    ?>
    <div class="wrap abs-settings">
        <h1>⚙️ Booking Settings</h1>
        <form method="post">
            <?php wp_nonce_field('abs_settings'); ?>
            
            <div class="settings-section">
                <h2>🏢 Business Information</h2>
                <table class="form-table">
                    <tr><th>Business Name</th><td><input type="text" name="abs_business_name" value="<?php echo esc_attr(get_option('abs_business_name', 'OnlyFotos')); ?>" class="regular-text"></td></tr>
                    <tr><th>Business Address</th><td><input type="text" name="abs_business_address" value="<?php echo esc_attr(get_option('abs_business_address', '3 Toorak Rd, South Yarra, VIC 3141')); ?>" class="large-text"></td></tr>
                    <tr><th>Business Phone</th><td><input type="text" name="abs_business_phone" value="<?php echo esc_attr(get_option('abs_business_phone')); ?>" class="regular-text"></td></tr>
                </table>
            </div>
            
            <div class="settings-section">
                <h2>🔑 API Key Testing</h2>
                <table class="form-table">
                    <tr>
                        <th>Google Maps API Key</th>
                        <td>
                            <input type="text" id="abs_google_api_key" name="abs_google_api_key" value="<?php echo esc_attr(get_option('abs_google_api_key')); ?>" class="large-text">
                            <p class="description">For address autocomplete</p>
                            
                            <!-- Real-time Testing Interface -->
                            <div class="abs-api-tester" style="margin-top: 15px;">
                                <div class="api-test-controls">
                                    <button type="button" id="abs_test_api_btn" class="button button-secondary">
                                        <span class="dashicons dashicons-update"></span> Test API Key
                                    </button>
                                    <span id="abs_api_status" class="api-status"></span>
                                </div>
                                
                                <div id="abs_api_result" class="api-result" style="display: none;">
                                    <div class="result-header">
                                        <span class="result-icon"></span>
                                        <span class="result-message"></span>
                                    </div>
                                    <div class="result-details" style="display: none;">
                                        <h4>Response Details:</h4>
                                        <div class="details-content"></div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="settings-section">
                <h2>💰 Pricing</h2>
                <table class="form-table">
                    <tr><th>Drone ($)</th><td><input type="number" name="abs_drone_price" value="<?php echo esc_attr(get_option('abs_drone_price', 100)); ?>"></td></tr>
                    <tr><th>Dusk ($)</th><td><input type="number" name="abs_dusk_price" value="<?php echo esc_attr(get_option('abs_dusk_price', 150)); ?>"></td></tr>
                    <tr><th>Proofing ($)</th><td><input type="number" name="abs_proofing_price" value="<?php echo esc_attr(get_option('abs_proofing_price', 75)); ?>"></td></tr>
                </table>
            </div>
            
            <div class="settings-section">
                <h2>⏰ Time Slots</h2>
                <table class="form-table">
                    <tr><th>Available Slots</th><td><textarea name="abs_time_slots" rows="6" class="large-text"><?php echo esc_textarea(get_option('abs_time_slots', "09:00\n10:00\n11:00\n12:00\n13:00\n14:00\n15:00\n16:00\n17:00")); ?></textarea><p class="description">One per line in 24h format (e.g., 09:00, 14:30)</p></td></tr>
                </table>
            </div>
            
            <div class="settings-section">
                <h2>🏢 Offices</h2>
                <table class="form-table">
                    <tr><th>Rental Offices</th><td><textarea name="abs_rental_offices" rows="3" class="large-text"><?php echo esc_textarea(get_option('abs_rental_offices')); ?></textarea><p class="description">One per line</p></td></tr>
                    <tr><th>Sales Offices</th><td><textarea name="abs_sales_offices" rows="4" class="large-text"><?php echo esc_textarea(get_option('abs_sales_offices')); ?></textarea></td></tr>
                </table>
            </div>
            
            <div class="settings-section">
                <h2>📧 Email</h2>
                <table class="form-table">
                    <tr><th>Admin Email</th><td><input type="email" name="abs_admin_email" value="<?php echo esc_attr(get_option('abs_admin_email')); ?>" class="regular-text"></td></tr>
                    <tr><th>Email Subject</th><td><input type="text" name="abs_email_subject" value="<?php echo esc_attr(get_option('abs_email_subject', '📷 Booking Confirmed - {address}')); ?>" class="large-text"><p class="description">Use {address} for property address</p></td></tr>
                </table>
            </div>
            
            <p class="submit"><button type="submit" name="abs_save" class="button button-primary button-large">💾 Save Settings</button></p>
        </form>
        
        <hr>
        <h2>📌 Shortcodes</h2>
        <table class="widefat" style="max-width:600px;">
            <tr><td><code>[abs_booking]</code></td><td>Booking form</td></tr>
            <tr><td><code>[abs_portal]</code></td><td>Agent portal</td></tr>
        </table>
    </div>
    <?php
}
