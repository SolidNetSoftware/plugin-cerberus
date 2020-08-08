<?php

class Sync {

    public function __construct()
    {
        Loader::loadModels($this, array(
            'Cerberus.CerberusOrgs',
            'Cerberus.CerberusSync',
            'Cerberus.CerberusEmails',
            'Cerberus.CerberusMapping',
            'AppModels.Clients',
            'AppModels.Contacts',
            'AppModels.Companies'
        ));

        Language::loadLang('cerberus', null, dirname(__FILE__) . DS . '..' . DS . 'language' . DS);
    }

    private function getPage($completed)
    {
        if($completed == 0) return 1;

        $perPage = Configure::get('Blesta.results_per_page');
        return ceil((($completed-1)/ $perPage)+1);
    }

    public function runSyncJob()
    {
        $job = $this->CerberusSync->getQueuedJob();
        if($job == false) return;

        $completed = $job->completed;
        $query = unserialize($job->query);

        $currentPage = $this->getPage($completed);
        $clients = $this->Clients->getList($query['status'], $currentPage, $query['order_by']);

        if($currentPage === 1) $this->CerberusSync->updateStatus($job->id, 'in_progress');

        $count = 0;
        foreach($clients as $client) {
            $contacts = $this->Contacts->getAll($client->id, null, $query['order_by']);
            $count++;

            foreach($contacts as $contact) {
                $error_message;
                if(!$this->contactSync($contact, $error_message))
                    $this->CerberusSync->appendError($job->id, $error_message);
            }
        }
        $this->CerberusSync->updateCompleted($job->id, $completed + $count);
    }

    public function callBackEvent($event)
    {
        if($event->getName() == 'Contacts.delete') {
            $this->CerberusMapping->delete($event->getParams()['old_contact']->client_id);
            return;
        }

        // Convert PHP Class to array. This is done because
        // the Event Call backup function use to pass StdClass
        // but has changed to pass in arrays. We can either
        // convert array to StdClass or StdClass to array. I
        // flipped a coin and array to StdClass was selected.

        $contact = new stdClass();
        foreach ($event->getParams()['vars'] as $key => $value)
            $contact->$key = $value;


        $error_message;
        $this->contactSync($contact, $error_message);
    }

    public function contactSync($contact, &$error_message)
    {
        // First check to see if we already mapped the user to a cerb org
        $this->CerberusMapping->id = $contact->client_id;
        $this->CerberusMapping->blesta_company_id = Configure::get('Blesta.company_id');
        $cerb_org_id = $this->CerberusMapping->get()->cerb_org_id;

        $company = $this->Companies->get(Configure::get('Blesta.company_id'));
        $base_url = 'https://' . $company->hostname; // TODO FIXME: any better way to figure out hostname?

        // TODO FIXME:  Bug Fix and work around for when Blesta fails to return contact_type
        //              when creating new clients.
        if(!property_exists($contact, 'contact_type'))  $contact->contact_type = 'primary';

        if($contact->contact_type == 'primary') {
            // Perhaps the org no longer exists in Blesta is out of sync
            // Use case: new re/install of Blesta with existing install of Cerb
            if($this->CerberusOrgs->doesOrgExists($contact->client_id, $cerb_org_id))
            {
                $this->CerberusMapping->cerb_org_id = $this->CerberusOrgs->updateOrg($base_url, $contact, $cerb_org_id);
                $this->CerberusMapping->upsert();
            } else {
                $this->CerberusMapping->cerb_org_id = $this->CerberusOrgs->createOrg($base_url, $contact);
                $this->CerberusMapping->upsert();
            }
            $this->CerberusEmails->createEmail($contact->email);
        } else if (!empty($cerb_org_id)) {
            // This is a contact, only primary contacts create and update orgs
            $this->CerberusEmails->createEmail($contact->email);
        } else {
                // unable to get information required to create org
                // something bad has happend since primary contact
                // should have this created
                // TODO FIXME: how offten does this happen? Should we try and
                //             correct this if we encounter it?
                //             We could get the client_id from $contact and call
                //             this function with client record that maps to client_id
                $error_message = Language::_('cerberus.cron.error.primary_contact', true);
                return false;
        }
        return true;
    }
}
