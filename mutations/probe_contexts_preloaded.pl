# Hand the probed unlisted courses to the plugin with their contexts cold: its relationship check then reads
# one {context} row per probed course, and the root of a large subtree stops being statement-flat.
s/            foreach \(\$probe as \$row\) \{\n\s*context_helper::preload_from_record\(clone \$row\);\n\s*\}\n//;
