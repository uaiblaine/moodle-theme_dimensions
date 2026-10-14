# Hand every unrelated unlisted course to the plugin's per-course probe, on both page-cutting
# paths: the request costs statements proportional to the unlisted count instead of a bounded fifty.
s/\$probe = \$(relatedunlisted|related) \+ array_slice\(\$unrelated, 0, self::PROBE_CAP, true\);/\$probe = \$$1 + \$unrelated;/g;
