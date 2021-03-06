<?php
namespace SeanKndy\Y1731;

class App extends \SeanKndy\Daemon\Daemon
{
    private $resultHandlers = [];
    private $pollers = [];

    public function __construct($name, $maxChildren = 100, $quietTime = 1000000, $syslog = true) {
        parent::__construct($name, $maxChildren, $quietTime, $syslog);

        if ($db = Database::getInstance()) {
            try {
                // set 'polling' flag to 0 in case some monitor was stuck on 1 when the daemon died
                $usth = $db->prepare('update y1731_monitors set polling = 0 where enabled = 1');
                $usth->execute();
            } catch (\PDOException $e) {
                $this->log(LOG_ERR, "Failed to run query to reset polling flags.");
            }
        }
    }

    public function addResultHandler(ResultHandler $handler) {
        $this->resultHandlers[] = $handler;
    }

    public function registerPoller($type, $class, array $args) {
        $this->pollers[$type] = [
            'class' => $class,
            'args' => $args
        ];
    }

    public function run() {
        $db = Database::getInstance();
        if (!$db) {
            $this->log(LOG_ERR, "Failed to get database PDO instance!");
            \sleep(5);
            return;
        }

        //
        // fetch monitors that need polled
	// 'need polled' meaning not currently polling and hasnt been polled since `interval` OR
	// polling flag is set but its been 2x intervals, meaning it's likely stuck in polling state
        //
        try {
            $sth = $db->prepare('select m.id, m.type, m.attributes, m.interval, m.latency_threshold as latencyThreshold, ' .
                'm.jitter_threshold as jitterThreshold, m.frameloss_threshold as framelossThreshold, m.last_polled as lastPolled, ' .
                'm.added, m.expire_data_days as expireDataDays, devices.ip as deviceIp, devices.snmp_read_community as deviceSnmpCommunity ' .
                'from y1731_monitors as m left join devices on devices.id = m.device_id where enabled = 1 and ' .
	        '(polling = 0 or timestampdiff(second, last_polled, now()) > `interval` * 2) and (m.last_polled is null or ' .
                'timestampdiff(second, m.last_polled, now()) > `interval`) order by m.last_polled asc');
            $sth->execute();

            // set 'polling' flag so we don't re-poll the same monitors before they finish
            $usth = $db->prepare('update y1731_monitors set polling = 1 where (last_polled is null or timestampdiff(second, last_polled, now()) > `interval`) and enabled = 1');
            $usth->execute();
        } catch (\PDOException $e) {
            $this->log(LOG_ERR, "Failed to fetch Y1731 monitors: " . $e->getMessage());
            \sleep(5);
        }
        while ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
            $monitor = Monitor::create($row);

            //
            // get Poller object for this type
            //
            $poller = $this->getPollerForType($monitor->getType());
            if ($poller === null) {
                try {
                    $sth = $db->prepare('update y1731_monitors set enabled = 0, polling = 0 where id = ?');
                    $sth->execute([$monitor->getId()]);
                    $this->log(LOG_INFO, "Y.1731 monitor for IP " . $monitor->getDeviceIp() . " does not have a valid type so has been disabled!");
                } catch (\PDOException $e) {
                    $this->log(LOG_ERR, "Failed to disable Y1731 monitor (ID=" . $monitor->getId() . ") after !");
                }
                continue;
            }


            //
            // queue the actual poll task
            //
            $this->queueTask(function() use ($poller, $monitor) {
                Database::close();

                try {
                    if (($result = $poller->poll($monitor)) instanceof Result) {
                        if (!$result->getMonitor()) {
                            $result->setMonitor($monitor);
                        }
                        $this->handleResult($result);
                    } else {
                        $this->log(LOG_ERR, "Monitor w/ ID " . $monitor->getId() . " returned non-Result object!");
                    }
                } catch (\Exception $e) {
                    $this->log(LOG_ERR, "Failed to poll Y.1731 monitor (ID=" . $monitor->getId() . "): " . $e->getMessage());
                }

                // update last polled time
                $db = Database::getInstance();
                try {
                    $sth = $db->prepare("update y1731_monitors set last_polled = now(), polling = 0 where id = ?");
                    $sth->execute([$monitor->getId()]);
                } catch (\Exception $e) {
                    $this->log(LOG_ERR, "Failed to update last polled time for Y.1731 monitor (ID=" . $monitor->getId() . "): " . $e->getMessage());
                }
            });
        }
    }

    //
    // feeds result to each result handler
    //
    private function handleResult(Result $result) {
        if (!$result) return;

        foreach ($this->resultHandlers as $handler) {
            try {
                $handler->process($result);
            } catch (\Exception $e) {
                $this->log(LOG_ERR, get_class($handler) . " failed to process Y.1731 result data:" . $e->getMessage());
            }
        }
    }

    //
    // fetches a Poller object that is for a given type
    //
    private function getPollerForType($type) {
        if (isset($this->pollers[$type]) && \class_exists($class = $this->pollers[$type]['class'])) {
            return \call_user_func_array(
                array(new \ReflectionClass($class), 'newInstance'),
                $this->pollers[$type]['args']
            );
        }
        return null;
    }
}
