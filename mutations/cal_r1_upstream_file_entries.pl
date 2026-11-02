# Drop the whole-file copies from the upstream entries: core's iCal script drifts unseen.
s/            self::function_entries\(\),\n            self::file_entries\(\)\n/            self::function_entries()\n/;
