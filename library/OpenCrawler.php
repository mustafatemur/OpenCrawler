<?php

/**
 * 
 * OpenCrawler is a PHP crawler in a standalone file
 * @author Emanuele Minotto
 *
 */
class OpenCrawler extends Zend_Controller_Plugin_Abstract
{
    /**
     * Global variable of this class
     * Contains information regarding the current URL
     * @var array
     */
    public $handler = array();
    /**
     * Global variable that contains the secondary information
     * Contains information about the URLs visited on the duration of the instance
     * @var array
     */
    protected $bin = array();
    /**
     * 
     * Agent (used in the User Agent and the control of robots.txt)
     * @var string
     */
    private $agent = 'OpenCrawler';
    /**
     * 
     * cURL referer for the extraction of content
     * @var string
     */
    private $referer = 'https://github.com/EmanueleMinotto/OpenCrawler';
    /**
     * given the structure of the OpenCrawler history is impossible to determine the exact date of the visit of a given page, 
     * so we need for the Re-visit policy set a minimum size limit for the array
     * @link http://en.wikipedia.org/wiki/Web_crawler#Re-visit_policy
     * @var int
     */
    private $history = 15000;
    /**
     * 
     * Cookies file & directory
     * @var string
     */
    private $cookies;

    /**
     * Class constructor
     */
    function __construct()
    {
        $this -> _bin['history'] = array();
        $this -> cookies = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'OpenCrawlerCookies.txt';
    }

    public function loadUrl($url)
    {
        
    }

    public function parseRobots($url)
    {
        
    }

    /**
     * Extraction of Headers, if you can not extract Headers returns an empty array
     * @param string $url
     * @return array
     */
    function parseHeaders($url)
    {
        $this -> _bin['headers'][$url] =& $headers;
        try {
            $headers = @get_headers($url, 1);
            return $headers;
        } catch (Exception $Exception) {
            return array();
        }
    }
    
    /**
     * Loading the contents of the page with cURL
     * @link http://php.net/curl
     * @param string $url
     * @return string
     */
    public function loadContent($url)
    {
        $curl = curl_init();
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIESESSION => true,
            CURLOPT_COOKIEJAR => $this -> cookies,
            CURLOPT_COOKIEFILE => $this -> cookies,
            CURLOPT_REFERER => $this -> referer,
            CURLOPT_USERAGENT => ini_get('user_agent'),
            CURLOPT_TIMEOUT => 300,
            CURLOPT_MAXREDIRS => 10
        );
        
        curl_setopt_array($curl, $options);
        $content = curl_exec($curl);
        
        $this -> mOpenCrawler['CurlInfo'] = curl_getinfo($curl);
        $this -> _bin['CurlInfo'][$url] =& $this -> mOpenCrawler['CurlInfo'];
        
        curl_close($curl);
        
        unset($curl, $options);
        
