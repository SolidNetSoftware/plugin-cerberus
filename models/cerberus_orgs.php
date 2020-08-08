<?php

class CerberusOrgs extends CerberusModel {

    public function __construct()
    {
        parent::__construct();
    }

    public function doesOrgExists($client_id, &$cerb_org_id /* REF */)
    {
        $cerb_org_id = null;
        $cerb_org_custom_field_name = Configure::get('cerberus.custom.fieldset.name.org') . '.' . Configure::get('cerberus.custom.field.name.org.id');

        $query = http_build_query([
            'q' => "$cerb_org_custom_field_name:$client_id"
        ]);

        $output = new stdClass();
        $response = $this->getCerb()->search(self::CERB_URI_ORG_SEARCH, $query);
        $this->jsonReader($response, $output);

        // TODO FIXME:  maybe in the future take a hash of the record and compare
        //              with the hash of the record in Blesta.
        if($output->count == 1) {
            $cerb_org_id = $output->results[0]->id;
            return true;
        }

        return false;
    }

    public function createOrg($base_url, stdClass $contact)
    {
        $postfields = [
           ['fields[name]', (empty($contact->company)) ? $contact->first_name . " " . $contact->last_name : $contact->company]
        ];

        $output = new stdClass();
        $response = $this->getCerb()->post(self::CERB_URI_ORG_CREATE, $postfields);
        $this->jsonReader($response, $output);

        // Fill in the rest of the fields
        $this->updateOrg($base_url, $contact, $output->id);

        return $output->id;
    }

    public function updateOrg($base_url, stdClass $contact, $cerb_org_id)
    {
        $postfields = [
            ['fields[name]',       (empty($contact->company))   ? $contact->first_name . " " . $contact->last_name : $contact->company],

            ['fields[city]',       (empty($contact->city))      ? ''  : $contact->city],
            ['fields[country]',    (empty($contact->country))   ? ''  : $contact->country],
            ['fields[postal]',     (empty($contact->zip))       ? ''  : $contact->zip],
            ['fields[province]',   (empty($contact->state))     ? ''  : $contact->state],
            ['fields[street]',     (empty($contact->address1))  ? ''  : $contact->address1 . " " . $contact->address2]//,

            // Custom Fields for mapping
            //['fields[custom_id]',  $contact->client_id],
            //['fields[custom_url]', sprintf("%s/admin/clients/view/%d/", $base_url, $contact->client_id)]
        ];
        $this->_fillOrgCustomFields($base_url, $contact, $postfields);

        $output = new stdClass();
        $response = $this->getCerb()->put(sprintf(self::CERB_URI_ORG, $cerb_org_id), $postfields);
        $this->jsonReader($response, $output);

        return $output->id;
    }

    private function _fillOrgCustomFields($base_url, stdClass $contact, &$ret /* REF */)
    {
        foreach($this->_getCerbOrgCustomField() as $key => $value) {
            if($key == Configure::get('cerberus.custom.field.name.org.id'))
                array_push($ret, ["fields[custom_$value]", $contact->client_id]);
            else if($key == Configure::get('cerberus.custom.field.name.org.url'))
                array_push($ret, ["fields[custom_$value]", $base_url . '/admin/clients/view/' . $contact->client_id . '/']);
        }
    }

    private function _getCerbOrgCustomField()
    {
        $query = http_build_query([
           'q' => 'name:["'.Configure::get('cerberus.custom.field.name.org.id').'", "'.Configure::get('cerberus.custom.field.name.org.url').'"] context:cerberusweb.contexts.org fieldset:(name:'.Configure::get('cerberus.custom.fieldset.name.org').')'
        ]);

        $output = new stdClass();
        $response = $this->getCerb()->search(self::CERB_URI_CXT_LIST, $query);
        $this->jsonReader($response, $output);

        $ret = [];
        foreach($output->results as $result)
            $ret[$result->name] = $result->id;

        return $ret;
    }

}
