<?php

namespace Bow\Session;

use Bow\Contracts\CollectionInterface;
use Bow\Security\Crypto;
use Bow\Session\Exception\SessionException;
use InvalidArgumentException;

class Session implements CollectionInterface
{
    /**
     * The internal session variable
     *
     * @var array
     */
    const CORE_SESSION_KEY = [
        "flash" => "__bow.flash",
        "old" => "__bow.old",
        "listener" => "__bow.event.listener",
        "csrf" => "__bow.csrf",
        "cookie" => "__bow.cookie.secure",
        "cache" => "__bow.session.key.cache"
    ];

    /**
     * The session available driver
     *
     * @var array
     */
    private $driver = [
        'database' => \Bow\Session\Driver\DatabaseDriver::class,
        'array' => \Bow\Session\Driver\ArrayDriver::class,
        'file' => \Bow\Session\Driver\FilesystemDriver::class,
    ];

    /**
     * The instance of Session
     *
     * @var Session
     */
    private static $instance;

    /**
     * The session configuration
     *
     * @var array
     */
    private $config;

    /**
     * Session constructor.
     *
     * @param array $config
     */
    private function __construct(array $config)
    {
        if (!isset($config['driver'])) {
            throw new SessionException("The session driver is undefined");
        }

        if (!isset($this->driver[$config['driver']])) {
            throw new SessionException("The session driver is not support");
        }

        // We merge configuration
        $this->config = array_merge([
            'name' => 'Bow',
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'httponly' => false,
            'save_path' => null,
        ], $config);
    }

    /**
     * Configure session instance
     *
     * @param array $config
     * @return mixed
     */
    public static function configure($config)
    {
        if (static::$instance == null) {
            static::$instance = new static($config);
        }

        return static::$instance;
    }

    /**
     * Get session singleton
     *
     * @return mixed
     */
    public static function getInstance()
    {
        return static::$instance;
    }

    /**
     * Session starteur.
     *
     * @return boolean
     */
    public function start()
    {
        if (PHP_SESSION_ACTIVE == session_status()) {
            return true;
        }

        // Load session driver
        $this->initializeDriver();

        // Set the cookie param
        $this->setCookieParmaters();

        // Boot session
        $started = $this->boot();

        // Init interne session manager
        $this->initializeInterneSessionStorage();

        return $started;
    }

    /**
     * Start session nativily
     *
     * @return bool
     */
    private function boot()
    {
        if (!headers_sent()) {
            return @session_start();
        }
        
        throw new SessionException('Headers already sent. Cannot start session.');
    }

    /**
     * Load session driver
     *
     * @return void
     */
    private function initializeDriver()
    {
        // We Apply session cookie name
        session_name($this->config['name']);

        if (!isset($_COOKIE[$this->config['name']])) {
            session_id(hash("sha256", $this->generateId()));
        }

        session_save_path(realpath($this->config['save_path']));

        // We create get driver
        $driver = $this->driver[$this->config['driver']];

        switch ($this->config['driver']) {
            case 'file':
                $handler = new $driver(realpath($this->config['save_path']));
                break;
            case 'database':
                $handler = new $driver($this->config['database'], request()->ip());
                break;
            case 'array':
                $handler = new $driver();
                break;
            default:
                throw new SessionException('Can not set the session driver');
                break;
        }

        // Set the session driver
        if (!session_set_save_handler($handler, true)) {
            throw new SessionException('Can not set the session driver');
        }
    }

    /**
     * Load internal session
     *
     * @return void
     */
    private function initializeInterneSessionStorage()
    {
        if (!isset($_SESSION[static::CORE_SESSION_KEY['csrf']])) {
            $_SESSION[static::CORE_SESSION_KEY['csrf']] = new \stdClass();
        }

        if (!isset($_SESSION[static::CORE_SESSION_KEY['cache']])) {
            $_SESSION[static::CORE_SESSION_KEY['cache']] = [];
        }

        if (!isset($_SESSION[static::CORE_SESSION_KEY['listener']])) {
            $_SESSION[static::CORE_SESSION_KEY['listener']] = [];
        }

        if (!isset($_SESSION[static::CORE_SESSION_KEY['flash']])) {
            $_SESSION[static::CORE_SESSION_KEY['flash']] = [];
        }

        if (!isset($_SESSION[static::CORE_SESSION_KEY['old']])) {
            $_SESSION[static::CORE_SESSION_KEY['old']] = [];
        }
    }

    /**
     * Set session cookie params
     *
     * @return void
     */
    private function setCookieParmaters()
    {
        session_set_cookie_params(
            $this->config["lifetime"],
            $this->config["path"],
            $this->config['domain'],
            $this->config["secure"],
            $this->config["httponly"]
        );
    }

    /**
     * Generate session ID
     *
     * @return string
     */
    private function generateId()
    {
        return Crypto::encrypt(uniqid(microtime(false)));
    }

    /**
     * Generate session
     */
    public function regenerate()
    {
        $this->flush();

        $this->start();
    }

