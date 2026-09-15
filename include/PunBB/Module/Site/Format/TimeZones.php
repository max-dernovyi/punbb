<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Format;

/**
 * The time zones a member or the board chooses from, as offsets from UTC.
 */
final class TimeZones {
	/** @var array<int|string, string> the offset in hours, as a form posts it => the key of its name in the profile language pack */
	public const OFFSETS = array(
		'-12'	=> 'UTC-12:00',
		'-11'	=> 'UTC-11:00',
		'-10'	=> 'UTC-10:00',
		'-9.5'	=> 'UTC-09:30',
		'-9'	=> 'UTC-09:00',
		'-8'	=> 'UTC-08:00',
		'-7'	=> 'UTC-07:00',
		'-6'	=> 'UTC-06:00',
		'-5'	=> 'UTC-05:00',
		'-4'	=> 'UTC-04:00',
		'-3.5'	=> 'UTC-03:30',
		'-3'	=> 'UTC-03:00',
		'-2'	=> 'UTC-02:00',
		'-1'	=> 'UTC-01:00',
		'0'		=> 'UTC',
		'1'		=> 'UTC+01:00',
		'2'		=> 'UTC+02:00',
		'3'		=> 'UTC+03:00',
		'3.5'	=> 'UTC+03:30',
		'4'		=> 'UTC+04:00',
		'4.5'	=> 'UTC+04:30',
		'5'		=> 'UTC+05:00',
		'5.5'	=> 'UTC+05:30',
		'5.75'	=> 'UTC+05:45',
		'6'		=> 'UTC+06:00',
		'6.5'	=> 'UTC+06:30',
		'7'		=> 'UTC+07:00',
		'8'		=> 'UTC+08:00',
		'8.75'	=> 'UTC+08:45',
		'9'		=> 'UTC+09:00',
		'9.5'	=> 'UTC+09:30',
		'10'	=> 'UTC+10:00',
		'10.5'	=> 'UTC+10:30',
		'11'	=> 'UTC+11:00',
		'11.5'	=> 'UTC+11:30',
		'12'	=> 'UTC+12:00',
		'12.75'	=> 'UTC+12:45',
		'13'	=> 'UTC+13:00',
		'14'	=> 'UTC+14:00',
	);

	/** Whether $stored, a time zone as a setting or a profile stores it, is $offset: compared as numbers. */
	public static function is(string $stored, string $offset): bool {
		return is_numeric($stored) && (float) $stored === (float) $offset;
	}
}
