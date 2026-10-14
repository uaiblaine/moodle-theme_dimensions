# An invalid custom URL falls back to the site home page: the shape and local-URL checks go, the root check stays.
s/            if \(self::is_url\(\$url\) && !self::is_root_path\(\$url\) && clean_param\(\$CFG->wwwroot \. \$url, PARAM_LOCALURL\) !== ''\) \{/            if (!self::is_root_path(\$url)) {/;
