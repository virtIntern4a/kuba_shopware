<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

/**
 * This class is highly based on Zend_Controller_Request_Http
 *
 * @see https://github.com/zendframework/zf1/blob/release-1.12.20/library/Zend/Controller/Request/Abstract.php
 * @see https://github.com/zendframework/zf1/blob/release-1.12.20/library/Zend/Controller/Request/Http.php
 */
class Enlight_Controller_Request_RequestHttp implements Enlight_Controller_Request_Request
{
    /**
     * Scheme for http
     */
    const SCHEME_HTTP = 'http';

    /**
     * Scheme for https
     */
    const SCHEME_HTTPS = 'https';
    /**
     * @var string[]
     */
    protected $validDeviceTypes = [
        'desktop',
        'tablet',
        'mobile',
    ];

    /**
     * Has the action been dispatched?
     *
     * @var bool
     */
    protected $_dispatched = false;

    /**
     * Module
     *
     * @var string
     */
    protected $_module;

    /**
     * Module key for retrieving module from params
     *
     * @var string
     */
    protected $_moduleKey = 'module';

    /**
     * Controller
     *
     * @var string
     */
    protected $_controller;

    /**
     * Controller key for retrieving controller from params
     *
     * @var string
     */
    protected $_controllerKey = 'controller';

    /**
     * Action
     *
     * @var string
     */
    protected $_action;

    /**
     * Action key for retrieving action from params
     *
     * @var string
     */
    protected $_actionKey = 'action';

    /**
     * Request parameters
     *
     * @var array
     */
    protected $_params = [];

    /**
     * Allowed parameter sources
     *
     * @var array
     */
    protected $_paramSources = ['_GET', '_POST'];

    /**
     * REQUEST_URI
     *
     * @var string;
     */
    protected $_requestUri;

    /**
     * Base URL of request
     *
     * @var string
     */
    protected $_baseUrl;

    /**
     * Base path of request
     *
     * @var string
     */
    protected $_basePath;

    /**
     * PATH_INFO
     *
     * @var string
     */
    protected $_pathInfo = '';

    /**
     * Raw request body
     *
     * @var string|false
     */
    protected $_rawBody;

    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var \Shopware\Components\DispatchFormatHelper
     */
    private $nameFormatter;

