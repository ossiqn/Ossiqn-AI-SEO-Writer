<?php
if (!defined('ABSPATH')) exit;

/*
Plugin Name: Ossiqn Global AI Content Generator
Plugin URI: https://ossiqn.global
Description: AI Content Generation Platform | Free Version | Multi-language support
Version: 3.0.0
Author: Ossiqn Global Team
Author URI: https://ossiqn.global
License: GPL v2 or later
Text Domain: ossiqn-global
Domain Path: /languages
Requires at least: 6.0
Requires PHP: 8.0
*/

define('OSSIQN_GLOBAL_VERSION', '3.0.0');
define('OSSIQN_GLOBAL_PATH', plugin_dir_path(__FILE__));
define('OSSIQN_GLOBAL_URL', plugin_dir_url(__FILE__));
define('OSSIQN_GLOBAL_FILE', __FILE__);

class Ossiqn_Global_Enterprise {
    private static $instance;
    private $supported_languages = array('en_US', 'tr_TR', 'es_ES', 'fr_FR', 'de_DE', 'ar_AR', 'pt_BR', 'ja_JP', 'zh_CN', 'ru_RU');
    private $supported_currencies = array('USD', 'EUR', 'GBP', 'TRY', 'BRL', 'JPY', 'CNY', 'RUB', 'AED', 'SAR');
    private $ai_providers = array('groq', 'openai', 'cohere', 'anthropic', 'mistral');

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        load_plugin_textdomain('ossiqn-global', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        add_action('plugins_loaded', array($this, 'ossiqn_init'));
        add_action('admin_menu', array($this, 'ossiqn_register_main_menu'));
        add_action('admin_enqueue_scripts', array($this, 'ossiqn_enqueue_global_assets'));
        add_action('wp_ajax_ossiqn_generate_ai_content', array($this, 'ossiqn_ajax_generate_content'));
        add_action('wp_ajax_ossiqn_get_analytics', array($this, 'ossiqn_ajax_get_analytics'));
        add_action('wp_ajax_ossiqn_switch_provider', array($this, 'ossiqn_ajax_switch_provider'));
        add_action('wp_ajax_ossiqn_bulk_generate', array($this, 'ossiqn_ajax_bulk_generate'));
        add_action('rest_api_init', array($this, 'ossiqn_register_rest_endpoints'));
        add_action('wp_dashboard_setup', array($this, 'ossiqn_register_global_widget'));

        register_activation_hook(OSSIQN_GLOBAL_FILE, array($this, 'ossiqn_global_activation'));
        register_deactivation_hook(OSSIQN_GLOBAL_FILE, array($this, 'ossiqn_global_deactivation'));
    }

    public function ossiqn_init() {
        // License check removed - free version
    }

    public function ossiqn_global_activation() {
        global $wpdb;
        
        $tables = array(
            'ossiqn_global_history' => "
                CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ossiqn_global_history (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    post_id bigint(20) NOT NULL,
                    title varchar(255) NOT NULL,
                    keywords text NOT NULL,
                    ai_provider varchar(50) NOT NULL,
                    model varchar(100) NOT NULL,
                    language varchar(10) NOT NULL,
                    style varchar(50) NOT NULL,
                    tone varchar(50) NOT NULL,
                    word_count int(11) NOT NULL,
                    tokens_used int(11) NOT NULL,
                    cost_usd decimal(10,4) NOT NULL,
                    user_id bigint(20) NOT NULL,
                    ip_address varchar(45) NOT NULL,
                    status varchar(20) NOT NULL DEFAULT 'success',
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY post_id (post_id),
                    KEY user_id (user_id),
                    KEY created_at (created_at)
                )
            "
        );

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $table_name => $sql) {
            dbDelta($sql);
        }

