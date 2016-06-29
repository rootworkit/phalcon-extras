<?php

namespace Rootwork\Test\Phalcon\Session\Adapter {

    use PHPUnit_Framework_TestCase as TestCase;
    use Rootwork\Phalcon\Session\Adapter\Jwt;
    use Phalcon\Http\Response\Cookies;
    use Firebase\JWT\JWT as JwtUtil;

    /**
     * Test case for Phalcon JWT adapter.
     *
     * @copyright   Copyright (c) 2016 Rootwork InfoTech LLC (www.rootwork.it)
     * @license     BSD-3-clause
     * @author      Mike Soule <mike@rootwork.it>
     * @package     Rootwork\Test\Phalcon\Session\Adapter\Jwt
     */
    class JwtTest extends TestCase
    {

        /**
         * JWT hash key.
         *
         * @var string
         */
        protected $jwtKey;

        /**
         * Subject under test.
         *
         * @var Jwt
         */
        protected $sut;

        /**
         * JWT payload data.
         *
         * @var object
         */
        protected $payload;

        /**
         * Set up the test.
         */
        public function setUp()
        {
            $_SERVER['SERVER_NAME'] = 'example.com';
            $this->payload          = (object) [
                'jti'   => bin2hex(openssl_random_pseudo_bytes(22)),
                'iss'   => $_SERVER['SERVER_NAME'],
                'sub'   => hexdec(uniqid()),
                'aud'   => 'Testers',
                'exp'   => time() + 900,
                'nbf'   => time() - 10,
                'iat'   => time(),
            ];

            $this->jwtKey   = bin2hex(openssl_random_pseudo_bytes(128));
            $this->sut      = new Jwt(['jwtKey' => $this->jwtKey]);
        }

        /**
         * Clean up after each test.
         */
        public function tearDown()
        {
            session_write_close();

            /** @var Cookies $cookies */
            $cookies = $this->sut->getDI()->get('cookies');
            $cookies->reset();
        }

        /**
         * Test starting a new session.
         */
        public function testStartNewSession()
        {
            /** @var Cookies $cookies */
            $started    = $this->sut->start();
            $cookies    = $this->sut->getDI()->get('response')->getCookies();
            $token      = $cookies->get($this->sut->getName())->getValue();

            $this->assertTrue($started);
            $this->assertNotEmpty($token);
            $this->assertRegExp('#^[a-zA-Z0-9+/\-_.]+={0,2}$#', $token);
        }

        /**
         * Test getting an existing session.
         */
        public function testRestartSession()
        {
            /** @var Cookies $cookies */
            $cookies    = $this->sut->getDI()->get('cookies');
            $reqToken   = JwtUtil::encode(
                $this->payload, $this->jwtKey, 'HS512'
            );
            $cookies->set(
                $this->sut->getName(), $reqToken, $this->payload->exp
            );

            $this->sut->start();

            $this->assertEquals($this->payload->iss, $this->sut->iss);
            $this->assertEquals($this->payload->sub, $this->sut->sub);
            $this->assertEquals($this->payload->aud, $this->sut->aud);
        }

        /**
         * Test custom token setter and getter.
         */
        public function testCustomTokenHandlers()
        {
            $_GET['token'] = JwtUtil::encode(
                $this->payload, $this->jwtKey, 'HS512'
            );
            $response = new \stdClass();

            $this->sut->registerTokenGetter(function () {
                return $_GET['token'];
            });

            $this->sut->registerTokenSetter(function ($token) use ($response) {
                $response->token = $token;
                return true;
            });

            $this->sut->start();
            $this->assertTrue(isset($response->token));
            $actual = JwtUtil::decode($response->token, $this->jwtKey, ['HS512']);

            $this->assertEquals($this->payload->iss, $actual->iss);
            $this->assertEquals($this->payload->sub, $actual->sub);
            $this->assertEquals($this->payload->aud, $actual->aud);
        }
    }
}

namespace Rootwork\Phalcon\Session\Adapter {

    /**
     * Override for built-in headers_sent() function.
     *
     * @return bool
     */
    function headers_sent()
    {
        return false;
    }

    /**
     * Error suppression wrapper for session_start().
     *
     * @return mixed
     */
    function session_start()
    {
        @\session_start();
        return true;
    }
}
