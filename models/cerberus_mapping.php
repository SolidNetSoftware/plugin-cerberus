<?php

class CerberusMapping extends CerberusModel
{
    public $id                  = null;
    public $cerb_org_id         = null;
    public $blesta_company_id   = null;

    public function __construct()
    {
        parent::__construct();

        $this->table = $this->cerberusMappingTable;
        // change the protected properties to lowercase
        //foreach ($this->protectedProperties as $key => &$value) {
        //    $value = strtolower($value);
        //}
    }

    public function getCerbOrg($client_id)
    {
        $this->id = $client_id;
        $model = $this->get();
        if(empty($model->cerb_org_id))
            return false;

        return $model->cerb_org_id;
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
        $data = $this->prepareData();
        $record = $this->getValues(['id' => 'asc'], $this);

        if(!empty($record))
            $statement = $this->update($data);
        else
            $statement = $this->create($data);

        return $statement;
    }

    protected function prepareData()
    {
        return [
            'id'                => $this->id,
            'cerb_org_id'       => $this->cerb_org_id,
            'blesta_company_id' => $this->blesta_company_id
        ];
    }
}
