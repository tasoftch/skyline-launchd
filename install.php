<?php
/**
 * BSD 3-Clause License
 *
 * Copyright (c) 2019, TASoft Applications
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 *  Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

use Skyline\Kernel\Service\PackageInstaller;

$sm = PackageInstaller::getServiceManager();

/** @var \TASoft\Util\PDO $PDO */
$PDO = $sm->get("PDO");

$SQL = [
    'SKY_LAUNCHD_TASK' => [
        'mysql' => "create table SKY_LAUNCHD_TASK (
    id              int auto_increment
        primary key,
    active          int(1)      default 1           not null,
    label           varchar(50)   null,
    schedule        varchar(30) default '* * * * *' not null,
    className       varchar(100)                    not null,
    serviceName     text          null,
    methodName      varchar(100)                    not null,
    options         int         default 1           not null,
    error_reporting int         default 65535       not null
)",
        'sqlite' => "create table SKY_LAUNCHD_TASK
(
    id              integer primary key autoincrement,
    active          int  default 0         null,
    label           varchar(50)   null,
    schedule        tinytext      null,
    className       tinytext      null,
    serviceName     text          null,
    methodName      tinytext      null,
    options         int           null,
    error_reporting int default 0 not null
);"
    ]
];

if($PDO instanceof \TASoft\Util\PDO) {
    $PDO->setAttribute(\TASoft\Util\PDO::ATTR_ERRMODE, \TASoft\Util\PDO::ERRMODE_EXCEPTION);
    $driver = $PDO->getAttribute(\TASoft\Util\PDO::ATTR_DRIVER_NAME);

    try {
        $PDO->prepare("SELECT TRUE FROM SKY_LAUNCHD_TASK LIMIT 1");
    } catch (Exception $exception) {
        if($sql = $SQL["SKY_LAUNCHD_TASK"][ $driver ] ?? NULL) {
            $PDO->exec($sql);
        } else
            trigger_error("Could not create SQL table for driver $driver", E_USER_WARNING);
    }
}
