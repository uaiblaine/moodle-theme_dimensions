# Answer a language that is not installed.
s/if \(!in_array\(\$half, publiccache::HALVES, true\) \|\| !get_string_manager\(\)->translation_exists\(\$lang, false\)\) \{/if (!in_array(\$half, publiccache::HALVES, true)) {/;
