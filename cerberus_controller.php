<?php

class CerberusController extends AppController
{
    const CLIENT_PLUGIN_BASE            = 'plugin/cerberus/';
    const ADMIN_PLUGIN_BASE             = 'plugin/cerberus/';

    const PLUGIN_BASE_TKT        = self::CLIENT_PLUGIN_BASE . 'tickets/index/';
    const PLUGIN_BASE_TKT_OPEN   = self::CLIENT_PLUGIN_BASE . 'tickets/open/';
    const PLUGIN_BASE_TKT_VIEW   = self::CLIENT_PLUGIN_BASE . 'tickets/view/';
    const PLUGIN_BASE_TKT_REPLY  = self::CLIENT_PLUGIN_BASE . 'tickets/reply/';
    const PLUGIN_BASE_TKT_CLOSE  = self::CLIENT_PLUGIN_BASE . 'tickets/close/';
    const PLUGIN_BASE_TKT_ATCHS  = self::CLIENT_PLUGIN_BASE . 'tickets/attachments/';
    const PLUGIN_BASE_TKT_ERROR  = self::CLIENT_PLUGIN_BASE . 'tickets/error/';

    const PLUGIN_BASE_CONFIG        = self::ADMIN_PLUGIN_BASE . 'admin_config/';
    const PLUGIN_BASE_CONFIG_CREATE = self::ADMIN_PLUGIN_BASE . 'admin_config/create/';
    const PLUGIN_BASE_DEPT          = self::ADMIN_PLUGIN_BASE . 'admin_departments/index/';
    const PLUGIN_BASE_DEPT_ADD      = self::ADMIN_PLUGIN_BASE . 'admin_departments/add/';
    const PLUGIN_BASE_DEPT_EDIT     = self::ADMIN_PLUGIN_BASE . 'admin_departments/edit/%d/';
    const PLUGIN_BASE_DEPT_DELETE   = self::ADMIN_PLUGIN_BASE . 'admin_departments/delete/';
    const PLUGIN_BASE_SYNC          = self::ADMIN_PLUGIN_BASE . 'admin_sync/';
    const PLUGIN_BASE_SYNC_CREATE   = self::ADMIN_PLUGIN_BASE . 'admin_sync/create/';

    public function preAction()
    {
        $this->structure->setDefaultView(APPDIR);
        parent::preAction();
        $this->requireLogin();

        $this->view->view          = "default";
        $this->origStructureView   = $this->structure->view;
        $this->structure->view     = "default";

        $this->view->setView(null, "Cerberus.default");

        // Load required elements
        //Loader::loadComponents($this, array('Input'));
        //Loader::loadHelpers($this, array('Form', 'Html', 'Widget', 'Date'));
        //Loader::loadModels($this, array(
        //    'Cerberus.TicketSettings',
        //    'Cerberus.CerberusServer',
        //    'Cerberus.CerberusOrgs',
        ///    'Cerberus.CerberusEmails',
        //    'AppModels.Clients'
        //));

        Configure::load("cerberus", dirname(__FILE__) . DS . "config" . DS);
        Language::loadLang('cerberus', null, dirname(__FILE__) . DS . 'language' . DS);
        Loader::load(dirname(__FILE__) . DS . 'cron' . DS . 'sync.php');

		$this->Date->setTimezone('UTC', $this->Companies->getSetting($this->company_id, 'timezone')->value);

    }

    protected function getURL($uri)
    {
        return $this->base_uri . $uri;
    }

    /**
     * Set variables for View (like $this->set(), but accept array as param)
     *
     * @param string|array $vName
     * @param mixed|null $vValue
     */
    protected function setVariables($vName, $vValue = '')
    {
        if (is_array($vName)) {
            foreach ($vName as $key => $value) {
                $this->setVariables($key, $value);
            }
        } else {
            $this->set($vName, $vValue);
        }
    }

	protected function getServiceFromURL($uri = '')
	{
		Loader::loadModels($this, array(
            'AppModels.Services'
        ));

		$tmp = explode('/', rtrim($uri, '/'));
		$serviceId = array_pop($tmp);

		return $this->Services->get($serviceId);
	}

    protected function status_to_label($status)
    {
        if     ($status == 'open')      return 'badge-success';
        else if($status == 'waiting')   return 'badge-danger';
        else                            return 'badge-secondary';
    }

    protected function human_filesize($size, $precision = 2)
    {
        static $units = array('Bytes','KiB','MiB','GiB','TiB','PiB','EiB');
        $step = 1024;
        $i = 0;
        while (($size / $step) > 0.9) {
            $size = $size / $step;
            $i++;
        }
        return round($size, $precision).' '.$units[$i];
    }

    /**
     * Converts a past date to x days y mins format
     *
     * @param string $date_time The date time to convert
     * @return string The date converted to time
     */
    protected function timeSince($date_time)
    {
        $time = $this->Date->toTime(date("c")) - $this->Date->toTime($date_time);

        // Only deal with times in the past
        if ($time < 0)
           return "";

        $day = 86400; // seconds in a day
        $hour = 3600; // seconds in an hour

        $days_since = floor($time/$day); // Number of days since
        $hours_since = ($time/$hour)%24; // Number of hours since
        $mins_since = ($time/60)%60; // Number of mins since

        // Set the time language
        $days_since_lang = ($days_since > 0 ? Language::_("cerberus.time_since.day", true, $days_since) : "");
        $hours_since_lang = ($hours_since > 0 ? Language::_("cerberus.time_since.hour", true, $hours_since) : "");
        $time_since = $days_since_lang . " " . $hours_since_lang . " ";

        // Include minutes if no other time unit is available, or if greater
        // than 0
        if (empty($days_since_lang) && empty($hours_since_lang))
            $time_since .= Language::_("cerberus.time_since.minute", true, $mins_since);
        else
            $time_since .= ($mins_since > 0 ? Language::_("cerberus.time_since.minute", true, $mins_since) : "");

        return $time_since;
    }

}
