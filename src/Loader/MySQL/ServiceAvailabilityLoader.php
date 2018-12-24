<?php
/**
 * Statusengine UI
 * Copyright (C) 2016-2018  Daniel Ziegler

 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Statusengine\Loader\Mysql;

use Statusengine\Backend\Mysql\MySQL;
use Statusengine\Backend\StorageBackend;
use Statusengine\Loader\ServiceAvailabilityLoaderInterface;

class ServiceAvailabilityLoader implements ServiceAvailabilityLoaderInterface {

    /**
     * @var StorageBackend
     */
    private $StorageBackend;

    /**
     * @var MySQL
     */
    private $Backend;

    /**
     * ServiceAvailabilityLoader constructor.
     * @param StorageBackend $StorageBackend
     */
    public function __construct(StorageBackend $StorageBackend) {
        $this->Backend = $StorageBackend->getBackend();
    }

    public function getPlannedDowntime() {
        $clusterNodes = $this->serviceDowntimeQuery([
            'hostname',
            'service_description',
	    'author_name',
	    'comment_data',
	    'scheduled_start_time',
	    'scheduled_end_time',
	    'actual_start_time',
	    'actual_end_time',
	    'internal_downtime_id'
        ]);
        foreach ($clusterNodes as $key => $node) {
            $hostCount = $this->Backend->prepare('SELECT COUNT(*) as counter from statusengine_hoststatus where node_name=?');
            $serviceCount = $this->Backend->prepare('SELECT COUNT(*) as counter from statusengine_servicestatus where node_name=?');
            $hostCount->bindValue(1, $node['node_name']);
            $serviceCount->bindValue(1, $node['node_name']);
            $hostCount = $this->Backend->fetchAll($hostCount);
            $serviceCount = $this->Backend->fetchAll($serviceCount);

            $clusterNodes[$key]['number_of_hosts'] = $hostCount[0]['counter'];
            $clusterNodes[$key]['number_of_services'] = $serviceCount[0]['counter'];
        }

        return $clusterNodes;
    }

    /**
     * @return array
     */
    public function getMaxAndMinCheckTime() {
        return $this->baseQuery(['node_name']);
    }

    /**
     * @param array $fields
     * @return array
     */
    private function serviceDowntimeQuery($fields = []) {
        $query = $this->Backend->prepare(
            sprintf('SELECT %s from statusengine_service_downtimehistory where scheduled_start_time <= %s and scheduled_end_time >= %s and service_description LIKE %s', implode(',', $fields), $start_epoch_time, $end_epoch_time)
        );
        return $this->Backend->fetchAll($query);
    }

}
