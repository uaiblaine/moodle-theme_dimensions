# The promotion section is drawn on the course home only.
s/(private static function add_promotion\(.*?)if \(!\$notice->coursehome\) \{/${1}if (false) {/s;
