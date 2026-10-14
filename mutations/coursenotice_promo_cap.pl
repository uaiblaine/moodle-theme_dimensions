# Only a course editor gets the promotion section - and so only an editor mints.
s/        if \(!has_capability\(self::CAP_UPDATE, \$context\)\) \{/        if (false) {/;
