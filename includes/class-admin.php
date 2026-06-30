<?php
/**
 * Admin dashboard.
 *
 * @package Greek_URL_Guard
 */

namespace Greek_URL_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the core settings UI.
 */
final class Admin {
	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Slug generator.
	 *
	 * @var Slug_Generator
	 */
	private $slug_generator;

	/**
	 * Health check.
	 *
	 * @var Health_Check
	 */
	private $health_check;

	/**
	 * Whether settings were saved on this request.
	 *
	 * @var bool
	 */
	private $settings_saved = false;

	/**
	 * Constructor.
	 *
	 * @param Settings       $settings       Settings.
	 * @param Slug_Generator $slug_generator Slug generator.
	 * @param Health_Check   $health_check   Health check.
	 */
	public function __construct( Settings $settings, Slug_Generator $slug_generator, Health_Check $health_check ) {
		$this->settings       = $settings;
		$this->slug_generator = $slug_generator;
		$this->health_check   = $health_check;
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_greek_url_guard_preview_slug', array( $this, 'ajax_preview_slug' ) );
		add_action( 'load-settings_page_greek-url-guard', array( $this, 'capture_settings_saved_notice' ) );
		add_filter( 'plugin_action_links_' . GREEK_URL_GUARD_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Captures the default Settings API success flag so the notice can render in-place.
	 *
	 * @return void
	 */
	public function capture_settings_saved_notice() {
		$settings_updated = filter_input( INPUT_GET, 'settings-updated', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		if ( true !== $settings_updated ) {
			return;
		}

		$this->settings_saved = true;
		unset( $_GET['settings-updated'], $_REQUEST['settings-updated'] );
	}

	/**
	 * Adds settings page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_options_page(
			__( 'Greek URL Guard', 'greek-url-guard' ),
			__( 'Greek URL Guard', 'greek-url-guard' ),
			'manage_options',
			'greek-url-guard',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Adds settings link on the plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function plugin_action_links( $links ) {
		$settings_url = admin_url( 'options-general.php?page=greek-url-guard' );
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $settings_url ),
				esc_html__( 'Settings', 'greek-url-guard' )
			)
		);

		return $links;
	}

	/**
	 * Enqueues assets only for this settings page.
	 *
	 * @param string $hook Admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_greek-url-guard' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'greek-url-guard-admin',
			GREEK_URL_GUARD_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			GREEK_URL_GUARD_VERSION,
			true
		);

		wp_enqueue_style(
			'greek-url-guard-admin',
			GREEK_URL_GUARD_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			GREEK_URL_GUARD_VERSION
		);

		wp_add_inline_script(
			'greek-url-guard-admin',
			'window.GreekURLGuardAdmin = ' . wp_json_encode(
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'previewNonce' => wp_create_nonce( 'greek_url_guard_preview_ajax' ),
					'previewError' => __( 'Preview request could not be completed.', 'greek-url-guard' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Returns an exact server-side slug preview.
	 *
	 * @return void
	 */
	public function ajax_preview_slug() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to preview slugs.', 'greek-url-guard' ),
				),
				403
			);
		}

