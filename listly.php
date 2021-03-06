<?php
/*
	Plugin Name: List.ly
	Plugin URI:  http://wordpress.org/extend/plugins/listly/
	Description: Plugin to easily integrate List.ly lists to Posts and Pages. It allows publishers to add/edit lists, add items to list and embed lists using shortcode. <a href="mailto:support@list.ly">Contact Support</a>
	Version:     2.0
	Author:      Milan Kaneria
	Author URI:  http://brandintellect.in/?Listly
*/


if ( ! class_exists( 'Listly' ) )
{
	class Listly
	{
		private static $Instance;


		static function Instance()
		{
			if ( ! self::$Instance )
			{
				self::$Instance = new self();
			}

			return self::$Instance;
		}


		function __construct()
		{
			$this->Version = '2.0';
			$this->PluginFile = __FILE__;
			$this->PluginName = 'Listly';
			$this->PluginPath = dirname( $this->PluginFile ) . '/';
			$this->PluginURL = get_bloginfo( 'wpurl' ) . '/wp-content/plugins/' . dirname( plugin_basename( $this->PluginFile ) ) . '/';
			$this->SettingsURL = 'options-general.php?page=Listly';
			$this->SettingsName = 'Listly';
			$this->Settings = get_option( $this->SettingsName );
			$this->SiteURL = is_ssl() ? 'https://list.ly/api/v2/' : 'http://list.ly/api/v2/';

			if ( self::$Instance )
			{
				wp_die( sprintf( '<strong>%s:</strong> Please use the <code>%s::Instance()</code> method for initialization.', $this->PluginName, __CLASS__ ) );
			}

			$this->SettingsDefaults = array
			(
				'Version' => 0,
				'PublisherKey' => '',
				'Layout' => 'full',
				'APIStylesheet' => '',
				'Styling' => array(),
			);

			$this->PostDefaults = array
			(
				'method' => 'POST',
				'timeout' => 5,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking' => true,
				'decompress' => true,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded', 'Accept-Encoding' => 'gzip, deflate' ),
				'body' => array(),
				'cookies' => array(),
			);


			register_activation_hook( $this->PluginFile, array( $this, 'Activate' ) );

			add_filter( 'plugin_action_links_' . plugin_basename( $this->PluginFile ), array( $this, 'ActionLinks' ) );
			add_action( 'init', array( $this, 'Init' ) );
			add_action( 'widgets_init', array( $this, 'WidgetsInit' ) );
			add_action( 'admin_init', array( $this, 'AdminInit' ) );
			add_action( 'admin_menu', array( $this, 'AdminMenu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'AdminEnqueueScripts' ), 10, 1 );
			add_action( 'wp_ajax_ListlyAJAXPublisherAuth', array( $this, 'ListlyAJAXPublisherAuth' ) );
			add_action( 'wp_insert_post', array( $this, 'WPInsertPost' ), 10, 3 );
			add_action( 'the_posts', array( $this, 'ThePosts' ), 10, 2 );
			add_shortcode( 'listly', array( $this, 'ShortCode' ) );
			//wp_embed_register_handler( 'listly', '#http://(?:www\.)?list\.ly/list/(\w+).*#i', array( $this, 'Embed' ) );

			if ( $this->Settings['PublisherKey'] == '' )
			{
				add_action( 'admin_notices', create_function( '', "print '<div class=\'error\'><p><strong>$this->PluginName:</strong> Please enter Publisher Key on <a href=\'$this->SettingsURL\'>Settings</a> page.</p></div>';" ) );
			}
		}

		function Activate( $NetworkWide = false )
		{
			if ( is_multisite() && ( $NetworkWide || is_plugin_active_for_network( plugin_basename( $this->PluginFile ) ) ) )
			{
				foreach ( get_blog_list( 0, 'all' ) as $Blog )
				{
					switch_to_blog( $Blog['blog_id'] );

						$SettingsCurrent = get_option( $this->SettingsName );

						if ( is_array( $SettingsCurrent ) )
						{
							$Settings = array_merge( $this->SettingsDefaults, $SettingsCurrent );
							$Settings = array_intersect_key( $Settings, $this->SettingsDefaults );

							$Settings['Version'] = $this->Version;

							update_option( $this->SettingsName, $Settings );
						}
						else
						{
							add_option( $this->SettingsName, $this->SettingsDefaults );
						}

					restore_current_blog();
				}
			}
			else
			{
				if ( is_array( $this->Settings ) )
				{
					$Settings = array_merge( $this->SettingsDefaults, $this->Settings );
					$Settings = array_intersect_key( $Settings, $this->SettingsDefaults );

					$Settings['Version'] = $this->Version;

					update_option( $this->SettingsName, $Settings );
				}
				else
				{
					add_option( $this->SettingsName, $this->SettingsDefaults );
				}
			}
		}


		function ActionLinks( $Links )
		{
			$Link = "<a href='$this->SettingsURL'>Settings</a>";

			array_push( $Links, $Link );

			return $Links;
		}


		function Init()
		{
			if ( isset( $_GET['ListlyDeleteCache'] ) )
			{
				global $wpdb;

				$TransientId = ( $_GET['ListlyDeleteCache'] != '' ) ? $_GET['ListlyDeleteCache'] : 'Listly-';

				$Transients = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT option_name FROM $wpdb->options WHERE option_name LIKE %s", array( "_transient_$TransientId%" ) ) );

				if ( $Transients )
				{
					foreach ( $Transients as $Transient )
					{
						delete_transient( str_ireplace( '_transient_', '', $Transient ) );
					}

					print 'Listly: Cached data deleted.';
				}
				else
				{
					print 'Listly: No cached data found.';
				}

				exit;
			}

/*
			if ( is_active_widget( false, false, 'listly-widget', true ) )
			{
				if ( $this->Settings['APIStylesheet'] )
				{
					wp_enqueue_style( 'listly-style', $this->Settings['APIStylesheet'], false, $this->Version );
				}

				wp_enqueue_script( 'jquery' );

				add_action( 'wp_head', array( $this, 'WPHead' ) );
			}
*/
		}


		function WidgetsInit()
		{
			register_widget( 'Listly_Widget' );
		}


		function AdminInit()
		{
			if ( version_compare( $this->Settings['Version'], $this->Version, '<' ) )
			{
				$this->Activate();

				add_action( 'admin_notices', create_function( '', "print '<div class=\'updated\'><p><strong>$this->PluginName:</strong> Plugin settings has been successfully updated!</p></div>';" ) );
			}
		}


		function AdminMenu()
		{
			$ListlyHook = add_submenu_page( 'options-general.php', 'Listly Settings', 'Listly', 'manage_options', 'Listly', array( $this, 'Admin' ) );

			add_action( "load-$ListlyHook", array( $this, 'AdminMenuLoad' ) );

			add_meta_box( 'ListlyMetaBox', 'Listly', array( $this, 'MetaBox' ), 'page', 'side', 'default' );
			add_meta_box( 'ListlyMetaBox', 'Listly', array( $this, 'MetaBox' ), 'post', 'side', 'core' );

			$PostTypes = get_post_types( array( '_builtin' => false ) );

			if ( count( $PostTypes ) )
			{
				foreach ( $PostTypes as $PostType )
				{
					add_meta_box( 'ListlyMetaBox', 'Listly', array( $this, 'MetaBox' ), $PostType, 'side', 'default' );
				}
			}
		}


		function AdminMenuLoad()
		{
			global $wp_version;

			if ( version_compare( $wp_version, '3.5', '>=' ) )
			{
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
			}

			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'listly-script', $this->PluginURL . 'script.js', false, $this->Version, false );
			wp_localize_script( 'listly-script', 'Listly', array( 'PluginURL' => $this->PluginURL, 'SiteURL' => $this->SiteURL, 'Key' => $this->Settings['PublisherKey'], 'Nounce' => wp_create_nonce( 'ListlyNounce' ) ) );
			wp_enqueue_style( 'listly-style', $this->PluginURL . 'style.css', false, $this->Version );

			add_filter( 'contextual_help', array( $this, 'AdminContextualHelp' ), 10, 3 );
		}


		function AdminContextualHelp( $Help, $ScreenId, $Screen )
		{
			return '<p><a href="mailto:support@list.ly">Contact Support</a></p> <p><a target="_blank" href="http://list.ly/publishers/landing">Request Publisher Key</a></p>';
		}


		function AdminEnqueueScripts( $Hook )
		{
			if ( $Hook == 'post.php' || $Hook == 'post-new.php' )
			{
				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'listly-script', $this->PluginURL . 'script.js', false, $this->Version, false );
				wp_localize_script( 'listly-script', 'Listly', array( 'PluginURL' => $this->PluginURL, 'SiteURL' => $this->SiteURL, 'Key' => $this->Settings['PublisherKey'], 'Nounce' => wp_create_nonce( 'ListlyNounce' ) ) );
				wp_enqueue_style( 'listly-style', $this->PluginURL . 'style.css', false, $this->Version );
			}
		}


		function Admin()
		{
			if ( ! current_user_can( 'manage_options' ) )
			{
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}

			if ( isset( $_POST['action'] ) && ! wp_verify_nonce( $_POST['nonce'], $this->SettingsName ) )
			{
				wp_die( __( 'Security check failed! Settings not saved.' ) );
			}

			global $wpdb, $wp_version;

			if ( isset( $_POST['action'] ) && $_POST['action'] == 'Save Settings' )
			{
				foreach ( $_POST as $Key => $Value )
				{
					if ( array_key_exists( $Key, $this->SettingsDefaults ) )
					{
						if ( is_array( $Value ) )
						{
							array_walk_recursive( $Value, array( $this, 'TrimByReference' ) );
						}
						else
						{
							$Value = trim( $Value );
						}

						$this->Settings[$Key] = $Value;
					}
				}

				if ( update_option( $this->SettingsName, $this->Settings ) )
				{
					print '<div class="updated"><p><strong>Settings saved.</strong></p></div>';
				}
			}

			if ( isset( $_POST['action'] ) && $_POST['action'] == 'Delete Cache' )
			{
				$Transients = $wpdb->get_col( "SELECT DISTINCT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_Listly-%'" );

				if ( $Transients )
				{
					foreach ( $Transients as $Transient )
					{
						delete_transient( str_ireplace( '_transient_', '', $Transient ) );
					}

					print '<div class="updated"><p><strong>All cached data deleted.</strong></p></div>';
				}
				else
				{
					print '<div class="error"><p><strong>No cached data found.</strong></p></div>';
				}
			}

		?>

			<div class="wrap">

				<h2>Listly Settings</h2>

				<p>You can create a Listly account on <a href="https://list.ly">Listly Website</a>.  You also need a Publisher Key to use this plugin, which you can get from <a href="https://list.ly/publishers/landing" target="_blank">Listly Publisher Page</a>.  <br/>Support and help are available on the <a href="https://list.ly/community" target="_blank">Listly Community Site</a>.  A Pro upgrade gets you <a href="http://list.ly/upgrade">cool features</a> and lots of <i class="dashicons dashicons-heart"></i> from all of us at Listly</p>

				<form method="post" action="">

					<h3>General</h3>

					<table class="form-table listly-table">

						<tr valign="top">
							<th scope="row">
								<a target="_blank" href="http://list.ly/publishers/landing">Publisher Key</a>
							</th>
							<td>
								<div>
								  <input name="PublisherKey" type="text" value="<?php print $this->Settings['PublisherKey']; ?>" class="regular-text" />
								  <button id="ListlyAdminAuthCheck" href="#">Check Status</button>
								</div>
								<span id="ListlyAdminAuthStatus"></span>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">
								 Default Layout For Lists
							</th>
							<td>
								<select name="Layout">
									<option value="full" <?php $this->CheckSelected( $this->Settings['Layout'], 'full' ); ?>>List</option>
									<option value="short" <?php $this->CheckSelected( $this->Settings['Layout'], 'short' ); ?>>Minimal</option>
									<option value="gallery" <?php $this->CheckSelected( $this->Settings['Layout'], 'gallery' ); ?>>Gallery</option>
									<option value="slideshow" <?php $this->CheckSelected( $this->Settings['Layout'], 'slideshow' ); ?>>Slideshow</option>
								</select>
								<br />
								<span class="description">This is the default option for ShortCode.</span>
							</td>
						</tr>

					</table>

					<h3>Custom List Styles</h3>
					<p> Custom styles will only work with premium lists. Free accounts have three premium lists. Pro accounts have unlimited.</p>
					<table class="form-table listly-table">

						<tr valign="top">
							<th scope="row">
								Text Color
							</th>
							<td>
								<input name="Styling[text_color]" type="text" value="<?php print $this->Settings['Styling']['text_color']; ?>" class="regular-text listly-color-field" />
								<p class="description">Color for normal text</p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								Text Font
							</th>
							<td>
								<input name="Styling[text_font]" type="text" value="<?php print $this->Settings['Styling']['text_font']; ?>" class="regular-text" />
								<p class="description">Font for normal text</p>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">
								Title Color
							</th>
							<td>
								<input name="Styling[title_color]" type="text" value="<?php print $this->Settings['Styling']['title_color']; ?>" class="regular-text listly-color-field" />
								<p class="description">Color for title text (h1, h2)</p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								Title Font
							</th>
							<td>
								<input name="Styling[title_font]" type="text" value="<?php print $this->Settings['Styling']['title_font']; ?>" class="regular-text" />
								<p class="description">Font for title text (h1, h2)</p>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">
								Border Color
							</th>
							<td>
								<input name="Styling[border_color]" type="text" value="<?php print $this->Settings['Styling']['border_color']; ?>" class="regular-text listly-color-field" />
								<p class="description">Border color for list area</p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								Link Color
							</th>
							<td>
								<input name="Styling[link_color]" type="text" value="<?php print $this->Settings['Styling']['link_color']; ?>" class="regular-text listly-color-field" />
								<p class="description">Link color for regular links in normal text</p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								Secondary Text Color
							</th>
							<td>
								<input name="Styling[secondary_link_color]" type="text" value="<?php print $this->Settings['Styling']['secondary_link_color']; ?>" class="regular-text listly-color-field" />
								<p class="description">Color for de-emphasized (dimmed) text</p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								Secondary Link Color
							</th>
							<td>
								<input name="Styling[secondary_link_color]" type="text" value="<?php print $this->Settings['Styling']['secondary_link_color']; ?>" class="regular-text listly-color-field" />
								<p class="description">Color for de-emphasized links and actions</p>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								Separator Color
							</th>
							<td>
								<input name="Styling[separator_color]" type="text" value="<?php print $this->Settings['Styling']['separator_color']; ?>" class="regular-text listly-color-field" />
								<p class="description">Color for separator lines within list</p>
							</td>
						</tr>

					</table>

					<h3>Caching</h3>

					<table class="form-table listly-table">

						<tr valign="top">
							<th scope="row">Delete Cache</th>
							<td>
								<input name="action" type="submit" value="Delete Cache" class="button-secondary" />
							</td>
						</tr>

						<?php if ( isset( $_GET['debug'] ) ) : ?>
							<tr valign="top">
								<th scope="row">Cached Items</th>
								<td>
									<?php
										$Transients = $wpdb->get_col( "SELECT DISTINCT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_Listly-%'" );

										if ( $Transients )
										{
											foreach ( $Transients as $Transient )
											{
												$TransientId = str_ireplace( '_transient_', '', $Transient );
												$Timeout = date( get_option( 'date_format' ).' '.get_option( 'time_format' ), get_option( "_transient_timeout_$TransientId" ) );

												print "<p>$TransientId ($Timeout)</p>";
											}
										}
										else
										{
											print 'No cached data found.';
										}
									?>
								</td>
							</tr>
						<?php endif; ?>

					</table>

					<input name="nonce" type="hidden" value="<?php print wp_create_nonce( $this->SettingsName ); ?>" />

					<div class="submit"><input name="action" type="submit" value="Save Settings" class="button-primary" /></div>

				</form>

			</div>

			<script type="text/javascript">

				jQuery( document ).ready( function( $ )
				{
					<?php if ( version_compare( $wp_version, '3.5', '>=' ) ) : ?>
						$( '.listly-color-field' ).wpColorPicker();
					<?php endif; ?>
				});

			</script>

		<?php

		}


		function ListlyAJAXPublisherAuth()
		{
			define( 'DONOTCACHEPAGE', true );

			if ( ! wp_verify_nonce( $_POST['nounce'], 'ListlyNounce' ) )
			{
				print '<span class="error">Authorisation failed.</span>';
				exit;
			}

			if ( isset( $_POST['action'], $_POST['Key'] ) )
			{
				if ( $_POST['Key'] == '' )
				{
					print '<span class="error">Please enter Publisher Key.</span>';
				}
				else
				{
					$PostParms = array_merge( $this->PostDefaults, array( 'body' => http_build_query( array( 'key' => $_POST['Key'] ) ) ) );
					$Response = wp_remote_post( $this->SiteURL . 'publisher/auth.json', $PostParms );

					if ( is_wp_error( $Response ) || ! isset( $Response['body'] ) || $Response['body'] == '' )
					{
						print '<span class="error">No connectivity or Listly service not available. Try later.</span>';
					}
					else
					{
						$ResponseJson = json_decode( $Response['body'], true );

						if ( $ResponseJson['status'] == 'ok' )
						{
							print '<span class="info"><i class="dashicons dashicons-yes"></i> ' . $ResponseJson['message'] . '</span>&nbsp;&nbsp;' . ($ResponseJson['subscription'] ? '<span class="info pro"><i class="dashicons dashicons-yes"></i> Pro Account</span>' : '<span class="info basic">Free Account</span>');
						}
						else
						{
							print '<span class="error">' . $ResponseJson['message'] . '</span>';
						}
					}
				}
			}

			exit;
		}


		function MetaBox()
		{
			if ( $this->Settings['PublisherKey'] == '' )
			{
				print "<p>Please enter Publisher Key on <a href='$this->SettingsURL'>Settings</a> page. You can get your Publisher Key from <a target='_blank' href='http://list.ly/publishers/landing'>Listly</a>.</p>";
			}
			else
			{
				$UserURL = '#';

				$PostParms = array_merge( $this->PostDefaults, array( 'body' => http_build_query( array( 'key' => $this->Settings['PublisherKey'] ) ) ) );

				if ( false === ( $Response = get_transient( 'Listly-Auth' ) ) )
				{
					$Response = wp_remote_post( $this->SiteURL . 'publisher/auth.json', $PostParms );

					if ( ! is_wp_error( $Response ) && isset( $Response['body'] ) && $Response['body'] != '' )
					{
						set_transient( 'Listly-Auth', $Response, 86400 );
					}
				}

				if ( ! is_wp_error( $Response ) && isset( $Response['body'] ) && $Response['body'] != '' )
				{
					$ResponseJson = json_decode( $Response['body'], true );

					if ( $ResponseJson['status'] == 'ok' )
					{
						$UserURL = $ResponseJson['user_url'] . '?trigger=newlist';
					}
				}

			?>

				<div style="text-align: right;"><a class="button button-small" target="_blank" href="<?php print $UserURL; ?>">Make New List</a></div>

				<p>
					<div class="ListlyAdminListSearchWrap">
						<input type="text" name="ListlyAdminListSearch" placeholder="Type 4 characters to search" autocomplete="off"/>
						<a class="ListlyAdminListSearchClear dashicons dashicons-no" href="javascript:void(0)"></a>
					</div>
					<label><input type="radio" name="ListlyAdminListSearchType" value="publisher" checked="checked" /> <small>Just My Lists</small></label> &nbsp; <label><input type="radio" name="ListlyAdminListSearchType" value="all" /> <small>Search All Lists</small></label>
				</p>

				<div id="ListlyAdminYourList"></div>

			<?php

			}
		}


		function WPInsertPost( $PostId, $Post, $Update )
		{
			if ( ! $Update )
			{
				delete_transient( 'Listly-Widget-Lists' );
				delete_transient( 'Listly-Widget-Posts' );
			}
		}


		function ThePosts( $Posts, $WPQuery )
		{
			if ( ! is_admin() && count( $Posts ) )
			{
				foreach ( $Posts as $Post )
				{
					if ( has_shortcode( $Post->post_content, 'listly' ) || preg_match( '#http://(?:www\.)?list\.ly/list/(\w+).*#i', $Post->post_content ) )
					{
						if ( $this->Settings['APIStylesheet'] )
						{
							wp_enqueue_style( 'listly-style', $this->Settings['APIStylesheet'], false, $this->Version );
						}

						wp_enqueue_script( 'jquery' );

						add_action( 'wp_head', array( $this, 'WPHead' ) );

						break;
					}
				}
			}

			return $Posts;
		}


		function WPHead()
		{
			static $Called;

			if ( ! $Called )
			{
				$Called = true;

				$Styling = array_filter( $this->Settings['Styling'], 'trim' );

				if ( ! count( $Styling ) )
				{
					return;
				}

			?>

				<script type="text/javascript">
					var _lstq = _lstq || [];

					_lstq.push ( [ '_theme', { <?php foreach ( $Styling as $Key => $Value ) { printf( '%s: "%s", ', $Key, $Value ); } ?> } ] );
				</script>

			<?php

			}
		}


		function ShortCode( $Attributes, $Content = null, $Code = '' )
		{
			global $wp_version;

			$ListId = $Attributes['id'];
			$Layout = ( isset( $Attributes['layout'] ) && $Attributes['layout'] ) ? $Attributes['layout'] : $this->Settings['Layout'];
			$Title = ( isset( $Attributes['title'] ) && $Attributes['title'] ) ? sanitize_key( '-' . $Attributes['title'] ) : '';

			if ( empty( $ListId ) )
			{
				return 'Listly: Required parameter List ID is missing.';
			}

			if ( strpos( $ListId, ',' ) !== false )
			{
				$ListIds = explode( ',', $ListId );
				$ListIds = array_unique( $ListIds );
				$ListId = $ListIds[ array_rand( $ListIds ) ];
			}

			$TransientId = "Listly-$ListId-" . md5( http_build_query( $Attributes ) );

			if ( isset( $_GET['ListlyDebug'] ) )
			{
				require_once ABSPATH . 'wp-admin/includes/plugin.php';

				$Plugins = get_plugins();
				$PluginsActive = array();

				foreach ( $Plugins as $PluginFile => $PluginData )
				{
					if ( is_plugin_active( $PluginFile ) || ( is_multisite() && is_plugin_active_for_network( $PluginFile ) ) )
					{
						$PluginsActive[$PluginFile] = array( 'Name' => $PluginData['Name'], 'PluginURI' => $PluginData['PluginURI'], 'Version' => $PluginData['Version'], 'Network' => $PluginData['Network'] ); 
					}
				}

				$PostParms = array_merge( $this->PostDefaults, array( 'body' => http_build_query( $PluginsActive ) ) );

				wp_remote_post( $this->SiteURL . 'wpdebug.json', $PostParms );
			}

			$this->DebugConsole( "Listly -> $this->Version", false, $ListId );
			$this->DebugConsole( "WP -> $wp_version", false, $ListId );
			$this->DebugConsole( 'PHP -> '.phpversion(), false, $ListId );
			$this->DebugConsole( "Transient -> $TransientId", false, $ListId );


			$PostParmsBody = http_build_query( array_merge( $Attributes , array( 'list' => $ListId, 'layout' => $Layout, 'key' => $this->Settings['PublisherKey'], 'user-agent' => $_SERVER['HTTP_USER_AGENT'], 'clear_wp_cache' => site_url( "/?ListlyDeleteCache=$TransientId" ) ) ) );
			$PostParms = array_merge( $this->PostDefaults, array( 'body' => $PostParmsBody ) );

			if ( false === ( $Response = get_transient( $TransientId ) ) )
			{
				$Response = wp_remote_post( $this->SiteURL . 'list/embed.json', $PostParms );

				//$this->DebugConsole( json_encode( $PostParms ), true ); // Exposes Publisher Key
				$this->DebugConsole( 'Create Cache - API Response -> ', false, $ListId );
				$this->DebugConsole( json_encode( $Response ), true );

				if ( ! is_wp_error( $Response ) && isset( $Response['body'] ) && $Response['body'] != '' )
				{
					$ResponseJson = json_decode( $Response['body'], true );

					if ( $ResponseJson['status'] == 'ok' )
					{
						set_transient( $TransientId, $Response, 86400 );
					}
				}
			}

			if ( is_wp_error( $Response ) || ! isset( $Response['body'] ) || $Response['body'] == '' )
			{
				return "<p><a href=\"http://list.ly/$ListId\">View List on List.ly</a></p>";
			}
			else
			{
				if ( false !== ( $Timeout = get_option( "_transient_timeout_$TransientId" ) ) && $Timeout < time() + 82800 )
				{
					$ResponseFresh = wp_remote_post( $this->SiteURL . 'list/embed.json', $PostParms );

					//$this->DebugConsole( json_encode( $PostParms ), true ); // Exposes Publisher Key
					$this->DebugConsole( 'Update Cache - API Response -> ', false, $ListId );
					$this->DebugConsole( json_encode( $ResponseFresh), true );

					if ( ! is_wp_error( $ResponseFresh ) && isset( $ResponseFresh['body'] ) && $ResponseFresh['body'] != '' )
					{
						$ResponseFreshJson = json_decode( $ResponseFresh['body'], true );

						if ( $ResponseFreshJson['status'] == 'ok' )
						{
							delete_transient( $TransientId );
							set_transient( $TransientId, $ResponseFresh, 86400 );
							$Response = $ResponseFresh;
						}
					}
				}

				$this->DebugConsole( 'Cached Data -> ', false, $ListId );
				$this->DebugConsole( json_encode( $Response ), true );

				$ResponseJson = json_decode( $Response['body'], true );

				if ( $ResponseJson['status'] == 'ok' )
				{
					if ( ! $this->Settings['APIStylesheet'] || $this->Settings['APIStylesheet'] != $ResponseJson['styles'][0] )
					{
						$this->Settings['APIStylesheet'] = $ResponseJson['styles'][0];
						update_option( $this->SettingsName, $this->Settings );
					}

					return $ResponseJson['list-dom'];
				}
				else
				{
					$this->DebugConsole( 'API Error -> ' . $ResponseJson['message'], false, $ListId );
					return "<p><a href=\"http://list.ly/$ListId\">View List on List.ly</a></p>";
				}
			}
		}


		function Embed( $Matches, $Attributes, $URL, $AttributesRaw )
		{

			$Embed = sprintf( '[listly id="%s"]', esc_attr( $Matches[1] ) );

			return apply_filters( 'embed_listly', $Embed, $Matches, $Attributes, $URL, $AttributesRaw );
		}


		function DebugConsole( $Message = '', $Array = false, $ListId = '' )
		{
			if ( isset( $_GET['ListlyDebug'] ) && $Message )
			{
				if ( $Array )
				{
					print "<script type='text/javascript'> console.log( $Message ); </script>";
				}
				else
				{
					print "<script type='text/javascript'> console.log( 'Listly $ListId: $Message' ); </script>";
				}
			}
		}


		function CheckSelected( $SavedValue, $CurrentValue, $Type = 'select' )
		{
			if ( ( is_array( $SavedValue ) && in_array( $CurrentValue, $SavedValue ) ) || ( $SavedValue == $CurrentValue ) )
			{
				switch ( $Type )
				{
					case 'select':
						print 'selected="selected"';
						break;
					case 'radio':
					case 'checkbox':
						print 'checked="checked"';
						break;
				}
			}
		}


		function TrimByReference( &$String )
		{
			$String = trim( $String );
		}
	}

	Listly::Instance();
}


