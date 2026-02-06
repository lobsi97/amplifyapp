<?php
/**
 * AJAX handlers for the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRI_Ajax {

	public static function init() {
		add_action( 'wp_ajax_wpri_preview', array( __CLASS__, 'handle_preview' ) );
		add_action( 'wp_ajax_wpri_apply', array( __CLASS__, 'handle_apply' ) );
		add_action( 'wp_ajax_wpri_restore', array( __CLASS__, 'handle_restore' ) );
	}

	/**
	 * Preview the rephrased intro without saving.
	 */
	public static function handle_preview() {
		check_ajax_referer( 'wpri_ajax_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permissions insuffisantes.', 'wp-rephrase-intro' ),
			) );
		}

		$result = WPRI_Rephraser::rephrase_post_intro( $post_id, true );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
			) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Apply the rephrased intro and save.
	 */
	public static function handle_apply() {
		check_ajax_referer( 'wpri_ajax_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permissions insuffisantes.', 'wp-rephrase-intro' ),
			) );
		}

		$result = WPRI_Rephraser::rephrase_post_intro( $post_id, false );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
			) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Restore the previous intro.
	 */
	public static function handle_restore() {
		check_ajax_referer( 'wpri_ajax_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Permissions insuffisantes.', 'wp-rephrase-intro' ),
			) );
		}

		$result = WPRI_Rephraser::restore_previous_intro( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
			) );
		}

		wp_send_json_success( array(
			'message' => __( 'Introduction précédente restaurée.', 'wp-rephrase-intro' ),
		) );
	}
}
