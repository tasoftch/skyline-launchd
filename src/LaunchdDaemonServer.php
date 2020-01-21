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

namespace Skyline\Launchd;


use DateTime;
use Skyline\Kernel\Service\Error\AbstractErrorHandlerService;
use TASoft\Util\Interval\Interval;
use TASoft\Util\Interval\IntervalInterface;
use TASoft\Util\Interval\Parser\IntervalStringParser;
use Skyline\Launchd\Task\Runner\ClassMethodRunner;
use Skyline\Launchd\Task\Runner\FunctionCallRunner;
use Skyline\Launchd\Task\Runner\ServiceMethodRunner;
use Skyline\Launchd\Task\Task;
use TASoft\Util\PDO;

class LaunchdDaemonServer
{
    const SERVICE_NAME = 'launchdService';

    /** @var PDO */
    private $PDO;
    /** @var DateTime|null  */
    private $remainSince;
    /** @var Interval */
    private $initialSchedule;

    private $cacheTasks;

    public function __construct($PDO, $remainSince, $initialSchedule)
    {
        $this->PDO = $PDO;
        $this->remainSince = $remainSince ? new DateTime($remainSince) : NULL;
        $this->initialSchedule = IntervalStringParser::parse($initialSchedule);
    }

    public function clearCache() {
        $this->cacheTasks = [];
    }

    /**
     * Internal method to check if given initial schedule is able to cover passed schedule interval.
     * This means, if initial promises a runloop every 5 minutes, a given interval of every minute will raise a warning.
     *
     * @param IntervalInterface $interval
     * @return bool
     */
    protected function checkScheduleInterval(IntervalInterface $interval) {
        if($this->initialSchedule && !$this->initialSchedule->hasIntersection($interval)) {
            trigger_error("Launchd is not able to schedule " . $interval->stringify() . " because its initial schedule " . $this->initialSchedule->stringify() . " won't include it. The task will be scheduled as requested but the executation is limited by initial schedule string.", E_USER_WARNING);
            return false;
        }
        return true;
    }

    /**
     * Registers a new task to be prepared for update and/or schedule
     * This method call adds given task information into the database and schedule it by the given interval string.
     *
     * @param IntervalInterface|string $schedule
     * @param FunctionCallRunner $runner
     * @param null $label
     * @param int $options
     * @return Task
     * @throws \Throwable
     */
    public function registerTask($schedule, FunctionCallRunner $runner, $label = NULL, $options = 0, int $error_reporting = E_ALL): Task {
        if(!($schedule instanceof IntervalInterface)) {
            $schedule = IntervalStringParser::parse($schedule);
        }


        $this->checkScheduleInterval($schedule);
        $PDO = $this->PDO;

        $PDO->transaction(function() use (&$options, $PDO, $label, $runner, $schedule, &$tid, &$sid, &$next, $error_reporting) {
            $conflicts = Task::OPTION_RESCHEDULE_TASK_DATE|Task::OPTION_RESCHEDULE_ACTUAL_DATE;
            if($options & $conflicts == $conflicts) {
                $options &= ~Task::OPTION_RESCHEDULE_TASK_DATE;
            }

            $PDO->inject("INSERT INTO SKY_LAUNCHD_TASK (active, label, schedule, className, serviceName, methodName, options, error_reporting) VALUES (1, ?, ?, ?, ?, ?, $options, ?)")->send([
                $label,
                $schedule->stringify(false),
                $runner instanceof ClassMethodRunner ? $runner->getClassName() : NULL,
                $runner instanceof ServiceMethodRunner ? $runner->getServiceName() : NULL,
                $runner->getFunctionName(),
                $error_reporting
            ]);

            $tid = $PDO->lastInsertId("SKY_LAUNCHD_TASK");

            $next = $schedule->next( new \DateTime() );

            if($next) {
                $nx = $PDO->quote($next->format("Y-m-d G:i:00"));
                $PDO->exec("INSERT INTO SKY_LAUNCHD_SCHEDULE (task, nextDate) VALUES ($tid, $nx)");
            }

            $sid = $PDO->lastInsertId("SKY_LAUNCHD_SCHEDULE");
        });

        return $this->cacheTasks[$tid] = new Task([
            'tid' => $tid,
            'schedule' => $schedule,
            'nextDate' => $next->format("Y-m-d G:i:s"),
            'options' => $options,
            'label' => $label,
            'runner' => $runner,
            'error_reporting' => $error_reporting
        ]);
    }


