# phalcon-extras
A growing collection of useful Phalcon classes

## Installation

```bash
composer require rootwork/phalcon-extras:dev-master
```

## Authorization Component

### Uses Phalcon\Acl, see acl.example.php.

```php
<?php
cp acl.example.php app/config/acl.php
```

### The AuthComponent uses events for your custom logic after allowing or denying a session role.

```php
<?php
/**
 * Setup the auth component
 */
$di->setShared('auth', function () {
    /** @var \Phalcon\Config $aclConfig */
    $aclConfig  = include APP_PATH . '/config/acl.php';
    $auth       = new AuthComponent($aclConfig->toArray());

    $eventsManager = new EventsManager();

    $eventsManager->attach('auth:afterAllowed', function (Event $event, AuthComponent $auth) {
        if ($auth->persistent->role == 'Guest') {
            return true; // If the ACL allows a Guest at this route, no additional steps
        }

        // Load authorized user from the DB
        if ($user = User::findFirstById($auth->persistent->userId)) {
            $auth->di->setShared('user', $user);
            return true;
        }

        return false;
    });

    $eventsManager->attach('auth:afterDenied', function (Event $event, AuthComponent $auth) {
        // Redirect unathorized users
        $auth->response->redirect('/login');
        $auth->response->send();
        return false;
    });

    $auth->setEventsManager($eventsManager);

    return $auth;
});

/**
 * Register a dispatcher
 */
$di->setShared('dispatcher', function () use ($config, $di) {
    $eventsManager = new EventsManager();
    $eventsManager->attach('dispatch:beforeExecuteRoute', $di->get('auth'));

    $dispatcher = new Dispatcher();
    $dispatcher->setDefaultNamespace('App\Controller');
    $dispatcher->setEventsManager($eventsManager);

    return $dispatcher;
});

/**
 * Make the current user available to the app
 */
$di->setShared('user', function () {
    return null;
});
```

### Create an authorized session
In your login code, you must set the user role. You can optionally persist other auth data.

```php
<?php
// In a login action... 
$auth = $this->getDI()->get('auth');
$auth->setRole($user->role);

// Optional persisted auth data
$auth->persistent->userId   = $user->id;
$auth->persistent->name     = $user->name;
```

### TODO
* Document usage with Micro app
