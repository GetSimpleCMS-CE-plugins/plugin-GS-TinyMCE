<?php

if(!defined('IN_GS')){ die('You cannot load this file directly.'); }

// plugin id based on filename
$thisfile = basename(__FILE__, '.php');

// ---------- register plugin ----------
register_plugin(
	$thisfile,
	'GS-TinyMCE',
	'1.2',
	'CE Team',
	'https://www.getsimple-ce.ovh/ce-plugins',
	'Replace Pages CKEditor with TinyMCE.',
	'plugins',
	'gs_tinymce'
);

// Add Plugins sidebar link
add_action('plugins-sidebar','createSideMenu',array($thisfile,'TinyMCE Editor <svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle" width="1.5em" height="1.5em" viewBox="0 0 512 512"><rect width="512" height="512" fill="none"/><path fill="#335DFF" d="m512 221.768l-16.778-16.976l-34.794 34.389L259.251 38.956L0 295.246l16.78 16.975l39.813-39.36l201.169 200.183zM259.195 72.574l184.258 183.384L257.82 439.43L73.567 256.082zm61.412 120.453H196.1v-21.004h124.508zm38.514 51.534H157.585v-21.003H359.12zm0 50.785H157.585v-21.003H359.12zm-39.066 50.785H196.651v-21.003h123.404z"/></svg>'));

// ---------- settings storage ----------
$GS_TINYMCE_DATA_DIR = GSDATAOTHERPATH . '/';
$GS_TINYMCE_SETTINGS = $GS_TINYMCE_DATA_DIR . '/gs-tinymce.json';

$default_settings = array(
	'selector' => '#post-body, #post-content, #content, #codetext',
	'sections' => 'edit.php',
	'plugins'  => 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount',
	'toolbar'  => 'undo redo | blocks | bold italic underline strikethrough removeformat | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image hr charmap | fullscreen code preview',
	'menubar'  => true,
	'relative_urls' => false,
	'use_cdn'  => false,
	'cdn_key'  => 'no-api-key',
	'tinymce_version' => '6',
	'language' => 'en',
	'height'   => 540,
	'autoresize' => true,
	'autoresize_bottom_margin' => 50,
	'toolbar_mode' => 'wrap'
);

function gs_tinymce_load_settings(){
	global $GS_TINYMCE_SETTINGS, $default_settings;
	if(!file_exists(dirname($GS_TINYMCE_SETTINGS))){ @mkdir(dirname($GS_TINYMCE_SETTINGS), 0755, true); }
	if(!file_exists($GS_TINYMCE_SETTINGS)){
		file_put_contents($GS_TINYMCE_SETTINGS, json_encode($default_settings, JSON_PRETTY_PRINT));
		return $default_settings;
	}
	$json = @file_get_contents($GS_TINYMCE_SETTINGS);
	$data = @json_decode($json, true);
	if(!is_array($data)) return $default_settings;
	return array_merge($default_settings, $data);
}

function gs_tinymce_save_settings($new){
	global $GS_TINYMCE_SETTINGS;
	@file_put_contents($GS_TINYMCE_SETTINGS, json_encode($new, JSON_PRETTY_PRINT));
}

// ---------- Helper: detect language files ----------
function gs_tinymce_get_languages($version){
	$lang_dir = GSPLUGINPATH . "GS-TinyMCE/tinymce-v{$version}/langs/";
	$languages = array('en'); // always include English

	if(is_dir($lang_dir)){
		foreach(glob($lang_dir . '*.js') as $file){
			$code = basename($file, '.js');
			if($code !== 'en') $languages[] = $code;
		}
	}

	sort($languages);
	return $languages;
}

// ---------- Inject TinyMCE ----------
add_action('common', 'gs_tinymce_detect_edit_page');

function gs_tinymce_detect_edit_page() {
    $settings = gs_tinymce_load_settings();

    // explode user-defined sections
    $sections = array_filter(array_map('trim', explode(',', $settings['sections'])));

    $current = basename($_SERVER['PHP_SELF']);

    // if current admin page is listed, load TinyMCE
    if (in_array($current, $sections)) {
        add_action('footer', 'gs_tinymce_header');
    }
}