    /**
     * Fetches a task from the database
     *
     * @param int $taskID
     * @return Task|null
     * @throws \Exception
     */
    public function fetchTask(int $taskID): ?Task {
        if(!isset($this->cacheTasks[$taskID]) || NULL === $this->cacheTasks[$taskID]) {
            $record = $this->PDO->selectOne("SELECT
        SKY_LAUNCHD_TASK.id AS tid,
       active,
       label,
       options,
       schedule,
       nextDate,
       className,
       serviceName,
       methodName,
       error_reporting
FROM SKY_LAUNCHD_TASK
JOIN SKY_LAUNCHD_SCHEDULE ON task = SKY_LAUNCHD_TASK.id
WHERE SKY_LAUNCHD_TASK.id = $taskID AND launchedDate IS NULL LIMIT 1");
            if($record)
                $this->cacheTasks[$taskID] = new Task($record);
            else
                $this->cacheTasks[$taskID] = false;
        }
        return $this->cacheTasks[$taskID] ?: NULL;
    }

    /**
     * Completely deletes a task (including all scheduled and launched instances and their errors and results) from database.
     *
     * @param int $tid
     */
    public function deleteTask(int $tid) {
        if($this->PDO->getAttribute( PDO::ATTR_DRIVER_NAME ) == 'mysql') {
            $this->PDO->exec("DELETE
            SKY_LAUNCHD_TASK_ERROR,
            SKY_LAUNCHD_SCHEDULE,
            SKY_LAUNCHD_TASK
            FROM SKY_LAUNCHD_TASK
            LEFT JOIN SKY_LAUNCHD_SCHEDULE ON SKY_LAUNCHD_TASK.id = SKY_LAUNCHD_SCHEDULE.task
            LEFT JOIN SKY_LAUNCHD_TASK_ERROR ON SKY_LAUNCHD_TASK_ERROR.schedule = SKY_LAUNCHD_SCHEDULE.id
            WHERE task = $tid");
        } else {
            $toDelete = iterator_to_array( $this->PDO->select("SELECT id FROM SKY_LAUNCHD_SCHEDULE WHERE task = $tid") );

            foreach($toDelete as $d) {
                $sid = $d["id"];
                $this->PDO->exec("DELETE FROM SKY_LAUNCHD_TASK_ERROR WHERE schedule = $sid");
                $this->PDO->exec("DELETE FROM SKY_LAUNCHD_SCHEDULE WHERE id = $sid");
            }

            $this->PDO->exec("DELETE FROM SKY_LAUNCHD_TASK WHERE id = $tid");
        }
    }

    /**
     * Fetches pendent tasks from database (or cache if already fetched tasks are among them)
     *
     * @param bool $all     If true, fetches all pendent tasks, otherwise only those with executation time <= now
     * @return array
     * @throws \Exception
     */
    public function fetchPendentTasks($all = false) {
        $condition = $all ? '1' : 'nextDate <= NOW()';
        $tasks = [];

        foreach($this->PDO->select("SELECT
        SKY_LAUNCHD_TASK.id AS tid,
       active,
       label,
       options,
       schedule,
       nextDate,
       className,
       serviceName,
       methodName,
       error_reporting
FROM SKY_LAUNCHD_TASK
JOIN SKY_LAUNCHD_SCHEDULE ON task = SKY_LAUNCHD_TASK.id
WHERE launchedDate IS NULL AND active = 1 AND $condition
ORDER BY nextDate") as $record) {
            $tid = $record["tid"]*1;

            if(!isset($this->cacheTasks[ $tid ])) {
                $this->cacheTasks[$tid] = new Task($record);
            }

            $tasks[$tid] = $this->cacheTasks[$tid];
        }
        return $tasks;
    }


    /**
     * Updates the metadata of a task
     *
     * @param int $taskID
     * @param bool $active
     * @param null $label
     * @param null $options
     * @param int|NULL $errorReporting
     */
    public function updateTask(int $taskID, bool $active = NULL, $label = NULL, $options = NULL, int $errorReporting = NULL) {
        $changes = [];
        if(NULL !== $label)
            $changes["label"] = $this->PDO->quote($label);
        if(NULL !== $options)
            $changes["options"] = $this->PDO->quote($options, PDO::PARAM_INT);
        if(NULL !== $errorReporting)
            $changes["error_reporting"] = $this->PDO->quote($errorReporting, PDO::PARAM_INT);
        if(NULL !== $active)
            $changes["active"] = $this->PDO->quote($active, PDO::PARAM_BOOL);

        if($changes) {
            $v = [];

            foreach($changes as $name => $value) {
                $v[] = "$name = $value";
            }

            $this->PDO->inject("UPDATE SKY_LAUNCHD_TASK SET ".implode(", ", $v)." WHERE id = $taskID")->send([]);
        }
    }

    /**
     * Updates a task runner
     *
     * @param int $taskID
     * @param FunctionCallRunner $runner
     */
    public function updateTaskRunner(int $taskID, FunctionCallRunner $runner) {
        if($runner instanceof ClassMethodRunner) {
            $this->PDO->inject("UPDATE SKY_LAUNCHD_TASK SET methodName = ?, className = ?, serviceName = NULL WHERE id = $taskID")->send([
                $runner->getFunctionName(),
                $runner->getClassName()
            ]);
        } elseif($runner instanceof ServiceMethodRunner) {
            $this->PDO->inject("UPDATE SKY_LAUNCHD_TASK SET methodName = ?, serviceName = ?, className = NULL WHERE id = $taskID")->send([
                $runner->getFunctionName(),
                $runner->getServiceName()
            ]);
        }
    }

    /**
     * Updates a task interval
     *
     * @param int $taskID
     * @param string|IntervalInterface $interval
     * @param bool $reschedule
     * @throws \Exception
     */
    public function updateTaskInterval(int $taskID, $interval, bool $reschedule = true) {
        if(!($interval instanceof IntervalInterface))
            $interval = IntervalStringParser::parse( $interval );

        if($interval instanceof IntervalInterface) {
            $string = $interval->stringify(false);
            $this->PDO->inject("UPDATE SKY_LAUNCHD_TASK SET schedule = ? WHERE id = $taskID")->send([
                $string
            ]);

            if($reschedule) {
                $lastLaunchedDate = $this->PDO->selectFieldValue("SELECT launchedDate FROM SKY_LAUNCHD_SCHEDULE WHERE task = 6 AND launchedDate IS NOT NULL ORDER BY launchedDate DESC LIMIT 1", 'launchedDate');
                if($lastLaunchedDate)
                    $date = new DateTime($lastLaunchedDate);
                else
                    $date = new DateTime();

                $this->rescheduleTask($taskID, $date, $interval);
            }
        }
    }

    /**
     * Reschedules a task from a referenced date using an interval
     *
     * @param int $taskID
     * @param DateTime $referenceDate
     * @param string|IntervalInterface|null $applyInterval
     */
    public function rescheduleTask(int $taskID, DateTime $referenceDate, $applyInterval = NULL) {
        if(NULL !== $applyInterval && !($applyInterval instanceof IntervalInterface))
            $applyInterval = IntervalStringParser::parse( $applyInterval );


        // Deletes all pending task instances before inserting a new one.
        // The launchd system of skyline assumes only one pending instance of a task.
        $this->PDO->exec("DELETE FROM SKY_LAUNCHD_SCHEDULE WHERE task = $taskID AND launchedDate IS NULL");

        if($applyInterval)
            $next = $applyInterval->next( $referenceDate );
        else
            $next = $referenceDate;

        $this->PDO->inject("INSERT INTO SKY_LAUNCHD_SCHEDULE (task, nextDate) VALUES ($taskID, ?)")->send([
            $next->format("Y-m-d G:i:s")
        ]);
    }


    public function completeTask(Task $task, bool $success, string $result, array $errors) {
        $output = $this->PDO->quote($result);
        $success *= 1;
        $taskID = $task->getTaskID();

        $schID = $this->PDO->selectOne("SELECT id FROM SKY_LAUNCHD_SCHEDULE WHERE launchedDate IS NULL AND task = $taskID")["id"];

        $this->PDO->exec("UPDATE SKY_LAUNCHD_SCHEDULE SET launchedDate = NOW(), success = $success, output = $output WHERE task = $taskID AND launchedDate IS NULL");

        $options = $task->getOptions();

        if($errors && $options & Task::OPTION_REPORT_ERRORS) {
            // Adds errors to database

            $errors = array_map(function($value) use ($schID) {
                $error = implode(", ", array_map(function($v) { return $this->PDO->quote($v); }, $value));
                return sprintf("($schID, $error)");
            }, $errors);

            $errors = implode(",", $errors);
            $this->PDO->exec("INSERT INTO SKY_LAUNCHD_TASK_ERROR (schedule, code, level, message, file, line) VALUES $errors");
        }

        if(!$success) {
            if($options & Task::OPTION_REPEAT_ON_FATAL_ERROR) {
                $this->rescheduleTask($taskID, $task->getScheduledDate());
                return;
            }
        }

        if($options & Task::OPTION_RESCHEDULE_TASK_DATE)
            $this->rescheduleTask($taskID, $task->getScheduledDate(), $task->getScheduleString());
        elseif($options & Task::OPTION_RESCHEDULE_ACTUAL_DATE)
            $this->rescheduleTask($taskID, new DateTime(), $task->getScheduleString());
    }


    private function listScheduled() {
        echo "SCHEDULED TASKS\nUNDER INITIAL          : $this->initialSchedule\n";
        $now = new DateTime();
        printf("NOW                    : %s,\nNEXT EXPECTED RUN LOOP : %s\n\n", $now->format("d.m.Y G:i:s"), ($next = $this->initialSchedule->next($now))->format("d.m.Y G:i:s"));

        foreach($this->PDO->select("SELECT
    SKY_LAUNCHD_SCHEDULE.id,
    active,
    label,
    schedule,
       options,
    case WHEN serviceName IS NULL
        THEN concat(className, '::', methodName)
        ELSE concat('$', serviceName, '::', methodName)
    END AS target,
       nextDate
FROM SKY_LAUNCHD_TASK
JOIN SKY_LAUNCHD_SCHEDULE ON task = SKY_LAUNCHD_TASK.id
WHERE launchedDate IS NULL
ORDER BY nextDate") as $record) {
            printf("** {$record["label"]} (%s)\n", $record["active"] ? 'enabled' : 'disabled');
            printf("    #%d <%s> (%d)\n", $record["id"], $record["schedule"], $record["options"]);
            printf("    %s\n", $record["target"]);
            $scheduled = new DateTime($record["nextDate"]);
            printf("    %s (scheduled)\n", $next->format("d.m.Y G:i:s"));
            if($scheduled->getTimestamp() < $now->getTimestamp())
                echo "    IMMEDIATELY\n";
            elseif($scheduled->getTimestamp() < $next->getTimestamp())
                echo "    AT NEXT RUNLOOP\n";
            else
                echo "    AT ", $scheduled->format("d.m.Y G:i:s"), "\n";
            echo "\n";
        }
    }


    /**
     * Called by the Launchd init plugin, listening on the bootstrap event.
     * Please don't call this method directly.
     *
     * @param $argc
     * @param $argv
     * @throws \Exception
     */
    public function run($argc, $argv) {
        if(in_array('--scheduled', $argv)) {
            $this->listScheduled();
            return;
        }

        if($this->PDO->getAttribute(PDO::ATTR_DRIVER_NAME) == 'sqlite') {
            $this->PDO->sqliteCreateFunction('NOW', function() {
                return (new DateTime())->format("Y-m-d G:i:s");
            });
        }

        if($this->remainSince) {
            $date = $this->remainSince->format("Y-m-d G:i:s");
            echo "Delete everything older than $date\n";
            if($this->PDO->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql') {
                $this->PDO->exec("DELETE
                SKY_LAUNCHD_SCHEDULE,
                SKY_LAUNCHD_TASK_ERROR
                FROM SKY_LAUNCHD_SCHEDULE
                LEFT JOIN SKY_LAUNCHD_TASK_ERROR ON schedule = SKY_LAUNCHD_SCHEDULE.id
                WHERE launchedDate IS NOT NULL AND launchedDate < '$date'");
            } else {
                $delete = iterator_to_array( $this->PDO->select("SELECT id FROM SKY_LAUNCHD_SCHEDULE WHERE launchedDate IS NOT NULL AND launchedDate < '$date'"));
                foreach($delete as $d) {
                    $sid = $d["id"];
                    $this->PDO->exec("DELETE FROM SKY_LAUNCHD_TASK_ERROR WHERE schedule = $sid");
                    $this->PDO->exec("DELETE FROM SKY_LAUNCHD_SCHEDULE WHERE id = $sid");
                }
            }
        }

        $tasks = $this->fetchPendentTasks( in_array('--run-all', $argv) ? true : false );

        $errors = [];


        set_error_handler(function($code, $msg, $file, $line) use (&$errors, &$errorReporting) {
            $level = AbstractErrorHandlerService::detectErrorLevel($code);

            if($errorReporting & $code) {
                $errors[] = [
                    $code,
                    $level,
                    $msg,
                    $file,
                    $line
                ];
            }

            if($level >= 3)
                throw new \Error("", -1);
        });

        /** @var Task $task */
        foreach($tasks as $task) {
            try {
                $runner = $task->getTaskRunner();

                ob_start();

                $this->PDO->beginTransaction();

                if(method_exists($task, 'getErrorReporting'))
                    $errorReporting = $task->getErrorReporting();
                else
                    $errorReporting = error_reporting();

                $runner->execute($argc, $argv);

                $cnt = ob_get_contents();
                ob_end_clean();

                $this->completeTask($task, true, $cnt, $errors);
                $this->PDO->commit();
            } catch (\Throwable $exception) {
                $this->PDO->rollBack();

                $cnt = ob_get_contents();
                ob_end_clean();
                if($exception->getCode() > -1) {
                    $errors[] = [
                        $exception->getCode(),
                        AbstractErrorHandlerService::FATAL_ERROR_LEVEL,
                        $exception->getMessage(),
                        $exception->getFile(),
                        $exception->getLine()
                    ];
                }

                $this->completeTask($task, false, $cnt, $errors);
            }
        }

        restore_error_handler();
    }
}