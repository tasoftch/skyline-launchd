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

namespace Skyline\Launchd\Task\Runner;


use Skyline\Launchd\Exception\TaskException;
use TASoft\Service\ServiceManager;

class TaskRunner
{
    /** @var null|string */
    private $className;
    /** @var string */
    private $methodName;
    /** @var string|null */
    private $serviceName;

    /**
     * TaskRunner constructor.
     * @param string|null $className
     * @param string $methodName
     * @param string|null $serviceName
     */
    public function __construct(string $methodName, string $className = NULL, string $serviceName = NULL)
    {
        $this->className = $className;
        $this->methodName = $methodName;
        $this->serviceName = $serviceName;
        if(!$className && !$serviceName)
            throw new TaskException("TaskRunner must have a class name or a service name");
    }


    /**
     * @return string|null
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * @return string
     */
    public function getMethodName(): string
    {
        return $this->methodName;
    }

    /**
     * @return string|null
     */
    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    public function __invoke(...$args)
    {
        if($cn = $this->getClassName()) {
            $method = [$cn, $this->getMethodName()];
            if(is_callable($method))
                return call_user_func_array($method, $args);
        } elseif($sn = $this->getServiceName()) {
            $service = ServiceManager::generalServiceManager()->get( $sn );
            return call_user_func_array([$service, $this->getMethodName()], $args);
        }

        trigger_error("Task $method is not callable.", E_USER_ERROR);
        return NULL;
    }
}