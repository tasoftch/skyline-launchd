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
 * IntervalParserTest.php
 * skyline-launchd
 *
 * Created on 2019-12-01 17:26 by thomas
 */

use PHPUnit\Framework\TestCase;
use Skyline\Launchd\Interval\Interval;
use Skyline\Launchd\Interval\Parse\IntervalStringParser;

class IntervalParserTest extends TestCase
{
    public function testParser() {
        /** @var Interval $int */
        $int = IntervalStringParser::parse( "* * * * *" );
        $this->assertEquals("* * * * *", $int);

        $int = IntervalStringParser::parse( "0 1-8/2 * 4 *" );
        $this->assertEquals("0 1-8/2 * 4 *", $int);

        $this->assertEquals([0], $int->getMinuteItems());
        $this->assertEquals([1, 3, 5, 7], $int->getHourItems());
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8,9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31], $int->getDayItems());
        $this->assertEquals([4], $int->getMonthItems());
        $this->assertEquals([0, 1, 2, 3, 4, 5, 6], $int->getWeekdayItems());

        $int = IntervalStringParser::parse( "0 1-8/2 * 4-6 *" );
        $this->assertEquals("0 1-8/2 * APR-JUN *", $int->stringify(true));
    }

    /**
     * @dataProvider dataProviders
     */
    public function testScheduleString($scheduleString, $date, $expected) {
        $result = IntervalStringParser::parse ($scheduleString);
        $this->assertEquals($expected, $result->match( $date ));
    }

    public function dataProviders() {
        return [
            ["* * * * * 2010,2013,2016-2019", new DateTime("2019-01-01 15:34"), true],
            ["0,23,57 * * JUN-SEP *", new DateTime("2019-01-01 15:34"), false],
            ["2-25,34 * 2-19/5 1,3,7 MON,TUE,FRI", new DateTime("2019-01-07 15:34"), true],
        ];
    }

    public function testSchedulable() {
        /** @var Interval $intv1 */
        $intv1 = IntervalStringParser::parse("* * * * *");
        /** @var Interval $intv2 */
        $intv2 = IntervalStringParser::parse("*/5 * * * *");

        $this->assertTrue($intv1->hasIntersection($intv2));
        $this->assertFalse($intv2->hasIntersection($intv1));
    }

    public function testNextDate() {
        $intv = IntervalStringParser::parse("* * * * *");
        $date = new DateTime("2019-11-30 12:37:33");
        $date = $intv->next($date);
        $this->assertEquals("2019-11-30 12:38", $date->format("Y-m-d G:i"));

        $intv = IntervalStringParser::parse("0 * * * *");
        $date = new DateTime("2019-11-30 12:37:33");
        $date = $intv->next($date);
        $this->assertEquals("2019-11-30 13:00", $date->format("Y-m-d G:i"));

        $intv = IntervalStringParser::parse("0 0 * * *");
        $date = new DateTime("2019-11-30 12:37:33");
        $date = $intv->next($date);
        $this->assertEquals("2019-12-01 0:00", $date->format("Y-m-d G:i"));

        $intv = IntervalStringParser::parse("0 0 4 * *");
        $date = new DateTime("2019-11-30 12:37:33");
        $date = $intv->next($date);
        $this->assertEquals("2019-12-04 0:00", $date->format("Y-m-d G:i"));

        $intv = IntervalStringParser::parse("0 0 2 APR-AUG *");
        $date = new DateTime("2019-11-30 12:37:33");
        $date = $intv->next($date);
        $this->assertEquals("2020-04-02 0:00", $date->format("Y-m-d G:i"));

        $intv = IntervalStringParser::parse("0 0 2 APR-AUG MON-WED");
        $date = new DateTime("2019-11-30 12:37:33");
        $date = $intv->next($date);
        $this->assertEquals("Tue 2020-06-02 0:00", $date->format("D Y-m-d G:i"));
    }
}
