<?php

class CerberusPlugin extends Plugin
{
    private $cerberusSyncTable          = 'cerberus_sync';
    private $cerberusConfigTable        = 'cerberus_configuration';
    private $cerberusMappingTable       = 'cerberus_mapping';
    private $cerberusDepartmentsTable   = 'cerberus_departments';

    public function __construct()
    {
        $this->loadConfig(dirname(__FILE__) . DS . "config.json");
        Configure::load('cerberus', dirname(__FILE__) . DS . 'config' . DS);

        Language::loadLang('cerberus', null, dirname(__FILE__) . DS . 'language' . DS);

        Loader::loadComponents($this, array('Input', 'Record'));
        Loader::loadModels($this, array('AppModels.Clients', 'CronTasks'));
        Loader::load(dirname(__FILE__) . DS . 'cron' . DS . 'sync.php');
    }

    public function install($plugin_id)
    {
        if (!isset($this->Record)) {
            Loader::loadComponents($this, ['Record']);
        }

        // cerberus config
        $this->Record
            // primary key
            ->setField('id',                        array('type' => 'int','size' => 10,'unsigned' => true,'auto_increment' => true))
            // fields
            ->setField('cerberus_secret_key',       array('type' => 'varchar', 'size' => 60))
            ->setField('cerberus_shared_secret',    array('type' => 'varchar', 'size' => 60))
            ->setField('cerberus_url',              array('type' => 'varchar', 'size' => 60))
            ->setField('sort_descending',           array('type' => 'int','size' => 1, 'is_null' => true))
            ->setField('attachments_allowed',       array('type' => 'int','size' => 1, 'is_null' => true))
            ->setField('blesta_company_id',         array('type' => 'int','size' => 10, 'unsigned' => true, 'is_null' => false, 'auto_increment' => false))
            ->setKey(array('id'), 'primary')
            ->setKey(array('blesta_company_id'), 'unique')
            ->create($this->cerberusConfigTable, true);

        // departments
        $this->Record
            // primary key
            ->setField('id',                array('type' => 'int','size' => 10,'unsigned' => true,'auto_increment' => true))
            // fields
            ->setField('name',              array('type' => 'varchar', 'size' => 60))
            ->setField('description',       array('type' => 'text'))
            ->setField('group',             array('type' => 'varchar', 'size' => 60))
            ->setField('bucket',            array('type' => 'varchar', 'size' => 60))
            ->setField('custom_fields',     array('type' => 'text'))
            ->setField('blesta_company_id', array('type' => 'int','size' => 10, 'unsigned' => true, 'is_null' => false, 'auto_increment' => false))
            ->setKey(array('id'), 'primary')
            ->setKey(array('blesta_company_id'), 'index')
            ->create($this->cerberusDepartmentsTable, true);

        // mapping between users and cerb org
        $this->Record
            // primary key
            ->setField('id',                array('type' => 'int','size' => 10, 'unsigned' => true, 'is_null' => false, 'auto_increment' => false))
            // fields
            ->setField('cerb_org_id',       array('type' => 'int','size' => 10, 'unsigned' => true, 'is_null' => false, 'auto_increment' => false))
            ->setField('blesta_company_id', array('type' => 'int','size' => 10, 'unsigned' => true, 'is_null' => false, 'auto_increment' => false))
            ->setKey(array('id'), 'primary')
            ->setKey(array('cerb_org_id'), 'unique')
            ->setKey(array('blesta_company_id'), 'index')
            ->create($this->cerberusMappingTable, true);

        // one-time user sync
        $this->Record
            // primary key
            ->setField('id', ['type' => 'int','size' => 10, 'unsigned' => true, 'is_null' => false, 'auto_increment' => true])
            // fields
            ->setField('blesta_company_id', array('type' => 'int', 'size' => 10, 'unsigned' => true, 'is_null' => false, 'auto_increment' => false))
            ->setField('date_created', ['type' => 'datetime'])
            ->setField('date_updated', ['type' => 'datetime'])
            ->setField('status', ['type'=>'enum', 'size' => "'pending', 'in_progress', 'completed', 'completed-errors'", 'default' => 'pending'])
            ->setField('total', ['type'=>'int', 'size' => 10, 'unsigned' => true, 'is_null' => false, 'auto_increment' => false])
            ->setField('completed', ['type'=>'int', 'size' => 10, 'unsigned' => true, 'is_null' => false, 'auto_increment' => false, 'default' => 0])
            ->setField('query', ['type'=>'mediumtext'])
            ->setField('errors', ['type'=>'mediumtext', 'is_null' => true])
            ->setKey(['id'], 'primary')
            ->setKey(['blesta_company_id'], 'index')
            ->create($this->cerberusSyncTable, true);

        $this->installCron();

    }

