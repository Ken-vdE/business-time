<?php

namespace BusinessTime;

use BusinessTime\Exceptions\InvalidArgumentException;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use DateInterval;

class Calculator
{
    /**
     * @var CarbonInterface
     */
    protected $date;

    /**
     * @var CarbonInterval
     */
    protected $interval;

    /**
     * @var bool
     */
    protected $open;

    /**
     * @var bool
     */
    protected $holidaysAreClosed;

    /**
     * @var bool
     */
    protected $past;

    public function __construct(CarbonInterface $date, CarbonInterval $interval, bool $open, bool $holidaysAreClosed)
    {
        $this->date = $date;
        $this->interval = $interval;
        $this->open = $open;
        $this->holidaysAreClosed = $holidaysAreClosed;
    }

    public function calculate($maximum = INF): CarbonInterface
    {
        $date = $this->date;
        $interval = $this->interval;
        $resultCandidate = $date->copy()->add($interval);
        $this->past = $resultCandidate < $date;
        $base = $this->getStartDate($date);

        for ($i = 0; $i < $maximum; $i++) {
            [$next, $resultCandidate] = $this->getNextAndCandidate($base, $interval);

            if ($this->isInLimit($resultCandidate, $next)) {
                return $date->setDateTimeFrom($resultCandidate);
            }

            $interval = $next->diff($resultCandidate, false);
            $base = $this->getNextInTakenState($next);
        }

        throw new InvalidArgumentException('Maximum iteration ('.$maximum.') has been reached.');
    }

    protected function isInExpectedState(CarbonInterface $date): bool
    {
        $methodPrefix = 'is';

        if ($this->holidaysAreClosed) {
            $methodPrefix .= 'Business';
        }

        return $date->{$methodPrefix.($this->open ? 'Open' : 'Closed')}();
    }

    protected function getNextInTakenState(CarbonInterface $date): CarbonInterface
    {
        return $this->getNext($date, $this->open);
    }

    protected function getNext(CarbonInterface $date, bool $openState): CarbonInterface
    {
        $methodPrefix = $this->past ? 'previous' : 'next';

        if ($this->holidaysAreClosed) {
            $methodPrefix .= 'Business';
        }

        return $date->copy()->{$methodPrefix.($this->past === $openState ? 'Close' : 'Open')}();
    }

    protected function getNextAndCandidate(CarbonInterface $date, DateInterval $interval): array
    {
        $next = $this->getNextInSkippedState($date);
        $resultCandidate = $date->copy()->add($interval);

        if (!$this->isInExpectedState($date)) {
            $next = $this->getNextInSkippedState($date);
        }

        return [$next, $resultCandidate];
    }

    protected function getNextInSkippedState(CarbonInterface $date): CarbonInterface
    {
        return $this->getNext($date, !$this->open);
    }

    protected function isInLimit(CarbonInterface $possibleResult, CarbonInterface $limitDate): bool
    {
        return $this->past ? $possibleResult >= $limitDate : $possibleResult < $limitDate;
    }

    protected function getStartDate(CarbonInterface $date)
    {
        return $this->isInExpectedState($date) || (
            $this->past && $this->isInExpectedState($date->copy()->subMicrosecond())
        )
            ? $date
            : $this->getNextInTakenState($date);
    }
}
