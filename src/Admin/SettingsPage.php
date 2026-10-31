<?php

declare(strict_types=1);

namespace BlackDigital\CustomAuthorBox\Admin;

use BlackDigital\CustomAuthorBox\Author;
use BlackDigital\CustomAuthorBox\AuthorRepository;
use BlackDigital\CustomAuthorBox\Plugin;
use BlackDigital\CustomAuthorBox\Sanitizer;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Settings → Custom Author Box.
 *
 * Writes go through admin-post.php (POST only, nonce + capability checked)
 * and redirect back afterwards, so refreshing never re-submits.
 */
final class SettingsPage
{
  private const SLUG          = 'custom-author-box-settings';
  private const SAVE_ACTION   = 'custom_author_box_save';
  private const DELETE_ACTION = 'custom_author_box_delete';

  public function __construct(private readonly AuthorRepository $authors)
  {
  }

  public function register(): void
  {
    add_action('admin_menu', $this->addMenu(...));
    add_action('admin_enqueue_scripts', $this->enqueue(...));
    add_action('admin_post_' . self::SAVE_ACTION, $this->handleSave(...));
    add_action('admin_post_' . self::DELETE_ACTION, $this->handleDelete(...));
    add_filter('plugin_action_links_' . plugin_basename(CUSTOM_AUTHOR_BOX_FILE), $this->actionLinks(...));
  }

  private function pageUrl(array $args = []): string
  {
    return add_query_arg(array_merge(['page' => self::SLUG], $args), admin_url('options-general.php'));
  }

  private function addMenu(): void
  {
    add_options_page(
      __('Custom Author Box', 'custom-author-box'),
      __('Custom Author Box', 'custom-author-box'),
      Plugin::capability(),
      self::SLUG,
      $this->render(...)
    );
  }

  /**
   * @param array<string, string> $links
   * @return array<string, string>
   */
  private function actionLinks(array $links): array
  {
    array_unshift($links, sprintf('<a href="%s">%s</a>', esc_url($this->pageUrl()), esc_html__('Authors', 'custom-author-box')));
    return $links;
  }

  private function enqueue(string $hook): void
  {
    if ($hook !== 'settings_page_' . self::SLUG) {
      return;
    }

    wp_enqueue_media();
    wp_enqueue_style('custom-author-box-admin', CUSTOM_AUTHOR_BOX_URL . 'assets/css/admin.css', [], CUSTOM_AUTHOR_BOX_VERSION);
    wp_enqueue_script('custom-author-box-admin', CUSTOM_AUTHOR_BOX_URL . 'assets/js/admin.js', [], CUSTOM_AUTHOR_BOX_VERSION, ['in_footer' => true, 'strategy' => 'defer']);
    wp_localize_script('custom-author-box-admin', 'customAuthorBox', [
      'chooseImage'   => __('Choose Author Image', 'custom-author-box'),
      'useImage'      => __('Use this image', 'custom-author-box'),
      'confirmDelete' => __('Delete this author? Posts using them will no longer show an author box.', 'custom-author-box'),
    ]);
  }

  private function authorize(string $nonceAction): void
  {
    if (!current_user_can(Plugin::capability())) {
      wp_die(esc_html__('You do not have permission to do that.', 'custom-author-box'), '', ['response' => 403]);
    }
    check_admin_referer($nonceAction);
  }

  private function handleSave(): void
  {
    $this->authorize(self::SAVE_ACTION);

    $post = wp_unslash($_POST);
    $text = static fn (string $key): string => isset($post[$key]) && is_scalar($post[$key]) ? (string) $post[$key] : '';
    $id   = absint($text('author_id'));

    if ($id > 0 && $this->authors->find($id) === null) {
      $this->redirect(['cab_notice' => 'missing']);
    }

    $name = Sanitizer::name($text('author_name'));
    $bio  = Sanitizer::bio($text('author_bio'));

    if ($name === '') {
      $this->redirect(['cab_notice' => 'name_required', 'action' => $id ? 'edit' : null, 'author_id' => $id ?: null]);
    }

    // Image: prefer a Media Library attachment, fall back to a pasted URL
    $imageId = absint($text('author_image_id'));
    if ($imageId > 0 && !wp_attachment_is_image($imageId)) {
      $imageId = 0;
    }

    $invalid = [];
    $fields  = [
      'image'           => [Sanitizer::httpUrl(...), 'author_image'],
      'user_posts_link' => [Sanitizer::httpUrl(...), 'author_posts_link'],
      'website_link'    => [Sanitizer::httpUrl(...), 'author_website'],
      'linkedin_link'   => [Sanitizer::linkedinUrl(...), 'author_linkedin'],
      'facebook_url'    => [Sanitizer::facebookUrl(...), 'author_facebook'],
      'x_handle'        => [Sanitizer::xHandle(...), 'author_x'],
    ];

    $data = ['name' => $name, 'bio' => $bio, 'image_id' => $imageId];

    foreach ($fields as $column => [$sanitize, $input]) {
      $clean = $sanitize($text($input));
      if ($clean === null) {
        $invalid[] = $column;
        $clean     = '';
      }
      $data[$column] = $clean;
    }

    if ($imageId > 0) {
      $data['image'] = (string) wp_get_attachment_image_url($imageId, 'medium');
    }

    $savedId = $this->authors->save($data, $id);

    if ($savedId === false) {
      $this->redirect(['cab_notice' => 'save_failed', 'action' => $id ? 'edit' : null, 'author_id' => $id ?: null]);
    }

    $args = ['cab_notice' => $id ? 'updated' : 'added'];
    if ($invalid) {
      // Send them back to the form so they can fix the rejected fields
      $args += ['action' => 'edit', 'author_id' => $savedId, 'cab_invalid' => implode(',', $invalid)];
    }
    $this->redirect($args);
  }

