# Treat every viewer as a learner rated in the course: the teacher gets learner figures of their own.
s/return is_enrolled\(\$context, \$USER, self::GRADABLE\)\n/return true\n/;
