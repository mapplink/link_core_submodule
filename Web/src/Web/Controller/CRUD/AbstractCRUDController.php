<?php
/**
 * Magelink\Controller
 *
 * @category    Magelink
 * @package     Magelink\Controller
 * @author      Sean Yao <sean@lero9.com>
 * @copyright   Copyright (c) 2014 LERO9 Ltd.
 * @license     Commercial - All Rights Reserved
 */

namespace Web\Controller\CRUD;

use Magelink\Exception\MagelinkException;
use Web\Controller\BaseController;
use Web\Helper\Paginator;
use Web\Form\DoctrineZFBaseForm;
use Web\Helper\ListViewSorter;
use Web\Helper\CRUDRouteGenerator;
use Web\Helper\CRUDSearchFilter;
use Zend\View\Model\ViewModel;
use Zend\Filter\Word\CamelCaseToSeparator;

/**
 * AbstractCRUDController for generating admin interface
 */
abstract class AbstractCRUDController extends BaseController
{

    /** @var \Web\Helper\CRUDRouteGenerator $this->routeGenerator */
    protected $routeGenerator;
    /** @var  \Doctrine\ORM\QueryBuilder $this->queryBuilder */
    protected $queryBuilder;

    protected $name;
    protected $formClassName;
    protected $searchFilter;
    protected $columnSorter;
    protected $isFilterOn = FALSE;


    public function __construct()
    {
        $this->setDefaultName();
        $this->setDefaultFormClassName();
    }

    /**
     * Child classes should override to return the Entity class name that this CRUD controller works on.
     * @return string
     */
    abstract protected function getEntityClass();

    /**
     * Child classes can override to return the number of items per page
     * @return int
     */
    protected function getResultsPerPage()
    {
        return 20;
    }

    /**
     * Child classes can override to return whether or not this CRUD controller supports creating entities
     * @return boolean
     */
    protected function getEnableCreate(){
        return true;
    }

    /**
     * Child classes can override to return whether or not this CRUD controller supports listing entities
     * @return boolean
     */
    protected function getEnableRead(){
        return true;
    }

    /**
     * Child classes can override to return whether or not this CRUD controller supports editing entities
     * @return boolean
     */
    protected function getEnableEdit(){
        return true;
    }

    /**
     * Child classes can override to return whether or not this CRUD controller supports deleting entities
     * @return boolean
     */
    protected function getEnableDelete(){
        return true;
    }

    /**
     * Get paginator for list view
     * @return object
     */
    protected function getPaginator()
    {
        if (!($queryBuilder = $this->getQueryBuilder())) {
            $queryBuilder = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('a')
                ->from($this->getEntityClass(), 'a');

            $this->setQueryBuilder($queryBuilder);
        }

        $this->getSearchFilter()->process($this->getQueryBuilder(), $this->getRequest());
        $this->getColumnSorter()->processQueryBuilder($this->getQueryBuilder(), $this->getRequest());

        $paginator = new Paginator($this->getQueryBuilder()->getQuery(), $this->getResultsPerPage());

        $paginator->setPage($this->params('page', 1))
            ->setRouteName($this->getRouteGenerator()->getRouteName('list'))
            ->setRouteQueries($this->params()->fromQuery());

        return $paginator;
    }

    /**
     * List view
     */
    public function listAction()
    {
        if (!$this->getEnableRead()) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        $viewModel = new ViewModel(array(
            'paginator' => $this->getPaginator(),
            'routeControl' => $this->getRouteGenerator(),
            'listConfig' => $this->getListViewConfig(),
            'title' => $this->getName(),
            'hasFilter' => $this->hasSearchFilter(),
            'isFilterOn' => $this->getSearchFilter()->isFilterOn(),
            'sortedField' => $this->getColumnSorter()->getSortedField(),
            'sortedDirection' => $this->getColumnSorter()->getSortedDirection(),
            'isCreateEnabled' => $this->getEnableCreate(),
        ));

        $viewModel->addChild($this->getSearchFilter()->buildView($this->getSearchFilter()->isFilterOn()), 'searchFilterBox');

        $viewModel->setTemplate('web/admin/list');

        return $viewModel;
    }

    /**
     * Update an object
     */
    public function editAction()
    {
        if (!$this->getEnableEdit()) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        //$this->formClassName
        $objectRepo = $this->getObjectRepo();

        $object = $objectRepo->find($this->params('id'));

        if (!$object) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        $form = $this->createOrUpdate($object, 'The ' . $this->name . ' has been updated.');

        if (!($form instanceof DoctrineZFBaseForm)) {
            return $form;
        }

        $viewModel = new ViewModel(array(
            'routeControl' => $this->getRouteGenerator(),
            'title' => $this->getName(),
            'form' => $form,
            'isDeleteEnabled' => $this->getEnableDelete(),
        ));

        $viewModel->setTemplate('web/admin/edit');

        return $viewModel;
    }

