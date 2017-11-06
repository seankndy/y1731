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
                $getMethod = 'get' . ucifrst($key);

                // check min
                $minVar = $dbPrefix . '_min';
                if ($result->$getMethod() < $row[$minVar]) {
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
                $newAvg = $curAvg + ($result->$getMethod() - $curAvg) / $row['samples'];
                //$newAvg = $row[$dbPrefix . '_avg'] * ($row['samples']-1)/$row['samples'] + $result->$getMethod() / $row['samples'];
                $sql .= "{$dbPrefix}_avg = ?, ";
                $vars[] = $newAvg;
            }

            // calculate unavailable time
            $sql .= "uat = ?, ";
            $vals[] = $row['uat'] + $result->calculateUat();

            $sql .= "samples = samples+1 where id = ?";
            $vars[] = $row['id'];
        } else {
            $sql = "insert into y1731_monitor_data (y1731_monitor_id, delay_ne_avg, delay_ne_min, delay_ne_max, delay_fe_avg, delay_fe_min, delay_fe_max, jitter_ne_avg, jitter_ne_min, jitter_ne_max, jitter_fe_avg, jitter_fe_min, jitter_fe_max, frameloss_ne_avg, frameloss_ne_min, frameloss_ne_max, frameloss_fe_avg, frameloss_fe_min, frameloss_fe_max, uat, date, samples) values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, curdate(), 1)"
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

        // GC?
    }
}