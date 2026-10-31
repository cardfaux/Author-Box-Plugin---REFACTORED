<?php

declare(strict_types=1);

namespace BlackDigital\CustomAuthorBox\Elementor;

use BlackDigital\CustomAuthorBox\Author;
use BlackDigital\CustomAuthorBox\Layout;
use BlackDigital\CustomAuthorBox\Plugin;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if (!defined('ABSPATH')) {
  exit;
}

class AuthorBoxWidget extends Widget_Base
{
  private const NAME_TAGS = ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'p' => 'p', 'div' => 'div'];

  // Same name as v1/v2 so existing widgets on pages keep working
  public function get_name(): string
  {
    return 'custom_author_box_widget';
  }

  public function get_title(): string
  {
    return __('Custom Author Box', 'custom-author-box');
  }

  public function get_icon(): string
  {
    return 'eicon-person';
  }

  public function get_categories(): array
  {
    return ['general'];
  }

  public function get_keywords(): array
  {
    return ['author', 'byline', 'bio', 'writer'];
  }

  public function get_style_depends(): array
  {
    return [Integration::STYLE_HANDLE];
  }

  /**
   * Output depends on the current post, so it must never be served from
   * Elementor's element cache.
   */
  protected function is_dynamic_content(): bool
  {
    return true;
  }

  /**
   * Use Elementor's optimized markup (no extra .elementor-widget-container).
   */
  public function has_widget_inner_wrapper(): bool
  {
    return false;
  }

  protected function register_controls(): void
  {
    $this->registerContentControls();
    $this->registerBoxStyles();
    $this->registerBandStyles();
    $this->registerImageStyles();
    foreach ($this->textParts() as $key => [$label, $selector]) {
      $this->registerTextStyles($key, $label, $selector);
    }
    $this->registerSocialStyles();
  }

  /**
   * @return array<string, array{0: string, 1: string}>
   */
  private function textParts(): array
  {
    return [
      'heading' => [__('Heading', 'custom-author-box'), '.cab__heading'],
      'name'    => [__('Name', 'custom-author-box'), '.cab__name'],
      'bio'     => [__('Bio', 'custom-author-box'), '.cab__bio'],
      'link'    => [__('Link', 'custom-author-box'), '.cab__link'],
    ];
  }

  private function registerContentControls(): void
  {
    $this->start_controls_section('content_section', [
      'label' => __('Author Box', 'custom-author-box'),
      'tab'   => Controls_Manager::TAB_CONTENT,
    ]);

    $this->add_control('layout', [
      'label'   => __('Layout', 'custom-author-box'),
      'type'    => Controls_Manager::SELECT,
      'default' => Layout::Classic->value,
      'options' => Layout::options(),
    ]);

    $this->add_control('source', [
      'label'     => __('Author', 'custom-author-box'),
      'type'      => Controls_Manager::SELECT,
      'default'   => 'post',
      'options'   => [
        'post'     => __('Author selected on the post', 'custom-author-box'),
        'specific' => __('A specific author', 'custom-author-box'),
      ],
      'separator' => 'before',
    ]);

    $this->add_control('specific_author', [
      'label'     => __('Choose Author', 'custom-author-box'),
      'type'      => Controls_Manager::SELECT,
      'default'   => '',
      'options'   => $this->authorOptions(),
      'condition' => ['source' => 'specific'],
    ]);

    $this->add_control('show_heading', [
      'label'     => __('Show Heading', 'custom-author-box'),
      'type'      => Controls_Manager::SWITCHER,
      'default'   => 'yes',
      'separator' => 'before',
    ]);

    $this->add_control('heading_text', [
      'label'     => __('Heading Text', 'custom-author-box'),
      'type'      => Controls_Manager::TEXT,
      'default'   => __('About the Author', 'custom-author-box'),
      'condition' => ['show_heading' => 'yes'],
    ]);

    $this->add_control('name_tag', [
      'label'   => __('Name HTML Tag', 'custom-author-box'),
      'type'    => Controls_Manager::SELECT,
      'default' => 'h3',
      'options' => self::NAME_TAGS,
    ]);

    $this->add_control('show_image', [
      'label'   => __('Show Image', 'custom-author-box'),
      'type'    => Controls_Manager::SWITCHER,
      'default' => 'yes',
    ]);

    $this->add_control('show_bio', [
      'label'   => __('Show Bio', 'custom-author-box'),
      'type'    => Controls_Manager::SWITCHER,
      'default' => 'yes',
    ]);

    $this->add_control('show_link', [
      'label'       => __('Show "More From" Link', 'custom-author-box'),
      'type'        => Controls_Manager::SWITCHER,
      'default'     => 'yes',
      'description' => __('Only appears for authors with a posts link.', 'custom-author-box'),
    ]);

    $this->add_control('link_text', [
      'label'       => __('Link Text', 'custom-author-box'),
      'type'        => Controls_Manager::TEXT,
      'default'     => __('More From {first_name}', 'custom-author-box'),
      'description' => __('{first_name} is replaced with the author\'s first name.', 'custom-author-box'),
      'condition'   => ['show_link' => 'yes'],
    ]);

    $this->add_control('show_social', [
      'label'       => __('Show Social Icons', 'custom-author-box'),
      'type'        => Controls_Manager::SWITCHER,
      'default'     => '',
      'description' => __('X, Facebook, LinkedIn and website, when the author has them.', 'custom-author-box'),
    ]);

    $this->add_control('social_new_tab', [
      'label'     => __('Open Social Links in New Tab', 'custom-author-box'),
      'type'      => Controls_Manager::SWITCHER,
      'default'   => 'yes',
      'condition' => ['show_social' => 'yes'],
    ]);

    $this->end_controls_section();
  }

  private function registerBoxStyles(): void
  {
    $this->start_controls_section('box_style_section', [
      'label' => __('Box', 'custom-author-box'),
      'tab'   => Controls_Manager::TAB_STYLE,
    ]);

    $this->add_group_control(Group_Control_Border::get_type(), [
      'name'           => 'box_border',
      'selector'       => '{{WRAPPER}} .cab',
      'fields_options' => [
        'border' => ['default' => 'solid'],
        'width'  => ['default' => ['top' => 2, 'right' => 2, 'bottom' => 2, 'left' => 2, 'unit' => 'px', 'isLinked' => true]],
        'color'  => ['default' => '#0E2A3F'],
      ],
    ]);

    $this->add_responsive_control('box_radius', [
      'label'      => __('Border Radius', 'custom-author-box'),
      'type'       => Controls_Manager::DIMENSIONS,
      'size_units' => ['px', '%', 'em'],
      'selectors'  => ['{{WRAPPER}} .cab' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
    ]);

    $this->add_responsive_control('box_padding', [
      'label'      => __('Padding', 'custom-author-box'),
      'type'       => Controls_Manager::DIMENSIONS,
      'size_units' => ['px', 'em', '%'],
      // The card layout pads its body so the header band stays edge to edge
      'selectors'  => ['{{WRAPPER}} .cab:not(.cab--card), {{WRAPPER}} .cab--card .cab__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
    ]);

    $this->add_control('box_background', [
      'label'     => __('Background Color', 'custom-author-box'),
      'type'      => Controls_Manager::COLOR,
      'selectors' => ['{{WRAPPER}} .cab' => 'background-color: {{VALUE}};'],
    ]);

    $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
      'name'     => 'box_shadow',
      'selector' => '{{WRAPPER}} .cab',
    ]);

    $this->add_responsive_control('box_max_width', [
      'label'      => __('Max Width', 'custom-author-box'),
      'type'       => Controls_Manager::SLIDER,
      'size_units' => ['px', '%'],
      'range'      => ['px' => ['min' => 240, 'max' => 1400]],
      'selectors'  => ['{{WRAPPER}} .cab' => 'max-width: {{SIZE}}{{UNIT}}; margin-inline: auto;'],
    ]);

    $this->end_controls_section();
  }

  private function registerBandStyles(): void
  {
    $this->start_controls_section('band_style_section', [
      'label'     => __('Header Band', 'custom-author-box'),
      'tab'       => Controls_Manager::TAB_STYLE,
      'condition' => ['layout' => Layout::Card->value],
    ]);

    $this->add_control('band_color', [
      'label'     => __('Band Color', 'custom-author-box'),
      'type'      => Controls_Manager::COLOR,
      'selectors' => ['{{WRAPPER}} .cab__band' => 'background-color: {{VALUE}};'],
    ]);

    $this->add_responsive_control('band_height', [
      'label'      => __('Band Height', 'custom-author-box'),
      'type'       => Controls_Manager::SLIDER,
      'size_units' => ['px'],
      'range'      => ['px' => ['min' => 20, 'max' => 300]],
      'selectors'  => ['{{WRAPPER}} .cab__band' => 'height: {{SIZE}}{{UNIT}};'],
    ]);

    $this->end_controls_section();
  }

  private function registerImageStyles(): void
  {
    $this->start_controls_section('image_style_section', [
      'label'     => __('Image', 'custom-author-box'),
      'tab'       => Controls_Manager::TAB_STYLE,
      'condition' => ['show_image' => 'yes'],
    ]);

    $this->add_responsive_control('image_size', [
      'label'      => __('Size', 'custom-author-box'),
      'type'       => Controls_Manager::SLIDER,
      'size_units' => ['px'],
      'range'      => ['px' => ['min' => 32, 'max' => 300]],
      'selectors'  => ['{{WRAPPER}} .cab' => '--cab-image-size: {{SIZE}}{{UNIT}};'],
    ]);

    $this->add_responsive_control('image_gap', [
      'label'      => __('Space Around Image', 'custom-author-box'),
      'type'       => Controls_Manager::SLIDER,
      'size_units' => ['px'],
      'range'      => ['px' => ['min' => 0, 'max' => 100]],
      'selectors'  => ['{{WRAPPER}} .cab' => '--cab-gap: {{SIZE}}{{UNIT}};'],
    ]);

    $this->add_control('image_radius', [
      'label'      => __('Border Radius', 'custom-author-box'),
      'type'       => Controls_Manager::SLIDER,
      'size_units' => ['%', 'px'],
      'selectors'  => ['{{WRAPPER}} .cab .cab__image' => 'border-radius: {{SIZE}}{{UNIT}};'],
    ]);

    $this->add_group_control(Group_Control_Border::get_type(), [
      'name'     => 'image_border',
      'selector' => '{{WRAPPER}} .cab .cab__image',
    ]);

    $this->end_controls_section();
  }

  private function registerTextStyles(string $key, string $label, string $selector): void
  {
    $conditions = [
      'heading' => ['show_heading' => 'yes'],
      'bio'     => ['show_bio' => 'yes'],
      'link'    => ['show_link' => 'yes'],
    ];

    $this->start_controls_section($key . '_style_section', [
      'label'     => $label,
      'tab'       => Controls_Manager::TAB_STYLE,
      'condition' => $conditions[$key] ?? [],
    ]);

    $this->add_control($key . '_color', [
      'label'     => __('Color', 'custom-author-box'),
      'type'      => Controls_Manager::COLOR,
      'selectors' => ['{{WRAPPER}} ' . $selector => 'color: {{VALUE}};'],
    ]);

    if ($key === 'link') {
      $this->add_control('link_hover_color', [
        'label'     => __('Hover Color', 'custom-author-box'),
        'type'      => Controls_Manager::COLOR,
        'selectors' => ['{{WRAPPER}} .cab__link:hover, {{WRAPPER}} .cab__link:focus-visible' => 'color: {{VALUE}};'],
      ]);
    }

    $this->add_group_control(Group_Control_Typography::get_type(), [
      'name'     => $key . '_typography',
      'selector' => '{{WRAPPER}} ' . $selector,
    ]);

    $this->add_responsive_control($key . '_spacing', [
      'label'      => __('Bottom Spacing', 'custom-author-box'),
      'type'       => Controls_Manager::SLIDER,
      'size_units' => ['px', 'em'],
      'range'      => ['px' => ['min' => 0, 'max' => 60]],
      'selectors'  => ['{{WRAPPER}} ' . $selector => 'margin-bottom: {{SIZE}}{{UNIT}};'],
    ]);

    $this->end_controls_section();
  }

  private function registerSocialStyles(): void
  {
    $this->start_controls_section('social_style_section', [
      'label'     => __('Social Icons', 'custom-author-box'),
      'tab'       => Controls_Manager::TAB_STYLE,
      'condition' => ['show_social' => 'yes'],
    ]);

    $this->add_control('social_color', [
      'label'     => __('Color', 'custom-author-box'),
      'type'      => Controls_Manager::COLOR,
      'selectors' => ['{{WRAPPER}} .cab__social a' => 'color: {{VALUE}};'],
    ]);

    $this->add_control('social_hover_color', [
      'label'     => __('Hover Color', 'custom-author-box'),
      'type'      => Controls_Manager::COLOR,
      'selectors' => ['{{WRAPPER}} .cab__social a:hover, {{WRAPPER}} .cab__social a:focus-visible' => 'color: {{VALUE}};'],
    ]);

    $this->add_responsive_control('social_size', [
      'label'      => __('Icon Size', 'custom-author-box'),
      'type'       => Controls_Manager::SLIDER,
      'size_units' => ['px'],
      'range'      => ['px' => ['min' => 12, 'max' => 48]],
      'selectors'  => ['{{WRAPPER}} .cab__social' => '--cab-icon-size: {{SIZE}}{{UNIT}};'],
    ]);

    $this->add_responsive_control('social_gap', [
      'label'      => __('Spacing', 'custom-author-box'),
      'type'       => Controls_Manager::SLIDER,
      'size_units' => ['px'],
      'range'      => ['px' => ['min' => 0, 'max' => 40]],
      'selectors'  => ['{{WRAPPER}} .cab__social' => 'gap: {{SIZE}}{{UNIT}};'],
    ]);

    $this->end_controls_section();
  }

  /**
   * @return array<string, string>
   */
  private function authorOptions(): array
  {
    $options = ['' => __('— Select —', 'custom-author-box')];
    foreach (Plugin::instance()->authors->all() as $author) {
      $options[(string) $author->id] = $author->name;
    }
    return $options;
  }

  /**
   * @return array{0: ?Author, 1: bool} The author and whether it's an editor-only preview.
   */
  private function resolveAuthor(array $settings): array
  {
    $plugin = Plugin::instance();

    if (($settings['source'] ?? 'post') === 'specific') {
      return [$plugin->authors->find(absint($settings['specific_author'] ?? 0)), false];
    }

    $author = $plugin->authorForPost((int) get_the_ID());
    if ($author !== null) {
      return [$author, false];
    }

    // In the editor (e.g. a single-post template), preview with a real author
    if ($this->isEditor()) {
      return [$plugin->authors->all()[0] ?? null, true];
    }

    return [null, false];
  }

  private function isEditor(): bool
  {
    $elementor = \Elementor\Plugin::$instance;
    return $elementor->editor->is_edit_mode() || $elementor->preview->is_preview_mode();
  }

  protected function render(): void
  {
    $settings = $this->get_settings_for_display();
    [$author, $isPreview] = $this->resolveAuthor($settings);

    if ($author === null) {
      if ($this->isEditor()) {
        printf(
          '<div class="cab cab--empty">%s</div>',
          esc_html__('No authors yet. Add one under Settings → Custom Author Box.', 'custom-author-box')
        );
      }
      return;
    }

    $layout  = Layout::fromSetting($settings['layout'] ?? '');
    $nameTag = array_key_exists($settings['name_tag'] ?? '', self::NAME_TAGS) ? $settings['name_tag'] : 'h3';
    $on      = static fn (string $key): bool => ($settings[$key] ?? '') === 'yes';

    $linkText = (string) ($settings['link_text'] ?? '');
    $linkText = str_replace('{first_name}', $author->firstName(), $linkText !== '' ? $linkText : __('More From {first_name}', 'custom-author-box'));

    $showLink   = $on('show_link') && $author->postsUrl !== '';
    $social     = $on('show_social') ? $author->socialLinks() : [];
    $image      = $on('show_image') ? $author->imageHtml($layout->imageSize(), [
      'class'         => 'cab__image',
      'sizes'         => $layout->sizesAttr(),
      // Author photos sit below the fold; never compete with the post's main image
      'loading'       => 'lazy',
      'decoding'      => 'async',
      'fetchpriority' => 'low',
    ]) : '';
    $newTab     = $on('social_new_tab');

    if ($isPreview) {
      printf(
        '<p class="cab-preview-note">%s</p>',
        esc_html__('Preview: this post has no author selected, so the first author is shown. Nothing is shown on the live site.', 'custom-author-box')
      );
    }
    ?>
    <aside class="cab cab--<?php echo esc_attr($layout->value); ?>" aria-label="<?php echo esc_attr(sprintf(__('About %s', 'custom-author-box'), $author->name)); ?>">
      <?php if ($layout === Layout::Card) : ?>
        <div class="cab__band" aria-hidden="true"></div>
      <?php endif; ?>

      <?php if ($image !== '') : ?>
        <div class="cab__media"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in Author::imageHtml() ?></div>
      <?php endif; ?>

      <div class="cab__body">
        <?php if ($on('show_heading') && !empty($settings['heading_text'])) : ?>
          <p class="cab__heading"><?php echo esc_html($settings['heading_text']); ?></p>
        <?php endif; ?>

        <<?php echo tag_escape($nameTag); ?> class="cab__name"><?php echo esc_html($author->name); ?></<?php echo tag_escape($nameTag); ?>>

        <?php if ($on('show_bio') && $author->bio !== '') : ?>
          <p class="cab__bio"><?php echo esc_html($author->bio); ?></p>
        <?php endif; ?>

        <?php if ($showLink || $social) : ?>
          <div class="cab__footer">
            <?php if ($showLink) : ?>
              <a class="cab__link" href="<?php echo esc_url($author->postsUrl); ?>"><?php echo esc_html($linkText); ?> <span class="cab__arrow" aria-hidden="true">&rarr;</span></a>
            <?php endif; ?>

            <?php if ($social) : ?>
              <ul class="cab__social">
                <?php foreach ($social as $network => $url) : ?>
                  <li>
                    <a href="<?php echo esc_url($url); ?>"<?php echo $newTab ? ' target="_blank" rel="noopener noreferrer"' : ''; ?> aria-label="<?php echo esc_attr($this->socialLabel($network, $author->name)); ?>">
                      <?php echo $this->icon($network); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </aside>
    <?php
  }

  private function socialLabel(string $network, string $name): string
  {
    $label = match ($network) {
      'x'        => __('%s on X', 'custom-author-box'),
      'facebook' => __('%s on Facebook', 'custom-author-box'),
      'linkedin' => __('%s on LinkedIn', 'custom-author-box'),
      default    => __('%s\'s website', 'custom-author-box'),
    };
    return sprintf($label, $name);
  }

  private function icon(string $network): string
  {
    $svg = '<svg class="cab__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"%s>%s</svg>';

    return match ($network) {
      'x'        => sprintf($svg, ' fill="currentColor"', '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>'),
      'facebook' => sprintf($svg, ' fill="currentColor"', '<path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.413c0-3.026 1.792-4.697 4.533-4.697 1.313 0 2.686.236 2.686.236v2.971H15.83c-1.491 0-1.956.93-1.956 1.886v2.264h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/>'),
      'linkedin' => sprintf($svg, ' fill="currentColor"', '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 1 1 0-4.125 2.062 2.062 0 0 1 0 4.125zM7.119 20.452H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>'),
      default    => sprintf($svg, ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"', '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>'),
    };
  }

  // No content_template(): the editor renders through render() so the
  // preview shows real author data.
}
