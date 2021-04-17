<?php

class Tickets extends CerberusController
{
    /**
     * @var string
     */
    private $defaultSortBy          = 'updated';

    /**
     * @var string
     */
    private $defaultSortOrder       = 'desc';

    /**
     * @var string
     */
    private $defaultTicketStatus    = 'open';

    /**
     * @var array
     */
    private $ignoredFieldTypes = array(
        'F',
        'X',
        'I',
        'L',
        'W',
    );

    /**
     * @var array
     */
    private $allowedFileExtensions = array(
        'jpg', 'jpeg', 'png', 'pdf', 'txt', 'odt', 'odp', 'ods', 'odg'
    );


    /**
    * Setup
    */
    public function preAction()
    {
        parent::preAction();

        $this->requireLogin();

        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->origStructureView);

		Loader::loadHelpers($this, array('Date'));
        Loader::loadComponents($this, array('Input','Record'));
        Loader::loadModels($this, array(
            'Cerberus.CerberusConfig',
            'Cerberus.CerberusDepartments',
            'Cerberus.CerberusTickets',
            'Cerberus.CerberusMapping',
            'Cerberus.CerberusGroups'
        ));

        if (!isset($this->Clients)) $this->uses(['Clients']);
        if (!isset($this->Contacts)) $this->uses(['Contacts']);

        $this->client  = $this->Clients->get($this->Session->read('blesta_client_id'), false);
        $this->contact = $this->Contacts->getByUserId($this->Session->read('blesta_id'), $this->Session->read('blesta_client_id'));
        if(empty($this->contact)) $this->contact  = $this->Clients->get($this->Session->read('blesta_client_id'));

