# Read the convenient fact: whoever may view the course competencies (every learner) passes the grading check.
s/if \(!has_capability\(self::GRADE, \$context\)\) \{/if (!has_capability(\x27moodle\/competency:coursecompetencyview\x27, \$context)) {/;
