# A session already logged in goes to the course and never gets the interstitial.
s/            if \(isloggedin\(\)\) \{\n                return self::redirect_to\(guestaccess::target\(\(int\) \$course->id\)\);\n            \}\n//;
