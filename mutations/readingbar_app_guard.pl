# Forget the Moodle app: the scroll machinery runs inside the app's own chrome.
s/(public static function applies\(\\moodle_page \$page\): bool \{\n(?:[^\n]*\n)*?)\s*if \(\\core_useragent::is_moodle_app\(\)\) \{\n\s*return false;\n\s*\}\n/$1\n/;
