<?php

class AdminConfig extends CerberusController
{
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
            'Cerberus.CerberusConfig'
        ));
        Language::loadLang('cerberus', null, dirname(__FILE__) . DS . '..' . DS . 'language' . DS);
        Configure::load("cerberus", dirname(__FILE__) . DS . '..' . DS . "config" . DS);
    }

    public function index()
    {
		$this->setVariables(
            array(
                'base_url' => $this->getURL(self::PLUGIN_BASE_CONFIG),
                'base_url_config_create' => $this->getURL(self::PLUGIN_BASE_CONFIG_CREATE),
                'cerberusConfig' => $this->CerberusConfig->get()
            )
        );

        return $this->renderAjaxWidgetIfAsync();
    }

    public function create()
    {
        if(empty($this->post) || !array_key_exists('api-config', $this->post)) {
            $this->flashMessage('error', Language::_('cerberus.admin.config.error.post', true));
            $this->redirect($this->getURL(self::PLUGIN_BASE_CONFIG));
        }

        $this->post['blesta_company_id'] = Configure::get('Blesta.company_id');
        $this->CerberusConfig->load($this->post);
        $this->CerberusConfig->upsert();
        $responseMessage = "";
        if($this->CerberusConfig->isValidConnection($responseMessage))
            $this->flashMessage('message', sprintf(Language::_('cerberus.admin.config.message.save', true), $responseMessage));
        else
            $this->flashMessage('error', sprintf(Language::_('cerberus.admin.config.error.save', true), $responseMessage));

        $this->redirect($this->getURL(self::PLUGIN_BASE_CONFIG));
    }
}
