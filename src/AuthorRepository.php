<?php

declare(strict_types=1);

namespace BlackDigital\CustomAuthorBox;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * All database access for authors. Every query uses $wpdb->prepare() or
 * $wpdb's insert/update/delete helpers, and reads go through the object cache.
 */
final class AuthorRepository
{
  public const DB_VERSION   = '3';
  private const OPTION      = 'custom_author_box_db_version';
  private const CACHE_GROUP = 'custom_author_box';

  /** Columns this plugin writes, with their $wpdb format. */
  private const COLUMNS = [
    'name'            => '%s',
    'bio'             => '%s',
    'image_id'        => '%d',
    'image'           => '%s',
    'user_posts_link' => '%s',
    'website_link'    => '%s',
    'linkedin_link'   => '%s',
    'facebook_url'    => '%s',
    'x_handle'        => '%s',
  ];

  public function __construct(private readonly \wpdb $db)
  {
  }

  public function table(): string
  {
    return $this->db->prefix . 'custom_authors';
  }

  public function find(int $id): ?Author
  {
    if ($id <= 0) {
      return null;
    }

    $key   = 'author_' . $id;
    $found = false;
    $row   = wp_cache_get($key, self::CACHE_GROUP, false, $found);

    if (!$found) {
      $row = $this->db->get_row(
        $this->db->prepare('SELECT * FROM %i WHERE id = %d', $this->table(), $id),
        ARRAY_A
      );
      $row = is_array($row) ? $row : 0; // cache misses too
      wp_cache_set($key, $row, self::CACHE_GROUP, HOUR_IN_SECONDS);
    }

    return is_array($row) ? Author::fromRow($row) : null;
  }

  /**
   * @return list<Author>
   */
  public function all(): array
  {
    $found = false;
    $rows  = wp_cache_get('all', self::CACHE_GROUP, false, $found);

    if (!$found || !is_array($rows)) {
      $rows = $this->db->get_results(
        $this->db->prepare('SELECT * FROM %i ORDER BY name ASC', $this->table()),
        ARRAY_A
      );
      $rows = is_array($rows) ? $rows : [];
      wp_cache_set('all', $rows, self::CACHE_GROUP, HOUR_IN_SECONDS);
    }

    return array_map(Author::fromRow(...), $rows);
  }

  /**
   * Insert ($id = 0) or update an author. $data must already be sanitized.
   *
   * @param array<string, string|int> $data
   * @return int|false The author ID, or false on failure.
   */
  public function save(array $data, int $id = 0): int|false
  {
    $data    = array_intersect_key($data, self::COLUMNS);
    $formats = array_values(array_intersect_key(self::COLUMNS, $data));

    if ($id > 0) {
      $ok = $this->db->update($this->table(), $data, ['id' => $id], $formats, ['%d']) !== false;
    } else {
      $ok = $this->db->insert($this->table(), $data, $formats) !== false;
      $id = (int) $this->db->insert_id;
    }

    $this->flush($id);

    return $ok ? $id : false;
  }

  public function delete(int $id): bool
  {
    $deleted = (bool) $this->db->delete($this->table(), ['id' => $id], ['%d']);

    if ($deleted) {
      // Unassign the author from any posts
      delete_metadata('post', 0, Plugin::META_KEY, (string) $id, true);
    }

    $this->flush($id);

    return $deleted;
  }

  /**
   * Create or update the table. dbDelta adds missing columns but never drops
   * any, so data from older versions (position, company) is kept.
   */
  public function install(): void
  {
    $table   = $this->table();
    $charset = $this->db->get_charset_collate();

    // dbDelta is picky: one column per line, two spaces after PRIMARY KEY.
    $sql = "CREATE TABLE {$table} (
  id mediumint(9) NOT NULL AUTO_INCREMENT,
  name tinytext NOT NULL,
  bio text NOT NULL,
  image_id bigint(20) unsigned NOT NULL DEFAULT 0,
  image varchar(255) NOT NULL DEFAULT '',
  user_posts_link varchar(255) DEFAULT NULL,
  website_link varchar(255) DEFAULT NULL,
  linkedin_link varchar(255) DEFAULT NULL,
  facebook_url varchar(255) DEFAULT NULL,
  x_handle varchar(15) DEFAULT NULL,
  PRIMARY KEY  (id)
) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // v1 created these as NOT NULL with no default; make them optional.
    $columns = $this->db->get_col($this->db->prepare('SHOW COLUMNS FROM %i', $table), 0);
    foreach (['position', 'company'] as $legacy) {
      if (in_array($legacy, $columns, true)) {
        $this->db->query($this->db->prepare('ALTER TABLE %i MODIFY %i tinytext NULL', $table, $legacy));
      }
    }

    if ($this->db->last_error !== '') {
      error_log('Custom Author Box: database upgrade error: ' . $this->db->last_error);
      return; // Leave the version unchanged so it retries
    }

    update_option(self::OPTION, self::DB_VERSION, true);
    $this->flush();
  }

  public function maybeUpgrade(): void
  {
    if (get_option(self::OPTION) !== self::DB_VERSION) {
      $this->install();
    }
  }

  private function flush(int $id = 0): void
  {
    wp_cache_delete('all', self::CACHE_GROUP);
    if ($id > 0) {
      wp_cache_delete('author_' . $id, self::CACHE_GROUP);
    }
  }
}