		if ( ! check_ajax_referer( 'greek_url_guard_preview_ajax', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Preview request could not be verified.', 'greek-url-guard' ),
				),
				403
			);
		}

		$text = isset( $_POST['text'] ) ? sanitize_text_field( wp_unslash( $_POST['text'] ) ) : '';
		$slug = $this->slug_generator->make_slug( $text, (int) $this->settings->get( 'max_slug_length' ) );

		wp_send_json_success(
			array(
				'slug' => $slug,
			)
		);
	}

	/**
	 * Renders admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings              = $this->settings->all();
		$preview_text          = '';
		$preview_slug          = '';
		$preview_error         = '';
		$woocommerce_active    = class_exists( '\WooCommerce' );
		$slug_example_text     = $this->text_from_json( '"\u039f\u03b4\u03b7\u03b3\u03cc\u03c2 SEO 2026"' );
		$slug_example_slug     = $this->slug_generator->make_slug( $slug_example_text, (int) $settings['max_slug_length'] );
		$filename_example_text = $this->text_from_json( '"\u03a6\u03c9\u03c4\u03bf\u03b3\u03c1\u03b1\u03c6\u03af\u03b1 \u03c0\u03c1\u03bf\u03ca\u03cc\u03bd\u03c4\u03bf\u03c2_175.JPG"' );
		$filename_example_name = $this->slug_generator->make_filename( $filename_example_text, $filename_example_text, (int) $settings['max_filename_length'] );

		if ( isset( $_POST['greek_url_guard_preview_nonce'] ) ) {
			if ( check_admin_referer( 'greek_url_guard_preview', 'greek_url_guard_preview_nonce' ) ) {
				$preview_text = isset( $_POST['greek_url_guard_preview_text'] ) ? sanitize_text_field( wp_unslash( $_POST['greek_url_guard_preview_text'] ) ) : '';
				$preview_slug = $this->slug_generator->make_slug( $preview_text, (int) $settings['max_slug_length'] );
			} else {
				$preview_error = __( 'Preview request could not be verified.', 'greek-url-guard' );
			}
		}

		?>
		<div class="wrap greek-url-guard-wrap">
			<h1><?php echo esc_html__( 'Greek URL Guard', 'greek-url-guard' ); ?></h1>
			<?php if ( $this->settings_saved ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__( 'Settings saved.', 'greek-url-guard' ); ?></p>
				</div>
			<?php endif; ?>
			<p><?php echo esc_html__( 'Create clean Greeklish slugs and upload filenames for new WordPress content without rewriting existing URLs.', 'greek-url-guard' ); ?></p>

			<h2><?php echo esc_html__( 'Core Settings', 'greek-url-guard' ); ?></h2>
			<form class="greek-url-guard-settings-form" method="post" action="options.php">
				<?php settings_fields( Settings::SETTING_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Status', 'greek-url-guard' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
								<?php echo esc_html__( 'Enable automatic cleanup', 'greek-url-guard' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Coverage', 'greek-url-guard' ); ?></th>
						<td>
							<div class="greek-url-guard-option-list">
								<label>
									<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[enable_posts_pages]" value="1" <?php checked( ! empty( $settings['enable_posts_pages'] ) ); ?> />
									<span><?php echo esc_html__( 'Posts and pages', 'greek-url-guard' ); ?></span>
								</label>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[post_types][]" value="post" <?php checked( in_array( 'post', (array) $settings['post_types'], true ) ); ?> />
									<span><?php echo esc_html__( 'Posts', 'greek-url-guard' ); ?></span>
								</label>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[post_types][]" value="page" <?php checked( in_array( 'page', (array) $settings['post_types'], true ) ); ?> />
									<span><?php echo esc_html__( 'Pages', 'greek-url-guard' ); ?></span>
								</label>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[enable_public_cpts]" value="1" <?php checked( ! empty( $settings['enable_public_cpts'] ) ); ?> />
									<span><?php echo esc_html__( 'Custom post types', 'greek-url-guard' ); ?></span>
								</label>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[enable_taxonomies]" value="1" <?php checked( ! empty( $settings['enable_taxonomies'] ) ); ?> />
									<span><?php echo esc_html__( 'Categories, tags, and taxonomies', 'greek-url-guard' ); ?></span>
								</label>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[enable_media]" value="1" <?php checked( ! empty( $settings['enable_media'] ) ); ?> />
									<span><?php echo esc_html__( 'Media file names', 'greek-url-guard' ); ?></span>
								</label>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[enable_woocommerce]" value="1" <?php checked( ! empty( $settings['enable_woocommerce'] ) ); ?> <?php disabled( ! $woocommerce_active ); ?> />
									<span><?php echo esc_html__( 'WooCommerce products', 'greek-url-guard' ); ?></span>
								</label>
							</div>
							<p class="description"><?php echo esc_html__( 'Old URLs and existing uploaded files are not changed.', 'greek-url-guard' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="greek-url-guard-max-slug-length"><?php echo esc_html__( 'Maximum slug length', 'greek-url-guard' ); ?></label>
						</th>
						<td>
							<input id="greek-url-guard-max-slug-length" type="number" min="20" max="150" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[max_slug_length]" value="<?php echo esc_attr( (string) $settings['max_slug_length'] ); ?>" class="small-text" />
							<p class="description"><?php echo esc_html__( 'Default: 70 characters. The limit is applied without cutting words when possible.', 'greek-url-guard' ); ?></p>
							<p class="description greek-url-guard-example"><?php echo esc_html__( 'Example:', 'greek-url-guard' ); ?> <code><?php echo esc_html( $slug_example_text ); ?></code> &rarr; <code><?php echo esc_html( $slug_example_slug ); ?></code></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="greek-url-guard-max-filename-length"><?php echo esc_html__( 'Maximum filename basename length', 'greek-url-guard' ); ?></label>
						</th>
						<td>
							<input id="greek-url-guard-max-filename-length" type="number" min="20" max="150" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[max_filename_length]" value="<?php echo esc_attr( (string) $settings['max_filename_length'] ); ?>" class="small-text" />
							<p class="description"><?php echo esc_html__( 'The extension is preserved separately. Underscores, spaces, Greek characters, symbols, and repeated dashes are cleaned in new upload filenames.', 'greek-url-guard' ); ?></p>
							<p class="description greek-url-guard-example"><?php echo esc_html__( 'Example:', 'greek-url-guard' ); ?> <code><?php echo esc_html( $filename_example_text ); ?></code> &rarr; <code><?php echo esc_html( $filename_example_name ); ?></code></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Manual slugs', 'greek-url-guard' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[preserve_manual_slugs]" value="1" <?php checked( ! empty( $settings['preserve_manual_slugs'] ) ); ?> />
								<?php echo esc_html__( 'Preserve manually entered slugs', 'greek-url-guard' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Changing the title of published content does not rewrite its existing URL. This option only controls slugs typed directly in the slug field.', 'greek-url-guard' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Uninstall behavior', 'greek-url-guard' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[remove_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['remove_data_on_uninstall'] ) ); ?> />
								<?php echo esc_html__( 'Remove plugin settings on uninstall', 'greek-url-guard' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Content slugs are never reverted or deleted on uninstall.', 'greek-url-guard' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />

			<h2 id="greek-url-guard-preview"><?php echo esc_html__( 'Slug Preview', 'greek-url-guard' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=greek-url-guard#greek-url-guard-preview' ) ); ?>" data-greek-url-guard-preview-form>
				<?php wp_nonce_field( 'greek_url_guard_preview', 'greek_url_guard_preview_nonce' ); ?>
				<p>
					<label for="greek-url-guard-preview-text" class="screen-reader-text"><?php echo esc_html__( 'Preview text', 'greek-url-guard' ); ?></label>
					<input id="greek-url-guard-preview-text" type="text" class="regular-text" name="greek_url_guard_preview_text" value="<?php echo esc_attr( $preview_text ); ?>" placeholder="<?php echo esc_attr__( 'Article title in Greek', 'greek-url-guard' ); ?>" />
					<?php submit_button( __( 'Preview', 'greek-url-guard' ), 'secondary', 'submit', false ); ?>
				</p>
				<?php if ( '' !== $preview_error ) : ?>
					<p class="notice notice-error inline"><span><?php echo esc_html( $preview_error ); ?></span></p>
				<?php endif; ?>
					<p data-greek-url-guard-preview-output <?php echo '' === $preview_text ? 'hidden' : ''; ?>>
						<strong><?php echo esc_html__( 'Result:', 'greek-url-guard' ); ?></strong>
						<code data-greek-url-guard-preview-result><?php echo esc_html( $preview_slug ); ?></code>
					</p>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'SEO Safety Check', 'greek-url-guard' ); ?></h2>
			<table class="widefat striped" role="presentation">
				<tbody>
					<?php foreach ( $this->health_check->checks() as $check ) : ?>
						<tr class="greek-url-guard-health-row greek-url-guard-health-row-<?php echo esc_attr( $check['status'] ); ?>">
							<th scope="row"><span class="greek-url-guard-status-dot" aria-hidden="true"></span><?php echo esc_html( $check['label'] ); ?></th>
							<td>
								<strong><?php echo esc_html( $this->status_label( $check['status'] ) ); ?></strong>
								<br />
								<?php echo esc_html( $check['description'] ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

		</div>
		<?php
	}

	/**
	 * Returns localized health status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function status_label( $status ) {
		$labels = array(
			'good'    => __( 'Good', 'greek-url-guard' ),
			'warning' => __( 'Warning', 'greek-url-guard' ),
			'info'    => __( 'Info', 'greek-url-guard' ),
			'neutral' => __( 'Neutral', 'greek-url-guard' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
	}

	/**
	 * Decodes fixed UTF-8 example text without depending on source-file encoding.
	 *
	 * @param string $json JSON string.
	 * @return string
	 */
	private function text_from_json( $json ) {
		$text = json_decode( $json );

		return is_string( $text ) ? $text : '';
	}
}
