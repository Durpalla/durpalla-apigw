<?php

use Illuminate\Http\Request;
use App\Services\OptionService;

// the excerpt
    function the_excerpt($content, $numwords = 50){
        $string = '';
        $string .= limit_to_numwords(strip_tags($content), $numwords);
        return $string;
    }

    function getOption($key, $default = null)
    {
//        $option = new OptionService();
//        return $option->get($key, $default);
        return $default;
    }

    function EtoB($str = null)
    {
        $eng_number = array(1,2,3,4,5,6,7,8,9,0,'');
        $ban_number = array('১','২','৩','৪','৫','৬','৭','৮','৯','০','');
        $converted = str_replace($eng_number, $ban_number, $str);
        return $converted;
    }

    function parseTemplate($string, $array)
    {
        foreach( $array as $key => $value ) {
            $string = preg_replace("/{" . $key ."}/i", $value, $string);
        }

        return $string;
    }

    function sendSMS($params): bool
    {
        if( $params['mobile'] && $params['message'] && getOption('sms_enabled', 0)) {

            $api_url = getOption('sms_api_url') . '?';
            if(getOption('sms_api_sender_key') !== null) {
                $api_url .= getOption('sms_api_sender_key') . '=' . getOption('sms_api_sender', 'Jolzan');
            }
            if(getOption('sms_api_username_key') !== null) {
                $api_url .= '&' . getOption('sms_api_username_key') . '=' . getOption('sms_api_username', 'Jolzan');
            }
            if(getOption('sms_api_sender_key') !== null) {
                $api_url .= '&' . getOption('sms_api_password_key') . '=' . getOption('sms_api_password', '');
            }
            if(getOption('sms_api_number_key') !== null) {
                $api_url .= '&' . getOption('sms_api_number_key') . '=' . $params['mobile'];
            }
            if(getOption('sms_api_message_key') !== null) {
                $api_url .= '&' . getOption('sms_api_message_key') . '=' . str_replace(' ', '+', $params['message'] );
            }
            if(getOption('sms_api_extra_key_1') !== null) {
                $api_url .= '&' . getOption('sms_api_extra_key_1') . '=' . getOption('sms_api_extra_1', '');
            }
            if(getOption('sms_api_extra_key_2') !== null) {
                $api_url .= '&' . getOption('sms_api_extra_key_2') . '=' . getOption('sms_api_extra_2', '');
            }
            if(getOption('sms_api_extra_key_3') !== null) {
                $api_url .= '&' . getOption('sms_api_extra_key_3') . '=' . getOption('sms_api_extra_3', '');
            }
            if(getOption('sms_api_extra_key_4') !== null) {
                $api_url .= '&' . getOption('sms_api_extra_key_4') . '=' . getOption('sms_api_extra_4', '');
            }

            try {
//             return $api_url;
                $ch = curl_init();
                // set the url
                curl_setopt($ch, CURLOPT_URL, $api_url);
                // Set the result output to be a string.
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                //Timeout after 7 seconds
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 7);
                //curl set useragent
                curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:19.0) Gecko/20100101 Firefox/19.0");
    //            curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type' => 'application/json',
                    'charset' => 'utf-8'
                ));

                $response = curl_exec( $ch );
//                var_dump(curl_getinfo($ch));

                // Check the return value of curl_exec(), too
                if ($response === false) {
                    throw new Exception(curl_error($ch), curl_errno($ch));
                }
                curl_close( $ch );
