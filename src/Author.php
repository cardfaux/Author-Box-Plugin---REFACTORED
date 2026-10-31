<?php

declare(strict_types=1);

namespace BlackDigital\CustomAuthorBox;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Immutable author record.
 */
final class Author
{
  public function __construct(
    public readonly int $id,
    public readonly string $name,
    public readonly string $bio = '',
    public readonly int $imageId = 0,
    public readonly string $imageUrl = '',
    public readonly string $postsUrl = '',
    public readonly string $websiteUrl = '',
    public readonly string $linkedinUrl = '',
    public readonly string $facebookUrl = '',
    public readonly string $xHandle = '',
  ) {
  }

  /**
   * Build from a database row (array). Missing columns (older schema) fall
   * back to empty values.
   *
   * @param array<string, mixed> $row
   */
  public static function fromRow(array $row): self
  {
    $str = static fn (string $key): string => isset($row[$key]) ? (string) $row[$key] : '';

    return new self(
      id: (int) ($row['id'] ?? 0),
      name: $str('name'),
      bio: $str('bio'),
      imageId: (int) ($row['image_id'] ?? 0),
      imageUrl: $str('image'),
      postsUrl: $str('user_posts_link'),
      websiteUrl: $str('website_link'),
      linkedinUrl: $str('linkedin_link'),
      facebookUrl: $str('facebook_url'),
      xHandle: $str('x_handle'),
    );
  }

  public function firstName(): string
  {
    $parts = preg_split('/\s+/u', trim($this->name)) ?: [];
    return $parts[0] ?? $this->name;
  }

  public function xUrl(): string
  {
    return $this->xHandle !== '' ? 'https://x.com/' . rawurlencode($this->xHandle) : '';
  }

  /**
   * Image URL, preferring the Media Library attachment.
   */
  public function imageSrc(string $size = 'medium'): string
  {
    if ($this->imageId > 0) {
      $src = wp_get_attachment_image_url($this->imageId, $size);
      if (is_string($src) && $src !== '') {
        return $src;
      }
    }
    return $this->imageUrl;
  }

  /**
   * <img> markup. Uses wp_get_attachment_image() (srcset, sizes, lazy
   * loading) when the image is in the Media Library.
   *
   * @param array<string, string> $attr
   */
  public function imageHtml(string $size, array $attr): string
  {
    $attr['alt'] = $this->name;

    if ($this->imageId > 0) {
      $html = wp_get_attachment_image($this->imageId, $size, false, $attr);
      if ($html !== '') {
        return $html;
      }
    }

    if ($this->imageUrl === '') {
      return '';
    }

    $attr += ['loading' => 'lazy', 'decoding' => 'async'];
    $attributes = '';
    foreach ($attr as $key => $value) {
      $attributes .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
    }

    return sprintf('<img src="%s"%s>', esc_url($this->imageUrl), $attributes);
  }

  /**
   * Profile URLs for Schema.org sameAs.
   *
   * @return list<string>
   */
  public function sameAs(): array
  {
    return array_values(array_filter([
      $this->websiteUrl,
      $this->linkedinUrl,
      $this->facebookUrl,
      $this->xUrl(),
    ]));
  }

  /**
   * Social links for display, keyed by network.
   *
   * @return array<string, string>
   */
  public function socialLinks(): array
  {
    return array_filter([
      'x'        => $this->xUrl(),
      'facebook' => $this->facebookUrl,
      'linkedin' => $this->linkedinUrl,
      'website'  => $this->websiteUrl,
    ]);
  }
}
