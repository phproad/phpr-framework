return array(
	'total' => 3, // the amount of pluralizations in the Russian language
	'current' => ($value === 0 ? 0 : (($value % 10 == 1) && ($value % 100 != 11)) ? 1 : ((($value % 10 >= 2) && ($value % 10 <= 4) && (($value % 100 < 10) || ($value % 100 >= 20))) ? 2 : 3))
);