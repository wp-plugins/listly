<?php
/*
	Plugin Name: List.ly
	Plugin URI:  http://wordpress.org/extend/plugins/listly/
	Description: Plugin to easily integrate List.ly lists to Posts and Pages. It allows publishers to add/edit lists, add items to list and embed lists using shortcode. <a href="mailto:support@list.ly">Contact Support</a>
	Version:     1.1
	Author:      Milan Kaneria
	Author URI:  mailto:milanmk@yahoo.com
*/


if (!class_exists('Listly'))
{
	class Listly
	{
		function __construct()
		{
			$this->PluginFile = __FILE__;
			$this->PluginName = 'Listly';
			$this->PluginPath = dirname($this->PluginFile) . '/';
			$this->PluginURL = get_bloginfo('wpurl') . '/wp-content/plugins/' . dirname(plugin_basename($this->PluginFile)) . '/';
			$this->SettingsURL = 'options-general.php?page='.dirname(plugin_basename($this->PluginFile)).'/'.basename($this->PluginFile);
			$this->SettingsName = 'Listly';
			$this->Settings = get_option($this->SettingsName);
			$this->SiteURL = 'http://api.list.ly/';

			$this->SettingsDefaults = array(
				'PublisherKey' => '',
				'Theme' => 'light',
			);

			$this->PostDefaults = array(
				'method' => 'POST',
				'timeout' => 5,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking' => true,
				'headers' => array('Content-Type' => 'application/json'),
				'body' => array(),
				'cookies' => array()
			);


			register_activation_hook($this->PluginFile, array(&$this, 'Activate'));

			add_filter('plugin_action_links', array(&$this, 'ActionLinks'), 10, 2);
			add_filter('contextual_help', array(&$this, 'ContextualHelp'), 10, 3);
			add_action('admin_menu', array(&$this, 'AdminMenu'));
			add_action('wp_head', array(&$this, 'WPHead'));
			add_action('wp_ajax_AJAXPublisherAuth', array(&$this, 'AJAXPublisherAuth'));
			add_action('wp_ajax_AJAXListAdd', array(&$this, 'AJAXListAdd'));
			add_action('wp_ajax_AJAXListInfo', array(&$this, 'AJAXListInfo'));
			add_action('wp_ajax_AJAXItemAdd', array(&$this, 'AJAXItemAdd'));
			add_action('wp_ajax_AJAXItemInfo', array(&$this, 'AJAXItemInfo'));
			add_shortcode('listly', array(&$this, 'ShortCode'));

			if ($this->Settings['PublisherKey'] == '')
			{
				add_action('admin_notices', create_function('', "print '<div class=\'error\'><p><strong>$this->PluginName:</strong> Please enter Publisher Key on <a href=\'$this->SettingsURL\'>Settings</a> page.</p></div>';"));
			}
		}


		function Activate()
		{
			if (is_array($this->Settings))
			{
				$Settings = array_merge($this->SettingsDefaults, $this->Settings);
				$Settings = array_intersect_key($Settings, $this->SettingsDefaults);

				update_option($this->SettingsName, $Settings);
			}
			else
			{
				add_option($this->SettingsName, $this->SettingsDefaults);
			}
		}


		function ActionLinks($Links, $File)
		{
			static $FilePlugin;

			if (!$FilePlugin)
			{
				$FilePlugin = plugin_basename($this->PluginFile);
			}
	
			if ($File == $FilePlugin)
			{
				$Link = "<a href='$this->SettingsURL'>Settings</a>";

				array_push($Links, $Link);
			}

			return $Links;
		}


		function ContextualHelp($Help, $ScreenId, $Screen)
		{
			global $ListlyPageSettings;

			if ($ScreenId == $ListlyPageSettings)
			{
				$Help = '<a href="mailto:support@list.ly">Contact Support</a>';
			}

			return $Help;
		}


		function AdminPrintScripts()
		{

		?>

			<script type="text/javascript">
				var ListlyNounce = '<?php print wp_create_nonce('ListlyNounce'); ?>';
				var ListlyURL = '<?php print $this->SiteURL; ?>';
			</script>

		<?php

			wp_enqueue_script('jquery');
			wp_enqueue_script('jquery-ui-core');
			wp_enqueue_script('jquery-ui-widget');
			wp_enqueue_script('jquery-ui-mouse');
			wp_enqueue_script('jquery-ui-position');
			wp_enqueue_script('jquery-ui-draggable');
			wp_enqueue_script('jquery-ui-button');
			wp_enqueue_script('jquery-ui-dialog');
			wp_enqueue_script('listly-script', $this->PluginURL.'script.js', false, '1.0', false);
		}

		function AdminPrintStyles()
		{
			wp_enqueue_style('listly-style-jquery-ui', $this->PluginURL.'style.jquery.ui.css', false, '1.8.14', 'screen');
			wp_enqueue_style('listly-style', $this->PluginURL.'style.css', false, '1.0', 'screen');
		}


		function AdminMenu()
		{
			global $ListlyPageSettings;

			$ListlyPageSettings = add_submenu_page('options-general.php', 'Listly &rsaquo; Settings', 'Listly', 'manage_options', $this->PluginFile, array(&$this, 'Admin'));

			add_action("admin_print_scripts", array(&$this, 'AdminPrintScripts'));
			add_action("admin_print_styles", array(&$this, 'AdminPrintStyles'));

			add_meta_box('ListlyMetaBox', 'Listly', array(&$this, 'MetaBox'), 'page', 'side', 'default');
			add_meta_box('ListlyMetaBox', 'Listly', array(&$this, 'MetaBox'), 'post', 'side', 'core');
		}


		function Admin()
		{
			if (!current_user_can('manage_options'))
			{
				wp_die(__('You do not have sufficient permissions to access this page.'));
			}

			if (isset($_POST['action']) && $_POST['action'] == 'ListlySaveSettings')
			{
				if (wp_verify_nonce($_POST['nonce'], $this->SettingsName))
				{
					foreach ($_POST as $Key => $Value)
					{
						if (array_key_exists($Key, $this->SettingsDefaults))
						{
							if (is_array($Value))
							{
								array_walk_recursive($Value, array(&$this, 'TrimByReference'));
							}
							else
							{
								$Value = trim($Value);
							}

							$Settings[$Key] = $Value;
						}
					}

					if (update_option($this->SettingsName, $Settings))
					{
						print '<div class="updated"><p><strong>Settings saved.</strong></p></div>';
					}
				}
				else
				{
					print '<div class="error"><p><strong>Security check failed! Settings not saved.</strong></p></div>';
				}
			}

			$Settings = get_option($this->SettingsName);

		?>

			<style type="text/css">

				input.large-text
				{
					width: 98%;
				}

			</style>

			<div class="wrap">

				<h2>Listly Settings</h2>

				<form method="post" action="">

					<table class="form-table listly-table">

						<tr valign="top">
							<th scope="row">
								Publisher Key
								<br /> <a target="_blank" href="<?php print $this->SiteURL; ?>publishers/landing">Request Publisher Key</a>
							</th>
							<td>
								<input name="PublisherKey" type="text" value="<?php print $Settings['PublisherKey']; ?>" class="large-text" />
								<a id="ListlyAdminAuthStatus" href="#">Check Key Status</a> : <span></span>
							</td>
						</tr>

						<tr valign="top">
							<th scope="row">Theme</th>
							<td>
								<select name="Theme">
									<option value="light" <?php $this->CheckSelected($Settings['Theme'], 'light'); ?>>Light</option>
									<option value="dark" <?php $this->CheckSelected($Settings['Theme'], 'dark'); ?>>Dark</option>
								</select>
							</td>
						</tr>

					</table>

					<input name="nonce" type="hidden" value="<?php print wp_create_nonce($this->SettingsName); ?>" />
					<input name="action" type="hidden" value="ListlySaveSettings" />

					<div class="submit"><input name="" type="submit" value="Save Settings" class="button-primary" /></div>

				</form>

			</div>

		<?php

		}


		function AJAXPublisherAuth()
		{
			if (!wp_verify_nonce($_POST['nounce'], 'ListlyNounce'))
			{
				print '<span class="error">Authorisation failed.</span>';
				exit;
			}

			if (isset($_POST['action'], $_POST['Key']))
			{
				if ($_POST['Key'] == '')
				{
					print '<span class="error">Please enter Publisher Key.</span>';
				}
				else
				{
					$PostParms = array_merge($this->PostDefaults, array('body' => json_encode(array('key' => $_POST['Key']))));

					$Response = wp_remote_post($this->SiteURL.'v1/publisher/auth.json', $PostParms);

					if (is_wp_error($Response) || !isset($Response['body']) || $Response['body'] == '')
					{
						print '<span class="error">No connectivity or Listly service not available. Try later.</span>';
					}
					else
					{
						$ResponseJson = json_decode($Response['body'], true);

						if ($ResponseJson['status'] == 'ok')
						{
							print '<span class="info">'.$ResponseJson['message'].'</span>';
						}
						else
						{
							print '<span class="error">'.$ResponseJson['message'].'</span>';
						}
					}
				}
			}

			exit;
		}


		function AJAXListAdd()
		{
			if (!wp_verify_nonce($_POST['nounce'], 'ListlyNounce'))
			{
				print "<div class='ui-state ui-state-error ui-corner-all'><p>Authorisation failed!</p></div>";
				exit;
			}

			if (isset($_POST['action'], $_POST['ListlyList']))
			{
				$_POST['ListlyList']['commentable'] = isset($_POST['ListlyList']['commentable']) ? $_POST['ListlyList']['commentable'] : 'false';
				$_POST['ListlyList']['moderate_items'] = isset($_POST['ListlyList']['moderate_items']) ? $_POST['ListlyList']['moderate_items'] : 'false';
				$_POST['ListlyList']['guest_participation'] = isset($_POST['ListlyList']['guest_participation']) ? $_POST['ListlyList']['guest_participation'] : 'false';
				$_POST['ListlyList']['no_dislike'] = isset($_POST['ListlyList']['no_dislike']) ? $_POST['ListlyList']['no_dislike'] : 'false';

				$PostParms = array_merge($this->PostDefaults, array('body' => json_encode(array('list' => $_POST['ListlyList'], 'key' => $this->Settings['PublisherKey']))));

				$Response = wp_remote_post($this->SiteURL.'v1/list/create.json', $PostParms);
//print '<pre>'; print_r ($Response); print '</pre>'; exit;

				if (is_wp_error($Response) || !isset($Response['body']) || $Response['body'] == '')
				{
					print json_encode(array('message' => '<span class="error">No connectivity or Listly service not available. Try later.</span>'));
				}
				else
				{
					print $Response['body'];
				}
			}
			else
			{

		?>

				<div class="listly-wrap listly-wrap-list-add">
					<form class="listly-form listly-form-list-add" method="post" action="" accept-charset="utf-8">
						<div style="height: 430px; overflow: auto;">
						<p class="listly-form-message listly-form-message-list-add strong"></p>
						<p>
							<label>Title</label>
							<input type="text" name="ListlyList[title]" maxlength="50" class="large-text" />
							<span class="description">Between 5-50 characters</span>
						</p>
						<p>
							<label>Description</label>
							<textarea name="ListlyList[description]" maxlength="512" class="large-text"></textarea>
							<span class="description">Up to 512 characters</span>
						</p>
						<p>
							<label>Tags</label>
							<input type="text" name="ListlyList[tag_list]" maxlength="40" class="large-text" />
							<span class="description">Separate tags with commas</span>
						</p>
						<p>
							<label>Credits</label>
							<input type="text" name="ListlyList[source]" maxlength="255" class="large-text" />
							<span class="description">Link to original content, if any</span>
						</p>
						<p>
							<label>
								<input type="checkbox" value="true" name="ListlyList[commentable]" checked="checked" />
								Allow Comments
							</label>
							<label>
								<input type="checkbox" value="true" name="ListlyList[moderate_items]" />
								Moderate Items
							</label>
							<label>
								<input type="checkbox" value="true" name="ListlyList[guest_participation]" checked="checked" />
								Allow Guest Participation
							</label>
							<label>
								<input type="checkbox" value="true" name="ListlyList[no_dislike]" />
								Disable Dislikes
							</label>
						</p>
						<input type="hidden" name="nounce" value="<?php print wp_create_nonce('ListlyNounce'); ?>" />
						<input type="hidden" name="action" value="AJAXListAdd" />
						</div>
						<div class="listly-form-submit">
							<hr />
							<button class="ui-button ui-button-text-only ui-widget ui-state-default ui-corner-all button-primary">
								<span class="ui-button-text">Start List</span>
							</button>
						</div>
					</form>
				</div>

		<?php

			}

			exit;
		}


		function AJAXListInfo()
		{
			if (!wp_verify_nonce($_POST['nounce'], 'ListlyNounce'))
			{
				print "<div class='ui-state ui-state-error ui-corner-all'><p>Authorisation failed!</p></div>";
				exit;
			}

			$ListId = isset($_POST['ListId']) ? $_POST['ListId'] : '';
			$Message = isset($_POST['Message']) ? $_POST['Message'] : '';

		?>

			<div class="listly-wrap listly-wrap-list-info">

			<?php

				$PostParms = array_merge($this->PostDefaults, array('body' => json_encode(array('id' => $ListId, 'key' => $this->Settings['PublisherKey']))));

				$Response = wp_remote_post($this->SiteURL.'v1/list/info', $PostParms);

				if (is_wp_error($Response) || !isset($Response['body']) || $Response['body'] == '')
				{
					print "<div class='ui-state ui-state-error ui-corner-all'><p>No connectivity or Listly service not available. Try later.</p></div>";
				}
				else
				{
					$ResponseJson = json_decode($Response['body'], true);

					if ($ResponseJson['status'] == 'ok')
					{
						$List = json_decode($ResponseJson['list'], true);

						?>

							<div>
								<a style="float: right; margin-top: -15px; color: #0D0DFE; font-size: 11px;" target="_blank" href="<?php print $this->SiteURL; ?>list/<?php print $ListId; ?>">Preview/Edit on Listly</a>
								<h2 style="margin-bottom: 0px;"><?php print $List['title']; ?></h2>
								<p style="margin-top: 5px;"><?php print $List['description']; ?></p>
							</div>

							<hr />

							<div style="clear: both;" class="listly-wrap listly-wrap-list-info-box">
							<div style="height: 350px; overflow: auto;">

								<div class='ui-state ui-state-highlight ui-corner-all'><p><?php print $Message; ?></p></div>

								<!--<p><a style="padding: 5px 10px;" class="ListlyAdminListAddItems ui-button ui-corner-all" href="#" data-Id="<?php print $ListId; ?>">+ Add an item to this list</a></p>-->
								<p><a class="ListlyAdminListAddItems" href="#" data-Id="<?php print $ListId; ?>"><img src="<?php print $this->PluginURL.'images/img-additem.png'; ?>" alt="" /></a></p>

								<?php

									if ($List['items'] != '')
									{
										$ListItems = json_decode($List['items'], true);

										$Count = 1;

										foreach ($ListItems as $ListItem)
										{
											print "<p><span class='strong'>$Count. {$ListItem['name']}</span> - {$ListItem['note']}</p>";

											$Count++;
										}
									}

								?>

								</div>

								<div class="listly-form-submit">
									<hr />
									<button class="ui-button ui-button-text-only ui-widget ui-state-active ui-corner-all button-secondary">
										<span class="ui-button-text">Done</span>
									</button>
								</div>

							<div>

						<?php

					}
					else
					{
						print "<div class='ui-state ui-state-error ui-corner-all'><p>{$ResponseJson['message']}</p></div>";
					}
				}

			?>

			</div>

		<?php

			exit;
		}


		function AJAXItemAdd()
		{
			if (!wp_verify_nonce($_POST['nounce'], 'ListlyNounce'))
			{
				print "<div class='ui-state ui-state-error ui-corner-all'><p>Authorisation failed!</p></div>";
				exit;
			}

			$ListId = isset($_POST['ListId']) ? $_POST['ListId'] : '';

			if (isset($_POST['action'], $_POST['ListlyItem']))
			{
				if (isset($_POST['ListlyItem']['image']) && $_POST['ListlyItem']['image'] == '')
				{
					unset($_POST['ListlyItem']['image']);
				}

				$PostParms = array_merge($this->PostDefaults, array('body' => json_encode(array('list' => $ListId, 'item' => $_POST['ListlyItem'], 'key' => $this->Settings['PublisherKey']))));

				$Response = wp_remote_post($this->SiteURL.'v1/item/create.json', $PostParms);
//trigger_error('Response - ' . var_export($Response, true), E_USER_WARNING);

				if (is_wp_error($Response) || !isset($Response['body']) || $Response['body'] == '')
				{
					print json_encode(array('message' => '<span class="error">No connectivity or Listly service not available. Try later.</span>'));
				}
				else
				{
					print $Response['body'];
				}
			}
			else
			{

		?>

				<form class="listly-form listly-form-item-add" method="post" action="" accept-charset="utf-8">
					<div style="height: 350px; overflow: auto;">
					<h3 style="margin: 0px auto 10px;">Add Item</h3>
					<p class="listly-form-message listly-form-message-item-add strong"></p>
					<p>
						<label>Item Name</label>
						<input type="text" name="ListlyItem[name]" maxlength="50" class="large-text" />
						<span class="description">Between 1-50 characters</span>
					</p>
					<p>
						<label>URL for Item</label>
						<input type="text" name="ListlyItem[url]" class="large-text" />
						<span class="description">Enter an URL. We'll grab the description for you</span>
					</p>
					<p>
						<label>Image for Item</label>
						<input type="text" name="ListlyItem[image]" class="large-text" />
						<span class="description">Enter an URL.</span>
					</p>
					<p>
						<label>Add a Description</label>
						<textarea name="ListlyItem[note]" rows="2" maxlength="1024" class="large-text"></textarea>
						<span class="description">Optional</span>
					</p>
					<input type="hidden" name="nounce" value="<?php print wp_create_nonce('ListlyNounce'); ?>" />
					<input type="hidden" name="ListId" value="<?php print $ListId; ?>" />
					<input type="hidden" name="action" value="AJAXItemAdd" />
					</div>
					<div class="listly-form-submit">
						<hr />
						<button style="margin-left: 30px;" class="ui-button ui-button-text-only ui-widget ui-state-default ui-corner-all button-primary" data-Id="<?php print $ListId; ?>">
							<span class="ui-button-text">Add Item</span>
						</button>
						<button class="ui-button ui-button-text-only ui-widget ui-state-active ui-corner-all button-secondary">
							<span class="ui-button-text">Cancel</span>
						</button>
					</div>
				</form>

		<?php

			}

			exit;
		}


		function AJAXItemInfo()
		{
			if (!wp_verify_nonce($_POST['nounce'], 'ListlyNounce'))
			{
				print "<div class='ui-state ui-state-error ui-corner-all'><p>Authorisation failed!</p></div>";
				exit;
			}

			$ListId = isset($_POST['ListId']) ? $_POST['ListId'] : '';

			$PostParms = array_merge($this->PostDefaults, array('body' => json_encode(array('id' => $ListId, 'key' => $this->Settings['PublisherKey']))));

			$Response = wp_remote_post($this->SiteURL.'v1/list/info', $PostParms);

			if (is_wp_error($Response) || !isset($Response['body']) || $Response['body'] == '')
			{
				print "<div class='ui-state ui-state-error ui-corner-all'><p>No connectivity or Listly service not available. Try later.</p></div>";
			}
			else
			{
				$ResponseJson = json_decode($Response['body'], true);

				if ($ResponseJson['status'] == 'ok')
				{
					$List = json_decode($ResponseJson['list'], true);

					print '<div style="height: 350px; overflow: auto;">';
					print "<div class='ui-state ui-state-highlight ui-corner-all'><p>Add more items or click done to finish.</p></div>";
					//print "<p><a style='padding: 5px 10px;' class='ListlyAdminListAddItems ui-button ui-corner-all' href='#' data-Id='$ListId'>+ Add an item to this list</a></p>";
					print "<p><a class='ListlyAdminListAddItems' href='#' data-Id='$ListId'><img src='{$this->PluginURL}images/img-additem.png' alt='' /></a></p>";

					if ($List['items'] != '')
					{
						$ListItems = json_decode($List['items'], true);

						$Count = 1;

						foreach ($ListItems as $ListItem)
						{
							print "<p><span class='strong'>$Count. {$ListItem['name']}</span> - {$ListItem['note']}</p>";

							$Count++;
						}
					}

					?>
						</div>

						<div class="listly-form-submit">
							<hr />
							<button class="ui-button ui-button-text-only ui-widget ui-state-active ui-corner-all button-secondary">
								<span class="ui-button-text">Done</span>
							</button>
						</div>

					<?php

				}
				else
				{
					print "<div class='ui-state ui-state-error ui-corner-all'><p>{$ResponseJson['message']}</p></div>";
				}
			}

			exit;
		}


		function MetaBox()
		{
			if ($this->Settings['PublisherKey'] == '')
			{
				print "<p>Please enter Publisher Key on <a href='$this->SettingsURL'>Settings</a> page.</p>";
			}
			else
			{
				$PostParms = array_merge($this->PostDefaults, array('body' => json_encode(array('key' => $this->Settings['PublisherKey']))));

				$Response = wp_remote_post($this->SiteURL.'v1/publisher/lists', $PostParms);

				if (is_wp_error($Response) || !isset($Response['body']) || $Response['body'] == '')
				{
					print '<p class="error">No connectivity or Listly service not available. Try later.</p>';
				}
				else
				{
					$ResponseJson = json_decode($Response['body'], true);

					if ($ResponseJson['status'] == 'ok')
					{
						$Lists = json_decode($ResponseJson['lists'], true);

					?>

						<p><strong>List</strong></p>
						<p><a id="ListlyAdminListAdd" href="#">Make New List</a></p>

						<p><strong>Your Lists</strong></p>
						<div id="ListlyAdminYourList" style="height: 250px; overflow: auto;">
							<?php foreach ($Lists as $Key => $List) : ?>
								<p><?php print $List['title']; ?><br />
								<a class="ListlyAdminListEmbed" href="#" data-Id="<?php print $List['list_id']; ?>">ShortCode</a>
								<a class="ListlyAdminListInfo" href="#" data-Id="<?php print $List['list_id']; ?>">Edit</a></p>
							<?php endforeach; ?>
						</div>

					<?php

					}
					else
					{
						print "<div class='ui-state ui-state-error ui-corner-all'><p>{$ResponseJson['message']} Enter a valid key under <a href='$this->SettingsURL'>Settings</a>.</p></div>";
					}
				}

				?>

		<?php

			}
		}


		function WPHead()
		{
			wp_enqueue_script('jquery');
			//wp_enqueue_script($this->SettingsName, $this->PluginURL.'scripts.js', array('jquery'), '1.0', false);
			//wp_enqueue_style($this->SettingsName, $this->PluginURL.'style.css', false, '1.0', 'screen');
		}


		function ShortCode($Attributes, $Content = null, $Code = '')
		{
			$ListId = $Attributes['id'];
			$Theme = isset($Attributes['theme']) ? $Attributes['theme'] : $this->Settings['Theme'];

			if (empty($ListId))
			{
				return 'List ID not supplied.';
			}

			$PostParms = array_merge($this->PostDefaults, array('body' => json_encode(array('list' => $ListId, 'theme' => $Theme, 'key' => $this->Settings['PublisherKey'], 'user-agent' => $_SERVER['HTTP_USER_AGENT']))));

			$Response = wp_remote_post($this->SiteURL.'v1/list/embed.json', $PostParms);

			if (is_wp_error($Response) || !isset($Response['body']) || $Response['body'] == '')
			{
				return 'Listly error!';
			}
			else
			{
				$ResponseJson = json_decode($Response['body'], true);

				if ($ResponseJson['status'] == 'ok')
				{
					return $ResponseJson['list-dom'];
				}
				else
				{
					return $ResponseJson['message'];
				}
			}
		}


		function CheckSelected($SavedValue, $CurrentValue, $Type = 'select')
		{
			if ( (is_array($SavedValue) && in_array($CurrentValue, $SavedValue)) || ($SavedValue == $CurrentValue) )
			{
				switch ($Type)
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


		function TrimByReference(&$String)
		{
			$String = trim($String);
		}
	}

	$Listly = new Listly();
}


?>