<?php


class RscDtgs_Lang
{

    /**
     * @var string
     */
    protected $domain;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var array
     */
    protected $files;

    /**
     * @var RscDtgs_Cache_Interface
     */
    protected $cache;

    /**
     * Constructor
     * @param null|string $domain Text domain
     * @param null|string $path Path to the *.mo files
     * @param RscDtgs_Cache $cache Caching class
     */
    public function __construct($domain = null, $path = null, RscDtgs_Cache $cache = null)
    {
        $this->domain = $domain;
        $this->path   = $path;
        $this->cache  = $cache;
    }

    /**
     * Set caching class
     * @param \RscDtgs_Cache_Interface $cache
     * @return RscDtgs_Lang
     */
    public function setCache(RscDtgs_Cache_Interface $cache)
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Load text domain
     * @throws RscDtgs_Exception_LangException If path or domain is not specified
     */
    public function loadTextDomain()
    {
        add_action('plugins_loaded', array($this, '_loadPluginsTextdomain'));
    }

    public function _loadPluginsTextDomain()
    {
        load_plugin_textdomain(
            $this->domain,
            false,
            $this->path
        );
    }

    /**
     * Translate specified string
     * @param string $msgid Message ID
     * @return string|void
     */
    public function translate($msgid)
    {
        if (did_action('init')) {    
            return __($msgid, $this->domain);
        }
        return $msgid;
    }

    /**
     * Alias for RscDtgs_Lang::translate()
     * @param string $msgid Message ID
     * @return string|void
     */
    public function _($msgid)
    {
        return $this->translate($msgid);
    }

    /**
     * Print translated string
     * @param string $msgid Message ID
     */
    public function _e($msgid)
    {
        echo $this->translate($msgid);
    }

    /**
     * Checks whether locale exists
     * @param string $locale
     * @return bool
     */
    public function hasLocale($locale)
    {
        return isset($this->files[$locale]);
    }

    /**
     * Returns current locale
     * @return null|string
     */
    public function getLocale()
    {
        if (defined('WPLANG')) {
            return WPLANG;
        }

        return null;
    }

    /**
     * Returns all registered MO files
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Returns all registered plugin locales
     * @return array
     */
    public function getLocales()
    {
        return array_keys($this->files);
    }

    /**
     * Set text domain
     * @param string $domain
     * @return RscDtgs_Lang
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * Get text domain
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Set domain path
     * @param string $path
     * @return RscDtgs_Lang
     */
    public function setPath($path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Get domain path
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

}
