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
 * RunnerTest.php
 * skyline-launchd
 *
 * Created on 2019-12-03 12:19 by thomas
 */

use PHPUnit\Framework\TestCase;
use Skyline\Launchd\Task\Runner\ClassMethodRunner;
use Skyline\Launchd\Task\Runner\FunctionCallRunner;
use Skyline\Launchd\Task\Runner\NullRunner;
use Skyline\Launchd\Task\Runner\ServiceMethodRunner;
use TASoft\Service\ServiceManager;

class RunnerTest extends TestCase
{
    public static function execute($argc, $argv) {
        $GLOBALS["testResult"] = func_get_args();
        return true;
    }

    public function testNullRunner() {
        $runner = new NullRunner();

        $this->assertTrue($runner->isEnabled());
        $this->assertTrue( $runner->execute(0, []) );
    }

    public function testFunctionCallRunner() {
        $runner = new FunctionCallRunner('myCBFunction');
        $this->assertTrue($runner->isEnabled());
        $this->assertTrue($runner->execute(1, [5]));

        global $testResult;
        $this->assertEquals([1, [5]], $testResult);
    }

    public function testClassCallback() {
        $runner = new ClassMethodRunner('execute', RunnerTest::class);
        $this->assertTrue($runner->isEnabled());

        global $testResult;

        $testResult = NULL;
        $this->assertTrue($runner->execute(1, [5]));

        $this->assertEquals([1, [5]], $testResult);
    }

    public function testServiceCallback() {
        ServiceManager::rejectGeneralServiceManager();
        $sm = ServiceManager::generalServiceManager([]);

        $sm->set('myService', $this);

        $runner = new ServiceMethodRunner("doRun", "myService");
        $this->assertTrue($runner->isEnabled());

        global $testResult;

        $testResult = NULL;
        $this->assertTrue($runner->execute(1, [5]));

        $this->assertEquals([1, [5]], $testResult);
    }

    public function testUnexistingFunctionName() {
        $runner = new FunctionCallRunner("nonexisting_functionname");

        $this->assertFalse( $runner->isEnabled() );
    }

    public function testUnexistingClassMethod() {
        $runner = new ClassMethodRunner('unexisting', RunnerTest::class);
        $this->assertFalse( $runner->isEnabled() );
    }

    /**
     * @depends testServiceCallback
     */
    public function testUnexistingServiceMethod() {
        $runner = new ServiceMethodRunner('unexisting', "myService");
        $this->assertFalse( $runner->isEnabled() );
    }

    /**
     * @expectedException TASoft\Service\Exception\UnknownServiceException
     */
    public function testUnexistingService() {
        $runner = new ServiceMethodRunner('unexisting', "unexisting_service");
        $this->assertFalse( $runner->isEnabled() );
    }

    public function doRun($argc, $argv) {
        $GLOBALS["testResult"] = func_get_args();
        return true;
    }
}


function myCBFunction($argc, $argv) {
    $GLOBALS["testResult"] = func_get_args();
    return true;
}