function gs_tinymce_header(){
	$settings = gs_tinymce_load_settings();

	$version = isset($settings['tinymce_version']) ? $settings['tinymce_version'] : '6';

	// Build link list from GetSimple pages with categories
	$linkList = array();
	
	// Add "Custom URL" option
	$linkList[] = array('title' => '-- Custom URL --', 'value' => '');
	
	// Get all page files
	$pageFiles = glob(GSDATAPAGESPATH . '*.xml');
	$pages = array();
	
	if ($pageFiles) {
		foreach ($pageFiles as $file) {
			$data = getXML($file);
			if ($data) {
				$pages[] = array(
					'title' => (string)$data->title,
					'url' => (string)$data->url,
					'menuStatus' => (string)$data->menuStatus,
					'menuOrder' => (int)$data->menuOrder
				);
			}
		}
		
		// Sort by menu order
		usort($pages, function($a, $b) {
			return $a['menuOrder'] - $b['menuOrder'];
		});
		
		// Add pages category header
		if (count($pages) > 0) {
			$linkList[] = array('title' => '-- Pages --', 'value' => '', 'menu' => array());
			
			// Build link list for pages
			foreach ($pages as $page) {
				$pageUrl = $GLOBALS['SITEURL'] . ($page['url'] == 'index' ? '' : $page['url']);
				$linkList[] = array(
					'title' => '   ' . $page['title'],
					'value' => $pageUrl
				);
			}
		}
	}
	
	// Add special link types
	$linkList[] = array('title' => '-- Special Links --', 'value' => '');
	$linkList[] = array('title' => '   Email', 'value' => 'mailto:email@example.com');
	$linkList[] = array('title' => '   Phone', 'value' => 'tel:+1234567890');
	$linkList[] = array('title' => '   Anchor', 'value' => '#anchor-name');
	
	$linkListJson = json_encode($linkList);

	// Determine TinyMCE source
	if($settings['use_cdn']){
		$cdn_key = !empty($settings['cdn_key']) ? $settings['cdn_key'] : 'no-api-key';
		$src = "https://cdn.tiny.cloud/1/{$cdn_key}/tinymce/{$version}/tinymce.min.js";
	} else {
		$local = GSPLUGINPATH . "GS-TinyMCE/tinymce-v{$version}/tinymce.min.js";
		if(file_exists($local)){
			$src = $GLOBALS['SITEURL'] . "plugins/GS-TinyMCE/tinymce-v{$version}/tinymce.min.js";
		} else {
			$cdn_key = !empty($settings['cdn_key']) ? $settings['cdn_key'] : 'no-api-key';
			$src = "https://cdn.tiny.cloud/1/{$cdn_key}/tinymce/{$version}/tinymce.min.js";
		}
	}

	// Build plugins
	$plugins_raw = isset($settings['plugins']) ? (string)$settings['plugins'] : '';
	$plugins_raw = trim(preg_replace('/\\s+/', ' ', $plugins_raw));
	$plugins_list = array_filter(array_map('trim', explode(' ', $plugins_raw)));

	$plugins_list = array_values(array_filter($plugins_list, function($p){
		return strtolower($p) !== 'autoresize';
	}));

	if (!empty($settings['autoresize'])) {
		$plugins_list[] = 'autoresize';
	}

	$plugins_string = implode(' ', $plugins_list);

	// Build basic config (without file_picker_callback which can't be JSON encoded)
	$cfg = array(
		'selector' => $settings['selector'],
		'plugins'  => $plugins_string,
		'toolbar'  => $settings['toolbar'],
		'toolbar_mode' => isset($settings['toolbar_mode']) ? $settings['toolbar_mode'] : 'wrap',
		'toolbar_sticky' => true,
		'menubar'  => (bool)$settings['menubar'],
		'promotion'  => false,
		'branding'   => false,
		'language'   => $settings['language'],
		'image_title' => true,
		'image_class_list' => array(
			array('title' => 'None', 'value' => ''),
			array('title' => 'Responsive', 'value' => 'img-responsive'),
			array('title' => 'Fluid', 'value' => 'img-fluid'),
			array('title' => 'Rounded', 'value' => 'rounded'),
			array('title' => 'Circle', 'value' => 'rounded-circle'),
			array('title' => 'Thumbnail', 'value' => 'img-thumbnail'),
			array('title' => 'Float Left', 'value' => 'float-left'),
			array('title' => 'Float Right', 'value' => 'float-right'),
			array('title' => 'Center', 'value' => 'mx-auto d-block')
		),
		'link_title' => true,
		'link_target_list' => array(
			array('title' => 'None', 'value' => ''),
			array('title' => 'New window', 'value' => '_blank'),
			array('title' => 'Same window', 'value' => '_self')
		),
		'extended_valid_elements' => 'img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name|style],a[href|target|title|class|rel]',
		'link_list' => '__GS_LINK_LIST__',  // Placeholder to be replaced
		'quickbars_selection_toolbar' => 'bold italic underline| blocks | quicklink',
		'quickbars_insert_toolbar' => 'quickimage quicktable | hr pagebreak',
		'quickbars_image_toolbar' => 'alignleft aligncenter alignright | imageoptions'
	);

	// Ensure SITEURL has trailing slash for TinyMCE
	$site_url = rtrim($GLOBALS['SITEURL'], '/') . '/';

	// Configure URL handling based on relative_urls setting
	if ($settings['relative_urls']) {
		// When "relative URLs" is enabled, we want root-relative paths starting from site root
		// e.g., /demo/data/uploads/a.jpg (includes the subdirectory if site is in one)
		$cfg['relative_urls'] = false;  // Counter-intuitive but correct!
		$cfg['remove_script_host'] = true;  // Removes domain, keeps path from root
		$cfg['document_base_url'] = $site_url;
		$cfg['convert_urls'] = true;
	} else {
		// When disabled, use absolute URLs with full domain
		// e.g., https://example/data/uploads/a.jpg
		$cfg['relative_urls'] = false;
		$cfg['remove_script_host'] = false;
		$cfg['document_base_url'] = $site_url;
		$cfg['convert_urls'] = true;
	}

	if (!empty($settings['autoresize'])) {
		$cfg['min_height'] = (int)$settings['height'];
		$cfg['autoresize_bottom_margin'] = (int)$settings['autoresize_bottom_margin'];
		$cfg['autoresize_overflow_padding'] = 10;
	} else {
		$cfg['height'] = (int)$settings['height'];
	}

	$json_cfg = json_encode($cfg);
	
	// Replace the placeholder with actual link list
	$json_cfg = str_replace('"__GS_LINK_LIST__"', $linkListJson, $json_cfg);

	echo "\n<!-- GS-TinyMCE plugin -->\n";
	echo '<script src="' . htmlspecialchars((string)$src) . '" referrerpolicy="origin"></script>' . "\n";

	// load language file if exists and not English
	if($settings['language'] !== 'en'){
		$langfile = "plugins/GS-TinyMCE/tinymce-v{$version}/langs/{$settings['language']}.js";
		if(file_exists(GSPLUGINPATH . "GS-TinyMCE/tinymce-v{$version}/langs/{$settings['language']}.js")){
			echo '<script src="' . $GLOBALS['SITEURL'] . $langfile . '"></script>' . "\n";
		}
	}

	$filebrowser_url = $GLOBALS['SITEURL'] . 'plugins/GS-TinyMCE/filebrowser/filebrowser.php';

	echo '
<script>
(function(){
	// File browser callback function for TinyMCE 6
	function tinymceFileBrowser(callback, value, meta) {
		var filetype = meta.filetype || "file";
		var url = "' . $filebrowser_url . '?type=" + filetype;
		
		// Open file browser in a modal
		tinymce.activeEditor.windowManager.openUrl({
			title: "File Browser",
			url: url,
			width: 900,
			height: 600,
			onMessage: function(api, message) {
				if (message.mceAction === "fileSelected") {
					// Call the callback with the selected file
					callback(message.url, {
						text: message.title || message.alt || "",
						alt: message.alt || "",
						title: message.title || ""
					});
					api.close();
				}
			}
		});
	}

	function removeCK(){
		try{
			if(window.CKEDITOR && CKEDITOR.instances){
				for(var i in CKEDITOR.instances){
					try{ CKEDITOR.instances[i].destroy(true); }catch(e){}
				}
			}
			if(window.CKEDITOR){ CKEDITOR.replace = function(){}; }
			document.querySelectorAll("script[src*=\'ckeditor\']").forEach(function(s){ s.remove(); });
		}catch(e){}
	}

	function initTiny(){
		removeCK();
		setTimeout(function(){
			if(typeof tinymce !== "undefined"){
				tinymce.remove();
				
				// Parse the config and add the file_picker_callback
				var config = ' . $json_cfg . ';
				config.file_picker_callback = tinymceFileBrowser;
				
				// Add setup to enable class editing in Advanced tab
				config.setup = function(editor) {
					// Track changes for unsaved notification
					editor.on("init", function() {
						editor.on("change keyup paste", function() {
							try {
								jQuery("#editform #post-content").trigger("change");
								if (typeof pageisdirty !== "undefined") pageisdirty = false;
								if (typeof warnme !== "undefined") warnme = true;
								if (typeof autoSaveInd === "function") autoSaveInd();
							} catch(e) {
								// Silently fail if functions dont exist yet
							}
						});
					});
				};
				
				tinymce.init(config);
			}
		},50);
	}

	if(document.readyState==="complete" || document.readyState==="interactive"){
		initTiny();
	} else {
		document.addEventListener("DOMContentLoaded", initTiny);
	}

	document.addEventListener("pjax:end", initTiny);
	document.addEventListener("ajaxComplete", initTiny);
})();
</script>';
}

