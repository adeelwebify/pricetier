<?php

namespace PriceTier;

defined('ABSPATH') || exit;

class Updater {

  private $plugin_slug;
  private $plugin_basename;
  private $version;
  private $cache_key;
  private $cache_allowed;
  private $github_owner;
  private $github_repo;

  public function __construct(string $plugin_file, string $version, string $github_owner, string $github_repo) {
    $this->plugin_basename = plugin_basename($plugin_file);
    $this->plugin_slug = dirname($this->plugin_basename);
    $this->version = $version;
    $this->cache_key = 'pricetier_updater_' . $this->plugin_slug . '_release_info';
    $this->cache_allowed = false; // Disable cache for debugging/instant updates
    $this->github_owner = $github_owner;
    $this->github_repo = $github_repo;

    add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
    add_filter('plugins_api', [$this, 'check_info'], 10, 3);
  }

  public function check_update($transient) {
    if (empty($transient->checked)) {
      return $transient;
    }

    $remote = $this->get_remote();

    if ($remote && version_compare($this->version, $remote->version, '<')) {
      $res = new \stdClass();
      $res->slug = $this->plugin_slug;
      $res->plugin = $this->plugin_basename; // Correctly matches installed file path "folder/pricetier.php"
      $res->new_version = $remote->version;
      $res->tested = $remote->tested;
      $res->package = $remote->download_url;
      
      $transient->response[$res->plugin] = $res;
    }

    return $transient;
  }

  public function check_info($false, $action, $arg) {
    if (isset($arg->slug) && $arg->slug === $this->plugin_slug) {
      $remote = $this->get_remote();
      if ($remote) {
        $res = new \stdClass();
        $res->name = $remote->name;
        $res->slug = $this->plugin_slug;
        $res->version = $remote->version;
        $res->tested = $remote->tested;
        $res->requires = $remote->requires;
        $res->author = $remote->author;
        $res->requires_php = $remote->requires_php;
        $res->last_updated = $remote->last_updated;
        $res->sections = [
          'description' => $remote->sections['description'],
          'installation' => $remote->sections['installation'],
          'changelog' => $remote->sections['changelog'],
        ];
        $res->download_link = $remote->download_url;
        $res->trunk = $remote->download_url;
        return $res;
      }
    }
    return $false;
  }

  private function get_remote() {
    $remote = get_transient($this->cache_key);

    if (false === $remote || !$this->cache_allowed) {
      $url = "https://api.github.com/repos/{$this->github_owner}/{$this->github_repo}/releases/latest";
      $response = wp_remote_get($url, [
        'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
        'timeout' => 10
      ]);

      if (is_wp_error($response)) {
        // error_log('PriceTier Updater Error: ' . $response->get_error_message());
        return false; 
      }
      
      if (200 !== wp_remote_retrieve_response_code($response)) {
        // error_log('PriceTier Updater API Error: ' . wp_remote_retrieve_response_code($response));
        return false;
      }

      $body = json_decode(wp_remote_retrieve_body($response));

      if (!$body) return false;

      $remote = new \stdClass();
      $remote->name = $body->name;
      $remote->version = str_replace('v', '', $body->tag_name);
      $remote->last_updated = $body->published_at;
      $remote->download_url = $body->zipball_url;
      $remote->author = $body->author->login;
      
      // Look for a specific asset zip if available (preferred over source code)
      if (!empty($body->assets)) {
        foreach ($body->assets as $asset) {
          if (strpos($asset->name, '.zip') !== false) {
             $remote->download_url = $asset->browser_download_url;
             break;
          }
        }
      }

      // Hardcoded for now as GitHub doesn't provide these easily in the release body without parsing
      $remote->tested = '6.7';
      $remote->requires = '6.0';
      $remote->requires_php = '7.4';
      
      $remote->sections = [
        'description' => 'Latest version from GitHub.',
        'installation' => 'Install via the Updates page.',
        'changelog' => isset($body->body) ? nl2br($body->body) : 'No changelog provided.' // Use body direct from GH
      ];

      set_transient($this->cache_key, $remote, 12 * HOUR_IN_SECONDS);
    }

    return $remote;
  }
}
