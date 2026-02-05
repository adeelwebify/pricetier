<?php

/**
 * PriceTier Configuration & Schema
 *
 * Central access for global settings and data structures.
 */

namespace PriceTier;

defined('ABSPATH') || exit;

final class Config {

  private const OPTION_KEY = 'pricetier_settings';

  private const DEFAULTS = [
    'enabled'       => true,
    'cost_meta_key' => '_wc_cog_cost',
    'fallback_mode' => 'normal_price',
  ];

  /* -------------------------------------------------------------------------
   * Global Settings
   * ------------------------------------------------------------------------- */

  private static function get_settings(): array {
    $stored = get_option(self::OPTION_KEY, []);
    return array_merge(self::DEFAULTS, (array) $stored);
  }

  public static function is_enabled(): bool {
    return (bool) self::get_settings()['enabled'];
  }

  public static function get_cost_meta_key(): string {
    $key = self::get_settings()['cost_meta_key'];
    return (is_string($key) && $key !== '') ? sanitize_key($key) : self::DEFAULTS['cost_meta_key'];
  }

  public static function get_fallback_mode(): string {
    $mode = self::get_settings()['fallback_mode'];
    return in_array($mode, ['normal_price', 'block_purchase'], true) ? $mode : self::DEFAULTS['fallback_mode'];
  }

  /* -------------------------------------------------------------------------
   * Rule Schema
   * ------------------------------------------------------------------------- */

  public static function get_rule_defaults(): array {
    return [
      'id'       => '',
      'name'     => '',
      'enabled'  => true,
      'priority' => 10,
      'products' => [
        'type'       => 'all',
        'products'   => [],
        'categories' => [],
        'tags'       => [],
        'attribute'  => ['taxonomy' => '', 'terms' => []],
      ],
      'users'    => [
        'type'  => 'all',
        'roles' => [],
        'users' => [],
      ],
      'pricing'  => [
        'type'      => 'percent',
        'value'     => 0,
        'rounding'  => 'none',
        'min_price' => null,
        'max_price' => null,
        'apply_to'  => 'final',
      ],
    ];
  }

  public static function sanitize_rule(array $rule): array {
    $rule = array_replace_recursive(self::get_rule_defaults(), $rule);

    $rule['name']     = sanitize_text_field($rule['name']);
    $rule['enabled']  = (bool) $rule['enabled'];
    $rule['priority'] = (int) $rule['priority'];

    // Products
    $p = &$rule['products'];
    $p['type']       = sanitize_key($p['type']);
    $p['products']   = array_map('intval', (array) $p['products']);
    $p['categories'] = array_map('intval', (array) $p['categories']);
    $p['tags']       = array_map('intval', (array) $p['tags']);
    $p['attribute']['taxonomy'] = sanitize_key($p['attribute']['taxonomy']);
    $p['attribute']['terms']    = array_map('intval', (array) $p['attribute']['terms']);

    // Users
    $u = &$rule['users'];
    $u['type']  = sanitize_key($u['type']);
    $u['roles'] = array_map('sanitize_key', (array) $u['roles']);
    $u['users'] = array_map('intval', (array) $u['users']);

    // Pricing
    $pr = &$rule['pricing'];
    $pr['type']      = in_array($pr['type'], ['percent', 'fixed'], true) ? $pr['type'] : 'percent';
    $pr['value']     = (float) $pr['value'];
    $pr['rounding']  = in_array($pr['rounding'], ['none', 'up', 'down', 'nearest'], true) ? $pr['rounding'] : 'none';
    $pr['min_price'] = ($pr['min_price'] !== '' && $pr['min_price'] !== null) ? (float) $pr['min_price'] : null;
    $pr['max_price'] = ($pr['max_price'] !== '' && $pr['max_price'] !== null) ? (float) $pr['max_price'] : null;
    $pr['apply_to']  = in_array($pr['apply_to'], ['regular', 'sale', 'final'], true) ? $pr['apply_to'] : 'final';

    return $rule;
  }

  public static function is_rule_valid(array $rule): bool {
    return !empty($rule['name']) && $rule['pricing']['value'] >= 0;
  }
}
