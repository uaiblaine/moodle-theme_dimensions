# A look cohort is made beside the cohort of another owner already holding its idnumber.
s/if \(\$DB->record_exists\('cohort', \['idnumber' => self::idnumber\(\$flavourid\)\]\)\) \{/if (false) {/;
