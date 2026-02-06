<?php
/**
 * Plugin settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRI_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function add_menu_page() {
		add_options_page(
			__( 'WP Rephrase Intro', 'wp-rephrase-intro' ),
			__( 'Rephrase Intro', 'wp-rephrase-intro' ),
			'manage_options',
			'wp-rephrase-intro',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function register_settings() {
		register_setting( 'wpri_settings', 'wpri_api_provider', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'openai',
		) );

		register_setting( 'wpri_settings', 'wpri_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		register_setting( 'wpri_settings', 'wpri_model', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		register_setting( 'wpri_settings', 'wpri_intro_length', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 2,
		) );

		register_setting( 'wpri_settings', 'wpri_tone', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'professional',
		) );

		register_setting( 'wpri_settings', 'wpri_language', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'fr',
		) );

		// API Section
		add_settings_section(
			'wpri_api_section',
			__( 'Configuration de l\'API', 'wp-rephrase-intro' ),
			array( __CLASS__, 'render_api_section' ),
			'wp-rephrase-intro'
		);

		add_settings_field( 'wpri_api_provider', __( 'Fournisseur d\'API', 'wp-rephrase-intro' ), array( __CLASS__, 'render_provider_field' ), 'wp-rephrase-intro', 'wpri_api_section' );
		add_settings_field( 'wpri_api_key', __( 'Clé API', 'wp-rephrase-intro' ), array( __CLASS__, 'render_api_key_field' ), 'wp-rephrase-intro', 'wpri_api_section' );
		add_settings_field( 'wpri_model', __( 'Modèle', 'wp-rephrase-intro' ), array( __CLASS__, 'render_model_field' ), 'wp-rephrase-intro', 'wpri_api_section' );

		// Content Section
		add_settings_section(
			'wpri_content_section',
			__( 'Configuration du contenu', 'wp-rephrase-intro' ),
			array( __CLASS__, 'render_content_section' ),
			'wp-rephrase-intro'
		);

		add_settings_field( 'wpri_intro_length', __( 'Paragraphes d\'introduction', 'wp-rephrase-intro' ), array( __CLASS__, 'render_intro_length_field' ), 'wp-rephrase-intro', 'wpri_content_section' );
		add_settings_field( 'wpri_tone', __( 'Ton', 'wp-rephrase-intro' ), array( __CLASS__, 'render_tone_field' ), 'wp-rephrase-intro', 'wpri_content_section' );
		add_settings_field( 'wpri_language', __( 'Langue', 'wp-rephrase-intro' ), array( __CLASS__, 'render_language_field' ), 'wp-rephrase-intro', 'wpri_content_section' );
	}

	public static function render_api_section() {
		echo '<p>' . esc_html__( 'Configurez votre fournisseur d\'IA pour la reformulation.', 'wp-rephrase-intro' ) . '</p>';
	}

	public static function render_content_section() {
		echo '<p>' . esc_html__( 'Définissez comment l\'introduction doit être détectée et reformulée.', 'wp-rephrase-intro' ) . '</p>';
	}

	public static function render_provider_field() {
		$value = get_option( 'wpri_api_provider', 'openai' );
		?>
		<select name="wpri_api_provider" id="wpri_api_provider">
			<option value="openai" <?php selected( $value, 'openai' ); ?>>OpenAI (GPT)</option>
			<option value="anthropic" <?php selected( $value, 'anthropic' ); ?>>Anthropic (Claude)</option>
		</select>
		<?php
	}

	public static function render_api_key_field() {
		$value = get_option( 'wpri_api_key', '' );
		?>
		<input type="password" name="wpri_api_key" id="wpri_api_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<p class="description"><?php esc_html_e( 'Entrez votre clé API. Elle sera stockée de manière sécurisée.', 'wp-rephrase-intro' ); ?></p>
		<?php
	}

	public static function render_model_field() {
		$value = get_option( 'wpri_model', '' );
		?>
		<input type="text" name="wpri_model" id="wpri_model" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Laisser vide pour le modèle par défaut', 'wp-rephrase-intro' ); ?>" />
		<p class="description"><?php esc_html_e( 'Ex: gpt-4o, claude-sonnet-4-5-20250929. Laissez vide pour utiliser le modèle par défaut.', 'wp-rephrase-intro' ); ?></p>
		<?php
	}

	public static function render_intro_length_field() {
		$value = get_option( 'wpri_intro_length', 2 );
		?>
		<input type="number" name="wpri_intro_length" id="wpri_intro_length" value="<?php echo esc_attr( $value ); ?>" min="1" max="10" class="small-text" />
		<p class="description"><?php esc_html_e( 'Nombre de paragraphes considérés comme l\'introduction (avant le premier sous-titre ou les N premiers paragraphes).', 'wp-rephrase-intro' ); ?></p>
		<?php
	}

	public static function render_tone_field() {
		$value = get_option( 'wpri_tone', 'professional' );
		$tones = array(
			'professional'  => __( 'Professionnel', 'wp-rephrase-intro' ),
			'casual'        => __( 'Décontracté', 'wp-rephrase-intro' ),
			'engaging'      => __( 'Engageant', 'wp-rephrase-intro' ),
			'formal'        => __( 'Formel', 'wp-rephrase-intro' ),
			'friendly'      => __( 'Amical', 'wp-rephrase-intro' ),
			'persuasive'    => __( 'Persuasif', 'wp-rephrase-intro' ),
		);
		?>
		<select name="wpri_tone" id="wpri_tone">
			<?php foreach ( $tones as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function render_language_field() {
		$value = get_option( 'wpri_language', 'fr' );
		$languages = array(
			'fr' => __( 'Français', 'wp-rephrase-intro' ),
			'en' => __( 'Anglais', 'wp-rephrase-intro' ),
			'es' => __( 'Espagnol', 'wp-rephrase-intro' ),
			'de' => __( 'Allemand', 'wp-rephrase-intro' ),
			'it' => __( 'Italien', 'wp-rephrase-intro' ),
			'pt' => __( 'Portugais', 'wp-rephrase-intro' ),
		);
		?>
		<select name="wpri_language" id="wpri_language">
			<?php foreach ( $languages as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'wpri_settings' );
				do_settings_sections( 'wp-rephrase-intro' );
				submit_button( __( 'Enregistrer les paramètres', 'wp-rephrase-intro' ) );
				?>
			</form>
		</div>
		<?php
	}
}
