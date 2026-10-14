# Gate the enrichment batch on an enrolment instead of on discoverability. A category page is
# mostly courses the viewer is not enrolled in, so the batch answers for almost none of the
# cards it was asked about and every one of them keeps a skeleton that never resolves.
s/if \(\\core_course_category::can_view_course_info\(\$course\)\) \{/if (is_enrolled(\\core\\context\\course::instance((int) \$courseid))) {/;
