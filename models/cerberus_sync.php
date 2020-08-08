<?php

class CerberusSync extends CerberusModel {

    public $id                  = null;
    public $date_created        = null;
    public $date_updated        = null;
    public $status              = null;
    public $total               = null;
    public $completed           = null;
    public $query               = null;
    public $errors              = null;
    public $blesta_company_id   = null;

    public function __construct()
    {
        parent::__construct();

        $this->table = $this->cerberusSyncTable;
    }

    public function getJob($job_id)
    {
        return $this->Record->select()->from($this->table)
                ->where('id', '=', $job_id)
                ->fetch();
    }

    public function getAllJobs()
    {
        return $this->getValues(['id' => 'asc']);
    }

    public function getQueuedJob()
    {
        return $this->Record->select()
                ->from($this->table)
                ->where('blesta_company_id', '=', Configure::get('Blesta.company_id'))
                ->where('status', '=', 'pending')
                ->orWhere('status', '=', 'in_progress')
                ->order(['date_created' => 'ASC'])
                ->fetch();
    }

    public function createJob($status = 'active')
    {
        Loader::loadModels($this, ['AppModel.Clients']);
        $filter = ['status' => $status, 'order_by' => ['id' => 'ASC']];
        $totalClients = $this->Clients->getListCount($status);

        $values = [
            'blesta_company_id' => Configure::get('Blesta.company_id'),
            'date_created' => $this->dateToUtc(date('c')),
            'date_updated' => $this->dateToUtc(date('c')),
            'total' => $totalClients,
            'query' => serialize($filter)
        ];

        $this->Record->insert($this->table, $values);
        return true;
    }

    public function updateStatus($job_id, $status)
    {
        return $this->updateColumn($job_id, ['status' => $status]);
    }

    public function updateCompleted($job_id, $completed)
    {
        $this->updateColumn($job_id, ['completed' => $completed, 'date_updated' => $this->dateToUtc(date('c'))]);
        $job = $this->getJob($job_id);
        if($job->total == $completed) {
            if(empty($job->errors)) return $this->updateStatus($job_id, 'completed');
            else                    return $this->updateStatus($job_id, 'completed-errors');
        }
    }

    public function appendError($job_id, $error_message)
    {
        $job = $this->Record->select()->from($this->table)
                ->where('id', '=', $job_id)
                ->fetch();

        $values = [
            'errors' => $job->errors . '<br />' . $error_message
        ];
        return $this->updateColumn($job_id, $values);
    }

    private function updateColumn($id, array $values)
    {
        return $this->Record->where('id', '=', $id)
                ->update($this->table, $values);
    }

    protected function prepareData()
    {
        return [
            'id'                => $this->id,
            'date_created'      => $this->date_created,
            'date_updated'      => $this->date_updated,
            'status'            => $this->status,
            'total'             => $this->total,
            'completed'         => $this->completed,
            'query'             => $this->query,
            'errors'            => $this->errors,
            'blesta_company_id' => $this->blesta_company_id
        ];
    }
}
