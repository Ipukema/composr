<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2017

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core
 */

/**
 * Hook class.
 */
class Hook_check_functions_needed
{
    /**
     * Check various input var restrictions.
     *
     * @return array List of warnings
     */
    public function run()
    {
        $warning = array();

        // These aren't all actually needed. But we can't reasonably expect developers to work if arbitrary stuff may be disabled:
        // so we allow everything that we could reasonably assume will be there.
        $baseline_functions = <<<END
            abs addslashes array_count_values array_diff array_flip array_key_exists array_keys
            array_intersect array_merge array_pop array_push array_reverse array_search array_shift
            array_slice array_splice array_unique array_values arsort asort base64_decode base64_encode
            call_user_func ceil chdir checkdate chmod chr chunk_split class_exists clearstatcache closedir
            constant copy cos count crypt current date dechex decoct define defined dirname
            deg2rad error_reporting eval exit explode fclose feof fgets file file_exists
            file_get_contents filectime filegroup filemtime fileowner fileperms filesize floatval floor
            get_defined_vars get_declared_classes get_defined_functions fopen fread fseek ftell
            function_exists fwrite get_class get_html_translation_table getcwd
            getdate getenv gmdate header headers_sent hexdec htmlentities is_float ob_get_level
            implode in_array include include_once ini_get ini_set intval is_a is_array is_bool
            is_integer is_null is_numeric is_object is_readable is_resource is_string is_uploaded_file
            isset krsort ksort localeconv ltrim mail max md5 method_exists microtime min is_writable
            mkdir mktime move_uploaded_file mt_getrandmax mt_rand mt_srand number_format ob_end_clean
            ob_end_flush ob_get_contents ob_start octdec opendir ord pack parse_url pathinfo
            preg_replace preg_replace_callback preg_split print_r putenv rawurldecode rmdir
            rawurlencode readdir realpath register_shutdown_function rename require require_once reset
            round rsort rtrim serialize set_error_handler preg_match preg_grep preg_match_all
            setcookie setlocale sha1 sin sort fprintf sprintf srand str_pad str_repeat str_replace
            strcmp strftime strip_tags stripslashes strlen strpos strrpos strstr strtok strtolower
            strtotime strtoupper strtr strval substr substr_count time trim trigger_error
            uasort ucfirst lcfirst ucwords uksort uniqid unlink unserialize unset urldecode urlencode usort
            utf8_decode utf8_encode wordwrap cos array_rand array_unshift asin assert
            assert_options atan base_convert basename bin2hex bindec call_user_func_array
            connection_aborted connection_status crc32 decbin each empty fflush fileatime flock flush
            gethostbyaddr getrandmax gmmktime gmstrftime ip2long is_dir is_file
            levenshtein log log10 long2ip md5_file pow preg_quote prev rad2deg
            range readfile shuffle similar_text sqrt strcasecmp strcoll strcspn stristr strnatcasecmp
            strnatcmp strncasecmp strncmp strrchr strrev strspn substr_replace tan unpack version_compare
            gettype var_dump vprintf vsprintf touch tanh sinh sleep stripcslashes
            restore_error_handler rewind rewinddir exp lcg_value localtime addcslashes
            array_filter array_map array_merge_recursive array_multisort array_pad array_reduce array_walk
            atan2 fgetc fgetcsv fgetss filetype fscanf fstat array_change_key_case
            date_default_timezone_get ftruncate func_get_arg func_get_args func_num_args
            parse_ini_file parse_str is_executable memory_get_usage
            is_scalar nl2br ob_get_length ob_implicit_flush
            ob_clean printf cosh count_chars gethostbynamel getlastmod fpassthru create_function
            gettimeofday get_cfg_var get_resource_type hypot ignore_user_abort array_intersect_assoc
            is_link is_callable debug_print_backtrace stream_context_create next usleep array_sum
            file_get_contents str_word_count html_entity_decode
            array_combine array_walk_recursive header_remove
            str_split strpbrk substr_compare file_put_contents get_headers headers_list
            http_build_query scandir str_shuffle
            ob_get_clean array_diff_assoc glob debug_backtrace date_default_timezone_set sha1
            array_diff_key inet_pton array_product json_encode json_decode
            inet_ntop fputcsv is_nan is_finite is_infinite ob_flush array_chunk array_fill
            var_export array_intersect_key end sys_get_temp_dir error_get_last
            gethostbyname htmlspecialchars stat str_ireplace stripos key pi print set_exception_handler acos
            readgzfile ob_gzhandler gzcompress gzdeflate gzencode gzfile gzinflate gzuncompress gzclose gzopen gzwrite
            array_column array_fill_keys getimagesizefromstring hash_equals
            http_response_code memory_get_peak_usage password_get_info password_hash
            password_needs_rehash password_verify str_getcsv strripos spl_autoload_register
END;

        if (function_exists('imagecreatefromstring')) {
            $baseline_functions .= <<<END
                imagecreatefromgif imagegif
                imagepalettetotruecolor iptcembed iptcparse
                imagecolorallocatealpha imageistruecolor imagealphablending imagecolorallocate imagecolortransparent imagecopy
                imagecopyresampled imagecopyresized imagecreate imagecreatefrompng
                imagecreatefromjpeg imagecreatetruecolor imagecolorat imagecolorsforindex
                imagedestroy imagefill imagefontheight imagefontwidth imagesavealpha
                imagesetpixel imagestring imagesx imagesy imagestringup imagettftext imagetypes
                imagearc imagefilledarc imagecopymergegray imageline imageellipse imagefilledellipse
                imagechar imagefilledpolygon imagepolygon imagefilledrectangle imagerectangle imagefilltoborder
                imagegammacorrect imageinterlace imageloadfont imagepalettecopy imagesetbrush
                imagesetstyle imagesetthickness imagesettile imagetruecolortopalette
                imagecharup imagecolorclosest imagecolorclosestalpha imagecolorclosesthwb
                imagecolordeallocate imagecolorexact imagecolorexactalpha imagecolorresolve image_type_to_mime_type
                imagecolorresolvealpha imagecolorset imagecolorstotal imagecopymerge getimagesize image_type_to_extension imagefilter
                gd_info
END;

            // These ones are separately checked as extension checks
            $notused = <<<END
                imagecreatefromstring imagejpeg imagepng imagettfbbox
END;
        }

        foreach (preg_split('#\s+#', $baseline_functions) as $function) {
            if (trim($function) == '') {
                continue;
            }
            if (!php_function_allowed($function)) {
                $ext = ((strpos($function, 'image') !== false) && (!function_exists('imagettfbbox'))); // GD/TTF is non-optional, but if it's not there it's likely due to extension being missing
                $warning[] = do_lang_tempcode($ext ? 'NONPRESENT_EXTENSION_FUNCTION' : 'DISABLED_FUNCTION', escape_html($function));
            }
        }

        return $warning;
    }
}
