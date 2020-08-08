<?php

class AdminSync extends CerberusController
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
            'Cerberus.CerberusSync',
            'AppModel.Clients'
        ));
    }

    public function index()
    {
		$this->setVariables(
            array(
                'sync_jobs' => $this->CerberusSync->getAllJobs(),
                'base_url' => $this->getURL(self::PLUGIN_BASE_SYNC),
                'base_url_sync_create' => $this->getURL(self::PLUGIN_BASE_SYNC_CREATE)
            )
        );
    }

    public function cron()
    {
        $sync = new Sync();
        $sync->runSyncJob();
    }

    public function create()
    {
        if(empty($this->post) || !array_key_exists('create-sync', $this->post)) {
            $this->flashMessage('error', Language::_('cerberus.admin.sync.error.body', true));
            $this->redirect($this->getURL(self::PLUGIN_BASE_SYNC));
        }

        // Attempt to create sync job
        if(!$this->CerberusSync->createJob()) {
            $this->flashMessage('error', Language::_('cerberus.admin.sync.error.job', true));
            $this->redirect($this->getURL(self::PLUGIN_BASE_SYNC));
        }

        $this->flashMessage('message', Language::_('cerberus.admin.sync.notice.job', true));
        $this->redirect($this->getURL(self::PLUGIN_BASE_SYNC));
    }

}
