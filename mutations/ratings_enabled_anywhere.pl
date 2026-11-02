# Take the plugin's presence for its switch: a site that installed it and rates nothing reads its table on every page.
s/return class_exists\(self::RATING_HELPER\) && \\tool_courserating\\helper::course_ratings_enabled_anywhere\(\);/return class_exists(self::RATING_HELPER);/;
