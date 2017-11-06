<?php
namespace SeanKndy\Y1731;

interface Poller
{
    /*
     * @return  Result object
     */
    public function poll(Monitor $monitor);
}