// ---------- Settings page ----------
function gs_tinymce(){
	$settings = gs_tinymce_load_settings();
	$langs = gs_tinymce_get_languages($settings['tinymce_version']);

	if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gs_tinymce_save'])){
		$new = array();

		$new['selector'] = trim($_POST['selector']);

		$raw_plugins = isset($_POST['plugins']) ? trim($_POST['plugins']) : '';
		$raw_plugins = preg_replace('/\\s+/', ' ', $raw_plugins);
		$plugins_parts = array_filter(array_map('trim', explode(' ', $raw_plugins)));
		$plugins_parts = array_values(array_filter($plugins_parts, function($p){
			return strtolower($p) !== 'autoresize';
		}));

		$new['plugins'] = implode(' ', $plugins_parts);
		$new['toolbar']  = trim($_POST['toolbar']);
		$new['menubar']  = isset($_POST['menubar']);
		$new['relative_urls'] = isset($_POST['relative_urls']);
		$new['use_cdn']  = isset($_POST['use_cdn']);
		$new['cdn_key']  = trim($_POST['cdn_key']);
		$new['tinymce_version'] = trim($_POST['tinymce_version']);
		$new['language'] = trim($_POST['language']);
		$new['height']   = (int)$_POST['height'];
		$new['autoresize'] = isset($_POST['autoresize']);
		$new['autoresize_bottom_margin'] = (int)$_POST['autoresize_bottom_margin'];
		$new['toolbar_mode'] = trim($_POST['toolbar_mode']);
		$new['sections'] = trim($_POST['sections']);

		gs_tinymce_save_settings($new);
		$settings = $new;
		$langs = gs_tinymce_get_languages($settings['tinymce_version']);

		echo '<div class="updated">Settings saved.</div>';
	}
	
	global $USR;
	global $SITEURL;
	
	echo '<link rel="stylesheet" href="' . $SITEURL . 'plugins/massiveAdmin/css/w3.css"/>';
	
	echo '
