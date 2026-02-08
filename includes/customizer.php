<?php
/**
 * Customizer - Real-time Color & Text Customization System
 * Allows admins to customize colors, text, and typography with live preview
 */

if (!defined('ABSPATH')) exit;

// ============================================
// DEFAULT CUSTOMIZATION OPTIONS
// ============================================
function abs_customizer_defaults() {
    return [
        // Colors
        'primary_color' => '#2563eb',
        'secondary_color' => '#1e40af',
        'accent_color' => '#3b82f6',
        'success_color' => '#10b981',
        'error_color' => '#ef4444',
        'hero_bg_start' => '#2563eb',
        'hero_bg_end' => '#1e40af',
        'card_bg_color' => '#ffffff',
        'form_bg_color' => '#f8fafc',
        'text_primary' => '#1e293b',
        'text_secondary' => '#64748b',
        'text_muted' => '#94a3b8',
        
        // Text Content
        'hero_heading' => '📸 Book a Photo Shoot',
        'hero_subheading' => 'Professional real estate photography',
        'rental_card_title' => 'Rental Photography',
        'rental_card_desc' => 'Fast and professional rental photography',
        'sales_card_title' => 'Sales Photography',
        'sales_card_desc' => 'Premium photography for property sales',
        'submit_button_text' => 'Confirm Booking →',
        'confirmation_heading' => 'Booking Confirmed!',
        'confirmation_message' => 'Your shoot is scheduled.',
        
        // Typography
        'heading_font' => 'system-ui, -apple-system, sans-serif',
        'body_font' => 'system-ui, -apple-system, sans-serif',
        'base_font_size' => '16px',
        'heading_font_weight' => '700',
        'body_font_weight' => '400',
    ];
}

// ============================================
// ADMIN MENU
// ============================================
add_action('admin_menu', 'abs_customizer_menu', 20);
function abs_customizer_menu() {
    add_submenu_page(
        'abs-dashboard',
        'Theme Customizer',
        'Theme Customizer',
        'manage_options',
        'abs-customizer',
        'abs_customizer_page'
    );
}

// ============================================
// ENQUEUE ASSETS
// ============================================
add_action('admin_enqueue_scripts', 'abs_customizer_assets');
function abs_customizer_assets($hook) {
    if ($hook !== 'bookings_page_abs-customizer') return;
    
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    
    wp_enqueue_style('abs-customizer', ABS_URL . 'assets/css/customizer.css', [], ABS_VERSION);
    wp_enqueue_script('abs-customizer', ABS_URL . 'assets/js/customizer.js', ['jquery', 'wp-color-picker'], ABS_VERSION, true);
    
    wp_localize_script('abs-customizer', 'absCustomizer', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('abs_customizer'),
        'previewUrl' => home_url('/book-a-shoot/'),
        'defaults' => abs_customizer_defaults(),
        'current' => abs_get_customizations()
    ]);
}

// ============================================
// GET CUSTOMIZATIONS
// ============================================
function abs_get_customizations() {
    $defaults = abs_customizer_defaults();
    $saved = get_option('abs_customizations', []);
    return array_merge($defaults, $saved);
}

