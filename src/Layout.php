<?php

declare(strict_types=1);

namespace BlackDigital\CustomAuthorBox;

if (!defined('ABSPATH')) {
  exit;
}

enum Layout: string
{
  case Classic  = 'classic';
  case Centered = 'centered';
  case Compact  = 'compact';
  case Card     = 'card';

  public function label(): string
  {
    return match ($this) {
      self::Classic  => __('Classic — image left', 'custom-author-box'),
      self::Centered => __('Centered — stacked', 'custom-author-box'),
      self::Compact  => __('Compact — byline', 'custom-author-box'),
      self::Card     => __('Card — header band', 'custom-author-box'),
    };
  }

  /** Default Media Library size to request for this layout. */
  public function imageSize(): string
  {
    return $this === self::Compact ? 'thumbnail' : 'medium';
  }

  /**
   * The <img sizes> hint: roughly how wide the image displays, so the
   * browser picks a small file from srcset instead of the full photo.
   */
  public function sizesAttr(): string
  {
    return match ($this) {
      self::Compact => '64px',
      self::Card, self::Centered => '(min-width: 600px) 160px, 120px',
      self::Classic => '120px',
    };
  }

  /**
   * @return array<string, string>
   */
  public static function options(): array
  {
    $options = [];
    foreach (self::cases() as $case) {
      $options[$case->value] = $case->label();
    }
    return $options;
  }

  public static function fromSetting(mixed $value): self
  {
    return is_string($value) ? (self::tryFrom($value) ?? self::Classic) : self::Classic;
  }
}