<style>
	table tr,td {border-bottom: 0!important; border-top: 0!important;} .w3-btn{margin-right: 5px !important;}
	.w3-parent hr {important;margin: 10px 0!important;} .hide{display:none;}
	.upcke{display:none !important}
</style>';

	echo '
<div class="w3-parent">
	<header class="w3-container w3-border-bottom w3-margin-bottom">
		<h3>GS-TinyMCE Settings <svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle" width="1.5em" height="1.5em" viewBox="0 0 512 512"><rect width="512" height="512" fill="none"/><path fill="#335DFF" d="m512 221.768l-16.778-16.976l-34.794 34.389L259.251 38.956L0 295.246l16.78 16.975l39.813-39.36l201.169 200.183zM259.195 72.574l184.258 183.384L257.82 439.43L73.567 256.082zm61.412 120.453H196.1v-21.004h124.508zm38.514 51.534H157.585v-21.003H359.12zm0 50.785H157.585v-21.003H359.12zm-39.066 50.785H196.651v-21.003h123.404z"/></svg></h3>
		<p>Replace Pages CKEditor with TinyMCE.</p>
	</header>

	<form method="post">
		<table class="form-table">

			<tr>
				<th>Presets</th>
				<td>
					<button type="button" class="w3-btn w3-round-large w3-aqua" onclick="tinymcePreset(\'basic\')">Basic</button>
					
					<button type="button" class="w3-btn w3-round-large w3-blue" onclick="tinymcePreset(\'classic\')">Default</button>
					
					<button type="button" class="w3-btn w3-round-large w3-indigo" onclick="tinymcePreset(\'full\')">Advanced</button>
					
					<button type="button" class="hide w3-btn w3-round-large w3-deep-purple" onclick="tinymcePreset(\'expert\')">Expert <small>(v7+)</small></button>
					
					<br><small>Click to auto-fill the Plugins and Toolbar fields.</small>
				</td>
			</tr>

