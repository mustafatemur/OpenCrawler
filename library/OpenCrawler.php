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
        $this -> bin['history'] = array();
        $this -> cookies = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'OpenCrawlerCookies.txt';
    }

    /**
     * Main function of the class, parses and extracts the link to follow
     * @param $url URL of the page to visit
     */
    public function loadUrl($url)
    {
        if (trim($url) == '')
        {
            return false;
        }
        
        $url = !preg_match('/^([a-z]+):\/\/(.*)/', $url) ? 'http://' . str_replace('://', null, $url) : $url;
        $url .= !@parse_url($url, PHP_URL_PATH) ? '/' : null;
        
        /**
         * Compatibility with the User-Agent browser without losing the special crawler
         */
        ini_set('user_agent', 'Mozilla/5.0 (compatible; ' . $this -> agent . '; +' . $this -> referer . '; Trident/4.0)');
        
        /**
         * Redirect via HTTP Location
         */
        $temp = $this -> parseHeaders($url);
        $counterHeaders = 0;
        while ($counterHeaders < 10 && ((isset($temp['Location']) && $temp['Location'] != $url) || (isset($temp['Content-Location']) && $temp['Content-Location'] != $url)))
        {
            if (!array_search($url, $this -> bin['history']))
            {
                $this -> bin['history'][] = $url;
                array_values(array_unique($this -> bin['history']));
            }
            
            if (isset($temp['Location']))
            {
                if (is_array($temp['Location']))
                {
                    $temp['Location'] = $temp['Location'][sizeof($temp['Location']) - 1];
                }
                $FullUri = $this -> completeUrl($url, $temp['Location']);
                
            }
            else
            {
                break;
            }
            
            $url = $FullUri;
            $temp = $this -> ParseHeaders($url);
        }
        
        if (!array_search($url, $this -> bin['history']))
        {
            $this -> bin['history'][] = $url;
        }
        
        $this -> handler['headers'] = $temp;
        unset($temp);
        
        $this -> handler['url'] =& $url;
        
        if ($key = array_search($url, $this -> bin['history']))
        {
            for ($c = $key - 1; ($c > 0 && $c > $key - 6); $c--)
            {
                if ($this -> bin['history'][$c] == $url)
                {
                    return $this -> loadNext();
                }
            }
        }
        
        $temp =& $this -> handler['headers']['Content-Type'];
        if (is_array($temp))
        {
            $temp = $temp[sizeof($temp) - 1];
        }
        
        if (!isset($temp) || !preg_match('/(x|ht)ml/', $temp))
        {
            return false;
        }
        
        /**
         * Robot access control
         */
        $this -> handler['robots'] = $this -> parseRobots($url);
        
        if (!$this -> crawlerAccess($url))
        {
            return false;
        }
        
        /**
         * Extraction of Contents
         */
        $this -> handler['DOMDocument'] = new DOMDocument;
        $this -> handler['DOMDocument'] -> loadHTML($this -> LoadContent($url));
        
        if ($metas = $this -> handler['DOMDocument'] -> getElementsByTagName('meta'))
        {
            for ($c = 0; $c < $metas -> length; $c++)
            {
                $meta = $metas -> item($c);
                
                if (strtolower($meta -> attributes -> getNamedItem("http-equiv") -> nodeValue) === "refresh")
                {
                    $metaContent = strtolower($meta -> attributes -> getNamedItem("content") -> nodeValue);
                    $newPath = preg_replace('/(.*)url=(.*)$/i', "$2", $metaContent);
                    $newUrl = $this -> completeUrl($url, $newPath);
                    if (!is_numeric($newPath) && $newUrl != $url && array_key_exists('scheme', parse_url($newUrl)))
                    {
                        return $this -> loadUrl($newUrl);
                    }
                    
                }
                elseif (strtolower($meta -> attributes -> getNamedItem("name") -> nodeValue) === "robots")
                {
                    $metaContent = strtolower($meta -> attributes -> getNamedItem("content") -> nodeValue);
                    $metaRobots = explode(',', $metaContent);
                    foreach ($metaRobots as $k => $v)
                    {
                        $metaRobots[$k] = trim($v);
                    }
                    
                }
            }
        }
        
        if ($links = $this -> handler['DOMDocument'] -> getElementsByTagName('link'))
        {
            for ($c = 0; $c < $links -> length; $c++)
            {
                $link = $links -> item($c);
                
                if (strtolower($link -> attributes -> getNamedItem("rel") -> nodeValue) === "canonical")
                {
                    $url = $this -> completeUrl($link -> attributes -> getNamedItem("href") -> nodeValue);
                }
                elseif (array_search(strtolower($link -> attributes -> getNamedItem("rel") -> nodeValue), array(
                    'appendix', 'chapter', 'contents', 'copyright', 'glossary', 'help', 'index', 'license', 'next', 'prev', 'previous', 'section', 'start', 
                    'subsection', 'tag', 'toc', 'home', 'directory', 'bibliography', 'cite', 'archive', 'archives', 'external'
                    )))
                {
                    $this -> pushLink($this -> completeUrl($link -> attributes -> getNamedItem("href") -> nodeValue));
                }
                
            }
        }
        
        if (!isset($metaRobots) || !array_search('nofollow', $metaRobots))
        {
            $this -> loadLinks($this -> handler['DOMDocument']);
        }
        
        if (isset($metaRobots) && array_search('noindex', $metaRobots))
        {
            return false;
        }
        
        $this -> bin['history'] = array_values(array_unique($this -> bin['history']));
        $this -> handler['a'] = array_values(array_unique($this -> handler['a']));
        
        ini_restore('user_agent');
        return true;
    }

    /**
     * robots.txt parsing
     * Parse the robots.txt file in the root directory and returns an array with the path set out in an orderly array on the User-Agent, 
     * the User-Agent default is the asterisk '*'
     * @link http://www.conman.org/people/spc/robots2.html
     * @param string $url
     * @return array
     */
    public function parseRobots($url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (isset($this -> bin['robots'][$host]))
        {
            return $this -> bin['robots'][$host];
        }
        
        $url = parse_url($url, PHP_URL_SCHEME) . '://' . $host . '/robots.txt';
        $robots = array();
        $UserAgent = '*';
        
        $this -> bin['robots'][$host] =& $robots;
        
        try
        {
            $rules = @file($url);
        }
        catch (Exception $Exception)
        {
            return array();
        }
        
        /**
         * @todo Controllo preg_match
         */
        for ($c = 0; $c < sizeof($rules); $c++)
        {
            $rule = trim(preg_replace('/(.*)?\#(.*)/', "$1", $rules[$c]));
            if ($rule == null)
            {
                continue;
            }
            elseif (preg_match('/^User-Agent:(.*)/i', $rule, $match))
            {
                $UserAgent = trim($match[1]);
            }
            elseif (preg_match('/^Disallow:(.*)/i', $rule, $match) && trim($match[1]) != null)
            {
                $robots[$UserAgent][] = trim($match[1]);
            }
            elseif (preg_match('/^Crawl\-delay:(.*)/i', $rule, $match) && trim($match[1]) != null)
            {
                $robots[$UserAgent]['Crawl-delay'] = trim($match[1]);
            }
        }
        return $robots;
    }

    /**
     * Extraction of Headers, if you can not extract Headers returns an empty array
     * @param string $url
     * @return array
     */
    function parseHeaders($url)
    {
        $this -> bin['headers'][$url] =& $headers;
        try
        {
            $headers = @get_headers($url, 1);
            return $headers;
        }
        catch (Exception $Exception)
        {
            return array();
        }
    }
    
    /**
     * Loading the contents of the page with cURL
     * @link http://php.net/curl
     * @param string $url
     * @return string
     */
    public function loadContent($url, $config = array())
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
        curl_setopt_array($curl, array_merge($options, $config));
        $content = curl_exec($curl);
        
        $this -> handler['CurlInfo'] = curl_getinfo($curl);
        $this -> bin['CurlInfo'][$url] =& $this -> handler['CurlInfo'];
        
        curl_close($curl);
        unset($curl, $options);
        return $content;
    }

    /**
     * Loading the next link in this bin[history]
     */
    public function loadNext()
    {
        $this -> _bin['history'] = array_values(array_unique($this -> _bin['history']));
        if (isset($this -> handler['a']))
        {
            $this -> handler['a'] = array_values(array_unique($this -> handler['a']));
        }
        
        if (sizeof($this -> _bin['history']) > $this -> history)
        {
            while (sizeof($this -> _bin['history']) > $this -> history)
            {
                $this -> _bin['history'] = array_shift($this -> _bin['history']);
            }
            $this -> _bin['history'] = array_values(array_unique($this -> _bin['history']));
        }
        
        $key = array_search($this -> handler['url'], $this -> _bin['history']);
        if (isset($this -> _bin['history'][$key + 1]))
        {
            if (
                parse_url($this -> _bin['history'][$key], PHP_URL_HOST) == parse_url($this -> _bin['history'][$key + 1], PHP_URL_HOST)
            )
            {
                $domain = parse_url($this -> _bin['history'][$key], PHP_URL_HOST);
                if (isset($this -> _bin['robots'][$domain]['*']['Crawl-delay']))
                {
                    $tempG = $this -> _bin['robots'][$domain]['*']['Crawl-delay'];
                }
                
                if (isset($this -> _bin['robots'][$domain][$this -> agent]['Crawl-delay']))
                {
                    $tempS = $this -> _bin['robots'][$domain][$this -> agent]['Crawl-delay'];
                }
                
                if (isset($tempS) && is_numeric($tempS))
                {
                    sleep($tempS);
                }
                elseif (isset($tempG) && is_numeric($tempG))
                {
                    sleep($tempG);
                }
                
            }
            $this -> loadUrl($this -> _bin['history'][$key + 1]);
        }
        else
        {
            return false;
        }
    }

    /**
     * Extraction of anchor links via the DOMDocument object
     * @param mixed &$DOMDocument DOMDocument Object
     */
    public function loadLinks(&$DOMDocument)
    {
        $bases = $DOMDocument -> getElementsByTagName('base');
        $base = ($bases -> length && $bases -> item(0) -> attributes -> getNamedItem("href") -> nodeValue) ? $bases -> item(0) -> attributes -> getNamedItem("href") -> nodeValue : $this -> handler['url'];
        
        $this -> handler['a'] = array();
        
        foreach ($DOMDocument -> getElementsByTagName('a') as $a)
        {
            $aHref = $this -> completeUrl($base, $a -> attributes -> getNamedItem("href") -> nodeValue);
            
            if (preg_match('/^(javascript\:)/', $aHref))
            {
                continue;
            }
            
            if (!isset($aHref) || array_search($aHref, $this -> handler['a']))
            {
                continue;
            }
            
            if (array_search('nofollow', explode(' ', $a -> attributes -> getNamedItem("rel") -> nodeValue)))
            {
                continue;
            }
            
            $this -> pushLink($aHref);
        }
        $this -> bin['history'] = array_values(array_unique($this -> bin['history']));
        $this -> handler['a'] = array_values(array_unique($this -> handler['a']));
    }

    /**
     * Loading a single Link
     * @param string $url Link to push in the internal stack
     */
    public function pushLink($url)
    {
        if (!preg_match('/^!/', parse_url($url, PHP_URL_FRAGMENT)))
        {
            $url = preg_replace('/^([^#]+)(#.+)$/', "$1", $url);
        }
        
        if (!isset($url) || array_search($url, $this -> handler['a']))
        {
            return false;
        }
        
        if (array_search($url, $this -> bin['history']) || $url == $this -> handler['url'])
        {
            return false;
        }
        
        if (!$this -> crawlerAccess($url))
        {
            return false;
        }
        
        if (array_key_exists('scheme', parse_url($url)) && preg_match('/^https?:/', $url))
        {
            $this -> handler['a'][] = trim($url);
            $this -> bin['history'][] = trim($url);
        }
    }

    /**
     * Check if the robot can visit a URL
     * @param string $url Address to be checked
     * @return bool
     */
    public function crawlerAccess($url)
    {
        $domain = parse_url($url, PHP_URL_HOST);
        $path = preg_replace('/^([a-z]+):\/\/([^\/]+)(.*)/', "$3", $url);
        foreach ($this -> bin['robots'][$domain] as $agent => $rules)
        {
            if (preg_match($agent, $this -> agent))
            {
                foreach ($this -> bin['robots'][$domain][$agent] as $k => $v)
                {
                    if (is_string($v) && !is_numeric($v) && !is_bool($v))
                    {
                        $line = str_replace('/', '\/', str_replace('\*', '.+', quotemeta($v)));
                        if (preg_match('/^' . $line . '/', $path))
                        {
                            return false;
                        }
                    }
                }
                break;
            }
        }
        return true;
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