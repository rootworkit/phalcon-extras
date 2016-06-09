<?php
/**
 * CrudController
 *
 * @package     Rootwork\Phalcon\Mvc\Controller
 * @copyright   Copyright (c) 2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     All Rights Reserved
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 */

namespace Rootwork\Phalcon\Mvc\Controller;

use Phalcon\Mvc\Controller;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Mvc\Model\Criteria;
use Phalcon\Paginator\Adapter\Model as Paginator;

/**
 * A basic, universal CRUD controller for Phalcon apps.
 *
 * @package     Rootwork\Phalcon\Mvc\Controller
 */
class CrudController extends Controller
{

    /**
     * @var string
     */
    protected $formClass;

    /**
     * @var string
     */
    protected $modelClass;

    /**
     * Model instance loaded in a CRUD action.
     *
     * @var \Phalcon\Mvc\Model
     */
    protected $model;

    /**
     * Form instance.
     *
     * @var \Phalcon\Forms\Form
     */
    protected $form;

    /**
     * Number of models to show on one page of search results
     * 
     * @var int
     */
    protected $pageLimit = 10;

    /**
     * Initialize the CRUD controller.
     */
    public function initialize()
    {
        if (empty($this->formClass) || empty($this->modelClass)) {
            $class = get_class($this);

            throw new \InvalidArgumentException(
                "$class::formClass and $class::modelClass must be set (with full namespaces)"
            );
        }
    }

    /**
     * Index action: usually displays a search page.
     */
    public function indexAction()
    {
        if ($this->fireEvent('beforeIndex') === false) {
            return false;
        }

        $this->persistent->searchParams = null;
        $this->form = new $this->{'formClass'}();
        $this->view->form = $this->form;

        return $this->fireEvent('afterIndex');
    }

    /**
     * Display paged search results.
     *
     * @return bool
     */
    public function searchAction()
    {
        if ($this->fireEvent('beforeSearch') === false) {
            return false;
        }

        /** @var string|object $modelClass */
        $modelClass = $this->modelClass;
        $pageNumber = 1;

        if ($this->request->isPost()) {
            $query = Criteria::fromInput($this->di, $modelClass, $this->request->getPost());
            $this->persistent->searchParams = $query->getParams();
        } else {
            $pageNumber = $this->request->getQuery('page', 'int');
        }

        $parameters = [];

        if ($this->persistent->searchParams) {
            $parameters = $this->persistent->searchParams;
        }

        $results = $modelClass::find($parameters);

        if (!count($results) && $this->fireEvent('onSearchNotFound') === false) {
            return false;
        }

        $paginator = new Paginator(array(
            "data"  => $results,
            "limit" => $this->pageLimit,
            "page"  => $pageNumber
        ));

        $this->view->page = $paginator->getPaginate();

        return $this->fireEvent('afterSearch');
    }

    /**
     * Displays a form for creating/editing a model.
     *
     * @param mixed|null $id
     *
     * @return bool
     */
    public function editAction($id = null)
    {
        if ($this->fireEvent('beforeEdit') === false) {
            return false;
        }

        if (!$this->request->isPost()) {
            /** @var string|object $modelClass */
            $modelClass = $this->modelClass;

            if (!is_null($id)) {
                if ($this->fireEvent('beforeEditExisting') === false) {
                    return false;
                }

                $this->model = $modelClass::findFirstById($id);

                if (!$this->model) {
                    return $this->fireEvent('onEditNotFound');
                }

                $this->form = new $this->{'formClass'}($this->model, ['edit' => true]);
            } else {
                if ($this->fireEvent('beforeEditNew') === false) {
                    return false;
                }

                $this->model    = new $modelClass();
                $this->form     = new $this->{'formClass'}(null, ['edit' => true]);
            }

            $this->view->form   = $this->form;
            $this->view->model  = $this->model;
        }

        return $this->fireEvent('afterEdit');
    }

