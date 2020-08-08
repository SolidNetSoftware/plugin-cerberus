<?php

class CerberusAttachments extends CerberusModel {

    public function __construct()
    {
        parent::__construct();
    }

    public function getAttachment($attachment_id)
    {
        $query = http_build_query([
            'q' => "id: $attachment_id"
        ]);

        $output = array();
        $response = $this->getCerb()->search(self::CERB_URI_ATM_SEARCH, $query);
        $this->jsonReader($response, $output);

        return $output;
    }

    public function getAttachmentData($attachment_id)
    {
        return $this->getCerb()->get(sprintf(self::CERB_URI_ATM_DOWNLOAD, $attachment_id));
    }
}
