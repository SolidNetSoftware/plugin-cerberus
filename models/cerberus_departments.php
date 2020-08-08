<?php

class CerberusDepartments extends CerberusModel
{
    public $id                  = null;
    public $name                = null;
    public $description         = null;
    public $group               = null;
    public $bucket              = null;
    public $custom_fields       = null;
    public $blesta_company_id   = null;

    public function __construct()
    {
        parent::__construct();

        $this->table = $this->cerberusDepartmentsTable;
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

    public function load(array $data, $encrypt = TRUE)
    {
        $this->assignValues($this, $data, $encrypt);
    }

    public function getAll(array $sort = array())
    {
        return $this->getValues($sort);
    }

    public function upsert()
    {
        return $this->save($this, $this->prepareData());
    }

    protected function prepareData()
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'description'       => $this->description,
            'group'             => $this->group,
            'bucket'            => $this->bucket,
            'custom_fields'     => $this->custom_fields,
            'blesta_company_id' => $this->blesta_company_id
        ];
    }
}
