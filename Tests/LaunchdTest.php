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

/**
 * LaunchdTest.php
 * skyline-launchd
 *
 * Created on 2019-12-03 17:01 by thomas
 */

use PHPUnit\Framework\TestCase;
use Skyline\Launchd\LaunchdDaemonServer;
use Skyline\Launchd\Task\Runner\FunctionCallRunner;
use Skyline\Launchd\Task\Runner\NullRunner;
use Skyline\Launchd\Task\Task;

class LaunchdTest extends TestCase
{
    /** @var \TASoft\Util\PDO */
    private static $PDO;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$PDO = new \TASoft\Util\PDO("sqlite:Tests/test.sqlite");

        self::$PDO->exec("create table SKY_LAUNCHD_TASK
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
);");

        self::$PDO->exec("create table SKY_LAUNCHD_SCHEDULE
(
    id           integer primary key autoincrement,
    task         int           null,
    nextDate     timestamp     null,
    launchedDate timestamp     null,
    success      int           null,
    output       varchar(4369) null
);");

        self::$PDO->exec("create table SKY_LAUNCHD_TASK_ERROR
(
    id       integer primary key autoincrement,
    schedule int           null,
    level    int           null,
    code     int           null,
    message  varchar(4369) null,
    file     varchar(4369) null,
    line     int           null
);");
    }

    public static function tearDownAfterClass()
    {
        unlink("Tests/test.sqlite");
        parent::tearDownAfterClass();
    }

    public function testRegisterTask1() {
        $lds = new LaunchdDaemonServer(self::$PDO, "now -3seconds", '* * * * *');

        $task = $lds->registerTask("0 0 * * *", new FunctionCallRunner("myFunc"), "Hello", Task::OPTION_RESCHEDULE_TASK_DATE);
        $this->assertInstanceOf(Task::class, $task);

        $this->assertEquals("0 0 * * *", $task->getScheduleString());
        $this->assertEquals(new DateTime("tomorrow 0:00"), $task->getScheduledDate());
        $this->assertEquals("Hello", $task->getLabel());
        $this->assertEquals(Task::OPTION_RESCHEDULE_TASK_DATE, $task->getOptions());
    }

    /**
     * @depends testRegisterTask1
     */
    public function testUpdateTask() {
        $lds = new LaunchdDaemonServer(self::$PDO, "now -3seconds", '* * * * *');

        $lds->updateTask(1, false, "Oh Hello", 12, E_ALL);

        $task = $lds->fetchTask(1);

        $this->assertEquals("0 0 * * *", $task->getScheduleString());
        $this->assertEquals(new DateTime("tomorrow 0:00"), $task->getScheduledDate());
        $this->assertEquals("Oh Hello", $task->getLabel());
        $this->assertEquals(12, $task->getOptions());
        $this->assertEquals(E_ALL, $task->getErrorReporting());

        $this->assertInstanceOf(NullRunner::class, $task->getTaskRunner());
    }

    /**
     * @depends testUpdateTask
     */
    public function testDatabaseStructure() {
        $records = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_TASK"));
        $this->assertEquals([
            [
                "id" => 1,
                "active" => false,
                "label" => 'Oh Hello',
                "schedule" => '0 0 * * *',
                'className' => NULL,
                "serviceName" => NULL,
                'methodName' => 'myFunc',
                "options" => 12,
                'error_reporting' => E_ALL
            ]
        ], $records);

        $records = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_SCHEDULE"));
        $this->assertEquals([
            [
                "id" => 1,
                "task" => 1,
                "nextDate" => (new DateTime("tomorrow 0:00"))->format("Y-m-d G:i:s"),
                "launchedDate" => NULL,
                'success' => NULL,
                "output" => NULL
            ]
        ], $records);

        $records = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_TASK_ERROR"));

        $this->assertEmpty($records);
    }

    public function testUpdateSchedule() {
        $lds = new LaunchdDaemonServer(self::$PDO, "now -3seconds", '* * * * *');

        $lds->updateTaskInterval(1, "0 * * * *");

        $records = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_SCHEDULE"));

        $date = new DateTime("now");
        $minutes = $date->format('i');

        if ($minutes > 0) {
            $date->modify("+1 hour");
            $date->modify('-'.$minutes.' minutes');
        }

        $date->setTime($date->format("G"), 0, 0);
        // Round date to next hour

        $this->assertEquals([
            [
                "id" => 2,      // Scheduled instance 1 was deleted
                "task" => 1,
                "nextDate" => $date->format("Y-m-d G:i:s"),
                "launchedDate" => NULL,
                'success' => NULL,
                "output" => NULL
            ]
        ], $records);
    }
}