		$this->Date->setTimezone('UTC', $this->Companies->getSetting($this->company_id, 'timezone')->value);
    }

	public function dashboard()
    {
        $org_id = $this->CerberusMapping->getCerbOrg($this->client->id);
        $count = $this->CerberusTickets->getTicketCount($org_id);
        unset($count['closed']);

		$this->set('count', $count);
		$this->set('base_url', $this->getURL(self::PLUGIN_BASE_TKT));
		return $this->renderAjaxWidgetIfAsync();
	}

    public function index()
    {
        $status = (isset($this->get[0])         ? $this->get[0]         : $this->defaultTicketStatus);
        $page   = (isset($this->get[1])         ? (int)$this->get[1]    : 1);
        $sort   = (isset($this->get['sort'])    ? $this->get['sort']    : $this->defaultSortBy);
        $order  = (isset($this->get['order'])   ? $this->get['order']   : $this->defaultSortOrder);

        $this->set('status', $status);
        $this->set('sort', $sort);
        $this->set('order', $order);
        $this->set('negate_order', ($order == 'asc' ? 'desc' : 'asc'));

        // get org id for client
        $org_id     = $this->CerberusMapping->getCerbOrg($this->client->id);
        if($org_id === false)
        {
            $this->flashMessage('error', Language::_('cerberus.client.ticket.message.no-org', true));
            $this->redirect($this->getURL(self::PLUGIN_BASE_TKT_ERROR));
            return;
        }

        // get all ticket count
        $ticket_count = $this->CerberusTickets->getTicketCount($org_id);
        $ticket_count['open'] += $ticket_count['waiting'];

        $ticket_count_pagination                            = (int) $ticket_count['open'];
        if($status == 'closed') $ticket_count_pagination    = (int) $ticket_count['closed'];

        $this->set('ticket_count', $ticket_count);

        // get tickets for this specific page and query
        $tickets = $this->CerberusTickets->getTicketList($org_id, $status, $page, $sort, $order, Configure::get('Blesta.pagination_client')['results_per_page']);
        foreach($tickets->results as $ticket) {
            $ticket->_updatedHuman = $this->timeSince($ticket->updated);
        }
        $this->set('tickets', $tickets);

        $paginationConfig = array_merge(
            Configure::get('Blesta.pagination_client'),
            [
                'total_results' => $ticket_count_pagination,
                'uri' => $this->getURL(self::PLUGIN_BASE_TKT) . $status . '/[p]/',
                'params' => ['sort' => $sort, 'order' => $order]
            ]
        );
        $this->setPagination($this->get, $paginationConfig);

        $colors = array(
            'open'    => Configure::get('cerberus.ticket.color.open'),
            'waiting' => Configure::get('cerberus.ticket.color.waiting'),
            'closed'  => Configure::get('cerberus.ticket.color.closed'),
            'deleted' => Configure::get('cerberus.ticket.color.deleted')
        );
        $this->set('colors', $colors);
		$this->set('base_url_tkt', $this->getURL(self::PLUGIN_BASE_TKT));
		$this->set('base_url_tkt_open', $this->getURL(self::PLUGIN_BASE_TKT_OPEN));
		$this->set('base_url_tkt_view', $this->getURL(self::PLUGIN_BASE_TKT_VIEW));

		return $this->renderAjaxWidgetIfAsync( isset($this->get[1]) || isset($this->get['sort']) );
    }


    // Grab a list of custom fields we setup in admin panel. Then get
    // those from Cerb
    private function getConfiguredFields($department_id)
    {
        $this->CerberusDepartments->id = $department_id;
        $configured_fields = json_decode($this->CerberusDepartments->get()->custom_fields, true);
        $cerb_fields = $this->CerberusTickets->getCustomFields($configured_fields);

        if(!empty($configured_fields)) {
            foreach($cerb_fields->results as $result) {
                $result->required = $configured_fields[$result->id];
            }
        }

        $view = $this->partial("cfajax", array(
            'fields' => (empty($configured_fields)) ? [] : $cerb_fields->results,
            'ignoredFieldTypes' => $this->ignoredFieldTypes
        ));


        return $view;
    }

    public function open()
    {
        $department_id  = (isset($this->get[0])) ? $this->get[0] : false;

        // Default case: Show a list of departments to select
        if($department_id === false && empty($this->post))
        {
            $this->setVariables(array(
                'departments'       => $this->CerberusDepartments->getAll(),
                'base_url_tkt_open' => $this->getURL(self::PLUGIN_BASE_TKT_OPEN)
            ));
            return;
        }

        // Department selected and show the form
        if (empty($this->post))
        {
            Loader::loadModels($this, array(
                'App.Services'
            ));

            $this->setVariables(array(
                'departments'       => null,
                'department_id'     => $department_id,
                'contact'           => $this->contact,
                'services'          => $this->Services->getAllByClient($this->client->id, 'all'),
                'cf_to_form'        => $this->getConfiguredFields($department_id),
                'allow_attachments' => $this->CerberusConfig->get()->attachments_allowed,
                'allowedFileExtensions' => $this->allowedFileExtensions,
                'base_url_tkt_open' => $this->getURL(self::PLUGIN_BASE_TKT_OPEN)
            ));
            return;
        }

        // Create ticket
        $attachments  = $this->checkAndValidateAttachments($this->files);
        $customFields = $this->extractCustomFields($this->post);
        $this->appendRequiredCustomFields($customFields, $this->post);

        // get org id for client
        $org_id = $this->CerberusMapping->getCerbOrg($this->client->id);
        if($org_id === false)
        {
            $this->flashMessage('error', Language::_('cerberus.client.ticket.message.no-org', true));
            $this->redirect($this->getURL(self::PLUGIN_BASE_TKT_ERROR));
            return;
        }

        $this->CerberusDepartments->id = $department_id;
        $department = $this->CerberusDepartments->get();
		$emailTo = $this->CerberusGroups->getGroupOrBucketEmail($department->group);

        $ticket_mask = $this->CerberusTickets->createTicket(
                            $emailTo,
                            $this->contact,
                            $attachments,
                            $customFields,
                            $department,
                            $org_id,
                            $this->post['subject'],
                            $this->post['message']
                       );

        $this->flashMessage('message', Language::_('cerberus.client.ticket.message.created', true));
        $this->redirect($this->getURL(self::PLUGIN_BASE_TKT_VIEW . $ticket_mask . '/'));
    }

    private function appendRequiredCustomFields(&$customFields, $data)
    {
        $service_url =  $this->base_url . 'admin/clients/editservice/'  . $this->contact->id . '/' . $data['service_id'] . '/';
        $client_url  =  $this->base_url . 'admin/clients/editcontact/'  . $this->contact->id . '/' . $this->contact->contact_id . '/';

        $this->CerberusTickets->fillTktCustomFields($client_url, $service_url, $customFields);
    }

    private function extractCustomFields(array $input)
    {
        $fields = array();
        if(!isset($input) || !is_array($input))
            return $fields;

        foreach($input as $key => $value) {
            // have a match for custom fields
            if(strpos($key, 'custom_') !== false && !empty($value))
                $fields[] = array("fields[".$key."]", $value);
        }

        return $fields;
    }

    # TODO FIXME:   maybe support validating attachment types via mime headers
    #               text/plain, application/pdf, application/vnd.oasis.opendocument.text, etc.
    private function checkAndValidateAttachments(array $attachments = array())
    {
        $validAttachments = array();
        if(!isset($attachments) || !is_array($attachments))
            return $validAttachments;

        foreach($attachments['attachments']['error'] as $key => $value) {
            if($value != 0) // have errors
                continue;

            $validAttachments[] = array(
                'name'      => $attachments['attachments']['name'][$key],
                'type'      => $attachments['attachments']['type'][$key],
                'tmp_name'  => $attachments['attachments']['tmp_name'][$key],
                'error'     => $attachments['attachments']['error'][$key],
                'size'      => $attachments['attachments']['size'][$key]
            );
        }

        return $validAttachments;
    }

    public function view()
    {
        $ticket_mask = (isset($this->get[0])) ? $this->get[0] : false;
        $org_id = $this->CerberusMapping->getCerbOrg($this->client->id);
        $ticket = $this->CerberusTickets->getTicket($ticket_mask, $org_id);

        // Verify the department exists for the specific blesta company
        if($ticket_mask == false || $ticket->count == 0)
        {
            $this->flashMessage('error', sprintf(Language::_('cerberus.client.ticket.message.404', true), $ticket_mask));
            $this->redirect($this->getURL(self::PLUGIN_BASE_TKT));
            return;
        }
        if($org_id === false)
        {
            $this->flashMessage('error', Language::_('cerberus.client.ticket.message.no-org', true));
            $this->redirect($this->getURL(self::PLUGIN_BASE_TKT_ERROR));
            return;
        }

        $order_by = ($this->CerberusConfig->get()->sort_descending) ? 'desc' : 'asc';
        $messages = $this->CerberusTickets->getTicketMessages($ticket->results[0]->id, true, $order_by);
        foreach($messages->results as $key => $value)
            if(!empty($value->attachments))
                foreach($value->attachments as $attachment)
                    $attachment->file_size_human = $this->human_filesize($attachment->file_size);

        $ticket->results[0]->status_label = $this->status_to_label($ticket->results[0]->status);
        $fields = $this->CerberusTickets->getCustomFields();

        $this->CerberusDepartments->group = $ticket->results[0]->group_id;
        $this->CerberusDepartments->bucket = $ticket->results[0]->bucket_id;
        $show_fields = json_decode($this->CerberusDepartments->get()->custom_fields, true);

        foreach($fields->results as $field)
        {
            if( isset($show_fields[$field->id]) && /*$show_fields[$field->id] &&*/
                property_exists($ticket->results[0], 'custom_'.$field->id) && !empty($ticket->results[0]->{'custom_'.$field->id})
              )
                $ticket->results[0]->fields[] = [
                    'id'    => $field->id,
                    'label' => 'custom_'.$field->id,
                    'name'  => $field->name,
                    'type'  => $field->type,
                    'value' => $ticket->results[0]->{'custom_'.$field->id}
                ];
        }

		$hiddenServiceFields = $this->CerberusTickets->getTktCustomFieldNumbers();
		$customService = 0;
		$ticketService = new stdClass();
		$customService = $hiddenServiceFields[Configure::get('cerberus.tktCustomFieldName.service_url')];

		if($customService != 0)
			$ticketService = $this->getServiceFromURL($ticket->results[0]->{'custom_'.$customService});


        $this->setVariables(array(
            'ticket'                => $ticket->results[0],
            'messages'              => $messages->results,
            'service'               => $ticketService,

            'base_url_tkt_reply'    => $this->getURL(self::PLUGIN_BASE_TKT_REPLY),
            'base_url_tkt_atchs'    => $this->getURL(self::PLUGIN_BASE_TKT_ATCHS),

            'fields'                => $fields
        ));
    }

    public function attachments()
    {
        Loader::loadModels($this, array(
            'Cerberus.CerberusAttachments'
        ));

        // get org id for client
        $org_id = $this->CerberusMapping->getCerbOrg($this->client->id);
        if($org_id === false)
        {
            $this->flashMessage('error', Language::_('cerberus.client.ticket.message.no-org', true));
            $this->redirect($this->getURL(self::PLUGIN_BASE_TKT_ERROR));
            return;
        }


        $ticket_mask    = (isset($this->get[0])) ? $this->get[0] : 0;
        $attachment_id  = (isset($this->get[1])) ? $this->get[1] : 0;
        $ticket = $this->CerberusTickets->getTicket($ticket_mask, $org_id);
        $attachment = $this->CerberusAttachments->getAttachment($attachment_id);


        if($ticket_mask == false || $attachment_id == false || $ticket->count == 0 || $attachment->count == 0) {
            $this->flashMessage('error', sprintf(Language::_('cerberus.client.ticket.message.attachment.404', true), $ticket_mask));
            $this->redirect($this->getURL(self::PLUGIN_BASE_TKT));
            return;
        }

        $messages = $this->CerberusTickets->getTicketMessages($ticket->results[0]->id);

        foreach($messages->results as $key => $reply) {
            if(empty($reply->attachments) || !property_exists($reply->attachments, $attachment_id))
                continue;

            $this->components(['Download']);
            $this->Download->setContentType($attachment->results[0]->mime_type);
            $this->Download->downloadData($attachment->results[0]->name, $this->CerberusAttachments->getAttachmentData($attachment_id));

            return false;
        }

        $this->flashMessage('error', Lsprintf(Language::_('cerberus.client.ticket.message.attachment.404', true), $ticket_mask));
        $this->redirect($this->getURL(self::PLUGIN_BASE_TKT));
    }

    public function reply()
    {
        // get org id for client
        $org_id = $this->CerberusMapping->getCerbOrg($this->client->id);
        if($org_id === false)
        {
            $this->flashMessage('error', Language::_('cerberus.client.ticket.message.no-org', true));
            $this->redirect($this->getURL(self::PLUGIN_BASE_TKT_ERROR));
            return;
        }

        $ticket_mask = (isset($this->get[0])) ? $this->get[0] : 0;
        $ticket = $this->CerberusTickets->getTicket($ticket_mask, $org_id);

        if( $ticket_mask == false || $ticket->count == 0 || empty($this->post) || !isset($this->post['action'])
            || ($this->post['action'] != 'reply' && $this->post['action'] != 'close')
          )
        {
            $this->flashMessage('error', sprintf(Language::_('cerberus.client.ticket.message.404', true), $ticket_mask));
            $this->redirect($this->getURL(self::PLUGIN_BASE_TKT));
            return;
        }

        $ticket_id = $ticket->results[0]->id;

        if($this->post['action'] == 'close')
        {
            $this->CerberusTickets->changeTicketStatus($ticket_id, 'c');
            $this->flashMessage('notice', sprintf(Language::_('cerberus.client.tickets.message.closed', true), $ticket_mask));
        } else if ($this->post['action'] == 'reply') {

            $ticket_subject = sprintf('Re: [#%s] %s', $ticket_mask, $ticket->results[0]->subject);
            if(property_exists($ticket->results[0], 'bucket_replyto_email'))
                $reply_to = $ticket->results[0]->bucket_replyto_email;
            else
                $reply_to = $ticket->results[0]->group_replyto_email;

            $message_id = $ticket->results[0]->latest_message_id;
            $in_reply_to = $ticket->results[0]->latest_message_headers->{"message-id"};

            $attachments = $this->checkAndValidateAttachments($this->files);
            $this->CerberusTickets->addReply($ticket_id, $in_reply_to, $reply_to, $this->contact, $attachments, $ticket_subject, $this->post['message']);

            $this->flashMessage('notice', Language::_('cerberus.client.ticket.message.reply-added', true));
        }

        $this->redirect($this->getURL(self::PLUGIN_BASE_TKT_VIEW . $ticket_mask));
    }

    public function error()
    {

    }
}
