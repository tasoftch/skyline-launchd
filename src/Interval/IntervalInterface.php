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

namespace Skyline\Launchd\Interval;


use DateTime;
use Skyline\Launchd\Exception\ScheduleIntervalMatchException;
use Skyline\Launchd\Interval\Component\IntervalComponentInterface;

interface IntervalInterface
{
    /**
     * Checks, if a given date matches an interval specification
     *
     * @param DateTime $dateTime
     * @param bool $shouldThrowException
     * @return bool
     * @throws ScheduleIntervalMatchException
     */
    public function match(DateTime $dateTime, bool $shouldThrowException = true): bool;

    /**
     * Applies the interval to a given date
     *
     * @param DateTime $dateTime
     * @return DateTime|null
     */
    public function next(DateTime $dateTime): ?DateTime;

    /**
     * Transforms the interval into a string value
     *
     * @param bool $useNames
     * @return string
     */
    public function stringify(bool $useNames = true): string;

    /**
     * @return IntervalComponentInterface[]
     */
    public function getMinuteComponents(): array;

    /**
     * @return IntervalComponentInterface[]
     */
    public function getHourComponents(): array;

    /**
     * @return IntervalComponentInterface[]
     */
    public function getDayComponents(): array;

    /**
     * @return IntervalComponentInterface[]
     */
    public function getMonthComponents(): array;

    /**
     * @return IntervalComponentInterface[]
     */
    public function getWeekdayComponents(): array;

    /**
     * @return IntervalComponentInterface[]
     */
    public function getYearComponents(): array;

    /**
     * Gets all available minutes in an hour
     *
     * @return int[]
     */
    public function getMinuteItems();

    /**
     * Gets all available hours in a day
     *
     * @return int[]
     */
    public function getHourItems();

    /**
     * Gets all available days in a month
     *
     * @return int[]
     */
    public function getDayItems();

    /**
     * Gets all available month in a year
     *
     * @return int[]
     */
    public function getMonthItems();

    /**
     * Gets all available weekdays
     *
     * @return int[]
     */
    public function getWeekdayItems();

    /**
     * Gets all available years
     *
     * @return int[]
     */
    public function getYearItems();
}