    public function uninstall($plugin_id, $last_instance)
    {
        foreach(array($this->cerberusConfigTable, $this->cerberusDepartmentsTable, $this->cerberusMappingTable, $this->cerberusSyncTable) as $i) {
            $this->Record->from($i)->where('blesta_company_id', '=', Configure::get('Blesta.company_id'))->delete();
            if($this->Record->from($i)->select()->numResults() == 0)
                $this->Record->drop($i, true);
        }

        $this->deleteCronTask('cerberus_user_sync', $last_instance);
    }

    public function upgrade($current_version, $plugin_id) {
        // Ensure new version is greater than installed version
        if (version_compare($this->getVersion(), $current_version) < 0) {
            $this->Input->setErrors(array(
                'version' => array(
                    'invalid' => "Downgrades are not allowed."
                )
            ));
            return;
        }
    }

    /**
     * Removes the given cron task
     *
     * @param array $task The cron task fields to remove
     * @param bool $last_instance Whether the plugin is being completely uninstalled
     */
    private function deleteCronTask($task, $last_instance = false)
    {
        // Delete the cron task run
        if (($task_run = $this->CronTasks->getTaskRunByKey($task, 'cerberus'))) {
            $this->CronTasks->deleteTaskRun($task_run->task_run_id);
        }

        // Delete the cron task only if this is the last instance
        if ($last_instance &&
            ($cron_task = $this->CronTasks->getByKey($task, 'cerberus'))
        ) {
            $this->CronTasks->delete($cron_task->id, 'cerberus');
        }
    }

    public function installCron()
    {
        $cronTask =
            [
                'key' => 'cerberus_user_sync',
                'task_type' => 'plugin',
                'dir' => 'cerberus',
                'name' => Language::_('cerberus.cron.name', true),
                'description' => Language::_('cerberus.cron.desc', true),
                'type' => 'interval',
                'type_value' => 1,
                'enabled' => 1
            ];

        // Delete any current cron tasks
        if (($cron = $this->CronTasks->getByKey($cronTask['key'], $cronTask['dir'], $cronTask['task_type'])))
            $this->CronTasks->deleteTask($cron->id, $cronTask['task_type'], $cronTask['dir']);

        // Create the cron task
        $cron = $this->CronTasks->add($cronTask);

        if (($errors = $this->CronTasks->errors())) {
            $this->Input->setErrors($errors);
            return false;
        }

        // Create the cron task run
        $task_vars = [
            'enabled' => $cronTask['enabled'],
            $cronTask['type'] => $cronTask['type_value']
        ];

        $this->CronTasks->addTaskRun($cron, $task_vars);

        if (($errors = $this->CronTasks->errors())) {
            $this->Input->setErrors($errors);
            return false;
        }

        return true;
    }

    public function cron($key)
    {
        if($key != 'cerberus_user_sync')
            return;

        $sync = new Sync();
        $sync->runSyncJob();
    }


    public function getPermissionGroups()
    {
        return [
            [
                'name' => Language::_('CerberusPlugin.name', true),
                'level' => 'staff',
                'alias' => 'cerberus.permission.staff'
            ]//,
            //[
            //    'name' => Language::_('cerberus.client.navbar.title', true),
            //    'level' => 'client',
            //    'alias' => 'cerberus.permission.client'
            //]
        ];
    }

    public function getPermissions()
    {
        return [

            // Admin Area
            [
                'group_alias' => 'cerberus.permission.staff',
                'name' => Language::_('cerberus.admin.navbar.config', true),
                'alias' => 'cerberus.admin_config',
                'action' => '*'
            ],
            [
                'group_alias' => 'cerberus.permission.staff',
                'name' => Language::_('cerberus.admin.navbar.departments', true),
                'alias' => 'cerberus.admin_departments',
                'action' => '*'
            ],
            [
                'group_alias' => 'cerberus.permission.staff',
                'name' => Language::_('cerberus.admin.navbar.sync', true),
                'alias' => 'cerberus.admin_sync',
                'action' => '*'
            ]//,

            // Client Area
            //[
            //    'group_alias' => 'cerberus.permission.client',
            //    'name' => Language::_('cerberus.client.navbar.title', true),
            //    'alias' => 'cerberus.tickets',
            //    'action' => '*'
            //]
        ];
    }


