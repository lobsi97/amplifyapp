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
		$history     = get_post_meta( $post->ID, '_wpri_intro_history', true );
		$has_history = is_array( $history ) && ! empty( $history );
		$ajax_url    = admin_url( 'admin-ajax.php' );
		$nonce       = wp_create_nonce( 'wpri_ajax_nonce' );
		?>
		<div id="wpri-metabox"
			data-ajax-url="<?php echo esc_attr( $ajax_url ); ?>"
			data-nonce="<?php echo esc_attr( $nonce ); ?>"
			data-post-id="<?php echo esc_attr( $post->ID ); ?>">

			<p class="wpri-description">
				<?php esc_html_e( 'Reformulez automatiquement l\'introduction de cet article grâce à l\'IA.', 'wp-rephrase-intro' ); ?>
			</p>

			<div id="wpri-actions">
				<button type="button" class="button button-primary" id="wpri-preview-btn">
					<?php esc_html_e( 'Prévisualiser', 'wp-rephrase-intro' ); ?>
				</button>
				<button type="button" class="button" id="wpri-apply-btn" style="display:none;">
					<?php esc_html_e( 'Appliquer', 'wp-rephrase-intro' ); ?>
				</button>
				<?php if ( $has_history ) : ?>
					<button type="button" class="button" id="wpri-restore-btn">
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

		<script>
		(function() {
			var box      = document.getElementById('wpri-metabox');
			var ajaxUrl  = box.getAttribute('data-ajax-url');
			var nonce    = box.getAttribute('data-nonce');
			var postId   = box.getAttribute('data-post-id');

			var previewBtn = document.getElementById('wpri-preview-btn');
			var applyBtn   = document.getElementById('wpri-apply-btn');
			var restoreBtn = document.getElementById('wpri-restore-btn');
			var statusDiv  = document.getElementById('wpri-status');
			var statusText = document.getElementById('wpri-status-text');
			var previewDiv = document.getElementById('wpri-preview-area');
			var origDiv    = document.getElementById('wpri-original-intro');
			var rephDiv    = document.getElementById('wpri-rephrased-intro');
			var errorDiv   = document.getElementById('wpri-error');
			var errorText  = document.getElementById('wpri-error-text');

			function showStatus(msg) {
				statusDiv.style.display = 'flex';
				statusText.textContent = msg;
				errorDiv.style.display = 'none';
			}

			function hideStatus() {
				statusDiv.style.display = 'none';
			}

			function showError(msg) {
				errorDiv.style.display = 'block';
				errorText.textContent = msg;
				hideStatus();
			}

			function doAjax(action, callback) {
				var body = new FormData();
				body.append('action', action);
				body.append('nonce', nonce);
				body.append('post_id', postId);

				fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
					.then(function(r) { return r.json(); })
					.then(function(data) { callback(null, data); })
					.catch(function(err) { callback(err, null); });
			}

			if (previewBtn) {
				previewBtn.addEventListener('click', function(e) {
					e.preventDefault();
					showStatus('Génération de la prévisualisation…');
					previewDiv.style.display = 'none';
					applyBtn.style.display   = 'none';

					doAjax('wpri_preview', function(err, data) {
						hideStatus();
						if (err || !data || !data.success) {
							showError((data && data.data && data.data.message) || 'Une erreur est survenue.');
							return;
						}
						origDiv.innerHTML = data.data.original_intro;
						rephDiv.innerHTML = data.data.rephrased_intro;
						previewDiv.style.display = 'block';
						applyBtn.style.display   = 'inline-block';
					});
				});
			}

			if (applyBtn) {
				applyBtn.addEventListener('click', function(e) {
					e.preventDefault();
					if (!confirm('Voulez-vous appliquer cette reformulation ?')) return;

					showStatus('Application de la reformulation…');

					doAjax('wpri_apply', function(err, data) {
						hideStatus();
						if (err || !data || !data.success) {
							showError((data && data.data && data.data.message) || 'Une erreur est survenue.');
							return;
						}
						previewDiv.style.display = 'none';
						applyBtn.style.display   = 'none';
						box.insertAdjacentHTML('afterbegin',
							'<div class="notice notice-success" style="margin:10px 0;padding:8px 12px;">' +
							'<p>Introduction reformulée avec succès !</p></div>');
						setTimeout(function() { location.reload(); }, 1500);
					});
				});
			}

			if (restoreBtn) {
				restoreBtn.addEventListener('click', function(e) {
					e.preventDefault();
					showStatus('Restauration de l\'introduction précédente…');

					doAjax('wpri_restore', function(err, data) {
						hideStatus();
						if (err || !data || !data.success) {
							showError((data && data.data && data.data.message) || 'Une erreur est survenue.');
							return;
						}
						box.insertAdjacentHTML('afterbegin',
							'<div class="notice notice-success" style="margin:10px 0;padding:8px 12px;">' +
							'<p>Introduction précédente restaurée.</p></div>');
						setTimeout(function() { location.reload(); }, 1500);
					});
				});
			}
		})();
		</script>

		<style>
			#wpri-metabox .wpri-description { color: #666; font-style: italic; margin-bottom: 12px; }
			#wpri-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
			#wpri-status { display: flex; align-items: center; padding: 8px 0; color: #0073aa; font-weight: 500; }
			#wpri-preview-area { margin-top: 12px; border-top: 1px solid #ddd; padding-top: 12px; }
			#wpri-preview-area h4 { margin: 8px 0 4px; font-size: 12px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; }
			.wpri-intro-box { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 4px; padding: 10px; font-size: 13px; line-height: 1.5; max-height: 200px; overflow-y: auto; margin-bottom: 8px; }
			.wpri-intro-box p { margin: 0 0 8px; }
			.wpri-intro-box p:last-child { margin-bottom: 0; }
			.wpri-rephrased { background: #f0f8e8; border-color: #7bc142; }
			#wpri-error { margin: 10px 0; padding: 8px 12px; }
			.wpri-history-info { margin-top: 10px; padding-top: 8px; border-top: 1px solid #eee; color: #999; }
		</style>
		<?php
	}

	public static function enqueue_assets( $hook ) {
		// Keep external assets as optional enhancement — the inline
		// script/style in render_meta_box is the primary source.
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
