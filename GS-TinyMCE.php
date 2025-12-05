<?php

if(!defined('IN_GS')){ die('You cannot load this file directly.'); }

// plugin id based on filename
$thisfile = basename(__FILE__, '.php');

// ---------- register plugin ----------
register_plugin(
	$thisfile,
	'GS-TinyMCE',
	'0.6',
	'CE Team',
	'https://www.getsimple-ce.ovh/',
	'Replace the admin CKEditor with TinyMCE.',
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
	'plugins'  => 'link image code lists paste table wordcount',
	'toolbar'  => 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist outdent indent | link image | table | code',
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
	if (basename($_SERVER['PHP_SELF']) == 'edit.php') {
		add_action('footer', 'gs_tinymce_header');
	}
}

function gs_tinymce_header(){
	$settings = gs_tinymce_load_settings();

	$version = isset($settings['tinymce_version']) ? $settings['tinymce_version'] : '6';

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

	$cfg = array(
		'selector' => $settings['selector'],
		'plugins'  => $plugins_string,
		'toolbar'  => $settings['toolbar'],
		'toolbar_mode' => isset($settings['toolbar_mode']) ? $settings['toolbar_mode'] : 'wrap',
		'toolbar_sticky' => true,
		'menubar'  => (bool)$settings['menubar'],
		'relative_urls' => (bool)$settings['relative_urls'],
		'promotion'  => false,
		'branding'   => false,
		'language'   => $settings['language']
	);

	if (!empty($settings['autoresize'])) {
		$cfg['min_height'] = (int)$settings['height'];
		$cfg['autoresize_bottom_margin'] = (int)$settings['autoresize_bottom_margin'];
		$cfg['autoresize_overflow_padding'] = 10;
	} else {
		$cfg['height'] = (int)$settings['height'];
	}

	$json_cfg = json_encode($cfg);

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

		gs_tinymce_save_settings($new);
		$settings = $new;
		$langs = gs_tinymce_get_languages($settings['tinymce_version']);

		echo '<div class="updated">Settings saved.</div>';
	}

	echo '
<style>
	table tr,td {border-bottom: 0!important; border-top: 0!important;}
		.w3-parent hr {important;margin: 10px 0!important;}
</style>';

	echo '
<div class="w3-parent">
<header class="w3-container w3-border-bottom w3-margin-bottom">
	<h3>GS-TinyMCE Settings</h3>
</header>

<form method="post">
<table class="form-table">

<tr>
<th>Presets</th>
<td>
	<button type="button" class="w3-btn w3-round-large w3-aqua" onclick="tinymcePreset(\'basic\')">Basic</button>
	<button type="button" class="w3-btn w3-round-large w3-blue" onclick="tinymcePreset(\'classic\')">Default</button>
	<button type="button" class="w3-btn w3-round-large w3-indigo" onclick="tinymcePreset(\'full\')">Advanced</button>
	<br><small>Click to auto-fill the Plugins and Toolbar fields.</small>
</td>
</tr>

<script>
function tinymcePreset(type){
	let pluginsField  = document.querySelector(\'input[name="plugins"]\');
	let toolbarField  = document.querySelector(\'input[name="toolbar"]\');

	if(type === \'basic\'){
		pluginsField.value = "link image lists";
		toolbarField.value = "bold italic | bullist numlist | link image";
	}

	if(type === \'classic\'){
		pluginsField.value = "link image code lists paste table wordcount";
		toolbarField.value = "undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist outdent indent | link image | table | code";
	}

	if(type === \'full\'){
		pluginsField.value = "anchor autolink charmap codesample image link lists media searchreplace table visualblocks wordcount code fullscreen";
		toolbarField.value = "undo redo | blocks | bold italic underline strikethrough | link image media table | align | bullist numlist | outdent indent | charmap codesample | fullscreen code";
	}
}
</script>

<tr>
<th>Plugins</th>
<td><input type="text" name="plugins" value="' . htmlspecialchars($settings['plugins']) . '" style="width:95%"/></td>
</tr>

<tr>
<th>Toolbar</th>
<td><input type="text" name="toolbar" value="' . htmlspecialchars($settings['toolbar']) . '" style="width:95%"/></td>
</tr>

<tr>
<th>Toolbar Mode</th>
<td>
<select name="toolbar_mode" style="width:30%">
    <option value="wrap"     '.($settings['toolbar_mode']=='wrap'?'selected':'').'>Wrap (default)</option>
    <option value="sliding"  '.($settings['toolbar_mode']=='sliding'?'selected':'').'>Sliding</option>
    <option value="floating" '.($settings['toolbar_mode']=='floating'?'selected':'').'>Floating</option>
    <option value="scrolling" '.($settings['toolbar_mode']=='scrolling'?'selected':'').'>Scrolling</option>
</select>
</td>
</tr>

<tr>
<th>Enable Auto-resize</th>
<td><input type="checkbox" name="autoresize" ' . ($settings['autoresize']?'checked':'') . ' /></td>
</tr>

<tr>
<th>Show menubar</th>
<td><input type="checkbox" name="menubar" ' . ($settings['menubar']?'checked':'') . ' /></td>
</tr>

<tr>
<th>Relative URLs</th>
<td><input type="checkbox" name="relative_urls" ' . ($settings['relative_urls']?'checked':'') . ' /></td>
</tr>

<tr>
<td colspan="2"><hr></td>
</tr>

<tr>
<th>Language</th>
<td>
<select name="language" style="width:20%">';
foreach($langs as $code){
	echo '<option value="'.$code.'" '.($settings['language']==$code?'selected':'').'>'.$code.'</option>';
}
echo '</select>
</td>
</tr>

<tr>
<th>Editor Minimum Height</th>
<td>
<input type="number" name="height" value="' . htmlspecialchars($settings['height']) . '" style="width:20%"/>
</td>
</tr>

<tr>
<th>Bottom Margin</th>
<td>
<input type="number" name="autoresize_bottom_margin" value="' . htmlspecialchars($settings['autoresize_bottom_margin']) . '" style="width:20%"/>
</td>
</tr>

<tr><td colspan="2"><hr></td></tr>

<tr>
<th>TinyMCE Version</th>
<td><input type="number" name="tinymce_version" value="' . htmlspecialchars($settings['tinymce_version']) . '" style="width:20%"/></td>
</tr>

<tr>
<th>Selector</th>
<td><input type="text" name="selector" value="' . htmlspecialchars($settings['selector']) . '" style="width:95%"/></td>
</tr>

<tr>
<th>Use CDN</th>
<td><input type="checkbox" name="use_cdn" ' . ($settings['use_cdn']?'checked':'') . ' /></td>
</tr>

<tr>
<th>CDN API Key</th>
<td><input type="text" name="cdn_key" value="' . htmlspecialchars($settings['cdn_key']) . '" style="width:95%"/></td>
</tr>

<tr>
<td colspan="2"><hr></td>
</tr>

</table>

<p><input type="submit" name="gs_tinymce_save" class="w3-btn w3-round-large w3-green" value="Save settings"/></p>
</form>
</div>';

}

?>
	