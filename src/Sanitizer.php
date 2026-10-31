<?php

declare(strict_types=1);

namespace BlackDigital\CustomAuthorBox;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Input validation for author fields. Each method returns the clean value,
 * '' for empty input, or null when the input was provided but invalid.
 */
final class Sanitizer
{
  public const NAME_MAX = 120;
  public const BIO_MAX  = 1000;

  /**
   * Accepts "handle", "@handle", or an x.com / twitter.com profile URL.
   */
  public static function xHandle(string $input): ?string
  {
    $input = trim($input);
    if ($input === '') {
      return '';
    }

    $pattern = '~^(?:(?:https?://)?(?:www\.|mobile\.)?(?:x|twitter)\.com/)?@?([A-Za-z0-9_]{1,15})/?(?:[?#].*)?$~i';

    return preg_match($pattern, $input, $m) === 1 ? $m[1] : null;
  }

  public static function facebookUrl(string $input): ?string
  {
    return self::profileUrl($input, ['facebook.com', 'fb.com']);
  }

  public static function linkedinUrl(string $input): ?string
  {
    return self::profileUrl($input, ['linkedin.com']);
  }

  /**
   * Any http(s) URL.
   */
  public static function httpUrl(string $input): ?string
  {
    $input = trim($input);
    if ($input === '') {
      return '';
    }

    // esc_url_raw() strips anything that isn't http(s) (javascript:, data:, …)
    $url  = esc_url_raw($input, ['http', 'https']);
    $host = $url !== '' ? wp_parse_url($url, PHP_URL_HOST) : null;

    if (!is_string($host) || $host === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
      return null;
    }

    return strlen($url) <= 255 ? $url : null;
  }

  public static function name(string $input): string
  {
    return mb_substr(sanitize_text_field($input), 0, self::NAME_MAX);
  }

  public static function bio(string $input): string
  {
    return mb_substr(sanitize_textarea_field($input), 0, self::BIO_MAX);
  }

  /**
   * https URL whose host is one of $domains or a subdomain of one.
   *
   * @param list<string> $domains
   */
  private static function profileUrl(string $input, array $domains): ?string
  {
    $input = trim($input);
    if ($input === '') {
      return '';
    }

    if (!preg_match('~^https?://~i', $input)) {
      $input = 'https://' . $input;
    }

    $url  = self::httpUrl($input);
    $host = is_string($url) && $url !== '' ? strtolower((string) wp_parse_url($url, PHP_URL_HOST)) : '';

    foreach ($domains as $domain) {
      if ($host === $domain || str_ends_with($host, '.' . $domain)) {
        return set_url_scheme((string) $url, 'https');
      }
    }

    return null;
  }
}
