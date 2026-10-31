<?php

declare(strict_types=1);

namespace BlackDigital\CustomAuthorBox\Seo;

use BlackDigital\CustomAuthorBox\Author;
use BlackDigital\CustomAuthorBox\Plugin;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Yoast SEO integration. On posts with a custom author, Yoast's author
 * output uses that author instead of the WordPress user:
 *
 *  - <meta name="author">
 *  - article:author        → author's Facebook URL (removed if none)
 *  - twitter:creator       → author's X handle (falls back to the site's X account)
 *  - twitter:label1/data1  → "Written by" in Slack / X / Discord previews
 *  - Schema.org Article author → Person node with image, bio and sameAs links
 *
 * Posts without a custom author are untouched. If Yoast isn't active these
 * filters simply never run.
 */
final class Yoast
{
  public function __construct(private readonly Plugin $plugin)
  {
  }

  public function register(): void
  {
    add_filter('wpseo_meta_author', $this->metaAuthor(...));
    add_filter('wpseo_opengraph_author_facebook', $this->facebook(...));
    add_filter('wpseo_twitter_creator_account', $this->twitterCreator(...));
    add_filter('wpseo_enhanced_slack_data', $this->slackData(...));
    add_filter('wpseo_schema_graph', $this->schemaGraph(...), 20);
  }

  private function author(): ?Author
  {
    if (!is_singular(Plugin::postTypes())) {
      return null;
    }
    return $this->plugin->authorForPost((int) get_queried_object_id());
  }

  private function metaAuthor(mixed $name): mixed
  {
    return $this->author()?->name ?? $name;
  }

  private function facebook(mixed $url): mixed
  {
    $author = $this->author();
    if ($author === null) {
      return $url;
    }
    // Never fall back to the WordPress user's Facebook profile
    return $author->facebookUrl !== '' ? $author->facebookUrl : '';
  }

  private function twitterCreator(mixed $handle): mixed
  {
    $author = $this->author();
    if ($author === null) {
      return $handle;
    }
    // Empty makes Yoast fall back to the site's own X account, if set
    return $author->xHandle;
  }

  private function slackData(mixed $data): mixed
  {
    $author = $this->author();
    if ($author === null || !is_array($data)) {
      return $data;
    }

    // Yoast keys this array by translated label ("Written by"), so match on
    // the WordPress author's display name rather than the key.
    $postId = (int) get_queried_object_id();
    $wpName = (string) get_the_author_meta('display_name', (int) get_post_field('post_author', $postId));

    foreach ($data as $label => $value) {
      if ($value === $wpName) {
        $data[$label] = $author->name;
      }
    }

    return $data;
  }

  private function schemaGraph(mixed $graph): mixed
  {
    $author = $this->author();
    if ($author === null || !is_array($graph)) {
      return $graph;
    }

    return self::rewriteGraph($graph, $author, home_url('/'));
  }

  /**
   * Point every piece's author at a Person node for the custom author, and
   * drop the WordPress user's Person node if nothing else references it
   * (it stays when the site is set to represent that person as publisher).
   *
   * @param array<int, mixed> $graph
   * @return array<int, mixed>
   */
  public static function rewriteGraph(array $graph, Author $author, string $homeUrl): array
  {
    $personId = trailingslashit($homeUrl) . '#/schema/person/custom-author-' . $author->id;
    $oldIds   = [];

    foreach ($graph as $i => $piece) {
      if (!is_array($piece) || empty($piece['author']) || !is_array($piece['author'])) {
        continue;
      }
      if (!empty($piece['author']['@id']) && is_string($piece['author']['@id'])) {
        $oldIds[] = $piece['author']['@id'];
      }
      $graph[$i]['author'] = ['name' => $author->name, '@id' => $personId];
    }

    if ($oldIds === []) {
      return $graph;
    }

    $person = array_filter([
      '@type'       => 'Person',
      '@id'         => $personId,
      'name'        => $author->name,
      'description' => $author->bio,
      'url'         => $author->postsUrl,
      'sameAs'      => $author->sameAs(),
    ]);

    $image = $author->imageSrc('medium');
    if ($image !== '') {
      $person['image'] = [
        '@type'      => 'ImageObject',
        '@id'        => $personId . '/image',
        'url'        => $image,
        'contentUrl' => $image,
        'caption'    => $author->name,
      ];
    }

    $oldIds = array_unique($oldIds);

    foreach ($graph as $i => $piece) {
      if (!is_array($piece) || !isset($piece['@id']) || !in_array($piece['@id'], $oldIds, true)) {
        continue;
      }
      $others = $graph;
      unset($others[$i]);
      if (!self::references($others, $piece['@id'])) {
        unset($graph[$i]);
      }
    }

    $graph[] = $person;

    return array_values($graph);
  }

  /**
   * Whether any {"@id": $id} reference appears anywhere in $data.
   */
  private static function references(mixed $data, string $id): bool
  {
    if (!is_array($data)) {
      return false;
    }
    if (($data['@id'] ?? null) === $id) {
      return true;
    }
    foreach ($data as $value) {
      if (is_array($value) && self::references($value, $id)) {
        return true;
      }
    }
    return false;
  }
}
