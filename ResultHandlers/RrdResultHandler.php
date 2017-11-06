<?php
class RrdResultHandler implements \SeanKndy\Y1731\ResultHandler
{
    private $rrdDir;
    private $rrdToolBin;
    private static $RRD_DS = ['framelossNe', 'framelossFe', 'delayNe',
        'delayFe', 'jitterNe', 'jitterFe', 'errors', 'timeouts'];

    public function __construct($rrdDir, $rrdToolBin = '/usr/bin/rrdtool') {
        $this->rrdDir = $rrdDir;
        $this->rrdToolBin = $rrdToolBin;
    }

    public function process(SeanKndy\Y1731\Result $result) {
        $rrdFileTpl = $this->rrdDir . "/" . $result->getMonitor()->getId() . "_%s.rrd";
        foreach (self::$RRD_DS as $ds) {
            $rrdFile = str_replace('%s', $ds, $rrdFileTpl);
            if (!file_exists($rrdFile)) {
                $this->createRrd($rrdFile, $result->getMonitor()->getInterval(), $ds);
            }

            $getMethod = 'get' . ucfirst($ds);
            if (method_exists($result, $getMethod)) {
                $this->updateRrd($rrdFile, $result->$getMethod());
            }
        }
    }

    private function updateRrd($rrdFile, $val) {
        $cmd = $this->rrdToolBin . " update $rrdFile " . time() . ":$val";
        system($cmd, $retval);
    }

    private function createRrd($rrdFile, $interval, $ds) {
        if (file_exists($rrdFile)) {
            throw new Exception("RRD file $rrdFile already exists!");
        }

        $cmd = $this->rrdToolBin . " create $rrdFile --start now-30s --step $interval ";
        $cmd .= "DS:$ds:GAUGE:" . ($interval*2) . ":U:U ";

        // define sample time frames
        $weekly_avg = 1800;  // 30m
        $monthly_avg = 7200; // 2h
        $yearly_avg = 43200; // 12h

        // daily MIN, $interval second avg
        $cmd .= "RRA:MIN:0.5:1:" . (86400 / $interval) . " ";
        // weekly MIN, 30m average
        $cmd .= "RRA:MIN:0.5:" . ($weekly_avg / $interval) . ":" . (86400 * 7 / $interval / ($weekly_avg / $interval)) . " ";
        // monthly MIN, 2h average
        $cmd .= "RRA:MIN:0.5:" . ($monthly_avg / $interval) . ":" . (86400 * 31 / $interval / ($monthly_avg / $interval)) . " ";
        // yearly MIN, 12h average
        $cmd .= "RRA:MIN:0.5:" . ($yearly_avg / $interval) . ":" . (86400 * 366 / $interval / ($yearly_avg / $interval)) . " ";

        // daily AVERAGE, $interval second avg
        $cmd .= "RRA:AVERAGE:0.5:1:" . (86400 / $interval) . " ";
        // weekly AVERAGE, 30m average
        $cmd .= "RRA:AVERAGE:0.5:" . ($weekly_avg / $interval) . ":" . (86400 * 7 / $interval / ($weekly_avg / $interval)) . " ";
        // monthly AVERAGE, 2h average
        $cmd .= "RRA:AVERAGE:0.5:" . ($monthly_avg / $interval) . ":" . (86400 * 31 / $interval / ($monthly_avg / $interval)) . " ";
        // yearly AVERAGE, 12h average
        $cmd .= "RRA:AVERAGE:0.5:" . ($yearly_avg / $interval) . ":" . (86400 * 366 / $interval / ($yearly_avg / $interval)) . " ";

        // daily MAX, $interval second avg
        $cmd .= "RRA:MAX:0.5:1:" . (86400 / $interval) . " ";
        // weekly MAX, 30m average
        $cmd .= "RRA:MAX:0.5:" . ($weekly_avg / $interval) . ":" . (86400 * 7 / $interval / ($weekly_avg / $interval)) . " ";
        // monthly MAX, 2h average
        $cmd .= "RRA:MAX:0.5:" . ($monthly_avg / $interval) . ":" . (86400 * 31 / $interval / ($monthly_avg / $interval)) . " ";
        // yearly MAX, 12h average
        $cmd .= "RRA:MAX:0.5:" . ($yearly_avg / $interval) . ":" . (86400 * 366 / $interval / ($yearly_avg / $interval)) . " ";

        system($cmd, $retval);
    }
}
