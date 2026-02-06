<?php
/**
 * Plugin Name: WP Rephrase Intro
 * Plugin URI: https://github.com/lobsi97/amplifyapp
 * Description: Reformule automatiquement les introductions des articles de blog et pages WordPress via IA (Claude ou OpenAI).
 * Version: 1.1.0
 * Author: lobsi97
 * License: GPL-2.0+
 * Text Domain: wp-rephrase-intro
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
   1. ACTIVATION — default options
   ========================================================================= */

register_activation_hook( __FILE__, function () {
	$defaults = array(
		'wpri_api_provider' => 'openai',
		'wpri_api_key'      => '',
		'wpri_model'        => '',
		'wpri_intro_length' => 2,
		'wpri_tone'         => 'professional',
		'wpri_language'     => 'fr',
	);
	foreach ( $defaults as $k => $v ) {
		if ( false === get_option( $k ) ) {
			add_option( $k, $v );
		}
	}
} );

/* =========================================================================
   2. SETTINGS PAGE  (Réglages > Rephrase Intro)
   ========================================================================= */

add_action( 'admin_menu', function () {
	add_options_page(
		'WP Rephrase Intro',
		'Rephrase Intro',
		'manage_options',
		'wp-rephrase-intro',
		'wpri_render_settings_page'
	);
} );

add_action( 'admin_init', function () {
	$fields = array(
		'wpri_api_provider' => array( 'sanitize_callback' => 'sanitize_text_field' ),
		'wpri_api_key'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
		'wpri_model'        => array( 'sanitize_callback' => 'sanitize_text_field' ),
		'wpri_intro_length' => array( 'sanitize_callback' => 'absint' ),
		'wpri_tone'         => array( 'sanitize_callback' => 'sanitize_text_field' ),
		'wpri_language'     => array( 'sanitize_callback' => 'sanitize_text_field' ),
	);
	foreach ( $fields as $name => $args ) {
		register_setting( 'wpri_settings', $name, $args );
	}
} );

