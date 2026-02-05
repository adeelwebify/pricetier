<?php

/**
 * PriceTier Admin
 *
 * Consolidates all admin UI, AJAX, and Menu logic.
 */

namespace PriceTier;

defined('ABSPATH') || exit;

final class Admin {

  public static function init(): void {
    add_action('admin_menu', [__CLASS__, 'register_menu']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    add_action('wp_ajax_pricetier_get_attribute_terms', [__CLASS__, 'ajax_get_terms']);
    add_action('wp_ajax_pricetier_search_users', [__CLASS__, 'ajax_search_users']);
    add_action('wp_ajax_pricetier_lookup_product', [__CLASS__, 'ajax_lookup_product']);

    add_filter('plugin_action_links_' . plugin_basename(PRICETIER_FILE), [__CLASS__, 'add_action_links']);
  }

  public static function add_action_links(array $links): array {
    $url = admin_url('admin.php?page=pricetier');
    $settings_link = '<a href="' . esc_url($url) . '">' . __('Settings', 'pricetier') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
  }

  /* -------------------------------------------------------------------------
   * Menu & Page
   * ------------------------------------------------------------------------- */

  public static function register_menu(): void {
    add_submenu_page(
      'woocommerce',
      __('PriceTier', 'pricetier'),
      __('PriceTier', 'pricetier'),
      'manage_woocommerce',
      'pricetier',
      [__CLASS__, 'render_page']
    );
  }

  public static function render_page(): void {
    self::handle_post();
    $settings = get_option('pricetier_settings', []);
    $enabled  = $settings['enabled'] ?? true;
    $cost_key = $settings['cost_meta_key'] ?? '_wc_cog_cost';
    ?>
    <div class="wrap pricetier">
      <div class="pricetier__header">
        <p><?php esc_html_e('PriceTier', 'pricetier'); ?></p>
        <a href="<?php echo esc_url('https://github.com/adeelwebify/pricetier'); ?>" target="_blank" class="button button-primary"><?php esc_html_e('Documentation ↗', 'pricetier'); ?></a>
      </div>

      <div class="pricetier__main">
        <?php self::render_lookup_section(); ?>
        
        <form method="post">
          <?php wp_nonce_field('pricetier_save', 'pricetier_nonce'); ?>
          
          <?php self::render_notices(); ?>
  
          <h2><?php esc_html_e('Global Settings', 'pricetier'); ?></h2>
          <div class="pricetier__card">
            <table class="form-table" role="presentation">
              <tr>
                <th scope="row"><label for="pt_enabled"><?php esc_html_e('Enable PriceTier', 'pricetier'); ?></label></th>
                <td>
                  <input type="checkbox" name="pricetier_settings[enabled]" id="pt_enabled" value="1" <?php checked($enabled); ?> />
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="pt_cost"><?php esc_html_e('Cost Source', 'pricetier'); ?></label></th>
                <td>
                  <select name="pricetier_settings[cost_meta_key]" id="pt_cost" class="regular-text">
                    <?php foreach (self::get_cost_sources() as $key => $label): ?>
                      <option value="<?php echo esc_attr($key); ?>" <?php selected($cost_key, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="description"><?php esc_html_e('Select the meta key for product cost.', 'pricetier'); ?></p>
                </td>
              </tr>
            </table>
          </div>
  
          <?php self::render_rules_section(); ?>
  
          <p><button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'pricetier'); ?></button></p>
        </form>
      </div>

    </div>
    <?php
  }

  /* -------------------------------------------------------------------------
   * Rules Rendering
   * ------------------------------------------------------------------------- */

  private static function render_rules_section(): void {
    $rules = get_option('pricetier_rules', []);
    ?>
    <h2><?php esc_html_e('Pricing Rules', 'pricetier'); ?></h2>
    <div id="pricetier-rules">
      <?php foreach ($rules as $idx => $rule) self::render_rule_block(Config::sanitize_rule((array)$rule), $idx); ?>
    </div>
    <p class="add-rule-wrapper">
      <button type="button" id="pricetier-add-rule" class="button button-primary-outline"><?php esc_html_e('Add Rule', 'pricetier'); ?></button>
    </p>
    <?php
  }

  private static function render_lookup_section(): void {
    ?>
    <h3><?php esc_html_e('Cost Lookup', 'pricetier'); ?></h3>
    <div class="pricetier__card pricetier__card--lookup">
      <p class="description"><?php esc_html_e('Search for a product to see its current cost and price details.', 'pricetier'); ?></p>
      
      <div>
        <select id="pricetier-lookup-input" class="wc-product-search" data-action="woocommerce_json_search_products_and_variations" data-placeholder="<?php esc_attr_e('Search product...', 'pricetier'); ?>" style="width:100%"></select>
      </div>

      <div id="pricetier-lookup-result">
        <table class="widefat striped">
           <thead>
             <tr>
               <th><?php esc_html_e('Cost Source', 'pricetier'); ?></th>
               <th><?php esc_html_e('Value', 'pricetier'); ?></th>
             </tr>
           </thead>
           <tbody>
             <tr>
               <td><strong><?php esc_html_e('Cost', 'pricetier'); ?></strong> <code id="pt-result-key"></code></td>
               <td id="pt-result-cost"></td>
             </tr>
             <tr>
               <td><strong><?php esc_html_e('Regular Price', 'pricetier'); ?></strong></td>
               <td id="pt-result-regular"></td>
             </tr>
              <tr>
               <td><strong><?php esc_html_e('Sale Price', 'pricetier'); ?></strong></td>
               <td id="pt-result-sale"></td>
             </tr>
           </tbody>
        </table>
      </div>
    </div>
    <?php
  }

  private static function render_rule_block(array $rule, $index, bool $open = false): void {
    ?>
    <details class="pricetier-rule" <?php echo $open ? 'open' : ''; ?>>
      <summary>
        <strong><?php echo esc_html($rule['name'] ?: __('New Rule', 'pricetier')); ?></strong>
        <?php if (!$rule['enabled']) echo ' <em style="color:#999;">(' . __('Disabled', 'pricetier') . ')</em>'; ?>
      </summary>
      <table class="form-table">
        <!-- Basic -->
        <tr>
          <th><label><?php esc_html_e('Rule Name', 'pricetier'); ?></label></th>
          <td>
            <input type="text" class="regular-text" name="pricetier_rules[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($rule['name']); ?>" />
          </td>
        </tr>
        <tr>
          <th><label><?php esc_html_e('Enabled', 'pricetier'); ?></label></th>
          <td>
            <input type="checkbox" name="pricetier_rules[<?php echo esc_attr($index); ?>][enabled]" value="1" <?php checked($rule['enabled']); ?> />
          </td>
        </tr>
        <tr>
          <th><label><?php esc_html_e('Priority', 'pricetier'); ?></label></th>
          <td>
            <input type="number" name="pricetier_rules[<?php echo esc_attr($index); ?>][priority]" value="<?php echo esc_attr($rule['priority']); ?>" />
            <p class="description"><?php esc_html_e('Determines the order of execution. Lower numbers (e.g., 1) run first. The system stops checking after the first match.', 'pricetier'); ?></p>
          </td>
        </tr>

        <!-- Products -->
        <tr><th colspan="2"><h3><?php esc_html_e('Products', 'pricetier'); ?></h3></th></tr>
        <tr>
          <th><?php esc_html_e('Apply rule to', 'pricetier'); ?></th>
          <td>
            <?php foreach (['all'=>'All products', 'products'=>'Specific products', 'categories'=>'Specific categories', 'tags'=>'Specific tags', 'attribute'=>'Specific attribute'] as $val => $lbl): ?>
              <label style="display:block;margin-bottom:4px;">
                <input type="radio" name="pricetier_rules[<?php echo esc_attr($index); ?>][products][type]" value="<?php echo esc_attr($val); ?>" <?php checked($rule['products']['type'], $val); ?> />
                <?php echo esc_html($lbl); ?>
              </label>
            <?php endforeach; ?>
          </td>
        </tr>
        
        <!-- Product Conditions -->
        <tr class="pricetier-condition pricetier-condition-products">
          <th><?php esc_html_e('Products', 'pricetier'); ?></th>
          <td>
            <select class="wc-product-search" multiple style="width:100%;" data-action="woocommerce_json_search_products_and_variations" name="pricetier_rules[<?php echo esc_attr($index); ?>][products][products][]">
              <?php foreach ($rule['products']['products'] as $pid) {
                if ($p = wc_get_product($pid)) echo '<option value="'.$pid.'" selected>'.esc_html($p->get_name()).'</option>';
              } ?>
            </select>
            <p class="description"><?php esc_html_e('Search and select products.', 'pricetier'); ?></p>
          </td>
        </tr>
        <tr class="pricetier-condition pricetier-condition-categories">
          <th><?php esc_html_e('Categories', 'pricetier'); ?></th>
          <td>
            <select class="wc-enhanced-select" multiple style="width:100%;" name="pricetier_rules[<?php echo esc_attr($index); ?>][products][categories][]">
              <?php foreach (get_terms(['taxonomy'=>'product_cat', 'hide_empty'=>false]) as $t) {
                echo '<option value="'.$t->term_id.'" '.selected(in_array($t->term_id, $rule['products']['categories']), true, false).'>'.esc_html($t->name).'</option>';
              } ?>
            </select>
             <p class="description"><?php esc_html_e('Select product categories.', 'pricetier'); ?></p>
          </td>
        </tr>
        <tr class="pricetier-condition pricetier-condition-tags">
          <th><?php esc_html_e('Tags', 'pricetier'); ?></th>
          <td>
             <select class="wc-enhanced-select" multiple style="width:100%;" name="pricetier_rules[<?php echo esc_attr($index); ?>][products][tags][]">
              <?php foreach (get_terms(['taxonomy'=>'product_tag', 'hide_empty'=>false]) as $t) {
                echo '<option value="'.$t->term_id.'" '.selected(in_array($t->term_id, $rule['products']['tags']), true, false).'>'.esc_html($t->name).'</option>';
              } ?>
            </select>
            <p class="description"><?php esc_html_e('Select product tags.', 'pricetier'); ?></p>
          </td>
        </tr>
        <tr class="pricetier-condition pricetier-condition-attribute">
          <th><?php esc_html_e('Attribute', 'pricetier'); ?></th>
          <td>
             <select class="wc-enhanced-select" style="width:100%;" name="pricetier_rules[<?php echo esc_attr($index); ?>][products][attribute][taxonomy]">
               <option value=""><?php esc_html_e('Select attribute', 'pricetier'); ?></option>
              <?php foreach (wc_get_attribute_taxonomies() as $tax) {
                $n = wc_attribute_taxonomy_name($tax->attribute_name);
                echo '<option value="'.$n.'" '.selected($rule['products']['attribute']['taxonomy'], $n, false).'>'.esc_html($tax->attribute_label).'</option>';
              } ?>
            </select>
             <p class="description"><?php esc_html_e('Select a product attribute.', 'pricetier'); ?></p>
          </td>
        </tr>
         <tr class="pricetier-condition pricetier-condition-attribute-terms">
          <th><?php esc_html_e('Attribute Terms', 'pricetier'); ?></th>
          <td>
             <select class="wc-enhanced-select" multiple style="width:100%;" name="pricetier_rules[<?php echo esc_attr($index); ?>][products][attribute][terms][]">
              <?php if (!empty($rule['products']['attribute']['taxonomy'])) {
                 foreach (get_terms(['taxonomy'=>$rule['products']['attribute']['taxonomy'], 'hide_empty'=>false]) as $t) {
                   echo '<option value="'.$t->term_id.'" '.selected(in_array($t->term_id, $rule['products']['attribute']['terms']), true, false).'>'.esc_html($t->name).'</option>';
                 }
              } ?>
            </select>
            <p class="description"><?php esc_html_e('Select terms for the chosen attribute.', 'pricetier'); ?></p>
          </td>
        </tr>

        <!-- Users -->
        <tr><th colspan="2"><h3><?php esc_html_e('Users', 'pricetier'); ?></h3></th></tr>
        <tr>
          <th><?php esc_html_e('Apply rule to', 'pricetier'); ?></th>
          <td>
             <?php foreach (['all'=>'All users', 'roles'=>'Specific roles', 'users'=>'Specific users'] as $val => $lbl): ?>
              <label style="display:block;margin-bottom:4px;">
                <input type="radio" name="pricetier_rules[<?php echo esc_attr($index); ?>][users][type]" value="<?php echo esc_attr($val); ?>" <?php checked($rule['users']['type'], $val); ?> />
                <?php echo esc_html($lbl); ?>
              </label>
            <?php endforeach; ?>
          </td>
        </tr>
        <tr class="pricetier-user-condition pricetier-user-condition-roles">
          <th><?php esc_html_e('Roles', 'pricetier'); ?></th>
          <td>
             <select class="wc-enhanced-select" multiple style="width:100%;" name="pricetier_rules[<?php echo esc_attr($index); ?>][users][roles][]">
              <?php foreach (wp_roles()->roles as $k => $r) {
                 echo '<option value="'.$k.'" '.selected(in_array($k, $rule['users']['roles']), true, false).'>'.esc_html($r['name']).'</option>';
              } ?>
            </select>
             <p class="description"><?php esc_html_e('Select user roles.', 'pricetier'); ?></p>
          </td>
        </tr>
        <tr class="pricetier-user-condition pricetier-user-condition-users">
          <th><?php esc_html_e('Users', 'pricetier'); ?></th>
          <td>
             <select class="pricetier-user-select" multiple style="width:100%;" name="pricetier_rules[<?php echo esc_attr($index); ?>][users][users][]">
               <?php foreach ($rule['users']['users'] as $uid) {
                 if ($u = get_user_by('id', $uid)) echo '<option value="'.$uid.'" selected>'.esc_html($u->display_name).'</option>';
               } ?>
            </select>
            <p class="description"><?php esc_html_e('Search and select specific users.', 'pricetier'); ?></p>
          </td>
        </tr>

         <!-- Pricing -->
        <tr><th colspan="2"><h3><?php esc_html_e('Pricing', 'pricetier'); ?></h3></th></tr>
        <tr>
          <th><?php esc_html_e('Type', 'pricetier'); ?></th>
           <td>
              <label style="margin-right:15px;"><input type="radio" name="pricetier_rules[<?php echo esc_attr($index); ?>][pricing][type]" value="percent" <?php checked($rule['pricing']['type'], 'percent'); ?> /> <?php esc_html_e('Cost + %', 'pricetier'); ?></label>
              <label><input type="radio" name="pricetier_rules[<?php echo esc_attr($index); ?>][pricing][type]" value="fixed" <?php checked($rule['pricing']['type'], 'fixed'); ?> /> <?php esc_html_e('Cost + Fixed', 'pricetier'); ?></label>
              <p class="description"><?php esc_html_e('How to calculate the new price.', 'pricetier'); ?></p>
          </td>
        </tr>
        <tr class="pricetier-pricing-value">
          <th><span class="pricetier-label-percent">Percentage</span><span class="pricetier-label-fixed">Fixed Amount</span></th>
          <td>
            <input type="number" step="0.01" name="pricetier_rules[<?php echo esc_attr($index); ?>][pricing][value]" value="<?php echo esc_attr($rule['pricing']['value']); ?>" />
             <p class="description"><?php esc_html_e('The margin value to add to the cost.', 'pricetier'); ?></p>
          </td>
        </tr>
        <tr>
          <th><?php esc_html_e('Rounding', 'pricetier'); ?></th>
          <td>
            <select name="pricetier_rules[<?php echo esc_attr($index); ?>][pricing][rounding]">
              <?php foreach (['none', 'up', 'down', 'nearest'] as $o) echo '<option value="'.$o.'" '.selected($rule['pricing']['rounding'], $o, false).'>'.ucfirst($o).'</option>'; ?>
            </select>
             <p class="description"><?php esc_html_e('Rounding method for the calculated price.', 'pricetier'); ?></p>
          </td>
        </tr>
         <tr>
          <th><?php esc_html_e('Min Price', 'pricetier'); ?></th>
          <td>
            <input type="number" step="0.01" name="pricetier_rules[<?php echo esc_attr($index); ?>][pricing][min_price]" value="<?php echo esc_attr($rule['pricing']['min_price']); ?>" />
             <p class="description"><?php esc_html_e('Prevents the price from being lower than this amount, even if the calculation says otherwise.', 'pricetier'); ?></p>
          </td>
        </tr>
         <tr>
          <th><?php esc_html_e('Max Price', 'pricetier'); ?></th>
          <td>
             <input type="number" step="0.01" name="pricetier_rules[<?php echo esc_attr($index); ?>][pricing][max_price]" value="<?php echo esc_attr($rule['pricing']['max_price']); ?>" />
              <p class="description"><?php esc_html_e('Prevents the price from going higher than this amount.', 'pricetier'); ?></p>
          </td>
        </tr>
         <tr>
          <th><?php esc_html_e('Apply To', 'pricetier'); ?></th>
          <td>
             <select name="pricetier_rules[<?php echo esc_attr($index); ?>][pricing][apply_to]">
               <?php foreach(['regular', 'sale', 'final'] as $a) echo '<option value="'.$a.'" '.selected($rule['pricing']['apply_to'], $a, false).'>'.ucfirst($a).'</option>'; ?>
             </select>
              <p class="description">
                <?php _e(
                  '<strong>Where the price should be applied.</strong> <br>' .
                  '<strong>Final:</strong> The actual price the customer pays.<br>' .
                  '<strong>Sale:</strong> The discounted price (shows previous price crossed out).<br>' .
                  '<strong>Regular:</strong> The standard list price.',
                  'pricetier'
                ); ?>
              </p>
          </td>
        </tr>
      </table>
      <button type="button" class="button button-link pricetier-delete-rule">Delete Rule</button>
    </details>
    <?php
  }

  /* -------------------------------------------------------------------------
   * Utilities & Handlers
   * ------------------------------------------------------------------------- */

  private static function handle_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!isset($_POST['pricetier_nonce']) || !wp_verify_nonce($_POST['pricetier_nonce'], 'pricetier_save')) {
      self::add_notice(__('Security check failed', 'pricetier'), 'error'); return;
    }
    if (!current_user_can('manage_woocommerce')) return;

