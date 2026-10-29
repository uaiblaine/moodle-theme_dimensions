# A look offered without a save of the settings page never gets its cohort at the first choice.
s/(if \(\$chosen !== flavourchoice::SITE_DEFAULT && !isset\(\$ours\[\$chosen\]\)\) \{)\s+self::provision\(\);\s+\$ours = self::cohorts\(\);/$1/;
