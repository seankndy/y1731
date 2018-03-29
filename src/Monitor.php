<?php
namespace SeanKndy\Y1731;

class Monitor
{
    protected $id;
    protected $type;
    protected $deviceIp;
    protected $deviceSnmpCommunity;
    protected $attributes;
    protected $interval;
    protected $latencyThreshold;
    protected $jitterThreshold;
    protected $framelossThreshold;
    protected $lastPolled;
    protected $expireDataDays;
    protected $added;
    protected $enabled;

    public static function create(array $fields) {
        $obj = new Monitor();
        foreach ($fields as $k => $v) {
            $setMethod = 'set' . ucfirst($k);
            if (method_exists($obj, $setMethod)) {
                $obj->$setMethod($v);
            }
        }
        return $obj;
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getType() {
        return $this->type;
    }

    public function setType($type) {
        $this->type = $type;
    }

    public function getDeviceIp() {
        return $this->deviceIp;
    }

    public function setDeviceIp($deviceIp) {
        $this->deviceIp = $deviceIp;
    }

    public function getDeviceSnmpCommunity() {
        return $this->deviceSnmpCommunity;
    }

    public function setDeviceSnmpCommunity($deviceSnmpCommunity) {
        $this->deviceSnmpCommunity = $deviceSnmpCommunity;
    }

    public function getAttributes() {
        return $this->attributes;
    }

    public function setAttributes($attributes) {
        if (!is_array($attributes) && $attributes) {
            $attributes = json_decode($attributes);
        }
        $this->attributes = $attributes;
    }

    public function getInterval() {
        return $this->interval;
    }

    public function setInterval($interval) {
        $this->interval = $interval;
    }

    public function getLatencyThreshold() {
        return $this->latencyThreshold;
    }

    public function setLatencyThreshold($latencyThreshold) {
        $this->latencyThreshold = $latencyThreshold;
    }

    public function getJitterThreshold() {
        return $this->jitterThreshold;
    }

    public function setJitterThreshold($jitterThreshold) {
        $this->jitterThreshold = $jitterThreshold;
    }

    public function getFramelossThreshold() {
        return $this->framelossThreshold;
    }

    public function setFramelossThreshold($framelossThreshold) {
        $this->framelossThreshold = $framelossThreshold;
    }

    public function getLastPolled() {
        return $this->lastPolled;
    }

    public function setLastPolled($lastPolled) {
        $this->lastPolled = $lastPolled;
    }

    public function getExpireDataDays() {
        return $this->expireDataDays;
    }

    public function setExpireDataDays($expireDataDays) {
        $this->expireDataDays = $expireDataDays;
    }

    public function getAdded() {
        return $this->added;
    }

    public function setAdded($added) {
        $this->added = $added;
    }

    public function isEnabled() {
        return $this->enabled;
    }

    public function setEnabled($enabled) {
        $this->enabled = $enabled;
    }


}
