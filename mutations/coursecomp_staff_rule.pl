# Read the convenient fact: whoever may view the course competencies (every learner) gets the class distribution.
s/return has_capability\(self::GRADE, \$context\) \|\| has_capability\(self::MANAGE, \$context\);/return has_capability(\x27moodle\/competency:coursecompetencyview\x27, \$context);/;
