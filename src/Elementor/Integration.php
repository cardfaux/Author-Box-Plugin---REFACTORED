<?php

declare(strict_types=1);

namespace BlackDigital\CustomAuthorBox\Elementor;

if (!defined('ABSPATH')) {
  exit;
}

final class Integration
{
  public const STYLE_HANDLE      = 'custom-author-box';
  private const MIN_ELEMENTOR    = '3.5.0';

  public function register(): void
  {
    add_action('wp_enqueue_scripts', $this->registerStyles(...));
    add_action('elementor/frontend/after_register_styles', $this->registerStyles(...));
    add_action('elementor/widgets/register', $this->registerWidget(...));
    add_action('admin_notices', $this->dependencyNotice(...));
  }

  private function registerStyles(): void
  {
    if (!wp_style_is(self::STYLE_HANDLE, 'registered')) {
      wp_register_style(self::STYLE_HANDLE, CUSTOM_AUTHOR_BOX_URL . 'assets/css/author-box.css', [], CUSTOM_AUTHOR_BOX_VERSION);
    }
  }

  private function registerWidget(\Elementor\Widgets_Manager $manager): void
  {
    if (!$this->elementorSupported()) {
      return;
    }
    $manager->register(new AuthorBoxWidget());
  }

  private function elementorSupported(): bool
  {
    return defined('ELEMENTOR_VERSION') && version_compare(ELEMENTOR_VERSION, self::MIN_ELEMENTOR, '>=');
  }

  private function dependencyNotice(): void
  {
    if (!current_user_can('activate_plugins') || $this->elementorSupported()) {
      return;
    }

    $message = defined('ELEMENTOR_VERSION')
      ? sprintf(
        /* translators: %s: minimum Elementor version */
        __('Custom Author Box needs Elementor %s or newer for its widget. Author management and SEO tags still work.', 'custom-author-box'),
        self::MIN_ELEMENTOR
      )
      : __('Custom Author Box needs Elementor active for its widget. Author management and SEO tags still work.', 'custom-author-box');

    printf('<div class="notice notice-warning"><p>%s</p></div>', esc_html($message));
  }
}