function wpri_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$provider     = get_option( 'wpri_api_provider', 'openai' );
	$api_key      = get_option( 'wpri_api_key', '' );
	$model        = get_option( 'wpri_model', '' );
	$intro_length = get_option( 'wpri_intro_length', 2 );
	$tone         = get_option( 'wpri_tone', 'professional' );
	$language     = get_option( 'wpri_language', 'fr' );

	$tones = array(
		'professional' => 'Professionnel',
		'casual'       => 'Décontracté',
		'engaging'     => 'Engageant',
		'formal'       => 'Formel',
		'friendly'     => 'Amical',
		'persuasive'   => 'Persuasif',
	);
	$langs = array(
		'fr' => 'Français', 'en' => 'Anglais', 'es' => 'Espagnol',
		'de' => 'Allemand', 'it' => 'Italien', 'pt' => 'Portugais',
	);
	?>
	<div class="wrap">
		<h1>WP Rephrase Intro</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'wpri_settings' ); ?>
			<table class="form-table">
				<tr>
					<th>Fournisseur d'API</th>
					<td>
						<select name="wpri_api_provider">
							<option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI (GPT)</option>
							<option value="anthropic" <?php selected( $provider, 'anthropic' ); ?>>Anthropic (Claude)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th>Clé API</th>
					<td>
						<input type="password" name="wpri_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text"/>
						<?php if ( empty( $api_key ) ) : ?>
							<p class="description" style="color:#d63638;font-weight:bold;">⚠ Clé API non configurée — le plugin ne pourra pas reformuler.</p>
						<?php else : ?>
							<p class="description" style="color:#00a32a;">✓ Clé API configurée.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>Modèle</th>
					<td>
						<input type="text" name="wpri_model" value="<?php echo esc_attr( $model ); ?>" class="regular-text" placeholder="Laisser vide pour le modèle par défaut"/>
						<p class="description">Ex : gpt-4o, claude-sonnet-4-5-20250929</p>
					</td>
				</tr>
				<tr>
					<th>Paragraphes d'introduction</th>
					<td><input type="number" name="wpri_intro_length" value="<?php echo esc_attr( $intro_length ); ?>" min="1" max="10" class="small-text"/></td>
				</tr>
				<tr>
					<th>Ton</th>
					<td>
						<select name="wpri_tone">
							<?php foreach ( $tones as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $tone, $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th>Langue</th>
					<td>
						<select name="wpri_language">
							<?php foreach ( $langs as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $language, $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Enregistrer' ); ?>
		</form>
	</div>
	<?php
}

/* =========================================================================
   3. REPHRASER — extract intro + call AI API
   ========================================================================= */

function wpri_extract_intro( $content ) {
	$max = (int) get_option( 'wpri_intro_length', 2 );

	// Split at first heading (h2–h6).
	if ( preg_match( '/(<h[2-6][^>]*>)/i', $content, $m, PREG_OFFSET_MATCH ) ) {
		$intro = trim( substr( $content, 0, $m[0][1] ) );
		if ( $intro !== '' ) {
			return array( 'intro' => $intro, 'rest' => substr( $content, $m[0][1] ) );
		}
	}

	// Fallback: first N <p> tags.
	if ( preg_match_all( '/<p[^>]*>.*?<\/p>/is', $content, $m ) ) {
		$count = min( $max, count( $m[0] ) );
		$intro = implode( "\n\n", array_slice( $m[0], 0, $count ) );
		$last  = $m[0][ $count - 1 ];
		$pos   = strpos( $content, $last ) + strlen( $last );
		return array( 'intro' => $intro, 'rest' => trim( substr( $content, $pos ) ) );
	}

	// Fallback: split by blank lines.
	$blocks = preg_split( '/\n\s*\n/', $content, -1, PREG_SPLIT_NO_EMPTY );
	$count  = min( $max, count( $blocks ) );
	if ( $count > 0 && count( $blocks ) > $count ) {
		return array(
			'intro' => implode( "\n\n", array_slice( $blocks, 0, $count ) ),
			'rest'  => implode( "\n\n", array_slice( $blocks, $count ) ),
		);
	}

	return array( 'intro' => $content, 'rest' => '' );
}

function wpri_call_ai( $intro, $post_title = '' ) {
	$provider = get_option( 'wpri_api_provider', 'openai' );
	$api_key  = get_option( 'wpri_api_key', '' );

	if ( empty( $api_key ) ) {
		return new WP_Error( 'no_key', 'Clé API non configurée. Allez dans Réglages → Rephrase Intro.' );
	}

	$tone     = get_option( 'wpri_tone', 'professional' );
	$language = get_option( 'wpri_language', 'fr' );
	$tones    = array( 'professional' => 'professionnel', 'casual' => 'décontracté', 'engaging' => 'engageant', 'formal' => 'formel', 'friendly' => 'amical', 'persuasive' => 'persuasif' );
	$langs    = array( 'fr' => 'français', 'en' => 'anglais', 'es' => 'espagnol', 'de' => 'allemand', 'it' => 'italien', 'pt' => 'portugais' );

	$sys = sprintf(
		"Tu es un rédacteur web expert. Reformule l'introduction en conservant le sens, les mots-clés SEO et le format HTML. Ton : %s. Langue : %s. Réponds uniquement avec le texte reformulé.",
		isset( $tones[ $tone ] ) ? $tones[ $tone ] : $tone,
		isset( $langs[ $language ] ) ? $langs[ $language ] : $language
	);

	$user = 'Reformule cette introduction';
	if ( $post_title ) {
		$user .= " de l'article « {$post_title} »";
	}
	$user .= " :\n\n{$intro}";

	if ( 'anthropic' === $provider ) {
		$model = get_option( 'wpri_model', '' );
		if ( ! $model ) {
			$model = 'claude-sonnet-4-5-20250929';
		}
		$res = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'timeout' => 60,
			'headers' => array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			),
			'body' => wp_json_encode( array(
				'model'      => $model,
				'max_tokens' => 2000,
				'system'     => $sys,
				'messages'   => array( array( 'role' => 'user', 'content' => $user ) ),
			) ),
		) );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'http', 'Erreur HTTP : ' . $res->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code ) {
			return new WP_Error( 'api', 'Erreur API Anthropic (' . $code . ') : ' . ( $body['error']['message'] ?? 'inconnue' ) );
		}
		return trim( $body['content'][0]['text'] ?? '' );
	}

	// OpenAI
	$model = get_option( 'wpri_model', '' );
	if ( ! $model ) {
		$model = 'gpt-4o';
	}
	$res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
		'timeout' => 60,
		'headers' => array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		),
		'body' => wp_json_encode( array(
			'model'       => $model,
			'messages'    => array(
				array( 'role' => 'system', 'content' => $sys ),
				array( 'role' => 'user', 'content' => $user ),
			),
			'temperature' => 0.7,
			'max_tokens'  => 2000,
		) ),
	) );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'http', 'Erreur HTTP : ' . $res->get_error_message() );
	}
	$code = wp_remote_retrieve_response_code( $res );
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( 200 !== $code ) {
		return new WP_Error( 'api', 'Erreur API OpenAI (' . $code . ') : ' . ( $body['error']['message'] ?? 'inconnue' ) );
	}
	return trim( $body['choices'][0]['message']['content'] ?? '' );
}