<script>
function tinymcePreset(type){
	let pluginsField  = document.querySelector(\'input[name="plugins"]\');
	let toolbarField  = document.querySelector(\'input[name="toolbar"]\');

	if(type === \'basic\'){
		pluginsField.value = "link image lists";
		
		toolbarField.value = "undo redo | blocks bold italic | bullist numlist | alignleft aligncenter alignright alignjustify | link image hr";
	}

	if(type === \'classic\'){
		pluginsField.value = "advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount";
		
		toolbarField.value = "undo redo | blocks | bold italic underline strikethrough removeformat | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image hr charmap | fullscreen code preview";
	}

	if(type === \'full\'){
		pluginsField.value = "preview searchreplace autolink code visualblocks visualchars fullscreen image link media codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount charmap quickbars accordion";
		
		toolbarField.value = "undo redo | blocks fontfamily fontsize | bold italic underline strikethrough removeformat | forecolor backcolor | link image media | align numlist bullist | accordion accordionremove | lineheight outdent indent | hr pagebreak anchor | table tabledelete | charmap code fullscreen | print preview";
	}

	if(type === \'expert\'){
		pluginsField.value = "tableofcontents powerpaste formatpainter footnotes export editimage checklist casechange advtable a11ychecker preview searchreplace autolink advcode visualblocks visualchars fullscreen image link media codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount charmap quickbars accordion";
		
		toolbarField.value = "a11ycheck | undo redo | blocks fontfamily fontsize | bold italic underline strikethrough removeformat casechange | link image media | hr pagebreak anchor | align numlist bullist checklist | table tabledelete tableofcontents footnotes | accordion accordionremove | outdent indent lineheight| formatpainter forecolor backcolor | charmap code | fullscreen print export preview";
	}
}
</script>

			<tr>
				<th>Plugins</th>
				<td>
					<input class="w3-input w3-border w3-round" type="text" name="plugins" value="' . htmlspecialchars($settings['plugins']) . '" style="width:95%"/>
				</td>
			</tr>

			<tr>
				<th>Toolbar</th>
				<td>
					<input class="w3-input w3-border w3-round" type="text" name="toolbar" value="' . htmlspecialchars($settings['toolbar']) . '" style="width:95%"/>
				</td>
			</tr>

			<tr>
				<th>Toolbar Mode</th>
				<td>
				<select class="w3-input w3-border w3-round" name="toolbar_mode" style="width:30%">
					<option value="wrap"     '.($settings['toolbar_mode']=='wrap'?'selected':'').'>Wrap (default)</option>
					<option value="sliding"  '.($settings['toolbar_mode']=='sliding'?'selected':'').'>Sliding</option>
					<option value="floating" '.($settings['toolbar_mode']=='floating'?'selected':'').'>Floating</option>
					<option value="scrolling" '.($settings['toolbar_mode']=='scrolling'?'selected':'').'>Scrolling</option>
				</select>
				</td>
			</tr>

			<tr>
				<th>Enable Auto-resize</th>
				<td>
					<input class="w3-check" type="checkbox" name="autoresize" ' . ($settings['autoresize']?'checked':'') . ' />
				</td>
			</tr>

			<tr>
			<th>Show menubar</th>
				<td>
					<input class="w3-check" type="checkbox" name="menubar" ' . ($settings['menubar']?'checked':'') . ' />
				</td>
			</tr>

			<tr>
				<th>Relative URLs</th>
				<td>
					<input class="w3-check" type="checkbox" name="relative_urls" ' . ($settings['relative_urls']?'checked':'') . ' />
				</td>
			</tr>

			<tr>
				<td colspan="2"><hr></td>
			</tr>

			<tr>
				<th>Language</th>
				<td>
					<select class="w3-input w3-border w3-round" name="language" style="width:20%">';
					foreach($langs as $code){
						echo '<option value="'.$code.'" '.($settings['language']==$code?'selected':'').'>'.$code.'</option>';
					}
					echo '</select>
				</td>
			</tr>

			<tr>
				<th>Editor Minimum Height</th>
				<td>
					<input class="w3-input w3-border w3-round" type="number" name="height" value="' . htmlspecialchars($settings['height']) . '" style="width:20%"/>
				</td>
			</tr>

			<tr>
				<th>Bottom Margin</th>
				<td>
					<input class="w3-input w3-border w3-round" type="number" name="autoresize_bottom_margin" value="' . htmlspecialchars($settings['autoresize_bottom_margin']) . '" style="width:20%"/>
				</td>
			</tr>

			<tr class="hide"><td colspan="2"><hr></td></tr>
			
			<tr class="hide">
				<th>Sections</th>
				<td>
					<input class="w3-input w3-border w3-round" type="text" name="sections" value="' . htmlspecialchars($settings['sections']) . '" style="width:95%"/>
				</td>
			</tr>
			
			<tr class="hide">
				<th>Selector</th>
				<td>
					<input class="w3-input w3-border w3-round" type="text" name="selector" value="' . htmlspecialchars($settings['selector']) . '" style="width:95%"/>
				</td>
			</tr>
			
			<tr><td colspan="2"><hr></td></tr>

			<tr>
				<th>TinyMCE Version</th>
				<td>
					<input class="w3-input w3-border w3-round" type="number" name="tinymce_version" value="' . htmlspecialchars($settings['tinymce_version']) . '" style="width:20%"/>
				</td>
			</tr>

			<tr>
				<th>Use CDN</th>
				<td>
					<input class="w3-check" type="checkbox" name="use_cdn" ' . ($settings['use_cdn']?'checked':'') . ' />
				</td>
			</tr>

			<tr>
				<th>CDN API Key</th>
				<td>
					<input class="w3-input w3-border w3-round" type="text" name="cdn_key" value="' . htmlspecialchars($settings['cdn_key']) . '" style="width:95%"/>
				</td>
			</tr>

			<tr>
				<td colspan="2"><hr></td>
			</tr>

		</table>

		<p><input type="submit" name="gs_tinymce_save" class="w3-btn w3-round-large w3-green" value="Save settings"/></p>
	</form>
	
	<div class="w3-padding-top-32 margin-bottom-none w3-border-top">
		<p style="margin:0 0 5px 0"><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle" width="1em" height="1em" viewBox="0 0 48 48"><rect width="48" height="48" fill="none"/><circle cx="24" cy="24" r="21" fill="#2196f3"/><path fill="#fff" d="M22 22h4v11h-4z"/><circle cx="24" cy="16.5" r="2.5" fill="#fff"/></svg> <b>About: </b></p>
		<ul>
			<li>Built with TinyMCE 6.8.6 (MIT license).</li>
			<li>Full v6 <a href="https://www.tiny.cloud/docs/tinymce/6/plugins/#open-source-plugins" target="_blank">Plugin</a> & <a href="https://www.tiny.cloud/docs/tinymce/6/available-toolbar-buttons/" target="_blank">Toolbar</a> documentation.</li>
			<li>v7+ both local and CDN versions require an <a href="https://www.tiny.cloud/tinymce/" target="_blank">API key</a>, but offer <a href="https://www.tiny.cloud/tinymce/features/" target="_blank">advanced features</a>.</li>
		</ul>
	</div>
	
	<footer class="w3-padding-top-32 margin-bottom-none w3-border-top">
			<p class="w3-small clear w3-margin-bottom w3-margin-left">Made with 
				<span class="credit-icon">❤️</span> especially for "
				<b>'.$USR.'</b>". Is this plugin useful to you?
	
				<span class="w3-btn w3-khaki w3-border w3-border-red w3-round-xlarge">
					<a href="https://getsimple-ce.ovh/donate" target="_blank" class="donateButton">
						<b>Buy Us A Coffee </b>
						<svg
							xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle" width="24" height="24" viewBox="0 0 24 24">
							<path fill="currentColor" fill-opacity="0" d="M17 14v4c0 1.66 -1.34 3 -3 3h-6c-1.66 0 -3 -1.34 -3 -3v-4Z">
								<animate fill="freeze" attributeName="fill-opacity" begin="0.8s" dur="0.5s" values="0;1"/>
							</path>
							<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
								<path stroke-dasharray="48" stroke-dashoffset="48" d="M17 9v9c0 1.66 -1.34 3 -3 3h-6c-1.66 0 -3 -1.34 -3 -3v-9Z">
									<animate fill="freeze" attributeName="stroke-dashoffset" dur="0.6s" values="48;0"/>
								</path>
								<path stroke-dasharray="14" stroke-dashoffset="14" d="M17 9h3c0.55 0 1 0.45 1 1v3c0 0.55 -0.45 1 -1 1h-3">
									<animate fill="freeze" attributeName="stroke-dashoffset" begin="0.6s" dur="0.2s" values="14;0"/>
								</path>
								<mask id="lineMdCoffeeHalfEmptyFilledLoop0">
									<path stroke="#fff" d="M8 0c0 2-2 2-2 4s2 2 2 4-2 2-2 4 2 2 2 4M12 0c0 2-2 2-2 4s2 2 2 4-2 2-2 4 2 2 2 4M16 0c0 2-2 2-2 4s2 2 2 4-2 2-2 4 2 2 2 4">
										<animateMotion calcMode="linear" dur="3s" path="M0 0v-8" repeatCount="indefinite"/>
									</path>
								</mask>
								<rect width="24" height="0" y="7" fill="currentColor" mask="url(#lineMdCoffeeHalfEmptyFilledLoop0)">
									<animate fill="freeze" attributeName="y" begin="0.8s" dur="0.6s" values="7;2"/>
									<animate fill="freeze" attributeName="height" begin="0.8s" dur="0.6s" values="0;5"/>
								</rect>
							</g>
						</svg>
					</a>
				</span>
			</p>
		</footer>
</div>';

}

?>
