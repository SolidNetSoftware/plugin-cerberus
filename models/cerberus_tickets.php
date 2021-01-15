<?php

class CerberusTickets extends CerberusModel {

    public function __construct()
    {
        parent::__construct();
    }

    public function getTicket($ticket_mask, $org_id)
    {
        if($ticket_mask == false || $org_id === false)
            return array();

        $query = http_build_query([
            'q'         => "mask:$ticket_mask org.id:$org_id",
            'expand'    => 'bucket_,group_,custom_,latest_message_,bucket_replyto_,group_replyto_,initial_message_sender_,requesters,requester_emails,latest_message_headers',
        ]);

        $output = array();
        $response = $this->getCerb()->search(self::CERB_URI_TKT_SEARCH, $query);
        $this->jsonReader($response, $output);

        return $output;
    }

    public function getTicketMessages($ticket_id, $get_content = true, $order = 'desc')
    {
        $expand = 'headers,attachments,sender_contact,worker_address';
        if($get_content == true) $expand .= ',content';

        $orderBy = ""; // ASC
        if($order == 'desc') $orderBy = "-"; // DESC

        $query = http_build_query([
            'q'         => "ticket.id:$ticket_id limit:".PHP_INT_MAX." sort:${orderBy}created",
            'expand'    =>  $expand
        ]);

        $output = array();
        $response = $this->getCerb()->search(self::CERB_URI_MSG_SEARCH, $query);

        // work around and bug in Cerb. API fails on expanding unknown labels
        if(empty($response) && $get_content == true)
            return $this->getTicketMessages($ticket_id, false, $order);
        $this->jsonReader($response, $output);

        return $output;
    }

    public function createTicket($emailTo, $contact, $attachments, $customFields, $department, $org_id, $subject, $message)
    {
        $postfields = [
			['message', $this->createRawTicket($emailTo, $contact->email, $contact->first_name, $contact->last_name, $attachments, $subject, $message)]
        ];

        $output = array();
        $response = $this->getCerb()->post(self::CERB_URI_TKT_CREATE, $postfields);
        $this->jsonReader($response, $output);

		$ticket_id = $output->id;
		$this->updateTicket($ticket_id, $department->group, $department->bucket, $org_id, $customFields);
		return $output->mask;
    }

    public function updateTicket($ticket_id, $group, $bucket, $org_id, array $customFields = array())
	{
        $postfields = [
			['fields[bucket_id]', $bucket],
			['fields[group_id]', $group],
			['fields[org_id]', $org_id],
			['fields[status_id]', 0] // open
        ];
        if(!empty($customFields))
            $postfields = array_merge($postfields, $customFields);

        $output = array();
        $response = $this->getCerb()->put(sprintf(self::CERB_URI_TKT, $ticket_id), $postfields);
        $this->jsonReader($response, $output);
	}

    public function addReply($ticket_id, $in_reply_to, $email_to, $contact, array $attachments, $subject, $message)
    {
        $headers = [];
        foreach(['In-Reply-To', 'References'] as $key)
            $headers[$key] = $in_reply_to;

        $postfields = [
            ['message', $this->createRawTicket($email_to, $contact->email, $contact->first_name,
                $contact->last_name, $attachments, $subject, $message, $headers)]
        ];

        $output = array();
        $response = $this->getCerb()->post(self::CERB_URI_TKT_CREATE, $postfields);
        $this->jsonReader($response, $output);

        $ticket_id = $output->id;
        $this->changeTicketStatus($ticket_id, 'o');
    }

    public function changeTicketStatus($ticket_id, $status = 'c' /* close */)
    {
        $date = $this->Date->toTime(date("c"));
        $postfields = [
            ['fields[status]', $status],
            ['fields[updated]', $date]
        ];
        if($status == 'c')      array_push($postfields, ['fields[closed]', $date]);
        else if($status == 'o') array_push($postfields, ['fields[reopen_date]', $date]);

        $output = new stdClass();
        $response = $this->getCerb()->put(sprintf(self::CERB_URI_TKT, $ticket_id), $postfields);
        $this->jsonReader($response, $output);
    }

