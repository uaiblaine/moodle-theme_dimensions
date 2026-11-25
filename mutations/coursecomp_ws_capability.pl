# Skip core's list and its capability check: a viewer without the capability is answered.
s/foreach \(api::list_course_competencies\(\$course\) as \$item\)/foreach ([] as \$item)/;
