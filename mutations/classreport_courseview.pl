# Open the class report without the course's competencies (coursecompetencyview or coursecompetencymanage).
s/if \(!has_any_capability\(self::COURSEVIEW, \$context\)\) \{/if (false) {/;
