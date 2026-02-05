<?php

/**
 * Plugin Bootstrap
 */

namespace PriceTier;

defined('ABSPATH') || exit;

use PriceTier\Admin\Ajax;
use PriceTier\Admin\Assets;
use PriceTier\Admin\Menu;

final class Plugin {

  private static ?Plugin $instance = null;

  public static function instance(): Plugin {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct() {
    $this->init();
  }

  private function init(): void {
    Config::is_enabled(); // Ensure defaults if needed? No, purely static.
    
    if (is_admin()) {
      Admin::init();
    }
    
    Pricing::init();

    // Auto-Updates
    new Updater('pricetier', PRICETIER_VERSION, 'adeelwebify', 'pricetier');
  }
}
