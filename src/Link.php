<?php declare(strict_types=1);

namespace Spatie\CalendarLinks;

use Spatie\CalendarLinks\Exceptions\InvalidLink;
use Spatie\CalendarLinks\Generators\Google;
use Spatie\CalendarLinks\Generators\Ics;
use Spatie\CalendarLinks\Generators\WebOffice;
use Spatie\CalendarLinks\Generators\WebOutlook;
use Spatie\CalendarLinks\Generators\Yahoo;

/**
 * @property-read string $title
 * @property-read \DateTimeInterface|\DateTime|\DateTimeImmutable $from
 * @property-read \DateTimeInterface|\DateTime|\DateTimeImmutable $to
 * @property-read string $description
 * @property-read string $address
 * @property-read bool $allDay
 * @psalm-import-type IcsOptions from \Spatie\CalendarLinks\Generators\Ics
 */
class Link
{
    public readonly string $title;

    public readonly \DateTimeImmutable $from;

    public readonly \DateTimeImmutable $to;

    public readonly bool $allDay;

    public string $description = '';

    public string $address = '';

    final public function __construct(string $title, \DateTimeInterface $from, \DateTimeInterface $to, bool $allDay = false)
    {
        $this->title = $title;
        $this->allDay = $allDay;

        if ($from > $to) {
            throw InvalidLink::negativeDateRange($from, $to);
        }

        $this->from = \DateTimeImmutable::createFromInterface($from);
        $this->to = \DateTimeImmutable::createFromInterface($to);
    }

    /**
     * @throws \Spatie\CalendarLinks\Exceptions\InvalidLink When date range is invalid.
     */
    public static function create(string $title, \DateTimeInterface $from, \DateTimeInterface $to, bool $allDay = false): static
    {
        // When creating all day events, we need to be in the UTC timezone as all day events are "floating" based on the user's timezone
        if ($allDay) {
            $startDate = new \DateTime($from->format('Y-m-d'), new \DateTimeZone('UTC'));
            $numberOfDays = $from->diff($to)->days + 1;

            return self::createAllDay($title, $startDate, $numberOfDays);
        }

        return new static($title, $from, $to, $allDay);
    }

    /**
     * @param positive-int $numberOfDays
     * @throws \Spatie\CalendarLinks\Exceptions\InvalidLink When date range is invalid.
     */
    public static function createAllDay(string $title, \DateTimeInterface $fromDate, int $numberOfDays = 1): self
    {
        // In cases where the from date is not UTC, make sure it's UTC, size all day events are floating and non UTC dates cause bugs in the generators
        if ($fromDate->getTimezone() !== new \DateTimeZone('UTC')) {
            $fromDate = \DateTime::createFromFormat('Y-m-d', $fromDate->format('Y-m-d'));
        }

        $from = \DateTimeImmutable::createFromInterface($fromDate)->modify('midnight');
        $to = $from->modify("+$numberOfDays days");
        assert($to instanceof \DateTimeImmutable);

        return new self($title, $from, $to, true);
    }

    /** Set description of the Event. */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** Set address of the Event. */
    public function address(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function formatWith(Generator $generator): string
    {
        return $generator->generate($this);
    }

    public function google(): string
    {
        return $this->formatWith(new Google());
    }

    /**
     * @psalm-param IcsOptions $options ICS specific properties and components
     * @param array<non-empty-string, non-empty-string> $options ICS specific properties and components
     * @param array{format?: \Spatie\CalendarLinks\Generators\Ics::FORMAT_*} $presentationOptions
     * @return string
     */
    public function ics(array $options = [], array $presentationOptions = []): string
    {
        return $this->formatWith(new Ics($options, $presentationOptions));
    }

    public function yahoo(): string
    {
        return $this->formatWith(new Yahoo());
    }

    public function webOutlook(): string
    {
        return $this->formatWith(new WebOutlook());
    }

    public function webOffice(): string
    {
        return $this->formatWith(new WebOffice());
    }
}
