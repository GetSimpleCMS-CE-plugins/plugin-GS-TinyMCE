<?php
/**
 * Modified File Browser for TinyMCE Integration
 * 
 * This is a modified version of GetSimple's filebrowser.php
 * that works with TinyMCE 6 & 7 file picker callback
 * 
 * @package GetSimple
 * @subpackage Files
 * @version 1.4
 */

// Enable error reporting for debugging
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

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
$subPath = (isset($_GET['path'])) ? $_GET['path'] : "";
$path = GSDATAUPLOADPATH . $subPath;

// Security check
if(!path_is_safe($path, GSDATAUPLOADPATH)) {
    die('Invalid path');
}

$type = isset($_GET['type']) ? var_out($_GET['type']) : 'all';

// Setup path variables
$path = tsl($path);
$sitepath = suggest_site_path();

global $LANG;
$LANG_header = preg_replace('/(?:(?<=([a-z]{2}))).*/', '', $LANG);
?>
<!DOCTYPE html>
<html lang="<?php echo $LANG_header; ?>">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title><?php echo i18n_r('FILE_BROWSER'); ?></title>
    <link rel="shortcut icon" href="<?php echo $SITEURL; ?>favicon.png" type="image/x-icon" />
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            margin: 0; 
            padding: 0;
            background: #f5f5f5; 
            font-size: 14px;
        }
        .wrapper { 
            max-width: 100%; 
            margin: 0;
            background: white; 
            min-height: 100vh;
        }
        .header { 
            background: #2c3e50; 
            color: white; 
            padding: 15px 20px;
            border-bottom: 3px solid #34495e;
        }
        .header h2 { 
            margin: 0; 
            font-size: 18px; 
            font-weight: 600;
        }
        .breadcrumb { 
            background: #ecf0f1; 
            padding: 12px 20px; 
            border-bottom: 1px solid #bdc3c7; 
            font-size: 13px;
            overflow-x: auto;
            white-space: nowrap;
        }
        .breadcrumb a { 
            color: #3498db; 
            text-decoration: none;
            font-weight: 500;
        }
        .breadcrumb a:hover { 
            text-decoration: underline; 
        }
        .content { 
            padding: 20px; 
        }
        .file-table { 
            width: 100%; 
            border-collapse: collapse;
            margin-top: 10px;
        }
        .file-table th { 
            background: #34495e; 
            color: white;
            padding: 12px; 
            text-align: left; 
            font-weight: 600; 
            font-size: 13px;
            position: sticky;
            top: 0;
        }
        .file-table td { 
            padding: 10px 12px; 
            border-bottom: 1px solid #ecf0f1; 
            vertical-align: middle; 
        }
        .file-table tbody tr:hover { 
            background: #f8f9fa; 
        }
        .folder-row { 
            background: #fff9e6; 
        }
        .folder-row:hover {
            background: #fff3cd !important;
        }
        .file-link { 
            color: #2c3e50; 
            text-decoration: none; 
            cursor: pointer; 
            display: inline-block; 
        }
        .file-link:hover { 
            color: #3498db; 
            text-decoration: underline; 
        }
        .thumbnail { 
            width: 50px; 
            height: 50px; 
            object-fit: cover; 
            border-radius: 4px; 
            border: 1px solid #ddd;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .thumbnail:hover {
            transform: scale(1.1);
        }
        .file-info { 
            font-size: 12px; 
            color: #666; 
        }
        .type-filter { 
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .type-filter label {
            font-weight: 600;
            margin-right: 10px;
        }
        .type-filter select { 
            padding: 8px 12px; 
            border: 1px solid #ddd; 
            border-radius: 4px;
            font-size: 14px;
        }
        .no-files { 
            text-align: center; 
            padding: 60px 20px; 
            color: #999; 
            font-style: italic;
            font-size: 16px;
        }
        .file-icon { 
            font-size: 24px;
            text-align: center;
        }
        .image-preview { 
            max-width: 250px; 
            max-height: 250px; 
            border: 2px solid #3498db; 
            border-radius: 4px;
        }
        .selected-file { 
            background: #e3f2fd !important; 
        }
        #previewSection { 
            display: none; 
            margin-bottom: 20px; 
            padding: 20px; 
            background: #f8f9fa; 
            border-radius: 6px; 
            border: 2px solid #3498db;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #2980b9;
        }
        .stats {
            margin-top: 15px;
            padding: 12px;
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            border-radius: 4px;
            font-size: 13px;
        }
    </style>
    <script>
    // Function to handle file selection for TinyMCE
    function selectFile(fileUrl, altText, titleText) {
        // Send message to parent window (TinyMCE modal)
        window.parent.postMessage({
            mceAction: 'fileSelected',
            url: fileUrl,
            alt: altText || '',
            title: titleText || ''
        }, '*');
    }
    
    // Function to filter files by type
    function filterFiles() {
        var filter = document.getElementById('typeFilter').value;
        var rows = document.querySelectorAll('.file-table tbody tr[data-type]');
        var visibleCount = 0;
        
        rows.forEach(function(row) {
            if (filter === 'all' || row.getAttribute('data-type') === filter) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update count
        var countEl = document.getElementById('visibleCount');
        if (countEl) {
            countEl.textContent = visibleCount;
        }
    }
    
    // Function to preview image
    function previewImage(imgSrc, fileName) {
        document.getElementById('previewImage').src = imgSrc;
        document.getElementById('previewFileName').textContent = fileName;
        
        // Set alt/title to filename without extension by default
        var nameWithoutExt = fileName.replace(/\.[^/.]+$/, "");
        document.getElementById('previewAlt').value = nameWithoutExt;
        document.getElementById('previewTitle').value = nameWithoutExt;
        
        // Store the current file URL for the select button
        document.getElementById('previewSection').setAttribute('data-file-url', imgSrc);
        
        // Mark selected row
        var rows = document.querySelectorAll('.file-table tbody tr');
        rows.forEach(function(row) {
            row.classList.remove('selected-file');
        });
        
        // Find and highlight the clicked row
        var clickedThumb = event.target;
        var row = clickedThumb.closest('tr');
        if (row) {
            row.classList.add('selected-file');
        }
        
        document.getElementById('previewSection').style.display = 'block';
        
        // Scroll preview into view
        document.getElementById('previewSection').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    // Function to select file from preview section
    function selectFromPreview() {
        var fileUrl = document.getElementById('previewSection').getAttribute('data-file-url');
        var altText = document.getElementById('previewAlt').value;
        var titleText = document.getElementById('previewTitle').value;
        selectFile(fileUrl, altText, titleText);
    }
    
    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Set initial filter based on URL parameter
        var urlParams = new URLSearchParams(window.location.search);
        var typeParam = urlParams.get('type');
        if (typeParam) {
            var filterSelect = document.getElementById('typeFilter');
            
            // Map TinyMCE types to our filter types
            if (typeParam === 'image') {
                filterSelect.value = 'image';
            } else if (typeParam === 'media') {
                filterSelect.value = 'media';
            } else if (typeParam === 'file') {
                filterSelect.value = 'all';
            }
        }
        
        filterFiles();
    });
    </script>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h2>📁 <?php echo i18n_r('FILE_BROWSER'); ?></h2>
    </div>
    
    <div class="breadcrumb">
        <?php
        $pathParts = explode("/", $subPath);
        $urlPath = "";
        
        echo '🏠 <a href="?type=' . htmlspecialchars($type) . '">uploads</a>';
        
        foreach ($pathParts as $pathPart) {
            if ($pathPart != '') {
                $urlPath .= $pathPart . "/";
                echo ' / <a href="?path=' . htmlspecialchars($urlPath) . '&type=' . htmlspecialchars($type) . '">' . htmlspecialchars($pathPart) . '</a>';
            }
        }
        ?>
    </div>
    
    <div class="content">
        <?php
        // Quick check - count files in directory
        $testFiles = [];
        if (function_exists('getFiles')) {
            $testFiles = getFiles($path);
        } else {
            if (is_dir($path) && $handle = opendir($path)) {
                while (false !== ($file = readdir($handle))) {
                    $testFiles[] = $file;
                }
                closedir($handle);
            }
        }
        
        // Debug information - remove this section once working
        /*echo '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin-bottom: 15px; border-radius: 4px; font-size: 12px; font-family: monospace;">';
        echo '<strong>Debug Info:</strong><br>';
        echo 'Scanning path: ' . htmlspecialchars($path) . '<br>';
        echo 'Path exists: ' . (file_exists($path) ? '✓ Yes' : '✗ No') . '<br>';
        echo 'Is directory: ' . (is_dir($path) ? '✓ Yes' : '✗ No') . '<br>';
        echo 'Is readable: ' . (is_readable($path) ? '✓ Yes' : '✗ No') . '<br>';
        echo 'GSDATAUPLOADPATH: ' . htmlspecialchars(GSDATAUPLOADPATH) . '<br>';
        echo 'SubPath: ' . htmlspecialchars($subPath) . '<br>';
        echo '<strong>Items found in directory: ' . count($testFiles) . '</strong><br>';
        if (count($testFiles) > 0) {
            echo 'Files: ' . htmlspecialchars(implode(', ', array_slice($testFiles, 0, 10))) . (count($testFiles) > 10 ? '...' : '');
        }
        echo '</div>';*/
        ?>
        
        <div class="type-filter">
            <label for="typeFilter">🔍 Filter by type:</label>
            <select id="typeFilter" onchange="filterFiles()">
                <option value="all">All Files</option>
                <option value="image">Images Only</option>
                <option value="document">Documents</option>
                <option value="archive">Archives</option>
                <option value="media">Media Files</option>
            </select>
        </div>
        
        <div id="previewSection" data-file-url="">
            <div style="display: flex; align-items: flex-start; gap: 20px; flex-wrap: wrap;">
                <img id="previewImage" src="" alt="Preview" class="image-preview">
                <div style="flex: 1; min-width: 250px;">
                    <h4 id="previewFileName" style="margin: 0 0 15px 0; color: #2c3e50;"></h4>
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #555;">Alt Text:</label>
                        <input type="text" id="previewAlt" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #555;">Title:</label>
                        <input type="text" id="previewTitle" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                    </div>
                    <button class="btn" onclick="selectFromPreview()">✓ Select This File</button>
                </div>
            </div>
        </div>
        
        <table class="file-table">
            <thead>
                <tr>
                    <th style="width: 60px;"></th>
                    <th>Name</th>
                    <th style="width: 100px;">Size</th>
                    <th style="width: 130px;">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $count = 0;
                $dircount = 0;
                $filesArray = [];
                $dirsArray = [];
                
                // Debug: Show the path we're scanning
                // echo "<!-- Scanning path: " . htmlspecialchars($path) . " -->\n";
                // echo "<!-- Path exists: " . (file_exists($path) ? 'yes' : 'no') . " -->\n";
                // echo "<!-- Is directory: " . (is_dir($path) ? 'yes' : 'no') . " -->\n";
                
                // Get files and directories - try multiple methods
                if (function_exists('getFiles')) {
                    $filenames = getFiles($path);
                } else {
                    // Fallback if getFiles doesn't exist
                    $filenames = [];
                    if (is_dir($path) && $handle = opendir($path)) {
                        while (false !== ($file = readdir($handle))) {
                            $filenames[] = $file;
                        }
                        closedir($handle);
                    }
                }
                
                // Debug output
                echo "<!-- Found " . count($filenames) . " items in directory -->\n";
                echo "<!-- Files array: " . print_r($filenames, true) . " -->\n";
                
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
                            
                            // Use get_FileType if available, otherwise default
                            if (function_exists('get_FileType')) {
                                $filesArray[$count]['type'] = get_FileType($ext);
                            } else {
                                $filesArray[$count]['type'] = 'File';
                            }
                            
                            clearstatcache();
                            $ss = @stat($path . $file);
                            $filesArray[$count]['date'] = @date('M j, Y', $ss['mtime']);
                            
                            // Use fSize if available, otherwise calculate manually
                            if (function_exists('fSize')) {
                                $filesArray[$count]['size'] = fSize($ss['size']);
                            } else {
                                $size = $ss['size'];
                                $units = array('B', 'KB', 'MB', 'GB');
                                $unitIndex = 0;
                                while ($size > 1024 && $unitIndex < count($units) - 1) {
                                    $size /= 1024;
                                    $unitIndex++;
                                }
                                $filesArray[$count]['size'] = round($size, 2) . ' ' . $units[$unitIndex];
                            }
                            
                            // Build the full URL to the file
                            $fileRelPath = $subPath . $file;
                            $filesArray[$count]['url'] = $SITEURL . 'data/uploads/' . $fileRelPath;
                            
                            $count++;
                        }
                    }
                    
                    if (count($filesArray) > 0) {
                        // Use subval_sort if available, otherwise use basic sort
                        if (function_exists('subval_sort')) {
                            $filesSorted = subval_sort($filesArray, 'name');
                        } else {
                            $filesSorted = $filesArray;
                            usort($filesSorted, function($a, $b) {
                                return strcmp($a['name'], $b['name']);
                            });
                        }
                    }
                    if (count($dirsArray) > 0) {
                        if (function_exists('subval_sort')) {
                            $dirsSorted = subval_sort($dirsArray, 'name');
                        } else {
                            $dirsSorted = $dirsArray;
                            usort($dirsSorted, function($a, $b) {
                                return strcmp($a['name'], $b['name']);
                            });
                        }
                    }
                }
                
                // Debug counts
                echo "<!-- Processed: " . count((array)$filesSorted) . " files, " . count((array)$dirsSorted) . " directories -->\n";
                
                // Display directories first
                if (count((array)$dirsSorted) != 0) {       
                    foreach ((array)$dirsSorted as $dir) {
                        $dirUrl = '?path=' . urlencode($subPath . $dir['name'] . '/') . '&type=' . urlencode($type);
                        echo '<tr class="folder-row" data-type="folder">';
                        echo '<td class="file-icon">📁</td>';
                        echo '<td><a href="' . htmlspecialchars($dirUrl) . '" class="file-link"><strong>' . htmlspecialchars($dir['name']) . '</strong></a></td>';
                        echo '<td><span class="file-info">—</span></td>';
                        echo '<td><span class="file-info">—</span></td>';
                        echo '</tr>';
                    }
                }
                
                // Display files
                if (count($filesSorted) != 0) {           
                    foreach ($filesSorted as $file) {
                        $fileType = $file['type'];
                        $fileUrl = $file['url'];
                        $fileName = $file['name'];
                        $fileSize = $file['size'];
                        $fileDate = $file['date'];
                        
                        // Determine file type for filtering
                        $filterType = 'other';
                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp', 'ico'])) {
                            $filterType = 'image';
                        } elseif (in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'xls', 'xlsx'])) {
                            $filterType = 'document';
                        } elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'])) {
                            $filterType = 'archive';
                        } elseif (in_array($ext, ['mp3', 'mp4', 'avi', 'mov', 'wmv', 'webm', 'ogg', 'wav', 'flac'])) {
                            $filterType = 'media';
                        }
                        
                        echo '<tr data-type="' . $filterType . '">';
                        
                        // Thumbnail or icon
                        if ($filterType === 'image') {
                            $thumbSrc = $fileUrl;
                            
                            // Try to get thumbnail if exists
                            $thumbFileName = 'thumbsm.' . $fileName;
                            
                            // Check if GSDATATHUMBPATH is defined and the thumbnail exists
                            if (defined('GSDATATHUMBPATH')) {
                                $thumbPath = GSDATATHUMBPATH . $subPath . $thumbFileName;
                                if (file_exists($thumbPath)) {
                                    $thumbSrc = $SITEURL . 'data/thumbs/' . $subPath . $thumbFileName;
                                }
                            }
                            
                            echo '<td><img src="' . htmlspecialchars($thumbSrc) . '" class="thumbnail" alt="' . htmlspecialchars($fileName) . '" onclick="previewImage(\'' . htmlspecialchars($fileUrl, ENT_QUOTES) . '\', \'' . htmlspecialchars($fileName, ENT_QUOTES) . '\')"></td>';
                        } else {
                            $iconEmoji = '📄';
                            if ($filterType === 'document') $iconEmoji = '📄';
                            elseif ($filterType === 'archive') $iconEmoji = '📦';
                            elseif ($filterType === 'media') $iconEmoji = '🎬';
                            echo '<td class="file-icon">' . $iconEmoji . '</td>';
                        }
                        
                        // File name with select action
                        $nameWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
                        echo '<td>';
                        echo '<a href="javascript:void(0)" class="file-link" onclick="selectFile(\'' . htmlspecialchars($fileUrl, ENT_QUOTES) . '\', \'' . htmlspecialchars($nameWithoutExt, ENT_QUOTES) . '\', \'' . htmlspecialchars($nameWithoutExt, ENT_QUOTES) . '\')">';
                        echo htmlspecialchars($fileName);
                        echo '</a>';
                        
                        // Additional actions for images
                        if ($filterType === 'image') {
                            echo '<br><small style="color: #666;">';
                            //echo '<a href="javascript:void(0)" onclick="previewImage(\'' . htmlspecialchars($fileUrl, ENT_QUOTES) . '\', \'' . htmlspecialchars($fileName, ENT_QUOTES) . '\')" style="color: #3498db;">👁 Preview</a>';
                            
                            // Check for thumbnail version
                            if (defined('GSDATATHUMBPATH')) {
                                $largeThumbnail = GSDATATHUMBPATH . $subPath . 'thumbnail.' . $fileName;
                                if (file_exists($largeThumbnail)) {
                                    $thumbUrl = $SITEURL . 'data/thumbs/' . $subPath . 'thumbnail.' . $fileName;
                                    echo ' | <a href="javascript:void(0)" onclick="selectFile(\'' . htmlspecialchars($thumbUrl, ENT_QUOTES) . '\', \'' . htmlspecialchars($nameWithoutExt, ENT_QUOTES) . '\', \'' . htmlspecialchars($nameWithoutExt, ENT_QUOTES) . '\')" style="color: #27ae60;">🖼 Thumbnail</a>';
                                }
                            }
                            echo '</small>';
                        }
                        echo '</td>';
                        
                        // File size
                        $displaySize = strip_tags($fileSize); // Remove any HTML tags from size
                        echo '<td><span class="file-info">' . htmlspecialchars($displaySize) . '</span></td>';
                        
                        // File date
                        echo '<td><span class="file-info">' . htmlspecialchars($fileDate) . '</span></td>';
                        
                        echo '</tr>';
                    }
                } elseif (count((array)$dirsSorted) == 0) {
                    echo '<tr><td colspan="4" class="no-files">📂 No files found in this directory.</td></tr>';
                }
                ?>
            </tbody>
        </table>
        
        <?php if (count($filesSorted) > 0 || count((array)$dirsSorted) > 0): ?>
        <div class="stats">
            <strong><span id="visibleCount"><?php echo count($filesSorted); ?></span></strong> file(s) visible
            <?php if (count((array)$dirsSorted) > 0): ?>
                | <strong><?php echo count((array)$dirsSorted); ?></strong> folder(s)
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>