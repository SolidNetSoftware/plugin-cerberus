<?php

class CerberusGroups extends CerberusModel {

    public function __construct()
    {
        parent::__construct();
    }

    public function getAllBuckets()
    {
        $query = http_build_query([
            'expand'    => 'group_',
            'q'         => "limit:".PHP_INT_MAX
        ]);

        $output = array();
        $response = $this->getCerb()->search(self::CERB_URI_BKT_SEARCH, $query);
        $this->jsonReader($response, $output);

        $ret = array();

        foreach ($output->results as $key => $bucket) {

            $var = array(
                'bucketid' => $bucket->{'id'},
                'bucketname' => $bucket->{'name'}
            );
            $bucketList = array();
            if(!empty($ret[$bucket->{'group_id'}]['buckets']))
                $bucketList = $ret[$bucket->{'group_id'}]['buckets'];
            array_push($bucketList, $var);

            $ret[$bucket->{'group_id'}] = array(
                'groupid' => $bucket->{'group_id'},
                'groupname' => $bucket->{'group_name'}
            );
            $ret[$bucket->{'group_id'}]['buckets'] = $bucketList;
        }

        return $ret;
    }

    public function getGroupOrBucketEmail($group)
    {
        $query = http_build_query([
            'expand'    => 'group_replyto_',
            'q'         => "id: $group"
        ]);

        $output = array();
        $response = $this->getCerb()->search(self::CERB_URI_BKT_SEARCH, $query);
        $this->jsonReader($response, $output);
		return $output->results[0]->group_replyto_email;
    }

}
