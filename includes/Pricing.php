<?php

/**
 * PriceTier Pricing Engine
 *
 * Handles price filtering and rule matching.
 */

namespace PriceTier;

use WC_Product;
use WP_User;

defined('ABSPATH') || exit;

final class Pricing {

  private static ?array $rules = null;

  public static function init(): void {
    add_filter('woocommerce_product_get_price', [__CLASS__, 'filter_price'], 20, 2);
    add_filter('woocommerce_product_get_regular_price', [__CLASS__, 'filter_regular_price'], 20, 2);
    add_filter('woocommerce_product_get_sale_price', [__CLASS__, 'filter_sale_price'], 20, 2);
  }

  /* -------------------------------------------------------------------------
   * Filter Hooks
   * ------------------------------------------------------------------------- */

  public static function filter_price($price, WC_Product $product) {
    if (! Config::is_enabled()) return $price;

    $rules = self::get_rules();
    $user  = wp_get_current_user();

    foreach ($rules as $rule) {
      if (! self::rule_applies($rule, $product, $user)) continue;

      if ($rule['pricing']['apply_to'] === 'sale') {
        $sale = $product->get_sale_price();
        return $sale !== '' ? $sale : $price;
      }
    }
    return self::apply_pricing($price, $product, 'final');
  }

  public static function filter_regular_price($price, WC_Product $product) {
    return self::apply_pricing($price, $product, 'regular');
  }

  public static function filter_sale_price($price, WC_Product $product) {
    return self::apply_pricing($price, $product, 'sale');
  }

  /* -------------------------------------------------------------------------
   * Calculation Logic
   * ------------------------------------------------------------------------- */

  private static function apply_pricing($price, WC_Product $product, string $context) {
    if (! Config::is_enabled()) return $price;

    $rules = self::get_rules();
    $user  = wp_get_current_user();

    foreach ($rules as $rule) {
      if (! self::rule_applies($rule, $product, $user)) continue;

      $pricing = $rule['pricing'];
      $apply   = $pricing['apply_to'];

      // Guard: Don't override final if rule targets sale
      if ($apply === 'sale' && $context === 'final') continue;

      // Context check
      if ($apply !== 'final' && $apply !== $context) continue;

      // Calculate
      $cost = self::get_cost($product);
      if ($cost === null) return $price;

      $new_price = ($pricing['type'] === 'percent')
        ? $cost * (1 + ($pricing['value'] / 100))
        : $cost + $pricing['value'];

      // Rounding
      switch ($pricing['rounding']) {
        case 'up':      $new_price = ceil($new_price); break;
        case 'down':    $new_price = floor($new_price); break;
        case 'nearest': $new_price = round($new_price); break;
      }

      // Bounds
      if ($pricing['min_price'] !== null) $new_price = max($pricing['min_price'], $new_price);
      if ($pricing['max_price'] !== null) $new_price = min($pricing['max_price'], $new_price);

      return wc_format_decimal($new_price);
    }

    return $price;
  }

  private static function get_cost(WC_Product $product): ?float {
    $key = Config::get_cost_meta_key();
    if (!$key) return null;
    $cost = $product->get_meta($key);
    return is_numeric($cost) ? (float) $cost : null;
  }

  private static function get_rules(): array {
    if (self::$rules !== null) return self::$rules;

    $stored = get_option('pricetier_rules', []);
    $rules = [];

    foreach ((array) $stored as $r) {
      $r = Config::sanitize_rule((array) $r);
      if (Config::is_rule_valid($r) && $r['enabled']) {
        $rules[] = $r;
      }
    }

    usort($rules, fn($a, $b) => $a['priority'] <=> $b['priority']);
    self::$rules = $rules;
    return $rules;
  }

  /* -------------------------------------------------------------------------
   * Matching Logic
   * ------------------------------------------------------------------------- */

  private static function rule_applies(array $rule, WC_Product $product, ?WP_User $user): bool {
    // User Match
    $u_cond = $rule['users'];
    $u_match = false;
    
    if ($user && $user->exists()) {
      switch ($u_cond['type']) {
        case 'all':   $u_match = true; break;
        case 'users': $u_match = in_array($user->ID, $u_cond['users'], true); break;
        case 'roles': 
          foreach ($user->roles as $role) {
            if (in_array($role, $u_cond['roles'], true)) {
              $u_match = true;
              break;
            }
          }
          break;
      }
    }

    if (!$u_match) return false;

    // Product Match
    $p_cond = $rule['products'];
    $pid    = $product->get_id();

    switch ($p_cond['type']) {
      case 'all':        return true;
      case 'products':   return in_array($pid, $p_cond['products'], true);
      case 'categories': return has_term($p_cond['categories'], 'product_cat', $pid);
      case 'tags':       return has_term($p_cond['tags'], 'product_tag', $pid);
      case 'attribute':
        if (empty($p_cond['attribute']['taxonomy'])) return false;
        return has_term($p_cond['attribute']['terms'], $p_cond['attribute']['taxonomy'], $pid);
    }

    return false;
  }
}