    /**
     * Constructor
     *
     * If a $uri is passed, the object will attempt to populate itself using
     * that information.
     *
     * @param string
     */
    public function __construct($uri = null)
    {
        if ($uri === null) {
            $this->setRequestUri();
        } else {
            $uri = Zend_Uri::factory($uri);
            if (!$uri->valid()) {
                throw new RuntimeException('Invalid URI provided to constructor');
            }

            $path = $uri->getPath();
            $query = $uri->getQuery();
            if (!empty($query)) {
                $path .= '?' . $query;
            }
            $this->setRequestUri($path);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * {@inheritdoc}
     */
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function __isset($key)
    {
        return $this->has($key);
    }

    /**
     * @return \Shopware\Components\DispatchFormatHelper
     */
    public function getNameFormatter()
    {
        if ($this->nameFormatter === null) {
            $this->nameFormatter = Shopware()->Container()->get('shopware.components.dispatch_format_helper');
        }

        return $this->nameFormatter;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($attribute, $default = null)
    {
        if (array_key_exists($attribute, $this->attributes) === false) {
            return $default;
        }

        return $this->attributes[$attribute];
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($attribute, $value)
    {
        $this->attributes[$attribute] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function unsetAttribute($attribute)
    {
        unset($this->attributes[$attribute]);
    }

    /**
     * {@inheritdoc}
     */
    public function setSecure($value = true)
    {
        $_SERVER['HTTPS'] = $value ? 'on' : null;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRemoteAddress($address)
    {
        $_SERVER['REMOTE_ADDR'] = $address;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setHttpHost($host)
    {
        $_SERVER['HTTP_HOST'] = $host;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setHeader($header, $value)
    {
        $temp = strtoupper(str_replace('-', '_', $header));
        $_SERVER['HTTP_' . $temp] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getModuleName()
    {
        if ($this->_module === null) {
            $module = $this->getParam($this->getModuleKey());
            if ($module) {
                $this->_module = strtolower($this->getNameFormatter()->formatNameForRequest($module));
            }
        }

        return $this->_module;
    }

    /**
     * {@inheritdoc}
     */
    public function getDeviceType()
    {
        $deviceType = strtolower($this->getCookie('x-ua-device', 'desktop'));

        return in_array($deviceType, $this->validDeviceTypes) ? $deviceType : 'desktop';
    }

    /**
     * {@inheritdoc}
     */
    public function setModuleName($value)
    {
        $this->_module = strtolower(trim($value));

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getControllerName()
    {
        if ($this->_controller === null) {
            $this->_controller = $this->getNameFormatter()->formatNameForRequest($this->getParam($this->getControllerKey()), true);
        }

        return $this->_controller;
    }

    /**
     * {@inheritdoc}
     */
    public function setControllerName($value)
    {
        $this->_controller = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getActionName()
    {
        if ($this->_action === null) {
            $this->_action = $this->getNameFormatter()->formatNameForRequest($this->getParam($this->getActionKey()));
        }

        return $this->_action;
    }

    /**
     * {@inheritdoc}
     */
    public function setActionName($value)
    {
        $this->_action = $value;
        /*
         * @see ZF-3465
         */
        if ($value === null) {
            $this->setParam($this->getActionKey(), $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getModuleKey()
    {
        return $this->_moduleKey;
    }

    /**
     * {@inheritdoc}
     */
    public function setModuleKey($key)
    {
        $this->_moduleKey = (string) $key;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getControllerKey()
    {
        return $this->_controllerKey;
    }

    /**
     * {@inheritdoc}
     */
    public function setControllerKey($key)
    {
        $this->_controllerKey = (string) $key;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getActionKey()
    {
        return $this->_actionKey;
    }

    /**
     * {@inheritdoc}
     */
    public function setActionKey($key)
    {
        $this->_actionKey = (string) $key;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserParams()
    {
        return $this->_params;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserParam($key, $default = null)
    {
        if (isset($this->_params[$key])) {
            return $this->_params[$key];
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function setParam($key, $value)
    {
        $key = (string) $key;

        if (($value === null) && isset($this->_params[$key])) {
            unset($this->_params[$key]);
        } elseif ($value !== null) {
            $this->_params[$key] = $value;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function clearParams()
    {
        $this->_params = [];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDispatched($flag = true)
    {
        $this->_dispatched = $flag ? true : false;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isDispatched()
    {
        return $this->_dispatched;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        switch (true) {
            case isset($this->_params[$key]):
                return $this->_params[$key];
            case isset($_GET[$key]):
                return $_GET[$key];
            case isset($_POST[$key]):
                return $_POST[$key];
            case isset($_COOKIE[$key]):
                return $_COOKIE[$key];
            case $key == 'REQUEST_URI':
                return $this->getRequestUri();
            case $key == 'PATH_INFO':
                return $this->getPathInfo();
            case isset($_SERVER[$key]):
                return $_SERVER[$key];
            case isset($_ENV[$key]):
                return $_ENV[$key];
            default:
                return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        throw new RuntimeException('Setting values in superglobals not allowed; please use setParam()');
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        switch (true) {
            case isset($this->_params[$key]):
                return true;
            case isset($_GET[$key]):
                return true;
            case isset($_POST[$key]):
                return true;
            case isset($_COOKIE[$key]):
                return true;
            case isset($_SERVER[$key]):
                return true;
            case isset($_ENV[$key]):
                return true;
            default:
                return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setQuery($spec, $value = null)
    {
        if (!is_array($spec) && $value === null) {
            unset($_GET[$spec]);

            return $this;
        }

        if (is_array($spec) && empty($spec)) {
            $_GET = [];

            return $this;
        }

        if (($value === null) && !is_array($spec)) {
            throw new RuntimeException('Invalid value passed to setQuery(); must be either array of values or key/value pair');
        }
        if (($value === null) && is_array($spec)) {
            /** @var array $spec */
            foreach ($spec as $key => $value) {
                $this->setQuery($key, $value);
            }

            return $this;
        }
        $_GET[(string) $spec] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery($key = null, $default = null)
    {
        if ($key === null) {
            return $_GET;
        }

        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function replacePost($data)
    {
        $_POST = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function setPost($spec, $value = null)
    {
        if (!is_array($spec) && $value === null) {
            unset($_POST[$spec]);

            return $this;
        }

        if (is_array($spec) && empty($spec)) {
            $_POST = [];

            return $this;
        }

        if (($value === null) && !is_array($spec)) {
            throw new RuntimeException('Invalid value passed to setPost(); must be either array of values or key/value pair');
        }
        if (($value === null) && is_array($spec)) {
            foreach ($spec as $key => $value) {
                $this->setPost($key, $value);
            }

            return $this;
        }
        $_POST[(string) $spec] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPost($key = null, $default = null)
    {
        if ($key === null) {
            return $_POST;
        }

        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getCookie($key = null, $default = null)
    {
        if ($key === null) {
            return $_COOKIE;
        }

        return isset($_COOKIE[$key]) ? $_COOKIE[$key] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getServer($key = null, $default = null)
    {
        if ($key === null) {
            return $_SERVER;
        }

        return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getEnv($key = null, $default = null)
    {
        if ($key === null) {
            return $_ENV;
        }

        return isset($_ENV[$key]) ? $_ENV[$key] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function setRequestUri($requestUri = null)
    {
        if ($requestUri === null) {
            if (
                // IIS7 with URL Rewrite: make sure we get the unencoded url (double slash problem)
                isset($_SERVER['IIS_WasUrlRewritten'])
                && $_SERVER['IIS_WasUrlRewritten'] == '1'
                && isset($_SERVER['UNENCODED_URL'])
                && $_SERVER['UNENCODED_URL'] != ''
            ) {
                $requestUri = $_SERVER['UNENCODED_URL'];
            } elseif (isset($_SERVER['REQUEST_URI'])) {
                $requestUri = $_SERVER['REQUEST_URI'];
                // Http proxy reqs setup request uri with scheme and host [and port] + the url path, only use url path
                $schemeAndHttpHost = $this->getScheme() . '://' . $this->getHttpHost();
                if (strpos($requestUri, $schemeAndHttpHost) === 0) {
                    $requestUri = substr($requestUri, strlen($schemeAndHttpHost));
                }
            } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0, PHP as CGI
                $requestUri = $_SERVER['ORIG_PATH_INFO'];
                if (!empty($_SERVER['QUERY_STRING'])) {
                    $requestUri .= '?' . $_SERVER['QUERY_STRING'];
                }
            } else {
                return $this;
            }
        } elseif (!is_string($requestUri)) {
            return $this;
        } else {
            // Set GET items, if available
            if (($pos = strpos($requestUri, '?')) !== false) {
                // Get key => value pairs and set $_GET
                $query = substr($requestUri, $pos + 1);
                parse_str($query, $vars);
                $this->setQuery($vars);
            }
        }

        $this->_requestUri = $requestUri;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestUri()
    {
        if (empty($this->_requestUri)) {
            $this->setRequestUri();
        }

        return $this->_requestUri;
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseUrl($baseUrl = null)
    {
        if (($baseUrl !== null) && !is_string($baseUrl)) {
            return $this;
        }

        if ($baseUrl !== null) {
            $this->_baseUrl = rtrim($baseUrl, '/');

            return $this;
        }

        $filename = isset($_SERVER['SCRIPT_FILENAME']) ? basename($_SERVER['SCRIPT_FILENAME']) : '';

        if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $filename) {
            $baseUrl = $_SERVER['SCRIPT_NAME'];
        } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $filename) {
            $baseUrl = $_SERVER['PHP_SELF'];
        } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $filename) {
            $baseUrl = $_SERVER['ORIG_SCRIPT_NAME']; // 1and1 shared hosting compatibility
        } else {
            // Backtrack up the script_filename to find the portion matching
            // php_self
            $path = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
            $file = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
            $segs = explode('/', trim($file, '/'));
            $segs = array_reverse($segs);
            $index = 0;
            $last = count($segs);
            $baseUrl = '';
            do {
                $seg = $segs[$index];
                $baseUrl = '/' . $seg . $baseUrl;
                ++$index;
            } while (($last > $index) && (($pos = strpos($path, $baseUrl)) !== false) && ($pos != 0));
        }

        // Does the baseUrl have anything in common with the request_uri?
        $requestUri = $this->getRequestUri();

        if (strpos($requestUri, $baseUrl) === 0) {
            // full $baseUrl matches
            $this->_baseUrl = $baseUrl;

            return $this;
        }

        if (strpos($requestUri, dirname($baseUrl)) === 0) {
            // directory portion of $baseUrl matches
            $this->_baseUrl = rtrim(dirname($baseUrl), '/');

            return $this;
        }

        $truncatedRequestUri = $requestUri;
        if (($pos = strpos($requestUri, '?')) !== false) {
            $truncatedRequestUri = substr($requestUri, 0, $pos);
        }

        $basename = basename($baseUrl);
        if (empty($basename) || !strpos($truncatedRequestUri, $basename)) {
            // no match whatsoever; set it blank
            $this->_baseUrl = '';

            return $this;
        }

        // If using mod_rewrite or ISAPI_Rewrite strip the script filename
        // out of baseUrl. $pos !== 0 makes sure it is not matching a value
        // from PATH_INFO or QUERY_STRING
        if ((strlen($requestUri) >= strlen($baseUrl))
            && ((($pos = strpos($requestUri, $baseUrl)) !== false) && ($pos !== 0))) {
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }

        $this->_baseUrl = rtrim($baseUrl, '/');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getBaseUrl($raw = false)
    {
        if ($this->_baseUrl === null) {
            $this->setBaseUrl();
        }

        return ($raw == false) ? urldecode($this->_baseUrl) : $this->_baseUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function setBasePath($basePath = null)
    {
        if ($basePath === null) {
            $filename = isset($_SERVER['SCRIPT_FILENAME'])
                ? basename($_SERVER['SCRIPT_FILENAME'])
                : '';

            $baseUrl = $this->getBaseUrl();
            if (empty($baseUrl)) {
                $this->_basePath = '';

                return $this;
            }

            $basePath = $baseUrl;
            if (basename($baseUrl) === $filename) {
                $basePath = dirname($baseUrl);
            }
        }

        if (strpos(PHP_OS, 'WIN') === 0) {
            $basePath = str_replace('\\', '/', $basePath);
        }

        $this->_basePath = rtrim($basePath, '/');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getBasePath()
    {
        if ($this->_basePath === null) {
            $this->setBasePath();
        }

        return $this->_basePath;
    }

    /**
     * {@inheritdoc}
     */
    public function setPathInfo($pathInfo = null)
    {
        if ($pathInfo === null) {
            $baseUrl = $this->getBaseUrl(); // this actually calls setBaseUrl() & setRequestUri()
            $baseUrlRaw = $this->getBaseUrl(false);
            $baseUrlEncoded = urlencode($baseUrlRaw);

            if (($requestUri = $this->getRequestUri()) === null) {
                return $this;
            }

            // Remove the query string from REQUEST_URI
            if ($pos = strpos($requestUri, '?')) {
                $requestUri = substr($requestUri, 0, $pos);
            }

            if (!empty($baseUrl) || !empty($baseUrlRaw)) {
                if (strpos($requestUri, $baseUrl) === 0) {
                    $pathInfo = substr($requestUri, strlen($baseUrl));
                } elseif (strpos($requestUri, $baseUrlRaw) === 0) {
                    $pathInfo = substr($requestUri, strlen($baseUrlRaw));
                } elseif (strpos($requestUri, $baseUrlEncoded) === 0) {
                    $pathInfo = substr($requestUri, strlen($baseUrlEncoded));
                } else {
                    $pathInfo = $requestUri;
                }
            } else {
                $pathInfo = $requestUri;
            }
        }

        $this->_pathInfo = (string) $pathInfo;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPathInfo()
    {
        if (empty($this->_pathInfo)) {
            $this->setPathInfo();
        }

        return $this->_pathInfo;
    }

    /**
     * {@inheritdoc}
     */
    public function setParamSources(array $paramSources = [])
    {
        $this->_paramSources = $paramSources;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParamSources()
    {
        return $this->_paramSources;
    }

    /**
     * {@inheritdoc}
     */
    public function getParam($key, $default = null)
    {
        $paramSources = $this->getParamSources();
        if (isset($this->_params[$key])) {
            return $this->_params[$key];
        } elseif (in_array('_GET', $paramSources) && isset($_GET[$key])) {
            return $_GET[$key];
        } elseif (in_array('_POST', $paramSources) && isset($_POST[$key])) {
            return $_POST[$key];
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getParams()
    {
        $return = $this->_params;
        $paramSources = $this->getParamSources();
        if (in_array('_GET', $paramSources)
            && isset($_GET)
            && is_array($_GET)
        ) {
            $return += $_GET;
        }
        if (in_array('_POST', $paramSources)
            && isset($_POST)
            && is_array($_POST)
        ) {
            $return += $_POST;
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function setParams(array $params)
    {
        foreach ($params as $key => $value) {
            $this->setParam($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod()
    {
        return $this->getServer('REQUEST_METHOD');
    }

    /**
     * {@inheritdoc}
     */
    public function isPost()
    {
        return $this->getMethod() === 'POST';
    }

    /**
     * {@inheritdoc}
     */
    public function isGet()
    {
        return $this->getMethod() === 'GET';
    }

    /**
     * {@inheritdoc}
     */
    public function isPut()
    {
        return $this->getMethod() === 'PUT';
    }

    /**
     * {@inheritdoc}
     */
    public function isDelete()
    {
        return $this->getMethod() === 'DELETE';
    }

    /**
     * {@inheritdoc}
     */
    public function isHead()
    {
        return $this->getMethod() === 'HEAD';
    }

    /**
     * {@inheritdoc}
     */
    public function isOptions()
    {
        return $this->getMethod() === 'OPTIONS';
    }

    /**
     * {@inheritdoc}
     */
    public function isXmlHttpRequest()
    {
        return $this->getHeader('X_REQUESTED_WITH') == 'XMLHttpRequest';
    }

    /**
     * {@inheritdoc}
     */
    public function isFlashRequest()
    {
        $header = strtolower($this->getHeader('USER_AGENT'));

        return strstr($header, ' flash') ? true : false;
    }

    /**
     * {@inheritdoc}
     */
    public function isSecure()
    {
        return $this->getScheme() === self::SCHEME_HTTPS;
    }

    /**
     * {@inheritdoc}
     */
    public function getRawBody()
    {
        if ($this->_rawBody === null) {
            $body = file_get_contents('php://input');

            if (strlen(trim($body)) > 0) {
                $this->_rawBody = $body;
            } else {
                $this->_rawBody = false;
            }
        }

        return $this->_rawBody;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($header)
    {
        $temp = strtoupper(str_replace('-', '_', $header));
        if (isset($_SERVER['HTTP_' . $temp])) {
            return $_SERVER['HTTP_' . $temp];
        }

        if (strpos($temp, 'CONTENT_') === 0 && isset($_SERVER[$temp])) {
            return $_SERVER[$temp];
        }

        if (empty($header)) {
            throw new RuntimeException('An HTTP header name is required');
        }

        // Try to get it from the $_SERVER array first
        $temp = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        if (isset($_SERVER[$temp])) {
            return $_SERVER[$temp];
        }

        // This seems to be the only way to get the Authorization header on
        // Apache
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers[$header])) {
                return $headers[$header];
            }
            $header = strtolower($header);
            foreach ($headers as $key => $value) {
                if (strtolower($key) == $header) {
                    return $value;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getScheme()
    {
        return ($this->getServer('HTTPS') === 'on') ? self::SCHEME_HTTPS : self::SCHEME_HTTP;
    }

    /**
     * {@inheritdoc}
     */
    public function getHttpHost()
    {
        $host = $this->getServer('HTTP_HOST');
        if (!empty($host)) {
            return $host;
        }

        $scheme = $this->getScheme();
        $name = $this->getServer('SERVER_NAME');
        $port = $this->getServer('SERVER_PORT');

        if ($name === null) {
            return '';
        } elseif (($scheme == self::SCHEME_HTTP && $port == 80) || ($scheme == self::SCHEME_HTTPS && $port == 443)) {
            return $name;
        }

        return $name . ':' . $port;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientIp()
    {
        return $this->getServer('REMOTE_ADDR');
    }
}
