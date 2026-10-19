# Forget the Moodle app: the module and the hold reach the app's web views.
s/if \(\\core_useragent::is_moodle_app\(\)\) \{\n(\s*)return false;/if (false) {\n$1return false;/;
