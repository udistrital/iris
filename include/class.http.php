<?php
/*********************************************************************
    class.http.php

    Http helper.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
class Http {

    static function header_code_verbose($code) {
        switch($code):
        case 200: return '200 OK';
        case 201: return '201 Created';
        case 204: return '204 No Content';
        case 205: return '205 Reset Content';
        case 400: return '400 Bad Request';
        case 401: return '401 Unauthorized';
        case 403: return '403 Forbidden';
        case 404: return '404 Not Found';
        case 405: return '405 Method Not Allowed';
        case 416: return '416 Requested Range Not Satisfiable';
        case 418: return "418 I'm a teapot";
        case 422: return '422 Unprocessable Entity';
        default:  return '500 Internal Server Error';
        endswitch;
    }

    static function response($code,$content=false,$contentType='text/html',$charset='UTF-8') {

        header('HTTP/1.1 '.Http::header_code_verbose($code));
		header('Status: '.Http::header_code_verbose($code)."\r\n");
		header("Connection: Close\r\n");
        $ct = "Content-Type: $contentType";
        if ($charset)
            $ct .= "; charset=$charset";
        header($ct);
        if (is_string($content)) {
            header('Content-Length: '.strlen($content)."\r\n\r\n");
            print $content;
            exit;
        }
    }

    /*
     *  Flush the content to requester without exiting
     *
     */
    static function flush($code, $content, $contentType='text/html', $charset='UTF-8') {
        self::response($code, null, $contentType, $charset);
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-Length: '.strlen($content)."\r\n\r\n");
        print $content;
        // Flush the request buffer
        while(@ob_end_flush());
        flush();
        // Terminate the request
        if (function_exists('fastcgi_finish_request'))
            fastcgi_finish_request();
    }

    static function redirect($url,$delay=0,$msg='') {

        $iis = strpos($_SERVER['SERVER_SOFTWARE'], 'IIS') !== false;
        @list($name, $version) = explode('/', $_SERVER['SERVER_SOFTWARE']);
        // Legacy code for older versions of IIS that would not emit the
        // correct HTTP status and headers when using the `Location`
        // header alone
        if ($iis && version_compare($version, '7.0', '<')) {
            header("Refresh: $delay; URL=$url");
        }else{
            header("Location: $url");
        }
        print('<html></html>');
        flush();
        ob_flush();
        exit;
    }

    static function cacheable($etag, $modified=false, $ttl=3600) {
        // Thanks, http://stackoverflow.com/a/1583753/1025836
        // Timezone doesn't matter here — but the time needs to be
        // consistent round trip to the browser and back.
        if ($modified) {
            $last_modified = strtotime($modified." GMT");
            header("Last-Modified: ".date('D, d M Y H:i:s', $last_modified)." GMT", false);
        }
        header('ETag: "'.$etag.'"');
        header("Cache-Control: private, max-age=$ttl");
        header('Expires: ' . gmdate('D, d M Y H:i:s', Misc::gmtime() + $ttl)." GMT");
        header('Pragma: private');
        if (($modified && @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $last_modified)
            || @trim($_SERVER['HTTP_IF_NONE_MATCH'], '" ') == $etag) {
                header("HTTP/1.1 304 Not Modified");
                exit();
        }
    }

    /**
     * Creates the filename=... part of the Content-Disposition header. This
     * is browser dependent, so the user agent is inspected to determine the
     * best encoding format for the filename
     */
    static function getDispositionFilename($filename) {
        $user_agent = strtolower ($_SERVER['HTTP_USER_AGENT']);
        if (false !== strpos($user_agent,'msie')
                && false !== strpos($user_agent,'win'))
            return 'filename='.rawurlencode($filename);
        elseif (false !== strpos($user_agent, 'safari')
                && false === strpos($user_agent, 'chrome'))
            // Safari and Safari only can handle the filename as is
            return 'filename='.str_replace(',', '', $filename);
        else
            // Use RFC5987
            return "filename*=UTF-8''".rawurlencode($filename);
    }

    static function download($filename, $type, $data=null, $disposition='attachment') {
        if (strpos($type, 'image/') !== false && preg_match('/image\/.*\+.*/', $type))
          $disposition='attachment';

        header('Pragma: private');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Content-Type: '.$type);
        header(sprintf('Content-Disposition: %s; %s',
            $disposition,
            self::getDispositionFilename(basename($filename))));
        header('Content-Transfer-Encoding: binary');
        if ($data !== null) {
            header('Content-Length: '.strlen($data));
            print $data;
            exit;
        }
    }

    static function build_query($vars, $encode=true, $separator='&amp;') {

        if (!$vars)
            return '';

        if ($encode)
            $vars = Format::htmlchars($vars);

        return http_build_query($vars, '', $separator);
    }

    static function build_clean_query($vars, $unsetDir, $unsetSort, $unsetPage) {

        if (!$vars)
            return '';
        if ($unsetDir)
            unset($vars['dir']);
        if ($unsetSort)
            unset($vars['sort']);
        if ($unsetPage)
            unset($vars['p']);

        $filteredParams = array_filter($vars, function($value) {
            return !empty($value);
        });

       return self::build_query($filteredParams);
    }

    static function domain() {
        $domain = null;
        if (isset($_SERVER['HTTP_HOST'])
                && strpos($_SERVER['HTTP_HOST'], '.') !== false
                && !Validator::is_ip($_SERVER['HTTP_HOST']))
            // Remote port specification, as it will make an invalid domain
            list($domain) = explode(':', $_SERVER['HTTP_HOST']);

        return $domain;
    }

    // Get current url
    static function url() {
        // Scheme + Host.
        $https =  osTicket::is_https();
        $url  = sprintf('http%s://%s',
                $https ? 's' : '',
                $_SERVER['HTTP_HOST']);
        //  Add Port if not 80 and not https
        $port = osTicket::get_client_port();
        if ($port && $port != 80 && !$https && !str_contains($url, $port))
            $url .= ":{$port}";
        // Path + Query string
        if (isset($_SERVER['REQUEST_URI']))
            $url  .= $_SERVER['REQUEST_URI'];
        else
            $url  .= sprintf('%s?$s',
                    $_SERVER['PHP_SELF'], $_SERVER['QUERY_STRING']);

        return $url;
    }

    // Parse path from given url or the current url
    static function url_path(string $url = null, bool $htmlentities = true) {
        $path = parse_url($url ?: self::url(), PHP_URL_PATH);
        return ($htmlentities && $path)
            ? htmlentities($path) : $path;
    }

    // Parse query string from current url
    static function query_string(string $url = null) {
        return parse_url($url ?: self::url(), PHP_URL_QUERY);
    }

    // Refresh url
    static function refresh_url(array $queryFilter = []) {
        $url = self::url();
        $refresh_url = self::url_path($url);
        if (($qs=self::query_string($url)))
            $refresh_url .= sprintf('?%s',
                    Format::http_query_string($qs, $queryFilter));
        return $refresh_url;
    }
}
?>
