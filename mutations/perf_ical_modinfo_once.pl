# Core's per-event shape again: each event asks modinfo for its own course in date order, by bare id, so twelve
# interleaved courses evict one another from modinfo's static cache and each is built again, a course row read each time.
s/if \(isset\(\$hidden\[\$key\]\)\) \{\n(\s+)continue;\n\s+\}/if (!empty(\$event->modulename) && empty(get_fast_modinfo(\$event->courseid, \$userid)->get_instances_of(\$event->modulename)[\$event->instance]->uservisible)) {\n${1}continue;\n${1}}/;