// ============================================
// CUSTOMIZER ADMIN PAGE
// ============================================
function abs_customizer_page() {
    $customs = abs_get_customizations();
    ?>
    <div class="abs-customizer-wrapper">
        <div class="abs-customizer-header">
            <h1>🎨 Theme Customizer</h1>
            <div class="header-actions">
                <button type="button" class="button" id="abs-reset-customizer">Reset to Defaults</button>
                <button type="button" class="button button-primary" id="abs-save-customizer">💾 Save Changes</button>
            </div>
        </div>
        
        <div class="abs-customizer-container">
            <!-- Controls Panel -->
            <div class="abs-customizer-controls">
                <div class="customizer-tabs">
                    <button class="tab-btn active" data-tab="colors">🎨 Colors</button>
                    <button class="tab-btn" data-tab="text">📝 Text</button>
                    <button class="tab-btn" data-tab="typography">🔤 Typography</button>
                </div>
                
                <div class="customizer-sections">
                    <!-- COLORS TAB -->
                    <div class="customizer-section active" id="tab-colors">
                        <div class="section-group">
                            <h3>Brand Colors</h3>
                            <div class="control-item">
                                <label>Primary Color</label>
                                <input type="text" class="color-picker" name="primary_color" value="<?php echo esc_attr($customs['primary_color']); ?>" data-default-color="#2563eb">
                                <p class="description">Main brand color for buttons and links</p>
                            </div>
                            <div class="control-item">
                                <label>Secondary Color</label>
                                <input type="text" class="color-picker" name="secondary_color" value="<?php echo esc_attr($customs['secondary_color']); ?>" data-default-color="#1e40af">
                                <p class="description">Secondary accent color</p>
                            </div>
                            <div class="control-item">
                                <label>Accent Color</label>
                                <input type="text" class="color-picker" name="accent_color" value="<?php echo esc_attr($customs['accent_color']); ?>" data-default-color="#3b82f6">
                                <p class="description">Hover states and highlights</p>
                            </div>
                        </div>
                        
                        <div class="section-group">
                            <h3>Status Colors</h3>
                            <div class="control-item">
                                <label>Success Color</label>
                                <input type="text" class="color-picker" name="success_color" value="<?php echo esc_attr($customs['success_color']); ?>" data-default-color="#10b981">
                                <p class="description">Success messages and confirmations</p>
                            </div>
                            <div class="control-item">
                                <label>Error Color</label>
                                <input type="text" class="color-picker" name="error_color" value="<?php echo esc_attr($customs['error_color']); ?>" data-default-color="#ef4444">
                                <p class="description">Error messages and warnings</p>
                            </div>
                        </div>
                        
                        <div class="section-group">
                            <h3>Background Colors</h3>
                            <div class="control-item">
                                <label>Hero Gradient Start</label>
                                <input type="text" class="color-picker" name="hero_bg_start" value="<?php echo esc_attr($customs['hero_bg_start']); ?>" data-default-color="#2563eb">
                            </div>
                            <div class="control-item">
                                <label>Hero Gradient End</label>
                                <input type="text" class="color-picker" name="hero_bg_end" value="<?php echo esc_attr($customs['hero_bg_end']); ?>" data-default-color="#1e40af">
                            </div>
                            <div class="control-item">
                                <label>Card Background</label>
                                <input type="text" class="color-picker" name="card_bg_color" value="<?php echo esc_attr($customs['card_bg_color']); ?>" data-default-color="#ffffff">
                            </div>
                            <div class="control-item">
                                <label>Form Background</label>
                                <input type="text" class="color-picker" name="form_bg_color" value="<?php echo esc_attr($customs['form_bg_color']); ?>" data-default-color="#f8fafc">
                            </div>
                        </div>
                        
                        <div class="section-group">
                            <h3>Text Colors</h3>
                            <div class="control-item">
                                <label>Primary Text</label>
                                <input type="text" class="color-picker" name="text_primary" value="<?php echo esc_attr($customs['text_primary']); ?>" data-default-color="#1e293b">
                            </div>
                            <div class="control-item">
                                <label>Secondary Text</label>
                                <input type="text" class="color-picker" name="text_secondary" value="<?php echo esc_attr($customs['text_secondary']); ?>" data-default-color="#64748b">
                            </div>
                            <div class="control-item">
                                <label>Muted Text</label>
                                <input type="text" class="color-picker" name="text_muted" value="<?php echo esc_attr($customs['text_muted']); ?>" data-default-color="#94a3b8">
                            </div>
                        </div>
                    </div>
                    
                    <!-- TEXT TAB -->
                    <div class="customizer-section" id="tab-text">
                        <div class="section-group">
                            <h3>Hero Section</h3>
                            <div class="control-item">
                                <label>Main Heading</label>
                                <input type="text" class="text-input" name="hero_heading" value="<?php echo esc_attr($customs['hero_heading']); ?>">
                            </div>
                            <div class="control-item">
                                <label>Subheading</label>
                                <input type="text" class="text-input" name="hero_subheading" value="<?php echo esc_attr($customs['hero_subheading']); ?>">
                            </div>
                        </div>
                        
                        <div class="section-group">
                            <h3>Service Cards</h3>
                            <div class="control-item">
                                <label>Rental Card Title</label>
                                <input type="text" class="text-input" name="rental_card_title" value="<?php echo esc_attr($customs['rental_card_title']); ?>">
                            </div>
                            <div class="control-item">
                                <label>Rental Card Description</label>
                                <input type="text" class="text-input" name="rental_card_desc" value="<?php echo esc_attr($customs['rental_card_desc']); ?>">
                            </div>
                            <div class="control-item">
                                <label>Sales Card Title</label>
                                <input type="text" class="text-input" name="sales_card_title" value="<?php echo esc_attr($customs['sales_card_title']); ?>">
                            </div>
                            <div class="control-item">
                                <label>Sales Card Description</label>
                                <input type="text" class="text-input" name="sales_card_desc" value="<?php echo esc_attr($customs['sales_card_desc']); ?>">
                            </div>
                        </div>
                        
                        <div class="section-group">
                            <h3>Buttons & Messages</h3>
                            <div class="control-item">
                                <label>Submit Button Text</label>
                                <input type="text" class="text-input" name="submit_button_text" value="<?php echo esc_attr($customs['submit_button_text']); ?>">
                            </div>
                            <div class="control-item">
                                <label>Confirmation Heading</label>
                                <input type="text" class="text-input" name="confirmation_heading" value="<?php echo esc_attr($customs['confirmation_heading']); ?>">
                            </div>
                            <div class="control-item">
                                <label>Confirmation Message</label>
                                <input type="text" class="text-input" name="confirmation_message" value="<?php echo esc_attr($customs['confirmation_message']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- TYPOGRAPHY TAB -->
                    <div class="customizer-section" id="tab-typography">
                        <div class="section-group">
                            <h3>Font Families</h3>
                            <div class="control-item">
                                <label>Heading Font</label>
                                <select class="text-input" name="heading_font">
                                    <option value="system-ui, -apple-system, sans-serif" <?php selected($customs['heading_font'], 'system-ui, -apple-system, sans-serif'); ?>>System Default</option>
                                    <option value="'Inter', sans-serif" <?php selected($customs['heading_font'], "'Inter', sans-serif"); ?>>Inter</option>
                                    <option value="'Roboto', sans-serif" <?php selected($customs['heading_font'], "'Roboto', sans-serif"); ?>>Roboto</option>
                                    <option value="'Outfit', sans-serif" <?php selected($customs['heading_font'], "'Outfit', sans-serif"); ?>>Outfit</option>
                                    <option value="'Poppins', sans-serif" <?php selected($customs['heading_font'], "'Poppins', sans-serif"); ?>>Poppins</option>
                                    <option value="Georgia, serif" <?php selected($customs['heading_font'], 'Georgia, serif'); ?>>Georgia</option>
                                </select>
                            </div>
                            <div class="control-item">
                                <label>Body Font</label>
                                <select class="text-input" name="body_font">
                                    <option value="system-ui, -apple-system, sans-serif" <?php selected($customs['body_font'], 'system-ui, -apple-system, sans-serif'); ?>>System Default</option>
                                    <option value="'Inter', sans-serif" <?php selected($customs['body_font'], "'Inter', sans-serif"); ?>>Inter</option>
                                    <option value="'Roboto', sans-serif" <?php selected($customs['body_font'], "'Roboto', sans-serif"); ?>>Roboto</option>
                                    <option value="'Outfit', sans-serif" <?php selected($customs['body_font'], "'Outfit', sans-serif"); ?>>Outfit</option>
                                    <option value="'Open Sans', sans-serif" <?php selected($customs['body_font'], "'Open Sans', sans-serif"); ?>>Open Sans</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="section-group">
                            <h3>Font Sizes & Weights</h3>
                            <div class="control-item">
                                <label>Base Font Size</label>
                                <select class="text-input" name="base_font_size">
                                    <option value="14px" <?php selected($customs['base_font_size'], '14px'); ?>>14px (Small)</option>
                                    <option value="16px" <?php selected($customs['base_font_size'], '16px'); ?>>16px (Medium)</option>
                                    <option value="18px" <?php selected($customs['base_font_size'], '18px'); ?>>18px (Large)</option>
                                </select>
                            </div>
                            <div class="control-item">
                                <label>Heading Font Weight</label>
                                <select class="text-input" name="heading_font_weight">
                                    <option value="600" <?php selected($customs['heading_font_weight'], '600'); ?>>600 (Semibold)</option>
                                    <option value="700" <?php selected($customs['heading_font_weight'], '700'); ?>>700 (Bold)</option>
                                    <option value="800" <?php selected($customs['heading_font_weight'], '800'); ?>>800 (Extra Bold)</option>
                                </select>
                            </div>
                            <div class="control-item">
                                <label>Body Font Weight</label>
                                <select class="text-input" name="body_font_weight">
                                    <option value="400" <?php selected($customs['body_font_weight'], '400'); ?>>400 (Regular)</option>
                                    <option value="500" <?php selected($customs['body_font_weight'], '500'); ?>>500 (Medium)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Preview Panel -->
            <div class="abs-customizer-preview">
                <div class="preview-header">
                    <span class="preview-label">Live Preview</span>
                    <a href="<?php echo home_url('/book-a-shoot/'); ?>" target="_blank" class="button button-small">Open in New Tab →</a>
                </div>
                <div class="preview-container">
                    <iframe id="abs-preview-frame" src="<?php echo home_url('/book-a-shoot/?customizer_preview=1'); ?>"></iframe>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ============================================
// AJAX: SAVE CUSTOMIZATIONS
// ============================================
add_action('wp_ajax_abs_save_customizations', 'abs_ajax_save_customizations');
function abs_ajax_save_customizations() {
    check_ajax_referer('abs_customizer', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('No permission');
    
    $data = $_POST['data'] ?? [];
    $allowed_keys = array_keys(abs_customizer_defaults());
    
    $sanitized = [];
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed_keys)) {
            $sanitized[$key] = sanitize_text_field($value);
        }
    }
    
    update_option('abs_customizations', $sanitized);
    wp_send_json_success('Saved successfully');
}