    /**
     * Allows to filter user defined variables
     * and those used by the framework.
     *
     * @return array
     */
    private function filter()
    {
        $arr = [];

        $this->start();

        foreach ($_SESSION as $key => $value) {
            if (!array_key_exists($key, static::CORE_SESSION_KEY)) {
                $arr[$key] = $value;
            }
        }

        return $arr;
    }

    /**
     * Allows checking for the existence of a key in the session collection
     *
     * @param string $key
     * @param bool   $strict
     * @return boolean
     */
    public function has($key, $strict = false)
    {
        $this->start();

        $cache = $_SESSION[static::CORE_SESSION_KEY['cache']];

        $flash = $_SESSION[static::CORE_SESSION_KEY['flash']];

        if (!$strict) {
            return isset($cache[$key]) ? true : isset($flash[$key]);
        }

        if (!isset($cache[$key])) {
            $value = $flash[$key] ?? null;

            return !is_null($value);
        }

        $value = $cache[$key] ?? null;

        return !is_null($value);
    }

    /**
     * Allows checking for the existence of a key in the session collection
     *
     * @param string $key
     * @return boolean
     */
    public function exists($key)
    {
        return $this->has($key, true);
    }

    /**
     * Check whether a collection is empty.
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return empty($this->filter());
    }

    /**
     * Retrieves a value or value collection.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        $content = $this->flash($key);

        if (!is_null($content)) {
            return $content;
        }

        if (is_null($content) && $this->has($key)) {
            return $_SESSION[$key];
        }

        if (is_callable($default)) {
            return $default();
        }

        return $default;
    }

    /**
     * Add an entry to the collection
     *
     * @param string|int $key
     * @param mixed $value
     * @param boolean $next
     * @throws InvalidArgumentException
     * @return mixed
     */
    public function add($key, $value, $next = false)
    {
        $this->start();

        $_SESSION[static::CORE_SESSION_KEY['cache']][$key] = true;

        if ($next == false) {
            return $_SESSION[$key] = $value;
        }

        if (! $this->has($key)) {
            $_SESSION[$key] = [];
        }

        if (!is_array($_SESSION[$key])) {
            $_SESSION[$key] = [$_SESSION[$key]];
        }

        $_SESSION[$key] = array_merge($_SESSION[$key], [$value]);

        return $value;
    }

    /**
     * The add alias
     *
     * @see \Bow\Session\Session::add
     */
    public function put($key, $value, $next = false)
    {
        return $this->add($key, $value, $next);
    }

    /**
     * Returns the list of session variables
     *
     * @return array
     */
    public function all()
    {
        return $this->filter();
    }

    /**
     * Delete an entry in the collection
     *
     * @param string $key
     *
     * @return mixed
     */
    public function remove($key)
    {
        $this->start();

        $old = null;

        if ($this->has($key)) {
            $old = $_SESSION[$key];
        }

        unset($_SESSION[$key]);

        return $old;
    }

    /**
     * set
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    public function set($key, $value)
    {
        $this->start();

        $old = null;

        $_SESSION[static::CORE_SESSION_KEY['cache']][$key] = true;

        if (!$this->has($key)) {
            $_SESSION[$key] = $value;

            return $old;
        }

        $old = $_SESSION[$key];

        $_SESSION[$key] = $value;

        return $old;
    }

    /**
     * Add flash data
     * After the data recovery is automatic deleted
     *
     * @param  mixed $key
     * @param  mixed $message
     * @return mixed
     */
    public function flash($key, $message = null)
    {
        $this->start();

        if ($message != null) {
            $_SESSION[static::CORE_SESSION_KEY['flash']][$key] = $message;

            return true;
        }

        $flash = $_SESSION[static::CORE_SESSION_KEY['flash']];

        $content = isset($flash[$key]) ? $flash[$key] : null;
        $tmp = [];

        foreach ($flash as $i => $value) {
            if ($i != $key) {
                $tmp[$i] = $value;
            }
        }

        $_SESSION[static::CORE_SESSION_KEY['flash']] = $tmp;

        return $content;
    }

    /**
     * Returns the list of session data as a array.
     *
     * @return array
     */
    public function toArray()
    {
        return self::filter();
    }

    /**
     * Empty the flash system.
     */
    public function clearFash()
    {
        $this->start();

        $_SESSION[static::CORE_SESSION_KEY['flash']] = [];
    }

    /**
     * Allows to clear the cache except csrf and __bow.flash
     */
    public function clear()
    {
        $this->start();

        foreach ($this->filter() as $key => $value) {
            unset($_SESSION[static::CORE_SESSION_KEY['cache']][$key]);

            unset($_SESSION[$key]);
        }
    }

    /**
     * Allows you to empty the session
     */
    public function flush()
    {
        session_destroy();
    }

    /**
     * Returns the list of session data as a toObject.
     *
     * @return array|void
     */
    public function toObject()
    {
        throw new \BadMethodCallException("Bad methode called");
    }

    /**
     * __toString
     *
     * @return string
     */
    public function __toString()
    {
        $this->start();

        return json_encode($this->filter());
    }
}
