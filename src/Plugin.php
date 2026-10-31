<?php

declare(strict_types=1);

namespace BlackDigital\CustomAuthorBox;

use BlackDigital\CustomAuthorBox\Admin\PostMetabox;
use BlackDigital\CustomAuthorBox\Admin\SettingsPage;
use BlackDigital\CustomAuthorBox\Elementor\Integration as ElementorIntegration;
use BlackDigital\CustomAuthorBox\Seo\Yoast;

if (!defined('ABSPATH')) {
  exit;
}

final class Plugin
{
  public const META_KEY    = '_custom_author_box_author';
  public const TEXT_DOMAIN = 'custom-author-box';

  private static ?self $instance = null;

  private function __construct(public readonly AuthorRepository $authors)
  {
  }

  public static function instance(): self
  {
    if (self::$instance === null) {
      global $wpdb;
      self::$instance = new self(new AuthorRepository($wpdb));
    }
    return self::$instance;
  }

  public static function activate(): void
  {
    self::instance()->authors->install();
  }

  public static function boot(): void
  {
    $plugin = self::instance();

    // Plugin updates don't re-run the activation hook; this is a cheap
    // autoloaded-option comparison on every request.
    add_action('init', $plugin->authors->maybeUpgrade(...), 5);
    add_action('init', static fn () => load_plugin_textdomain(self::TEXT_DOMAIN, false, dirname(plugin_basename(CUSTOM_AUTHOR_BOX_FILE)) . '/languages'));

    (new SettingsPage($plugin->authors))->register();
    (new PostMetabox($plugin->authors))->register();
    (new Yoast($plugin))->register();
    (new ElementorIntegration())->register();
  }

  /**
   * Post types that can have a custom author.
   *
   * @return list<string>
   */
  public static function postTypes(): array
  {
    $types = apply_filters('custom_author_box_post_types', ['post']);
    return array_values(array_filter((array) $types, 'is_string'));
  }

  /**
   * Capability required to manage authors.
   */
  public static function capability(): string
  {
    $cap = apply_filters('custom_author_box_capability', 'manage_options');
    return is_string($cap) && $cap !== '' ? $cap : 'manage_options';
  }

  /**
   * The custom author assigned to a post, if any.
   */
  public function authorForPost(int $postId): ?Author
  {
    if ($postId <= 0 || !in_array(get_post_type($postId), self::postTypes(), true)) {
      return null;
    }

    $authorId = absint(get_post_meta($postId, self::META_KEY, true));

    return $authorId > 0 ? $this->authors->find($authorId) : null;
  }
}