// ============================================
// AJAX: RESET CUSTOMIZATIONS
// ============================================
add_action('wp_ajax_abs_reset_customizations', 'abs_ajax_reset_customizations');
function abs_ajax_reset_customizations() {
    check_ajax_referer('abs_customizer', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('No permission');
    
    delete_option('abs_customizations');
    wp_send_json_success(['defaults' => abs_customizer_defaults()]);
}

// ============================================
// INJECT CSS VARIABLES INTO FRONTEND
// ============================================
add_action('wp_head', 'abs_inject_custom_css', 100);
function abs_inject_custom_css() {
    $customs = abs_get_customizations();
    ?>
    <style id="abs-custom-styles">
        :root {
            --abs-primary: <?php echo esc_attr($customs['primary_color']); ?>;
            --abs-secondary: <?php echo esc_attr($customs['secondary_color']); ?>;
            --abs-accent: <?php echo esc_attr($customs['accent_color']); ?>;
            --abs-success: <?php echo esc_attr($customs['success_color']); ?>;
            --abs-error: <?php echo esc_attr($customs['error_color']); ?>;
            --abs-hero-start: <?php echo esc_attr($customs['hero_bg_start']); ?>;
            --abs-hero-end: <?php echo esc_attr($customs['hero_bg_end']); ?>;
            --abs-card-bg: <?php echo esc_attr($customs['card_bg_color']); ?>;
            --abs-form-bg: <?php echo esc_attr($customs['form_bg_color']); ?>;
            --abs-text-primary: <?php echo esc_attr($customs['text_primary']); ?>;
            --abs-text-secondary: <?php echo esc_attr($customs['text_secondary']); ?>;
            --abs-text-muted: <?php echo esc_attr($customs['text_muted']); ?>;
            --abs-heading-font: <?php echo esc_attr($customs['heading_font']); ?>;
            --abs-body-font: <?php echo esc_attr($customs['body_font']); ?>;
            --abs-base-size: <?php echo esc_attr($customs['base_font_size']); ?>;
            --abs-heading-weight: <?php echo esc_attr($customs['heading_font_weight']); ?>;
            --abs-body-weight: <?php echo esc_attr($customs['body_font_weight']); ?>;
        }
    </style>
    <?php
}

// ============================================
// ENQUEUE PREVIEW SCRIPT ON FRONTEND
// ============================================
add_action('wp_enqueue_scripts', 'abs_customizer_preview_script');
function abs_customizer_preview_script() {
    if (!isset($_GET['customizer_preview'])) return;
    
    wp_enqueue_script('abs-customizer-preview', ABS_URL . 'assets/js/customizer-preview.js', ['jquery'], ABS_VERSION, true);
    wp_localize_script('abs-customizer-preview', 'absCustoms', abs_get_customizations());
}

// ============================================
// TEXT FILTERS FOR CUSTOMIZABLE CONTENT
// ============================================
add_filter('abs_hero_heading', 'abs_custom_text_filter');
add_filter('abs_hero_subheading', 'abs_custom_text_filter');
add_filter('abs_rental_card_title', 'abs_custom_text_filter');
add_filter('abs_rental_card_desc', 'abs_custom_text_filter');
add_filter('abs_sales_card_title', 'abs_custom_text_filter');
add_filter('abs_sales_card_desc', 'abs_custom_text_filter');
add_filter('abs_submit_button_text', 'abs_custom_text_filter');
add_filter('abs_confirmation_heading', 'abs_custom_text_filter');
add_filter('abs_confirmation_message', 'abs_custom_text_filter');

function abs_custom_text_filter($default, $key = '') {
    if (empty($key)) {
        // Get key from current filter
        $current_filter = current_filter();
        $key = str_replace('abs_', '', $current_filter);
    }
    
    $customs = abs_get_customizations();
    return $customs[$key] ?? $default;
}
