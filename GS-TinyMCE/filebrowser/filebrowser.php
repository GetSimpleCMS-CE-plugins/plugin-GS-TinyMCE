<?php
/**
 * Modified File Browser for TinyMCE Integration
 * 
 * This is a modified version of GetSimple's filebrowser.php
 * that works with TinyMCE 6 & 7 file picker callback
 * 
 * @package GetSimple
 * @subpackage Files
 * @version 1.2
 */

include('../../../gsconfig.php');
	$admin = defined('GSADMIN') ? GSADMIN : 'admin';
	include("../../../".$admin."/inc/common.php");
	$loggedin = cookie_check();
	if (!$loggedin) die("Not logged in!");
	if (!defined('IN_GS')) {
		die('you cannot load this page directly.');
	}

$filesSorted = null;
$dirsSorted = null;

// Get parameters
$path = (isset($_GET['path'])) ? "../data/uploads/".$_GET['path'] : "../data/uploads/";
$subPath = (isset($_GET['path'])) ? $_GET['path'] : "";

// Security check
if(!path_is_safe($path, GSDATAUPLOADPATH)) die();

$type = isset($_GET['type']) ? var_out($_GET['type']) : 'all';
$callback = isset($_GET['callback']) ? var_out($_GET['callback']) : '';

// Setup path variables
$path = tsl($path);
$isUnixHost = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? false : true);
$sitepath = suggest_site_path();
$fullPath = $sitepath . "data/uploads/";

// Detect if we're in a TinyMCE context
$isTinyMCE = !empty($callback) || (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'tinymce') !== false);

