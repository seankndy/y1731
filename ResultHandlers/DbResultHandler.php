<?php
class DbResultHandler implements \SeanKndy\Y1731\ResultHandler
{
    // objectProperty => dbPrefix
    private static $RESULT_ATTRS = ['framelossNe' => 'frameloss_ne', 'framelossFe' => 'frameloss_fe', 'delayNe' => 'delay_ne',
        'delayFe' => 'delay_fe', 'jitterNe' => 'jitter_ne', 'jitterFe' => 'jitter_fe'];

    public function process(SeanKndy\Y1731\Result $result) {
        $db = \SeanKndy\Y1731\Database::getInstance();

        //
        // fetch existing data for today
        //
        try {
            $sth = $db->prepare('select * from y1731_monitor_data where y1731_monitor_id = ? and `date` = curdate()');
            $sth->execute([$result->getMonitor()->getId()]);
        } catch (\PDOException $e) {
            throw new \Exception("Failed to fetch Y1731 monitor data: " . $e->getMessage());
        }
        if ($row = $sth->fetch(\PDO::FETCH_ASSOC)) {
            $vars = [];
            $sql = "update y1731_monitor_data set ";
            foreach (self::$RESULT_ATTRS as $key => $dbPrefix) {
                $getMethod = 'get' . ucfirst($key);

                // check min
                $minVar = $dbPrefix . '_min';
                if ($result->$getMethod() != 0 && $result->$getMethod() < $row[$minVar]) {
                    $sql .= "$minVar = ?, ";
                    $vars[] = $result->$getMethod();
                }

                // check max
                $maxVar = $dbPrefix . '_max';
                if ($result->$getMethod() > $row[$maxVar]) {
                    $sql .= "$maxVar = ?, ";
                    $vars[] = $result->$getMethod();
                }

                // calculate new average
                $curAvg = $row[$dbPrefix . '_avg'];
                $newAvg = $curAvg + ($result->$getMethod() - $curAvg) / ($row['samples']+1);
                //$newAvg = $row[$dbPrefix . '_avg'] * ($row['samples']-1)/$row['samples'] + $result->$getMethod() / $row['samples'];
                $sql .= "{$dbPrefix}_avg = ?, ";
                $vars[] = $newAvg;
            }

            // calculate unavailable time
            $sql .= "uat = ?, ";
            $vars[] = $row['uat'] + $result->calculateUat();

            $sql .= "samples = samples+1 where id = ?";
            $vars[] = $row['id'];
        } else {
            $sql = "insert into y1731_monitor_data (y1731_monitor_id, delay_ne_avg, delay_ne_min, delay_ne_max, delay_fe_avg, delay_fe_min, delay_fe_max, jitter_ne_avg, jitter_ne_min, jitter_ne_max, jitter_fe_avg, jitter_fe_min, jitter_fe_max, frameloss_ne_avg, frameloss_ne_min, frameloss_ne_max, frameloss_fe_avg, frameloss_fe_min, frameloss_fe_max, uat, date, samples) values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, curdate(), 1)";
            $vars = [
                $result->getMonitor()->getId(),
                $result->getDelayNe(),
                $result->getDelayNe(),
                $result->getDelayNe(),
                $result->getDelayFe(),
                $result->getDelayFe(),
                $result->getDelayFe(),
                $result->getJitterNe(),
                $result->getJitterNe(),
                $result->getJitterNe(),
                $result->getJitterFe(),
                $result->getJitterFe(),
                $result->getJitterFe(),
                $result->getFramelossNe(),
                $result->getFramelossNe(),
                $result->getFramelossNe(),
                $result->getFramelossFe(),
                $result->getFramelossFe(),
                $result->getFramelossFe(),
                $result->calculateUat()
            ];
        }
        try {
            $sth = $db->prepare($sql);
            $sth->execute($vars);
        } catch (\PDOException $e) {
            throw new \Exception("Failed to update/insert Y1731 monitor data: " . $e->getMessage());
        }

        // if this result violated any threshold, store it
        $this->storeViolations($result);

        // GC around once every 10 executions
        if ($result->getMonitor()->getExpireDataDays() > 0 && rand(1, 100) % 10 == 0) {
            $sql = "delete from y1731_violations where y1731_monitor_id = ? and timestampdiff(day, violation_datetime, now()) > ?";
            $sth = $db->prepare($sql);
            $sth->execute([$result->getMonitor()->getId(), $result->getMonitor()->getExpireDataDays()]);

            $sql = "delete from y1731_monitor_data where y1731_monitor_id = ? and timestampdiff(day, `date`, now()) > ?";
            $sth = $db->prepare($sql);
            $sth->execute([$result->getMonitor()->getId(), $result->getMonitor()->getExpireDataDays()]);
        }
    }

    protected function storeViolations(SeanKndy\Y1731\Result $result) {
        $db = \SeanKndy\Y1731\Database::getInstance();

        $tests = [
            [$result->getDelayNe()*.01, $result->getMonitor()->getLatencyThreshold(), 'Delay Near End'],
            [$result->getDelayFe()*.01, $result->getMonitor()->getLatencyThreshold(), 'Delay Far End'],
            [$result->getJitterNe()*.01, $result->getMonitor()->getJitterThreshold(), 'Jitter Near End'],
            [$result->getJitterFe()*.01, $result->getMonitor()->getJitterThreshold(), 'Jitter Far End'],
            [$result->getFramelossNe(), $result->getMonitor()->getFramelossThreshold(), 'Frameloss Near End'],
            [$result->getFramelossFe(), $result->getMonitor()->getFramelossThreshold(), 'Frameloss Far End'],
        ];
        foreach ($tests as $test) {
            if ($test[0] > $test[1]) {
                try {
                    $sql = "insert into y1731_violations (y1731_monitor_id, violation_datetime, measurement, threshold, violation) values(?, now(), ?, ?, ?)";
                    $sth = $db->prepare($sql);
                    $sth->execute([$result->getMonitor()->getId(), $test[0], $test[1], $test[2]]);
                } catch (\PDOException $e) {
                    throw new \Exception("Failed to insert Y1731 violation data: " . $e->getMessage());
                }
            }
        }
    }
}
