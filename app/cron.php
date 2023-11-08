<?php

/* --------------------------------------------------------------------

  Chevereto
  https://chevereto.com/

  @author	Rodolfo Berrios A. <http://rodolfoberrios.com/>
            <inbox@rodolfoberrios.com>

  Copyright (C) Rodolfo Berrios A. All rights reserved.

  BY USING THIS SOFTWARE YOU DECLARE TO ACCEPT THE CHEVERETO EULA
  https://chevereto.com/license

  --------------------------------------------------------------------- */

if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
}
$jobs = ['storageDelete', 'deleteExpiredImages', 'cleanUnconfirmedUsers', 'removeDeleteLog', 'tryForUpdates'];
if(G\get_app_setting('htaccess_enforce') !== false) {
    $jobs[] = 'checkHtaccess';
}
shuffle($jobs);
foreach ($jobs as $job) {
    if (!CHV\isSafeToExecute()) {
        echo "⏱ [DONE] Exit - (time is up)\n";
        writeLastRan();
    }
    echo "* Processing $job\n";
    $job();
}
writeLastRan();

function writeLastRan() {
    $datetimegmt = G\datetimegmt();
    CHV\Settings::update(['cron_last_ran' => G\datetimegmt()]);
    echo "--\n✅ [DONE] Cron tasks ran @ $datetimegmt\n";
}

function echoLocked() {
    echo "> Job locked, skipping\n";
}

function storageDelete()
{
    $lock = new CHV\Lock('storage-delete');
    if ($lock->create()) {
        CHV\Queue::process(['type' => 'storage-delete']);
        $lock->destroy();
    } else {
        echoLocked();
    }
}

function deleteExpiredImages()
{
    $lock = new CHV\Lock('delete-expired-images');
    if ($lock->create()) {
        CHV\Image::deleteExpired(50);
        $lock->destroy();
    } else {
        echoLocked();
    }
}

function cleanUnconfirmedUsers()
{
    $lock = new CHV\Lock('clean-unconfirmed-users');
    if ($lock->create()) {
        CHV\User::cleanUnconfirmed(5);
        $lock->destroy();
    } else {
        echoLocked();
    }
}

function removeDeleteLog()
{
    $lock = new CHV\Lock('remove-delete-log');
    if ($lock->create()) {
        $db = CHV\DB::getInstance();
        $db->query('DELETE FROM ' . CHV\DB::getTable('deletions') . ' WHERE deleted_date_gmt <= :time;');
        $db->bind(':time', G\datetime_sub(G\datetimegmt(), 'P3M'));
        $db->exec();
        $lock->destroy();
    } else {
        echoLocked();
    }
}

function tryForUpdates()
{
    if (
        is_null(CHV\Settings::get('update_check_datetimegmt'))
        || G\datetime_add(CHV\Settings::get('update_check_datetimegmt'), 'P1D') < G\datetimegmt()) {
        CHV\L10n::setLocale(CHV\Settings::get('default_language'));
        $lock = new CHV\Lock('check-updates');
        if ($lock->create()) {
            CHV\checkUpdates();
            CHV\updateCheveretoNews();
            $lock->destroy();
        } else {
            echoLocked();
        }
    }
}

function checkHtaccess()
{
    include 'htaccess-enforce.php';
}