    public function getActions()
    {
        return [
            // client area
            [
                'action' => 'nav_primary_client',
                'uri' => 'plugin/cerberus/tickets/index/',
                'name' => Language::_('cerberus.client.navbar.title', true),
                'options' => null
            ],
            [
                'action' => 'widget_client_home',
                'uri' => 'plugin/cerberus/tickets/dashboard/',
                'name' => Language::_('cerberus.client.widget.title', true)
            ],
            // admin area
            [
                'action' => 'nav_primary_staff',
                'uri' => 'plugin/cerberus/admin/index/',
                'name' => Language::_('cerberus.admin.navbar.title', true),
                'options' => [
                    'sub' => [
                        [
                            'uri' => 'plugin/cerberus/admin_config/index/',
                            'name' => Language::_('cerberus.admin.navbar.config', true)
                        ],
                        [
                            'uri' => 'plugin/cerberus/admin_departments/index/',
                            'name' => Language::_('cerberus.admin.navbar.departments', true)
                        ],
                        [
                            'uri' => 'plugin/cerberus/admin_sync/index/',
                            'name' => Language::_('cerberus.admin.navbar.sync', true)
                        ]
                    ]
                ]
            ]
        ];
    }

    public function getEvents() {
        return array(
            array(
               'event' => 'Clients.create',
               'callback' => array('this', 'clientEvents')
            ),
            array(
               'event' => 'Clients.edit',
               'callback' => array('this', 'clientEvents')
            ),
            array(
               'event' => 'Clients.delete',
               'callback' => array('this', 'clientEvents')
            ),
            array(
               'event' => 'Contacts.add',
               'callback' => array('this', 'contactEvents')
            ),
            array(
               'event' => 'Contacts.edit',
               'callback' => array('this', 'contactEvents')
            ),
            array(
               'event' => 'Contacts.delete',
               'callback' => array('this', 'contactEvents')
            )
        );
    }

    public function clientEvents($event)
    {
        // For now do nothing
        return;
    }

    public function contactEvents($event)
    {
        $sync = new Sync();
        $sync->callBackEvent($event);
    }

    /**
     * Returns all cards to be configured for this plugin (invoked after install() or upgrade(),
     * overwrites all existing cards)
     *
     * @return array A numerically indexed array containing:
     *
     *  - level The level this card should be displayed on (client or staff) (optional, default client)
     *  - callback A method defined by the plugin class for calculating the value of the card or fetching a custom html
     *  - callback_type The callback type, 'value' to fetch the card value or
     *      'html' to fetch the custom html code (optional, default value)
     *  - background The background color in hexadecimal or path to the background image for this card (optional)
     *  - background_type The background type, 'color' to set a hexadecimal background or
     *      'image' to set an image background (optional, default color)
     *  - label A string or language key appearing under the value as a label
     *  - link The link to which the card will be pointed (optional)
     *  - enabled Whether this card appears on client profiles by default
     *      (1 to enable, 0 to disable) (optional, default 1)
     */
    public function getCards()
    {
        return [
            [
                'level' => 'client',
                'callback' => ['this', 'getCardTicketCount'],
                'callback_type' => 'value',
                'background' => '#D9EDF7',
                'background_type' => 'color',
                'label' => Language::_('cerberus.client.card.title', true),
                'link' => 'plugin/cerberus/tickets/index/',
                'enabled' => 1
            ]
        ];
    }

    public function getCardTicketCount()
    {
        Loader::loadModels($this, array(
            'Cerberus.CerberusTickets',
            'Cerberus.CerberusMapping',
            'AppModels.Session',
            'AppModels.Clients'
        ));

        $this->client  = $this->Clients->get($this->Session->read('blesta_client_id'), false);

        $org_id = $this->CerberusMapping->getCerbOrg($this->client->id);
        $count = $this->CerberusTickets->getTicketCount($org_id);
        unset($count['closed']);

        $ticketCount = 0;
        foreach($count as $status => $value)
            $ticketCount += $value;

        return $ticketCount;
    }
}
