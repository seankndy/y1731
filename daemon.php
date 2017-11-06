<?php
require 'src/include/bootstrap.php';
require 'ResultHandlers/RrdResultHandler.php';
require 'ResultHandlers/DbResultHandler.php';
require 'Pollers/BinosPoller.php';
require 'Pollers/BinoxPoller.php';

$app = new \SeanKndy\Y1731\App('y1731d', 25, 1000000, true);
$app->registerPoller('binox', '\\BinoxPoller', array('/usr/share/snmp/mibs:/www/vcn.com/circuits/telco-binox-mibs'));
$app->registerPoller('binos', '\\BinosPoller', array('/www/vcn.com/circuits/telco-binos-mibs'));
$app->addResultHandler(new RrdResultHandler('/mnt/circuitsmngr/rrd/y1731'));
$app->addResultHandler(new DbResultHandler());
$app->start();
