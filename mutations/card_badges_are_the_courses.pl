# Draw the category's badges on the card instead of the course's own.
s/foreach \(array_slice\(\$values->brasoes, 0, self::MAX_BADGES\) as \$file\) \{/foreach (array_slice(\\theme_dimensions\\local\\category\\branding::badges((int) (\$course->category ?? 0)), 0, self::MAX_BADGES) as \$file) {/;
