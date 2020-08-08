<?php

class CerberusEmails extends CerberusModel {

    public function __construct() {
        parent::__construct();
    }

    public function createEmail($email)
    {
        $email_id = $this->getEmailId($email);
        if($email_id != 0)
            return $email_id;

        $postfields = [
            ['fields[email]', $email]
        ];

        $output = new stdClass();
        $response = $this->getCerb()->post(self::CERB_URI_EML_CREATE, $postfields);
        $this->jsonReader($response, $output);

        return $output->id;
    }

    public function getEmailId($email)
    {
        $query = http_build_query([
            'q' => "email:$email"
        ]);

        $output = array();
        $response = $this->getCerb()->search(self::CERB_URI_EML_SEARCH, $query);
        $this->jsonReader($response, $output);

        if($output->count != 1)
            return 0;

        foreach($output->results as $e)
            return $e->id;
    }

}
