# Keep the language the page named for the rest of the process.
s/            if \(\$previous !== null\) \{\n                force_current_language\(\$previous\);/            if (\$previous !== null) {\n                \$unused = 0;/;
