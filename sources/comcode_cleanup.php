<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2015

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core_rich_media
 */

/**
 * Filter external media, copying it locally.
 *
 * @param  string $text Comcode / HTML
 */
function download_associated_media(&$text)
{
    $matches = array();
    $num_matches = preg_match_all('#<(img|source)\s[^<>]*src="([^"<>]*)"#i', $text, $matches);
    for ($i = 0; $i < $num_matches; $i++) {
        $old_url = $matches[2][$i];
        _download_associated_media($text, $old_url);
    }
    $num_matches = preg_match_all('#<(img|source)\s[^<>]*src=\'([^\'<>]*)\'#i', $text, $matches);
    for ($i = 0; $i < $num_matches; $i++) {
        $old_url = $matches[2][$i];
        _download_associated_media($text, $old_url);
    }
}

/**
 * Filter external media, copying it locally (helper function).
 *
 * @param  string $text Comcode / HTML
 * @param  string $old_url Old URL to download and replace
 */
function _download_associated_media(&$text, $old_url)
{
    global $HTTP_DOWNLOAD_MIME_TYPE, $HTTP_FILENAME;

    $local_url_1 = parse_url(get_base_url());
    $local_domain_1 = $local_url_1['host'];

    $local_url_2 = parse_url(get_custom_base_url());
    $local_domain_2 = $local_url_2['host'];

    $matches2 = array();
    if ((preg_match('#^https?://([^:/]+)#', $old_url, $matches2) != 0) && ($matches2[1] != $local_domain_1) && ($matches2[1] != $local_domain_2)) {
        $temp_filename = uniqid('', true);
        $temp_path = get_custom_file_base() . '/uploads/external_media/' . $temp_filename;

        $write_to_file = fopen($temp_path, 'wb');
        $test = http_download_file($old_url, null, false, false, 'Composr', null, null, null, null, null, $write_to_file);
        if ($test === null) {
            @unlink($temp_path);
            return;
        }

        $mapping = array(
            'image/png' => 'png',
            'image/gif' => 'png',
            'image/jpeg' => 'png',
            'video/mp4' => 'mp4',
            'video/ogg' => 'ogv',
            'video/webm' => 'webm',
            'video/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
        );
        if (!isset($mapping[$HTTP_DOWNLOAD_MIME_TYPE])) {
            @unlink($temp_path);
            return;
        }

        $new_filename = preg_replace('#\..*#', '', basename($HTTP_FILENAME));
        if ($new_filename == '') {
            $new_filename = uniqid('', true);
        }
        $new_filename .= '.' . $mapping[$HTTP_DOWNLOAD_MIME_TYPE];
        $new_path = get_custom_file_base() . '/uploads/external_media/' . $new_filename;
        $i = 2;
        while (is_file($new_path)) {
            $new_filename = strval($i) . '_' . urldecode(basename($old_url));
            $new_path = get_custom_file_base() . '/uploads/external_media/' . $new_filename;
            $i++;
        }
        rename($temp_path, $new_path);

        fix_permissions($new_path);
        sync_file($new_path);

        $new_url = get_custom_base_url() . '/uploads/external_media/' . $new_filename;
        $text = str_replace($old_url, $new_url, $text);
    }
}
