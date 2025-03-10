<?php
namespace LWS\Adminpanel\Tools;

if( !defined( 'ABSPATH' ) ) exit();

class Dates
{
	/** list of order status formatted for LAC */
	static function create($d=false): \DateTimeImmutable
	{
		$date = false;
		if (\is_numeric($d)) $date = \date_create_immutable('now', \wp_timezone())->setTimestamp($d);
		elseif (\is_string($d)) $date = \date_create_immutable($d, \wp_timezone());
		elseif (\is_a($d, '\DateTimeImmutable')) $date = clone $d;
		elseif (\is_a($d, '\DateTimeInterface')) $date = \DateTimeImmutable::createFromInterface($d);

		if (!$date) $date = \date_create_immutable('now', \wp_timezone());
		return $date;
	}

	/** @return array|object {day, month, year} */
	static public function split(\DateTimeInterface $date, $asObject=true)
	{
		$s = [
			'day'   => (int)$date->format('d'),
			'month' => (int)$date->format('m'),
			'year'  => (int)$date->format('Y'),
		];
		return $asObject ? (object)$s : $s;
	}

	static public function replace(\DateTimeInterface $date, array $values): \DateTimeImmutable
	{
		$d = self::create($date);
		$s = (object)\array_merge(self::split($d, false), $values);
		return $d->setDate($s->year, $s->month, \min($s->day, self::daysInMonth($s->year, $s->month)));
	}

	static public function daysInMonth(int $year, int $month): int
	{
		return (int) \cal_days_in_month(CAL_GREGORIAN, $month, $year);
	}

	static public function maybeKeepLastDay(int $day, int $yearFrom, int $monthFrom, int $yearTo, int $monthTo): int
	{
		if ($day == self::daysInMonth($yearFrom, $monthFrom))
			return self::daysInMonth($yearTo, $monthTo);
		else
			return \min($day, self::daysInMonth($yearTo, $monthTo));
	}

	static public function add($date, string $interval): \DateTimeImmutable
	{
		if ('P' !== \substr($interval, 0, 1)) $interval = 'P' . $interval;
		return self::create($date)->add(new \DateInterval($interval));
	}

	static public function sub($date, string $interval): \DateTimeImmutable
	{
		if ('P' !== \substr($interval, 0, 1)) $interval = 'P' . $interval;
		return self::create($date)->sub(new \DateInterval($interval));
	}

	static public function addMonths($date, int $number, int $sameDay=-1): \DateTimeImmutable
	{
		$date = self::create($date);
		$before = self::split($date);

		if ($number) {
			$date = $date->setDate($before->year, $before->month, 1);
			if ($number > 0) $date = $date->add(new \DateInterval("P{$number}M"));
			else $date = $date->sub(new \DateInterval("P{$number}M"));
		}

		$after = self::split($date);
		if ($sameDay > 0) $day = \min($sameDay, self::daysInMonth($after->year, $after->month));
		else $day = self::maybeKeepLastDay($before->day, $before->year, $before->month, $after->year, $after->month);
		return $date->setDate($after->year, $after->month, $day);
	}

	static public function subMonths($date, int $number, int $sameDay=-1): \DateTimeImmutable
	{
		return self::addMonths($date, -$number, $sameDay);
	}
}