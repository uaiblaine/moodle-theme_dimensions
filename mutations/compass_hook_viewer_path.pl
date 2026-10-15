# Answer block_compass's hook from the landing page values directly, past theme_dimensions_course_badges()
# and so past its viewer filter: every course the block asks about gets its crests, whoever is reading.
s/: theme_dimensions_course_badges\(\$ids\);/: (static fn(array \$v): array => array_combine(array_keys(\$v), array_map(static fn(int \$id, \\stdClass \$one): array => \\theme_dimensions\\output\\category_card::crests((object) ['id' => \$id], \$one), array_keys(\$v), \$v)))(\\theme_dimensions\\local\\landingfields::values_many(\$ids, false));/;