function wpri_rephrase_post( $post_id, $preview = false ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'no_post', 'Article introuvable.' );
	}
	if ( empty( trim( $post->post_content ) ) ) {
		return new WP_Error( 'empty', "L'article n'a pas de contenu." );
	}

	$parts     = wpri_extract_intro( $post->post_content );
	$rephrased = wpri_call_ai( $parts['intro'], $post->post_title );
	if ( is_wp_error( $rephrased ) ) {
		return $rephrased;
	}

	$result = array(
		'original_intro'  => $parts['intro'],
		'rephrased_intro' => $rephrased,
		'post_id'         => $post_id,
	);

	if ( ! $preview ) {
		$new = $rephrased . ( $parts['rest'] ? "\n\n" . $parts['rest'] : '' );
		$up  = wp_update_post( array( 'ID' => $post_id, 'post_content' => $new ), true );
		if ( is_wp_error( $up ) ) {
			return $up;
		}
		$history   = get_post_meta( $post_id, '_wpri_history', true );
		$history   = is_array( $history ) ? $history : array();
		$history[] = array( 'original' => $parts['intro'], 'rephrased' => $rephrased, 'date' => current_time( 'mysql' ) );
		update_post_meta( $post_id, '_wpri_history', $history );
		$result['saved'] = true;
	}

	return $result;
}

function wpri_restore_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'no_post', 'Article introuvable.' );
	}
	$history = get_post_meta( $post_id, '_wpri_history', true );
	if ( ! is_array( $history ) || empty( $history ) ) {
		return new WP_Error( 'no_history', 'Aucun historique.' );
	}
	$last   = array_pop( $history );
	$parts  = wpri_extract_intro( $post->post_content );
	$new    = $last['original'] . ( $parts['rest'] ? "\n\n" . $parts['rest'] : '' );
	$up     = wp_update_post( array( 'ID' => $post_id, 'post_content' => $new ), true );
	if ( is_wp_error( $up ) ) {
		return $up;
	}
	update_post_meta( $post_id, '_wpri_history', $history );
	return true;
}

/* =========================================================================
   4. AJAX HANDLERS
   ========================================================================= */

/**
 * Shared AJAX bootstrap: clean output buffer, verify nonce, get post ID.
 * Returns post ID on success, or sends JSON error and dies.
 */
function wpri_ajax_bootstrap() {
	// Catch any stray PHP output (notices, warnings) that would break JSON.
	if ( ob_get_level() ) {
		ob_clean();
	}
	ob_start();

	$nonce = isset( $_POST['_wpri_nonce'] ) ? sanitize_text_field( $_POST['_wpri_nonce'] ) : '';
	if ( ! wp_verify_nonce( $nonce, 'wpri_ajax' ) ) {
		ob_end_clean();
		wp_send_json_error( array( 'message' => 'Nonce invalide. Rechargez la page et réessayez.' ) );
	}

	$id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
		ob_end_clean();
		wp_send_json_error( array( 'message' => 'Permissions insuffisantes ou article introuvable.' ) );
	}

	return $id;
}

add_action( 'wp_ajax_wpri_preview', function () {
	$id = wpri_ajax_bootstrap();
	$r  = wpri_rephrase_post( $id, true );
	ob_end_clean();
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'message' => $r->get_error_message() ) );
	}
	wp_send_json_success( $r );
} );

add_action( 'wp_ajax_wpri_apply', function () {
	$id = wpri_ajax_bootstrap();
	$r  = wpri_rephrase_post( $id, false );
	ob_end_clean();
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'message' => $r->get_error_message() ) );
	}
	wp_send_json_success( $r );
} );

