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
 * LaunchdRunnerTest.php
 * skyline-launchd
 *
 * Created on 2020-01-04 01:38 by thomas
 */

use PHPUnit\Framework\TestCase;
use Skyline\Launchd\LaunchdDaemonServer;
use Skyline\Launchd\Task\Runner\FunctionCallRunner;
use Skyline\Launchd\Task\Task;

class LaunchdRunnerTest extends TestCase
{
    /** @var \TASoft\Util\PDO */
    private static $PDO;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$PDO = new \TASoft\Util\PDO("sqlite:Tests/runner.sqlite");

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
    error_reporting int default 65535 not null
);");

        self::$PDO->exec("create table SKY_LAUNCHD_SCHEDULE
(
    id           integer primary key autoincrement,
    task         int           not null,
    nextDate     timestamp     not null,
    launchedDate timestamp     null,
    success      int           null,
    output       varchar(4369) null
);");

        self::$PDO->exec("create table SKY_LAUNCHD_TASK_ERROR
(
    id       integer primary key autoincrement,
    schedule int           not null,
    level    int           not null,
    code     int           not null,
    message  varchar(4369) null,
    file     varchar(4369) null,
    line     int           null
);");
    }

    public static function tearDownAfterClass()
    {
        unlink("Tests/runner.sqlite");
        parent::tearDownAfterClass();
    }

    public function testSuccessfulRunner() {
        $lds = new LaunchdDaemonServer(self::$PDO, "now -3seconds", '* * * * *');

        $lds->registerTask("* * * * *", new FunctionCallRunner('my_test_function'), 'Test', 0);

        $lds->run(0, ['--run-all']);

        $schedules = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_SCHEDULE"));
        $this->assertCount(1, $schedules);


        $scheduled = $schedules[0];

        $this->assertEquals(1, $scheduled["id"]);
        $this->assertEquals(1, $scheduled["task"]);
        $this->assertEquals(1, $scheduled["success"]);
        $this->assertEquals("Output", $scheduled["output"]);

        $schedules = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_TASK_ERROR"));
        $this->assertCount(0, $schedules);

        $lds->deleteTask(1);
        $schedules = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_SCHEDULE"));
        $this->assertCount(0, $schedules);
        $schedules = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_TASK"));
        $this->assertCount(0, $schedules);
        $schedules = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_TASK_ERROR"));
        $this->assertCount(0, $schedules);
    }

    public function testSuccessfulRunnerWithErrorReporting() {
        $lds = new LaunchdDaemonServer(self::$PDO, "now -3seconds", '* * * * *');

        $tk = $lds->registerTask("* * * * *", new FunctionCallRunner('my_test_function'), 'Test', Task::OPTION_REPORT_ERRORS);
        $lds->updateTask($tk->getTaskID(), true, NULL, NULL, E_ALL);

        $lds->clearCache();

        $lds->run(0, ['--run-all']);

        $schedules = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_SCHEDULE"));
        $this->assertCount(1, $schedules);

        $schedules = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_TASK_ERROR"));
        $this->assertCount(2, $schedules);

        $lds->deleteTask($tk->getTaskID());

        $schedules = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_TASK_ERROR"));
        $this->assertCount(0, $schedules);
    }

    public function testRescheduleFromTask() {
        $lds = new LaunchdDaemonServer(self::$PDO, "now -3seconds", '* * * * *');

        $tk = $lds->registerTask("* * * * *", new FunctionCallRunner('my_test_function'), 'Test', Task::OPTION_RESCHEDULE_TASK_DATE);
        $lds->run(0, ['--run-all']);

        $schedules = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_SCHEDULE"));
        $this->assertCount(2, $schedules);

        $launched = array_shift($schedules);
        $next = array_shift($schedules);

        $this->assertEquals($launched["task"], $next["task"]);
        $this->assertEquals($launched["id"]+1, $next["id"]);
        $this->assertNotNull($launched["launchedDate"]);
        $this->assertNull($next["launchedDate"]);

        $date = new DateTime( $launched["nextDate"] );
        $date2 = new DateTime($next["nextDate"]);

        $this->assertEquals( $date->getTimestamp() + 60, $date2->getTimestamp() );

        $lds->deleteTask($tk->getTaskID());
        $schedules = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_SCHEDULE"));
        $this->assertCount(0, $schedules);
    }

    public function testRescheduleFromNow() {
        $lds = new LaunchdDaemonServer(self::$PDO, "now -3seconds", '* * * * *');

        $tk = $lds->registerTask("* * * * *", new FunctionCallRunner('my_test_function'), 'Test', Task::OPTION_RESCHEDULE_ACTUAL_DATE);

        $date = new DateTime("now");
        $seconds = $date->format('s');

        if ($seconds > 0) {
            $date->modify("+1 minute");
            $date->modify('-'.$seconds.' seconds');
        }

        $date->setTime($date->format("G"), $date->format("i"), 0);

        $lds->run(0, ['--run-all']);

        $schedules = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_SCHEDULE"));
        $this->assertCount(2, $schedules);

        array_shift($schedules);
        $next = array_shift($schedules);

        $nd = new DateTime( $next["nextDate"] );

        $this->assertEquals($date->getTimestamp(), $nd->getTimestamp());

        $lds->deleteTask($tk->getTaskID());
    }

    public function testFailedRunner() {
        $lds = new LaunchdDaemonServer(self::$PDO, "now -3seconds", '* * * * *');

        $tk = $lds->registerTask("* * * * *", new FunctionCallRunner('my_error_function'), "Test", Task::OPTION_REPORT_ERRORS);
        $lds->run(0, ['--run-all']);

        $schedules = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_SCHEDULE"));
        $this->assertCount(1, $schedules);

        $sch = array_shift($schedules);
        $this->assertEquals(0, $sch["success"]);
        $this->assertEquals("", $sch["output"]);

        $errors = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_TASK_ERROR"));
        $this->assertCount(1, $errors);
        $error = array_shift($errors);

        $this->assertEquals(3, $error['level']);
        $this->assertEquals("Error", $error["message"]);

        $lds->deleteTask($tk->getTaskID());
    }

    public function testFailedExceptionRunner() {
        $lds = new LaunchdDaemonServer(self::$PDO, "now -3seconds", '* * * * *');

        $tk = $lds->registerTask("* * * * *", new FunctionCallRunner('my_exception_function'), "Test", Task::OPTION_REPORT_ERRORS | Task::OPTION_REPEAT_ON_FATAL_ERROR);
        $lds->run(0, ['--run-all']);

        $schedules = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_SCHEDULE"));
        $this->assertCount(2, $schedules);

        $sch = array_shift($schedules);
        $this->assertEquals(0, $sch["success"]);
        $this->assertEquals("", $sch["output"]);

        $errors = iterator_to_array(self::$PDO->select("SELECT * FROM SKY_LAUNCHD_TASK_ERROR"));
        $this->assertCount(1, $errors);
        $error = array_shift($errors);

        $this->assertEquals(3, $error['level']);
        $this->assertEquals("Failed", $error["message"]);

        $lds->deleteTask($tk->getTaskID());
    }
}

function my_test_function() {
    echo "Output";
    trigger_error("Notice", E_USER_NOTICE);
    trigger_error("Warning", E_USER_WARNING);
}

function my_error_function() {
    trigger_error("Error", E_USER_ERROR);
    echo "BAD";
}

function my_exception_function() {
    throw new RuntimeException("Failed");
    echo "BAD";
}