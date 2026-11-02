# Ask a predicate class that is not there.
s/(\$predicate = \$predicate \?\? self::PREDICATE;\n        if \(!class_exists\(\$predicate\)\) \{\n            \/\/ Fail closed: no predicate, no public category.\n            return \[\];)/\$predicate = \$predicate ?? self::PREDICATE;\n        if (false) {\n            return [];/;
