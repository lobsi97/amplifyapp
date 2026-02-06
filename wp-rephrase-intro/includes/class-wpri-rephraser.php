<?php
/**
 * Handles the actual rephrasing logic via AI APIs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRI_Rephraser {

	/**
	 * Extract the introduction from post content.
	 *
	 * The intro is defined as the content before the first heading tag (h2-h6),
	 * or the first N paragraphs if no heading is found.
	 *
	 * @param string $content The full post content.
	 * @return array{intro: string, rest: string} The intro and remaining content.
	 */
	public static function extract_intro( $content ) {
		$intro_length = (int) get_option( 'wpri_intro_length', 2 );

		// Try splitting at the first heading (h2-h6).
		if ( preg_match( '/(<h[2-6][^>]*>)/i', $content, $matches, PREG_OFFSET_MATCH ) ) {
			$pos   = $matches[0][1];
			$intro = trim( substr( $content, 0, $pos ) );
			$rest  = substr( $content, $pos );

			if ( ! empty( $intro ) ) {
				return array(
					'intro' => $intro,
					'rest'  => $rest,
				);
			}
		}

		// Fallback: take the first N paragraphs.
		// Handle both HTML paragraphs and double-newline separated blocks.
		if ( preg_match_all( '/<p[^>]*>.*?<\/p>/is', $content, $matches ) ) {
			$paragraphs = $matches[0];
			$count      = min( $intro_length, count( $paragraphs ) );

			if ( $count > 0 ) {
				$intro_parts = array_slice( $paragraphs, 0, $count );
				$intro       = implode( "\n\n", $intro_parts );

				// Find the position after the last intro paragraph.
				$last_para = $intro_parts[ $count - 1 ];
				$last_pos  = strpos( $content, $last_para ) + strlen( $last_para );
				$rest      = trim( substr( $content, $last_pos ) );

				return array(
					'intro' => $intro,
					'rest'  => $rest,
				);
			}
		}

		// If no paragraphs found, split by double newlines.
		$blocks = preg_split( '/\n\s*\n/', $content, -1, PREG_SPLIT_NO_EMPTY );
		$count  = min( $intro_length, count( $blocks ) );

		if ( $count > 0 && count( $blocks ) > $count ) {
			$intro = implode( "\n\n", array_slice( $blocks, 0, $count ) );
			$rest  = implode( "\n\n", array_slice( $blocks, $count ) );

			return array(
				'intro' => trim( $intro ),
				'rest'  => trim( $rest ),
			);
		}

		// Content is too short to split — treat it all as intro.
		return array(
			'intro' => $content,
			'rest'  => '',
		);
	}

	/**
	 * Rephrase the given intro text using the configured AI API.
	 *
	 * @param string $intro      The introduction text to rephrase.
	 * @param string $post_title The post title for context.
	 * @return string|WP_Error The rephrased intro or error.
	 */
	public static function rephrase( $intro, $post_title = '' ) {
		$provider = get_option( 'wpri_api_provider', 'openai' );
		$api_key  = get_option( 'wpri_api_key', '' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'Clé API non configurée. Allez dans Réglages > Rephrase Intro.', 'wp-rephrase-intro' ) );
		}

		$tone     = get_option( 'wpri_tone', 'professional' );
		$language = get_option( 'wpri_language', 'fr' );

		$tone_labels = array(
			'professional' => 'professionnel',
			'casual'       => 'décontracté',
			'engaging'     => 'engageant',
			'formal'       => 'formel',
			'friendly'     => 'amical',
			'persuasive'   => 'persuasif',
		);

		$lang_labels = array(
			'fr' => 'français',
			'en' => 'anglais',
			'es' => 'espagnol',
			'de' => 'allemand',
			'it' => 'italien',
			'pt' => 'portugais',
		);

		$tone_label = isset( $tone_labels[ $tone ] ) ? $tone_labels[ $tone ] : $tone;
		$lang_label = isset( $lang_labels[ $language ] ) ? $lang_labels[ $language ] : $language;

		$system_prompt = "Tu es un rédacteur web expert. Tu reformules les introductions d'articles de blog en conservant le sens original, les mots-clés SEO importants et le format HTML existant. Tu écris dans un ton {$tone_label}. Tu réponds uniquement avec le texte reformulé, sans explications ni commentaires. Tu écris en {$lang_label}.";

		$user_prompt = "Reformule l'introduction suivante d'un article de blog";
		if ( ! empty( $post_title ) ) {
			$user_prompt .= " intitulé \"{$post_title}\"";
		}
		$user_prompt .= ". Conserve le format HTML, la longueur approximative et les mots-clés SEO :\n\n{$intro}";

		if ( 'anthropic' === $provider ) {
			return self::call_anthropic( $api_key, $system_prompt, $user_prompt );
		}

		return self::call_openai( $api_key, $system_prompt, $user_prompt );
	}

	/**
	 * Call the OpenAI API.
	 */
	private static function call_openai( $api_key, $system_prompt, $user_prompt ) {
		$model = get_option( 'wpri_model', '' );
		if ( empty( $model ) ) {
			$model = 'gpt-4o';
		}

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'timeout' => 60,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( array(
				'model'    => $model,
				'messages' => array(
					array( 'role' => 'system', 'content' => $system_prompt ),
					array( 'role' => 'user', 'content' => $user_prompt ),
				),
				'temperature' => 0.7,
				'max_tokens'  => 2000,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Erreur inconnue de l\'API OpenAI.', 'wp-rephrase-intro' );
			return new WP_Error( 'openai_error', $message );
		}

		if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'openai_empty', __( 'Réponse vide de l\'API OpenAI.', 'wp-rephrase-intro' ) );
		}

		return trim( $body['choices'][0]['message']['content'] );
	}

	/**
	 * Call the Anthropic (Claude) API.
	 */
	private static function call_anthropic( $api_key, $system_prompt, $user_prompt ) {
		$model = get_option( 'wpri_model', '' );
		if ( empty( $model ) ) {
			$model = 'claude-sonnet-4-5-20250929';
		}

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'timeout' => 60,
			'headers' => array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			),
			'body'    => wp_json_encode( array(
				'model'      => $model,
				'max_tokens' => 2000,
				'system'     => $system_prompt,
				'messages'   => array(
					array( 'role' => 'user', 'content' => $user_prompt ),
				),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Erreur inconnue de l\'API Anthropic.', 'wp-rephrase-intro' );
			return new WP_Error( 'anthropic_error', $message );
		}

		if ( ! isset( $body['content'][0]['text'] ) ) {
			return new WP_Error( 'anthropic_empty', __( 'Réponse vide de l\'API Anthropic.', 'wp-rephrase-intro' ) );
		}

		return trim( $body['content'][0]['text'] );
	}

	/**
	 * Rephrase the intro of a post and update it.
	 *
	 * @param int  $post_id  The post ID.
	 * @param bool $preview  If true, return the result without saving.
	 * @return array|WP_Error Result with original and rephrased intro, or error.
	 */
	public static function rephrase_post_intro( $post_id, $preview = false ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'no_post', __( 'Article introuvable.', 'wp-rephrase-intro' ) );
		}

		$content = $post->post_content;
		if ( empty( trim( $content ) ) ) {
			return new WP_Error( 'empty_content', __( 'L\'article n\'a pas de contenu.', 'wp-rephrase-intro' ) );
		}

		$parts = self::extract_intro( $content );
		$intro = $parts['intro'];
		$rest  = $parts['rest'];

		$rephrased = self::rephrase( $intro, $post->post_title );
		if ( is_wp_error( $rephrased ) ) {
			return $rephrased;
		}

		$result = array(
			'original_intro'  => $intro,
			'rephrased_intro' => $rephrased,
			'post_id'         => $post_id,
		);

		if ( ! $preview ) {
			$new_content = $rephrased;
			if ( ! empty( $rest ) ) {
				$new_content .= "\n\n" . $rest;
			}

			$update = wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => $new_content,
			), true );

			if ( is_wp_error( $update ) ) {
				return $update;
			}

			// Store the original intro as post meta for potential rollback.
			$history = get_post_meta( $post_id, '_wpri_intro_history', true );
			if ( ! is_array( $history ) ) {
				$history = array();
			}
			$history[] = array(
				'original'  => $intro,
				'rephrased' => $rephrased,
				'date'      => current_time( 'mysql' ),
			);
			update_post_meta( $post_id, '_wpri_intro_history', $history );

			$result['saved'] = true;
		}

		return $result;
	}

	/**
	 * Restore the previous intro of a post.
	 *
	 * @param int $post_id The post ID.
	 * @return true|WP_Error
	 */
	public static function restore_previous_intro( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'no_post', __( 'Article introuvable.', 'wp-rephrase-intro' ) );
		}

		$history = get_post_meta( $post_id, '_wpri_intro_history', true );
		if ( ! is_array( $history ) || empty( $history ) ) {
			return new WP_Error( 'no_history', __( 'Aucun historique de reformulation trouvé.', 'wp-rephrase-intro' ) );
		}

		$last_entry = array_pop( $history );
		$content    = $post->post_content;

		// Replace the current intro (which is the rephrased one) with the original.
		$parts       = self::extract_intro( $content );
		$new_content = $last_entry['original'];
		if ( ! empty( $parts['rest'] ) ) {
			$new_content .= "\n\n" . $parts['rest'];
		}

		$update = wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => $new_content,
		), true );

		if ( is_wp_error( $update ) ) {
			return $update;
		}

		update_post_meta( $post_id, '_wpri_intro_history', $history );

		return true;
	}
}
