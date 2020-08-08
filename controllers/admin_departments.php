<?php

class AdminDepartments extends CerberusController
{
    private $ignoreFields = array(
        'F', 'I', 'X', 'L', 'W'
    );

    public function preAction()
    {
        parent::preAction();

        $this->requireLogin();
        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->origStructureView);
        $this->view->setView(null, "Cerberus.default");

        // Load required elements
        Loader::loadComponents($this, array('Input'));
        Loader::loadHelpers($this, array('Form', 'Html', 'Widget'));
        Loader::loadModels($this, array(
            'Cerberus.CerberusConfig',
            'Cerberus.CerberusDepartments',
            'Cerberus.CerberusTickets',
            'Cerberus.CerberusGroups'
        ));
    }

    public function index()
    {
        $sort  = (isset($this->get['sort'])  ? $this->get['sort']  : "id");
        $order = (isset($this->get['order']) ? $this->get['order'] : "asc");

        $this->set("sort", $sort);
        $this->set("order", $order);
        $this->set("negate_order", ($order == "asc" ? "desc" : "asc"));

        $this->setVariables(array(
            'base_url' => $this->getURL(self::PLUGIN_BASE_CONFIG),
            'base_url_dept' => $this->getURL(self::PLUGIN_BASE_DEPT),
            'base_url_dept_add' => $this->getURL(self::PLUGIN_BASE_DEPT_ADD),
            'base_url_dept_edit' => $this->getURL(self::PLUGIN_BASE_DEPT_EDIT),
            'base_url_dept_delete' => $this->getURL(self::PLUGIN_BASE_DEPT_DELETE),
            'departments' => $this->CerberusDepartments->getAll(array($sort => $order))
        ));

        return $this->renderAjaxWidgetIfAsync(isset($this->get[0]) || isset($this->get['sort']));
    }

    // might be able to use /records/search .. some how?
    public function add()
    {
        if(!empty($this->post)) {
            $this->CerberusDepartments->load($this->prepareData($this->post), FALSE);
            $this->CerberusDepartments->upsert();

            $this->flashMessage('message', sprintf(Language::_('cerberus.admin.departments.message.created', true), $this->post['name']));
            $this->redirect($this->getURL(self::PLUGIN_BASE_DEPT));
        }

        $this->setVariables(array(
            'base_url' => $this->getURL(self::PLUGIN_BASE_DEPT),
            'base_url_dept' => $this->getURL(self::PLUGIN_BASE_DEPT),
            'ignoreFields' => $this->ignoreFields,
            'groups_buckets' => $this->CerberusGroups->getAllBuckets(),
            'custom_fields' => $this->CerberusTickets->getCustomFields(),
            'savedValues' => null,
            'widgetText' => Language::_('cerberus.admin.departments.button.create', true)
        ));
        $this->render('admin_departments_crud');
    }

    public function edit()
    {
        // Pull information about a department
        if ( !empty($this->get) && empty($this->post) && isset($this->get[0]) ) {
            $this->CerberusDepartments->id = $this->get[0];
            $department = $this->CerberusDepartments->get();
            $this->setVariables(array(
                'base_url' => $this->getURL(self::PLUGIN_BASE_DEPT),
                'base_url_dept' => $this->getURL(self::PLUGIN_BASE_DEPT),
                'ignoreFields' => $this->ignoreFields,
                'groups_buckets' => $this->CerberusGroups->getAllBuckets(),
                'custom_fields' => $this->CerberusTickets->getCustomFields(),
                'savedValues' => $department,
                'widgetText' => Language::_('cerberus.admin.departments.title.edit', true)
            ));
            if(empty($department->name)) {
                $this->flashMessage('error', Language::_('cerberus.admin.config.error.post', true));
                $this->redirect($this->getURL(self::PLUGIN_BASE_DEPT));
            }
            $this->render('admin_departments_crud');

        // Update existing department
        } else if ( !empty($this->get) && !empty($this->post) && isset($this->get[0]) ) {

            $data = $this->prepareData($this->post);
            $data['id'] = $this->get[0];

            $this->CerberusDepartments->load($data, FALSE);
            $this->CerberusDepartments->upsert();

            $this->flashMessage('message', sprintf(Language::_('cerberus.admin.departments.message.edited', true), $this->post['name']));
            $this->redirect($this->getURL(self::PLUGIN_BASE_DEPT));
        } else {
            // return error
            $this->flashMessage('error', Language::_('cerberus.admin.config.error.post', true));
            $this->redirect($this->getURL(self::PLUGIN_BASE_DEPT));
        }
    }

    public function delete()
    {
        if(!empty($this->post) && array_key_exists('id', $this->post)) {
            $this->CerberusDepartments->delete($this->post['id']);
            $this->flashMessage("message", Language::_('cerberus.admin.departments.message.delete', true));
        }

        $this->redirect($this->getURL(self::PLUGIN_BASE_DEPT));
    }

    private function prepareData(array $data)
    {
        $custom_fields = array();
        if(isset($data['customfields']))
            foreach($data['customfields'] as $cfKey => $cfValue)
                // determine if custom field is required or not
                if(isset($data['required_customfield']) && array_key_exists($cfKey, $data['required_customfield']))
                    $custom_fields[$cfKey] = true;
                else
                    $custom_fields[$cfKey] = false;

        return array(
            'name' => $data['name'],
            'description' => $data['description'],
            'group' => $data['group'],
            'bucket' => $data['bucket'],
            'custom_fields' => json_encode($custom_fields),
            'blesta_company_id' => Configure::get('Blesta.company_id')
        );
    }

}
