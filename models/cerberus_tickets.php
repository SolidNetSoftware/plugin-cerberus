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
            'expand'    => 'bucket_,group_,custom_,latest_message_,bucket_replyto_,group_replyto_,initial_message_sender_,requesters,requester_emails',
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
            ['fields[group_id]', $department->group],
            ['fields[bucket_id]', $department->bucket],
            ['fields[status]', 'o'],
            ['fields[subject]', $subject],
            ['fields[org_id]', $org_id],
            ['fields[participants]', $contact->email]
        ];
        foreach($customFields as $field)
            array_push($postfields, $field);


        $output = array();
        $response = $this->getCerb()->post(self::CERB_URI_TKT_CREATE, $postfields);
        $this->jsonReader($response, $output);

		$ticket_id = $output->id;
        $message_id = $this->addTicketMessage($ticket_id, $emailTo, $contact->email, $contact->first_name, $contact->last_name, $subject, $message);
        $this->addMessageAttachments($message_id, $attachments);
		return $output->mask;
    }

    public function addTicketMessage($ticket_id, $emailTo, $emailFrom, $first_name, $last_name, $subject, $message)
    {
        $headers = $this->createEmailHeaders($emailTo, $emailFrom, $first_name, $last_name, $subject);
        $postfields = [
            ['fields[headers]', $headers],
            ['fields[is_outgoing]', '0'],
            ['fields[sender]', $emailFrom],
            ['fields[ticket_id]', $ticket_id],
            ['fields[content]', $message]
        ];
        $output = array();
        $response = $this->getCerb()->post(self::CERB_URI_MSG_CREATE, $postfields);
        $this->jsonReader($response, $output);

        $message_id = $output->id;
        return $message_id;
    }

    public function addMessageAttachments($message_id, $attachments)
    {
        foreach($attachments as $attachment)
        {
            $postfields = [
                ['fields[attach][]', "message:$message_id"],
                ['fields[name]', $attachment['name']],
                ['fields[mime_type]', $attachment['type']],
                ['fields[content]', "data:{$attachment['type']};base64,".base64_encode(file_get_contents($attachment['tmp_name']))]
            ];
            $output = array();
            $response = $this->getCerb()->post(self::CERB_URI_ATM_CREATE, $postfields);
            $this->jsonReader($response, $output);
        }
    }

    public function addReply($ticket_id, $emailTo, $contact, array $attachments, $subject, $message)
    {
        $message_id = $this->addTicketMessage($ticket_id, $emailTo, $contact->email, $contact->first_name, $contact->last_name, $subject, $message);
        $this->changeTicketStatus($ticket_id, 'o');
        $this->addMessageAttachments($message_id, $attachments);
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

    private function createEmailHeaders($emailTo, $emailFrom, $first_name, $last_name, $subject)
    {
        $date = sprintf("Date: %s\r\n", date('D, j M Y H:i:s O'));
        $from = sprintf("From: %s %s <%s>\r\n", $first_name, $last_name, $emailFrom);
        $message_id = sprintf("Message-ID: <%s@%s>\r\n", md5(uniqid(time())), $this->getServerHostname());
        $x_mailer = "X-Mailer: Blesta Cerb Helpdesk\r\n";
        $mine = "MIME-Version: 1.0\r\n";
        $content = "Content-Type: text/plain; charset=utf-8\r\n";
        $to = sprintf("To: %s\r\n", $emailTo);
        $subject = sprintf("Subject: %s\r\n", $subject);

        return $date . $from . $message_id . $x_mailer . $mine . $content . $to . $subject;
    }

    private function getServerHostname()
    {
        if (isset($_SERVER) && array_key_exists('SERVER_NAME', $_SERVER) && !empty($_SERVER['SERVER_NAME']))
            return $_SERVER['SERVER_NAME'];
        elseif (gethostname() !== false)
            return gethostname();

        return 'localhost.localdomain';
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

}
