# Hand an older picker, which has no choice of ratios, the recommended ratio: it would lock a free field to it.
s/        if \(isset\(\$profileoptions\['cropaspectratios'\]\) && !self::offers_ratio_choices\(\$element\)\) \{\n            unset\(\$profileoptions\['cropaspectratios'\], \$profileoptions\['cropaspectratio'\]\);\n        \}\n//;
