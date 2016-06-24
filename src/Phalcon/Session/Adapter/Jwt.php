<?php
/**
 * Jwt Session adapter
 *
 * @package     Rootwork\Phalcon\Session\Adapter
 * @copyright   Copyright (c) 2015-2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     BSD-3-clause
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 */
namespace Rootwork\Phalcon\Session\Adapter;

use Phalcon\Session\Adapter;
use Phalcon\Session\AdapterInterface;
use Phalcon\Session\Exception;
use Phalcon\Di;
use Phalcon\DiInterface;
use Phalcon\Di\InjectionAwareInterface;
use Phalcon\Http\Response;
use Phalcon\Http\Request;
use Firebase\JWT\JWT as JwtUtil;
use Closure;

/**
 * Jwt Session adapter
 *
 * @copyright   Copyright (c) 2015-2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     BSD-3-clause
 * @author      Mike Soule <mike@rootwork.it>
 * @package     Rootwork\Phalcon\Session\Adapter
 *
 * @property    string  $iss    Issuer: server name
 * @property    string  $sub    Subject: Usually a user ID
 * @property    string  $aud    Audience: Ggood place for user role
 * @property    string  $exp    Expire: time token expires
 * @property    string  $nbf    Not before: time token becomes valid
 * @property    string  $iat    Issued at: time when the token was generated
 * @property    string  $jti    Json Token Id: unique identifier for the token
 * @property    string  $typ    Type: Mirrors the typ header (rarely used)
 */
class Jwt extends Adapter implements AdapterInterface, InjectionAwareInterface
{

    /**
     * @var array
     */
    protected $defaultOptions = [
        'algorithm' => 'HS512',
        'lifetime'  => 900,
        'name'      => 'X-Access-Token',
        'id'        => null,
    ];

    /**
     * The last session error message.
     *
     * @var string|null
     */
    protected $lastError = null;

    /**
     * Dependency injector instance.
     *
     * @var DiInterface
     */
    protected $dependencyInjector;

    /**
     * Custom token setter function.
     *
     * @var Closure
     */
    protected $tokenSetter;

    /**
     * Custom token getter function.
     *
     * @var Closure
     */
    protected $tokenGetter;

    /**
     * Class constructor.
     *
     * @param  array     $options
     * @throws Exception
     */
    public function __construct($options = null)
    {
        if (!isset($options['jwtKey'])) {
            throw new Exception('A JWT key is required');
        }

        ini_set('session.use_cookies', false);
        ini_set('session.serialize_handler', 'php_serialize');
        session_set_save_handler(
            [$this, 'open'],
            [$this, 'close'],
            [$this, 'read'],
            [$this, 'write'],
            [$this, 'destroy'],
            [$this, 'gc'],
            [$this, 'create_sid']
        );

        $options = array_merge($this->defaultOptions, $options);
        $this->setName($options['name']);

        parent::__construct($options);
    }

    /**
     * Start the session
     *
     * @return bool
     */
    public function start()
    {
        if (!headers_sent() && $this->status() !== self::SESSION_ACTIVE) {
            $this->receiveToken();
            session_start();
            $this->_started = true;
            $this->sendToken();

            return true;
        }

        return false;
    }

    /**
     * Decodes the request token and loads the session data.
     *
     * @return bool
     */
    public function open()
    {
        return true;
    }

    /**
     * Session close method.
     *
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * Read session data from the access token
     *
     * @param  string $accessToken
     * @return string
     */
    public function read($accessToken)
    {
        try {
            $payload = JwtUtil::decode(
                $accessToken,
                $this->_options['jwtKey'],
                [$this->_options['algorithm']]
            );
        } catch (\Exception $e) {
            // The JWT library throws exceptions for invalid tokens
            $this->lastError = $e->getMessage();
        }

        return isset($payload) ? serialize((array) $payload) : '';
    }

    /**
     * Write the session data.
     *
     * @return bool
     */
    public function write()
    {
        $this->regenerateId(); // Regenerates the JWT from latest session data
        $this->sendToken();

        return true;
    }

    /**
     * Garbage cleanup handler (unused).
     *
     * @param integer $maxLifetime
     *
     * @return bool
     */
    public function gc($maxLifetime)
    {
        return (bool) $maxLifetime;
    }

    /**
     * Generate an encoded JWT as a session ID.
     *
     * @return string
     */
    public function create_sid()
    {
        $data = isset($_SESSION) ? $_SESSION : [];
        return $this->generateToken($data);
    }

    /**
     * Generate encoded JWT from an array.
     *
     * @param array $data
     *
     * @return string
     */
    public function generateToken(array $data = [])
    {
        $now = time();

        $data['jti'] = bin2hex(openssl_random_pseudo_bytes(22));
        $data['iat'] = $now;
        $data['nbf'] = $now;
        $data['exp'] = $now + $this->_options['lifetime'];
        $data['iss'] = null;

        if (isset($_SERVER['SERVER_NAME'])) {
            $data['iss'] = $_SERVER['SERVER_NAME'];
        }

        $jwtKey     = $this->_options['jwtKey'];
        $algorithm  = $this->_options['algorithm'];
        $jwt        = JwtUtil::encode($data, $jwtKey, $algorithm);

        return $jwt;
    }

    /**
     * Set up delivery of the JWT.
     *
     * @return bool
     */
    public function sendToken()
    {
        if ($setter = $this->tokenSetter) {
            return $setter($this->getId());
        }

        /** @var Response\CookiesInterface $cookies */
        $cookies = $this->getDI()->get('cookies');
        $cookies->set($this->getName(), $this->getId(), $this->exp);

        return true;
    }

    /**
     * Get JWT from the request.
     *
     * @return string
     */
    public function receiveToken()
    {
        if ($getter = $this->tokenGetter) {
            $token = $getter();
        } else {
            $cookies    = $this->getDI()->get('cookies');
            $token      = $cookies->get($this->getName())->getValue();
        }

        if ($token) {
            $this->setId($token);
        }

        return true;
    }

    /**
     * Get the last error message or NULL if no errors.
     *
     * @return null|string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Register a custom token setter for setting the access token
     * in the response.
     *
     * @param Closure $tokenSetter
     *
     * @return $this
     */
    public function registerTokenSetter(Closure $tokenSetter)
    {
        $this->tokenSetter = $tokenSetter;
        return $this;
    }

    /**
     * Register a custom token getter for getting access token
     * from the request.
     *
     * @param Closure $tokenGetter
     *
     * @return $this
     */
    public function registerTokenGetter(Closure $tokenGetter)
    {
        $this->tokenGetter = $tokenGetter;
        return $this;
    }

    /**
     * Sets the dependency injector
     *
     * @param DiInterface $dependencyInjector
     */
    public function setDI(DiInterface $dependencyInjector)
    {
        $this->dependencyInjector = $dependencyInjector;
    }

    /**
     * Returns the internal dependency injector
     *
     * @return DiInterface
     */
    public function getDI()
    {
        if (!$this->dependencyInjector) {
            $this->dependencyInjector = Di::getDefault();
        }

        return $this->dependencyInjector;
    }

}
