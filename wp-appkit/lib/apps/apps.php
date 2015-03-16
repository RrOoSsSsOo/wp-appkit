<?php

class WpakApps {

	const menu_item = 'wpak_main_bo_settings';

	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'apps_custom_post_type' ) );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'add_settings_panels' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
			add_action( 'admin_print_styles', array( __CLASS__, 'admin_print_styles' ) );
			add_action( 'add_meta_boxes', array( __CLASS__, 'add_main_meta_box' ), 29 );
			add_action( 'add_meta_boxes', array( __CLASS__, 'add_phonegap_meta_box' ), 30 ); //30 to pass after the "Simulation" and "Export" boxes (see WpakBuild)
			add_action( 'wpak_inner_simulation_box', array( __CLASS__, 'inner_security_box' ), 10, 2 );
			add_action( 'save_post', array( __CLASS__, 'save_post' ) );
			add_filter( 'post_row_actions', array( __CLASS__, 'remove_quick_edit' ), 10, 2 );
			add_action( 'admin_head', array( __CLASS__, 'add_icon' ) );
		}
	}

	public static function admin_enqueue_scripts() {
		global $pagenow, $typenow;
		if ( ($pagenow == 'post.php' || $pagenow == 'post-new.php') && $typenow == 'wpak_apps' ) {
			wp_enqueue_script( 'wpak_apps_js', plugins_url( 'lib/apps/apps.js', dirname( dirname( __FILE__ ) ) ), array( 'jquery' ), WpAppKit::resources_version );
			wp_localize_script( 'wpak_apps_js', 'Apps', array( 'phonegap_mandatory' => self::get_phonegap_mandatory_fields() ) );
		}
	}

	public static function admin_print_styles() {
		global $pagenow, $typenow;
		if ( ($pagenow == 'post.php' || $pagenow == 'post-new.php') && $typenow == 'wpak_apps' ) {
			wp_enqueue_style( 'wpak_apps_css', plugins_url( 'lib/apps/apps.css', dirname( dirname( __FILE__ ) ) ), array(), WpAppKit::resources_version );
		}
	}

	public static function apps_custom_post_type() {

		$capability = current_user_can('wpak_edit_apps') ? 'wpak_app' : 'post';

		register_post_type(
			'wpak_apps',
			array(
				'label' => __( 'Applications', WpAppKit::i18n_domain ),
				'description' => '',
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => self::menu_item,
				'exclude_from_search' => true,
				'publicly_queryable' => false,
				'show_in_nav_menus' => false,
				'capability_type' => $capability,
				'hierarchical' => false,
				'rewrite' => false,
				'query_var' => false,
				'has_archive' => false,
				'supports' => array( 'title' ),
				'labels' => array(
					'name' => __( 'Applications', WpAppKit::i18n_domain ),
					'singular_name' => __( 'Application', WpAppKit::i18n_domain ),
					'menu_name' => __( 'Applications', WpAppKit::i18n_domain ),
					'add_new' => __( 'Add', WpAppKit::i18n_domain ),
					'add_new_item' => __( 'Add an application', WpAppKit::i18n_domain ),
					'edit' => __( 'Edit', WpAppKit::i18n_domain ),
					'edit_item' => __( 'Edit Application', WpAppKit::i18n_domain ),
					'new_item' => __( 'New application', WpAppKit::i18n_domain ),
					'not_found' => __( 'No application found', WpAppKit::i18n_domain ),
				)
			)
		);
	}

	public static function add_icon() {
		global $pagenow, $typenow;

		//TODO : use an external CSS instead of writing style directly in <head>...

		if ( $typenow == 'wpak_apps' && in_array( $pagenow, array( 'edit.php', 'post-new.php', 'post.php' ) ) ) {
			?>
			<style>
				#icon-wpak_main_bo_settings{
					background-image: url(<?php echo admin_url() ?>/images/icons32.png);
					background-position: -552px -5px;
				}
			</style>
			<?php
		}
	}

	public static function add_settings_panels() {
		$capability_required = current_user_can( 'wpak_edit_apps' ) ? 'wpak_edit_apps' : 'manage_options';
		add_menu_page( __( 'WP AppKit', WpAppKit::i18n_domain ), __( 'WP AppKit', WpAppKit::i18n_domain ), $capability_required, self::menu_item, array( __CLASS__, 'settings_panel' ) );
	}

	public static function add_main_meta_box() {
		remove_meta_box( 'submitdiv', 'wpak_apps', 'side' );

		add_meta_box(
			'wpak_app_publish',
			__( 'Publish', WpAppKit::i18n_domain ),
			array( __CLASS__, 'inner_publish_box' ),
			'wpak_apps',
			'side',
			'high'
		);

		add_meta_box(
			'wpak_app_main_infos',
			__( 'Platform', WpAppKit::i18n_domain ),
			array( __CLASS__, 'inner_main_infos_box' ),
			'wpak_apps',
			'normal',
			'default'
		);

	}

	public static function add_phonegap_meta_box() {

		add_meta_box(
			'wpak_app_phonegap_data',
			__( 'Phonegap config.xml data', WpAppKit::i18n_domain ),
			array( __CLASS__, 'inner_phonegap_infos_box' ),
			'wpak_apps',
			'normal',
			'default'
		);

	}

	public static function get_phonegap_mandatory_fields() {
		return array(
			// TODO: Determine which fields are mandatory
		);
	}

	public static function inner_publish_box( $post, $current_box ) {
		$first_save = !in_array( $post->post_status, array('publish', 'future', 'private') ) || 0 == $post->ID;
		$main_infos = self::get_app_main_infos( $post->ID );
		$mandatory = self::get_phonegap_mandatory_fields();
		$phonegap = 'ok';

		foreach( $mandatory as $key ) {
			if( '' === $main_infos[$key] ) {
				$phonegap = 'nok';
				break;
			}
		}

		$classes = array(
			'title' => ( !empty( $post->post_title ) ? 'ok' : 'nok' ),
			'components' => ( !empty( WpakComponents::get_app_components( $post->ID ) ) ? 'ok' : 'nok' ),
			'navigation' => ( !empty( WpakNavigation::get_app_navigation( $post->ID ) ) ? 'ok' : 'nok' ),
			'phonegap' => $phonegap,
			'save' => ( !$first_save ? 'ok' : 'nok' ),
		);
		?>
		<div class="submitbox" id="submitpost">
			<div style="display:none;">
				<?php submit_button( __( 'Save' ), 'button', 'save' ); ?>
			</div>

			<div id="minor-publishing">
				<div id="minor-publishing-actions">
					<div id="preview-action">
						<a href="<?php echo WpakBuild::get_appli_index_url( $post->ID ); ?>" class="preview button" target="_blank"><?php _e( 'Preview', WpAppKit::i18n_domain ) ?></a>
					</div>
					<div class="clear"></div>
				</div>
			</div>

			<div id="wpak_publish_box">
				<ol id="wpak_app_wizard">
					<li id="wpak_app_wizard_title" class="<?php echo $classes['title']; ?>"><?php _e( 'Define a title', WpAppKit::i18n_domain ); ?></li>
					<li id="wpak_app_wizard_components" class="<?php echo $classes['components']; ?>"><?php _e( 'Add components', WpAppKit::i18n_domain ); ?></li>
					<li id="wpak_app_wizard_navigation" class="<?php echo $classes['navigation']; ?>"><?php _e( 'Setup appearance and navigation', WpAppKit::i18n_domain ); ?></li>
					<li id="wpak_app_wizard_phonegap" class="<?php echo $classes['phonegap']; ?>"><?php _e( 'Setup PhoneGap config', WpAppKit::i18n_domain ); ?></li>
					<li id="wpak_app_wizard_save" class="<?php echo $classes['save']; ?>"><?php _e( 'Save your app', WpAppKit::i18n_domain ); ?></li>
				</ol>
			</div>

			<div id="major-publishing-actions">
				<div id="delete-action">
					<?php
					if ( current_user_can( "delete_post", $post->ID ) ) {
						if ( !EMPTY_TRASH_DAYS )
							$delete_text = __( 'Delete Permanently' );
						else
							$delete_text = __( 'Move to Trash' );
						?>
					<a class="submitdelete deletion" href="<?php echo get_delete_post_link( $post->ID ); ?>"><?php echo $delete_text; ?></a><?php
					} ?>
				</div>

				<div id="publishing-action">
					<span class="spinner"></span>
					<?php
					if ( $first_save ) { ?>
						<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Publish' ) ?>" />
						<?php submit_button( __( 'Save' ), 'primary button-large', 'publish', false, array( 'accesskey' => 'p' ) );
					} else { ?>
						<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Update' ) ?>" />
						<?php submit_button( __( 'Update' ), 'primary button-large', 'save', false, array( 'accesskey' => 'p', 'id' => 'publish' ) ); ?>
					<?php
					} ?>
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
	}

	public static function inner_main_infos_box( $post, $current_box ) {
		$main_infos = self::get_app_main_infos( $post->ID );
		?>
		<div class="wpak_settings">
			<select name="wpak_app_platform">
				<?php foreach ( self::get_platforms() as $value => $label ): ?>
					<?php $selected = $value == $main_infos['platform'] ? 'selected="selected"' : '' ?>
					<option value="<?php echo $value ?>" <?php echo $selected ?>><?php echo $label ?></option>
				<?php endforeach ?>
			</select>
			<?php wp_nonce_field( 'wpak-main-infos-' . $post->ID, 'wpak-nonce-main-infos' ) ?>
		</div>
		<style>
			.wpak_settings input[type=text]{ width:100% }
			.wpak_settings textarea{ width:100%;height:5em }
		</style>
		<?php
	}

	public static function inner_phonegap_infos_box( $post, $current_box ) {
		$main_infos = self::get_app_main_infos( $post->ID );
		?>
		<div class="wpak_settings">
			<span class="description"><?php _e( 'PhoneGap config.xml informations that are going to be displayed on App Stores.<br/>They are required when exporting the App to Phonegap, but are not used for App debug and simulation in browsers.', WpAppKit::i18n_domain ) ?></span>
			<br/><br/>
			<label><?php _e( 'Application name', WpAppKit::i18n_domain ) ?></label> : <br/>
			<input type="text" name="wpak_app_name" value="<?php echo esc_attr( $main_infos['name'] ) ?>" id="wpak_app_name" />
			<br/><br/>
			<label><?php _e( 'Application description', WpAppKit::i18n_domain ) ?></label> : <br/>
			<textarea name="wpak_app_desc" id="wpak_app_desc"><?php echo esc_textarea( $main_infos['desc'] ) ?></textarea>
			<br/><br/>
			<label><?php _e( 'Application id', WpAppKit::i18n_domain ) ?></label> : <br/>
			<input type="text" name="wpak_app_phonegap_id" value="<?php echo esc_attr( $main_infos['app_phonegap_id'] ) ?>" id="wpak_app_app_phonegap_id" />
			<br/><br/>
			<label><?php _e( 'Version', WpAppKit::i18n_domain ) ?></label> : <br/>
			<input type="text" name="wpak_app_version" value="<?php echo esc_attr( $main_infos['version'] ) ?>" id="wpak_app_version" />
			<br/><br/>
			<label><?php _e( 'Application versionCode (Android only)', WpAppKit::i18n_domain ) ?></label> : <br/>
			<input type="text" name="wpak_app_version_code" value="<?php echo esc_attr( $main_infos['version_code'] ) ?>" id="wpak_app_version_code" />
			<br/><br/>
			<label><?php _e( 'Phonegap version', WpAppKit::i18n_domain ) ?></label> : <br/>
			<input type="text" name="wpak_app_phonegap_version" value="<?php echo esc_attr( $main_infos['phonegap_version'] ) ?>" id="wpak_app_phonegap_version" />
			<br/><br/>
			<label><?php _e( 'Application author', WpAppKit::i18n_domain ) ?></label> : <br/>
			<input type="text" name="wpak_app_author" value="<?php echo esc_attr( $main_infos['author'] ) ?>" id="wpak_app_author" />
			<br/><br/>
			<label><?php _e( 'Application author website', WpAppKit::i18n_domain ) ?></label> : <br/>
			<input type="text" name="wpak_app_author_website" value="<?php echo esc_attr( $main_infos['author_website'] ) ?>" id="wpak_app_author_website" />
			<br/><br/>
			<label><?php _e( 'Application author email', WpAppKit::i18n_domain ) ?></label> : <br/>
			<input type="text" name="wpak_app_author_email" value="<?php echo esc_attr( $main_infos['author_email'] ) ?>" id="wpak_app_author_email" />
			<br/><br/>
			<label><?php _e( 'Phonegap plugins', WpAppKit::i18n_domain ) ?></label> : <br/>
			<textarea name="wpak_app_phonegap_plugins" id="wpak_app_phonegap_plugins"><?php echo esc_textarea( $main_infos['phonegap_plugins'] ) ?></textarea>
			<span class="description"><?php _e( 'Write the phonegap plugins tags as defined in the PhoneGap documentation.<br/>Example : to include the "In App Browser" plugin for a Phonegap Build compilation, enter &lt;gap:plugin name="org.apache.cordova.inappbrowser" version="0.3.3" /&gt; directly in the textarea.', WpAppKit::i18n_domain ) ?></span>
			<br/><br/>
			<label><?php _e( 'Icons and splashscreens', WpAppKit::i18n_domain ) ?></label> : <br/>
			<textarea name="wpak_app_icons" id="wpak_app_icons"><?php echo esc_textarea( $main_infos['icons'] ) ?></textarea>
			<span class="description"><?php _e( 'Write the icons and spashscreens tags as defined in the PhoneGap documentation.<br/>Example : &lt;icon src="icons/ldpi.png" gap:platform="android" gap:qualifier="ldpi" /&gt;', WpAppKit::i18n_domain ) ?></span>
			<br/><br/>
			<a href="<?php echo WpakBuild::get_appli_dir_url() . '/config.xml?wpak_app_id=' . self::get_app_slug( $post->ID ) ?>" target="_blank"><?php _e( 'View config.xml', WpAppKit::i18n_domain ) ?></a>
			<?php wp_nonce_field( 'wpak-phonegap-infos-' . $post->ID, 'wpak-nonce-phonegap-infos' ) ?>
		</div>
		<?php
	}

	public static function inner_security_box( $post, $current_box ) {
		$secured = self::get_app_is_secured( $post->ID );
		$simulation_secured = self::get_app_simulation_is_secured( $post->ID );
		?>
		<br/>
		<label><?php _e( 'Private App simulation', WpAppKit::i18n_domain ) ?></label> : <br/>
		<span class="description"><?php _e( 'If activated, only connected users with right permissions can access the app simulation in web browser.<br/>If deactivated, the app simulation is publicly available in any browser, including the config.js and config.xml files, that can contain sensitive data.', WpAppKit::i18n_domain ) ?></span>
		<br/>
		<select name="wpak_app_simulation_secured">
			<option value="1" <?php echo $simulation_secured ? 'selected="selected"' : '' ?>><?php _e( 'Private', WpAppKit::i18n_domain ) ?></option>
			<option value="0" <?php echo!$simulation_secured ? 'selected="selected"' : '' ?>><?php _e( 'Public', WpAppKit::i18n_domain ) ?></option>
		</select>
		<?php wp_nonce_field( 'wpak-security-infos-' . $post->ID, 'wpak-nonce-security-infos' ) ?>
		<?php
	}

	public static function remove_quick_edit( $actions ) {
		global $post;
		if ( $post->post_type == 'wpak_apps' ) {
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	public static function save_post( $post_id ) {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( empty( $_POST['post_type'] ) || $_POST['post_type'] != 'wpak_apps' ) {
			return;
		}

		if ( !current_user_can( 'edit_post', $post_id ) && !current_user_can( 'wpak_edit_apps', $post_id ) ) {
			return;
		}

		if ( !check_admin_referer( 'wpak-main-infos-' . $post_id, 'wpak-nonce-main-infos' ) || !check_admin_referer( 'wpak-phonegap-infos-' . $post_id, 'wpak-nonce-phonegap-infos' ) || !check_admin_referer( 'wpak-security-infos-' . $post_id, 'wpak-nonce-security-infos' )
		) {
			return;
		}

		if ( isset( $_POST['wpak_app_name'] ) ) {
			update_post_meta( $post_id, '_wpak_app_name', sanitize_text_field( $_POST['wpak_app_name'] ) );
		}

		if ( isset( $_POST['wpak_app_phonegap_id'] ) ) {
			update_post_meta( $post_id, '_wpak_app_phonegap_id', sanitize_text_field( $_POST['wpak_app_phonegap_id'] ) );
		}

		if ( isset( $_POST['wpak_app_desc'] ) ) {
			update_post_meta( $post_id, '_wpak_app_desc', sanitize_text_field( $_POST['wpak_app_desc'] ) );
		}

		if ( isset( $_POST['wpak_app_version'] ) ) {
			$app_version = self::sanitize_app_version( $_POST['wpak_app_version'] );
			update_post_meta( $post_id, '_wpak_app_version', $app_version );
		}

		if ( isset( $_POST['wpak_app_version_code'] ) ) {
			update_post_meta( $post_id, '_wpak_app_version_code', sanitize_text_field( $_POST['wpak_app_version_code'] ) );
		}

		if ( isset( $_POST['wpak_app_phonegap_version'] ) ) {
			update_post_meta( $post_id, '_wpak_app_phonegap_version', sanitize_text_field( $_POST['wpak_app_phonegap_version'] ) );
		}

		if ( isset( $_POST['wpak_app_platform'] ) ) {
			update_post_meta( $post_id, '_wpak_app_platform', sanitize_text_field( $_POST['wpak_app_platform'] ) );
		}

		if ( isset( $_POST['wpak_app_author'] ) ) {
			update_post_meta( $post_id, '_wpak_app_author', sanitize_text_field( $_POST['wpak_app_author'] ) );
		}

		if ( isset( $_POST['wpak_app_author_website'] ) ) {
			update_post_meta( $post_id, '_wpak_app_author_website', sanitize_text_field( $_POST['wpak_app_author_website'] ) );
		}

		if ( isset( $_POST['wpak_app_author_email'] ) ) {
			update_post_meta( $post_id, '_wpak_app_author_email', sanitize_text_field( $_POST['wpak_app_author_email'] ) );
		}

		if ( isset( $_POST['wpak_app_phonegap_plugins'] ) ) {
			$phonegap_plugins = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $_POST['wpak_app_phonegap_plugins'] );
			update_post_meta( $post_id, '_wpak_app_phonegap_plugins', trim( $phonegap_plugins ) );
		}

		if ( isset( $_POST['wpak_app_icons'] ) ) {
			$app_icons = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $_POST['wpak_app_icons'] );
			update_post_meta( $post_id, '_wpak_app_icons', trim( $app_icons ) );
		}

		if ( isset( $_POST['wpak_app_simulation_secured'] ) ) {
			update_post_meta( $post_id, '_wpak_app_simulation_secured', sanitize_text_field( $_POST['wpak_app_simulation_secured'] ) );
		}

	}

	/**
	 * Checks that the app version is in the 1.2.3 format.
	 *
	 * @param string $app_version_raw App version to sanitize
	 * @return string Sanitized app version
	 */
	public static function sanitize_app_version( $app_version_raw ) {
		$app_version = array();
		$app_version_raw = sanitize_text_field( $app_version_raw );
		$app_version_split = explode( '.', $app_version_raw );
		foreach ( $app_version_split as $version_part ) {
			if ( is_numeric( $version_part ) ) {
				$app_version[] = intval( $version_part );
			}
		}
		$app_version = implode( '.', $app_version );
		return $app_version;
	}

	private static function get_platforms() {
		return array(
			'ios' => __( 'iOS', WpAppKit::i18n_domain ),
			'android' => __( 'Android', WpAppKit::i18n_domain )
		);
	}

	public static function get_apps() {
		$args = array(
			'post_type' => 'wpak_apps',
			'post_status' => 'publish',
			'numberposts' => -1
		);

		$apps_raw = get_posts( $args );

		$apps = array();
		foreach ( $apps_raw as $app ) {
			$apps[$app->ID] = $app;
		}

		return $apps;
	}

	public static function get_app( $app_id_or_slug, $no_meta = false ) {
		$app = null;

		$app_id = self::get_app_id( $app_id_or_slug );

		if ( !empty( $app_id ) ) {
			$app = get_post( $app_id );
			if ( !$no_meta ) {
				if ( !empty( $app ) ) {
					$app->main_infos = self::get_app_main_infos( $app_id );
					$app->components = WpakComponents::get_app_components( $app_id );
					$app->navigation = WpakNavigation::get_app_navigation( $app_id );
				} else {
					$app = null;
				}
			}
		}

		return $app;
	}

	public static function app_exists( $app_id_or_slug ) {
		return self::get_app_id( $app_id_or_slug ) != 0;
	}

	public static function get_app_id( $app_id_or_slug ) {

		if ( is_numeric( $app_id_or_slug ) ) {
			return intval( $app_id_or_slug );
		}

		$args = array(
			'name' => $app_id_or_slug,
			'post_type' => 'wpak_apps',
			'post_status' => 'publish',
			'numberposts' => 1
		);

		$apps = get_posts( $args );

		return !empty( $apps ) ? $apps[0]->ID : 0;
	}

	public static function get_app_slug( $app_id_or_slug ) {
		$app = self::get_app( $app_id_or_slug, true );
		return !empty( $app ) ? $app->post_name : '';
	}

	public static function get_app_main_infos( $post_id ) {
		$title = get_post_meta( $post_id, '_wpak_app_title', true ); //handled in WpakThemesBoSettings
		$app_phonegap_id = get_post_meta( $post_id, '_wpak_app_phonegap_id', true );
		$name = get_post_meta( $post_id, '_wpak_app_name', true );
		$desc = get_post_meta( $post_id, '_wpak_app_desc', true );
		$version = get_post_meta( $post_id, '_wpak_app_version', true );
		$version_code = get_post_meta( $post_id, '_wpak_app_version_code', true );
		$phonegap_version = get_post_meta( $post_id, '_wpak_app_phonegap_version', true );
		$platform = get_post_meta( $post_id, '_wpak_app_platform', true );
		$author = get_post_meta( $post_id, '_wpak_app_author', true );
		$author_website = get_post_meta( $post_id, '_wpak_app_author_website', true );
		$author_email = get_post_meta( $post_id, '_wpak_app_author_email', true );
		$icons = get_post_meta( $post_id, '_wpak_app_icons', true );

		$phonegap_plugins = '';
		if ( metadata_exists( 'post', $post_id, '_wpak_app_phonegap_plugins' ) ) {
			$phonegap_plugins = get_post_meta( $post_id, '_wpak_app_phonegap_plugins', true );
		}

		return array( 'title' => $title,
			'name' => $name,
			'app_phonegap_id' => $app_phonegap_id,
			'desc' => $desc,
			'version' => $version,
			'version_code' => $version_code,
			'phonegap_version' => $phonegap_version,
			'platform' => $platform,
			'author' => $author,
			'author_website' => $author_website,
			'author_email' => $author_email,
			'phonegap_plugins' => $phonegap_plugins,
			'icons' => $icons,
		);
	}

	public static function get_app_is_secured( $post_id ) {
		return apply_filters( 'wpak_app_secured', true );
	}

	public static function get_app_simulation_is_secured( $post_id ) {
		$secured_raw = get_post_meta( $post_id, '_wpak_app_simulation_secured', true );
		$secured_raw = $secured_raw === '' || $secured_raw === false ? 1 : $secured_raw;
		return intval( $secured_raw ) == 1;
	}

	/**
	 * Add/merge WP AppKit default Phonegap Build plugins to those set in BO and return
	 * them as config.xml ready XML.
	 *
	 * @param int $app_id Application ID
	 * @param string $bo_plugins_xml Optional. Pass this if the BO plugins XML has already be computed.
	 * @return string Merged BO and default plugins XML.
	 */
	public static function get_merged_phonegap_plugins_xml( $app_id, $bo_plugins_xml = '' ) {

		if ( empty( $bo_plugins_xml ) ) {
			$app_main_infos = WpakApps::get_app_main_infos( $app_id );
			$bo_plugins_xml = $app_main_infos['phonegap_plugins'];
		}

		$bo_plugins_array = self::parse_plugins_from_xml( $bo_plugins_xml );

		$merged_plugins = array_merge( self::get_default_phonegap_plugins( $app_id ), $bo_plugins_array );

		return self::get_plugins_xml($merged_plugins);
	}

	protected static function get_default_phonegap_plugins( $app_id ) {

		$default_plugins = array(
			'org.apache.cordova.inappbrowser' => array( 'version' => '' ),
			'org.apache.cordova.network-information' => array( 'version' => '' )
		);

		$app_main_infos = WpakApps::get_app_main_infos( $app_id );
		if( $app_main_infos['platform'] == 'ios' ) {
			$default_plugins['com.phonegap.plugin.statusbar'] = array( 'version' => '' );
		}

		/**
		 * Filter the Phonegap Build plugins that are included by default by WP AppKit
		 *
		 * @param array		$default_plugins	Array of default Phonegap plugins.
		 * @param int		$app_id				Application id
		 */
		$default_plugins = apply_filters( 'wpak_default_phonegap_build_plugins', $default_plugins, $app_id );

		return $default_plugins;
	}

	protected static function get_plugins_xml( $plugins ) {
		$plugins_xml = '';

		if ( is_array( $plugins ) ) {
			$plugins_xml_array = array();
			foreach ( $plugins as $plugin_name => $plugin_data ) {
				$plugin_xml = '<gap:plugin name="' . $plugin_name . '"';
				if ( !empty( $plugin_data['version'] ) ) {
					$plugin_xml .= ' version="'. $plugin_data['version'] .'"';
				}
				$plugin_xml .= ' />';
				$plugins_xml_array[] = $plugin_xml;
			}
			$plugins_xml = implode( "\n", $plugins_xml_array );
		}

		return $plugins_xml;
	}

	protected static function parse_plugins_from_xml( $plugins_xml ) {
		$plugins_array = array();

		if ( preg_match_all( '/<gap:plugin [^\/]+\/>/s', $plugins_xml, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				$name = '';
				$version = '';
				if ( preg_match( '/name="([^"]+)"/', $match, $name_match ) && strlen( $name_match[1] ) > 0 ) {
					if ( preg_match( '/version="([^"]+)"/', $match, $version_match ) && strlen( $version_match[1] ) > 0 ) {
						$version = $version_match[1];
					}
					$plugins_array[$name_match[1]] = array( 'version' => $version );
				}
			}
		}

		return $plugins_array;
	}

}

WpakApps::hooks();