add_action( 'wp_ajax_wpri_restore', function () {
	$id = wpri_ajax_bootstrap();
	$r  = wpri_restore_post( $id );
	ob_end_clean();
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'message' => $r->get_error_message() ) );
	}
	wp_send_json_success( array( 'message' => 'Restauré.' ) );
} );

/* =========================================================================
   5. METABOX (HTML only — JS is printed via admin_footer)
   ========================================================================= */

add_action( 'add_meta_boxes', function () {
	foreach ( array( 'post', 'page' ) as $pt ) {
		add_meta_box( 'wpri_box', 'Reformuler l\'introduction', 'wpri_render_metabox', $pt, 'side', 'high' );
	}
} );

function wpri_render_metabox( $post ) {
	$api_key     = get_option( 'wpri_api_key', '' );
	$history     = get_post_meta( $post->ID, '_wpri_history', true );
	$has_history = is_array( $history ) && ! empty( $history );

	if ( empty( $api_key ) ) {
		echo '<p style="color:#d63638;"><strong>⚠ Clé API non configurée.</strong><br>';
		echo '<a href="' . esc_url( admin_url( 'options-general.php?page=wp-rephrase-intro' ) ) . '">Configurer maintenant →</a></p>';
		return;
	}

	echo '<div id="wpri-box" '
		. 'data-url="' . esc_attr( admin_url( 'admin-ajax.php' ) ) . '" '
		. 'data-nonce="' . esc_attr( wp_create_nonce( 'wpri_ajax' ) ) . '" '
		. 'data-pid="' . esc_attr( $post->ID ) . '">';
	echo '<p style="color:#666;font-style:italic;margin:0 0 10px;">Reformulez l\'intro via IA.</p>';
	echo '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">';
	echo '<button type="button" class="button button-primary" id="wpri-btn-preview">Prévisualiser</button>';
	echo '<button type="button" class="button" id="wpri-btn-apply" style="display:none;">Appliquer</button>';
	if ( $has_history ) {
		$last = end( $history );
		echo '<button type="button" class="button" id="wpri-btn-restore">Restaurer</button>';
	}
	echo '</div>';
	echo '<div id="wpri-loading" style="display:none;color:#0073aa;"><span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span><span id="wpri-loading-msg">Chargement…</span></div>';
	echo '<div id="wpri-err" style="display:none;background:#fcf0f1;border-left:4px solid #d63638;padding:8px 12px;margin:10px 0;"></div>';
	echo '<div id="wpri-result" style="display:none;">';
	echo '<h4 style="margin:8px 0 4px;font-size:11px;text-transform:uppercase;color:#888;">Actuelle :</h4>';
	echo '<div id="wpri-orig" style="background:#f9f9f9;border:1px solid #ddd;border-radius:3px;padding:8px;font-size:13px;max-height:150px;overflow:auto;margin-bottom:6px;"></div>';
	echo '<h4 style="margin:8px 0 4px;font-size:11px;text-transform:uppercase;color:#888;">Reformulée :</h4>';
	echo '<div id="wpri-new" style="background:#f0f8e8;border:1px solid #7bc142;border-radius:3px;padding:8px;font-size:13px;max-height:150px;overflow:auto;"></div>';
	echo '</div>';
	if ( $has_history ) {
		echo '<p style="margin-top:8px;color:#999;font-size:11px;">Dernière reformulation : ' . esc_html( $last['date'] ) . '</p>';
	}
	echo '</div>';
}

/* =========================================================================
   6. JAVASCRIPT — printed in admin footer (guaranteed to execute)
   ========================================================================= */

