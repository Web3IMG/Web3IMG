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

namespace CHV;

use G;
use Exception;

class Queue
{
    public static $max_execution_time;

    public static function insert($values)
    {
        $values = array_merge([
            'date_gmt'	=> G\datetimegmt(),
            'status'	=> 'pending'
        ], $values);
        DB::insert('queues', $values);
    }

    public static function process($args)
    {
        self::$max_execution_time = ini_get('max_execution_time');
        $queues_db = DB::get(['table' => 'queues', 'join' => 'LEFT JOIN ' . DB::getTable('storages') . ' ON ' . DB::getTable('queues') .'.queue_join = '. DB::getTable('storages') . '.storage_id'], ['type' => $args['type'], 'status' => 'pending'], 'AND', null, 250);
        $queues = [];
        foreach ($queues_db as $k => $v) {
            $queue_item = DB::formatRow($v);
            $queue_item['args'] = json_decode($queue_item['args'], true);
            if (!array_key_exists($queue_item['storage']['id'], $queues)) {
                $queues[$queue_item['storage']['id']] = ['storage' => $queue_item['storage'], 'files' => []];
            }
            $queues[$queue_item['storage']['id']]['files'][] = G\array_filter_array($queue_item, ['id', 'args'], 'exclusion');
        }
        foreach ($queues as $k => $storage_queue) {
            if (!self::canKeepGoing()) {
                break;
            }
            $storage = $storage_queue['storage'];
            $storage_files = $storage_queue['files'];
            $storage['api_type'] = Storage::getApiType($storage['api_id']);

            $files = [];
            $storage_keys = [];
            $deleted_queue_ids = [];
            $disk_space_freed = 0;
            $disk_space_used = 0;

            // Localize the array 'key'
            foreach ($storage_files as $k => $v) {
                $files[$v['args']['key']] = array_merge($v['args'], ['id' => $v['id']]);
                switch ($storage['api_type']) {
                    case 's3':
                    case 's3compatible':
                        $storage_keys[] = ['Key' => $v['args']['key']];
                    break;
                    case 'azure':
                    case 'b2':
                    case 'oss':
                    case 'gcloud':
                    case 'ftp':
                    case 'sftp':
                    case 'local':
                        $storage_keys[] = $v['args']['key'];
                    break;
                    case 'openstack':
                        $storage_keys[] = $storage['bucket'] . '/' . $v['args']['key'];
                    break;
                }
                unset($files[$k]);
                $disk_space_used += $v['args']['size'];
                $deleted_queue_ids[] = $v['id']; // Generate the queue_id stock
            }

            try {
                $StorageAPI = Storage::requireAPI($storage);
            } catch (Exception $e) {
                self::logAttempt($deleted_queue_ids);
                $error = $e;
                break;
            }

            switch ($storage['api_type']) {

                case 's3':
                case 's3compatible':
                    try {
                        $deleteFromStorage = $StorageAPI->deleteObjects([
                            'Bucket'	=> $storage['bucket'],
                            'Delete'	=> [
                                'Objects'	=> $storage_keys
                            ]
                        ]);
                    } catch (Exception $e) {
                        $error = $e;
                        break;
                    }
                    $deleted_queue_ids = []; // Just in case
                    foreach ($deleteFromStorage['Deleted'] ?? [] as $k => $v) {
                        $disk_space_freed += $files[$v['Key']]['size'];
                        $deleted_queue_ids[] = $files[$v['Key']]['id'];
                    }
                break;

                case 'openstack':
                    try {
                        $deleteFromStorage = $StorageAPI->batchDelete($storage_keys);
                    } catch (Exception $e) {
                        $error = $e;
                        break;
                    }
                break;

                case 'oss':
                    try {
                        $deleteFromStorage = $StorageAPI->deleteObjects($storage['bucket'], $storage_keys);
                    } catch (\OSS\Core\OSSException $e) {
                        $error = $e;
                        break;
                    }

                    $deleted_queue_ids = []; // Just in case
                    foreach ($deleteFromStorage as $k => $v) {
                        $deleteKey = G\xml2array($v)[0];
                        $disk_space_freed += $files[$deleteKey]['size'];
                        $deleted_queue_ids[] = $files[$deleteKey]['id'];
                    }
                break;

                // AKA single object APIs (no multiple or batch delete
                case 'azure':
                case 'b2':
                case 'gcloud':
                case 'ftp':
                    foreach ($files as $k => $v) { // No batch operation here
                        if (!self::canKeepGoing()) { // Time safe
                            break;
                        }
                        switch ($storage['api_type']) {
                            case 'azure':
                                $StorageAPI->deleteBlob($storage['bucket'], $v['key']);
                            break;
                            case 'b2':
                                try {
                                    $StorageAPI->deleteFile([
                                        'BucketName' => $storage['bucket'],
                                        'FileName' => $v['key']
                                    ]);
                                } catch(\obregonco\B2\Exceptions\NotFoundException $e) {
                                    // Don't panic if the file has been already removed
                                }
                            break;
                            case 'gcloud':
                                $StorageAPI->objects->delete($storage['bucket'], $v['key']);
                            break;
                            case 'ftp':
                                $StorageAPI->delete($v['key']);
                            break;
                        }
                        $deleted_queue_ids[] = $v['id'];
                        $disk_space_freed += $v['size'];
                    }
                    if ($storage['api_type'] == 'ftp') {
                        $StorageAPI->close(); // Close FTP
                    }
                break;

                case 'sftp':
                    // This thing uses direct rm command (wow, such raw)
                    $StorageAPI->deleteMultiple($storage_keys);
                    $disk_space_freed = $disk_space_used;
                    $StorageAPI->close(); // Close SFTP
                break;

                case 'local':
                    $StorageAPI->deleteMultiple($storage_keys);
                    $deleted_queue_ids = []; // All over again
                    foreach ($StorageAPI->deleted as $k => $v) {
                        $disk_space_freed += $files[$v]['size'];
                        $deleted_queue_ids[] = $files[$v]['id'];
                    }
                break;
            }

            self::logAttempt($deleted_queue_ids);

            if(isset($error) && $error instanceof Exception) {
                throw new QueueException($e->getMessage(), $e->getCode(), $e->getPrevious());
            }
            DB::increment('storages', ['space_used' => '-' . $disk_space_freed], ['id' => $storage['id']]);
            self::delete($deleted_queue_ids);
        }
    }

    public static function delete($ids)
    {
        if ($ids === []) {
            return 0;
        }
        $db = DB::getInstance();
        $db->query('DELETE from ' . DB::getTable('queues') . ' WHERE queue_id IN (' . implode(',', $ids) . ')');
        return $db->exec() ? $db->rowCount() : false;
    }

    public static function logAttempt($ids)
    {
        if ($ids === []) {
            return;
        }
        $db = DB::getInstance();
        $db->query('UPDATE ' . DB::getTable('queues') . ' SET queue_attempts = queue_attempts + 1, queue_status = IF(queue_attempts > 3, "failed", "pending") WHERE queue_id IN (' . implode(',', $ids) . ')');
        $db->exec();
    }

    public static function canKeepGoing()
    {
        return isSafeToExecute(self::$max_execution_time);
    }
}

class QueueException extends Exception
{
}
