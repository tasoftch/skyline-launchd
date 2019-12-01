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

namespace Skyline\Launchd\Interval\Component;


class IntervalComponent implements IntervalComponentInterface
{
    /** @var int|null */
    private $minimum;
    /** @var int|null */
    private $maximum;
    /** @var int|null */
    private $interval;

    /**
     * ScheduleStringComponent constructor.
     * @param int|null $minimum
     * @param int|null $maximum
     * @param int|null $interval
     */
    public function __construct(?int $minimum, int $interval = NULL, int $maximum = NULL)
    {
        $this->minimum = $minimum;
        $this->maximum = $maximum;
        $this->interval = $interval;
    }

    public function hasRange(): bool {
        return $this->maximum !== NULL;
    }

    public function hasInterval(): bool {
        return $this->interval !== NULL;
    }

    /**
     * @return int|null
     */
    public function getMinimum(): ?int
    {
        return $this->minimum;
    }

    /**
     * @return int|null
     */
    public function getMaximum(): ?int
    {
        return $this->maximum;
    }

    /**
     * @return int|null
     */
    public function getInterval(): ?int
    {
        return $this->interval;
    }

    public function stringify(int $minimum = NULL, int $maximum = NULL, array $names = []): string {
        if($this->interval == 1 && $this->minimum == $minimum && $this->maximum == $maximum)
            return "*";

        $string = $this->getMinimum();
        if($this->hasRange()) {
            if($this->minimum == $minimum && $this->maximum == $maximum)
                $string = "*";
            else {
                $min = $this->getMinimum();
                $max = $this->getMaximum();

                if($names && ($idx = array_search($min, $names)) !== NULL)
                    $min = $idx;
                if($names && ($idx = array_search($max, $names)) !== NULL)
                    $max = $idx;

                $string = sprintf("%s-%s", $min, $max);
            }
        }

        if($this->hasInterval() && $this->interval>1) {
            return "$string/$this->interval";
        }
        return $string;
    }
}