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
     * Flash message for no search results.
     *
     * @var string
     */
    protected $msgNoResults = 'No results found';

    /**
     * Flash message for no model found with the given ID.
     *
     * @var string
     */
    protected $msgNotFoundId = 'Item was not found';

    /**
     * Flash message for item saved successfully.
     *
     * @var string
     */
    protected $msgSavedSuccess = 'Item saved successfully';

    /**
     * Flash message for item deleted.
     *
     * @var string
     */
    protected $msgItemDeleted = 'Item was deleted';

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
     * Handler to fire 'CRUD before' event
     */
    public function beforeExecuteRoute()
    {
        $action = $this->router->getActionName();
        $event  = ucfirst($action);
        $this->getEventsManager()->fire("crud:before$event", $this);
    }

    /**
     * Handler to fire 'CRUD after' event
     */
    public function afterExecuteRoute()
    {
        $action = $this->router->getActionName();
        $event  = ucfirst($action);
        $this->getEventsManager()->fire("crud:after$event", $this);
    }

    /**
     * Index action: usually displays a search page.
     */
    public function indexAction()
    {
        $this->persistent->searchParams = null;
        $this->view->form = new $this->{'formClass'}();
    }

    /**
     * Display paged search results.
     *
     * @return bool
     */
    public function searchAction()
    {
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

        if (count($results) == 0) {
            $this->flash->notice($this->msgNoResults);
            return $this->forward($this->router->getControllerName() . '/index');
        }

        $paginator = new Paginator(array(
            "data"  => $results,
            "limit" => 10,
            "page"  => $pageNumber
        ));

        $this->view->page = $paginator->getPaginate();
        return true;
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
        if (!$this->request->isPost()) {
            /** @var string|object $modelClass */
            $modelClass     = $this->modelClass;

            if (!is_null($id)) {
                $this->model    = $modelClass::findFirstById($id);

                if (!$this->model) {
                    $this->flash->error($this->msgNotFoundId);
                    return $this->forward($this->router->getControllerName() . '/index');
                }

                $form = new $this->{'formClass'}($this->model, ['edit' => true]);
            } else {
                $this->model    = new $modelClass();
                $form           = new $this->{'formClass'}(null, ['edit' => true]);
            }

            $this->view->form   = $form;
            $this->view->model  = $this->model;
        }

        return true;
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
        $this->model    = new $this->{'modelClass'}();
        $controller     = $this->router->getControllerName();
        $indexUri       = "$controller/index";
        $editUri        = "$controller/edit/$id";

        if (!$this->request->isPost()) {
            return $this->forward($indexUri);
        }

        if (!is_null($id)) {
            /** @var string|object $modelClass */
            $modelClass     = $this->modelClass;
            $this->model    = $modelClass::findFirstById($id);

            if (!$this->model) {
                $this->flash->error($this->msgNotFoundId);
                return $this->forward($indexUri);
            }

            $form = new $this->{'formClass'}($this->model, ['edit' => true]);
        } else {
            $form = new $this->{'formClass'}(null, ['edit' => true]);
        }

        $this->view->form   = $form;
        $this->view->model  = $this->model;
        $data               = $this->request->getPost();

        /** @var \Phalcon\Forms\Form $form */
        if (!$form->isValid($data, $this->model)) {
            foreach ($form->getMessages() as $message) {
                $this->flash->error($message);
            }

            return $this->forward($editUri);
        }

        if ($this->model->save() == false) {
            foreach ($this->model->getMessages() as $message) {
                $this->flash->error($message);
            }

            return $this->forward($editUri);
        }

        $form->clear();
        return true;
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
        /** @var string|object $modelClass */
        $modelClass     = $this->modelClass;
        $this->model    = $modelClass::findFirstById($id);
        $controller     = $this->router->getControllerName();

        if (!$this->model) {
            $this->flash->error($this->msgNotFoundId);
            return $this->forward("$controller/index");
        }

        if (!$this->model->delete()) {
            foreach ($this->model->getMessages() as $message) {
                $this->flash->error($message);
            }

            return $this->forward("$controller/search");
        }

        return true;
    }

    /**
     * After save event.
     */
    public function afterSave()
    {
        $controller = $this->router->getControllerName();
        $this->flash->success($this->msgSavedSuccess);
        $this->forward("$controller/index");
    }

    /**
     * After delete event.
     */
    public function afterDelete()
    {
        $controller = $this->router->getControllerName();
        $this->flash->success($this->msgItemDeleted);
        $this->forward("$controller/index");
    }

    /**
     * Forward the request to another controller/action.
     *
     * @param string $uri
     *
     * @return null
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

}
