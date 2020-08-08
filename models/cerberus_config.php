<?php

class CerberusConfig extends CerberusModel
{
    public $id                      = null;
    public $cerberus_secret_key     = null;
    public $cerberus_shared_secret  = null;
    public $cerberus_url            = null;
    public $sort_descending         = null;
    public $attachments_allowed     = null;
    public $blesta_company_id       = null;

    public function __construct()
    {
        parent::__construct();

        $this->table = $this->cerberusConfigTable;
        $this->encryptedProperties = ['cerberus_secret_key'];
        // change the protected properties to lowercase
        //foreach ($this->protectedProperties as $key => &$value) {
        //    $value = strtolower($value);
        //}
    }

    public function get()
    {
        $this->getValue($this);
        return $this;
    }

    public function load(array $data, $encrypt = true)
    {
        $this->assignValues($this, $data, $encrypt);
    }

    public function upsert()
    {
        return $this->save($this, $this->prepareData());
    }

    protected function prepareData()
    {
        return [
            'id'                        => $this->id,
            'cerberus_secret_key'       => $this->cerberus_secret_key,
            'cerberus_shared_secret'    => $this->cerberus_shared_secret,
            'cerberus_url'              => $this->cerberus_url,
            'sort_descending'           => $this->sort_descending,
            'attachments_allowed'       => $this->attachments_allowed,
            'blesta_company_id'         => $this->blesta_company_id
        ];
    }

    public function isValidConnection(&$responseMessage /* REF */)
    {
        $output;
        $response = $this->getCerb()->get(sprintf(self::CERB_URI_TKT, 0));
        if(empty($response)) {
            $responseMessage = Language::_('cerberus.admin.config.error.generic', true);
            return false;
        }
        $this->jsonReader($response, $output, true);
        if(property_exists($output, "__version")) {
            $responseMessage = $output->__version;
            return true;
        }
        $responseMessage = $output->message;
        return false;
    }
}
