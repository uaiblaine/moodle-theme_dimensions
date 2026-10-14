# The promotion section is drawn on the landing page only.
s/(private static function add_promotion\(.*?)if \(!\$notice->landing\) \{/${1}if (false) {/s;
