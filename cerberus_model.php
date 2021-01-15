<?php

class CerberusModel extends AppModel {

    const CERB_GRAMMAR_STATUS   = '__status';
    const CERB_GRAMMAR_SUCCESS  = 'success';
    const CERB_RESPONSE_FORMAT  = '.json';
    //const CERB_RESPONSE_FORMAT  = '.xml';

    const CERB_URI_CXT_LIST     = 'records/custom_field/search' . self::CERB_RESPONSE_FORMAT;

    const CERB_URI_EML_SEARCH   = 'records/address/search'      . self::CERB_RESPONSE_FORMAT;
    const CERB_URI_EML_CREATE   = 'records/address/create'      . self::CERB_RESPONSE_FORMAT;

    const CERB_URI_ORG          = 'records/org/%d'              . self::CERB_RESPONSE_FORMAT;
    const CERB_URI_ORG_SEARCH   = 'records/org/search'          . self::CERB_RESPONSE_FORMAT;
    const CERB_URI_ORG_CREATE   = 'records/org/create'          . self::CERB_RESPONSE_FORMAT;

    const CERB_URI_TKT          = 'records/ticket/%d'           . self::CERB_RESPONSE_FORMAT;
    const CERB_URI_TKT_CREATE   = 'parser/parse'                . self::CERB_RESPONSE_FORMAT;
    const CERB_URI_TKT_SEARCH   = 'records/ticket/search'       . self::CERB_RESPONSE_FORMAT;
    const CERB_URI_MSG_SEARCH   = 'records/message/search'      . self::CERB_RESPONSE_FORMAT;
    const CERB_URI_MSG_CREATE   = 'records/message/create'      . self::CERB_RESPONSE_FORMAT;

    const CERB_URI_ATM_CREATE   = 'records/attachment/create'   . self::CERB_RESPONSE_FORMAT;
    const CERB_URI_ATM_SEARCH   = 'records/attachment/search'   . self::CERB_RESPONSE_FORMAT;
    const CERB_URI_ATM_DOWNLOAD = 'attachments/%d/download'     . self::CERB_RESPONSE_FORMAT;

    const CERB_URI_BKT_SEARCH   = 'records/bucket/search'       . self::CERB_RESPONSE_FORMAT;

    protected $cerberusSyncTable        = 'cerberus_sync';
    protected $cerberusConfigTable      = 'cerberus_configuration';
    protected $cerberusMappingTable     = 'cerberus_mapping';
    protected $cerberusDepartmentsTable = 'cerberus_departments';

    protected $table                = null;
    protected $encryptedProperties  = array();
    protected $protectedProperties  = array();

    private $_cerb = null;
    private $_phpMailer = null;

    public function __construct()
    {
        parent::__construct();
        Configure::load('cerberus', dirname(__FILE__) . DS . 'config' . DS);
        Language::loadLang('cerberus', null, dirname(__FILE__) . DS . 'language' . DS);
        Loader::load(dirname(__FILE__) . DS . "vendors" . DS . "cerberus"  . DS . "cerberus.php");
        Loader::load(dirname(__FILE__) . DS . "vendors" . DS . "PHPMailer" . DS . "class.phpmailer.php");
        Loader::loadComponents($this, ['Record']);
    }

    public function getCerb()
    {
        if(empty($this->_cerb)) $this->getCerbConnection();

        return $this->_cerb;
    }

    private function getCerbConnection()
    {
        Loader::loadModels($this, array('Cerberus.CerberusConfig'));
        $server = $this->CerberusConfig->get();
        $this->_cerb = new Cerberus($server->cerberus_url, $server->cerberus_secret_key, $server->cerberus_shared_secret);
    }

    public function getPHPMailer()
    {
        if(empty($this->_phpMailer)) $this->createPHPMailer();

        return $this->_phpMailer;
    }

    private function createPHPMailer()
    {
        $this->_phpMailer = new PHPMailer(true);
    }

    protected function getValue($model)
    {
        $rows = $this->getValues(['id' => 'asc'], $model);
        if (!empty($rows))  $this->assignValues($model, $rows[0], false);
    }

    protected function getValues(array $sort, $model = null)
    {
        $record = $this->Record->select()
            ->from($this->table)
            ->where('blesta_company_id', '=', Configure::get('Blesta.company_id'))
            ->order($sort);
        if($model)
            foreach($model->prepareData() as $property => $value)
                if(isset($value))
                    $record->where($property, '=', $value);

        return $record->fetchAll();
    }

    protected function assignValues($model, $values, $encrypt, $toLower = false)
    {
        if (empty($values))     return;
        if (is_array($values))  $values = (object) $values;

        foreach ($model as $property => $propertyValue) {
            $finalName = $toLower ? strtolower($property) : $property;
            if (array_key_exists($finalName, $values) && !in_array(strtolower($property), $this->protectedProperties))
                if(in_array($property, $model->encryptedProperties))
                    if($encrypt)    $model->$property = $this->systemEncrypt($values->{$finalName});
                    else            $model->$property = $this->systemDecrypt($values->{$finalName});
                else
                    $model->$property = $values->{$finalName};
        }
    }

    protected function save($model, array $data)
    {
        if ($model->id) return $this->update($data); // update
        else            return $this->create($data); // insert
    }

    protected function create(array $data)
    {
        // Can not insert null auto_increment into database
        if(array_key_exists('id', $data) && empty($data['id']))
            unset($data['id']);

        return $this->Record->insert($this->table, $data);
    }

    protected function update(array $data)
    {
        return $this->Record->where('id', '=', $this->id)->update($this->table, $data);
    }

    public function delete($id)
    {
        return $this->Record->from($this->table)->where('id', '=', $id)->delete();
    }

    # TODO FIXME add type to output
    // return output rather then pass by reference
    protected function jsonReader($_json, &$output /* REF */, $ignoreErrors = false)
    {
        $output = json_decode($_json);

        if(!$ignoreErrors && $output->{self::CERB_GRAMMAR_STATUS} != self::CERB_GRAMMAR_SUCCESS) {
            echo "<pre>"; print_r($output); echo "</pre>";
            echo "<pre>"; print_r($this->_cerb->getResponseHeaders()); echo "</pre>";
            throw new CerberusAPIException("Cerberus API returned a bad status code", $output, $this->_cerb->getResponseHeaders());
        }
    }
}

class CerberusAPIException extends Exception {

    private $_json = null;
    private $_headers = array();

    public function __construct($message, $json = null, array $headers = array(), $code = 0, Exception $previous = null)
    {
        $this->_json = $json;
        $this->_headers = $headers;
        parent::__construct($message, $code, $previous);
    }

    public function getJson()
    {
        return $this->_json;
    }

    public function getHttpStatusCode()
    {
        return (isset($this->_headers['http_code'])) ? $this->_headers['http_code'] : 0;
    }

}