add_action( 'admin_footer', function () {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->base, array( 'post' ), true ) ) {
		return;
	}
	if ( ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}
	?>
	<script>
	(function(){
		function q(id){ return document.getElementById(id); }

		var box = q('wpri-box');
		if(!box) return;

		var ajaxUrl = box.getAttribute('data-url');
		var nonce   = box.getAttribute('data-nonce');
		var pid     = box.getAttribute('data-pid');

		var btnP = q('wpri-btn-preview');
		var btnA = q('wpri-btn-apply');
		var btnR = q('wpri-btn-restore');
		var load = q('wpri-loading');
		var lmsg = q('wpri-loading-msg');
		var errD = q('wpri-err');
		var resD = q('wpri-result');
		var orig = q('wpri-orig');
		var newD = q('wpri-new');

		function showLoad(m){ load.style.display='flex'; lmsg.textContent=m; errD.style.display='none'; }
		function hideLoad(){ load.style.display='none'; }
		function showErr(m){ errD.style.display='block'; errD.textContent=m; hideLoad(); }

		function ajax(action, cb){
			var fd = new FormData();
			fd.append('action', action);
			fd.append('_wpri_nonce', nonce);
			fd.append('post_id', pid);
			fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
				.then(function(r){ return r.json(); })
				.then(function(d){ cb(null,d); })
				.catch(function(e){ cb(e,null); });
		}

		if(btnP) btnP.addEventListener('click', function(){
			showLoad('Appel API en cours…');
			resD.style.display='none';
			if(btnA) btnA.style.display='none';

			ajax('wpri_preview', function(err, data){
				hideLoad();
				if(err){ showErr('Erreur réseau : '+err.message); return; }
				if(!data.success){ showErr(data.data && data.data.message ? data.data.message : 'Erreur inconnue'); return; }
				orig.innerHTML = data.data.original_intro;
				newD.innerHTML = data.data.rephrased_intro;
				resD.style.display='block';
				if(btnA) btnA.style.display='inline-block';
			});
		});

		if(btnA) btnA.addEventListener('click', function(){
			if(!confirm('Appliquer cette reformulation ?')) return;
			showLoad('Enregistrement…');

			ajax('wpri_apply', function(err, data){
				hideLoad();
				if(err){ showErr('Erreur réseau : '+err.message); return; }
				if(!data.success){ showErr(data.data && data.data.message ? data.data.message : 'Erreur'); return; }
				resD.style.display='none';
				btnA.style.display='none';
				errD.style.display='none';
				box.insertAdjacentHTML('afterbegin','<div style="background:#ecf7ed;border-left:4px solid #00a32a;padding:8px 12px;margin-bottom:10px;">✓ Introduction mise à jour !</div>');
				setTimeout(function(){ location.reload(); }, 1500);
			});
		});

		if(btnR) btnR.addEventListener('click', function(){
			if(!confirm('Restaurer l\'introduction précédente ?')) return;
			showLoad('Restauration…');

			ajax('wpri_restore', function(err, data){
				hideLoad();
				if(err){ showErr('Erreur réseau : '+err.message); return; }
				if(!data.success){ showErr(data.data && data.data.message ? data.data.message : 'Erreur'); return; }
				box.insertAdjacentHTML('afterbegin','<div style="background:#ecf7ed;border-left:4px solid #00a32a;padding:8px 12px;margin-bottom:10px;">✓ Introduction restaurée.</div>');
				setTimeout(function(){ location.reload(); }, 1500);
			});
		});
	})();
	</script>
	<?php
} );

/* =========================================================================
   7. BULK ACTION (liste des articles/pages)
   ========================================================================= */

add_filter( 'bulk_actions-edit-post', function ( $a ) { $a['wpri_rephrase'] = 'Reformuler l\'introduction'; return $a; } );
add_filter( 'bulk_actions-edit-page', function ( $a ) { $a['wpri_rephrase'] = 'Reformuler l\'introduction'; return $a; } );

$wpri_bulk_handler = function ( $redirect, $action, $ids ) {
	if ( 'wpri_rephrase' !== $action ) return $redirect;
	$ok = $ko = 0;
	foreach ( $ids as $id ) {
		if ( ! current_user_can( 'edit_post', $id ) ) { $ko++; continue; }
		$r = wpri_rephrase_post( $id );
		is_wp_error( $r ) ? $ko++ : $ok++;
	}
	return add_query_arg( array( 'wpri_ok' => $ok, 'wpri_ko' => $ko ), $redirect );
};
add_filter( 'handle_bulk_actions-edit-post', $wpri_bulk_handler, 10, 3 );
add_filter( 'handle_bulk_actions-edit-page', $wpri_bulk_handler, 10, 3 );

add_action( 'admin_notices', function () {
	if ( ! isset( $_GET['wpri_ok'] ) ) return;
	$ok = (int) $_GET['wpri_ok'];
	$ko = (int) ( $_GET['wpri_ko'] ?? 0 );
	if ( $ok ) echo '<div class="notice notice-success is-dismissible"><p>' . $ok . ' introduction(s) reformulée(s).</p></div>';
	if ( $ko ) echo '<div class="notice notice-error is-dismissible"><p>' . $ko . ' article(s) en erreur.</p></div>';
} );