//                $response = json_decode($response);
//                if( $response[0]->success ) {
//                    return true;
//                }else {
//                    return false;
//                }
            } catch(Exception $e) {

                trigger_error(sprintf(
                    'Curl failed with error #%d: %s',
                    $e->getCode(), $e->getMessage()),
                    E_USER_ERROR);

            }
        }

        return false;
    }

    /**
     * Return nav-here if current path begins with this path.
     *
     * @param string $path
     * @return string
     */
    function isActive($path)
    {
        return Request::is($path) ? 'active' : '';
    }

    function niceSlug($string, $separator = '-')
    {
        $accents_regex = '~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i';
        $special_cases = array('&' => 'and', "'" => '');
        $string = mb_strtolower(trim($string), 'UTF-8');
        $string = str_replace(array_keys($special_cases), array_values($special_cases), $string);
        $string = preg_replace($accents_regex, '$1', htmlentities($string, ENT_QUOTES, 'UTF-8'));
        $string = preg_replace("/[^a-z0-9]/u", "$separator", $string);
        $string = preg_replace("/[$separator]+/u", "$separator", $string);
        return $string;
    }

    function notifyMe($userId, $message, $type = 'pending')
    {
        $notice = new \App\Notification;
        $notice->user_id = $userId;
        $notice->message = $message;
        $notice -> status = 0;
        $notice->type = $type;
        $notice->save();

        return ($notice->id) ? true : false;
    }

    function words($value, $words = 100, $end = '...')
    {
        return \Illuminate\Support\Str::words($value, $words, $end);
    }

    function array_key_sort($array, $on, $order=SORT_DESC){
        $sortable_array = array();

        if ( count( $array) > 0 ) {
            foreach ( $array as $k => $v ) {
                if ( is_array( $v ) ) {
                    foreach ( $v as $k2 => $v2 ) {
                        if ($k2 == $on) {
                            $sortable_array[$k] = $v2;
                        }
                    }
                } else {
                    $sortable_array[$k] = $v;
                }
            }
        }

        return $sortable_array;
    }

    //This is my group_by Array ** Array group by a default key 'Month'
    function _my_group_by_old( $array, $key = 'cabin_row' ){
        $arr = array();//declare new array
        if(count($array)){
            foreach($array as $k => $v){
                $arr[$v[$key]][] = $v;
            }
        }

        return $arr;
    }

    //This is my group_by Array ** Array group by a default key 'Month'
    function _my_group_by( $array, $key = 'cabin_row' ){
        $arr = [];//declare new array
        if(count($array)){
            foreach($array as $k => $v){
                $rowKey = $v[$key] ?? null;
                $posKey = $v['cabin_position'] ?? null;
                if ($rowKey === null || $rowKey === '' || $posKey === null || $posKey === '') {
                    continue;
                }
                $rowInt = (int) $rowKey;
                $posInt = (int) $posKey;
                // Drop obviously corrupt layout coordinates (see _my_layout caps).
                // Real launch layouts use cabin_position up to ~99; keep headroom.
                if ($rowInt < 1 || $rowInt > 24 || $posInt < 1 || $posInt > 120) {
                    continue;
                }
                $arr[$rowInt][$posInt] = $v;
            }
        }

        return $arr;
    }

    function _my_layout($arr)
    {
        if (!is_array($arr) || empty($arr)) {
            return [];
        }

        // Cap sparse grids — bad cabin_row / cabin_position values previously
        // expanded into huge arrays and OOM'd the trip endpoint.
        $maxCols = 24;
        $maxPos = 120;

        $hCol = min($maxCols, (int) max(array_keys($arr)));
        if ($hCol < 1) {
            return [];
        }

        $cols = [];
        for ($i = 1; $i <= $hCol; $i++) {
            if (!array_key_exists($i, $arr) || !is_array($arr[$i]) || empty($arr[$i])) {
                $cols[$i] = [];
                continue;
            }

            $col = $arr[$i];
            $keys = array_filter(array_keys($col), static fn ($k) => (int) $k >= 1 && (int) $k <= $maxPos);
            if ($keys === []) {
                $cols[$i] = [];
                continue;
            }
            $hRow = (int) max($keys);
            $cols[$i] = [];
            for ($j = 1; $j <= $hRow; $j++) {
                if (array_key_exists($j, $col)) {
                    $cols[$i][] = $col[$j];
                } else {
                    $cols[$i][] = [
                        'fare' => 0,
                        'cabin_type' => 'empty',
                    ];
                }
            }
        }

        return $cols;
    }

    function _my_layout_old($arr)
    {
        if(!is_null($arr)) {
            if(is_array($arr)) {
                $newArr = [];
                $hCol = max(array_keys($arr));
                $cols = [];
                for($i = 1; $i <= $hCol; $i++) {
                    if(array_key_exists($i, $arr)) {
                        $cols[$i] = [];
                        $hRow = max(array_keys($arr[$i]));
                        $col = $arr[$i];
                        for($j = 0; $j <= $hRow; $j++) {
                            if(array_key_exists($j, $col)) {
                                array_push($cols[$i], $col[$j]);
                            } else {
                                $cols[$i][$j] = [
                                    'cabin_type' => 'empty',
                                    'trip_id' => -1,
                                    'trip_date' => date('Y-m-d H:i:s'),
                                    'route_id' => -1,
                                    'launch_id' => -1,
                                    'launch_name' => '',
                                    'merchant_id' => -1,
                                    'cabin_id' => -1,
                                    'cabin_type_id' => -1,
                                    'cabin_floor' => -1,
                                    'cabin_no' => '',
                                    'cabin_fare' => -1,
                                    'cabin_is_ac' => -1,
                                    'capacity' => -1,
                                    'cabin_row' => -1,
                                    'cabin_position' => -1,
                                    'description' => '',
                                    'status' => -1,
                                    'cabin_class' => ''
                                ];
                            }
                        }
                    } else {
                        $cols[$i] = [];
                    }
                }
                return $cols;
            } else {
                return $arr;
            }
        } else {
            return $arr;
        }
    //        echo end(array_keys($arr));
    //        max(array_keys($arr));
    }

    /**
     * @param $timestamp
     * @return string
     */
    function get_time_ago($timestamp)
    {

        //date_default_timezone_set("Asia/dhaka");
        $time_ago = strtotime($timestamp);
        $current_time = time();
        $time_difference = $current_time - $time_ago;
        $seconds = $time_difference;

        $minutes = round($seconds / 60); // value 60 is seconds
        $hours = round($seconds / 3600); //value 3600 is 60 minutes * 60 sec
        $days = round($seconds / 86400); //86400 = 24 * 60 * 60;
        $weeks = round($seconds / 604800); // 7*24*60*60;
        $months = round($seconds / 2629440); //((365+365+365+365+366)/5/12)*24*60*60
        $years = round($seconds / 31553280); //(365+365+365+365+366)/5 * 24 * 60 * 60

        if ($seconds <= 60) {

            return "Just Now";

        } else if ($minutes <= 60) {

            if ($minutes == 1) {

                return "one minute ago";

            } else {

                return "$minutes minutes ago";

            }

        } else if ($hours <= 24) {

            if ($hours == 1) {

                return "an hour ago";

            } else {

                return "$hours hrs ago";

            }

        } else if ($days <= 7) {

            if ($days == 1) {

                return "yesterday";

            } else {

                return "$days days ago";

            }

        } else if ($weeks <= 4.3) {

            if ($weeks == 1) {

                return "a week ago";

            } else {

                return "$weeks weeks ago";

            }

        } else if ($months <= 12) {

            if ($months == 1) {

                return "a month ago";

            } else {

                return "$months months ago";

            }

        } else {

            if ($years == 1) {

                return "one year ago";

            } else {

                return "$years years ago";

            }
        }
    }

    function reviewAverage($reviews)
    {
        $reviewArr[] = 0;

        if ($reviews->count() > 0) :

            foreach ($reviews as $review) :
                $reviewArr[] = $review->rating . ", ";
            endforeach;

        endif;

        return number_format(array_sum($reviewArr), 1);

    }

    /**
     * @param $string
     * @param $repl
     * @param $limit
     * @return string
     */
    function addDotsToLongText($string, $repl, $limit)
    {
        if (strlen($string) > $limit) {
            return substr($string, 0, $limit) . $repl;
        } else {
            return $string;
        }
    }


    function getCategoryNameById($id)
    {
        if (isset($id)) {

            $parent = \App\Category::where('parent', null)
                ->where('id', $id)->first();
            if (!empty($parent)) {
                return $parent->name;
            } else {
                $child = App\Category::where('id', $id)->first();
                return ( $child ) ? $child->name : '';
            }

        }

    }

    function get_options($array, $parent = "", $indent = "")
    {
        $return = array();
        foreach ($array as $key => $val) {
            if ($val["parent"] == $parent) {
                $return[] = $indent . $val["id"];
                $return = array_merge($return, get_options($array, $val["id"], $indent));
            }
        }
        return $return;
    }

    function partitionArray( $arr, $p = 3 ) {
        //check array is an array
        if( is_array( $arr ) ) :

            //count the given array
            $listlen = count( $arr );

            //floor pertition
            $partlen = floor( $listlen / $p );
            $partrem = $listlen % $p;
            $partition = array();
            $mark = 0;

            //loop through array
            for ( $px = 0; $px < $p; $px++ ) {
                $incr = ($px < $partrem) ? $partlen + 1 : $partlen;
                $partition[$px] = array_slice( $arr, $mark, $incr );
                $mark += $incr;
            }
            return $partition;

        else :
            return $arr;
        endif;
    }


    ///for chat feature increase
    function formatUrlsInText($text)
    {
        // The Regular Expression filter
        $reg_exUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";

        // Check if there is a url in the text
        if (preg_match($reg_exUrl, $text, $url)) {

            // make the urls hyper links
            return preg_replace($reg_exUrl, "<a href=" . $url[0] . " title=" . $url[0] . " target='_blank'>.$url[0].</a> ", $text);

        } else {

            return $text;
        }
    }

    function check_base64_image($data)
    {

        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type))
        {
            $data = substr($data, strpos($data, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif

            if (in_array($type, [ 'jpg', 'jpeg', 'gif', 'png' ])) {
                return true;
            }

            $data = base64_decode($data);

            if ($data === false) {
                return false;
            }
        }
        return false;
    }


    function checkMessage($data)
    {
        //youtube video
        if (strpos($data, 'youtube') > 0) {
            $data = preg_replace("/\s*[a-zA-Z\/\/:\.]*youtube.com\/watch\?v=([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i", "<iframe width=\"200\" height=\"100\" src=\"//www.youtube.com/embed/$1\" frameborder=\"0\" allowfullscreen></iframe>", $data);

        } elseif (strpos($data, 'youtu') > 0) {
            $data = preg_replace("/\s*[a-zA-Z\/\/:\.]*youtube.com\/watch\?v=([a-zA-Z0-9\-_]+)([a-zA-Z0-9\/\*\-\_\?\&\;\%\=\.]*)/i", "<iframe width=\"200\" height=\"100\" src=\"//www.youtube.com/embed/$1\" frameborder=\"0\" allowfullscreen></iframe>", $data);

        }

//        //base64
        else if (check_base64_image($data))
        {
            $data ='<a href="'.$data.'" target="_blank"><img src="'.$data.'" style="overflow:auto; height:100px;width:170px;"></a>';
        }
        //normal text
        else
        {
            $data = formatUrlsInText($data);
        }

        return html_entity_decode($data);
    }


    function escapePhpString($target) {
        $replacements = array(
            "'" => '"',
            "\\" => '\\\\',
            "\r\n" => "\\r\\n",
            "\n" => "\\n"
        );
        return strtr($target, $replacements);
    }

//return currency sign
    function getCurrencySignByName($str)
    {
        if($str=='euro')
        {
            return '€';
        }
        elseif($str == 'afn')
        {
            return '؋';
        }
        else{
            return '$';
        }
    }

    //add http to url
    function addhttp($url) {
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "http://" . $url;
        }
        return $url;
    }


    function addMissingMonth($months, $array)
    {

        if (is_array($array)) {
            $data = $array;
        } else {
            $data = $array->toArray();
        }

        $newArr = array_merge($months, $array);
        ksort($newArr);
        return $newArr;
    }

    /**
     * Dump helper. Functions to dump variables to the screen, in a nicley formatted manner.
     * @author Joost van Veen
     * @version 1.0
     */
    if (!function_exists('dump')) {
        function dump ($var, $label = 'Dump', $echo = TRUE)
        {
            // Store dump in variable
            ob_start();
            var_dump($var);
            $output = ob_get_clean();

            // Add formatting
            $output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);
            $output = '<pre style="background: #FFFEEF; color: #000; border: 1px dotted #000; padding: 10px; margin: 10px 0; text-align: left;">' . $label . ' => ' . $output . '</pre>';

            // Output
            if ($echo == TRUE) {
                echo $output;
            }
            else {
                return $output;
            }
        }
    }

    if (!function_exists('dump_exit')) {
        function dump_exit($var, $label = 'Dump', $echo = TRUE) {
            dump ($var, $label, $echo);
            exit;
        }
    }

    /**
     * Public URL for user-uploaded files. Uses UPLOADS_PUBLIC_BASE_URL (CDN) when configured.
     */
    if (! function_exists('upload_asset')) {
        function upload_asset(?string $path): ?string
        {
            if ($path === null || $path === '') {
                return $path;
            }

            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            $normalized = ltrim(str_replace('\\', '/', $path), '/');
            $base = (string) config('uploads.public_base_url', '');

            if ($base !== '') {
                foreach ((array) config('uploads.cdn_path_prefixes', []) as $prefix) {
                    if (str_starts_with($normalized, ltrim($prefix, '/'))) {
                        return $base.'/'.$normalized;
                    }
                }
            }

            return asset($path);
        }
    }

    if (! function_exists('merchant_auth_user')) {
        /**
         * Merchant owner or staff from merchant / merchant_staff session or API guards.
         */
        function merchant_auth_user(): \App\Models\Merchant|\App\Models\MerchantStaff|null
        {
            return \App\Support\MerchantContext::user();
        }
    }

    if (! function_exists('current_merchant_id')) {
        /**
         * Resolved merchants.id for the current merchant owner or staff member.
         */
        function current_merchant_id(): ?int
        {
            return \App\Support\MerchantContext::currentMerchantId();
        }
    }