  private function handleDelete(): void
  {
    $id = isset($_POST['author_id']) && is_scalar($_POST['author_id']) ? absint($_POST['author_id']) : 0;

    // Nonce is tied to this specific author, so a form for one author can't delete another
    $this->authorize(self::DELETE_ACTION . '_' . $id);

    $this->redirect(['cab_notice' => $id > 0 && $this->authors->delete($id) ? 'deleted' : 'delete_failed']);
  }

  private function redirect(array $args): never
  {
    wp_safe_redirect($this->pageUrl(array_filter($args, static fn ($v) => $v !== null)));
    exit;
  }

  private function notice(): void
  {
    $code = isset($_GET['cab_notice']) ? sanitize_key(wp_unslash($_GET['cab_notice'])) : '';

    [$type, $message] = match ($code) {
      'added'         => ['success', __('Author added.', 'custom-author-box')],
      'updated'       => ['success', __('Author updated.', 'custom-author-box')],
      'deleted'       => ['success', __('Author deleted.', 'custom-author-box')],
      'name_required' => ['error', __('Name is required.', 'custom-author-box')],
      'save_failed'   => ['error', __('The author could not be saved. Please try again.', 'custom-author-box')],
      'delete_failed' => ['error', __('The author could not be deleted.', 'custom-author-box')],
      'missing'       => ['error', __('That author no longer exists.', 'custom-author-box')],
      default         => [null, null],
    };

    if ($type !== null) {
      printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), esc_html($message));
    }

    $labels = [
      'image'           => __('Image URL', 'custom-author-box'),
      'user_posts_link' => __('Posts link', 'custom-author-box'),
      'website_link'    => __('Website', 'custom-author-box'),
      'linkedin_link'   => __('LinkedIn URL (must be a linkedin.com link)', 'custom-author-box'),
      'facebook_url'    => __('Facebook URL (must be a facebook.com link)', 'custom-author-box'),
      'x_handle'        => __('X handle (letters, numbers and _ only, up to 15 characters)', 'custom-author-box'),
    ];

    $raw     = isset($_GET['cab_invalid']) ? sanitize_text_field(wp_unslash($_GET['cab_invalid'])) : '';
    $invalid = array_intersect_key($labels, array_flip(explode(',', $raw)));

    if ($invalid) {
      echo '<div class="notice notice-warning"><p>'
        . esc_html__('These fields were not valid and were left empty:', 'custom-author-box')
        . '</p><ul class="cab-invalid-list">';
      foreach ($invalid as $label) {
        echo '<li>' . esc_html($label) . '</li>';
      }
      echo '</ul></div>';
    }
  }

  private function render(): void
  {
    if (!current_user_can(Plugin::capability())) {
      wp_die(esc_html__('You do not have permission to access this page.', 'custom-author-box'), '', ['response' => 403]);
    }

    $editing = null;
    if (isset($_GET['action'], $_GET['author_id']) && $_GET['action'] === 'edit' && is_scalar($_GET['author_id'])) {
      $editing = $this->authors->find(absint($_GET['author_id']));
    }
    $author  = $editing ?? new Author(0, '');
    $authors = $this->authors->all();
    ?>
    <div class="wrap cab-admin">
      <h1><?php esc_html_e('Custom Author Box', 'custom-author-box'); ?></h1>
      <?php $this->notice(); ?>

      <div class="cab-admin__columns">
        <div class="cab-admin__list">
          <h2><?php esc_html_e('Authors', 'custom-author-box'); ?></h2>
          <?php if ($authors) : ?>
            <table class="widefat striped">
              <thead>
                <tr>
                  <th scope="col"><?php esc_html_e('Author', 'custom-author-box'); ?></th>
                  <th scope="col"><?php esc_html_e('Social', 'custom-author-box'); ?></th>
                  <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Actions', 'custom-author-box'); ?></span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($authors as $item) : ?>
                  <tr>
                    <td class="cab-admin__author">
                      <?php echo $item->imageHtml('thumbnail', ['class' => 'cab-admin__thumb']); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in imageHtml() ?>
                      <strong><?php echo esc_html($item->name); ?></strong>
                    </td>
                    <td><?php echo esc_html(implode(', ', array_map('ucfirst', array_keys($item->socialLinks()))) ?: '—'); ?></td>
                    <td class="cab-admin__actions">
                      <a class="button button-small" href="<?php echo esc_url($this->pageUrl(['action' => 'edit', 'author_id' => $item->id])); ?>"><?php esc_html_e('Edit', 'custom-author-box'); ?></a>
                      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cab-delete-form">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                        <input type="hidden" name="author_id" value="<?php echo esc_attr((string) $item->id); ?>">
                        <?php wp_nonce_field(self::DELETE_ACTION . '_' . $item->id); ?>
                        <button type="submit" class="button button-small button-link-delete"><?php esc_html_e('Delete', 'custom-author-box'); ?></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else : ?>
            <p><?php esc_html_e('No authors yet. Add your first one using the form.', 'custom-author-box'); ?></p>
          <?php endif; ?>
        </div>

        <div class="cab-admin__form">
          <h2><?php echo $editing ? esc_html__('Edit Author', 'custom-author-box') : esc_html__('Add New Author', 'custom-author-box'); ?></h2>

          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
            <input type="hidden" name="author_id" value="<?php echo esc_attr((string) $author->id); ?>">
            <?php wp_nonce_field(self::SAVE_ACTION); ?>

            <h3><?php esc_html_e('Author', 'custom-author-box'); ?></h3>

            <label for="cab-name"><?php esc_html_e('Name', 'custom-author-box'); ?></label>
            <input type="text" id="cab-name" name="author_name" value="<?php echo esc_attr($author->name); ?>" maxlength="<?php echo esc_attr((string) Sanitizer::NAME_MAX); ?>" required>

            <label for="cab-image"><?php esc_html_e('Image', 'custom-author-box'); ?></label>
            <div class="cab-admin__image">
              <?php $src = $author->imageSrc('thumbnail'); ?>
              <img id="cab-image-preview" src="<?php echo esc_url($src); ?>" alt="" <?php echo $src === '' ? 'hidden' : ''; ?>>
              <input type="hidden" id="cab-image-id" name="author_image_id" value="<?php echo esc_attr((string) $author->imageId); ?>">
              <input type="url" id="cab-image" name="author_image" value="<?php echo esc_attr($author->imageId ? '' : $author->imageUrl); ?>" placeholder="<?php echo esc_attr($author->imageId ? __('Using Media Library image', 'custom-author-box') : 'https://'); ?>">
              <button type="button" class="button" id="cab-image-select"><?php esc_html_e('Choose', 'custom-author-box'); ?></button>
              <button type="button" class="button-link" id="cab-image-remove" <?php echo $src === '' ? 'hidden' : ''; ?>><?php esc_html_e('Remove', 'custom-author-box'); ?></button>
            </div>

            <label for="cab-bio"><?php esc_html_e('Short Bio', 'custom-author-box'); ?></label>
            <textarea id="cab-bio" name="author_bio" rows="4" maxlength="<?php echo esc_attr((string) Sanitizer::BIO_MAX); ?>"><?php echo esc_textarea($author->bio); ?></textarea>

            <label for="cab-posts"><?php esc_html_e('Posts Link', 'custom-author-box'); ?> <span class="cab-admin__hint"><?php esc_html_e('(shows the "More From" link)', 'custom-author-box'); ?></span></label>
            <input type="url" id="cab-posts" name="author_posts_link" value="<?php echo esc_attr($author->postsUrl); ?>" placeholder="https://">

            <h3><?php esc_html_e('Social Profiles', 'custom-author-box'); ?></h3>
            <p class="cab-admin__hint"><?php esc_html_e('Optional. Used for social share tags, search-engine author data, and the icons in the author box.', 'custom-author-box'); ?></p>

            <label for="cab-x"><?php esc_html_e('X (Twitter) Handle', 'custom-author-box'); ?></label>
            <div class="cab-admin__prefixed"><span>@</span><input type="text" id="cab-x" name="author_x" value="<?php echo esc_attr($author->xHandle); ?>" maxlength="60" autocomplete="off" spellcheck="false"></div>

            <label for="cab-facebook"><?php esc_html_e('Facebook Profile URL', 'custom-author-box'); ?></label>
            <input type="url" id="cab-facebook" name="author_facebook" value="<?php echo esc_attr($author->facebookUrl); ?>" placeholder="https://www.facebook.com/…">

            <label for="cab-linkedin"><?php esc_html_e('LinkedIn Profile URL', 'custom-author-box'); ?></label>
            <input type="url" id="cab-linkedin" name="author_linkedin" value="<?php echo esc_attr($author->linkedinUrl); ?>" placeholder="https://www.linkedin.com/in/…">

            <label for="cab-website"><?php esc_html_e('Website', 'custom-author-box'); ?></label>
            <input type="url" id="cab-website" name="author_website" value="<?php echo esc_attr($author->websiteUrl); ?>" placeholder="https://">

            <p class="submit">
              <button type="submit" class="button button-primary"><?php echo $editing ? esc_html__('Update Author', 'custom-author-box') : esc_html__('Add Author', 'custom-author-box'); ?></button>
              <?php if ($editing) : ?>
                <a class="button" href="<?php echo esc_url($this->pageUrl()); ?>"><?php esc_html_e('Cancel', 'custom-author-box'); ?></a>
              <?php endif; ?>
            </p>
          </form>
        </div>
      </div>
    </div>
    <?php
  }
}
