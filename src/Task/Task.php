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

namespace Skyline\Launchd\Task;


use DateTime;
use Skyline\Launchd\Task\Runner\ClassMethodRunner;
use Skyline\Launchd\Task\Runner\FunctionCallRunner;
use Skyline\Launchd\Task\Runner\NullRunner;
use Skyline\Launchd\Task\Runner\RunnerInterface;
use Skyline\Launchd\Task\Runner\ServiceMethodRunner;

class Task extends AbstractTask
{
    const OPTION_RESCHEDULE_ACTUAL_DATE = 1;
    const OPTION_RESCHEDULE_TASK_DATE = 2;
    const OPTION_REPEAT_ON_FATAL_ERROR = 4;
    const OPTION_REPORT_ERRORS = 8;

    /** @var int */
    private $options;
    /** @var string */
    private $label;
    /** @var int */
    private $errorReporting;

    /**
     * @return int
     */
    public function getOptions(): int
    {
        return $this->options;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Task constructor.
     * @param array $record
     * @throws \Exception
     */
    public function __construct($record)
    {
        parent::__construct(
            $record["tid"]*1,
            $record["schedule"],
            $this->makeRunner($record),
            new DateTime($record["nextDate"])
        );
        $this->options = $record["options"]*1;
        $this->label = $record["label"];
        $this->errorReporting = ($record["error_reporting"]??0)*1;
    }

    /**
     * Internal method to decide, which runner used to perform task
     *
     * @param $record
     * @return RunnerInterface
     */
    protected function makeRunner($record): RunnerInterface {
        if($rn = $record["runner"] ?? NULL) {
            if($rn instanceof RunnerInterface)
                return $rn;
        }
        if($record["active"]) {
            $mt = $record["methodName"];

            if($cn = $record["className"])
                return new ClassMethodRunner($mt, $cn);

            if($sn = $record["serviceName"])
                return new ServiceMethodRunner($mt, $cn);

            if($mt)
                return new FunctionCallRunner($mt);
        }
        return new NullRunner();
    }

    /**
     * Declare which errors should be reported during task executation.
     * Same constant values as in PHP itself. E_* constants
     *
     * @return int
     */
    public function getErrorReporting(): int
    {
        return $this->errorReporting;
    }
}