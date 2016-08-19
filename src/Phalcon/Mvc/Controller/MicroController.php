<?php
/**
 * MicroController
 *
 * @package     Rootwork\Phalcon\Mvc\Controller
 * @copyright   Copyright (c) 2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     BSD-3-clause
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 */

namespace Rootwork\Phalcon\Mvc\Controller;

use Phalcon\Mvc\Controller;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Mvc\Model\Criteria;
use Phalcon\Paginator\Adapter\Model as Paginator;

/**
 * A CRUD controller for micro applications.
 *
 * @package     Rootwork\Phalcon\Mvc\Controller
 */
class MicroController extends Controller
{

    /**
     * Name of the validation class to use.
     *
     * @var string
     */
    protected $validationClass;

    /**
     * Name of the model class to use.
     *
     * @var string
     */
    protected $modelClass;

    /**
     * Validation instance.
     *
     * @var \Phalcon\Validation
     */
    protected $validation;

    /**
     * Model instance loaded in an action.
     *
     * @var \Phalcon\Mvc\Model
     */
    protected $model;

    /**
     * Array of model results.
     *
     * @var \Phalcon\Mvc\Model[]
     */
    protected $results = [];

    /**
     * Number of models to show on one page of search results
     *
     * @var int
     */
    protected $pageLimit = 10;

    /**
     * Initialize the controller.
     */
    public function initialize()
    {
        if (empty($this->validationClass) || empty($this->modelClass)) {
            $class = get_class($this);

            throw new \InvalidArgumentException(
                "$class::validationClass and $class::modelClass must be set (with full namespaces)"
            );
        }
    }

    /**
     * Return paged search results.
     *
     * @return bool
     */
    public function search()
    {
        if ($this->fireEvent('beforeSearch') === false) {
            return false;
        }

        /** @var string|object $modelClass */
        $modelClass = $this->modelClass;
        $query      = Criteria::fromInput($this->di, $modelClass, $this->request->getQuery());
        $models     = $modelClass::find($query->getParams());

        if (!count($models) && $this->fireEvent('onResultsNotFound') === false) {
            return false;
        }

        $paginator = new Paginator([
            "data"  => $models,
            "limit" => $this->request->getQuery('limit', 'int', $this->pageLimit),
            "page"  => $this->request->getQuery('page', 'int', 1),
        ]);

        $this->results = $paginator->getPaginate()->items;

        return $this->fireEvent('afterSearch');
    }

    /**
     * Read a result by ID.
     *
     * @param mixed $id
     *
     * @return bool
     */
    public function read($id)
    {
        if ($this->fireEvent('beforeRead') === false) {
            return false;
        }

        /** @var string|object $modelClass */
        $modelClass = $this->modelClass;
        $model      = $modelClass::findFirstById($id);

        if (!$model && $this->fireEvent('onResultsNotFound') === false) {
            return false;
        }

        $this->model = $model;

        return $this->fireEvent('afterRead');
    }

    /**
     * Creates or updates a model from user submitted data.
     *
     * @param mixed|null $id
     *
     * @return bool
     */
    public function save($id = null)
    {
        if ($this->fireEvent('beforeSave') === false) {
            return false;
        }

        $this->model        = new $this->{'modelClass'}();
        $this->validation   = new $this->{'validationClass'}();

        if (!is_null($id)) {
            if ($this->fireEvent('beforeSaveExisting') === false) {
                return false;
            }

            /** @var string|object $modelClass */
            $modelClass     = $this->modelClass;
            $this->model    = $modelClass::findFirstById($id);

            if (!$this->model) {
                return $this->fireEvent('onSaveNotFound');
            }
        } else {
            if ($this->fireEvent('beforeSaveNew') === false) {
                return false;
            }
        }

        $data = $this->getRequestData();

        if ($this->fireEvent('beforeValidation') === false) {
            return false;
        }

        if (!$this->validation->validate($data)) {
            return $this->fireEvent('onValidationFails');
        }

        if ($this->fireEvent('afterValidation') === false) {
            return false;
        }

        if ($this->model->save() == false) {
            return $this->fireEvent('onSaveFails');
        }

        return $this->fireEvent('afterSave');
    }

    /**
     * Deletes a model record by ID.
     *
     * @param mixed $id
     *
     * @return bool
     */
    public function delete($id)
    {
        if ($this->fireEvent('beforeDelete') === false) {
            return false;
        }

        /** @var string|object $modelClass */
        $modelClass     = $this->modelClass;
        $this->model    = $modelClass::findFirstById($id);

        if (!$this->model) {
            return $this->fireEvent('onResultsNotFound');
        }

        if (!$this->model->delete()) {
            return $this->fireEvent('onDeleteFails');
        }

        return $this->fireEvent('afterDelete');
    }

    /**
     * Get the events manager (create if necessary).
     *
     * @return \Phalcon\Events\ManagerInterface
     */
    public function getEventsManager()
    {
        if (!($this->_eventsManager instanceof EventsManager)) {
            $eventsManager = new EventsManager();
            $eventsManager->attach('microController', $this);
            $this->setEventsManager($eventsManager);
        }

        return parent::getEventsManager();
    }

    /**
     * Fire a controller event by name.
     *
     * @param string $event
     *
     * @return mixed
     */
    public function fireEvent($event)
    {
        return $this->getEventsManager()->fire("microController:$event", $this);
    }

    /**
     * No results found event.
     *
     * @return bool
     */
    public function onResultsNotFound()
    {
        $this->flash->notice('No results found');
        return false;
    }

    /**
     * On validation fails event.
     *
     * @return bool
     */
    public function onValidationFails()
    {
        foreach ($this->validation->getMessages() as $message) {
            $this->flash->error($message);
        }
    }

    /**
     * On save fails event.
     *
     * @return bool
     */
    public function onSaveFails()
    {
        foreach ($this->model->getMessages() as $message) {
            $this->flash->error($message);
        }
    }

    /**
     * After save event.
     */
    public function afterSave()
    {
        $this->flash->success('Item saved successfully');
    }

    /**
     * On delete fails event.
     *
     * @return bool
     */
    public function onDeleteFails()
    {
        foreach ($this->model->getMessages() as $message) {
            $this->flash->error($message);
        }
    }

    /**
     * After delete event.
     */
    public function afterDelete()
    {
        $this->flash->success('Item was deleted');
    }

    /**
     * Forward the request to another controller/action.
     *
     * @param string $uri
     *
     * @return bool
     */
    protected function forward($uri)
    {
        $uriParts   = explode('/', $uri);
        $params     = array_slice($uriParts, 2);
        $this->dispatcher->forward([
            'controller'    => $uriParts[0],
            'action'        => $uriParts[1],
            'params'        => $params,
        ]);

        return true;
    }

    /**
     * Set number of models to show on one page of search results
     *
     * @param int $pageLimit
     *
     * @return $this
     */
    public function setPageLimit($pageLimit)
    {
        $this->pageLimit = $pageLimit;

        return $this;
    }

    /**
     * Get the request data.
     *
     * @return array
     */
    protected function getRequestData()
    {
        if ($data = $this->request->getJsonRawBody(true)) {
            return $data;
        }

        if ($this->request->isPut()) {
            return $this->request->getPut();
        }

        return $this->request->get();
    }
}