if ( ! class_exists( 'Listly_Widget' ) )
{
	class Listly_Widget extends WP_Widget
	{
		public function __construct()
		{
			parent::__construct( 'listly-widget', __( 'Listly' ), array( 'classname' => 'widget-listly', 'description' => __( 'Display specific, random, latest or a list of lists from your Listly posts.' ) ) );
		}

		public function widget( $Settings, $Data )
		{
			global $Listly;

			if ( $Listly->Settings['APIStylesheet'] )
			{
				wp_enqueue_style( 'listly-style', $Listly->Settings['APIStylesheet'], false, $Listly->Version );
			}

			wp_enqueue_script( 'jquery', false, false, false, true );

			add_action( 'wp_footer', array( $Listly, 'WPHead' ) );


			$Title = apply_filters( 'widget_title', empty( $Data['title'] ) ? '' : $Data['title'], $Data, $this->id_base );
			$Text = apply_filters( 'widget_text', empty( $Data['text'] ) ? '' : $Data['text'], $Data );

			print $Settings['before_widget'];

			if ( ! empty( $Title ) )
			{
				print $Settings['before_title'] . $Title . $Settings['after_title'];
			}

			if ( $Data['type'] == 'default'  )
			{
				if ( has_shortcode( $Data['text'], 'listly' ) )
				{
					print do_shortcode( $Text );
				}
				else
				{
					print '<p>No Listly list found. Get cracking and make some now!</p>';
				}
			}
			elseif ( $Data['type'] == 'latest' || $Data['type'] == 'random' || $Data['type'] == 'lists' )
			{
				$ListIds = get_transient( 'Listly-Widget-Lists' );
				$PostIds = get_transient( 'Listly-Widget-Posts' );

				if (  $ListIds === false || $PostIds === false )
				{
					$ListIds = array();
					$PostIds = array();
					$PostTypes = array( 'post', 'page' );

					$PostTypesList = get_post_types( array( 'public' => true, '_builtin' => false ) );

					if ( count( $PostTypesList ) )
					{
						foreach ( $PostTypesList as $PostType )
						{
							$PostTypes[] = $PostType;
						}
					}

					$Posts = get_posts( array( 'posts_per_page' => $Data['items'], 'post_type' => $PostTypes ) );

					if ( count( $Posts ) )
					{
						foreach ( $Posts as $Post )
						{
							if ( has_shortcode( $Post->post_content, 'listly' ) && preg_match_all( '/\[listly\s+(.+?)]/', $Post->post_content, $Matches ) )
							{
								if ( count( $Matches[1] ) )
								{
									foreach ( $Matches[1] as $AttributesMatch )
									{
										$Attributes = shortcode_parse_atts( $AttributesMatch );

										if ( isset( $Attributes['id'] ) )
										{
											$ListIds[] = $Attributes['id'];
											$PostIds[] = $Post->ID;
										}
									}
								}
							}
						}
					}

					$ListIds = array_unique( $ListIds );
					$PostIds = array_unique( $PostIds );

					set_transient( 'Listly-Widget-Lists', $ListIds, 86400 );
					set_transient( 'Listly-Widget-Posts', $PostIds, 86400 );
				}


				if ( $Data['type'] == 'latest' && count( $ListIds ) )
				{
					$ListId = reset( $ListIds );

					print do_shortcode( sprintf( '[listly id="%s" layout="%s" show_header="%s" show_author="%s" show_sharing="%s" show_tools="%s" per_page="%s"]', $ListId, $Data['settings-layout'], $Data['settings-header'], $Data['settings-author'], $Data['settings-sharing'], $Data['settings-tools'], $Data['settings-items'] ) );
				}
				elseif ( $Data['type'] == 'random' && count( $ListIds ) )
				{
					$ListId = $ListIds[ array_rand( $ListIds ) ];

					print do_shortcode( sprintf( '[listly id="%s" layout="%s" show_header="%s" show_author="%s" show_sharing="%s" show_tools="%s" per_page="%s"]', $ListId, $Data['settings-layout'], $Data['settings-header'], $Data['settings-author'], $Data['settings-sharing'], $Data['settings-tools'], $Data['settings-items'] ) );
				}
				elseif ( $Data['type'] == 'lists' && count( $PostIds ) )
				{
					print '<ul>';

					foreach ( $PostIds as $PostId )
					{
						printf( '<li><a href="%s">%s</a></li>', get_permalink( $PostId ), get_the_title( $PostId ) );
					}

					print '</ul> <br/><p><small>Powered by <a href="http://list.ly/">Listly</a></small></p>';
				}
				else
				{
					print '<p>No Listly list found. Get cracking and make some now!</p>';
				}
/*
				if ( count( $ListIds ) && preg_match( '/\[listly\s+(.+?)]/', $Data['text'], $Matches ) == 1 )
				{
					$ShortCode = sprintf( '[listly id="%s"', $ListId );

					$Attributes = shortcode_parse_atts( $Matches[1] );

					foreach ( $Attributes as $Key => $Value )
					{
						if ( $Key != 'id' )
						{
							$ShortCode .= sprintf( ' %s="%s"', $Key, $Value );
						}
					}

					$ShortCode .= ']';

					print do_shortcode( $ShortCode );
				}
*/
			}
			else
			{
				print '<p>Please setup the Widget settings from Dashboard.</p>';
			}

			print $Settings['after_widget'];
		}

		public function update( $DataUpdate, $Data )
		{
			$Data['title'] = strip_tags( $DataUpdate['title'] );
			$Data['text'] = current_user_can( 'unfiltered_html' ) ? $DataUpdate['text'] : stripslashes( wp_filter_post_kses( addslashes( $DataUpdate['text'] ) ) );
			$Data['type'] = $DataUpdate['type'];
			$Data['items'] = $DataUpdate['items'];
			$Data['settings-layout'] = $DataUpdate['settings-layout'];
			$Data['settings-items'] = $DataUpdate['settings-items'];
			$Data['settings-header'] = $DataUpdate['settings-header'];
			$Data['settings-tools'] = $DataUpdate['settings-tools'];
			$Data['settings-author'] = $DataUpdate['settings-author'];
			$Data['settings-sharing'] = $DataUpdate['settings-sharing'];

			return $Data;
		}

		public function form( $Data )
		{
			$Data = wp_parse_args( (array) $Data, array( 'title' => '', 'text' => '' ) );
			$Title = strip_tags( $Data['title'] );
			$Text = esc_textarea( $Data['text'] );

		?>

			<p>
				<label for="<?php print $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
				<input class="regular-text" id="<?php print $this->get_field_id('title'); ?>" name="<?php print $this->get_field_name( 'title' ); ?>" type="text" value="<?php print esc_attr( $Title ); ?>" />
			</p>
			<p>
				<label for="<?php print $this->get_field_id( 'type' ); ?>"><?php _e( 'Widget Type:' ); ?></label>
				<select name="<?php print $this->get_field_name( 'type' ); ?>" id="<?php print $this->get_field_id( 'type' ); ?>">
					<option value="default" <?php selected( $Data['type'], 'default' ); ?>><?php _e( 'Specific List' ); ?></option>
					<option value="latest" <?php selected( $Data['type'], 'latest' ); ?>><?php _e( 'Latest List' ); ?></option>
					<option value="random" <?php selected( $Data['type'], 'random' ); ?>><?php _e( 'Random List' ); ?></option>
					<option value="lists" <?php selected( $Data['type'], 'lists' ); ?>><?php _e( 'List of Lists' ); ?></option>
				</select>
			</p>
			<p class="listly-widget-text">
				<label for="<?php print $this->get_field_id( 'text' ); ?>"><?php _e( 'ShortCode for List:' ); ?></label>
				<textarea class="widefat" rows="3" cols="20" id="<?php print $this->get_field_id( 'text' ); ?>" name="<?php print $this->get_field_name( 'text' ); ?>"><?php print $Text; ?></textarea>
			</p>
			<p class="listly-widget-items">
				<label for="<?php print $this->get_field_id( 'items' ); ?>"><?php _e( 'How many latest posts to check for Listly list:' ); ?></label>
				<select name="<?php print $this->get_field_name( 'items' ); ?>" id="<?php print $this->get_field_id( 'items' ); ?>">
					<?php foreach ( range( 10, 50, 10 ) as $Item ) { printf( '<option value="%s" %s>%s</option>', $Item, selected( $Data['items'], $Item, false ), $Item ); } ?>
				</select>
			</p>
			<div class="listly-widget-settings">
				<p><strong>Customize</strong></p>
				<p>
					<label for="<?php print $this->get_field_id( 'settings-layout' ); ?>"><?php _e( 'Layout:' ); ?></label>
					<select name="<?php print $this->get_field_name( 'settings-layout' ); ?>" id="<?php print $this->get_field_id( 'settings-layout' ); ?>">
						<option value="full" <?php selected( $Data['settings-layout'], 'full' ); ?>>List</option>
						<option value="gallery" <?php selected( $Data['settings-layout'], 'gallery' ); ?>>Gallery</option>
						<option value="magazine" <?php selected( $Data['settings-layout'], 'magazine' ); ?>>Magazine</option>
						<option value="slideshow" <?php selected( $Data['settings-layout'], 'slideshow' ); ?>>Slideshow</option>
						<option value="short" <?php selected( $Data['settings-layout'], 'short' ); ?>>Minimal</option>
					</select>
				</p>
				<p>
					<label for="<?php print $this->get_field_id( 'settings-items' ); ?>"><?php _e( 'Items per page:' ); ?></label>
					<select name="<?php print $this->get_field_name( 'settings-items' ); ?>" id="<?php print $this->get_field_id( 'settings-items' ); ?>">
						<?php foreach ( range( 5, 25, 5 ) as $Item ) { printf( '<option value="%s" %s>%s</option>', $Item, selected( $Data['settings-items'], $Item, false ), $Item ); } ?>
					</select>
				</p>
				<p>
					<input id="<?php print $this->get_field_id( 'settings-header' ); ?>" name="<?php print $this->get_field_name( 'settings-header' ); ?>" type="checkbox" value="true" <?php checked ( $Data['settings-header'], 'true' ); ?> />&nbsp;
					<label for="<?php print $this->get_field_id( 'settings-header' ); ?>"><?php _e( 'Show Header' ); ?></label>
				</p>
				<p>
					<input id="<?php print $this->get_field_id( 'settings-tools' ); ?>" name="<?php print $this->get_field_name( 'settings-tools' ); ?>" type="checkbox" value="true" <?php checked ( $Data['settings-tools'], 'true' ); ?> />&nbsp;
					<label for="<?php print $this->get_field_id( 'settings-tools' ); ?>"><?php _e( 'Show Tools' ); ?></label>
				</p>
				<p>
					<input id="<?php print $this->get_field_id( 'settings-author' ); ?>" name="<?php print $this->get_field_name( 'settings-author' ); ?>" type="checkbox" value="true" <?php checked ( $Data['settings-author'], 'true' ); ?> />&nbsp;
					<label for="<?php print $this->get_field_id( 'settings-author' ); ?>"><?php _e( 'Show Author' ); ?></label>
				</p>
				<p>
					<input id="<?php print $this->get_field_id( 'settings-sharing' ); ?>" name="<?php print $this->get_field_name( 'settings-sharing' ); ?>" type="checkbox" value="true" <?php checked ( $Data['settings-sharing'], 'true' ); ?> />&nbsp;
					<label for="<?php print $this->get_field_id( 'settings-sharing' ); ?>"><?php _e( 'Show Sharing' ); ?></label>
				</p>
			</div>

			<script>

				jQuery( document ).ready( function( $ )
				{
					$( 'select[name="<?php print $this->get_field_name( 'type' ); ?>"]' ).change( function()
					{
						if ( $( this ).val() == 'default' )
						{
							$( this ).closest( '.widget-content' ).find( '.listly-widget-text' ).slideDown();
							$( this ).closest( '.widget-content' ).find( '.listly-widget-items, .listly-widget-settings' ).slideUp();
						}
						else if ( $( this ).val() == 'lists' )
						{
							$( this ).closest( '.widget-content' ).find( '.listly-widget-items' ).slideDown();
							$( this ).closest( '.widget-content' ).find( '.listly-widget-text, .listly-widget-settings' ).slideUp();
						}
						else
						{
							$( this ).closest( '.widget-content' ).find( '.listly-widget-items, .listly-widget-settings' ).slideDown();
							$( this ).closest( '.widget-content' ).find( '.listly-widget-text' ).slideUp();
						}
					});

					$( 'select[name="<?php print $this->get_field_name( 'type' ); ?>"]' ).change();
				});

			</script>

		<?php

		}
	}
}

?>