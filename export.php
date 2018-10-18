<?php

require_once __DIR__ . '/vendor/autoload.php';

function indexBy(array $list, string $key): array
{
	$indexed = [];
	foreach ($list as $item) {
		$indexed[$item[$key]] = $item;
	}

	return $indexed;
}

$ymlParser = new \Symfony\Component\Yaml\Parser();

$scheduleDay1 = $ymlParser->parseFile(__DIR__ . '/geecon-2015-mobile/_data/schedule.yml');
$scheduleDay2 = $ymlParser->parseFile(__DIR__ . '/geecon-2015-mobile/_data/schedule2.yml');
$sessions = $ymlParser->parseFile(__DIR__ . '/geecon-2015-mobile/_data/sessions.yml');
$speakers = $ymlParser->parseFile(__DIR__ . '/geecon-2015-mobile/_data/speakers.yml');

$sessionsById = indexBy($sessions, 'id');
$speakersById = indexBy($speakers, 'id');

$vCalendar = new \Eluceo\iCal\Component\Calendar('2018.geecon.cz');

$days = [
	'2018-10-18' => $scheduleDay1['timeslots'],
	'2018-10-19' => $scheduleDay2['timeslots']
];
$roomsByDay = [
	'2018-10-19' => $scheduleDay2['tracks'],
	'2018-10-18' => $scheduleDay1['tracks']
];

foreach ($days as $date => $timeslots) {
	$day = new DateTimeImmutable($date);
	$rooms = $roomsByDay[$date];

	foreach ($timeslots as $timeslot) {
		list($startTimeHours, $startTimeMinutes) = explode(':', $timeslot['startTime'], 2);
		list($endTimeHours, $endTimeMinutes) = explode(':', $timeslot['endTime'], 2);

		$startTime = $day->setTime((int) ltrim($startTimeHours, '0'), (int) ltrim($startTimeMinutes, '0'));
		$endTime = $day->setTime((int) ltrim($endTimeHours, '0'), (int) ltrim($endTimeMinutes, '0'));

		foreach ($timeslot['sessionIds'] as $sessionIndex => $sessionId) {
			$session = $sessionsById[$sessionId];
			$speakers = array_key_exists('speakers', $session) ? array_map(function ($item) use ($speakersById): string {
				$speaker = $speakersById[$item];
				return $speaker['name'] . ' ' . $speaker['surname'];
			}, $session['speakers']) : [];

			$vEvent = new \Eluceo\iCal\Component\Event();
			$vEvent
				->setDtStart($startTime)
				->setDtEnd($endTime)
				->setUseTimezone(true)
				->setTimezoneString('Europe/Prague')
				->setLocation($rooms[$sessionIndex]['title'])
				->setDescription($session['description'])
				->setSummary(implode(', ', $speakers) . (count($speakers) > 0 ? ': ' : '') . $session['title']);

			$vCalendar->addComponent($vEvent);
		}
	}
}

file_put_contents(__DIR__ . '/geecon.2018.ical', $vCalendar->render());