    public function getTicketList($org_id, $status, $page, $sort, $order)
    {
        $status_value   = '0'; // 0=open, 1=waiting, 2=closed, 3=deleted
        if      ($status == 'open'   || $status == 'waiting') $status_value = '[o,w]';
        else if ($status == 'closed' || $status == 'deleted') $status_value = '[c,d]';

        $orderBy = ""; // ASC
        if($order == 'desc') $orderBy = "-"; // DESC

        $query = http_build_query([
            'q'         => "org.id:$org_id status:$status_value limit:10 page:$page sort:{$orderBy}{$sort}",
            'expand'    => "bucket_,group_"
        ]);

        $output = new stdClass();
        $response = $this->getCerb()->search(self::CERB_URI_TKT_SEARCH, $query);
        $this->jsonReader($response, $output);
        return $output;
    }

    public function getTicketCount($org_id)
    {
        $status = array( 'waiting' => 0, 'open' => 0, 'closed' => 0 );

        // return an empty ticket count
        if($org_id === false)
            return $status;

        $query = http_build_query([
            'q'             => "org.id:$org_id limit:0 page:1",
            'subtotals[]'   => 'status'
        ]);

        $output = array();
        $response = $this->getCerb()->search(self::CERB_URI_TKT_SEARCH, $query);
        $this->jsonReader($response, $output);

        // We merge waiting for reply with open and deleted
        // as closed
        foreach($output->{'subtotals'}->status as $value) {
            $label = strtolower($value->label);

            if($label == 'waiting for reply')
                $label = 'waiting';
            else if($label == 'deleted')
                $label = 'closed';

            $status[$label] += $value->hits;
        }

        return $status;
    }

    public function getCustomFields(array $fields = array())
    {
        $append = "";
        if(!empty($fields)) {
            $append .= "id:[";
            $i = 0;
            foreach($fields as $key => $value)
            {
                if($i++ != 0) $append .= ", ";
                $append .= "$key";
            }
            $append .= "]";
        }

        $query = http_build_query([
            'q' => "context:cerberusweb.contexts.ticket $append limit:".PHP_INT_MAX
        ]);

        $output = array();
        $response = $this->getCerb()->search(self::CERB_URI_CXT_LIST, $query);
        $this->jsonReader($response, $output);

        return $output;
    }

    public function fillTktCustomFields($client_url, $service_url, &$ret /* REF */)
    {
        foreach($this->getTktCustomFieldNumbers() as $key => $value) {
            if($key == Configure::get('cerberus.tktCustomFieldName.client_url'))
                array_push($ret, array("fields[custom_$value]", $client_url));
            else if($key == Configure::get('cerberus.tktCustomFieldName.service_url'))
                array_push($ret, array("fields[custom_$value]", $service_url));
        }
    }

    public function getTktCustomFieldNumbers()
    {
         $query = http_build_query([
            'q' => 'name:["'.Configure::get('cerberus.tktCustomFieldName.service_url').'",
                          "'.Configure::get('cerberus.tktCustomFieldName.client_url').'"
                         ] context:cerberusweb.contexts.ticket'
        ]);

        $output = new stdClass();
        $response = $this->getCerb()->search(self::CERB_URI_CXT_LIST, $query);
        $this->jsonReader($response, $output);

        $ret = [];
        foreach($output->results as $result)
            $ret[$result->name] = $result->id;

        return $ret;

    }

    private function createRawTicket($emailTo, $email, $firstName, $lastName, array $attachments, $subject, $message, array $headers = array())
    {
        $mailer = $this->getPHPMailer();
        $mailer->setFrom($email, $firstName . ' ' . $lastName);
        $mailer->addAddress($emailTo);
        $mailer->Subject = $subject;
        $mailer->XMailer = 'Blesta Cerb Plugin (https://docs.solidnet.software/p/blesta-cerb/)';
        $mailer->isHTML(false);
        $mailer->Body = $message;
        $mailer->CharSet = 'utf-8';

        foreach($attachments as $attachment)
            $mailer->addAttachment($attachment['tmp_name'], $attachment['name'], 'base64', $attachment['type']);

        foreach($headers as $name => $value)
            if(!empty($value))  $mailer->addCustomHeader($name, $value);

		$mailer->CreateHeader();
		$mailer->CreateBody();
        $mailer->preSend();
        return $mailer->getSentMIMEMessage();
    }

}