global $LANG;
$LANG_header = preg_replace('/(?:(?<=([a-z]{2}))).*/', '', $LANG);
?>
<!DOCTYPE html>
<html lang="<?php echo $LANG_header; ?>">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title><?php echo i18n_r('FILE_BROWSER'); ?></title>
    <link rel="shortcut icon" href="../favicon.png" type="image/x-icon" />
    <link rel="stylesheet" type="text/css" href="../template/style.php?v=<?php echo GSVERSION; ?>" media="screen" />
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .wrapper { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: #2c3e50; color: white; padding: 15px 20px; }
        .header h2 { margin: 0; font-size: 18px; }
        .breadcrumb { background: #ecf0f1; padding: 10px 20px; border-bottom: 1px solid #ddd; font-size: 14px; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .content { padding: 20px; }
        .file-table { width: 100%; border-collapse: collapse; }
        .file-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #dee2e6; }
        .file-table td { padding: 12px; border-bottom: 1px solid #dee2e6; vertical-align: middle; }
        .file-table tr:hover { background: #f8f9fa; }
        .folder-row { background: #fff9e6; }
        .file-link { color: #2c3e50; text-decoration: none; cursor: pointer; display: inline-block; padding: 5px 0; }
        .file-link:hover { color: #3498db; text-decoration: underline; }
        .thumbnail { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
        .file-info { font-size: 12px; color: #666; }
        .action-buttons { padding: 20px; background: #f8f9fa; border-top: 1px solid #dee2e6; text-align: center; }
        .btn { padding: 8px 16px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #2980b9; }
        .btn-cancel { background: #95a5a6; }
        .btn-cancel:hover { background: #7f8c8d; }
        .type-filter { margin-bottom: 20px; }
        .type-filter select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .no-files { text-align: center; padding: 40px; color: #666; font-style: italic; }
        .file-icon { width: 20px; margin-right: 8px; vertical-align: middle; }
        .image-preview { max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px; margin-right: 15px; }
        .selected-file { background: #e3f2fd; }
    </style>
    <script>
    // Function to handle file selection
    function selectFile(fileUrl, altText, titleText) {
        <?php if ($isTinyMCE && !empty($callback)): ?>
            // TinyMCE integration
            if (window.opener && window.opener.gsTinyMCEInsertFile) {
                window.opener.gsTinyMCEInsertFile(fileUrl, altText, titleText);
            } else if (window.opener && window.opener.tinymceFileCallback) {
                var metaData = {};
                if (altText) metaData.alt = altText;
                if (titleText) metaData.title = titleText;
                window.opener.tinymceFileCallback(fileUrl, metaData);
            }
            window.close();
        <?php else: ?>
            // Default behavior (for CKEditor compatibility)
            if (window.opener && window.opener.CKEDITOR) {
                var funcNum = <?php echo isset($_GET['CKEditorFuncNum']) ? intval($_GET['CKEditorFuncNum']) : '0'; ?>;
                window.opener.CKEDITOR.tools.callFunction(funcNum, fileUrl);
                window.close();
            } else if (window.opener) {
                // Fallback for other integrations
                window.opener.postMessage({
                    action: 'fileSelected',
                    url: fileUrl,
                    alt: altText || '',
                    title: titleText || ''
                }, '*');
                window.close();
            }
        <?php endif; ?>
    }
    
    // Function to filter files by type
    function filterFiles() {
        var filter = document.getElementById('typeFilter').value;
        var rows = document.querySelectorAll('.file-table tr[data-type]');
        
        rows.forEach(function(row) {
            if (filter === 'all' || row.getAttribute('data-type') === filter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    // Function to preview image
    function previewImage(imgSrc, fileName) {
        document.getElementById('previewImage').src = imgSrc;
        document.getElementById('previewFileName').textContent = fileName;
        document.getElementById('previewAlt').value = fileName.replace(/\.[^/.]+$/, ""); // Remove extension for alt text
        document.getElementById('previewTitle').value = fileName.replace(/\.[^/.]+$/, "");
        
        // Mark selected row
        var rows = document.querySelectorAll('.file-table tr');
        rows.forEach(function(row) {
            row.classList.remove('selected-file');
        });
        event.target.closest('tr').classList.add('selected-file');
        
        document.getElementById('previewSection').style.display = 'block';
    }
    
    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
        filterFiles();
        
        // Set initial filter based on URL parameter
        var urlParams = new URLSearchParams(window.location.search);
        var typeParam = urlParams.get('type');
        if (typeParam) {
            document.getElementById('typeFilter').value = typeParam;
            filterFiles();
        }
    });
    </script>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h2><?php echo i18n_r('FILE_BROWSER'); ?></h2>
    </div>
    
    <div class="breadcrumb">
        <?php
        $pathParts = explode("/", $subPath);
        $urlPath = "";
        
        echo '/ <a href="?type=' . htmlspecialchars($type) . '&callback=' . htmlspecialchars($callback) . '">uploads</a> / ';
        foreach ($pathParts as $pathPart) {
            if ($pathPart != '') {
                $urlPath .= $pathPart . "/";
                echo '<a href="?path=' . htmlspecialchars($urlPath) . '&type=' . htmlspecialchars($type) . '&callback=' . htmlspecialchars($callback) . '">' . htmlspecialchars($pathPart) . '</a> / ';
            }
        }
        ?>
    </div>
    
    <div class="content">
        <div class="type-filter">
            <label for="typeFilter">Filter by type:</label>
            <select id="typeFilter" onchange="filterFiles()">
                <option value="all">All Files</option>
                <option value="image" <?php echo $type == 'images' ? 'selected' : ''; ?>>Images Only</option>
                <option value="document">Documents</option>
                <option value="archive">Archives</option>
                <option value="media">Media Files</option>
            </select>
        </div>
        
        <div id="previewSection" style="display: none; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
            <div style="display: flex; align-items: center;">
                <img id="previewImage" src="" alt="Preview" class="image-preview">
                <div>
                    <h4 id="previewFileName" style="margin: 0 0 10px 0;"></h4>
                    <div style="margin-bottom: 10px;">
                        <label>Alt Text: <input type="text" id="previewAlt" style="margin-left: 5px; padding: 4px;"></label>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label>Title: <input type="text" id="previewTitle" style="margin-left: 5px; padding: 4px;"></label>
                    </div>
                    <button class="btn" onclick="selectFile(
                        document.getElementById('previewImage').src,
                        document.getElementById('previewAlt').value,
                        document.getElementById('previewTitle').value
                    )">Select This File</button>
                </div>
            </div>
        </div>
        
        <table class="file-table">
            <thead>
                <tr>
                    <th style="width: 50px;"></th>
                    <th>Name</th>
                    <th style="width: 100px;">Size</th>
                    <th style="width: 120px;">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 0;
                $dircount = 0;
                $filesArray = [];
                $dirsArray = [];
                
                // Get files and directories
                $filenames = getFiles($path);
                if (count($filenames) != 0) { 
                    foreach ($filenames as $file) {
                        if ($file == "." || $file == ".." || $file == ".htaccess") {
                            // Skip system files
                            continue;
                        } elseif (is_dir($path . $file)) {
                            // Directory
                            $dirsArray[$dircount]['name'] = $file;
                            $dircount++;
                        } else {
                            // File
                            $filesArray[$count]['name'] = $file;
                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            $filesArray[$count]['type'] = get_FileType($ext);
                            clearstatcache();
                            $ss = @stat($path . $file);
                            $filesArray[$count]['date'] = @date('M j, Y', $ss['mtime']);
                            $filesArray[$count]['size'] = fSize($ss['size']);
                            $filesArray[$count]['fullpath'] = $fullPath . $subPath . $file;
                            $count++;
                        }
                    }
                    $filesSorted = subval_sort($filesArray, 'name');
                    $dirsSorted = subval_sort($dirsArray, 'name');
                }
                
                // Display directories first
                if (count((array)$dirsSorted) != 0) {       
                    foreach ((array)$dirsSorted as $dir) {
                        $dirUrl = '?path=' . urlencode($subPath . $dir['name'] . '/') . 
                                 '&type=' . urlencode($type) . 
                                 '&callback=' . urlencode($callback);
                        echo '<tr class="folder-row" data-type="folder">';
                        echo '<td><img src="../template/images/folder.png" class="file-icon" alt="Folder"></td>';
                        echo '<td><a href="' . htmlspecialchars($dirUrl) . '" class="file-link"><strong>' . htmlspecialchars($dir['name']) . '</strong></a></td>';
                        echo '<td></td>';
                        echo '<td></td>';
                        echo '</tr>';
                    }
                }
                
                // Display files
                if (count($filesSorted) != 0) {           
                    foreach ($filesSorted as $file) {
                        $fileType = $file['type'];
                        $fileUrl = $file['fullpath'];
                        $fileName = $file['name'];
                        $fileSize = $file['size'];
                        $fileDate = shtDate($file['date']);
                        
                        // Determine file type for filtering
                        $filterType = 'other';
                        if (strpos($fileType, 'Image') !== false) {
                            $filterType = 'image';
                        } elseif (in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)), 
                                  ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt'])) {
                            $filterType = 'document';
                        } elseif (in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)), 
                                  ['zip', 'rar', '7z', 'tar', 'gz'])) {
                            $filterType = 'archive';
                        } elseif (in_array(strtolower(pathinfo($fileName, PATHINFO_EXTENSION)), 
                                  ['mp3', 'mp4', 'avi', 'mov', 'wmv'])) {
                            $filterType = 'media';
                        }
                        
                        echo '<tr data-type="' . $filterType . '">';
                        
                        // Thumbnail or icon
                        if ($filterType === 'image') {
                            $thumbSrc = $fileUrl;
                            // Try to get thumbnail if exists
                            $thumbPath = '../data/thumbs/' . ($subPath ? $subPath . '/' : '') . 'thumbsm.' . $fileName;
                            if (file_exists($thumbPath)) {
                                $thumbSrc = str_replace($fullPath, $sitepath . 'data/thumbs/', $fileUrl);
                                $thumbSrc = dirname($thumbSrc) . '/thumbsm.' . basename($thumbSrc);
                            }
                            echo '<td><img src="' . htmlspecialchars($thumbSrc) . '" class="thumbnail" alt="' . htmlspecialchars($fileName) . '" onclick="previewImage(\'' . htmlspecialchars($fileUrl) . '\', \'' . htmlspecialchars($fileName) . '\')"></td>';
                        } else {
                            echo '<td><img src="../template/images/file.png" class="file-icon" alt="File"></td>';
                        }
                        
                        // File name with select action
                        echo '<td>';
                        echo '<a href="javascript:void(0)" class="file-link" onclick="selectFile(\'' . htmlspecialchars($fileUrl) . '\', \'' . htmlspecialchars(pathinfo($fileName, PATHINFO_FILENAME)) . '\', \'' . htmlspecialchars(pathinfo($fileName, PATHINFO_FILENAME)) . '\')">';
                        echo htmlspecialchars($fileName);
                        echo '</a>';
                        
                        // Additional actions for images
                        if ($filterType === 'image') {
                            echo '<br><small>';
                            echo '<a href="javascript:void(0)" onclick="previewImage(\'' . htmlspecialchars($fileUrl) . '\', \'' . htmlspecialchars($fileName) . '\')">Preview</a>';
                            // Check for thumbnail
                            $thumbPath = '../data/thumbs/' . ($subPath ? $subPath . '/' : '') . 'thumbnail.' . $fileName;
                            if (file_exists($thumbPath)) {
                                $thumbUrl = $sitepath . 'data/thumbs/' . ($subPath ? $subPath . '/' : '') . 'thumbnail.' . $fileName;
                                echo ' | <a href="javascript:void(0)" onclick="selectFile(\'' . htmlspecialchars($thumbUrl) . '\', \'' . htmlspecialchars(pathinfo($fileName, PATHINFO_FILENAME)) . '\', \'' . htmlspecialchars(pathinfo($fileName, PATHINFO_FILENAME)) . '\')">Use Thumbnail</a>';
                            }
                            echo '</small>';
                        }
                        echo '</td>';
                        
                        // File size
                        echo '<td><span class="file-info">' . htmlspecialchars($fileSize) . '</span></td>';
                        
                        // File date
                        echo '<td><span class="file-info">' . htmlspecialchars($fileDate) . '</span></td>';
                        
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="4" class="no-files">No files found in this directory.</td></tr>';
                }
                ?>
            </tbody>
        </table>
        
        <?php if (count($filesSorted) > 0): ?>
        <div class="file-info" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <em><b><?php echo count($filesSorted); ?></b> <?php echo i18n_r('TOTAL_FILES'); ?></em>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="action-buttons">
        <button class="btn btn-cancel" onclick="window.close()">Cancel</button>
    </div>
</div>
</body>
</html>