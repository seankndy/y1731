<?php
class BinoxPoller implements \SeanKndy\Y1731\Poller
{
    protected $mibDir;

    public function __construct($mibDir) {
        $this->mibDir = $mibDir;
    }

    public function poll(\SeanKndy\Y1731\Monitor $monitor) {
        $attribs = $monitor->getAttributes();
        // little bit of a hack to get the latest index, works for now
        $oid = "PRVT-SAA-MIB::prvtSaaTestY1731ResultDelayNearEnd.\"{$attribs->saa_name}\".\"{$attribs->saa_owner_name}\"";
        if (!($delay_ne_walk = $this->snmpWalk($monitor->getDeviceIp(), $monitor->getDeviceSnmpCommunity(), $oid))) {
            throw new \Exception("Failed to walk " . $monitor->getDeviceIp() . " w/ OID $oid");
        }
        print_r($delay_ne_walk);
        $index = null;
        $keys = array_keys($delay_ne_walk);
        $key = array_pop($keys);
        if (preg_match('/\.([0-9]+)$/', $key, $m))
            $index = $m[1];
        else
            throw new \Exception("Could not parse snmp output to determine latest index");

        // verify we haven't already pulled this data index
        /*
        if (($last_index = y1731_monitor_get_last_result_index($monitor['id'])) && $last_index == $index) {
            return true;
        }
        */

        $oids = array(
            'delayNe'	  => "PRVT-SAA-MIB::prvtSaaTestY1731ResultDelayNearEnd.\"{$attribs->saa_name}\".\"{$attribs->saa_owner_name}\".$index",
            'delayFe'	  => "PRVT-SAA-MIB::prvtSaaTestY1731ResultDelayFarEnd.\"{$attribs->saa_name}\".\"{$attribs->saa_owner_name}\".$index",
            'jitterNe'    => "PRVT-SAA-MIB::prvtSaaTestY1731ResultJitterNearEnd.\"{$attribs->saa_name}\".\"{$attribs->saa_owner_name}\".$index",
            'jitterFe'    => "PRVT-SAA-MIB::prvtSaaTestY1731ResultJitterFarEnd.\"{$attribs->saa_name}\".\"{$attribs->saa_owner_name}\".$index",
            'framelossNe' => "PRVT-SAA-MIB::prvtSaaTestY1731ResultFrameLossNearEnd.\"{$attribs->saa_name}\".\"{$attribs->saa_owner_name}\".$index",
            'framelossFe' => "PRVT-SAA-MIB::prvtSaaTestY1731ResultFrameLossFarEnd.\"{$attribs->saa_name}\".\"{$attribs->saa_owner_name}\".$index",
            'errors'      => "PRVT-SAA-MIB::prvtSaaTestY1731ResultErrors.\"{$attribs->saa_name}\".\"{$attribs->saa_owner_name}\".$index",
            'timeouts'	  => "PRVT-SAA-MIB::prvtSaaTestY1731ResultTimeouts.\"{$attribs->saa_name}\".\"{$attribs->saa_owner_name}\".$index"
        );
        $result = new \SeanKndy\Y1731\Result($monitor);
        foreach ($oids as $type => $oid) {
            $setMethod = 'set' . ucfirst($type);
            if (!method_exists($result, $setMethod)) continue;

            if (($val = $ths->snmpGet($monitor->getDeviceIp(), $monitor->getDeviceSnmpCommunity(), $oid)) !== false) {
                $result->$setMethod(preg_replace('/^Gauge(32|64):\s*/', '', $val));
            } else {
                $result->$setMethod(-1);
            }
        }

        $result->setDelayNe($result->getDelayNe() * .1);
        $result->setDelayFe($result->getDelayFe() * .1);
        $result->setJitterNe($result->getJitterNe() * .1);
        $result->setJitterFe($result->getJitterFe() * .1);

        return $result;
    }

    private function snmpWalk($ip, $community, $oid) {
        exec("snmpwalk -M '{$this->mibDir}' -v2c '$ip' -c '$community' '$oid' 2>/dev/null", $output);
        $data = [];
        foreach ($output as $line) {
            list($key,$value) = preg_split('/\s*=\s*/', $line);
         	$data[$key] = $value;
        }
        return $data;
    }

    private function snmpGet($ip, $community, $oid)
    {
        exec("snmpget -M '{$this->mibDir}' -v2c '$ip' -c '$community' '$oid' 2>/dev/null", $output);
        foreach ($output as $line) {
            list($key,$value) = preg_split('/\s*=\s*/', $line);
            return $value;
        }
    }
}