    // Save Settings
    $raw_s = $_POST['pricetier_settings'] ?? [];
    update_option('pricetier_settings', [
      'enabled' => !empty($raw_s['enabled']),
      'cost_meta_key' => sanitize_key(wp_unslash($raw_s['cost_meta_key'] ?? ''))
    ]);

    // Save Rules
    $raw_r = $_POST['pricetier_rules'] ?? [];
    $saved = [];
    foreach ($raw_r as $k => $r) {
      if ($k === 'new' && empty($r['name'])) continue;
      // Note: Config::sanitize_rule handles deep sanitization, but we should unslash first
      $saved[] = Config::sanitize_rule(wp_unslash($r));
    }
    update_option('pricetier_rules', $saved);
    self::add_notice(__('Settings saved.', 'pricetier'));
    
    // PRG pattern: Redirect to prevent form resubmission and render quirks
    wp_safe_redirect(add_query_arg('saved', 'true', remove_query_arg('saved')));
    exit;
  }

  private static function get_cost_sources(): array {
    $src = [
      '_price' => 'Product: Active Price',
      '_regular_price' => 'Product: Regular Price',
      '_sale_price' => 'Product: Sale Price'
    ];
    $pid = wc_get_products(['limit'=>1, 'status'=>'publish', 'return'=>'ids']);
    if (!empty($pid)) {
      foreach (get_post_meta($pid[0]) as $k => $v) {
        if (strpos($k, '_') === 0 && !in_array($k, ['_wc_cog_cost', '_price', '_regular_price', '_sale_price'])) continue;
        if (is_numeric($v[0] ?? null)) $src[$k] = "Meta: $k";
      }
    }
    ksort($src);
    return $src;
  }

  public static function enqueue_assets(): void {
    if (!isset($_GET['page']) || $_GET['page'] !== 'pricetier') return;
    
    wp_enqueue_script('wc-enhanced-select');
    wp_enqueue_style('woocommerce_admin_styles');
    
    // Vite built assets
    wp_enqueue_style('pricetier-admin', PRICETIER_URL . 'assets/dist/css/style.css', ['woocommerce_admin_styles'], PRICETIER_VERSION);
    wp_enqueue_script('pricetier-admin', PRICETIER_URL . 'assets/dist/js/admin.js', ['jquery', 'wc-enhanced-select'], PRICETIER_VERSION, true);

    ob_start();
    self::render_rule_block(Config::get_rule_defaults(), '__INDEX__', true);
    $template = ob_get_clean();

    wp_localize_script('pricetier-admin', 'PriceTierAdmin', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('pricetier_admin'),
      'ruleTemplate' => $template
    ]);
  }

  public static function ajax_get_terms(): void {
    check_ajax_referer('pricetier_admin', 'nonce');
    $tax = sanitize_text_field(wp_unslash($_POST['taxonomy'] ?? ''));
    $terms = get_terms(['taxonomy'=>$tax, 'hide_empty'=>false]);
    if (is_wp_error($terms)) wp_send_json_error();
    
    $out = [];
    foreach ($terms as $t) $out[] = ['id'=>$t->term_id, 'text'=>$t->name];
    wp_send_json_success($out);
  }

  public static function ajax_search_users(): void {
    check_ajax_referer('pricetier_admin', 'nonce');
    $s = sanitize_text_field(wp_unslash($_GET['search'] ?? ''));
    $users = new \WP_User_Query([
      'search' => "*$s*", 
      'search_columns' => ['user_login', 'user_email', 'display_name'],
      'number' => 20
    ]);
    
    $out = [];
    foreach ($users->get_results() as $u) $out[] = ['id'=>$u->ID, 'text'=>"$u->display_name ($u->user_email)"];
    wp_send_json_success($out);
  }

  public static function ajax_lookup_product(): void {
    check_ajax_referer('pricetier_admin', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error();

    $pid = (int) $_POST['product_id'];
    $product = wc_get_product($pid);
    if (!$product) wp_send_json_error(['message' => 'Product not found']);

    $key = Config::get_cost_meta_key();
    $cost = $product->get_meta($key);

    wp_send_json_success([
        'cost_key' => $key,
        'cost'     => is_numeric($cost) ? wc_price($cost) : '—',
        'regular'  => $product->get_regular_price() ? wc_price($product->get_regular_price()) : '—',
        'sale'     => $product->get_sale_price() ? wc_price($product->get_sale_price()) : '—'
    ]);
  }

  private static function add_notice(string $msg, string $type = 'success'): void {
    $n = get_transient('pricetier_notices') ?: [];
    $n[] = ['message'=>$msg, 'type'=>$type];
    set_transient('pricetier_notices', $n, 30);
  }

  private static function render_notices(): void {
    if ($n = get_transient('pricetier_notices')) {
      delete_transient('pricetier_notices');
      foreach ($n as $note) printf('<div class="notice notice-%s is-dismissible inline"><p>%s</p></div>', esc_attr($note['type']), esc_html($note['message']));
    }
  }
}
