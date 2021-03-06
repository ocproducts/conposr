<?php /*

 Conposr Framework (a Composr-lite designed for standalone projects)
 Copyright (c) ocProducts, 2004-2018

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    conposr
 */

function http_download_file($url, $post_params = null, $http_verb = null, $raw_content_type = null)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    if ($post_params !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_params));
    }

    if ($http_verb !== null) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $http_verb);
    }

    if ($raw_content_type !== null) {
        if (defined('CURLINFO_HEADER_OUT')) {
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, 'Content-type: ' . $raw_content_type);
    }

    $ret = curl_exec($ch);

    curl_close($ch);

    return $ret;
}

function cms_file_get_contents_safe($path)
{
    $tmp = fopen($path, 'rb');
    if ($tmp === false) {
        return false;
    }
    flock($tmp, LOCK_SH);
    $contents = stream_get_contents($tmp);
    flock($tmp, LOCK_UN);
    fclose($tmp);
    return $contents;
}

function cms_file_put_contents_safe($path, $contents)
{
    $num_bytes_to_save = strlen($contents);

    $exists_already = file_exists($path);

    // Error condition: If there's a lack of disk space
    $num_bytes_to_write = $num_bytes_to_save;
    if (is_file($path)) {
        $num_bytes_to_write -= @filesize($path); /* @ is for race condition */
    }
    static $disk_space = null;
    if ($disk_space === null) {
        $disk_space = disk_free_space(dirname($path));
    }
    if ($disk_space < $num_bytes_to_write) {
        fatal_exit('Could not save file ' . $path . ', out of disk space');
    }

    // Save
    $num_bytes_written = file_put_contents($path, $contents, LOCK_EX);
    $disk_space -= $num_bytes_written;

    // Error condition: If it did not save all bytes
    if ($num_bytes_written < $num_bytes_to_save) {
        if ($exists_already) {
            @unlink($path);
        }

        fatal_exit('Could not save file ' . $path . ', out of disk space?');
    }
}

function clean_file_size($bytes)
{
    if ($bytes < 0) {
        return '-' . clean_file_size(-$bytes);
    }

    if (floatval($bytes) > 2.0 * 1024.0 * 1024.0 * 1024.0) {
        return strval(intval(round(floatval($bytes) / 1024.0 / 1024.0 / 1024.0))) . ' GB';
    }
    if (floatval($bytes) > 1024.0 * 1024.0 * 1024.0) {
        return float_format(floatval($bytes) / 1024.0 / 1024.0 / 1024.0, 2) . ' GB';
    }
    if (floatval($bytes) > 2.0 * 1024.0 * 1024.0) {
        return strval(intval(round(floatval($bytes) / 1024.0 / 1024.0))) . ' MB';
    }
    if (floatval($bytes) > 1024.0 * 1024.0) {
        return float_format(floatval($bytes) / 1024.0 / 1024.0, 2) . ' MB';
    }
    if (floatval($bytes) > 2.0 * 1024.0) {
        return strval(intval(round(floatval($bytes) / 1024.0))) . ' KB';
    }
    if (floatval($bytes) > 1024.0) {
        return float_format(floatval($bytes) / 1024.0, 2) . ' KB';
    }
    return strval($bytes) . ' Bytes';
}
