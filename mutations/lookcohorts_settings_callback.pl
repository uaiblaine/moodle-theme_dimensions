# Saving the offered looks provisions and empties no cohort.
s/(function theme_dimensions_sync_look_cohorts\(\): void \{.*?)\\theme_dimensions\\local\\lookcohorts::provision\(\);/$1/s;
