<?php

require 'Base.php';

// save new toots to db
class SaveToots extends Base {


    private $readOnly = false;


    public function __construct($config) {
        parent::__construct($config);

    }

    public function run($hashtag) {

        if (empty($hashtag)) {
            $this->log("No hashtag provided");
            return;
        }

        $this->log("Fetching toots for hashtag: " . $hashtag);

        $url = $this->config['mastodon']['instance'] . "/api/v1/timelines/tag/" . $hashtag . "?limit=40";
        $json = $this->httpRequest($url);

        if (empty($json)) {
            $this->log("No json returned");
            return;
        }

        $toots = json_decode($json, true);

        $newToots = 0;
        foreach ($toots as $toot) {
            
            // toot exists already?
            $stmt = $this->db->prepare("SELECT * FROM toots WHERE id = ?");
            $stmt->execute([$toot['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $this->log("Toot " . $toot['id'] . " already exists");
                continue;
            }

            // create datetime from $toot['created_at'] which is like "2024-09-25T01:54:43.000Z"
            $created_at = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $toot['created_at']);

            // save toot (id, data (the whole json), created_at)
            $stmt = $this->db->prepare("INSERT INTO toots (id, data, created_at) VALUES (?, ?, ?)");
            $stmt->execute([$toot['id'], json_encode($toot), $created_at->format('Y-m-d H:i:s')]);
            $this->log("Saved toot " . $toot['id'] . " from " . $toot['account']['username']);

            $newToots++;

        }

        // no new toots? check again in some seconds
        if ($newToots == 0) {
            sleep(30);

        // otherwise, check more frequently, depending on number of new toots
        } else if ($newToots < 5) {
            sleep(15);
        } else if ($newToots < 20) {
            sleep(5);    
        } else {
            sleep(3);
        }


    }

}