        return $content;
    }

    public function loadNext()
    {
        
    }

    public function loadLinks(&$DOMDocument)
    {
        
    }

    public function pushLink($url)
    {
        
    }

    public function crawlerAccess($url)
    {
        
    }

    /**************************************************************
    These functions aim to implement a relative URL resolver
    according to the RFC 1808 specification.
    Copyright (C) 2007 David Doran (http://www.daviddoranmedia.com/)
    ****************************************************************/
    public function absoluteUrl($base, $path = null, $fragment = false)
    {
        $path = trim($path);
        $base = trim($base);
        $base_parse = @parse_url($base);

        $this -> filloutParse( $base_parse );

        $GOTO_SEVEN = FALSE;

        //Output variables
        $u = array();
        $u['scheme'] = '';
        $u['host'] = '';
        $u['path'] = '';
        $u['query'] = '';
        $u['params'] = '';
        $u['fragment'] = '';

        /* 1: The base URL is established according to the rules of Section 3.
         *    If the base URL is the empty string (unknown), the embedded URL
         *    is interpreted as an absolute URL and we are done.
         */
        if ($base == '' || $base_parse == false)
        {
            if ($path == '')
            {
                return false;
            }
            else
            {
                return $path;
            }
        }

        /* 2: Both the base and embedded URLs are parsed into their component
              parts as described in Section 2.4.
              1: If the embedded URL is entirely empty, it inherits the entire
                  base URL (i.e., is set equal to the base URL) and we are done.
              2: If the embedded URL starts with a scheme name, it is
                  interpreted as an absolute URL and we are done.
              3: Otherwise, the embedded URL inherits the scheme of the base URL.
        */
        $parse_url = @parse_url($path);
        if ($parse_url == false)
        {
            if ($path == '#')
            {
                return $base;
            }
            else
            {
                return false;
            }
        }

        $this -> filloutParse($parse_url);

        if (!is_array($parse_url))
        {
            // # means the current url!
            if ($path == '#')
            {
                return $base;
            }
            else
            {
                return false;
            }
        }
        if ($path == '')
        {
            return $base;
        }
        if (isset($parse_url['scheme']) && trim($parse_url['scheme']) != '')
        {
            if (!isset($parse_url['path']) || $parse_url['path'] == '')
            {
                $path .= '/';
            }
            return $path;
        }

        //Set the scheme equal to the base scheme
        $u['scheme'] = $base_parse['scheme'];
        $u['query'] = $parse_url['query'];
        $u['fragment'] = $parse_url['fragment'];

        /* 3: If the embedded URL's <net_loc> is non-empty, we skip to Step 7.
              Otherwise, the embedded URL inherits the <net_loc> (if any) of the base URL.
         */
        if (isset($parse_url['host']) && trim($parse_url['host']) != '')
        {
            //SKIP TO SECTION SEVEN
            $u['host'] = $parse_url['host'];
            $GOTO_SEVEN = true;
        }
        else
        {
            $u['host'] = $base_parse['host'];
        }

        /* 4: If the embedded URL path is preceded by a slash "/",
              the path is not relative and we skip to Step 7.
         */
        if (!$GOTO_SEVEN)
        {
            if (isset($parse_url['path'][0]) && $parse_url['path'][0] == '/')
            {
                //SKIP TO SECTION SEVEN
                $u['path'] = $parse_url['path'];
                $GOTO_SEVEN = true;
            }
        }


        /* 5: If the embedded URL path is empty (and not preceded by a slash),
              then the embedded URL inherits the base URL path, and
              1: if the embedded URL's <params> is non-empty, we skip to step 7;
                  otherwise, it inherits the <params> of the base URL (if any) and
              2: if the embedded URL's <query> is non-empty, we skip to step 7;
                  otherwise, it inherits the <query> of the base URL (if any) and we skip to step 7.
        */
        if (!$GOTO_SEVEN)
        {
            if (!isset($parse_url['path']) || $parse_url['path'] == '')
            {
                $u['path'] = $base_parse['path'];
                if (isset($parse_url['query']) && $parse_url['query'] != '')
                {
                    $u['query'] = $parse_url['query'];
                    $GOTO_SEVEN = true;
                }
                else
                {
                    $u['query'] = $base_parse['query'];
                    $GOTO_SEVEN = true;
                }
            }
        }

        /* 6: The last segment of the base URL's path (anything following the rightmost
              slash "/", or the entire path if no slash is present) is removed and the
              embedded URL's path is appended in its place.
              The following operations are then applied, in order, to the new path:
         */
        if (!$GOTO_SEVEN)
        {
            $base_path_strlen = ((isset($base_parse['path']))?( strlen( $base_parse['path'] ) ):( 0 ));
            $proc_path = '';
            //$exit_for = true;
            for ($i = ($base_path_strlen-1); $i > 0; $i--)
            {
                if ($base_parse['path'][$i] == '/')
                {
                    $proc_path = substr($base_parse['path'], 0, $i);
                    break;
                }
            }
            $u['path'] = ((!isset($proc_path[0]) || $proc_path[0] != '/') ? '/' : '') . $proc_path . (($parse_url['path'][0] != '/') ? '/' : '') . $parse_url['path'];
            $path_parse_array = array();
            $path_parse = $this -> parseSegments($u['path']);
            $path_parse_len = count($path_parse);
            $path_parse_keys = array_keys($path_parse);
            for ($i = 0; $i < $path_parse_len; $i++)
            {
                $cur = $path_parse[$path_parse_keys[$i]];
                if ($cur == '..')
                {
                    if (isset($path_parse_keys[$i - 1]))
                    {
                        unset($path_parse[$path_parse_keys[$i]]);
                        unset($path_parse[$path_parse_keys[$i - 1]]);
                        $i = $i - 2;
                        $path_parse_len = count($path_parse);
                        $path_parse_keys = array_keys( $path_parse );
                    }
                    else
                    {
                        unset($path_parse[$path_parse_keys[$i]]);
                        $i = $i - 1;
                        $path_parse_len = count($path_parse);
                        $path_parse_keys = array_keys($path_parse);
                    }
                }
                elseif ($cur == '.')
                {
                    unset($path_parse[$path_parse_keys[$i]]);
                    $i = $i - 1;
                    $path_parse_len = count($path_parse);
                    $path_parse_keys = array_keys( $path_parse );
                }
            }
            if ($path_parse_len > 0)
            {
                $u['path'] = '/' . implode('/', $path_parse);
            }
            else
            {
                $u['path'] = '/';
            }
        }
        //////////////////////////////
        //** THIS IS NUMBER SEVEN!!
        //////////////////////////////
        $frag = (($u['fragment'] != '' && $fragment != true) ? '#'. $u['fragment'] : '');
        return ($finish_url = $u['scheme'] . '://' . $u['host'] . $u['path'] .($u['query'] != '' ? '?'. $u['query'] : '') . $frag);
    }
     
    private function filloutParse(&$parse_array)
    {
        if (!isset($parse_array['scheme']))
        {
            $parse_array['scheme'] = '';
        }
        if (!isset($parse_array['host']))
        {
            $parse_array['host'] = '';
        }
        if (!isset($parse_array['path']))
        {
            $parse_array['path'] = '';
        }
        if (!isset($parse_array['query']))
        {
            $parse_array['query'] = '';
        }
        if (!isset($parse_array['fragment']))
        {
            $parse_array['fragment'] = '';
        }
    }
    
    private function parseSegments($str_path)
    {
        $str_path = trim($str_path);
        $str_len = strlen($str_path);
        $str_array = array();
        $str_array[0] = '';
        $str_num = 0;
        
        for ($i = 0; $i < $str_len; $i++)
        {
            $chr = $str_path[$i];
            if ($chr != '/')
            {
                $str_array[$str_num] .= $chr;
                continue;
            }
            if ($chr == '/' && $i < ($str_len-1))
            {
                $str_num++;
                $str_array[$str_num] = '';
                if (isset($str_array[$str_num-1]) && $str_array[$str_num - 1] == '')
                {
                    unset( $str_array[$str_num-1] );
                }
                continue;
            }
            if ($chr == '/' && count($str_array) < 1)
            {
                continue;
            }
        }
        return $str_array;
    }
}