    /**
     * Creates or updates a model from user submitted data.
     *
     * @param mixed|null $id
     *
     * @return bool
     */
    public function saveAction($id = null)
    {
        if ($this->fireEvent('beforeSave') === false) {
            return false;
        }

        if (!$this->request->isPost()) {
            return $this->fireEvent('onSaveNotPostRequest');
        }

        $this->model = new $this->{'modelClass'}();

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

            $this->form = new $this->{'formClass'}($this->model, ['edit' => true]);
        } else {
            if ($this->fireEvent('beforeSaveNew') === false) {
                return false;
            }

            $this->form = new $this->{'formClass'}(null, ['edit' => true]);
        }

        $this->view->form   = $this->form;
        $this->view->model  = $this->model;
        $data               = $this->request->getPost();

        if ($this->fireEvent('beforeValidation') === false) {
            return false;
        }

        if (!$this->form->isValid($data, $this->model)) {
            return $this->fireEvent('onFormValidationFails');
        }

        if ($this->fireEvent('afterValidation') === false) {
            return false;
        }

        if ($this->model->save() == false) {
            return $this->fireEvent('onSaveFails');
        }

        $this->form->clear();

        return $this->fireEvent('afterSave');
    }

    /**
     * Deletes a model record by ID.
     *
     * @param mixed $id
     *
     * @return bool
     */
    public function deleteAction($id)
    {
        if ($this->fireEvent('beforeDelete') === false) {
            return false;
        }

        /** @var string|object $modelClass */
        $modelClass     = $this->modelClass;
        $this->model    = $modelClass::findFirstById($id);

        if (!$this->model) {
            return $this->fireEvent('onDeleteNotFound');
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
            $eventsManager->attach('crud', $this);
            $this->setEventsManager($eventsManager);
        }

        return parent::getEventsManager();
    }

    /**
     * Fire a CRUD event by name.
     *
     * @param string $event
     *
     * @return mixed
     */
    public function fireEvent($event)
    {
        return $this->getEventsManager()->fire("crud:$event", $this);
    }

    /**
     * No results found event.
     *
     * @return bool
     */
    public function onResultsNotFound()
    {
        $this->flash->notice('No results found');
        return $this->forward($this->router->getControllerName() . '/index');
    }

    /**
     * Search results not found event.
     *
     * @return bool
     */
    public function onSearchNotFound()
    {
        return $this->onResultsNotFound();
    }

    /**
     * Edit model not found event.
     *
     * @return bool
     */
    public function onEditNotFound()
    {
        return $this->onResultsNotFound();
    }

    /**
     * Edit results not found event.
     *
     * @return bool
     */
    public function onSaveNotFound()
    {
        return $this->onResultsNotFound();
    }

    /**
     * Save without post request.
     *
     * @return bool
     */
    public function onSaveNotPostRequest()
    {
        return $this->forward($this->router->getControllerName() . '/index');
    }

    /**
     * On form validation fails event.
     *
     * @return bool
     */
    public function onFormValidationFails()
    {
        foreach ($this->form->getMessages() as $message) {
            $this->flash->error($message);
        }

        return $this->forward($this->router->getControllerName() . '/edit');
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

        return $this->forward($this->router->getControllerName() . '/edit/' . $this->model->id);
    }

    /**
     * After save event.
     */
    public function afterSave()
    {
        $this->flash->success('Item saved successfully');
        $this->forward($this->router->getControllerName() . '/index');
    }

    /**
     * Delete not found event.
     *
     * @return bool
     */
    public function onDeleteNotFound()
    {
        return $this->onResultsNotFound();
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

        return $this->forward($this->router->getControllerName() . '/edit/' . $this->model->id);
    }

    /**
     * After delete event.
     */
    public function afterDelete()
    {
        $this->flash->success('Item was deleted');
        $this->forward($this->router->getControllerName() . '/index');
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

}
