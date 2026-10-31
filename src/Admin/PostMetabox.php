<?php

declare(strict_types=1);

namespace BlackDigital\CustomAuthorBox\Admin;

use BlackDigital\CustomAuthorBox\AuthorRepository;
use BlackDigital\CustomAuthorBox\Plugin;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * "Custom Author Box" panel on the post edit screen (classic and block editor).
 */
final class PostMetabox
{
  private const NONCE_ACTION = 'custom_author_box_save_post';
  private const NONCE_FIELD  = 'custom_author_box_post_nonce';

  public function __construct(private readonly AuthorRepository $authors)
  {
  }

  public function register(): void
  {
    add_action('init', $this->registerMeta(...));
    add_action('add_meta_boxes', $this->add(...));
    add_action('save_post', $this->save(...), 10, 2);
  }

  private function registerMeta(): void
  {
    foreach (Plugin::postTypes() as $postType) {
      register_post_meta($postType, Plugin::META_KEY, [
        'type'              => 'integer',
        'single'            => true,
        'default'           => 0,
        'sanitize_callback' => 'absint',
        'auth_callback'     => static fn ($allowed, $metaKey, $postId): bool => current_user_can('edit_post', (int) $postId),
        'show_in_rest'      => false,
      ]);
    }
  }

  private function add(): void
  {
    add_meta_box(
      'custom_author_box_metabox',
      __('Custom Author Box', 'custom-author-box'),
      $this->render(...),
      Plugin::postTypes(),
      'side'
    );
  }

  private function render(\WP_Post $post): void
  {
    wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

    $selected = absint(get_post_meta($post->ID, Plugin::META_KEY, true));
    $authors  = $this->authors->all();
    ?>
    <p>
      <label for="custom_author_box_author"><?php esc_html_e('Author shown in the author box:', 'custom-author-box'); ?></label>
      <select name="custom_author_box_author" id="custom_author_box_author" class="widefat">
        <option value="0"><?php esc_html_e('— None —', 'custom-author-box'); ?></option>
        <?php foreach ($authors as $author) : ?>
          <option value="<?php echo esc_attr((string) $author->id); ?>" <?php selected($selected, $author->id); ?>><?php echo esc_html($author->name); ?></option>
        <?php endforeach; ?>
      </select>
    </p>
    <?php if (current_user_can(Plugin::capability())) : ?>
      <p><a href="<?php echo esc_url(admin_url('options-general.php?page=custom-author-box-settings')); ?>"><?php esc_html_e('Manage authors', 'custom-author-box'); ?></a></p>
    <?php endif;
  }

  private function save(int|string $postId, \WP_Post $post): void
  {
    $postId = (int) $postId;

    if (!in_array($post->post_type, Plugin::postTypes(), true)
      || !isset($_POST[self::NONCE_FIELD])
      || !wp_verify_nonce(sanitize_key(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
      || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
      || wp_is_post_revision($postId)
      || !current_user_can('edit_post', $postId)
      || !isset($_POST['custom_author_box_author'])
      || !is_scalar($_POST['custom_author_box_author'])
    ) {
      return;
    }

    $authorId = absint($_POST['custom_author_box_author']);

    // Only store IDs that belong to a real author
    if ($authorId > 0 && $this->authors->find($authorId) !== null) {
      update_post_meta($postId, Plugin::META_KEY, $authorId);
    } else {
      delete_post_meta($postId, Plugin::META_KEY);
    }
  }
}
