<?php
namespace SeanKndy\Y1731;

class App extends \SeanKndy\Daemon\Daemon
{
    private $resultHandlers = [];
    private $pollers = [];

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

        //
        // fetch monitors that need polled
        //
        try {
            $sth = $db->prepare('select m.id, m.type, m.attributes, m.interval, m.latency_threshold as latencyThreshold, ' .
                'm.jitter_threshold as jitterThreshold, m.frameloss_threshold as framelossThreshold, m.last_polled as lastPolled, ' .
                'm.added, devices.ip as deviceIp, devices.snmp_read_community as deviceSnmpCommunity from y1731_monitors as m ' .
                'left join devices on devices.id = m.device_id where (m.last_polled is null or ' .
                'timestampdiff(second, m.last_polled, now()) > `interval`) order by m.last_polled asc');
            $sth->execute();
        } catch (\PDOException $e) {
            $this->log(LOG_ERR, "Failed to fetch Y1731 monitors!");
            sleep(5);
        }
        while ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
            $monitor = Monitor::create($row);

            //
            // get Poller object for this type
            //
            $poller = $this->getPollerForType($monitor->getType());
            if ($poller === null) {
                try {
                    $sth = $db->prepare('update y1731_monitors set enabled = 0 where id = ?');
                    $sth->execute();
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
                try {
                    $result = $poller->poll($monitor);
                    if (!$result->getMonitor()) {
                        $result->setMonitor($monitor);
                    }
                    $this->handleResult($result);
                } catch (\Exception $e) {
                    $this->log(LOG_ERR, "Failed to poll Y.1731 monitor (ID=" . $monitor->getId() . "): " . $e->getMessage());
                }
            });
        }
    }

    //
    // feeds result to each result handler
    //
    private function handleResult(Result $result) {
        if (!$result) return;

        // update last polled time
        $db = Database::getInstance();
        try {
            $sth = $db->prepare("update y1731_monitors set last_polled = now() where id = ?");
            $sth->execute([$result->getMonitor()->getId()]);
        } catch (\Exception $e) {
            $this->log(LOG_ERR, "Failed to update last polled time for Y.1731 monitor (ID=" . $monitor->getId() . "): " . $e->getMessage());
        }

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
            return call_user_func_array(
                array(new ReflectionClass($class), 'newInstance'),
                $this->pollers[$type]['args']
            );
        }
        return null;
    }
}
