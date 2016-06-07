<?php
/**
 * An authorization component for Phalcon apps
 *
 * @package     Rootwork\Phalcon\Mvc\User\Component
 * @copyright   Copyright (c) 2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     All Rights Reserved
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 */

namespace Rootwork\Phalcon\Mvc\User\Component;

use Phalcon\Mvc\User\Component;
use Phalcon\Mvc\Micro\MiddlewareInterface;
use Phalcon\Mvc\Micro;
use Phalcon\Acl\Role;
use Phalcon\Acl\Resource;
use Phalcon\Acl\Adapter\Memory as AclList;
use Phalcon\Cache\BackendInterface as CacheBackend;
use Phalcon\Events\Manager as EventsManager;

/**
 * An authorization component for Phalcon apps
 *
 * @package     Rootwork\Phalcon\Component
 *
 * @method      EventsManager   getEventsManager()
 */
class AuthComponent extends Component implements MiddlewareInterface
{

    /**
     * Authorization status.
     *
     * @var bool
     */
    protected $authorized = false;

    /**
     * Default user role.
     *
     * @var string
     */
    protected $defaultRole = 'Guest';

    /**
     * Plugin options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * App instance (for Micro applications only).
     *
     * @var Micro
     */
    protected $app;

    /**
     * Array of services (for __set compatibility).
     *
     * @var array
     */
    protected $services = [];

    /**
     * AuthComponent constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * Set the plugin options.
     *
     * @param array $options
     *
     * @return $this
     */
    public function setOptions(array $options = [])
    {
        if ($options) {
            if (isset($options['defaultRole'])) {
                $this->setDefaultRole($options['defaultRole']);
            }
        }

        $this->options = $options;

        return $this;
    }

    /**
     * Set the default role.
     *
     * @param string $defaultRole
     *
     * @return $this
     */
    public function setDefaultRole($defaultRole)
    {
        $this->defaultRole = $defaultRole;

        return $this;
    }

    /**
     * Get the default role.
     *
     * @return string
     */
    public function getDefaultRole()
    {
        return $this->defaultRole;
    }

    /**
     * Set the current session role.
     *
     * @param string $role
     *
     * @return $this
     */
    public function setRole($role)
    {
        $this->persistent->set('role', $role);

        return $this;
    }

    /**
     * Get the current session role.
     *
     * @return string
     */
    public function getRole()
    {
        if (!$this->persistent->get('role')) {
            $this->persistent->set('role', $this->defaultRole);
        }

        return $this->persistent->get('role');
    }

    /**
     * Get the optional cache backend.
     *
     * @return CacheBackend|null
     */
    protected function getCache()
    {
        if (isset($this->options['cacheService'])) {
            $name = $this->options['cacheService'];
            return $this->getDI()->get($name);
        }

        return null;
    }

    /**
     * Authorizes the current user, optionally with an ACL.
     *
     * @return bool
     */
    public function authorize()
    {
        $role           = $this->getRole();
        $controller     = $this->getController();
        $action         = $this->getAction();
        $acl            = $this->getAcl();
        $allowed        = $acl->isAllowed($role, $controller, $action);
        $eventsManager  = $this->getEventsManager();

        if ($allowed) {
            $this->authorized = true;

            $eventsManager->collectResponses(true);
            $eventsManager->fire('auth:afterAllowed', $this);
            $eventResponses = $eventsManager->getResponses();

            foreach ((array) $eventResponses as $response) {
                if ($response === false) {
                    $this->authorized = false;
                }
            }
        }

        if (!$this->authorized) {
            $eventsManager->fire('auth:afterDenied', $this);
        }

        return $this->authorized;
    }

    /**
     * Load and return the ACL list
     *
     * @return AclList
     */
    public function getAcl()
    {
        $acl = null;

        if ($cache = $this->getCache()) {
            $acl = $cache->get('acl');
        }

        if (!$acl) {
            $acl = new AclList();
            $options        = $this->options;
            $roles          = isset($options['roles']) ? (array) $options['roles'] : [];
            $inheritance    = isset($options['inheritance']) ? (array) $options['inheritance'] : [];
            $resources      = isset($options['resources']) ? (array) $options['resources'] : [];

            if (isset($options['defaultAction'])) {
                $acl->setDefaultAction($options['defaultAction']);
            }

            foreach ($roles as $role) {
                $acl->addRole(new Role($role));
            }

            foreach ($inheritance as $role => $inherit) {
                $acl->addInherit($role, $inherit);
            }

            foreach ($resources as $role => $controllers) {
                foreach ((array) $controllers as $controller => $methods) {
                    $acl->addResource(new Resource($controller), (array) $methods);

                    foreach ($methods as $method) {
                        $acl->allow($role, $controller, $method);
                    }
                }
            }

            if ($cache) {
                $cache->save('acl', $acl);
            }
        }

        return $acl;
    }

    /**
     * Get the controller name.
     *
     * @return string
     */
    protected function getController()
    {
        if ($this->app instanceof Micro) {
            $handler    = $this->app->getActiveHandler();
            $parts      = explode('\\', get_class($handler[0]));
            $controller = str_replace('Controller', '', array_pop($parts));
        } else {
            $controller = ucfirst($this->dispatcher->getControllerName());
        }

        return $controller;
    }

    /**
     * Get the action name.
     *
     * @return string
     */
    protected function getAction()
    {
        if ($this->app instanceof Micro) {
            $handler    = $this->app->getActiveHandler();
            $action     = $handler[1];
        } else {
            $action     = $this->dispatcher->getActionName();
        }

        return $action;
    }

    /**
     * Middleware caller for micro applications.
     *
     * @param   Micro $app
     * @return  bool
     */
    public function call(Micro $app)
    {
        $this->app = $app;

        return $this->authorize();
    }

    /**
     * Invoker for closure compatibility.
     *
     * @param   Micro $app
     * @return  bool
     */
    public function __invoke(Micro $app)
    {
        return $this->call($app);
    }

    /**
     * Dispatch event handler for non-micro applications.
     *
     * @return bool
     */
    public function beforeExecuteRoute()
    {
        return $this->authorize();
    }
}
