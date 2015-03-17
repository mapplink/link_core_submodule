<?php
/**
 * Web\Controller
 *
 * @category Web
 * @package Web\Controller
 * @author Seo Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Web\Controller\Entity;

use Entity\Entity;
use Magelink\Exception\MagelinkException;
use Web\Controller\BaseController;
use Web\Helper\BaseEntityAttributes;

use Zend\Db\TableGateway\TableGateway;
use Zend\View\Model\ViewModel;


abstract class AbstractEntityController extends BaseController
{

    /**
     * Should be overridden with the required route name, to be used in URL generation
     * @return string
     */
    public abstract function getRouteName();

    /**
     * Should be overridden if the implementing controller represents actions of a specific node.
     * @return int
     */
    public function getNodeId()
    {
        return 0;
    }

    /**
     * Returns the title to be used for the grid
     * @return string
     */
    protected function getTitle()
    {
        $title = ucfirst($this->getEntityType()).' entity details';
        // <<<Temporary solution : Revise till 13 Feb 2015 at latest
        if ($this->getEntityType() == 'picklist' || strpos($this->getEntityType(), 'order') === 0) {
            $title .= ' - <span class="flagged">Please do NEVER change anything while the entity is in PICKING state!</span>';
        }
        // Temporary solution : Revise till 13 Feb 2015 at latest

        return $title;
    }

    /**
     * Returns an array of extra static actions available above this grid
     */
    protected function getExtraActions()
    {
        return array();
    }

    /**
     * Returns an array of mass actions available on this grid
     * @return array
     */
    protected function getMassActions()
    {
        return array();
    }

    /**
     * Returns an array of simple actions available for the given Entity
     * @param Entity $entity
     * @return array
     */
    protected function getSimpleActions(\Entity\Entity $entity)
    {
        return array();
    }

    /**
     * Returns the minimum filter data to be always applied on this grid (NOTE: will override any filters attempted to be applied to these fields)
     * @return array
     */
    protected function getMinimumFilter()
    {
        return array('searchData'=>array(), 'searchType'=>array());
    }

    /**
     * Returns the default filter to be applied to this grid
     * @todo Implement
     * @return array
     */
    protected function getDefaultFilter()
    {
        return array('searchData'=>array(), 'searchType'=>array());
    }

    /**
     * Returns the default sort to be used when none is specified by the user.
     * @return array
     */
    protected function getDefaultSort()
    {
        return array('ENTITY_ID'=>'DESC');
    }

    /**
     * Can/should be overridden by subclasses to define which fields from the Entity record itself should be displayed
     * @return array
     */
    protected function getStaticFields()
    {
        /** @var \Entity\Service\EntityConfigService $entityConfigService */
        $entityConfigService = $this->getServiceLocator()->get('entityConfigService');

        $staticFields = $entityConfigService->getStaticFields();
        $mapFieldsLabel = array(
            'UPDATED_AT'=>'Last Updated At'
        );

        $staticFieldLabels = array();
        foreach ($staticFields as $field) {
            if (isset($mapFieldsLabel[$field])) {
                $label = $mapFieldsLabel[$field];
            }else{
                $label = ucwords(str_replace('_', ' ', strtolower($field)));
            }
            $staticFieldLabels[$field] = $label;
        }

        return $staticFieldLabels;
    }

    /**
     * Can be overridden by subclasses to define which Entity Attributes should be displayed. Null (default return) means all attributes. NOTE: This should be a value-only array, not associative.
     * @return array|null
     */
    protected function getDynamicFields()
    {
        return NULL;
    }

    protected function getEnableView()
    {
        return FALSE;
    }

    protected function getEnableCreate()
    {
        return FALSE;
    }

    protected function getEnableEdit()
    {
        return TRUE;
    }

    protected function getEnableDelete()
    {
        return FALSE;
    }

    /**
     * Child classes must override to specify the entity type for the grid
     * @return string
     */
    abstract protected function getEntityType();

    /**
     * Returns all the available attributes for an entity type, as an array where the key is the attribute ID and the value is the attribute data array
     * @param $entity_type
     * @return array
     */
    protected function getAttributes($entityType)
    {
        /** @var \Entity\Service\EntityConfigService $entityConfigService */
        $entityConfigService = $this->getServiceLocator()->get('entityConfigService');

        $attributesRaw = $entityConfigService->getAttributesCode($entityType);
        asort($attributesRaw);

        $attributes = array();
        foreach ($attributesRaw as $attributeId => $attributeCode) {
            $attributes[$attributeId] = $entityConfigService->getAttribute($attributeId);

        }
        return $attributes;
    }

    /**
     * Retrieves the selected entity for use in simple action handlers
     * @return \Entity\Entity
     */
    protected function getSelectedEntity()
    {
        if($this->params()->fromRoute('entity_id')){
            /** @var \Entity\Service\EntityService $entityService */
            $entityService = $this->getServiceLocator()->get('entityService');
            return $entityService->loadEntityId($this->getNodeId(), $this->params()->fromRoute('entity_id'));
        }else if($this->params()->fromQuery('entity_id')){
            /** @var \Entity\Service\EntityService $entityService */
            $entityService = $this->getServiceLocator()->get('entityService');
            return $entityService->loadEntityId($this->getNodeId(), $this->params()->fromQuery('entity_id'));
        }else{
            return NULL;
        }
    }

    /**
     * For a massaction request, returns the affected Entity objects
     * @throws MagelinkException If invalid data is passed
     * @return \Entity\Entity[]
     */
    protected function getMassactionEntities()
    {

        /** @var \Entity\Service\EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');

        $data = $this->getRequest()->getContent();
        if(!strlen($data) && $this->params()->fromQuery('entity_id')){
            return array($entityService->loadEntityId($this->getNodeId(), $this->params()->fromQuery('entity_id')));
        }
        $data = json_decode($data);
        if(!$data){
            throw new MagelinkException('Invalid massaction data passed!');
        }

        $affectedIds = array();
        foreach ($data as $object) {
            if (is_object($object)) {
                $object = get_object_vars($object);
            }
            if($object['value'] == '1' || $object['value'] == 1){
                $affectedIds[] = intval($object['id']);
            }
        }

        return $entityService->locateEntity(
            $this->getNodeId(),
            $this->getEntityType(),
            0,
            array('ENTITY_ID'=>$affectedIds),
            array('ENTITY_ID'=>'in'),
            array('sort'=>array('ENTITY_ID'=>'ASC'))
        );
    }

    /**
     * Should be called at the end of a massaction request, will return the appropriate action to perform
     * @param string $action The action to perform - generally, reload or redirect
     * @param array $data Data to go with the above action
     */
    protected function sendMassactionResponse($action, $message=null, $data=array())
    {
        print json_encode(array(
                'action'=>$action,
                'message'=>$message,
                'data'=>$data)
        );
        print PHP_EOL;
        die();
    }

    /**
     * Internal function, should not be overridden. Appends mandatory extra actions where enabled.
     * @return array
     */
    protected function getAllExtraActions()
    {
        $actions = $this->getExtraActions();
        if($this->getEnableCreate()){
            $actions['create'] = array(
                'label'=>'Create New',
                'action'=>'create',
            );
        }
        return $actions;
    }

    /**
     * Internal function, should not be overridden. Appends mandatory mass actions where enabled.
     * @return array
     */
    protected function getAllMassActions()
    {
        $actions = $this->getMassActions();
        if($this->getEnableDelete()){
            $actions['delete'] = array(
                'label'=>'Delete',
                'action'=>'delete',
                'icon'=>'times',
            );
        }
        return $actions;
    }

    /**
     * Internal function, should not be overridden. Appends mandatory simple actions where enabled.
     * @param Entity $entity
     * @return array
     */
    protected function getAllSimpleActions(\Entity\Entity $entity)
    {
        $actions = $this->getSimpleActions($entity, FALSE);
        if($this->getEnableEdit()){
            $actions = array_merge(array(
                    'edit'=>array(
                        'label'=>'Edit',
                        'action'=>'edit',
                        'method'=>'LINK',
                        'icon'=>'edit',
                    )
                ), $actions);
        }
        if($this->getEnableView()){
            $actions = array_merge(array(
                    'view'=>array(
                        'label'=>'View',
                        'action'=>'view',
                        'method'=>'LINK',
                        'icon'=>'align-left',
                    )
                ), $actions);
        }
        return $actions;
    }

    /**
     * Returns the maximum number of simple actions (used for calculating column width)
     * @return int
     */
    protected function getMaxSimpleActions()
    {
        $count = 0;
        if($this->getEnableEdit() || $this->getEnableView()){
            $count++;
        }
        if($this->getEnableDelete()){
            $count++;
        }
        return $count;
    }

    /**
     * By default simply calls gridAction. Can be overridden to render a slightly different page.
     * @return array|ViewModel
     */
    public function indexAction()
    {
        return $this->gridAction();
    }

    /**
     * Renders the grid page itself
     * @return ViewModel
     * @throws \Magelink\Exception\MagelinkException
     */
    public function gridAction($sortOrder = 'desc')
    {
        $entityType = $this->getEntityType();

        /** @var \Entity\Service\EntityConfigService $entityConfigService */
        $entityConfigService = $this->getServiceLocator()->get('entityConfigService');

        $entityTypeId = $entityConfigService->parseEntityType($entityType);
        if (!$entityTypeId) {
            throw new MagelinkException('Invalid entity type '.$entityType);
        }

        $attributes = $this->getAttributes($entityType);
        $massActions = $this->getAllMassActions();

        $columns = array();
        $columnLabels = array();

        $columnLabels[] = 'Select';
        $columns[] = json_encode(array(
                'name'=>'massedit',
                'label'=>'Select',
                'resizable'=>false,
                'formatter'=>'checkbox',
                'formatoptions'=>array(
                    'disabled'=>false,
                ),
                'edittype'=>'checkbox',
                'editoptions'=>array('value'=>'1:0'),
                'sortable'=>false,
                'search'=>false,
                'width'=>'15px',
            ));

        $columnLabels[] = 'Actions';
        $actionsCol = array(
            'name'=>'actions',
            'label'=>'Actions',
            'resizable'=>false,
            'editable'=>false,
            'searchable'=>false,
            'search'=>false,
            'sortable'=>false,
            'formatter'=>null,
            'width'=>'77' //(string) (18 * $this->getMaxSimpleActions()),
        );
        $columns[] = json_encode($actionsCol);

        $minFilter = $this->getMinimumFilter();

        foreach ($this->getStaticFields() as $code => $label) {
            $cdata = array(
                'name'=>$code,
                'editable'=>false,
                'align'=>'center',
                'formatoptions'=>array(),
            );
            if($code == 'ENTITY_ID'){
                $cdata['key'] = true;
                $cdata['formatter'] = 'integer';
                $cdata['formatoptions']['thousandsSeparator'] = '';
                $cdata['formatoptions']['defaultValue'] = '';
            }
            if($code == 'STORE_ID' || $code == 'PARENT_ID'){
                $cdata['formatter'] = 'integer';
            }

            if(array_key_exists($code, $minFilter['searchData'])){
                $cdata['search'] = FALSE;
            }

            $columns[] = json_encode($cdata);
            $columnLabels[] = $label;
        }

        $dynamicAttributes = $this->getDynamicFields();

        $attributesSorted = array();
        if ($dynamicAttributes == NULL) {
            $attributesSorted = $attributes;
        }else{
            foreach ($dynamicAttributes as $code) {
                foreach ($attributes as $attributeId=>$attributeData) {
                    if ($attributeData['code'] == $code) {
                        $attributesSorted[$attributeId] = $attributeData;
                    }
                }
            }
        }

        foreach ($attributesSorted as $attributeId=>$attributeData) {

            if($dynamicAttributes != null && !in_array($attributeData['code'], $dynamicAttributes)) {
                continue;
            }

            $display_data = (isset($attributeData['display_data']) && strlen($attributeData['display_data']))
                ? unserialize($attributeData['display_data']) : array();
            $fetch_data = (isset($attributeData['fetch_data']) && strlen($attributeData['fetch_data']))
                ? unserialize($attributeData['fetch_data']) : array();

            $label = $attributeData['name'];
            if($attributeData['type'] == 'entity' && isset($fetch_data['fkey_type'])){
                $label .= ' {'.$fetch_data['fkey_type'].'}';
            }else if($attributeData['type'] == 'fkey' && isset($fetch_data['fkey_table'])){
                $label .= ' [' . $fetch_data['fkey_table'] . ']';
            }

            $cdata = array(
                'name'=>$attributeData['code'],
                'label'=>$label,
                'resizable'=>true,
                'index'=>$attributeData['code'],
                'editable'=>true,
                'align'=>'center',
                'editoptions'=>array(),
                'searchoptions'=>array(),
                'formatoptions'=>array(),
            );

            $cdata['width'] = '90';
            if (isset($display_data['render_func']) || isset($display_data['render_fkey_func'])
                || isset($display_data['fkey_render_field'])) {

                $cdata['editable'] = false;
                $cdata['formatter'] = null;
                $cdata['searchable'] = false;
                $cdata['search'] = false;
                $cdata['sortable'] = false;

            }else{
                switch ($attributeData['type']) {

                    case 'decimal':
                        $cdata['formatter'] = 'number';
                        $cdata['formatoptions']['decimalPlaces'] = 4;
                        $cdata['formatoptions']['defaultValue'] = '';
                        $cdata['align'] = 'right';
                        if(isset($fetch_data['price']) && $fetch_data['price']){
                            $cdata['formatoptions']['prefix'] = '$';
                        }
                        break;

                    case 'int':
                        $cdata['formatter'] = 'integer';
                        $cdata['align'] = 'left';
                        break;

                    case 'fkey':
                        if(isset($fetch_data['fkey_table'])){
                            if(isset($fetch_data['fkey_editable']) && $fetch_data['fkey_editable']){
                                $cdata['edittype'] = 'select';
                                $cdata['editoptions']['dataUrl'] = $this->url()->fromRoute($this->getRouteName(),
                                        array('action'=>'fkeydata', 'type'=>$entityType)).'?att='.$attributeData['code'];
                                $cdata['stype'] = 'select';
                                $cdata['searchoptions']['dataUrl'] = $cdata['editoptions']['dataUrl'];
                            }else if(!isset($display_data['enum'])){
                                $cdata['formatter'] = 'integer';
                                $cdata['editable'] = false;
                                $cdata['formatoptions']['thousandsSeparator'] = '';
                                $cdata['formatoptions']['defaultValue'] = '';
                            }
                        }else{
                            $cdata['formatter'] = 'integer';
                            $cdata['editable'] = false;
                            $cdata['formatoptions']['thousandsSeparator'] = '';
                            $cdata['formatoptions']['defaultValue'] = '';
                        }
                        break;

                    case 'entity':
                        if(isset($fetch_data['fkey_type'])){
                            if(isset($fetch_data['fkey_editable']) && $fetch_data['fkey_editable']){
                                $cdata['edittype'] = 'select';
                                $cdata['editoptions']['dataUrl'] = $this->url()->fromRoute($this->getRouteName(),
                                        array('action'=>'fkeydata', 'type'=>$entityType)).'?att='.$attributeData['code'];
                                $cdata['stype'] = 'select';
                                $cdata['searchoptions']['dataUrl'] = $cdata['editoptions']['dataUrl'];
                            }else if(!isset($display_data['enum'])){
                                $cdata['formatter'] = 'integer';
                                $cdata['editable'] = false;
                                $cdata['formatoptions']['thousandsSeparator'] = '';
                                $cdata['formatoptions']['defaultValue'] = '';
                            }
                        }else{
                            $cdata['formatter'] = 'integer';
                            $cdata['editable'] = false;
                            $cdata['formatoptions']['thousandsSeparator'] = '';
                            $cdata['formatoptions']['defaultValue'] = '';
                        }
                        break;

                    case 'datetime':
                        $cdata['formatter'] = null;
                        $cdata['width'] = '120';
                        break;

                    case 'multi':
                        $cdata['formatter'] = null;
                        if (strpos($attributeData['code'], 'missing_stock') !== FALSE
                            || strpos($attributeData['code'], 'follow_up_date')) {
                            $cdata['align'] = 'center';
                        }else{
                            $cdata['align'] = 'left';
                            if (strpos($attributeData['code'], 'payment_method') !== FALSE
                                || strpos($attributeData['code'], 'shipping_method') !== FALSE) {
                                $cdata['width'] = '150';
                            }
                        }

                        break;
                    default:
                        if(strpos($attributeData['code'], 'email') !== false){
                            $cdata['formatter'] = 'email';
                            $cdata['width'] = '150';
                        }else{
                            $cdata['width'] = '100';
                        }
                        break;
                }

                if(isset($display_data['align'])){
                    $cdata['align'] = $display_data['align'];
                }
                if(isset($display_data['formatter'])){
                    $cdata['formatter'] = $display_data['formatter'];
                }
                if(isset($display_data['enum'])){
                    $cdata['edittype'] = 'select';
                    //$cdata['editoptions']['dataUrl'] = $this->url()->fromRoute($this->getRouteName(), array('action'=>'enumdata', 'type'=>$entityType)).'?att='.$attributeData['code'];
                    $cdata['editoptions']['value'] = $this->getEnumDataSimple($attributeData);
                    $cdata['stype'] = 'select';
                    //$cdata['searchoptions']['dataUrl'] = $cdata['editoptions']['dataUrl'];
                    $cdata['searchoptions']['value'] = $cdata['editoptions']['value'];
                    //$cdata['formatter'] = 'select';
                }
                if(isset($display_data['boolean']) && $attributeData['type'] == 'int'){
                    $cdata['edittype'] = 'checkbox';
                    $cdata['editoptions']['value'] = '1:0';
                    $cdata['formatter'] = 'checkbox';
                }
            }

            if(array_key_exists($attributeData['code'], $minFilter['searchData'])){
                $cdata['search'] = false;
            }

            $columnLabels[] = $label;
            $columns[] = json_encode($cdata);
        }

        $view = new ViewModel(array(
            'entityType'=>$entityType,
            'queryData'=>array('minimumFilter'=>json_encode($this->getMinimumFilter())),
            'sortOrder'=>$sortOrder,
            'title'=>ucfirst($entityType).' entities',
            'colLabels'=>'["' . implode('", "', $columnLabels) . '"]',
            'colConfig'=>'[' . implode(', ', $columns) . ']',
            'massActions'=>$massActions,
            'extraActions'=>$this->getAllExtraActions(),
            'routeName'=>$this->getRouteName(),
        ));
        $view->setTemplate('web/entity/grid');

        return $view;
    }

    /**
     * Generic Entity view action
     * @todo
     */
    public function viewAction(){}

    /**
     * Generic Entity create action
     * @todo
     */
    public function createAction(){}

    /**
     * Generic Entity edit action
     * @todo
     */
    public function editAction()
    {
        $entityType = $this->getEntityType();
        $entity = $this->getSelectedEntity();
        if(!$this->getEnableEdit() || !$entity){
            $this->getResponse()->setStatusCode(404);
            return;
        }

        /** @var \Entity\Service\EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');
        /** @var \Entity\Service\EntityConfigService $entityConfigService */
        $entityConfigService = $this->getServiceLocator()->get('entityConfigService');

        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            $message = 'Entity has been successfully updated!';

            // ToDo: unique_id update

            unset($data->submit);
            foreach ($this->getStaticFields() as $code=>$label) {
                $key = strtolower($code);
                unset($data->$key);
            }

            $dataArray = (array) $data;
            $dataArray['entityTypeStr'] = $entity->getTypeStr();
            $attributeHelper = new BaseEntityAttributes($entityConfigService);

            foreach ($attributeHelper->getAllAttributesAsFormFields($dataArray) as $fieldName=>$fieldData) {
                $isReadonly = isset($fieldData['attributes']['readonly']);
                if ($isReadonly) {
                    unset($data->{$fieldData['name']});
                }
            }

            foreach ($attributeHelper->getCombinedMultiFields($dataArray) as $multiFieldName=>$multiFieldData) {
                $data->set($multiFieldName, $multiFieldData['multi']);
                unset($data->{$multiFieldData['key']}, $data->{$multiFieldData['value']});
            }

            $dataArray = (array) $data;
            foreach ($dataArray as $attributeCode=>$fieldData) {
                $hasValue = $fieldData !== NULL && $fieldData !== '' && $fieldData !== array(''=>NULL);
                if (!$hasValue) {
                    unset($data->$attributeCode);
                }
            }

            $entityService->updateEntity($this->getNodeId(), $entity, (array) $data);
            $entityService->createEntityComment(
                $entity, $this->getCurrentUser()->getDisplayName(), 'Entity Edited', $message);
            $this->flashMessenger()->setNamespace('success')
                ->addMessage($message);

            return $this->redirect()->toUrl($this->request->getRequestUri());

        }else{

            $form = new \Web\Form\Entity\EditForm($this->getEntityType(), 'entity-edit-form');
            $form->setServiceLocator($this->getServiceLocator());
            $form->bind($entity);

            $title = 'Entity Details: '.$entityType;
            // <<<Temporary solution : Revise till 13 Feb 2015 at latest
            if ($entityType == 'picklist' || strpos($entityType, 'order') === 0) {
                $title .= ' - Please do NEVER change anything while the entity is in PICKING state!';
            }
            // Temporary solution : Revise till 13 Feb 2015 at latest


            $data = array(
                'title'=>$this->getTitle(),
                'entity_type'=>$this->getEntityType(),
                'form'=>$form,
                'entity'=>$entity,
                'route'=>$this->getRouteName(),
                'simpleActions'=>$this->getSimpleActions($entity),
            );
            $data = array_merge($data, $this->getEditViewdata($entity));

            $view = new ViewModel($data);

            $this->addEntityComments($view, $entity, true);

            if(isset($data['view_template'])){
                $view->setTemplate($data['view_template']);
            }else{
                $view->setTemplate('web/entity/edit');
            }

            return $view;
        }
    }

    public function addcommentAction(){
        $entityType = $this->getEntityType();
        $entity_id = $this->params()->fromQuery('entity_id');
        $text = $this->params()->fromPost('commentBody');
        $visible = $this->params()->fromPost('commentVisible', 0);
        $redirect = $this->params()->fromPost('redirect', 'edit');

        if($text && strlen(trim($text))){

            /** @var \Entity\Service\EntityService $entityService */
            $entityService = $this->getServiceLocator()->get('entityService');
            /** @var \Zend\Authentication\AuthenticationService $authService */
            $authService = $this->getServiceLocator()->get('zfcuser_auth_service');

            $entity = $entityService->loadEntityId($this->getNodeId(), $entity_id);

            $entityService->createEntityComment($entity, $authService->getIdentity()->getDisplayName(), 'Staff Comment', $text, '', $visible ? true : false, $this->getNodeId());
            $this->flashMessenger()->setNamespace('success')->addMessage('Comment successfully added!');

        }

        $this->redirect()->toRoute($this->getRouteName(), array('action'=>$redirect, 'entity_id'=>$entity_id, 'entity_type'=>$this->getEntityType()));
    }

    /**
     * Adds entity comments view to specified ViewModel
     * @param ViewModel $view The ViewModel in use for this page
     * @param Entity $entity The entity for which to display the comments of
     * @param bool $allowAdd Whether to show UI elements for adding new comments
     * @return ViewModel
     */
    protected function addEntityComments(ViewModel $view, \Entity\Entity $entity, $allowAdd=true)
    {
        /** @var \Entity\Service\EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');

        $comments = array_reverse($entityService->loadEntityComments($entity));

        $commentView = new ViewModel();
        $commentView->setTemplate('web/entity/comments');
        $commentView->setVariables(array(
                'comments' => $comments,
                'entity' => $entity,
                'allowAdd' => $allowAdd,
                'route' => $this->getRouteName(),
            ));

        $view->addChild($commentView, 'entityComments');

        return $view;
    }

    protected function getEditViewdata(\Entity\Entity $entity)
    {
        return array();
    }

    protected function getEnumDataSimple($data)
    {
        $entityType = $this->getEntityType();
        if(!isset($data['display_data']) || !strlen($data['display_data'])){
            throw new MagelinkException('No display data for ' . $data['code']);
        }
        $display_data = unserialize($data['display_data']);
        if(!isset($display_data['enum']) || !is_array($display_data['enum'])){
            throw new MagelinkException('No enum data for ' . $data['code']);
        }
        $ret = array(':');
        foreach($display_data['enum'] as $k=>$v){
            $ret[] = $k.':'.$v;
        }
        return implode(';', $ret);
    }

    public function enumdataAction()
    {
        $entityType = $this->getEntityType();
        $attributes = $this->params()->fromQuery('att');

        $attributes = $this->getAttributes($entityType);
        foreach($attributes as $id=>$data){
            if($data['code'] == $attributes){
                if(!isset($data['display_data']) || !strlen($data['display_data'])){
                    echo '<!-- No display data -->'.PHP_EOL;
                    die();
                }
                $display_data = unserialize($data['display_data']);
                if(!isset($display_data['enum']) || !is_array($display_data['enum'])){
                    echo '<!-- No enum data -->'.PHP_EOL;
                    die();
                }
                echo '<select>'.PHP_EOL.'<option value="__NULL__"></option>'.PHP_EOL;
                foreach($display_data['enum'] as $k=>$v){
                    echo '<option value="'.$k.'">'.$v.'</option>'.PHP_EOL;
                }
                echo '</select>'.PHP_EOL;
                die();
            }
        }
        // Could not find attribute?
        echo '<!-- Could not find -->'.PHP_EOL;
        die();
    }

    /**
     * @param Entity $entity
     * @return string
     */
    protected function getActionsHtml(\Entity\Entity $entity)
    {
        $html = array();
        foreach ($this->getAllSimpleActions($entity) as $key=>$data) {
            $method = 'executeSimpleAction';
            if (isset($data['simple_method'])) {
                $method = $data['simple_method'];
            }elseif (isset($data['method'])) {
                $method = $data['method'];
            }

            if (isset($data['simple_action'])) {
                $action = $data['simple_action'];
            }else{
                $action = $data['action'];
            }

            if ($method == 'LINK'){
                $html[] = '<a class="mlgrid-simple-action" title="'.$data['label']
                    .'" href="'.$this->url()->fromRoute(
                        $this->getRouteName(),
                        array(
                            'action'=>$action,
                            'type'=>$this->getEntityType(),
                            'entity_id'=>$entity->getId())).'"'
                    .(isset($data['target']) ? ' target="'.$data['target'].'"' : '').'>'
                    .(isset($data['icon']) ? '<i class="fa fa-'.$data['icon'].'"></i>' : $data['label']).'</a>';
            }else{
                $html[] = '<a class="mlgrid-simple-action" title="'.$data['label']
                    .'" onclick="return '.$method.'('.$entity->getId().', \''
                    .$this->url()->fromRoute(
                        $this->getRouteName(),
                        array(
                            'action'=>$action,
                            'type'=>$this->getEntityType(),
                            'entity_id'=>$entity->getId())).'\');"'
                    .' href="#"'.(isset($data['target']) ? ' target="'.$data['target'].'"' : '').'>'
                    .(isset($data['icon']) ? '<i class="fa fa-'.$data['icon'].'"></i>' : $data['label']).'</a>';
            }
        }
        if(count($html)){
            return implode('&nbsp;', $html);
        }else{
            return ' ';
        }
    }

    public function dataAction()
    {
        /** @var \Entity\Service\EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');
        /** @var \Entity\Service\EntityConfigService $entityConfigService */
        $entityConfigService = $this->getServiceLocator()->get('entityConfigService');

        $entityType = $this->getEntityType();
        $entityTypeId = $entityConfigService->parseEntityType($entityType);
        if (!$entityTypeId) {
            throw new MagelinkException('Invalid entity type '.$entityType);
        }

        $staticAttributes = $this->getStaticFields();
        $dynamicAttributes = $this->getDynamicFields();
        $attributes = $this->getAttributes($entityType);

        $attributesCodes = array();
        $attributesDisplayData = array();
        $attributesFetchData = array();

        foreach ($attributes as $attributeData) {

            if ($dynamicAttributes != null && !in_array($attributeData['code'], $dynamicAttributes)) {
                continue;
            }
            $attributesDisplayData[$attributeData['code']] =
                isset($attributeData['display_data']) && strlen($attributeData['display_data'])
                    ? unserialize($attributeData['display_data']) : array();
            $attributesFetchData[$attributeData['code']] =
                isset($attributeData['fetch_data']) && strlen($attributeData['fetch_data'])
                    ? unserialize($attributeData['fetch_data']) : array();
            $attributesCodes[] = $attributeData['code'];
        }

        $attributesSorted = array();
        if($dynamicAttributes == null){
            $attributesSorted = $attributes;
        }else{
            foreach($dynamicAttributes as $code){
                foreach($attributes as $aid=>$attributeData){
                    if($attributeData['code'] == $code){
                        $attributesSorted[$aid] = $attributeData;
                    }
                }
            }
        }

        $rows = $this->params()->fromQuery('rows');
        $page = $this->params()->fromQuery('page');
        $sort_field = $this->params()->fromQuery('sidx');
        $sort_order = $this->params()->fromQuery('sord');

        $searchData = array();
        $searchType = array();
        $sort = array();

        $search = $this->params()->fromQuery('_search', 'false');
        if ($search == 'true') {
            $searchField = $this->params()->fromQuery('searchField', false);
            $searchOper = $this->params()->fromQuery('searchOper');
            $searchString = $this->params()->fromQuery('searchString');

            if($searchField){
                $searchData[$searchField] = $searchString;
                $searchType[$searchField] = $searchOper;
            }else{
                foreach($staticAttributes as $attributes=>$label){
                    $sval = $this->params()->fromQuery($attributes, null);
                    if($sval !== null && strlen($sval)){
                        $searchData[$attributes] = $sval;
                        $searchType[$attributes] = 'like';
                    }
                }
                foreach($attributesCodes as $attributes){
                    $sval = $this->params()->fromQuery($attributes, null);
                    if($sval !== null && strlen($sval)){
                        $searchData[$attributes] = $sval;
                        $searchType[$attributes] = 'like';
                    }
                }
            }
        }

        $minimumFilter = $this->getMinimumFilter();
        if($this->params()->fromQuery('minimumFilter', false)){
            $minimumFilter = json_decode($this->params()->fromQuery('minimumFilter'));
            if(is_object($minimumFilter)){
                $minimumFilter = get_object_vars($minimumFilter);
            }
            foreach($minimumFilter as $k=>$v){
                if(is_object($v)){
                    $minimumFilter[$k] = get_object_vars($v);
                }
            }
        }
        $searchData = array_merge($searchData, $minimumFilter['searchData']);
        $searchType = array_merge($searchType, $minimumFilter['searchType']);

        if($sort_field && $sort_order){
            $sort[$sort_field] = strtoupper($sort_order);
        }else{
            $sort = $this->getDefaultSort();
        }

        $totalCount = $entityService->countEntity(
            0,
            $entityType,
            false,
            $searchData,
            $searchType
        );

        if ($totalCount > 0) {
            $rawData = $entityService->locateEntity(
                0,
                $entityType,
                false,
                $searchData,
                $searchType,
                array(
                    'order'=>$sort,
                    'limit'=>$rows,
                    'offset'=>$rows*$page - $rows,
                )
            );
        }else{
            $rawData = array();
        }
        $thisCount = count($rawData);

        $totalPages = ($rows > 0 ? intval($totalCount/$rows) : 0);

        header('Content-Type: text/xml');
        echo <<<EOF
<?xml version='1.0' encoding='utf-8'?>
<rows>
<page>{$page}</page>
<total>{$totalPages}</total>
<records>{$thisCount}</records>
EOF;

        foreach ($rawData as $row) {
            echo "\t<row id='" . $row->getId() . "'>".PHP_EOL;
            echo "\t\t<cell>0</cell>".PHP_EOL; // Massedit selected
            echo "\t\t<cell><![CDATA[" . $this->getActionsHtml($row) . "]]></cell>".PHP_EOL;
            if (isset($staticAttributes['ENTITY_ID'])) {
                echo "\t\t<cell>".$row->getId().'</cell>'.PHP_EOL;
            }
            if (isset($staticAttributes['UNIQUE_ID'])) {
                echo "\t\t<cell>".$row->getUniqueId().'</cell>'.PHP_EOL;
            }
            if (isset($staticAttributes['STORE_ID'])) {
                echo "\t\t<cell>".$row->getStoreId().'</cell>'.PHP_EOL;
            }
            if (isset($staticAttributes['PARENT_ID'])) {
                echo "\t\t<cell>".$row->getParentId().'</cell>'.PHP_EOL;
            }
            if (isset($staticAttributes['UPDATED_AT'])) {
                echo "\t\t<cell><![CDATA[".$row->getUpdatedAt().']]></cell>'.PHP_EOL;
            }

            foreach ($attributesSorted as $attributeId => $attributeData) {
                if($dynamicAttributes != null && !in_array($attributeData['code'], $dynamicAttributes)){
                    continue;
                }
                $display_data = $attributesDisplayData[$attributeData['code']];
                $fetch_data = $attributesFetchData[$attributeData['code']];

                if (isset($display_data['render_func'])) {
                    $data = $row->$display_data['render_func']();
                }elseif (isset($display_data['render_fkey_func'])) {
                    if($attributeData['type'] == 'entity'){
                        $fkey = $row->resolve(
                            $attributeData['code'],
                            (isset($fetch_data['fkey_type']) ? $fetch_data['fkey_type'] : NULL)
                        );
                        if($fkey){
                            $data = $fkey->$display_data['render_fkey_func']();
                        }else{
                            $data = null;
                        }
                    }else{
                        $data = 'INVALID_CONFIG';
                    }
                }elseif (isset($display_data['fkey_render_field'])) {
                    if (!$row->getData($attributeData['code'])) {
                        $data = ' ';
                    }else{
                        if(!isset($fetch_data['fkey_table'])){
                            throw new MagelinkException('Cannot render fkey value, fetchdata missing fkey table entry');
                        }
                        $table = $fetch_data['fkey_table'];
                        if(!isset($fetch_data['fkey_field'])){
                            $fetch_data['fkey_field'] = 'id'; // Default to field called ID
                        }
                        $idfield = $fetch_data['fkey_field'];
                        $renderfield = $display_data['fkey_render_field'];

                        $data = $this->fkeyRenderField(
                            $table,
                            $idfield,
                            $renderfield,
                            $row->getData($attributeData['code'])
                        );
                    }
                }else{
                    $data = $row->getData($attributeData['code']);
                }

                if(is_array($data) && array_key_exists(0, $data) && count($data)){
                    $data = '<ul><li>'.implode('</li><li>', $data).'</li>';
                }else if(is_array($data) && count($data)){
                    $arr = $data;
                    $data = '<ul>';
                    foreach($arr as $k=>$v){
                        $data .= '<li>' . $k . ': ' . $v . '</li>';
                    }
                }

                echo "\t\t<cell><![CDATA[".$data.']]></cell>'.PHP_EOL;
            }
            echo "\t</row>";
        }
        echo <<<EOF
</rows>
EOF;

        die();
    }

    /**
     * Cache for fkeyRenderField()
     * @var array
     */
    protected $_fkeyRenderFieldCache = array();

    /**
     * Renders a foreign-key field using the "fkey" type.
     * @param string $table The table to select from
     * @param string $idfield The primary key (or otherwise referenced) field in the foreign table
     * @param string $renderfield The field to output
     * @param int $fkeyVal The value of the fkey field, to look up on
     * @return string
     */
    protected function fkeyRenderField($table, $idfield, $renderfield, $fkeyVal){
        $cacheKey = $table . ' - ' . $idfield . ' - ' . $renderfield . ' - ' . $fkeyVal;
        if(isset($this->_fkeyRenderFieldCache[$cacheKey])){
            return $this->_fkeyRenderFieldCache[$cacheKey];
        }
        $data = null;
        $sql = 'SELECT ' . $renderfield . ' FROM ' . $table . ' WHERE ' . $idfield . ' = ' . $fkeyVal . ';';
        try{
            $res = $this->getAdapter()->query($sql, \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE);
            if($res && $res->count()){
                $resrow = $res->current();
                $data = $resrow[$renderfield];
                if(!$data){
                    $data = ' ';
                }
            }else{
                $data = ' ';
            }
        }catch(\PDOException $e){
            $data = 'RETRIEVE_ERROR';
        }
        $this->_fkeyRenderFieldCache[$cacheKey] = $data;
        return $data;
    }



    /**
     * Return the database adapter to be used to communicate with Entity storage.
     * @return \Zend\Db\Adapter\Adapter
     */
    protected function getAdapter(){
        return $this->getServiceLocator()->get('zend_db');
    }

    /**
     * Returns a new TableGateway instance for the requested table
     * @param string $table
     * @return \Zend\Db\TableGateway\TableGateway
     */
    protected function getTableGateway($table){
        return new TableGateway($table, \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::getStaticAdapter());
    }
}