    /**
     * Get form
     * @param  object $object
     * @return \Zend\Form\Form
     */
    protected function getForm($object)
    {
        $classRelection = new \ReflectionClass($this->formClassName);
        $form = $classRelection->newInstanceArgs(array($this->getEntityManager()));
        $form->bind($object);

        return $form;
    }

    /**
     * Add an new object
     */
    public function createAction()
    {
        if (!$this->getEnableCreate()) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        $classRelection = new \ReflectionClass($this->getEntityClass());

        $form = $this->createOrUpdate($classRelection->newInstance(), 'An new ' . $this->name . ' has been created.');


        if (!($form instanceof DoctrineZFBaseForm)) {
            return $form;
        }

        $form->get('submit')->setValue('Create');

        $viewModel = new ViewModel(array(
            'form' => $form,
            'routeControl' => $this->getRouteGenerator(),
            'title' => $this->getName(),
        ));

        $viewModel->setTemplate('web/admin/edit');

        return $viewModel;
    }

    /**
     * Update or create a object with form
     * @param  object $object
     * @param  string $message
     * @return mixed
     */
    protected function createOrUpdate($object, $message)
    {

        $form = $this->getForm($object);
        $request = $this->getRequest();

        if ($request->isPost()) {
            $form->setData($request->getPost());

            try {
                if ($form->isValid() && $form->save()) {
                    $this->flashMessenger()->setNamespace('success')->addMessage($message);

                    $this->redirect()->toRoute(
                        $this->getRouteGenerator()->getRouteName('edit'),
                        array('id' => $object->getId())
                    );
                }else{
                    throw new MagelinkException('A problem occurred on save. Please check data entered.');
                }
            }catch(\Exception $exception) {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('Save failed with error: '.$exception->getMessage());
            }
        }

        return $form;
    }

    /**
     * Delete a object
     */
    public function deleteAction()
    {
        if (!$this->getEnableDelete()) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        $objectRepo = $this->getObjectRepo();
        $object = $objectRepo->find($this->params('id'));

        if (!$object) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        $this->removeEntity($object);

        $this->flashMessenger()->setNamespace('success')->addMessage('The ' . $this->name . ' has been deleted.');

        return $this->redirect()->toRoute($this->getRouteGenerator()->getRouteName('list'));
    }

    /**
     * Get search filter object
     * @return CRUDSearchFilter
     */
    protected function getSearchFilter()
    {
        if (!$this->searchFilter) {
            $this->searchFilter = new CRUDSearchFilter(
                $this->getSearchFilterConfig(),
                get_called_class()
            );
        }

        return $this->searchFilter;
    }

    /**
     * Get sorter
     * @return ListViewSorter
     */
    protected function getColumnSorter()
    {
        if (!$this->columnSorter) {
            $this->columnSorter = new ListViewSorter($this->getListViewConfig());
        }

        return $this->columnSorter;
    }

    /**
     * Get search filter config
     * @return array
     */
    protected function getSearchFilterConfig()
    {
        return array();
    }

    /**
     * Check if search filter has been set
     * @return boolean
     */
    protected function hasSearchFilter()
    {
        $config = $this->getSearchFilterConfig();
        return !empty($config);
    }

    /**
     * Guess the form class based on entity class name
     */
    protected function setDefaultFormClassName()
    {
        if (!$this->formClassName) {
            $this->formClassName = str_replace('\\Entity\\', '\\Form\\', $this->getEntityClass()) . 'Form';
        }
    }

    /**
     * Set default name for admin
     */
    protected function setDefaultName()
    {
        if (!$this->name) {
            $classRelection = new \ReflectionClass($this->getEntityClass());
            $name = $classRelection->getShortName();
            $camelCaseToSeparatorFilter = new CamelCaseToSeparator();
            $name = $camelCaseToSeparatorFilter->filter($name);

            $this->name = $name;
        }
    }

    /**
     * Get name for the admin templates
     * @return string
     */
    protected function getName()
    {
        return $this->name;
    }

    /**
     * Get route control object
     * @return CRUDRouteGenerator
     */
    public function getRouteGenerator()
    {
        if (!$this->routeGenerator) {
            $this->routeGenerator = new CRUDRouteGenerator($this);
        }

        return $this->routeGenerator;
    }

    /**
     * Get doctrine repository for the entity
     * @return \Doctrine\ORM\EntityRepository
     */
    protected function getObjectRepo()
    {
        return $this->getRepo($this->getEntityClass());
    }

    /**
     * Get doctrine query builder
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

    /**
     * Set doctrine query builder
     */
    protected function setQueryBuilder($queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Config the fields to be displayed in list view
     * @return array
     */
    protected function getListViewConfig()
    {
        return array();
    }
}