        update_option('ossiqn_global_version', OSSIQN_GLOBAL_VERSION);
        update_option('ossiqn_global_settings', $this->ossiqn_get_default_settings());
        update_option('ossiqn_global_activated_at', current_time('mysql'));
    }

    public function ossiqn_global_deactivation() {
        wp_clear_scheduled_hook('ossiqn_global_cleanup_logs');
    }

    private function ossiqn_get_default_settings() {
        return array(
            'primary_ai_provider' => 'groq',
            'fallback_provider' => 'openai',
            'default_language' => 'en_US',
            'default_currency' => 'USD',
            'enable_auto_publish' => 0,
            'enable_webhooks' => 1,
            'enable_analytics' => 1,
            'max_content_length' => 5000,
            'api_timeout' => 120,
            'rate_limit_per_hour' => 100,
            'timezone' => get_option('timezone_string')
        );
    }

    public function ossiqn_register_main_menu() {
        add_menu_page(
            __('Ossiqn Global', 'ossiqn-global'),
            __('Ossiqn Global', 'ossiqn-global'),
            'manage_options',
            'ossiqn-global-dashboard',
            array($this, 'ossiqn_render_dashboard'),
            'dashicons-rocket',
            28
        );

        $submenus = array(
            'ossiqn-global-dashboard' => __('Dashboard', 'ossiqn-global'),
            'ossiqn-global-generator' => __('AI Content Generator', 'ossiqn-global'),
            'ossiqn-global-templates' => __('Templates', 'ossiqn-global'),
            'ossiqn-global-analytics' => __('Analytics', 'ossiqn-global'),
            'ossiqn-global-api' => __('API & Webhooks', 'ossiqn-global'),
            'ossiqn-global-settings' => __('Settings', 'ossiqn-global'),
        );

        foreach ($submenus as $page => $title) {
            add_submenu_page(
                'ossiqn-global-dashboard',
                $title,
                $title,
                'manage_options',
                $page,
                array($this, 'ossiqn_render_' . str_replace('ossiqn-global-', '', $page))
            );
        }
    }

    public function ossiqn_enqueue_global_assets($hook) {
        if (strpos($hook, 'ossiqn-global') === false) return;

        wp_enqueue_style('ossiqn-global-admin', OSSIQN_GLOBAL_URL . 'assets/ossiqn-global-admin.css', array(), OSSIQN_GLOBAL_VERSION);
        wp_enqueue_script('ossiqn-global-chart', 'https://cdn.jsdelivr.net/npm/chart.js@4.2.1/dist/chart.min.js', array(), '4.2.1', true);
        wp_enqueue_script('ossiqn-global-admin', OSSIQN_GLOBAL_URL . 'assets/ossiqn-global-admin.js', array('jquery'), OSSIQN_GLOBAL_VERSION, true);

        wp_localize_script('ossiqn-global-admin', 'ossiqn_global', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ossiqn_global_nonce'),
            'languages' => $this->supported_languages,
            'currencies' => $this->supported_currencies,
            'providers' => $this->ai_providers,
            'site_url' => site_url(),
            'rest_url' => rest_url()
        ));

        wp_enqueue_style('dashicons');
    }

    public function ossiqn_render_dashboard() {
        if (!current_user_can('manage_options')) wp_die(__('Unauthorized', 'ossiqn-global'));
        
        global $wpdb;
        $history = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}ossiqn_global_history 
            WHERE user_id = " . get_current_user_id() . " 
            ORDER BY created_at DESC LIMIT 50
        ");
        ?>
        <div class="ossiqn-global-container">
            <div class="ossiqn-global-header">
                <div class="header-content">
                    <h1>🚀 <?php _e('Ossiqn Global AI', 'ossiqn-global'); ?></h1>
                    <p><?php _e('Free AI Content Generation Platform', 'ossiqn-global'); ?></p>
                </div>
                <div class="header-badge">
                    <span class="version">v<?php echo OSSIQN_GLOBAL_VERSION; ?></span>
                    <span class="license-badge active"><?php _e('Free Version', 'ossiqn-global'); ?></span>
                </div>
            </div>

            <div class="ossiqn-stats-container">
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-info">
                        <h3><?php echo count($history); ?></h3>
                        <p><?php _e('Generated Contents', 'ossiqn-global'); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">⚡</div>
                    <div class="stat-info">
                        <h3>∞</h3>
                        <p><?php _e('Free Tokens', 'ossiqn-global'); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3><?php _e('Free', 'ossiqn-global'); ?></h3>
                        <p><?php _e('No Cost', 'ossiqn-global'); ?></p>
                    </div>
                </div>
            </div>

            <div class="ossiqn-quick-actions">
                <a href="<?php echo admin_url('admin.php?page=ossiqn-global-generator'); ?>" class="btn btn-primary">
                    ✨ <?php _e('Generate Content', 'ossiqn-global'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ossiqn-global-templates'); ?>" class="btn btn-secondary">
                    📋 <?php _e('Browse Templates', 'ossiqn-global'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ossiqn-global-analytics'); ?>" class="btn btn-secondary">
                    📊 <?php _e('View Analytics', 'ossiqn-global'); ?>
                </a>
            </div>

            <div class="ossiqn-chart-section">
                <h3><?php _e('Monthly Content Generation', 'ossiqn-global'); ?></h3>
                <canvas id="ossiqn-global-chart"></canvas>
            </div>

            <div class="ossiqn-recent-content">
                <h3><?php _e('Recent Generations', 'ossiqn-global'); ?></h3>
                <table class="wp-list-table widefat fixed">
                    <thead>
                        <tr>
                            <th><?php _e('Title', 'ossiqn-global'); ?></th>
                            <th><?php _e('Language', 'ossiqn-global'); ?></th>
                            <th><?php _e('Provider', 'ossiqn-global'); ?></th>
                            <th><?php _e('Tokens', 'ossiqn-global'); ?></th>
                            <th><?php _e('Created', 'ossiqn-global'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($history, 0, 10) as $item): ?>
                             <tr>
                                <td><a href="<?php echo get_edit_post_link($item->post_id); ?>" target="_blank"><?php echo esc_html($item->title); ?></a></td>
                                <td><flag><?php echo strtoupper(substr($item->language, 0, 2)); ?></flag></td>
                                <td><?php echo esc_html($item->ai_provider); ?></td>
                                <td><?php echo number_format($item->tokens_used); ?></td>
                                <td><?php echo date_i18n('M d, Y H:i', strtotime($item->created_at)); ?></td>
                             </tr>
                        <?php endforeach; ?>
                    </tbody>
                 </table>
            </div>
        </div>
        <?php
    }

    public function ossiqn_render_generator() {
        if (!current_user_can('manage_options')) wp_die(__('Unauthorized', 'ossiqn-global'));
        ?>
        <div class="ossiqn-global-container">
            <h1>🎨 <?php _e('AI Content Generator', 'ossiqn-global'); ?></h1>
            
            <form id="ossiqn-global-generator-form" class="ossiqn-generator-form">
                <?php wp_nonce_field('ossiqn_global_nonce', 'ossiqn_nonce'); ?>

                <div class="form-section">
                    <h3><?php _e('Content Details', 'ossiqn-global'); ?></h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?php _e('Title', 'ossiqn-global'); ?> *</label>
                            <input type="text" name="title" required class="form-input" placeholder="<?php _e('Your article title', 'ossiqn-global'); ?>">
                        </div>

                        <div class="form-group">
                            <label><?php _e('Keywords', 'ossiqn-global'); ?> *</label>
                            <input type="text" name="keywords" required class="form-input" placeholder="<?php _e('keyword1, keyword2, keyword3', 'ossiqn-global'); ?>">
                        </div>

                        <div class="form-group">
                            <label><?php _e('Content Language', 'ossiqn-global'); ?></label>
                            <select name="language" class="form-select">
                                <?php foreach ($this->supported_languages as $lang): ?>
                                    <option value="<?php echo $lang; ?>"><?php echo $this->ossiqn_get_language_name($lang); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><?php _e('AI Provider', 'ossiqn-global'); ?></label>
                            <select name="ai_provider" class="form-select">
                                <?php foreach ($this->ai_providers as $provider): ?>
                                    <option value="<?php echo $provider; ?>"><?php echo ucfirst($provider); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><?php _e('Content Style', 'ossiqn-global'); ?></label>
                            <select name="style" class="form-select">
                                <option value="blog">Blog Post</option>
                                <option value="technical">Technical Guide</option>
                                <option value="social">Social Media</option>
                                <option value="email">Email Campaign</option>
                                <option value="seo">SEO Optimized</option>
                                <option value="marketing">Marketing Copy</option>
                                <option value="educational">Educational</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><?php _e('Tone', 'ossiqn-global'); ?></label>
                            <select name="tone" class="form-select">
                                <option value="professional">Professional</option>
                                <option value="casual">Casual</option>
                                <option value="academic">Academic</option>
                                <option value="creative">Creative</option>
                                <option value="persuasive">Persuasive</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><?php _e('Word Count', 'ossiqn-global'); ?></label>
                            <select name="word_count" class="form-select">
                                <option value="500">Short (500)</option>
                                <option value="1500" selected>Medium (1500)</option>
                                <option value="3000">Long (3000)</option>
                                <option value="5000">Extra Long (5000)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><?php _e('Category', 'ossiqn-global'); ?></label>
                            <select name="category" class="form-select">
                                <option value="0"><?php _e('Select Category', 'ossiqn-global'); ?></option>
                                <?php wp_dropdown_categories(array('hide_empty' => false, 'echo' => true)); ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><?php _e('Advanced Options', 'ossiqn-global'); ?></h3>
                    
                    <label class="checkbox-label">
                        <input type="checkbox" name="include_seo" checked>
                        <?php _e('Include SEO Optimization', 'ossiqn-global'); ?>
                    </label>

                    <label class="checkbox-label">
                        <input type="checkbox" name="auto_publish">
                        <?php _e('Auto Publish', 'ossiqn-global'); ?>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">
                        <span class="btn-text">✨ <?php _e('Generate Content', 'ossiqn-global'); ?></span>
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <?php _e('Reset', 'ossiqn-global'); ?>
                    </button>
                </div>
            </form>

            <div id="ossiqn-generation-preview" class="generation-preview" style="display:none;">
                <div class="preview-content"></div>
            </div>

            <div id="ossiqn-global-notification" class="ossiqn-notification"></div>
        </div>
        <?php
    }

    public function ossiqn_render_templates() {
        if (!current_user_can('manage_options')) wp_die(__('Unauthorized', 'ossiqn-global'));
        ?>
        <div class="ossiqn-global-container">
            <h1>📋 <?php _e('Content Templates', 'ossiqn-global'); ?></h1>
            <div class="templates-grid" id="ossiqn-templates-grid">
                <!-- Templates loaded via AJAX -->
            </div>
        </div>
        <?php
    }

    public function ossiqn_render_analytics() {
        if (!current_user_can('manage_options')) wp_die(__('Unauthorized', 'ossiqn-global'));
        ?>
        <div class="ossiqn-global-container">
            <h1>📊 <?php _e('Analytics & Reports', 'ossiqn-global'); ?></h1>
            
            <div class="analytics-filters">
                <input type="date" id="analytics-from" class="form-input">
                <input type="date" id="analytics-to" class="form-input">
                <button type="button" class="btn btn-primary" id="analytics-filter-btn">
                    <?php _e('Filter', 'ossiqn-global'); ?>
                </button>
                <button type="button" class="btn btn-secondary" id="analytics-export-btn">
                    📥 <?php _e('Export CSV', 'ossiqn-global'); ?>
                </button>
            </div>

            <div class="analytics-grid">
                <div class="chart-container">
                    <canvas id="analytics-content-chart"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="analytics-provider-chart"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="analytics-cost-chart"></canvas>
                </div>
                <div class="chart-container">
                    <canvas id="analytics-language-chart"></canvas>
                </div>
            </div>
        </div>
        <?php
    }

    public function ossiqn_render_api() {
        if (!current_user_can('manage_options')) wp_die(__('Unauthorized', 'ossiqn-global'));
        ?>
        <div class="ossiqn-global-container">
            <h1>🔌 <?php _e('API Settings', 'ossiqn-global'); ?></h1>
            
            <div class="api-section">
                <h3><?php _e('Groq API Key', 'ossiqn-global'); ?></h3>
                <div class="api-key-display">
                    <input type="password" id="api-key-input" class="form-input" value="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">
                    <button type="button" class="btn btn-secondary" id="api-key-toggle">👁️</button>
                    <button type="button" class="btn btn-secondary" id="api-key-copy"><?php _e('Copy', 'ossiqn-global'); ?></button>
                </div>
                <p class="description"><?php _e('Default Groq API key is pre-configured for free usage.', 'ossiqn-global'); ?></p>
            </div>

            <div class="webhook-section">
                <h3><?php _e('Webhooks', 'ossiqn-global'); ?></h3>
                <form id="ossiqn-webhook-form" class="webhook-form">
                    <div class="form-group">
                        <label><?php _e('Webhook URL', 'ossiqn-global'); ?></label>
                        <input type="url" name="webhook_url" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label><?php _e('Event Type', 'ossiqn-global'); ?></label>
                        <select name="event_type" class="form-select">
                            <option value="content_generated">Content Generated</option>
                            <option value="content_published">Content Published</option>
                            <option value="error_occurred">Error Occurred</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><?php _e('Add Webhook', 'ossiqn-global'); ?></button>
                </form>

                <div id="webhooks-list" class="webhooks-list">
                    <!-- Webhooks listed here -->
                </div>
            </div>
        </div>
        <?php
    }

    public function ossiqn_render_settings() {
        if (!current_user_can('manage_options')) wp_die(__('Unauthorized', 'ossiqn-global'));
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ossiqn_settings_nonce'])) {
            if (!wp_verify_nonce($_POST['ossiqn_settings_nonce'], 'ossiqn_save_settings')) {
                wp_die(__('Nonce verification failed', 'ossiqn-global'));
            }

            $settings = get_option('ossiqn_global_settings', array());
            $settings['default_language'] = sanitize_text_field($_POST['default_language'] ?? 'en_US');
            $settings['default_currency'] = sanitize_text_field($_POST['default_currency'] ?? 'USD');
            $settings['primary_ai_provider'] = sanitize_text_field($_POST['primary_ai_provider'] ?? 'groq');
            $settings['enable_auto_publish'] = isset($_POST['enable_auto_publish']) ? 1 : 0;
            $settings['enable_webhooks'] = isset($_POST['enable_webhooks']) ? 1 : 0;
            $settings['rate_limit_per_hour'] = intval($_POST['rate_limit_per_hour'] ?? 100);
            $settings['timezone'] = sanitize_text_field($_POST['timezone'] ?? 'UTC');

            update_option('ossiqn_global_settings', $settings);
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully', 'ossiqn-global') . '</p></div>';
        }

        $settings = get_option('ossiqn_global_settings', $this->ossiqn_get_default_settings());
        ?>
        <div class="ossiqn-global-container">
            <h1>⚙️ <?php _e('Settings', 'ossiqn-global'); ?></h1>

            <form method="POST" class="settings-form">
                <?php wp_nonce_field('ossiqn_save_settings', 'ossiqn_settings_nonce'); ?>

                <div class="settings-section">
                    <h3><?php _e('General Settings', 'ossiqn-global'); ?></h3>
                    
                    <div class="form-group">
                        <label><?php _e('Default Language', 'ossiqn-global'); ?></label>
                        <select name="default_language" class="form-select">
                            <?php foreach ($this->supported_languages as $lang): ?>
                                <option value="<?php echo $lang; ?>" <?php selected($settings['default_language'], $lang); ?>>
                                    <?php echo $this->ossiqn_get_language_name($lang); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?php _e('Default Currency', 'ossiqn-global'); ?></label>
                        <select name="default_currency" class="form-select">
                            <?php foreach ($this->supported_currencies as $currency): ?>
                                <option value="<?php echo $currency; ?>" <?php selected($settings['default_currency'], $currency); ?>>
                                    <?php echo $currency; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?php _e('Timezone', 'ossiqn-global'); ?></label>
                        <?php wp_timezone_choice($settings['timezone']); ?>
                    </div>
                </div>

                <div class="settings-section">
                    <h3><?php _e('API Settings', 'ossiqn-global'); ?></h3>
                    
                    <div class="form-group">
                        <label><?php _e('Primary AI Provider', 'ossiqn-global'); ?></label>
                        <select name="primary_ai_provider" class="form-select">
                            <?php foreach ($this->ai_providers as $provider): ?>
                                <option value="<?php echo $provider; ?>" <?php selected($settings['primary_ai_provider'], $provider); ?>>
                                    <?php echo ucfirst($provider); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><?php _e('Rate Limit (requests per hour)', 'ossiqn-global'); ?></label>
                        <input type="number" name="rate_limit_per_hour" value="<?php echo esc_attr($settings['rate_limit_per_hour']); ?>" min="1" max="1000" class="form-input">
                    </div>
                </div>

                <div class="settings-section">
                    <h3><?php _e('Features', 'ossiqn-global'); ?></h3>
                    
                    <label class="checkbox-label">
                        <input type="checkbox" name="enable_auto_publish" <?php checked($settings['enable_auto_publish']); ?>>
                        <?php _e('Enable Auto-Publish', 'ossiqn-global'); ?>
                    </label>

                    <label class="checkbox-label">
                        <input type="checkbox" name="enable_webhooks" <?php checked($settings['enable_webhooks']); ?>>
                        <?php _e('Enable Webhooks', 'ossiqn-global'); ?>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary"><?php _e('Save Settings', 'ossiqn-global'); ?></button>
            </form>
        </div>
        <?php
    }

    public function ossiqn_ajax_generate_content() {
        check_ajax_referer('ossiqn_global_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'ossiqn-global'), 403);
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        $language = sanitize_text_field($_POST['language'] ?? 'en_US');
        $provider = sanitize_text_field($_POST['ai_provider'] ?? 'groq');
        $style = sanitize_text_field($_POST['style'] ?? 'blog');
        $tone = sanitize_text_field($_POST['tone'] ?? 'professional');
        $word_count = intval($_POST['word_count'] ?? 1500);
        $category = intval($_POST['category'] ?? 0);
        $include_seo = isset($_POST['include_seo']) ? true : false;
        $auto_publish = isset($_POST['auto_publish']) ? true : false;

        if (empty($title) || empty($keywords)) {
            wp_send_json_error(__('Title and keywords are required', 'ossiqn-global'));
        }

        $prompt = $this->ossiqn_build_prompt($title, $keywords, $language, $style, $tone, $word_count, $include_seo);

        $api_response = $this->ossiqn_call_ai_provider($provider, $prompt, $language);

        if (is_wp_error($api_response)) {
            wp_send_json_error($api_response->get_error_message());
        }

        $content = $api_response['content'];
        $tokens_used = $api_response['tokens_used'];

        $post_data = array(
            'post_title' => $title,
            'post_content' => wp_kses_post($content),
            'post_status' => $auto_publish ? 'publish' : 'draft',
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
            'post_category' => $category ? array($category) : array(),
            'meta_input' => array(
                '_ossiqn_generated' => 1,
                '_ossiqn_provider' => $provider,
                '_ossiqn_language' => $language,
                '_ossiqn_style' => $style,
                '_ossiqn_tokens' => $tokens_used
            )
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            wp_send_json_error($post_id->get_error_message());
        }

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'ossiqn_global_history',
            array(
                'post_id' => $post_id,
                'title' => $title,
                'keywords' => $keywords,
                'ai_provider' => $provider,
                'model' => 'default',
                'language' => $language,
                'style' => $style,
                'tone' => $tone,
                'word_count' => $word_count,
                'tokens_used' => $tokens_used,
                'cost_usd' => 0,
                'user_id' => get_current_user_id(),
                'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR']),
                'status' => 'success'
            )
        );

        wp_send_json_success(array(
            'message' => __('Content generated successfully!', 'ossiqn-global'),
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'edit_url' => get_edit_post_link($post_id, 'raw')
        ));
    }

    public function ossiqn_ajax_get_analytics() {
        check_ajax_referer('ossiqn_global_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'ossiqn-global'), 403);
        }

        global $wpdb;
        
        $from = sanitize_text_field($_POST['from'] ?? date('Y-m-01'));
        $to = sanitize_text_field($_POST['to'] ?? date('Y-m-d'));

        $analytics = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count,
                SUM(tokens_used) as tokens,
                ai_provider
            FROM {$wpdb->prefix}ossiqn_global_history
            WHERE user_id = %d AND DATE(created_at) BETWEEN %s AND %s
            GROUP BY DATE(created_at), ai_provider
            ORDER BY created_at ASC
        ", get_current_user_id(), $from, $to));

        wp_send_json_success($analytics);
    }

    public function ossiqn_ajax_switch_provider() {
        check_ajax_referer('ossiqn_global_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'ossiqn-global'), 403);
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');

        if (!in_array($provider, $this->ai_providers)) {
            wp_send_json_error(__('Invalid provider', 'ossiqn-global'));
        }

        $settings = get_option('ossiqn_global_settings', array());
        $settings['primary_ai_provider'] = $provider;
        update_option('ossiqn_global_settings', $settings);

        wp_send_json_success(array(
            'message' => __('Provider switched successfully', 'ossiqn-global')
        ));
    }

    public function ossiqn_ajax_bulk_generate() {
        check_ajax_referer('ossiqn_global_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'ossiqn-global'), 403);
        }

        $items = json_decode(stripslashes($_POST['items'] ?? '[]'), true);

        if (!is_array($items) || empty($items)) {
            wp_send_json_error(__('Invalid items data', 'ossiqn-global'));
        }

        $results = array();
        foreach ($items as $item) {
            $_POST = array_merge($_POST, $item);
            $result = $this->ossiqn_ajax_generate_content();
            $results[] = $result;
        }

        wp_send_json_success(array(
            'message' => sprintf(__('%d items generated successfully', 'ossiqn-global'), count($results)),
            'results' => $results
        ));
    }

    public function ossiqn_register_rest_endpoints() {
        register_rest_route('ossiqn/v1', '/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'ossiqn_rest_generate_content'),
            'permission_callback' => array($this, 'ossiqn_rest_check_permission')
        ));

        register_rest_route('ossiqn/v1', '/analytics', array(
            'methods' => 'GET',
            'callback' => array($this, 'ossiqn_rest_get_analytics'),
            'permission_callback' => array($this, 'ossiqn_rest_check_permission')
        ));
    }

    public function ossiqn_rest_generate_content($request) {
        $params = $request->get_json_params();
        $params['nonce'] = wp_create_nonce('ossiqn_global_nonce');
        $_POST = array_merge($_POST, $params);

        $this->ossiqn_ajax_generate_content();
    }

    public function ossiqn_rest_get_analytics($request) {
        return rest_ensure_response(array('status' => 'success'));
    }

    public function ossiqn_rest_check_permission($request) {
        return current_user_can('manage_options');
    }

    public function ossiqn_register_global_widget() {
        wp_add_dashboard_widget(
            'ossiqn_global_widget',
            __('Ossiqn Global AI', 'ossiqn-global'),
            array($this, 'ossiqn_widget_output')
        );
    }

    public function ossiqn_widget_output() {
        global $wpdb;
        $count = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}ossiqn_global_history 
            WHERE user_id = " . get_current_user_id()
        );
        ?>
        <div style="padding: 15px;">
            <p><?php printf(__('Generated %d contents so far', 'ossiqn-global'), $count); ?></p>
            <p><?php _e('Free AI content generation with Groq', 'ossiqn-global'); ?></p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=ossiqn-global-generator'); ?>" class="button button-primary">
                    <?php _e('Generate Content', 'ossiqn-global'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function ossiqn_build_prompt($title, $keywords, $language, $style, $tone, $word_count, $include_seo) {
        $lang_name = $this->ossiqn_get_language_name($language);
        $seo_instruction = $include_seo ? "\nOptimize for SEO with keywords: $keywords" : '';
        
        return sprintf(
            "Write a %s word %s article in %s with a %s tone about '%s'. %s%s Target keywords: %s. Structure with proper headings and paragraphs.",
            $word_count,
            $style,
            $lang_name,
            $tone,
            $title,
            "Style: $style.",
            $seo_instruction,
            $keywords
        );
    }

    private function ossiqn_call_ai_provider($provider, $prompt, $language) {
        switch ($provider) {
            case 'groq':
                return $this->ossiqn_call_groq_api($prompt);
            case 'openai':
                return $this->ossiqn_call_openai_api($prompt);
            case 'cohere':
                return $this->ossiqn_call_cohere_api($prompt);
            default:
                return new WP_Error('invalid_provider', __('Invalid AI provider', 'ossiqn-global'));
        }
    }

    private function ossiqn_call_groq_api($prompt) {
        $api_key = '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';
        
        $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', array(
            'timeout' => 120,
            'sslverify' => false,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => wp_json_encode(array(
                'model' => 'llama3-8b-8192',
                'messages' => array(array('role' => 'user', 'content' => $prompt)),
                'max_tokens' => 3000,
                'temperature' => 0.7
            ))
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['choices'][0]['message']['content'])) {
            return new WP_Error('api_error', __('API returned invalid response', 'ossiqn-global'));
        }

        return array(
            'content' => $body['choices'][0]['message']['content'],
            'tokens_used' => $body['usage']['total_tokens'] ?? 0,
            'provider' => 'groq'
        );
    }

    private function ossiqn_call_openai_api($prompt) {
        $api_key = get_option('ossiqn_openai_api_key', '');
        
        if (empty($api_key)) {
            return $this->ossiqn_call_groq_api($prompt);
        }

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 120,
            'sslverify' => false,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => wp_json_encode(array(
                'model' => 'gpt-3.5-turbo',
                'messages' => array(array('role' => 'user', 'content' => $prompt)),
                'max_tokens' => 3000,
                'temperature' => 0.7
            ))
        ));

        if (is_wp_error($response)) {
            return $this->ossiqn_call_groq_api($prompt);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['choices'][0]['message']['content'])) {
            return $this->ossiqn_call_groq_api($prompt);
        }

        return array(
            'content' => $body['choices'][0]['message']['content'],
            'tokens_used' => $body['usage']['total_tokens'] ?? 0,
            'provider' => 'openai'
        );
    }

    private function ossiqn_call_cohere_api($prompt) {
        return $this->ossiqn_call_groq_api($prompt);
    }

    private function ossiqn_get_language_name($code) {
        $languages = array(
            'en_US' => 'English',
            'tr_TR' => 'Turkish',
            'es_ES' => 'Spanish',
            'fr_FR' => 'French',
            'de_DE' => 'German',
            'ar_AR' => 'Arabic',
            'pt_BR' => 'Portuguese',
            'ja_JP' => 'Japanese',
            'zh_CN' => 'Chinese',
            'ru_RU' => 'Russian'
        );

        return $languages[$code] ?? ucfirst($code);
    }
}

$ossiqn_global = Ossiqn_Global_Enterprise::get_instance();
?>
