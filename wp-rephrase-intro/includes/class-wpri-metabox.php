<?php
/**
 * Adds a metabox to the post/page editor for rephrasing intros.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRI_Metabox {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'bulk_actions-edit-post', array( __CLASS__, 'add_bulk_action' ) );
		add_filter( 'bulk_actions-edit-page', array( __CLASS__, 'add_bulk_action' ) );
		add_filter( 'handle_bulk_actions-edit-post', array( __CLASS__, 'handle_bulk_action' ), 10, 3 );
		add_filter( 'handle_bulk_actions-edit-page', array( __CLASS__, 'handle_bulk_action' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'bulk_action_notice' ) );
	}

	public static function add_meta_box() {
		$post_types = array( 'post', 'page' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'wpri_rephrase_intro',
				__( 'Reformuler l\'introduction', 'wp-rephrase-intro' ),
				array( __CLASS__, 'render_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	public static function render_meta_box( $post ) {
		$history = get_post_meta( $post->ID, '_wpri_intro_history', true );
		$has_history = is_array( $history ) && ! empty( $history );
		?>
		<div id="wpri-metabox">
			<p class="wpri-description">
				<?php esc_html_e( 'Reformulez automatiquement l\'introduction de cet article grâce à l\'IA.', 'wp-rephrase-intro' ); ?>
			</p>

			<div id="wpri-actions">
				<button type="button" class="button button-primary wpri-btn" id="wpri-preview-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
					<?php esc_html_e( 'Prévisualiser', 'wp-rephrase-intro' ); ?>
				</button>
				<button type="button" class="button wpri-btn" id="wpri-apply-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>" style="display:none;">
					<?php esc_html_e( 'Appliquer', 'wp-rephrase-intro' ); ?>
				</button>
				<?php if ( $has_history ) : ?>
					<button type="button" class="button wpri-btn" id="wpri-restore-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
						<?php esc_html_e( 'Restaurer', 'wp-rephrase-intro' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div id="wpri-status" style="display:none;">
				<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span>
				<span id="wpri-status-text"></span>
			</div>

			<div id="wpri-preview-area" style="display:none;">
				<h4><?php esc_html_e( 'Introduction actuelle :', 'wp-rephrase-intro' ); ?></h4>
				<div id="wpri-original-intro" class="wpri-intro-box"></div>

				<h4><?php esc_html_e( 'Introduction reformulée :', 'wp-rephrase-intro' ); ?></h4>
				<div id="wpri-rephrased-intro" class="wpri-intro-box wpri-rephrased"></div>
			</div>

			<div id="wpri-error" class="notice notice-error" style="display:none;">
				<p id="wpri-error-text"></p>
			</div>

			<?php if ( $has_history ) : ?>
				<div class="wpri-history-info">
					<small>
						<?php
						$last = end( $history );
						printf(
							/* translators: %s: date of last rephrase */
							esc_html__( 'Dernière reformulation : %s', 'wp-rephrase-intro' ),
							esc_html( $last['date'] )
						);
						?>
					</small>
				</div>
			<?php endif; ?>
		</div>
		<?php
		wp_nonce_field( 'wpri_nonce_action', 'wpri_nonce' );
	}

	public static function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'wpri-admin',
			WPRI_PLUGIN_URL . 'admin/css/wpri-admin.css',
			array(),
			WPRI_VERSION
		);

		wp_enqueue_script(
			'wpri-admin',
			WPRI_PLUGIN_URL . 'admin/js/wpri-admin.js',
			array( 'jquery' ),
			WPRI_VERSION,
			true
		);

		wp_localize_script( 'wpri-admin', 'wpriData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wpri_ajax_nonce' ),
			'i18n'    => array(
				'previewing'   => __( 'Génération de la prévisualisation...', 'wp-rephrase-intro' ),
				'applying'     => __( 'Application de la reformulation...', 'wp-rephrase-intro' ),
				'restoring'    => __( 'Restauration de l\'introduction précédente...', 'wp-rephrase-intro' ),
				'success'      => __( 'Introduction reformulée avec succès !', 'wp-rephrase-intro' ),
				'restored'     => __( 'Introduction précédente restaurée.', 'wp-rephrase-intro' ),
				'error'        => __( 'Une erreur est survenue.', 'wp-rephrase-intro' ),
				'confirmApply' => __( 'Voulez-vous appliquer cette reformulation ?', 'wp-rephrase-intro' ),
			),
		) );
	}

	public static function add_bulk_action( $bulk_actions ) {
		$bulk_actions['wpri_rephrase'] = __( 'Reformuler l\'introduction', 'wp-rephrase-intro' );
		return $bulk_actions;
	}

	public static function handle_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( 'wpri_rephrase' !== $action ) {
			return $redirect_to;
		}

		$success = 0;
		$errors  = 0;

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				$errors++;
				continue;
			}

			$result = WPRI_Rephraser::rephrase_post_intro( $post_id );
			if ( is_wp_error( $result ) ) {
				$errors++;
			} else {
				$success++;
			}
		}

		$redirect_to = add_query_arg( array(
			'wpri_bulk_success' => $success,
			'wpri_bulk_errors'  => $errors,
		), $redirect_to );

		return $redirect_to;
	}

	public static function bulk_action_notice() {
		if ( ! isset( $_REQUEST['wpri_bulk_success'] ) ) {
			return;
		}

		$success = intval( $_REQUEST['wpri_bulk_success'] );
		$errors  = isset( $_REQUEST['wpri_bulk_errors'] ) ? intval( $_REQUEST['wpri_bulk_errors'] ) : 0;

		if ( $success > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				sprintf(
					/* translators: %d: number of posts rephrased */
					esc_html( _n(
						'%d introduction reformulée avec succès.',
						'%d introductions reformulées avec succès.',
						$success,
						'wp-rephrase-intro'
					) ),
					$success
				)
			);
		}

		if ( $errors > 0 ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				sprintf(
					/* translators: %d: number of errors */
					esc_html( _n(
						'%d article n\'a pas pu être reformulé.',
						'%d articles n\'ont pas pu être reformulés.',
						$errors,
						'wp-rephrase-intro'
					) ),
					$errors
				)
			);
		}
	}
}
