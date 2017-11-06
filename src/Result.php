<?php
namespace SeanKndy\Y1731;

class Result
{
    protected $monitor;
    protected $delayNe, $delayFe;
    protected $framelossNe, $framelossFe; // these are whole numbers that are 0.001 of a percent
    protected $jitterNe, $jitterFe;
    protected $timeouts, $errors;

    public function __construct(Monitor $monitor) {
        $this->monitor = $monitor;
    }

    public function setMonitor(Monitor $monitor) {
        $this->monitor = $monitor;
    }

    public function getMonitor() {
        return $this->monitor;
    }

    public function getDelayNe() {
        return $this->delayNe;
    }

    public function setDelayNe($delayNe){
        $this->delayNe = $delayNe;
    }

    public function getDelayFe() {
        return $this->delayFe;
    }

    public function setDelayFe($delayFe){
        $this->delayFe = $delayFe;
    }

    public function getFramelossNe() {
        return $this->framelossNe;
    }

    public function setFramelossNe($framelossNe){
        $this->framelossNe = $framelossNe;
    }

    public function getFramelossFe() {
        return $this->framelossFe;
    }

    public function setFramelossFe($framelossFe){
        $this->framelossFe = $framelossFe;
    }

    public function getJitterNe() {
        return $this->jitterNe;
    }

    public function setJitterNe($jitterNe){
        $this->jitterNe = $jitterNe;
    }

    public function getJitterFe() {
        return $this->jitterFe;
    }

    public function setJitterFe($jitterFe){
        $this->jitterFe = $jitterFe;
    }

    public function getTimeouts() {
        return $this->timeouts;
    }

    public function setTimeouts($timeouts) {
        $this->timeouts = $timeouts;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function setErrors($errors) {
        $this->errors = $errors;
    }

    // calculate whole seconds of unavailable time based on frameloss
    // must be over half a second to be counted
    // this is hackish because Telco does not report severely errored seconds/unavailable time
    // so unvailable time must be crudely calculated based on the average loss over the period
    // of the interval
    public function calculateUat() {
        return round(
            ($this->getFrameLossNe() * 0.001 * $this->getMonitor()->getInterval()) +
            ($this->getFrameLossFe() * 0.001 * $this->getMonitor()->getInterval())
        );
    }
}
