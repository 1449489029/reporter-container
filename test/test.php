<?php
$startTime = microtime(true);
include './A.php';
include './B.php';
include './C.php';
include '../src' . DIRECTORY_SEPARATOR . 'Container.php';
include '../src' . DIRECTORY_SEPARATOR . 'ContainerException.php';
include './TestContainer.php';
echo "消耗时长：" . round(microtime(true) - $startTime, 5) . "\r\n";
$startTime = microtime(true);
TestContainer::get('a')->get();
TestContainer::get('b')->get();
TestContainer::get('c')->get();
echo "消耗时长：" . round(microtime(true) - $startTime, 5) . "\r\n";
